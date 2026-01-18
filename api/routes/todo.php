<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../dbconn.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

/*
  NOTE about your filter names:
  JS sends: distance_min, distance_max, elev_min, elev_max, pavement_type
  In DB you currently store route type in routes.activity_type (you used it in public.php)
  So pavement_type -> r.activity_type
*/

// Build filters
$where = [];
$params = [$user_id];
$types  = "i";

if (!empty($_GET['distance_min'])) {
  $where[] = 'CAST(r.distance_km AS DECIMAL(10,3)) >= ?';
  $params[] = (float)$_GET['distance_min'];
  $types .= 'd';
}
if (!empty($_GET['distance_max'])) {
  $where[] = 'CAST(r.distance_km AS DECIMAL(10,3)) <= ?';
  $params[] = (float)$_GET['distance_max'];
  $types .= 'd';
}
if (!empty($_GET['elev_min'])) {
  $where[] = 'r.elevation_gain_m >= ?';
  $params[] = (int)$_GET['elev_min'];
  $types .= 'i';
}
if (!empty($_GET['elev_max'])) {
  $where[] = 'r.elevation_gain_m <= ?';
  $params[] = (int)$_GET['elev_max'];
  $types .= 'i';
}
if (!empty($_GET['pavement_type'])) {
  $where[] = 'r.activity_type = ?';
  $params[] = (string)$_GET['pavement_type'];
  $types .= 's';
}

$whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

$sql = "
SELECT r.*, 1 AS is_saved
FROM saved_routes s
JOIN routes r ON r.id = s.route_id
WHERE s.user_id = ?
$whereSql
ORDER BY s.created_at DESC
LIMIT 200
";

$stmt = $connection->prepare($sql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $connection->error]);
  exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// Favourite ids (for this user)
$fav_ids = [];
$fav_q = $connection->prepare("SELECT route_id FROM favorites WHERE user_id = ?");
$fav_q->bind_param("i", $user_id);
$fav_q->execute();
$fav_res = $fav_q->get_result();
while ($f = $fav_res->fetch_assoc()) { $fav_ids[(int)$f['route_id']] = 1; }
$fav_q->close();

$routes = [];
while ($row = $res->fetch_assoc()) {
  $row['is_saved'] = 1;
  $row['is_favourited'] = isset($fav_ids[(int)$row['id']]) ? 1 : 0;
  $routes[] = $row;
}

echo json_encode(['success' => true, 'routes' => $routes]);
