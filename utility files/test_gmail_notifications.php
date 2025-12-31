<?php
session_start();
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Gmail Notification Test - bisnar.quien18@gmail.com</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-container { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #10B981; background: #ECFDF5; }
        .error { border-left: 4px solid #EF4444; background: #FEF2F2; }
        .gmail-test { border: 2px solid #EA4335; background: #FFF; }
        button { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #2563EB; }
        .gmail-btn { background: #EA4335; }
        .gmail-btn:hover { background: #D33426; }
    </style>
</head>
<body>
    <h1>📧 Gmail Notification Test</h1>
    <p>Testing delivery to: <strong>bisnar.quien18@gmail.com</strong></p>";

$your_gmail = "bisnar.quien18@gmail.com";

// Test 1: Direct Gmail Tests
echo "<div class='test-container gmail-test'>
        <h2>🎯 Direct Gmail Delivery Tests</h2>
        <p>These tests will send actual notifications to your Gmail inbox.</p>
        
        <form method='POST'>
            <button type='submit' name='test_gmail_all' class='gmail-btn'>Test ALL Templates to Gmail</button>
            <button type='submit' name='test_gmail_alumni'>Test Alumni Notifications</button>
            <button type='submit' name='test_gmail_admin'>Test Admin Notifications</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_gmail_all'])) {
        test_all_templates_to_gmail($your_gmail);
    }
    if (isset($_POST['test_gmail_alumni'])) {
        test_alumni_notifications_to_gmail($your_gmail);
    }
    if (isset($_POST['test_gmail_admin'])) {
        test_admin_notifications_to_gmail($your_gmail);
    }
}

echo "</div>";

// Test 2: Real Scenario Simulation to Gmail
echo "<div class='test-container'>
        <h2>🔄 Real Scenario Simulation to Gmail</h2>
        <form method='POST'>
            <button type='submit' name='simulate_full_workflow'>Simulate Complete Workflow</button>
            <button type='submit' name='test_reminder_schedule'>Test Reminder Schedule</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simulate_full_workflow'])) {
        simulate_complete_workflow($your_gmail);
    }
    if (isset($_POST['test_reminder_schedule'])) {
        test_reminder_schedule_logic();
    }
}

echo "</div>";

// Test 3: Batch Testing
echo "<div class='test-container'>
        <h2>📊 Batch Notification Test</h2>
        <form method='POST'>
            <input type='number' name='batch_count' value='3' min='1' max='10' placeholder='Number of tests'>
            <button type='submit' name='test_batch'>Run Batch Test</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_batch'])) {
    $batch_count = intval($_POST['batch_count'] ?? 3);
    test_batch_notifications($your_gmail, $batch_count);
}

echo "</div>";

echo "</body></html>";

// Test Functions
function test_all_templates_to_gmail($gmail) {
    echo "<div class='test-container'>
            <h3>Testing ALL Templates to Gmail</h3>";
    
    $templates = [
        ['template_one', 'Profile Update Reminder'],
        ['template_approved', 'Approval Notification'],
        ['template_rejected', 'Rejection Notification'],
        ['alum_resubmit_admin_notif', 'Resubmission Admin Notification'],
        ['alum_update_admin_notif', 'Update Admin Notification'],
        ['template_admin_notif', 'New Submission Admin Notification']
    ];
    
    foreach ($templates as $template) {
        $result = send_notification($template[0], $gmail, [
            'alumni_name' => 'Quien Bisnar',
            'graduation_year' => '2020',
            'rejection_reason' => 'Test rejection reason for Gmail testing',
            'employment_status' => 'Employed',
            'current_position' => 'Software Developer',
            'current_company' => 'Tech Solutions Inc.',
            'submission_date' => date('F j, Y \a\t g:i A')
        ]);
        
        echo "<div class='" . ($result['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0; border-radius: 4px;'>
                <strong>{$template[1]}:</strong> " . 
                ($result['success'] ? 
                 '✅ Sent to Gmail - Check your inbox!' : 
                 '❌ Failed: ' . $result['error']) . "
              </div>";
        
        sleep(2); // Avoid rate limiting
    }
    
    echo "<p><strong>🎉 Check your Gmail inbox for all 6 notification types!</strong></p>";
    echo "</div>";
}

function test_alumni_notifications_to_gmail($gmail) {
    echo "<div class='test-container'>
            <h3>Alumni-Facing Notifications to Gmail</h3>";
    
    // Test profile update reminder
    $result1 = send_profile_update_reminder($gmail, 'Quien Bisnar', '2020');
    echo "<div class='" . ($result1['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Profile Update Reminder:</strong> " . 
            ($result1['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    // Test approval notification
    $result2 = send_approval_notification($gmail, 'Quien Bisnar', '2020', 'Senior Developer', 'Google');
    echo "<div class='" . ($result2['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Approval Notification:</strong> " . 
            ($result2['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    // Test rejection notification
    $result3 = send_rejection_notification($gmail, 'Quien Bisnar', '2020', 'Please upload clearer documents');
    echo "<div class='" . ($result3['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Rejection Notification:</strong> " . 
            ($result3['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    echo "</div>";
}

function test_admin_notifications_to_gmail($gmail) {
    echo "<div class='test-container'>
            <h3>Admin-Facing Notifications to Gmail</h3>";
    
    // Test new submission notification
    $result1 = send_new_submission_admin_notification($gmail, 'New Student', 'newstudent@example.com', '2023', 'Student');
    echo "<div class='" . ($result1['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>New Submission Alert:</strong> " . 
            ($result1['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    // Test update notification
    $result2 = send_update_admin_notification($gmail, 'Existing Alumni', 'existing@example.com', '2021', 'Employed');
    echo "<div class='" . ($result2['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Profile Update Alert:</strong> " . 
            ($result2['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    // Test resubmission notification
    $result3 = send_resubmission_admin_notification($gmail, 'Rejected Alumni', 'rejected@example.com', '2022', 'Documents were blurry');
    echo "<div class='" . ($result3['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Resubmission Alert:</strong> " . 
            ($result3['success'] ? '✅ Sent to Gmail' : '❌ Failed') . "
          </div>";
    
    echo "</div>";
}

function simulate_complete_workflow($gmail) {
    echo "<div class='test-container'>
            <h3>🔄 Complete Workflow Simulation</h3>";
    
    // Step 1: Alumni submits profile (admin notification)
    $result1 = send_new_submission_admin_notification($gmail, 'Quien Bisnar', 'bisnar.quien18@gmail.com', '2020', 'Employed');
    echo "<div class='" . ($result1['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Step 1 - New Submission:</strong> Admin notified of new profile submission<br>
            " . ($result1['success'] ? '✅ Success' : '❌ Failed') . "
          </div>";
    sleep(2);
    
    // Step 2: Admin rejects (alumni notification)
    $result2 = send_rejection_notification($gmail, 'Quien Bisnar', '2020', 'Please provide clearer employment certificate');
    echo "<div class='" . ($result2['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Step 2 - Rejection:</strong> Alumni notified of rejection with reason<br>
            " . ($result2['success'] ? '✅ Success' : '❌ Failed') . "
          </div>";
    sleep(2);
    
    // Step 3: Alumni resubmits (admin notification)
    $result3 = send_resubmission_admin_notification($gmail, 'Quien Bisnar', 'bisnar.quien18@gmail.com', '2020', 'Previous: Blurry documents');
    echo "<div class='" . ($result3['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Step 3 - Resubmission:</strong> Admin notified of resubmission<br>
            " . ($result3['success'] ? '✅ Success' : '❌ Failed') . "
          </div>";
    sleep(2);
    
    // Step 4: Admin approves (alumni notification)
    $result4 = send_approval_notification($gmail, 'Quien Bisnar', '2020', 'Software Engineer', 'Microsoft');
    echo "<div class='" . ($result4['success'] ? 'success' : 'error') . "' style='padding: 10px; margin: 5px 0;'>
            <strong>Step 4 - Approval:</strong> Alumni notified of approval<br>
            " . ($result4['success'] ? '✅ Success' : '❌ Failed') . "
          </div>";
    
    echo "<p><strong>🎉 Complete workflow simulation finished! Check your Gmail for the sequence.</strong></p>";
    echo "</div>";
}

function test_reminder_schedule_logic() {
    global $conn;
    
    echo "<div class='test-container'>
            <h3>⏰ Reminder Schedule Logic Test</h3>";
    
    require_once 'api/notification/scheduled_reminders.php';
    
    // Test alumni who need reminders
    $alumni_needing_reminders = getAlumniForReminders($conn);
    echo "<p><strong>Alumni needing reminders:</strong> " . count($alumni_needing_reminders) . "</p>";
    
    // Test submission schedule
    $schedule = getSubmissionSchedule($conn);
    if ($schedule) {
        echo "<p><strong>Current Schedule:</strong></p>";
        echo "<ul>
                <li>Open: " . ($schedule['is_open'] ? 'Yes' : 'No') . "</li>
                <li>Manual Override: " . ($schedule['manual_override'] ? 'Yes' : 'No') . "</li>
                <li>Open Date: " . ($schedule['open_date'] ?: 'Not set') . "</li>
                <li>Close Date: " . ($schedule['close_date'] ?: 'Not set') . "</li>
              </ul>";
    }
    
    // Test scheduled reminders function
    $result = runScheduledReminders($conn);
    echo "<p><strong>Scheduled Reminders Result:</strong> $result</p>";
    
    echo "</div>";
}

function test_batch_notifications($gmail, $count) {
    echo "<div class='test-container'>
            <h3>📊 Batch Notification Test ($count iterations)</h3>";
    
    $success_count = 0;
    
    for ($i = 1; $i <= $count; $i++) {
        $result = send_profile_update_reminder($gmail, "Test User $i", 2020 + $i);
        
        if ($result['success']) {
            $success_count++;
            echo "<div class='success' style='padding: 8px; margin: 3px 0;'>
                    ✅ Test $i: Success - Sent to Gmail
                  </div>";
        } else {
            echo "<div class='error' style='padding: 8px; margin: 3px 0;'>
                    ❌ Test $i: Failed - " . $result['error'] . "
                  </div>";
        }
        
        sleep(1); // Rate limiting
    }
    
    echo "<p><strong>Batch Test Complete:</strong> $success_count/$count successful</p>";
    echo "</div>";
}
?>