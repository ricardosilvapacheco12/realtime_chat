<?php
session_start();
require '../../config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$receiverId = intval($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
if ($receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('INSERT INTO requests (sender_id, receiver_id, message) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $userId, $receiverId, $message);
if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
$stmt->close();
