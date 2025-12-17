<?php
// api/cleanup_old_notifications.php
require_once '../connect.php';

// Only allow execution from command line or with secret key
$allowed = false;

// Check for command line execution
if (php_sapi_name() === 'cli') {
    $allowed = true;
}

// Check for secret key in GET parameter
if (isset($_GET['key']) && $_GET['key'] === 'your_secret_key_here') {
    $allowed = true;
}

if (!$allowed) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Delete notifications older than 30 days
$stmt = $conn->prepare("DELETE FROM admin_notifications WHERE submission_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$deleted_count = $stmt->affected_rows;
$stmt->close();

// Also delete old activity logs
$stmt = $conn->prepare("DELETE FROM alumni_activity_log WHERE activity_date < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$stmt->execute();
$deleted_activity = $stmt->affected_rows;
$stmt->close();

error_log("Cleanup: Deleted $deleted_count old notifications and $deleted_activity old activity logs");
echo "Deleted $deleted_count old notifications and $deleted_activity old activity logs\n";
?>