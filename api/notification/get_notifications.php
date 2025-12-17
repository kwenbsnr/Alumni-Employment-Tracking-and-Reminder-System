<?php
// api/get_notifications.php
session_start();
require_once '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get unread count
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM admin_notifications 
    WHERE is_read = FALSE
");
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['unread_count'] ?? 0;
$stmt->close();

// Get recent notifications (last 20)
$stmt = $conn->prepare("
    SELECT 
        n.notification_id,
        n.notification_type,
        n.alumni_name,
        n.employment_status,
        n.batch_year,
        n.submission_time,
        n.is_read
    FROM admin_notifications n
    ORDER BY n.submission_time DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'notification_id' => $row['notification_id'],
        'notification_type' => $row['notification_type'],
        'alumni_name' => $row['alumni_name'],
        'employment_status' => $row['employment_status'],
        'batch_year' => $row['batch_year'],
        'submission_time' => $row['submission_time'],
        'is_read' => (bool)$row['is_read']
    ];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);
?>