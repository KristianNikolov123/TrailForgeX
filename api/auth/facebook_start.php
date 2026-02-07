<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/_oauth_bootstrap.php';
require_once __DIR__ . '/oauth_config.php';

use League\OAuth2\Client\Provider\Facebook;

// Hard guard for “localhost start / ngrok callback” mismatch
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if (str_contains(FACEBOOK_REDIRECT_URI, 'ngrok-free.dev') && $currentHost === 'localhost') {
  http_response_code(400);
  exit("Open the site via ngrok (not localhost) because Facebook redirect is ngrok.");
}

if (!defined('FACEBOOK_APP_ID') || trim(FACEBOOK_APP_ID) === '') {
  http_response_code(500);
  exit('FACEBOOK_APP_ID missing.');
}
if (!defined('FACEBOOK_APP_SECRET') || trim(FACEBOOK_APP_SECRET) === '') {
  http_response_code(500);
  exit('FACEBOOK_APP_SECRET missing.');
}
if (!defined('FACEBOOK_REDIRECT_URI') || trim(FACEBOOK_REDIRECT_URI) === '') {
  http_response_code(500);
  exit('FACEBOOK_REDIRECT_URI missing.');
}

$provider = new League\OAuth2\Client\Provider\Facebook([
  'clientId'        => $_ENV['FACEBOOK_APP_ID'],
  'clientSecret'    => $_ENV['FACEBOOK_APP_SECRET'],
  'redirectUri'     => $_ENV['FACEBOOK_REDIRECT_URI'],
  'graphApiVersion' => 'v20.0', // can be v19/v20; any modern version is fine
]);

// store next
$next = safe_next();
$_SESSION['oauth_next'] = $next;

$authUrl = $provider->getAuthorizationUrl([
  'scope' => ['public_profile'],
]);

$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit;
