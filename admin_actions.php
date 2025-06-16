<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'];
$user_id = intval($data['user_id']);

// Prevent admin from banning themselves
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot perform action on yourself']);
    exit();
}

switch ($action) {
    case 'ban':
        $stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
        break;
    case 'unban':
        $stmt = $conn->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit();
}

$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Action failed']);
}
?>
