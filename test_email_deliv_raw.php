<?php

require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "=== Email Delivery Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Target: bisnar.quien18@gmail.com\n\n";

$test_email = "bisnar.quien18@gmail.com";

// Test 1: Simple Notification
echo "1. Sending test notification...\n";
$result = send_notification('template_one', $test_email, [
    'alumni_name' => 'Quien Bisnar',
    'graduation_year' => '2020',
    'submission_date' => date('Y-m-d H:i:s')
]);

if ($result['success']) {
    echo "   ✅ SUCCESS: Notification sent to API\n";
    
    // Check response data
    if (isset($result['data']) && is_array($result['data'])) {
        if (isset($result['data']['http_code'])) {
            echo "   - HTTP Code: " . $result['data']['http_code'] . "\n";
        }
        if (isset($result['data']['url'])) {
            echo "   - API Endpoint: " . $result['data']['url'] . "\n";
        }
    }
    
    // Log the attempt
    error_log("EMAIL_TEST: Sent to " . $test_email . " at " . date('Y-m-d H:i:s'));
    
} else {
    echo "   ❌ FAILED: " . $result['error'] . "\n";
    error_log("EMAIL_TEST_FAILED: " . $result['error']);
}

// Test 2: Check API Response Details
echo "\n2. Checking API response...\n";
if ($result['success'] && isset($result['data'])) {
    $api_response = $result['data'];
    
    if (isset($api_response['http_code'])) {
        echo "   - HTTP Status: " . $api_response['http_code'] . "\n";
        
        if ($api_response['http_code'] == 202) {
            echo "   ✅ API accepted the request\n";
        } else {
            echo "   ⚠️  Unexpected HTTP status\n";
        }
    }
    
    if (isset($api_response['size_upload'])) {
        echo "   - Request Size: " . $api_response['size_upload'] . " bytes\n";
    }
    
    if (isset($api_response['total_time'])) {
        echo "   - Response Time: " . $api_response['total_time'] . " seconds\n";
    }
}

// Test 3: Multiple Template Test
echo "\n3. Testing multiple templates...\n";
$templates = [
    'template_one' => 'Update Reminder',
    'template_approved' => 'Approval',
    'template_rejected' => 'Rejection'
];

foreach ($templates as $template => $name) {
    echo "   - $name... ";
    $result = send_notification($template, $test_email, [
        'alumni_name' => 'Quien Bisnar',
        'graduation_year' => '2020',
        'rejection_reason' => 'Test reason'
    ]);
    
    echo $result['success'] ? "✅\n" : "❌\n";
    sleep(1); // Rate limiting
}

echo "\n=== Delivery Test Complete ===\n";
echo "Check your Gmail inbox for test emails.\n";
echo "Also check spam folder if not in inbox.\n";
echo "API logs are saved to error_log file.\n";

// Write to a test log file
file_put_contents('email_test_log.txt', 
    "Test completed at: " . date('Y-m-d H:i:s') . "\n" .
    "Email: " . $test_email . "\n" .
    "Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n",
    FILE_APPEND
);
?>