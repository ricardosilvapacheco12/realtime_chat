<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['messages' => []]);
    exit;
}

$contactId = intval($_GET['contact_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($contactId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['messages' => []]);
    exit;
}

$stmt = $conn->prepare(
    'SELECT m.id, m.message, m.created_at, u.firstname, u.lastname, m.user_id
     FROM messages m
     JOIN users u ON u.id = m.user_id
     WHERE ((m.user_id = ? AND m.receiver_id = ?) OR (m.user_id = ? AND m.receiver_id = ?))
     ORDER BY m.created_at ASC'
);
$stmt->bind_param('iiii', $userId, $contactId, $contactId, $userId);
$stmt->execute();
$stmt->bind_result($id, $message, $createdAt, $firstname, $lastname, $messageUserId);

$messages = [];
while ($stmt->fetch()) {
    $timestamp = $createdAt ? strtotime($createdAt) : false;
    $timeLabel = $timestamp ? date('H:i', $timestamp) : '';

    $messages[] = [
        'id' => $id,
        'message' => $message,
        'created_at' => $timeLabel,
        'author_name' => trim("$firstname $lastname"),
        'is_own' => $messageUserId === $userId,
    ];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);
