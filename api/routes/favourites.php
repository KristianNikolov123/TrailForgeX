<?php
session_start();
header('Content-Type: application/json');

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
// Join with routes to get info for listings
$sql = "SELECT r.*, f.created_at as favourited_at FROM favorites f JOIN routes r ON r.id = f.route_id WHERE f.user_id = ? ORDER BY f.created_at DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$favs = [];
while($row = $result->fetch_assoc()) {
    $favs[] = $row;
}
$stmt->close();
$mysqli->close();

echo json_encode(['success' => true, 'routes' => $favs]);


