<?php
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/dbconn.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
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

if ($connection->connect_errno) {
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

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $connection->prepare(
  'INSERT INTO users (username, email, password_hash, is_verified) VALUES (?, ?, ?, 0)'
);
$stmt->bind_param('sss', $username, $email, $hashed);
$stmt->execute();

$user_id = $stmt->insert_id;
$stmt->close();

$code = random_int(100000, 999999);
$expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes

$stmt = $connection->prepare('UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?');
$stmt->bind_param('ssi', $code, $expires, $user_id);
$stmt->execute();
$stmt->close();

$result = sendVerificationEmail($email, $code);
if ($result !== true) {
    echo json_encode(['success' => false, 'error' => $result]);
    exit;
}


mysqli_close($connection);

$_SESSION['pending_verify_user_id'] = $user_id;

echo json_encode([
    'success' => true,
    'verify_required' => true
]);
exit;
