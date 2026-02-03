<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    session_start();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];

    $data = json_decode(file_get_contents('php://input'), true);

    $route_id = (int)($data['route_id'] ?? 0);
    $action   = (string)($data['action'] ?? '');
    $name     = trim((string)($data['route_name'] ?? ''));
    $name     = mb_substr($name, 0, 80);

    if ($route_id <= 0 || !in_array($action, ['favourite', 'publish'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bad request']);
        exit;
    }

    $mysqli = new mysqli('localhost', 'root', '', 'trailforgex');

    // Only the creator can rename their route (safe & simple rule)
    // ✅ First: verify the route exists and belongs to this user
    $stmt = $mysqli->prepare("SELECT id FROM routes WHERE id = ? AND creator_id = ?");
    $stmt->bind_param('ii', $route_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Route not found or not yours']);
        $stmt->close();
        $mysqli->close();
        exit;
    }
    $stmt->close();

// ✅ Optional rename: only run if name is not empty
    if ($name !== '') {
        $stmt = $mysqli->prepare("UPDATE routes SET title = ? WHERE id = ? AND creator_id = ?");
        $stmt->bind_param('sii', $name, $route_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }


    if ($action === 'favourite') {
        // favourites has (user_id, route_id) primary key -> duplicates safe with INSERT IGNORE
        $stmt = $mysqli->prepare("INSERT IGNORE INTO favorites (user_id, route_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $user_id, $route_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->close();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'publish') {
        // mark route public (optional but matches your schema)
        $stmt = $mysqli->prepare("UPDATE routes SET is_public = 1 WHERE id = ? AND creator_id = ?");
        $stmt->bind_param('ii', $route_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // route_shares logs that user shared it (dedupe optional: add unique key if you want)
        $stmt = $mysqli->prepare("INSERT IGNORE INTO route_shares (route_id, user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $route_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->close();
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage() ?: 'Server error']);
    exit;
}
