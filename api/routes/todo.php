<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/dbconn.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

/*
  JS sends: distance_min, distance_max, elev_min, elev_max, pavement_type
  DB: routes.activity_type used as pavement_type
*/

// ----------------------------
// Pagination input
// ----------------------------
$page = (isset($_GET['page']) && ctype_digit($_GET['page'])) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

$perPage = (isset($_GET['per_page']) && ctype_digit($_GET['per_page'])) ? (int)$_GET['per_page'] : 12;
$perPage = max(1, min(50, $perPage)); // clamp 1..50

$offset = ($page - 1) * $perPage;

// ----------------------------
// Build filters (shared)
// ----------------------------
$where = [];
$paramsBase = [$user_id];
$typesBase  = "i";

if (!empty($_GET['distance_min'])) {
  $where[] = 'CAST(r.distance_km AS DECIMAL(10,3)) >= ?';
  $paramsBase[] = (float)$_GET['distance_min'];
  $typesBase .= 'd';
}
if (!empty($_GET['distance_max'])) {
  $where[] = 'CAST(r.distance_km AS DECIMAL(10,3)) <= ?';
  $paramsBase[] = (float)$_GET['distance_max'];
  $typesBase .= 'd';
}
if (!empty($_GET['elev_min'])) {
  $where[] = 'r.elevation_gain_m >= ?';
  $paramsBase[] = (int)$_GET['elev_min'];
  $typesBase .= 'i';
}
if (!empty($_GET['elev_max'])) {
  $where[] = 'r.elevation_gain_m <= ?';
  $paramsBase[] = (int)$_GET['elev_max'];
  $typesBase .= 'i';
}
if (!empty($_GET['pavement_type'])) {
  $where[] = 'r.activity_type = ?';
  $paramsBase[] = (string)$_GET['pavement_type'];
  $typesBase .= 's';
}

$whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

// ----------------------------
// 1) COUNT total items (same joins/filters)
// ----------------------------
$sqlCount = "
  SELECT COUNT(*)
  FROM saved_routes s
  JOIN routes r ON r.id = s.route_id
  WHERE s.user_id = ?
  $whereSql
";

$stmt = $connection->prepare($sqlCount);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'SQL prepare failed (count): ' . $connection->error]);
  exit;
}

$stmt->bind_param($typesBase, ...$paramsBase);
$stmt->execute();
$stmt->bind_result($totalItems);
$stmt->fetch();
$stmt->close();

$totalItems = (int)$totalItems;
$totalPages = (int)max(1, ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// ----------------------------
// 2) Fetch one page
// ----------------------------
$sql = "
  SELECT r.*, 1 AS is_saved
  FROM saved_routes s
  JOIN routes r ON r.id = s.route_id
  WHERE s.user_id = ?
  $whereSql
  ORDER BY s.created_at DESC
  LIMIT ? OFFSET ?
";

// add LIMIT/OFFSET bindings
$params = array_merge($paramsBase, [$perPage, $offset]);
$types  = $typesBase . "ii";

$stmt = $connection->prepare($sql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $connection->error]);
  exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

// ----------------------------
// Favourite ids (for this user)
// ----------------------------
$fav_ids = [];
$fav_q = $connection->prepare("SELECT route_id FROM favorites WHERE user_id = ?");
$fav_q->bind_param("i", $user_id);
$fav_q->execute();
$fav_res = $fav_q->get_result();
while ($f = $fav_res->fetch_assoc()) {
  $fav_ids[(int)$f['route_id']] = 1;
}
$fav_q->close();

// ----------------------------
// Build routes response
// ----------------------------
$routes = [];
while ($row = $res->fetch_assoc()) {
  $row['is_saved'] = 1;
  $row['is_favourited'] = isset($fav_ids[(int)$row['id']]) ? 1 : 0;
  $routes[] = $row;
}

// ----------------------------
// Output
// ----------------------------
echo json_encode([
  'success' => true,
  'routes' => $routes,
  'pagination' => [
    'page' => (int)$page,
    'per_page' => (int)$perPage,
    'total_items' => (int)$totalItems,
    'total_pages' => (int)$totalPages,
  ]
]);
