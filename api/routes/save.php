<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug log for AJAX POSTs
file_put_contents(__DIR__.'/../../insert_debug.txt', print_r($_POST, 1), FILE_APPEND);
file_put_contents(__DIR__.'/../../insert_debug.txt', print_r(json_decode(file_get_contents('php://input'), 1), true), FILE_APPEND);
file_put_contents(__DIR__.'/../../insert_debug.txt', "\n", FILE_APPEND);

// echo json_encode(['debug'=>'Reached here after debug!']); exit;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['coordinates']) || count($data['coordinates']) < 2) {
        echo json_encode(['success' => false, 'error' => 'Invalid route data']);
        exit;
    }

    $mysqli = new mysqli('localhost', 'root', '', 'trailforgex');
    if ($mysqli->connect_errno) {
        echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $mysqli->connect_error]);
        exit;
    }

    // Supply user_id by session or default for now
    session_start();
    $creator_id = $_SESSION['user_id'] ?? 1;
    $title = $data['title'] ?? "Route from (" . $data['start_lat'] . ", " . $data['start_lng'] . ")";
    $desc = $data['description'] ?? "Generated route.";
    $activity_type = $data['activity_type'] ?? 'run';
    $distance_km = $data['distance_km'] ?? 0;
    $elevation = $data['elevation_gain_m'] ?? 0;
    $is_public = 0;

    $stmt = $mysqli->prepare("INSERT INTO routes (creator_id, title, description, activity_type, distance_km, elevation_gain_m, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $mysqli->error]);
        exit;
    }
    $stmt->bind_param('isssdii', $creator_id, $title, $desc, $activity_type, $distance_km, $elevation, $is_public);
    $stmt->execute();
    if ($stmt->error) {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    $route_id = $stmt->insert_id;
    $stmt->close();

    $order = 1;
    $pt_stmt = $mysqli->prepare("INSERT INTO route_points (route_id, latitude, longitude, point_order) VALUES (?, ?, ?, ?)");
    foreach ($data['coordinates'] as $pt) {
        $lat = $pt[0];
        $lng = $pt[1];
        $pt_stmt->bind_param('iddi', $route_id, $lat, $lng, $order);
        $pt_stmt->execute();
        $order++;
    }
    $pt_stmt->close();
    $mysqli->close();
    echo json_encode(['success' => true, 'route_id' => $route_id]);
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (!$msg) $msg = 'Unknown error (see server logs)';
    file_put_contents(__DIR__.'/../../insert_debug.txt', "EXCEPTION: ".print_r($e, true), FILE_APPEND);
    echo json_encode(['success'=>false, 'error'=>$msg]);
    exit;
}

