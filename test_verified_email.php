<?php

require_once 'api/notification/notif_service.php';

echo "=== Testing with Alternative Email ===\n\n";

$test_emails = [
    "bisnar.quien18@gmail.com", 
    "alumtrak@gmail.com",     
    "quein.bisnar@gmail.com"             
];

foreach ($test_emails as $email) {
    echo "Testing: $email\n";
    
    $result = send_notification('template_one', $email, [
        'alumni_name' => 'Qwen',
        'graduation_year' => '2020'
    ]);
    
    if ($result['success']) {
        echo "✅ SUCCESS - Request accepted\n";
        
        // Check if email was actually sent or discarded
        if (isset($result['data'][1])) {
            $response = json_decode($result['data'][1], true);
            if (isset($response['messages'])) {
                foreach ($response['messages'] as $message) {
                    echo "   - $message\n";
                }
            }
        }
    } else {
        echo "❌ FAILED: " . $result['error'] . "\n";
    }
    
    echo "---\n";
    sleep(2);
}

echo "\n=== OMG ===\n";
?>