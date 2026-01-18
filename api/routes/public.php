<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
    exit;
}
// Build filters
$where = ['is_public = 1'];
$params = [];
$types = '';

if (!empty($_GET['distance_min'])) {
    $where[] = 'CAST(distance_km AS DECIMAL(10,3)) >= ?';
    $params[] = floatval($_GET['distance_min']);
    $types .= 'd';
}
if (!empty($_GET['distance_max'])) {
    $where[] = 'CAST(distance_km AS DECIMAL(10,3)) <= ?';
    $params[] = floatval($_GET['distance_max']);
    $types .= 'd';
}
if (!empty($_GET['elev_min'])) {
    $where[] = 'elevation_gain_m >= ?';
    $params[] = intval($_GET['elev_min']);
    $types .= 'i';
}
if (!empty($_GET['elev_max'])) {
    $where[] = 'elevation_gain_m <= ?';
    $params[] = intval($_GET['elev_max']);
    $types .= 'i';
}
if (!empty($_GET['pavement_type'])) {
    $where[] = 'activity_type = ?';
    $params[] = $_GET['pavement_type'];
    $types .= 's';
}
$where_clause = implode(' AND ', $where);

$query = "SELECT * FROM routes WHERE $where_clause ORDER BY created_at DESC LIMIT 100";
$stmt = $mysqli->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$publics = [];
while($row = $res->fetch_assoc()) {
    $publics[] = $row;
}
$stmt->close();
$mysqli->close();
session_start();
$fav_ids = [];
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $mysqli2 = new mysqli('localhost', 'root', '', 'trailforgex');
    if (!$mysqli2->connect_errno) {
        $sqlfavs = "SELECT route_id FROM favorites WHERE user_id = ?";
        $s2 = $mysqli2->prepare($sqlfavs);
        $s2->bind_param('i', $user_id);
        $s2->execute();
        $res2 = $s2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $fav_ids[$r['route_id']] = 1;
        }
        $s2->close();
        $mysqli2->close();
    }
}
// Get all saved (pinned) route IDs
$saved_ids = [];
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $mys = new mysqli('localhost', 'root', '', 'trailforgex');
    if (!$mys->connect_errno) {
        $sqlsaved = "SELECT route_id FROM saved_routes WHERE user_id = ?";
        $s3 = $mys->prepare($sqlsaved);
        $s3->bind_param('i', $user_id);
        $s3->execute();
        $res3 = $s3->get_result();
        while ($s = $res3->fetch_assoc()) {
            $saved_ids[$s['route_id']] = 1;
        }
        $s3->close();
        $mys->close();
    }
}
foreach ($publics as &$row) {
    $row['is_favourited'] = isset($fav_ids[$row['id']]) ? 1 : 0;
    $row['is_saved'] = isset($saved_ids[$row['id']]) ? 1 : 0;
}
echo json_encode(['success' => true, 'routes' => $publics]);
