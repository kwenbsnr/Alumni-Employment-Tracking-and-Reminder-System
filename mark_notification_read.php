<?php
session_start();
include("../connect.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$user_id = $_SESSION["user_id"];
$notification_id = $_POST['notification_id'] ?? 0;

if ($notification_id > 0) {
    $stmt = $conn->prepare("
        UPDATE alumni_notifications 
        SET is_read = TRUE 
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
}
?>