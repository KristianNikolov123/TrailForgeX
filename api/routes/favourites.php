<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'Not logged in.']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
  echo json_encode(['success' => false, 'error' => 'DB unavailable.']);
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
$distance_min = (isset($_GET['distance_min']) && $_GET['distance_min'] !== '') ? (float)$_GET['distance_min'] : null;
$distance_max = (isset($_GET['distance_max']) && $_GET['distance_max'] !== '') ? (float)$_GET['distance_max'] : null;

$elev_min = (isset($_GET['elev_min']) && $_GET['elev_min'] !== '') ? (int)$_GET['elev_min'] : null;
$elev_max = (isset($_GET['elev_max']) && $_GET['elev_max'] !== '') ? (int)$_GET['elev_max'] : null;

$pavement = (isset($_GET['pavement_type']) && $_GET['pavement_type'] !== '') ? (string)$_GET['pavement_type'] : null;

/* IMPORTANT:
   In your other endpoints you use routes.activity_type for pavement_type.
   Your old favourites.php used r.pavement_type (likely wrong column).
   We'll use r.activity_type here to match your filters + public.php.
*/

/* -------------------------
   Build WHERE (shared)
--------------------------*/
$where = "WHERE f.user_id = ?";
$types = "i";
$params = [$user_id];

if ($distance_min !== null) { $where .= " AND CAST(r.distance_km AS DECIMAL(10,3)) >= ?"; $types .= "d"; $params[] = $distance_min; }
if ($distance_max !== null) { $where .= " AND CAST(r.distance_km AS DECIMAL(10,3)) <= ?"; $types .= "d"; $params[] = $distance_max; }

if ($elev_min !== null) { $where .= " AND r.elevation_gain_m >= ?"; $types .= "i"; $params[] = $elev_min; }
if ($elev_max !== null) { $where .= " AND r.elevation_gain_m <= ?"; $types .= "i"; $params[] = $elev_max; }

if ($pavement !== null) { $where .= " AND r.activity_type = ?"; $types .= "s"; $params[] = $pavement; }

/* -------------------------
   1) COUNT total items
--------------------------*/
$countSql = "
  SELECT COUNT(*)
  FROM favorites f
  JOIN routes r ON r.id = f.route_id
  $where
";

$stmt = $mysqli->prepare($countSql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed (count): ' . $mysqli->error]);
  exit;
}

$bind = [$types];
for ($i=0; $i<count($params); $i++) $bind[] = &$params[$i];
call_user_func_array([$stmt, 'bind_param'], $bind);

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
$sql = "
  SELECT r.*, f.created_at AS favourited_at
  FROM favorites f
  JOIN routes r ON r.id = f.route_id
  $where
  ORDER BY f.created_at DESC
  LIMIT ? OFFSET ?
";

$types2 = $types . "ii";
$params2 = array_merge($params, [$per_page, $offset]);

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed (list): ' . $mysqli->error]);
  exit;
}

$bind2 = [$types2];
for ($i=0; $i<count($params2); $i++) $bind2[] = &$params2[$i];
call_user_func_array([$stmt, 'bind_param'], $bind2);

$stmt->execute();
$result = $stmt->get_result();

/* pinned route ids */
$pinned = [];
$pins = $mysqli->prepare("SELECT route_id FROM saved_routes WHERE user_id = ?");
$pins->bind_param("i", $user_id);
$pins->execute();
$pres = $pins->get_result();
while ($pr = $pres->fetch_assoc()) { $pinned[(int)$pr['route_id']] = 1; }
$pins->close();

$routes = [];
while ($row = $result->fetch_assoc()) {
  $row['is_favourited'] = 1;
  $row['is_saved'] = isset($pinned[(int)$row['id']]) ? 1 : 0;
  $routes[] = $row;
}
$stmt->close();
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
