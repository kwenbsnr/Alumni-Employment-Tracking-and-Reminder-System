<?php
// api/mark_notification_read.php
session_start();
require_once '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? null;

if (!$notification_id) {
    echo json_encode(['error' => 'Notification ID required']);
    exit();
}

$stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE notification_id = ?");
$stmt->bind_param("i", $notification_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
?>