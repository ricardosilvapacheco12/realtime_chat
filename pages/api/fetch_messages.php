<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['messages' => []]);
    exit;
}

$lastId = intval($_GET['last_id'] ?? 0);
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT m.id, m.message, m.created_at, u.firstname, u.lastname, m.user_id
     FROM messages m
     JOIN users u ON u.id = m.user_id
     WHERE m.id > ?
     ORDER BY m.id ASC
     LIMIT 200'
);
$stmt->bind_param('i', $lastId);
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
