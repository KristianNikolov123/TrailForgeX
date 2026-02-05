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

require_once __DIR__ . '/../../includes/dbconn.php';

$duration_min = (int)($_POST['duration_min'] ?? 0);
$distance_km  = (float)($_POST['distance_km'] ?? 0);

if ($duration_min <= 0) $duration_min = 1;
if ($distance_km < 0) $distance_km = 0;

$elevation_gain_m = 0; // you can compute later if you want

$stmt = $connection->prepare("
  INSERT INTO activities (user_id, route_id, duration_min, distance_km, elevation_gain_m, completed_at)
  VALUES (?, NULL, ?, ?, ?, NOW())
");
$stmt->bind_param("iidi", $user_id, $duration_min, $distance_km, $elevation_gain_m);

if (!$stmt->execute()) {
  echo json_encode(['success' => false, 'error' => $stmt->error]);
  exit;
}
$stmt->close();

echo json_encode(['success' => true]);
