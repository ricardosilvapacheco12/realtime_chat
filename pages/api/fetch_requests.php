<?php
session_start();
require '../../config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['requests' => []]);
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare(
    'SELECT r.id, r.sender_id, r.message, r.status, r.created_at, u.firstname, u.lastname
     FROM requests r
     JOIN users u ON u.id = r.sender_id
     WHERE r.receiver_id = ? AND r.status = "pending"
     ORDER BY r.created_at DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($id, $senderId, $message, $status, $createdAt, $firstname, $lastname);

$requests = [];
while ($stmt->fetch()) {
    $requests[] = [
        'id' => $id,
        'sender_id' => $senderId,
        'message' => $message,
        'status' => $status,
        'created_at' => $createdAt,
        'sender_name' => trim("$firstname $lastname"),
    ];
}
$stmt->close();

echo json_encode(['requests' => $requests]);
