<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();




$isHttps =
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'path' => '/TrailForgeX',   // or '/' if you want it site-wide
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}