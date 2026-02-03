<?php
require_once __DIR__ . '/../../includes/bootstrap.php'; // adjust path as needed

header('Content-Type: application/json');
echo json_encode([
  'logged_in' => !empty($_SESSION['user_id'])
]);
