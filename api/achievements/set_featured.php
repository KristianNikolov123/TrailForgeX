<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'Not logged in']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request method']);
  exit;
}

require_once __DIR__ . '/../../includes/dbconn.php';
if (!isset($connection) || $connection->connect_errno) {
  echo json_encode(['success' => false, 'error' => 'DB connection failed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
  exit;
}

$ids = $data['badge_ids'] ?? [];
if (!is_array($ids)) $ids = [];

$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_filter($ids, fn($x) => $x > 0);
$ids = array_slice($ids, 0, 3);

$b1 = $ids[0] ?? null;
$b2 = $ids[1] ?? null;
$b3 = $ids[2] ?? null;

/**
 * Safety: only allow featuring badges the user has EARNED.
 */
if (count($ids) > 0) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $sql = "SELECT achievement_id FROM user_achievements WHERE user_id = ? AND achievement_id IN ($in)";
  $stmt = $connection->prepare($sql);

  $types = 'i' . str_repeat('i', count($ids));
  $params = array_merge([$user_id], $ids);

  $bind = [];
  $bind[] = $types;
  foreach ($params as $k => $v) $bind[] = &$params[$k];
  call_user_func_array([$stmt, 'bind_param'], $bind);

  $stmt->execute();
  $res = $stmt->get_result();

  $owned = [];
  while ($row = $res->fetch_assoc()) {
    $owned[(int)$row['achievement_id']] = true;
  }
  $stmt->close();

  // Remove any IDs the user doesn't own
  $ids = array_values(array_filter($ids, fn($id) => isset($owned[$id])));
  $b1 = $ids[0] ?? null;
  $b2 = $ids[1] ?? null;
  $b3 = $ids[2] ?? null;
}

$stmt = $connection->prepare("
  UPDATE users
  SET featured_badge_1 = ?, featured_badge_2 = ?, featured_badge_3 = ?
  WHERE id = ?
");
$stmt->bind_param("iiii", $b1, $b2, $b3, $user_id);

$ok = $stmt->execute();
$stmt->close();
$connection->close();

echo json_encode(['success' => (bool)$ok, 'badge_ids' => $ids]);
