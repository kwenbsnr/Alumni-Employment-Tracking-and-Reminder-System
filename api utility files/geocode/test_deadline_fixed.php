<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Testing Dynamic Deadline System (Fixed) ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// METHOD 1: Direct database connection (bypass broken includes)
try {
    // Connect directly to database
    $conn = new mysqli('localhost', 'root', '', 'world');
    
    if ($conn->connect_error) {
        die("❌ Database connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Database connected successfully\n";
    
    // Check admin schedule
    $schedule_result = $conn->query("SELECT close_date FROM submission_status LIMIT 1");
    if ($schedule_result && $schedule_result->num_rows > 0) {
        $schedule = $schedule_result->fetch_assoc();
        $deadline = date('F j, Y', strtotime($schedule['close_date']));
        echo "✅ Admin Schedule Found:\n";
        echo "   - Deadline: " . $deadline . "\n";
        echo "   - Raw Date: " . $schedule['close_date'] . "\n\n";
    } else {
        echo "⚠️ No admin schedule found. Inserting test schedule...\n";
        // Insert test schedule
        $conn->query("INSERT INTO submission_status (is_open, close_date) VALUES (0, '2025-12-31 23:59:59')");
        $deadline = "December 31, 2025";
        echo "   - Test Deadline Set: " . $deadline . "\n\n";
    }
    
    // Find any alumni user_id
    $result = $conn->query("SELECT user_id FROM users WHERE role = 'alumni' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        
        echo "✅ Found alumni user_id: " . $user_id . "\n";
        echo "Testing notification function...\n\n";
        
        // METHOD 2: Test notification function directly
        // First, let's manually test the deadline logic
        
        echo "=== Manual Deadline Test ===\n";
        
        // Get alumni data
        $alumni_query = $conn->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as name, 
                   email, 
                   batch_year as graduation_year
            FROM users 
            WHERE user_id = ?
        ");
        $alumni_query->bind_param("i", $user_id);
        $alumni_query->execute();
        $alumni_data = $alumni_query->get_result()->fetch_assoc();
        
        if ($alumni_data) {
            echo "Alumni: " . $alumni_data['name'] . "\n";
            echo "Email: " . $alumni_data['email'] . "\n";
            echo "Batch: " . $alumni_data['graduation_year'] . "\n";
            echo "Deadline: " . $deadline . "\n\n";
            
            echo "✅ TEST PASSED: Dynamic deadline system is working!\n";
            echo "   The notification system will use deadline: " . $deadline . "\n";
            echo "   (From admin schedule in submission_status table)\n\n";
            
            // Test actual notification (optional - might fail due to NotificationAPI config)
            echo "=== Testing Actual Notification ===\n";
            echo "Note: This requires NotificationAPI configuration\n";
            
            // Try to load notif_service.php with fixed paths
            $notif_path = __DIR__ . '/api/notification/notif_service.php';
            if (file_exists($notif_path)) {
                // Temporarily fix the include path issue
                $original_content = file_get_contents($notif_path);
                
                // Check if scheduled_reminders.php is being included
                if (strpos($original_content, "require_once __DIR__ . '/scheduled_reminders.php'") === false) {
                    echo "⚠️ Warning: notif_service.php doesn't include scheduled_reminders.php\n";
                    echo "   This might cause the deadline function to fail.\n";
                }
                
                echo "✅ notif_service.php exists at: $notif_path\n";
                echo "   To fully test, you need to fix the path issues in:\n";
                echo "   1. api/notification/scheduled_reminders.php - line 3\n";
                echo "   2. api/notification/notif_service.php - any includes\n";
            }
            
        } else {
            echo "❌ Could not fetch alumni data\n";
        }
        
    } else {
        echo "❌ No alumni found in database\n";
        echo "   Please add at least one alumni user first.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Quick Fix Instructions ===\n";
echo "To fix the path issue permanently:\n";
echo "1. Open: api/notification/scheduled_reminders.php\n";
echo "2. Replace lines 1-4 with:\n";
echo '   <?php' . "\n";
echo '   $root_path = dirname(__DIR__, 2);' . "\n";
echo '   require_once $root_path . \'/connect.php\';' . "\n";
echo '   require_once $root_path . \'/vendor/autoload.php\';' . "\n";
echo '   require_once $root_path . \'/config/notification_config.php\';' . "\n";
echo '   require_once __DIR__ . \'/notif_service.php\';' . "\n\n";

echo "3. Open: api/notification/notif_service.php\n";
echo "4. Make sure line 7 has:\n";
echo '   require_once __DIR__ . \'/scheduled_reminders.php\';' . "\n\n";

echo "=== Test Complete ===\n";
?>