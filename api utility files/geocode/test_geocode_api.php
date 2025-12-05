<?php
echo "Testing geocode API...\n\n";

// Test 1: Direct file check
echo "=== Test 1: File Existence ===\n";
$api_file = __DIR__ . '/api/geocode.php';
echo "File: $api_file\n";
echo "Exists: " . (file_exists($api_file) ? "YES" : "NO") . "\n\n";

// Test 2: Direct API call
echo "=== Test 2: Direct API Call ===\n";
$url = "http://localhost/Alumni-Employment-Tracking-and-Reminder-System/api/geocode.php?action=reverse&lat=14.5995&lon=120.9842&debug=1";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response:\n";
print_r(json_decode($response, true));
echo "\n";

// Check XAMPP error logs
echo "=== Check XAMPP Logs ===\n";
echo "Check error logs at: C:\\xampp\\apache\\logs\\error.log\n";
?>