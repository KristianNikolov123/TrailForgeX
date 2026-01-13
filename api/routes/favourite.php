<?php
session_start();
header('Content-Type: application/json');

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Check for POST with route_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['route_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing route_id or invalid request.']);
    exit;
}
$route_id = intval($_POST['route_id']);

// Create connection (update with your DB credentials)
$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Check if already favourited
$query = $mysqli->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND route_id = ?");
$query->bind_param('ii', $user_id, $route_id);
$query->execute();
$query->store_result();
$already_fav = $query->num_rows > 0;
$query->close();

if ($already_fav) {
    // Remove favourite
    $del = $mysqli->prepare("DELETE FROM favorites WHERE user_id = ? AND route_id = ?");
    $del->bind_param('ii', $user_id, $route_id);
    $result = $del->execute();
    $del->close();
    echo json_encode(['success' => $result, 'action' => 'removed']);
} else {
    // Add favourite
    $ins = $mysqli->prepare("INSERT INTO favorites (user_id, route_id) VALUES (?, ?)");
    $ins->bind_param('ii', $user_id, $route_id);
    $result = $ins->execute();
    $ins->close();
    echo json_encode(['success' => $result, 'action' => 'added']);
}
$mysqli->close();


