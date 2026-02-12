<?php
require_once '../../includes/bootstrap.php';
require_once '../../includes/dbconn.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code'] ?? '');

$user_id = $_SESSION['pending_verify_user_id'] ?? null;

if (!$user_id || !$code) {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

$stmt = $connection->prepare("
    SELECT verification_code, verification_expires
    FROM users WHERE id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($db_code, $expires);
$stmt->fetch();
$stmt->close();

if ($db_code !== $code) {
    echo json_encode(['success'=>false,'error'=>'Invalid code']);
    exit;
}

if (strtotime($expires) < time()) {
    echo json_encode(['success'=>false,'error'=>'Code expired']);
    exit;
}

$stmt = $connection->prepare("
    UPDATE users
    SET is_verified = 1,
        verification_code = NULL,
        verification_expires = NULL
    WHERE id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();

unset($_SESSION['pending_verify_user_id']);
$_SESSION['user_id'] = $user_id;

echo json_encode(['success'=>true]);
