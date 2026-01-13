<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'DB unavailable.']);
    exit;
}
$route_id = isset($_GET['route_id']) ? intval($_GET['route_id']) : 0;
if (!$route_id) {
    echo json_encode(['success' => false, 'error' => 'Missing route_id']);
    exit;
}
$res = $mysqli->query("SELECT latitude, longitude, point_order FROM route_points WHERE route_id = $route_id ORDER BY point_order ASC");
$points = [];
while ($row = $res->fetch_assoc()) {
    $points[] = [floatval($row['latitude']), floatval($row['longitude'])];
}
$mysqli->close();
echo json_encode(['success' => true, 'coordinates' => $points]);

