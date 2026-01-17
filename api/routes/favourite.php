<?php
session_start();
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// route_id from form or JSON
$route_id = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
if (!$route_id) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data['route_id'])) {
        $route_id = (int)$data['route_id'];
    }
}
if (!$route_id) {
    echo json_encode(['success' => false, 'error' => 'Missing route_id.']);
    exit;
}

// DB
require_once __DIR__ . '/../../dbconn.php';

// dbconn.php should define $connection (mysqli)
if (!isset($connection) || !($connection instanceof mysqli) || $connection->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Check already favourited
$query = $connection->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND route_id = ?");
if (!$query) {
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $connection->error]);
    exit;
}
$query->bind_param('ii', $user_id, $route_id);
$query->execute();
$query->store_result();
$already_fav = $query->num_rows > 0;
$query->close();

if ($already_fav) {
    $del = $connection->prepare("DELETE FROM favorites WHERE user_id = ? AND route_id = ?");
    if (!$del) {
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $connection->error]);
        exit;
    }
    $del->bind_param('ii', $user_id, $route_id);
    $ok = $del->execute();
    $del->close();
    echo json_encode(['success' => (bool)$ok, 'action' => 'removed']);
} else {
    $ins = $connection->prepare("INSERT INTO favorites (user_id, route_id) VALUES (?, ?)");
    if (!$ins) {
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $connection->error]);
        exit;
    }
    $ins->bind_param('ii', $user_id, $route_id);
    $ok = $ins->execute();
    $ins->close();
    echo json_encode(['success' => (bool)$ok, 'action' => 'added']);
}

$connection->close();
