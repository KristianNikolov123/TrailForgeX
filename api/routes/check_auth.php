<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
echo json_encode(['logged_in' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])]);





