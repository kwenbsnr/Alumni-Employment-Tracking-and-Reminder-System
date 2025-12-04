<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/semiannual_updates.log');

echo "=== Semiannual Update Notification Script ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// Load dependencies
require_once __DIR__ . '/../../connect.php';
require_once __DIR__ . '/notif_service.php';

try {
    echo "Connecting to database...\n";
    
    // Send semiannual updates to all eligible alumni
    echo "Checking for alumni needing semiannual updates...\n";
    
    $result = send_semiannual_updates_to_all($conn);
    
    echo "\n=== RESULTS ===\n";
    echo "Total notifications sent: " . $result['total_sent'] . "\n";
    
    if ($result['total_sent'] > 0) {
        foreach ($result['results'] as $user_id => $res) {
            $status = $res['success'] ? 'SUCCESS' : 'FAILED';
            $error = isset($res['error']) ? " - " . $res['error'] : '';
            echo "User $user_id: $status$error\n";
        }
    } else {
        echo "No alumni require semiannual updates at this time.\n";
        echo "Criteria: Approved alumni who haven't updated in 6+ months.\n";
    }
    
    echo "\nScript completed successfully.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Script Error: " . $e->getMessage());
}

echo "\nEnded: " . date('Y-m-d H:i:s') . "\n";
?>