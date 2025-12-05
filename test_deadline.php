<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Testing Dynamic Deadline System ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Load notification service
require_once 'api/notification/notif_service.php';

$root_path = dirname(__DIR__, 2); // Go up 2 levels from api/notification/
require_once $root_path . '/connect.php';
require_once $root_path . '/vendor/autoload.php';
require_once $root_path . '/config/notification_config.php';
require_once __DIR__ . '/notif_service.php';

// Check admin schedule first
$schedule_query = "SELECT close_date FROM submission_status LIMIT 1";
$schedule_result = $conn->query($schedule_query);
if ($schedule_result && $schedule_result->num_rows > 0) {
    $schedule = $schedule_result->fetch_assoc();
    $deadline = date('F j, Y', strtotime($schedule['close_date']));
    echo "✅ Admin Schedule Found:\n";
    echo "   - Deadline: " . $deadline . "\n";
    echo "   - Raw Date: " . $schedule['close_date'] . "\n\n";
} else {
    echo "❌ No admin schedule found in submission_status table\n";
    exit;
}

// Find a test alumni
// Option 1: Try to find an approved alumni who hasn't updated in 6+ months
$query = "
    SELECT u.user_id, 
           CONCAT(u.first_name, ' ', u.last_name) as name,
           ap.submission_status,
           ap.last_profile_update,
           TIMESTAMPDIFF(MONTH, ap.last_profile_update, NOW()) as months_since_update
    FROM users u 
    INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
    WHERE u.role = 'alumni'
    AND ap.submission_status = 'Approved'
    AND ap.last_profile_update IS NOT NULL
    LIMIT 1
";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
    
    echo "✅ Test Alumni Found:\n";
    echo "   - Name: " . $row['name'] . "\n";
    echo "   - User ID: " . $user_id . "\n";
    echo "   - Status: " . $row['submission_status'] . "\n";
    echo "   - Last Update: " . $row['last_profile_update'] . "\n";
    echo "   - Months Since Update: " . $row['months_since_update'] . "\n\n";
    
    echo "Sending test notification...\n";
    $notification_result = send_profile_update_reminder($conn, $user_id);
    
    echo "\n=== Notification Result ===\n";
    if ($notification_result['success']) {
        echo "✅ SUCCESS: Notification sent!\n";
        echo "📧 Email should show deadline: " . $deadline . "\n";
        echo "📋 Check NotificationAPI dashboard for sent email.\n";
    } else {
        echo "❌ FAILED: " . $notification_result['error'] . "\n";
        
        // Check specific error
        if (strpos($notification_result['error'], 'less than 6 months') !== false) {
            echo "\n💡 INFO: This alumni was approved but updated recently.\n";
            echo "To test semiannual updates, we need an alumni who:\n";
            echo "1. Is approved (submission_status = 'Approved')\n";
            echo "2. Hasn't updated in 6+ months\n\n";
            
            echo "Would you like to temporarily modify this alumni for testing? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $response = trim(fgets($handle));
            
            if (strtolower($response) === 'y') {
                // Update last_profile_update to 7 months ago
                $update_query = "UPDATE alumni_profile SET last_profile_update = DATE_SUB(NOW(), INTERVAL 7 MONTH) WHERE user_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    echo "✅ Updated alumni's last_profile_update to 7 months ago.\n";
                    echo "Sending notification again...\n";
                    
                    $second_result = send_profile_update_reminder($conn, $user_id);
                    
                    if ($second_result['success']) {
                        echo "✅ SUCCESS: Notification sent with dynamic deadline!\n";
                        echo "📧 Deadline in email: " . $deadline . "\n";
                    } else {
                        echo "❌ Still failed: " . $second_result['error'] . "\n";
                    }
                    
                    // Restore original date (optional)
                    // echo "\n💡 Note: Alumni's last_profile_update was modified for testing.\n";
                } else {
                    echo "❌ Failed to update alumni record.\n";
                }
                $stmt->close();
            }
        }
    }
    
} else {
    echo "❌ No approved alumni found in database.\n";
    echo "Please ensure you have at least one alumni with:\n";
    echo "1. role = 'alumni' in users table\n";
    echo "2. submission_status = 'Approved' in alumni_profile\n\n";
    
    // Show all alumni for debugging
    $debug_query = "
        SELECT u.user_id, u.email, u.first_name, u.last_name, 
               ap.submission_status, ap.last_profile_update
        FROM users u 
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.role = 'alumni'
        LIMIT 5
    ";
    
    $debug_result = $conn->query($debug_query);
    if ($debug_result && $debug_result->num_rows > 0) {
        echo "Current alumni in database:\n";
        while ($debug_row = $debug_result->fetch_assoc()) {
            echo " - " . $debug_row['first_name'] . " " . $debug_row['last_name'];
            echo " (" . $debug_row['email'] . "): ";
            echo "Status: " . ($debug_row['submission_status'] ?? 'NO PROFILE');
            echo ", Last Update: " . ($debug_row['last_profile_update'] ?? 'NEVER') . "\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
?>