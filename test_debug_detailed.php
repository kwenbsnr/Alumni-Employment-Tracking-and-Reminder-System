<?php

// Shows exactly what's being sent to NotificationAPI
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

echo "=== Detailed Debug Test ===\n\n";

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test credentials
$clientId = "ls4kt1i6t2hhh7rxd51k00rjj3";
$clientSecret = "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av";

echo "1. Credentials Check:\n";
echo "   - Client ID: " . substr($clientId, 0, 10) . "..." . (strlen($clientId) > 10 ? "✓" : "✗") . "\n";
echo "   - Client Secret: " . (strlen($clientSecret) > 10 ? "✓" : "✗") . "\n";

// Create request payload
$payload = [
    'notificationId' => 'alumni_employment_tracking_update_your_profile',
    'templateId' => 'template_one',
    'user' => [
        'id' => md5('bisnar.quien18@gmail.com'),
        'email' => 'bisnar.quien18@gmail.com'
    ],
    'mergeTags' => [
        "alumni_name" => "Quien Bisnar",
        "graduation_year" => "2020",
        "alumni_portal_link" => "http://localhost/Alumni-Employment-Tracking-and-Reminder-System/alumni/alumni_dashboard.php",
        "name" => "Quien Bisnar",
        "submission_date" => date('Y-m-d H:i:s')
    ]
];

echo "\n2. Request Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";

echo "\n3. Making API Call...\n";

try {
    $notificationapi = new NotificationAPI($clientId, $clientSecret);
    
    // Make the request
    $result = $notificationapi->send($payload);
    
    echo "   ✅ API Call Successful\n";
    
    // Parse response
    if (is_string($result)) {
        $response_data = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "   - Response JSON:\n";
            print_r($response_data);
        } else {
            echo "   - Raw Response: " . $result . "\n";
        }
    } else {
        echo "   - Response Type: " . gettype($result) . "\n";
        print_r($result);
    }
    
} catch (Exception $e) {
    echo "   ❌ API Call Failed: " . $e->getMessage() . "\n";
    echo "   - Error Type: " . get_class($e) . "\n";
    echo "   - File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test cURL directly
echo "\n4. Testing cURL Directly...\n";
$ch = curl_init();
$url = "https://api.notificationapi.com/ls4kt1i6t2hhh7rxd51k00rjj3/sender";

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode($clientId . ":" . $clientSecret),
        'Content-Type: application/json'
    ],
    CURLOPT_VERBOSE => true
]);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

echo "   - HTTP Code: " . $http_code . "\n";
echo "   - Response: " . substr($response, 0, 200) . "\n";
if ($curl_error) {
    echo "   - cURL Error: " . $curl_error . "\n";
}

rewind($verbose);
$verbose_log = stream_get_contents($verbose);
echo "   - cURL Verbose Log saved to curl_debug.log\n";
file_put_contents('curl_debug.log', $verbose_log);

echo "\n=== Debug Complete ===\n";
?>