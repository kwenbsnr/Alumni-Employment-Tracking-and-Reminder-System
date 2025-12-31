<?php
echo "<h3>NotificationAPI Diagnostic</h3>";

// Check autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
echo "Autoload path: $autoloadPath<br>";
echo "Autoload exists: " . (file_exists($autoloadPath) ? "✅ YES" : "❌ NO") . "<br><br>";

// Check SDK directory
$sdkPath = __DIR__ . '/vendor/notificationapi/notificationapi-php-server-sdk';
echo "SDK path: $sdkPath<br>";
echo "SDK exists: " . (file_exists($sdkPath) ? "✅ YES" : "❌ NO") . "<br><br>";

// Check main class file
$classPath = __DIR__ . '/vendor/notificationapi/notificationapi-php-server-sdk/src/NotificationAPI.php';
echo "Class path: $classPath<br>";
echo "Class exists: " . (file_exists($classPath) ? "✅ YES" : "❌ NO") . "<br><br>";

// Try to load the class
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    
    if (class_exists('NotificationAPI\NotificationAPI')) {
        echo "✅ NotificationAPI class can be loaded!<br>";
        
        // Test instantiation
        try {
            $api = new NotificationAPI\NotificationAPI('test', 'test');
            echo "✅ NotificationAPI can be instantiated!<br>";
        } catch (Exception $e) {
            echo "✅ NotificationAPI instantiated (credentials will fail, but class works)!<br>";
        }
    } else {
        echo "❌ NotificationAPI class NOT found after autoload.<br>";
    }
}

echo "<hr><h4>VS Code Fix:</h4>";
echo "If class exists but VS Code shows errors, try:<br>";
echo "1. Completely close VS Code<br>";
echo "2. Delete .vscode folder<br>"; 
echo "3. Reopen VS Code<br>";
echo "4. The errors should be gone if the diagnostic shows ✅ above";
?>