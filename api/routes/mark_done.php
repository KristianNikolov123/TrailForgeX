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

// Validate route exists + fetch distance/elevation
$stmt = $connection->prepare("
  SELECT distance_km, elevation_gain_m
  FROM routes
  WHERE id = ?
");
$stmt->bind_param("i", $route_id);
$stmt->execute();
$stmt->bind_result($distance_km, $elevation_gain_m);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
  echo json_encode(['success' => false, 'error' => 'Invalid route_id (route does not exist).']);
  exit;
}

$distance_km = (float)($distance_km ?? 0);
$elevation_gain_m = (int)($elevation_gain_m ?? 0);

// Insert activity (= "done")
$stmt = $connection->prepare("
  INSERT INTO activities
    (user_id, route_id, duration_min, distance_km, elevation_gain_m, completed_at)
  VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiidi", $user_id, $route_id, $duration_min, $distance_km, $elevation_gain_m);

if (!$stmt->execute()) {
  echo json_encode(['success' => false, 'error' => $stmt->error]);
  exit;
}
$stmt->close();

// Remove from To-Do (saved_routes) so it disappears from that tab
$del = $connection->prepare("DELETE FROM saved_routes WHERE user_id = ? AND route_id = ?");
$del->bind_param("ii", $user_id, $route_id);
$del->execute();
$del->close();

// Award run achievements
require_once __DIR__ . '/../achievements/award_by_metric.php';
$u1 = tf_award_by_metric($connection, $user_id, 'run_count');
$u2 = tf_award_by_metric($connection, $user_id, 'run_distance_km');
$u3 = tf_award_by_metric($connection, $user_id, 'run_elevation_m');

echo json_encode([
  'success' => true,
  'logged' => [
    'route_id' => $route_id,
    'duration_min' => $duration_min,
    'distance_km' => $distance_km,
    'elevation_gain_m' => $elevation_gain_m
  ],
  'achievements_unlocked' => array_merge($u1, $u2, $u3)
]);