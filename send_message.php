<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$recipient_id = intval($data['recipient_id']);
$content = trim($conn->real_escape_string($data['content']));

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

// Check if recipient exists and is not banned
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_banned = 0");
$stmt->bind_param("i", $recipient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Recipient not available']);
    exit();
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, content) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $_SESSION['user_id'], $recipient_id, $content);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>
