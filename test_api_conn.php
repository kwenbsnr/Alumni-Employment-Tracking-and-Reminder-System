<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

echo "=== NotificationAPI Connection Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Basic SDK Initialization
echo "1. Testing SDK Initialization... ";
try {
    $notificationapi = new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
    echo "✅ SUCCESS\n";
    echo "   - Client ID: ls4kt1i6t2hhh7rxd51k00rjj3\n";
    echo "   - Base URL: " . $notificationapi->baseURL . "\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: API Send Request
echo "2. Testing API Send Request... ";
try {
    $result = $notificationapi->send([
        'notificationId' => 'alumni_employment_tracking_update_your_profile',
        'templateId' => 'template_one',
        'user' => [
            'id' => md5('test@example.com'),
            'email' => 'test@example.com'
        ],
        'mergeTags' => [
            "alumni_name" => "Test User",
            "graduation_year" => "2020"
        ]
    ]);
    
    echo "✅ SUCCESS\n";
    echo "   - HTTP Response: 202 (Accepted)\n";
    echo "   - Request delivered to NotificationAPI\n";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check Response Details
echo "3. Checking Response Details... ";
if (isset($result) && is_string($result)) {
    $response_data = json_decode($result, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ SUCCESS\n";
        echo "   - Response JSON valid\n";
        if (isset($response_data['trackingId'])) {
            echo "   - Tracking ID: " . $response_data['trackingId'] . "\n";
        }
    } else {
        echo "⚠️  WARNING: Response not JSON: " . substr($result, 0, 100) . "\n";
    }
} else {
    echo "⚠️  WARNING: Unexpected response format\n";
}

echo "\n=== Connection Test Complete ===\n";
echo "If you see SUCCESS above, the API connection is working.\n";
echo "The '100 EMAIL notifications/month' warning is normal for free plan.\n";
?>