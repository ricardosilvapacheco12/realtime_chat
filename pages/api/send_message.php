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
$receiverId = intval($_POST['receiver_id'] ?? 0);
if ($message === '' || $receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

$userId = $_SESSION['user_id'];
// Verify there is an accepted request between users
$check = $conn->prepare('SELECT id FROM requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = "accepted" LIMIT 1');
$check->bind_param('iiii', $userId, $receiverId, $receiverId, $userId);
$check->execute();
$res = $check->get_result();
if (!($res && $res->fetch_assoc())) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No accepted request']);
    $check->close();
    exit;
}
$check->close();

$stmt = $conn->prepare('INSERT INTO messages (user_id, receiver_id, message) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $userId, $receiverId, $message);
if ($stmt->execute()) {
    $update = $conn->prepare('UPDATE users SET last_active = NOW() WHERE id = ?');
    $update->bind_param('i', $userId);
    $update->execute();
    $update->close();
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
$stmt->close();
