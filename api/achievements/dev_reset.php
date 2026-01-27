<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'error'=>'Not logged in']);
  exit;
}

require_once __DIR__ . '/../../includes/dbconn.php';

$user_id = (int)$_SESSION['user_id'];
$code = $_GET['code'] ?? 'first_favourite';

$stmt = $connection->prepare("
  DELETE ua
  FROM user_achievements ua
  JOIN achievements a ON a.id = ua.achievement_id
  WHERE ua.user_id = ? AND a.code = ?
");
$stmt->bind_param('is', $user_id, $code);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

echo json_encode(['success'=>true,'deleted'=>$deleted,'code'=>$code]);
