<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

// Accept JSON or form POST
parse_str(file_get_contents('php://input'), $vars);
$route_id = isset($_POST['route_id']) ? intval($_POST['route_id']) : (isset($vars['route_id']) ? intval($vars['route_id']) : 0);

if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$route_id) {
    echo json_encode(['success' => false, 'error' => 'Missing route_id or invalid request.']);
    exit;
}

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$mysqli->query("UPDATE routes SET is_public = 1 WHERE id = $route_id AND creator_id = $user_id");

$check = $mysqli->prepare("SELECT 1 FROM route_shares WHERE user_id = ? AND route_id = ?");
$check->bind_param('ii', $user_id, $route_id);
$check->execute();
$check->store_result();
$already_shared = $check->num_rows > 0;
$check->close();

if (!$already_shared) {
    $ins = $mysqli->prepare("INSERT INTO route_shares (user_id, route_id) VALUES (?, ?)");
    $ins->bind_param('ii', $user_id, $route_id);
    $ins->execute();
    $ins->close();
}
$mysqli->close();
echo json_encode(['success' => true, 'action' => 'shared']);
