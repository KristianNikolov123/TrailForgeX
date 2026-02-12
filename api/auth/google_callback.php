<?php
require_once __DIR__ . '/_oauth_bootstrap.php';
require_once __DIR__ . '/oauth_config.php';

use League\OAuth2\Client\Provider\Google;

if (empty($_GET['state']) || empty($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
  unset($_SESSION['oauth2state']);
  die('Invalid OAuth state');
}


$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
    'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
  ]);
  

try {
  $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
  $owner = $provider->getResourceOwner($token);

  $sub   = $owner->getId();
  $email = $owner->getEmail();
  $name  = $owner->getName() ?: '';

  // 1) Find by provider+sub
  $stmt = $connection->prepare("SELECT id FROM users WHERE oauth_provider='google' AND oauth_sub=? LIMIT 1");
  $stmt->bind_param("s", $sub);
  $stmt->execute();
  $stmt->bind_result($uid);
  if ($stmt->fetch()) { 
    $stmt->close();
    login_user((int)$uid, safe_next());
  }
  $stmt->close();

  // 2) Optional: link by email if user exists
  $stmt = $connection->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->bind_result($existing);
  if ($stmt->fetch()) {
    $stmt->close();
    $stmt = $connection->prepare("UPDATE users SET oauth_provider='google', oauth_sub=?, oauth_email=? WHERE id=?");
    $stmt->bind_param("ssi", $sub, $email, $existing);
    $stmt->execute();
    $stmt->close();
    login_user((int)$existing, safe_next());
  }
  $stmt->close();

  // 3) Create new user
$base = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(explode('@', $email)[0]));
if (!$base) $base = 'user';

$username = $base;
$try = 0;

// ensure unique username
while (true) {
  $stmt = $connection->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();
  $exists = $stmt->num_rows > 0;
  $stmt->close();

  if (!$exists) break;
  $try++;
  $username = $base . $try; // e.g. nikolovviktor0852
  if ($try > 9999) { $username = 'user' . rand(100000,999999); break; }
}

$stmt = $connection->prepare("
  INSERT INTO users (username, email, password_hash, oauth_provider, oauth_sub, oauth_email, is_verified)
  VALUES (?, ?, NULL, 'google', ?, ?, 1)
");
$stmt->bind_param("ssss", $username, $email, $sub, $email);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

login_user((int)$newId, safe_next());


} catch (Throwable $e) {
  echo "<pre>";
  echo "OAuth error:\n";
  echo $e->getMessage();
  echo "\n\n";
  echo $e->getTraceAsString();
  echo "</pre>";
  exit;
}
