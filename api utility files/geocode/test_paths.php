<?php
// test_paths.php - Place in project root
echo "Testing project paths...\n";
echo "Current directory: " . __DIR__ . "\n\n";

// Test connect.php
$connect_path = __DIR__ . '/connect.php';
if (file_exists($connect_path)) {
    echo "✅ connect.php found at: $connect_path\n";
} else {
    echo "❌ connect.php NOT found at: $connect_path\n";
}

// Test notif_service.php  
$notif_path = __DIR__ . '/api/notification/notif_service.php';
if (file_exists($notif_path)) {
    echo "✅ notif_service.php found at: $notif_path\n";
} else {
    echo "❌ notif_service.php NOT found at: $notif_path\n";
}

// Test scheduled_reminders.php
$scheduled_path = __DIR__ . '/api/notification/scheduled_reminders.php';
if (file_exists($scheduled_path)) {
    echo "✅ scheduled_reminders.php found at: $scheduled_path\n";
    
    // Check its require_once paths
    echo "\nChecking scheduled_reminders.php requires:\n";
    $content = file_get_contents($scheduled_path);
    if (preg_match_all('/require_once\s+[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $path) {
            $full_path = __DIR__ . '/' . dirname('api/notification/scheduled_reminders.php') . '/' . $path;
            if (file_exists($full_path)) {
                echo "  ✅ $path -> FOUND\n";
            } else {
                echo "  ❌ $path -> NOT FOUND (looked at: $full_path)\n";
            }
        }
    }
} else {
    echo "❌ scheduled_reminders.php NOT found at: $scheduled_path\n";
}
?>