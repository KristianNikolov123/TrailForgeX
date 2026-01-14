<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = @json_decode(file_get_contents('php://input'), true);
if (!$data && !empty($_POST)) $data = $_POST;
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$username || !$email || !$password) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

require_once '../../dbconn.php';
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
// Check for duplicate username or email
$stmt = $connection->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
    exit;
}
// For demo: store password as plain text (INSECURE), use password_hash() in prod
$stmt = $connection->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $username, $email, $password);
$stmt->execute();
$user_id = $stmt->insert_id;
$stmt->close();
mysqli_close($connection);
session_start();
$_SESSION['user_id'] = $user_id;
echo json_encode(['success' => true, 'user_id' => $user_id]);


