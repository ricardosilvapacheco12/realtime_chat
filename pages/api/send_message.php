<?php
session_start();
require '../../config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error']);
    exit;
}

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('INSERT INTO messages (user_id, message) VALUES (?, ?)');
$stmt->bind_param('is', $userId, $message);
if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
$stmt->close();
