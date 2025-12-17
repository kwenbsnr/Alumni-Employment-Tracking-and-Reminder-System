<?php
// api/mark_all_notifications_read.php
session_start();
require_once '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE is_read = FALSE");
$stmt->execute();
$affected_rows = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'success' => true,
    'marked_read' => $affected_rows
]);
?>