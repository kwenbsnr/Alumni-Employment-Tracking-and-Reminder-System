<?php
echo "<h3>File Structure Check</h3>";

$files_to_check = [
    'notification_helper.php' => 'C:/xampp/htdocs/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_helper.php',
    'connect.php' => 'C:/xampp/htdocs/Alumni-Employment-Tracking-and-Reminder-System/connect.php',
    'autoload.php' => 'C:/xampp/htdocs/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php'
];

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists at: $path<br>";
    } else {
        echo "❌ $name NOT FOUND at: $path<br>";
        
        // Try to find it
        $found = false;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['DOCUMENT_ROOT']));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $name) {
                echo "🔍 Found $name at: " . $file->getPathname() . "<br>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "🔍 $name not found anywhere in the project<br>";
        }
    }
}

// Check directory structure
echo "<h4>Directory Structure:</h4>";
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/';
if (is_dir($base_path . 'api/notification/')) {
    echo "✅ api/notification/ directory exists<br>";
    
    // List files in notification directory
    $files = scandir($base_path . 'api/notification/');
    echo "Files in notification directory: " . implode(', ', array_filter($files, function($file) {
        return $file !== '.' && $file !== '..';
    })) . "<br>";
} else {
    echo "❌ api/notification/ directory does not exist<br>";
}
?>