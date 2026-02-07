<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/_oauth_bootstrap.php';
require_once __DIR__ . '/oauth_config.php';

use League\OAuth2\Client\Provider\Google;

$host = $_SERVER['HTTP_HOST'] ?? '';
if (str_contains(GOOGLE_REDIRECT_URI, 'ngrok-free.dev') && $host === 'localhost') {
  die(
    "OAuth misconfiguration:\n\n" .
    "You are starting Google login on localhost,\n" .
    "but your redirect URI points to ngrok.\n\n" .
    "Open the site via:\n" .
    "https://thomas-overstraight-ouida.ngrok-free.dev/trailforgex/login_page.php"
  );
}

// Hard guard: if config isn't loaded, stop NOW with a clear message
if (!defined('GOOGLE_CLIENT_ID') || trim(GOOGLE_CLIENT_ID) === '') {
  http_response_code(500);
  exit('GOOGLE_CLIENT_ID is missing/empty. Check oauth_config.php include + value.');
}
if (!defined('GOOGLE_CLIENT_SECRET') || trim(GOOGLE_CLIENT_SECRET) === '') {
  http_response_code(500);
  exit('GOOGLE_CLIENT_SECRET is missing/empty. Check oauth_config.php.');
}
if (!defined('GOOGLE_REDIRECT_URI') || trim(GOOGLE_REDIRECT_URI) === '') {
  http_response_code(500);
  exit('GOOGLE_REDIRECT_URI is missing/empty. Check oauth_config.php.');
}

$provider = new League\OAuth2\Client\Provider\Google([
  'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
  'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
  'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
]);

$next = safe_next();
$_SESSION['oauth_next'] = $next;

$authUrl = $provider->getAuthorizationUrl([
  'scope' => ['openid', 'email', 'profile']
]);

// Debug: make sure URL contains client_id=
if (strpos($authUrl, 'client_id=') === false) {
  http_response_code(500);
  exit("Auth URL missing client_id. Generated URL:\n\n" . htmlspecialchars($authUrl));
}

$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit;
