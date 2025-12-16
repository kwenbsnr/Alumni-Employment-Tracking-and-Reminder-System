<?php
session_start();
include("../connect.php");

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = $_SESSION["user_id"];

// Get unread notification count
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM alumni_notifications 
    WHERE user_id = ? AND is_read = FALSE
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

echo json_encode(['count' => $data['unread_count'] ?? 0]);
?>