<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/connect.php';

echo "<h3>Fixed Structure Test</h3>";

// Test 1: Check if NotificationAPI is available
if (class_exists('NotificationAPI\NotificationAPI')) {
    echo "✅ NotificationAPI class loaded successfully<br>";
} else {
    echo "❌ NotificationAPI class failed to load<br>";
}

// Test 2: Database connection
if ($conn && $conn->ping()) {
    echo "✅ Database connection working<br>";
} else {
    echo "❌ Database connection failed<br>";
}

// Test 3: Check existing notification files
$notification_dir = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/';
$existing_files = scandir($notification_dir);
$notification_files = array_filter($existing_files, function($file) {
    return $file !== '.' && $file !== '..';
});

echo "✅ Existing notification files: " . implode(', ', $notification_files) . "<br>";

// Test 4: Try to include and use existing notification functions
try {
    include_once $notification_dir . 'notification_functions.php';
    
    // Check if any notification functions exist
    if (function_exists('sendAlumniReminder')) {
        echo "✅ Existing notification functions found<br>";
    } else {
        echo "⚠️ No existing notification functions found (this is OK if creating new)<br>";
    }
    
} catch (Exception $e) {
    echo "⚠️ Could not load existing notification functions: " . $e->getMessage() . "<br>";
}

// Test 5: Create and test NotificationHelper
try {
    $helper_path = $notification_dir . 'notification_helper.php';
    
    if (!file_exists($helper_path)) {
        echo "❌ notification_helper.php not found - creating it now...<br>";
        
        // You would create the file here using the code I provided above
        echo "📝 Please create the notification_helper.php file using the code provided<br>";
    } else {
        include_once $helper_path;
        
        if (class_exists('NotificationHelper')) {
            $notif = new NotificationHelper($conn);
            echo "✅ Notification helper created successfully<br>";
            
            // Test basic functionality
            $admin_emails = $notif->getAdminEmails();
            echo "✅ Admin emails check completed: " . count($admin_emails) . " found<br>";
            
            // Test connection (without real credentials)
            $test_connection = $notif->testConnection();
            if ($test_connection['success']) {
                echo "✅ NotificationAPI connection successful<br>";
            } else {
                echo "⚠️ NotificationAPI connection failed (expected without credentials): " . $test_connection['message'] . "<br>";
            }
            
        } else {
            echo "❌ NotificationHelper class not found after inclusion<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Notification helper test failed: " . $e->getMessage() . "<br>";
}

echo "<h4>🎉 System structure analysis complete!</h4>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Create the notification_helper.php file in your api/notification/ directory</li>";
echo "<li>Add your actual NotificationAPI credentials</li>";
echo "<li>Run the test again to verify everything works</li>";
echo "</ol>";

// Show environment info
echo "<h4>Environment Info:</h4>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

$required_extensions = ['curl', 'openssl', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    echo (extension_loaded($ext) ? "✅" : "❌") . " $ext<br>";
}
?>