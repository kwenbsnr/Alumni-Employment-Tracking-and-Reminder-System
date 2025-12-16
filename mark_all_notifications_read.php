<?php
session_start();
include("../connect.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    UPDATE alumni_notifications 
    SET is_read = TRUE 
    WHERE user_id = ? AND is_read = FALSE
");
$stmt->bind_param("i", $user_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>