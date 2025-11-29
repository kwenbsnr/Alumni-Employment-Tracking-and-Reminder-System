<?php
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "Testing Notification Integration:\n";

// Test with a real alumni user
$test_user = $conn->query("SELECT user_id FROM users WHERE role = 'alumni' LIMIT 1")->fetch_assoc();

if ($test_user) {
    $result = send_profile_update_reminder($conn, $test_user['user_id']);
    echo $result['success'] ? "✅ Notification sent successfully!\n" : "❌ Failed: " . $result['error'] . "\n";
} else {
    echo "❌ No alumni users found for testing\n";
}
?>