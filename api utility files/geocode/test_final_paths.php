<?php

echo "<h2>Testing All Paths</h2>";
echo "Current DIR: " . __DIR__ . "<br>";
echo "Project Root: " . realpath(__DIR__) . "<br><br>";

$files = [
    'connect.php' => __DIR__ . '/connect.php',
    'config.php' => __DIR__ . '/includes/config.php',
    'notif_service.php' => __DIR__ . '/api/notification/notif_service.php',
    'submission_status.php' => __DIR__ . '/api/reports/submission_status.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name found at: $path<br>";
    } else {
        echo "❌ $name NOT found at: $path<br>";
    }
}