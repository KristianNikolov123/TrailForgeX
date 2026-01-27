<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/dbconn.php';
require_once __DIR__ . '/../achievements/award.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

$route_id = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
if (!$route_id) {
  echo json_encode(['success' => false, 'error' => 'Missing route_id.']);
  exit;
}

// check exists
$chk = $connection->prepare("SELECT 1 FROM saved_routes WHERE user_id = ? AND route_id = ?");
$chk->bind_param("ii", $user_id, $route_id);
$chk->execute();
$chk->store_result();
$exists = $chk->num_rows > 0;
$chk->close();

if ($exists) {
  $del = $connection->prepare("DELETE FROM saved_routes WHERE user_id = ? AND route_id = ?");
  $del->bind_param("ii", $user_id, $route_id);
  $ok = $del->execute();
  $del->close();

  echo json_encode(['success' => (bool)$ok, 'action' => 'removed']);
  exit;
} else {
  $ins = $connection->prepare("INSERT INTO saved_routes (user_id, route_id) VALUES (?, ?)");
  $ins->bind_param("ii", $user_id, $route_id);
  $ok = $ins->execute();
  $ins->close();

  // Optional achievement for first "save for later"
  $unlocked = null;
  if ($ok) {
    $unlocked = awardAchievement($connection, $user_id, 'first_saved'); // create this achievement key if you want
  }

  echo json_encode([
    'success' => (bool)$ok,
    'action' => 'added',
    'achievement_unlocked' => $unlocked
  ]);
  exit;
}
