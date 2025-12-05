<?php
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

// Manually test with any alumni user_id
$user_id = 1; // Replace with actual alumni user_id

echo "Testing dynamic deadline for user_id: $user_id\n";
echo "Admin schedule deadline: December 31, 2025\n\n";

$result = send_profile_update_reminder($conn, $user_id);

if ($result['success']) {
    echo "✅ SUCCESS: Notification sent with dynamic deadline!\n";
    echo "The email should show deadline: December 31, 2025\n";
    echo "(From admin schedule in submission_status table)\n";
} else {
    echo "❌ FAILED: " . $result['error'] . "\n";
    
    // Check if it's because alumni is approved but updated recently
    if (strpos($result['error'], 'less than 6 months') !== false) {
        echo "\n💡 Tip: This alumni is approved but updated recently.\n";
        echo "To test semiannual updates, you need an alumni who:\n";
        echo "1. Has submission_status = 'Approved'\n";
        echo "2. Has last_profile_update older than 6 months\n";
    }
}
?>