<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/dbconn.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
// safe next
function safe_next(): string {
  $next = $_GET['next'] ?? $_SESSION['oauth_next'] ?? 'generate.php';
  if (!preg_match('/^[a-zA-Z0-9_\-\/]+\.php$/', $next)) $next = 'generate.php';
  return $next;
}

function login_user(int $user_id, string $next) {
  $_SESSION['user_id'] = $user_id;
  header('Location: /trailforgex/' . ltrim($next, '/'));
  exit;
}
