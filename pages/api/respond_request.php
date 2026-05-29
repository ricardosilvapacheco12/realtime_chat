<?php
session_start();
require '../../config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error']);
    exit;
}

$userId = $_SESSION['user_id'];
$requestId = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
if ($requestId <= 0 || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

// verify request belongs to this user
$stmt = $conn->prepare('SELECT id, sender_id FROM requests WHERE id = ? AND receiver_id = ? LIMIT 1');
$stmt->bind_param('ii', $requestId, $userId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || !($row = $result->fetch_assoc())) {
    http_response_code(404);
    echo json_encode(['status' => 'error']);
    exit;
}
$stmt->close();
// remember sender id to return to client
$senderId = intval($row['sender_id']);

$newStatus = $action === 'accept' ? 'accepted' : 'rejected';
$update = $conn->prepare('UPDATE requests SET status = ? WHERE id = ?');
$update->bind_param('si', $newStatus, $requestId);
if ($update->execute()) {
    $out = ['status' => 'ok', 'result' => $newStatus];
    if ($newStatus === 'accepted') {
        $out['sender_id'] = $senderId;
    }
    echo json_encode($out);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
$update->close();
