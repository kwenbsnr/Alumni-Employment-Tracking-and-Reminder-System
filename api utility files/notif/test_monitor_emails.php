<?php

// Check if emails are actually being sent

echo "=== Email Delivery Monitor ===\n";
echo "Monitoring for: bisnar.quien18@gmail.com\n";
echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

require_once 'api/notification/notif_service.php';

// Test sequence
$tests = [
    ['template_one', 'Profile Update Reminder'],
    ['template_approved', 'Approval Notification'],
    ['template_rejected', 'Rejection Notification']
];

foreach ($tests as $test) {
    echo "Sending: {$test[1]}... ";
    
    $result = send_notification($test[0], 'bisnar.quien18@gmail.com', [
        'alumni_name' => 'Quien Bisnar',
        'graduation_year' => '2020',
        'rejection_reason' => 'Test rejection for monitoring',
        'current_position' => 'Developer',
        'current_company' => 'Test Corp'
    ]);
    
    if ($result['success']) {
        echo "✅ SENT\n";
        
        // Log deets
        if (isset($result['data']['http_code'])) {
            echo "   HTTP: {$result['data']['http_code']}\n";
        }
        if (isset($result['data']['total_time'])) {
            echo "   Time: {$result['data']['total_time']}s\n";
        }
        
    } else {
        echo "❌ FAILED: {$result['error']}\n";
    }
    
    echo "   Waiting 3 seconds...\n";
    sleep(3);
}

echo "\n=== Monitoring Complete ===\n";
echo "All test emails should be delivered within few minutes.\n";
echo "Check your Gmail inbox and spam folder.\n";

// Create summary file
$summary = "Email Test Summary\n";
$summary .= "================\n";
$summary .= "Time: " . date('Y-m-d H:i:s') . "\n";
$summary .= "Email: bisnar.quien18@gmail.com\n";
$summary .= "Tests Run: " . count($tests) . "\n";
$summary .= "Check Gmail inbox for delivery.\n";

file_put_contents('email_test_summary.txt', $summary);
echo "Summary saved to email_test_summary.txt\n";
?>