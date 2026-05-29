<?php
session_start();
require '../../config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['users' => []]);
    exit;
}

$userId = $_SESSION['user_id'];
// Fetch users along with relation status (none, pending, accepted)
$stmt = $conn->prepare(
    'SELECT u.id, u.firstname, u.lastname, u.last_active, r.status, r.sender_id
     FROM users u
     LEFT JOIN requests r ON ((r.sender_id = ? AND r.receiver_id = u.id) OR (r.sender_id = u.id AND r.receiver_id = ?))
     WHERE u.id <> ?
     ORDER BY u.firstname ASC, u.lastname ASC'
);
$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$stmt->bind_result($id, $firstname, $lastname, $lastActive, $reqStatus, $reqSenderId);

$users = [];
while ($stmt->fetch()) {
    $name = trim("$firstname $lastname");
    $initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
    $online = false;
    if ($lastActive) {
        $timestamp = strtotime($lastActive);
        $online = $timestamp && (time() - $timestamp < 120);
    }

    $relation = 'none';
    $relation_role = null;
    if ($reqStatus) {
        if ($reqStatus === 'pending') $relation = 'pending';
        elseif ($reqStatus === 'accepted') $relation = 'accepted';
        $relation_role = ($reqSenderId === $userId) ? 'sender' : 'receiver';
    }

    $users[] = [
        'id' => $id,
        'name' => $name,
        'initials' => $initials,
        'online' => $online,
        'relation' => $relation,
        'relation_role' => $relation_role,
    ];
}
$stmt->close();

echo json_encode(['users' => $users]);
