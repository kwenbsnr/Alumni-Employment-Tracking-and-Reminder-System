<?php
// cleanup_old_notifications.php
include("connect.php");

// Delete notifications older than 90 days
$stmt = $conn->prepare("DELETE FROM alumni_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$stmt->execute();
$deleted_count = $stmt->affected_rows;
$stmt->close();

error_log("Cleanup: Deleted $deleted_count old notifications");
?>