<?php
require_once '../connect.php';
require_once '../api/notification/notif_service.php';

// Set error logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log execution
error_log("=== CRON JOB: Semiannual Update Notification Started ===");
error_log("Execution time: " . date('Y-m-d H:i:s'));

try {
    // Send notifications to all eligible alumni
    $results = send_all_semiannual_updates($conn);
    
    // Log results
    $count = count($results);
    error_log("Sent $count semiannual update notifications");
    
    foreach ($results as $user_id => $result) {
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';
        error_log("User $user_id: $status");
    }
    
    error_log("=== CRON JOB: Semiannual Update Notification Completed ===");
    
} catch (Exception $e) {
    error_log("CRON JOB ERROR: " . $e->getMessage());
}

// Close connection
$conn->close();
?>