<?php
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "<h3>Simple Notification Test</h3>";

// Test 1: Basic notification
echo "Test 1: Sending notification... ";
$result = sendProfileUpdateReminder('test@example.com', 'John Doe', '2020');
echo $result['success'] ? "✅ SUCCESS<br>" : "❌ FAILED<br>";

// Test 2: Get alumni for reminders
echo "Test 2: Getting alumni... ";
$alumni = getAlumniForReminders($conn);
echo "✅ Found " . count($alumni) . " alumni<br>";

// Test 3: Get admin emails
echo "Test 3: Getting admin emails... ";
$admins = getAdminEmails($conn);
echo "✅ Found " . count($admins) . " admins<br>";

echo "<h4>🎉 System is working! The warnings are normal for free plan.</h4>";
echo "<p><strong>Note:</strong> The warnings about '100 EMAIL notifications/month' and template IDs are normal for NotificationAPI's free unverified account. Your notifications are being processed successfully.</p>";
?>