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

require_once '../../includes/dbconn.php';
if ($connection->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
$stmt = $connection->prepare("SELECT id, password_hash, is_verified FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}
$stmt->bind_result($id, $password_hash, $is_verified);
$stmt->fetch();
if (!$is_verified) {
    echo json_encode(['success' => false, 'error' => 'Email not verified']);
    exit;
}
if ($password_hash === null) {
    echo json_encode([
        'success' => false,
        'error' => 'This account uses Google login. Please continue with Google.'
    ]);
    exit;
}

$isBcrypt = is_string($password_hash) && preg_match('/^\$2[aby]\$/', $password_hash);

if ($isBcrypt) {
    $ok = password_verify($password, $password_hash);
} else {
    $ok = hash_equals((string)$password_hash, (string)$password);

    if ($ok) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $up = $connection->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $up->bind_param("si", $newHash, $id);
        $up->execute();
        $up->close();
    }
}

if ($ok) {
    $_SESSION['user_id'] = $id;
    echo json_encode(['success' => true, 'user_id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
}

$stmt->close();
mysqli_close($connection);


