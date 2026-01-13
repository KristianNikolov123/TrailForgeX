<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = @json_decode(file_get_contents('php://input'), true);
if (!$data && !empty($_POST)) $data = $_POST;
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

$mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
$stmt = $mysqli->prepare("SELECT id, password_hash FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}
$stmt->bind_result($id, $password_hash);
$stmt->fetch();
// For demo: SIMPLE plaintext, real app should use password_verify()
if ($password === $password_hash) {
    $_SESSION['user_id'] = $id;
    echo json_encode(['success' => true, 'user_id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
}
$stmt->close();
$mysqli->close();

