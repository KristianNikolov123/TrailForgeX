<?php
require_once __DIR__ . '/_oauth_bootstrap.php';
require_once __DIR__ . '/oauth_config.php';

use League\OAuth2\Client\Provider\Facebook;

if (empty($_GET['state']) || empty($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
  unset($_SESSION['oauth2state']);
  die('Invalid OAuth state');
}

$provider = new Facebook([
    'clientId'     => $_ENV['FACEBOOK_APP_ID'],
    'clientSecret' => $_ENV['FACEBOOK_APP_SECRET'],
    'redirectUri'  => $_ENV['FACEBOOK_REDIRECT_URI'],
    'graphApiVersion' => 'v20.0',
]);
  

try {
  $token = $provider->getAccessToken('authorization_code', [
    'code' => $_GET['code']
  ]);

  // Request email too (FB sometimes returns null if not granted/available)
  $owner = $provider->getResourceOwner($token);

  $sub   = $owner->getId();          // Facebook user id
  $name  = method_exists($owner, 'getName') ? ($owner->getName() ?: '') : '';
  $email = $owner->getEmail();
  if (!$email) {
    $email = null; // don’t crash
  }
  // 1) Find by provider+sub
  $stmt = $connection->prepare("SELECT id FROM users WHERE oauth_provider='facebook' AND oauth_sub=? LIMIT 1");
  $stmt->bind_param("s", $sub);
  $stmt->execute();
  $stmt->bind_result($uid);
  if ($stmt->fetch()) {
    $stmt->close();
    login_user((int)$uid, safe_next());
  }
  $stmt->close();

  // 2) Optional: link by email if available
  if ($email) {
    $stmt = $connection->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($existing);
    if ($stmt->fetch()) {
      $stmt->close();
      $stmt = $connection->prepare("UPDATE users SET oauth_provider='facebook', oauth_sub=?, oauth_email=? WHERE id=?");
      $stmt->bind_param("ssi", $sub, $email, $existing);
      $stmt->execute();
      $stmt->close();
      login_user((int)$existing, safe_next());
    }
    $stmt->close();
  }

  // 3) Create a new user
  // If email missing, create a synthetic one so DB constraints won’t break.
  if (!$email) {
    $email = "fb_" . $sub . "@noemail.local";
  }

  $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($name ?: 'fbuser'));
  if (!$username) $username = 'fbuser';
  $username .= rand(1000, 9999);

  $stmt = $connection->prepare("
    INSERT INTO users (username, email, password_hash, oauth_provider, oauth_sub, oauth_email)
    VALUES (?, ?, '', 'facebook', ?, ?)
  ");
  $stmt->bind_param("ssss", $username, $email, $sub, $email);
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  login_user((int)$newId, safe_next());

} catch (Throwable $e) {
  error_log("Facebook OAuth error: " . $e->getMessage());
  die("OAuth failed");
}
