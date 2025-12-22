<?php
require_once 'api/notification/notif_service.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Delivery Verification</title>
</head>
<body>
    <h1>📧 Quick Email Delivery Test</h1>";

$test_email = "bisnar.quien18@gmail.com";

// Quick single test
$result = send_profile_update_reminder($test_email, "Quien Bisnar", "2020");

if ($result['success']) {
    echo "<div style='background: #DCFCE7; padding: 20px; border-radius: 8px; border: 2px solid #22C55E;'>
            <h2>✅ SUCCESS!</h2>
            <p><strong>Notification sent to:</strong> $test_email</p>
            <p><strong>Please check your Gmail inbox</strong> for the test notification.</p>
            <p>Look for an email from NotificationAPI with subject about profile updates.</p>
        </div>";
} else {
    echo "<div style='background: #FECACA; padding: 20px; border-radius: 8px; border: 2px solid #EF4444;'>
            <h2>❌ FAILED</h2>
            <p><strong>Error:</strong> " . $result['error'] . "</p>
        </div>";
}

echo "</body></html>";
?>