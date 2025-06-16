<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user1 = intval($_GET['user1']);
$user2 = intval($_GET['user2']);

// Get messages between two users
$sql = "SELECT m.*, u.username as sender_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) 
        OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $user1, $user2, $user2, $user1);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'sender_id' => $row['sender_id'],
        'sender_name' => $row['sender_name'],
        'content' => htmlspecialchars($row['content']),
        'timestamp' => date('Y-m-d H:i', strtotime($row['timestamp']))
    ];
}

echo json_encode($messages);
?>
