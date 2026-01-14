<?php

header('Content-Type: application/json');

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
    $where[] = 'distance_km >= ?';
    $params[] = floatval($_GET['distance_min']);
    $types .= 'd';
}
if (!empty($_GET['distance_max'])) {
    $where[] = 'distance_km <= ?';
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
echo json_encode(['success' => true, 'routes' => $publics]);




