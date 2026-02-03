<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'path' => '/TrailForgeX',   // or '/' if you want it site-wide
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}