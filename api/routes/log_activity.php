<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'Not logged in']);
  exit;
}

$user_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../../dbconn.php';

$route_id = (int)($_POST['route_id'] ?? 0);
$duration_min = (int)($_POST['duration_min'] ?? 0);

if (!$route_id) {
  echo json_encode(['success' => false, 'error' => 'Missing route_id']);
  exit;
}

// fetch distance + elevation from routes
$stmt = $connection->prepare("
  SELECT distance_km, elevation_gain_m
  FROM routes
  WHERE id = ?
");
if (!$stmt) {
  echo json_encode(['success'=>false,'error'=>$connection->error]);
  exit;
}

$stmt->bind_param("i", $route_id);
$stmt->execute();
$stmt->bind_result($distance_km, $elevation_gain_m);
$stmt->fetch();
$stmt->close();

$distance_km = (float)($distance_km ?? 0);
$elevation_gain_m = (int)($elevation_gain_m ?? 0);

// Validate route exists (and optionally belongs to user)
$chk = $connection->prepare("SELECT id FROM routes WHERE id = ?");
$chk->bind_param("i", $route_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
  $chk->close();
  echo json_encode(['success' => false, 'error' => 'Invalid route_id (route does not exist).']);
  exit;
}
$chk->close();


// insert activity
$stmt = $connection->prepare("
  INSERT INTO activities
  (user_id, route_id, duration_min, distance_km, elevation_gain_m, completed_at)
  VALUES (?, ?, ?, ?, ?, NOW())
");
if (!$stmt) {
  echo json_encode(['success'=>false,'error'=>$connection->error]);
  exit;
}

$stmt->bind_param(
  "iiidi",
  $user_id,
  $route_id,
  $duration_min,
  $distance_km,
  $elevation_gain_m
);

if (!$stmt->execute()) {
  echo json_encode(['success'=>false,'error'=>$stmt->error]);
  exit;
}
$stmt->close();

// award achievements
require_once __DIR__ . '/../achievements/award_by_metric.php';

$u1 = tf_award_by_metric($connection, $user_id, 'run_count');
$u2 = tf_award_by_metric($connection, $user_id, 'run_distance_km');
$u3 = tf_award_by_metric($connection, $user_id, 'run_elevation_m');

$unlocked = array_merge($u1, $u2, $u3);

echo json_encode([
  'success' => true,
  'achievement_unlocked' => $unlocked ? $unlocked[0] : null,
  'achievements_unlocked' => $unlocked
]);

$connection->close();
