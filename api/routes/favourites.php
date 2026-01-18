<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'DB unavailable.']);
    exit;
}

// Read filters (optional)
$distance_min = (isset($_GET['distance_min']) && $_GET['distance_min'] !== '') ? floatval($_GET['distance_min']) : null;
$distance_max = (isset($_GET['distance_max']) && $_GET['distance_max'] !== '') ? floatval($_GET['distance_max']) : null;

$elev_min = (isset($_GET['elev_min']) && $_GET['elev_min'] !== '') ? floatval($_GET['elev_min']) : null;
$elev_max = (isset($_GET['elev_max']) && $_GET['elev_max'] !== '') ? floatval($_GET['elev_max']) : null;

$pavement = (isset($_GET['pavement_type']) && $_GET['pavement_type'] !== '') ? $_GET['pavement_type'] : null;

// Build query dynamically
$sql = "SELECT r.*, f.created_at as favourited_at
        FROM favorites f
        JOIN routes r ON r.id = f.route_id
        WHERE f.user_id = ?";

$types = "i";
$params = [$user_id];

// IMPORTANT: if distance_km is stored as TEXT, CAST it.
// If it's numeric already, you can remove CAST(...)
if ($distance_min !== null) { $sql .= " AND CAST(r.distance_km AS DECIMAL(10,3)) >= ?"; $types .= "d"; $params[] = $distance_min; }
if ($distance_max !== null) { $sql .= " AND CAST(r.distance_km AS DECIMAL(10,3)) <= ?"; $types .= "d"; $params[] = $distance_max; }

if ($elev_min !== null) { $sql .= " AND r.elevation_gain_m >= ?"; $types .= "d"; $params[] = $elev_min; }
if ($elev_max !== null) { $sql .= " AND r.elevation_gain_m <= ?"; $types .= "d"; $params[] = $elev_max; }

if ($pavement !== null) { $sql .= " AND r.pavement_type = ?"; $types .= "s"; $params[] = $pavement; }

$sql .= " ORDER BY f.created_at DESC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

// bind_param with variable arguments
// bind_param requires references, so we build a ref array
$bind = [];
$bind[] = $types;

for ($i = 0; $i < count($params); $i++) {
    $bind[] = &$params[$i];
}

call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();

$result = $stmt->get_result();
$favs = [];
// build list of saved (pinned) route IDs outside the loop
$pinned = [];
$pins = $mysqli->prepare("SELECT route_id FROM saved_routes WHERE user_id = ?");
$pins->bind_param("i", $user_id);
$pins->execute();
$pres = $pins->get_result();
while ($pr = $pres->fetch_assoc()) { $pinned[$pr['route_id']] = 1; }
$pins->close();

while ($row = $result->fetch_assoc()) {
    $row['is_favourited'] = 1; // Always true in user's favourite list
    $row['is_saved'] = isset($pinned[$row['id']]) ? 1 : 0;
    $favs[] = $row;
}
$stmt->close();
$mysqli->close();

echo json_encode(['success' => true, 'routes' => $favs]);
