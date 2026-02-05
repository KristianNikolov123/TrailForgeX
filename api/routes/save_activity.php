<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/dbconn.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'Not authenticated']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
  exit;
}

$route_id = (int)($data['route_id'] ?? 0);
$duration_sec = (int)($data['duration_sec'] ?? 0);
$distance_m = (int)($data['distance_m'] ?? 0);
$route_completed = (int)($data['route_completed'] ?? 0); // we’ll use it optionally

if ($route_id <= 0 || $duration_sec <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Bad payload']);
  exit;
}

$duration_min = (int)ceil($duration_sec / 60);
$distance_km = round($distance_m / 1000, 2);

// For now: copy planned elevation from routes, or 0 if null
$elevation_gain_m = 0;
$meta = $connection->prepare("SELECT elevation_gain_m FROM routes WHERE id = ?");
$meta->bind_param("i", $route_id);
$meta->execute();
$res = $meta->get_result();
if ($row = $res->fetch_assoc()) {
  $elevation_gain_m = (int)($row['elevation_gain_m'] ?? 0);
}
$meta->close();

// Insert activity
$stmt = $connection->prepare("
  INSERT INTO activities (user_id, route_id, duration_min, distance_km, elevation_gain_m)
  VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iiidi", $user_id, $route_id, $duration_min, $distance_km, $elevation_gain_m);

try {
  $ok = $stmt->execute();
  $activity_id = $stmt->insert_id;
  $stmt->close();

  if (!$ok) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Insert failed']);
    exit;
  }

  // OPTIONAL: if completed => also mark done automatically
  // If you want this, paste your mark_done.php/table and I’ll wire it properly.
  // For now we’ll leave it out to avoid mismatching your schema.

  echo json_encode(['success'=>true,'activity_id'=>$activity_id]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage() ?: 'Server error']);
  exit;
}
