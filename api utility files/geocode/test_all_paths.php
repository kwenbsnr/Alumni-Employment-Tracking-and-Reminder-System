<?php
echo "<h2>Testing All System Paths</h2>";
echo "Current DIR: " . __DIR__ . "<br><br>";

// Check all important files
$files = [
    'Root Files' => [
        'connect.php' => __DIR__ . '/connect.php',
        'notification_config.php' => __DIR__ . '/config/notification_config.php',
        'paths.php' => __DIR__ . '/config/paths.php',
    ],
    'API Files' => [
        'notif_service.php' => __DIR__ . '/api/notification/notif_service.php',
        'scheduled_reminders.php' => __DIR__ . '/api/notification/scheduled_reminders.php',
        'submission_status.php' => __DIR__ . '/api/reports/submission_status.php',
        'geocode.php' => __DIR__ . '/api/geocode.php',
    ],
    'Admin Files' => [
        'admin_dashboard.php' => __DIR__ . '/admin/admin_dashboard.php',
        'set_schedule.php' => __DIR__ . '/admin/set_schedule.php',
    ]
];

foreach ($files as $category => $fileList) {
    echo "<h3>$category</h3>";
    foreach ($fileList as $name => $path) {
        if (file_exists($path)) {
            echo "✅ $name<br>";
        } else {
            echo "❌ $name - NOT FOUND at: $path<br>";
        }
    }
    echo "<br>";
}