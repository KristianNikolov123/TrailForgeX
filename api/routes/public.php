<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
  echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
  exit;
}

/* -------------------------
   Pagination params
--------------------------*/
$page = (isset($_GET['page']) && ctype_digit($_GET['page'])) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

$per_page = (isset($_GET['per_page']) && ctype_digit($_GET['per_page'])) ? (int)$_GET['per_page'] : 12;
$per_page = max(1, min(50, $per_page)); // clamp 1..50

$offset = ($page - 1) * $per_page;

/* -------------------------
   Filters
--------------------------*/
$where = ['is_public = 1'];
$params = [];
$types = '';

if (isset($_GET['distance_min']) && $_GET['distance_min'] !== '') {
  $where[] = 'CAST(distance_km AS DECIMAL(10,3)) >= ?';
  $params[] = (float)$_GET['distance_min'];
  $types .= 'd';
}
if (isset($_GET['distance_max']) && $_GET['distance_max'] !== '') {
  $where[] = 'CAST(distance_km AS DECIMAL(10,3)) <= ?';
  $params[] = (float)$_GET['distance_max'];
  $types .= 'd';
}
if (isset($_GET['elev_min']) && $_GET['elev_min'] !== '') {
  $where[] = 'elevation_gain_m >= ?';
  $params[] = (int)$_GET['elev_min'];
  $types .= 'i';
}
if (isset($_GET['elev_max']) && $_GET['elev_max'] !== '') {
  $where[] = 'elevation_gain_m <= ?';
  $params[] = (int)$_GET['elev_max'];
  $types .= 'i';
}
if (isset($_GET['pavement_type']) && $_GET['pavement_type'] !== '') {
  $where[] = 'activity_type = ?';
  $params[] = (string)$_GET['pavement_type'];
  $types .= 's';
}

$where_clause = implode(' AND ', $where);

/* -------------------------
   1) COUNT total
--------------------------*/
$countSql = "SELECT COUNT(*) FROM routes WHERE $where_clause";
$stmt = $mysqli->prepare($countSql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed (count): ' . $mysqli->error]);
  exit;
}
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($totalItems);
$stmt->fetch();
$stmt->close();

$totalItems = (int)$totalItems;
$totalPages = ($totalItems > 0) ? (int)ceil($totalItems / $per_page) : 1;
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $per_page; }

/* -------------------------
   2) Fetch page
--------------------------*/
$listSql = "
  SELECT *
  FROM routes
  WHERE $where_clause
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($listSql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed (list): ' . $mysqli->error]);
  exit;
}

if ($types) {
  $types2 = $types . "ii";
  $params2 = array_merge($params, [$per_page, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param("ii", $per_page, $offset);
}

$stmt->execute();
$res = $stmt->get_result();
$routes = [];
while ($row = $res->fetch_assoc()) {
  $routes[] = $row;
}
$stmt->close();

/* Mark is_favourited and is_saved for logged-in user */
$fav_ids = [];
$saved_ids = [];

if (isset($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];

  // favourites
  $s2 = $mysqli->prepare("SELECT route_id FROM favorites WHERE user_id = ?");
  $s2->bind_param('i', $uid);
  $s2->execute();
  $res2 = $s2->get_result();
  while ($r = $res2->fetch_assoc()) $fav_ids[(int)$r['route_id']] = 1;
  $s2->close();

  // saved/pinned
  $s3 = $mysqli->prepare("SELECT route_id FROM saved_routes WHERE user_id = ?");
  $s3->bind_param('i', $uid);
  $s3->execute();
  $res3 = $s3->get_result();
  while ($s = $res3->fetch_assoc()) $saved_ids[(int)$s['route_id']] = 1;
  $s3->close();
}

foreach ($routes as &$row) {
  $rid = (int)$row['id'];
  $row['is_favourited'] = isset($fav_ids[$rid]) ? 1 : 0;
  $row['is_saved'] = isset($saved_ids[$rid]) ? 1 : 0;
}
unset($row);

$mysqli->close();

echo json_encode([
  'success' => true,
  'routes' => $routes,
  'pagination' => [
    'page' => (int)$page,
    'per_page' => (int)$per_page,
    'total_items' => (int)$totalItems,
    'total_pages' => (int)$totalPages
  ]
]);
