<?php
session_start();
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Notification API Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-container { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #10B981; background: #ECFDF5; }
        .error { border-left: 4px solid #EF4444; background: #FEF2F2; }
        .warning { border-left: 4px solid #F59E0B; background: #FFFBEB; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        button { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #2563EB; }
        .test-section { margin: 20px 0; padding: 15px; background: #EFF6FF; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>🔔 Notification API Integration Test</h1>
    <p>Testing all notification triggers and scenarios</p>";

// Test 1: Basic Notification Service
echo "<div class='test-section'>
        <h2>1. Basic Notification Service Test</h2>";

$test_email = "test@example.com";
$test_result = send_notification('template_one', $test_email, [
    'alumni_name' => 'Test User',
    'graduation_year' => '2020'
]);

echo "<div class='test-container " . ($test_result['success'] ? 'success' : 'error') . "'>
        <h3>Basic Notification Test</h3>
        <div class='test-result'>
            <strong>Status:</strong> " . ($test_result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "<br>
            <strong>Template:</strong> template_one<br>
            <strong>Recipient:</strong> $test_email<br>
            " . (!$test_result['success'] ? "<strong>Error:</strong> " . $test_result['error'] : "") . "
        </div>
    </div>";

// Test 2: Helper Functions
echo "<div class='test-section'>
        <h2>2. Helper Functions Test</h2>";

// Test get_admin_emails
$admin_emails = get_admin_emails($conn);
echo "<div class='test-container " . (count($admin_emails) > 0 ? 'success' : 'warning') . "'>
        <h3>Admin Emails Retrieval</h3>
        <div class='test-result'>
            <strong>Found:</strong> " . count($admin_emails) . " admin emails<br>
            <strong>Emails:</strong> " . implode(', ', $admin_emails) . "
        </div>
    </div>";

// Test get_alumni_for_reminders
$alumni_reminders = get_alumni_for_reminders($conn);
echo "<div class='test-container'>
        <h3>Alumni Reminders Check</h3>
        <div class='test-result'>
            <strong>Alumni needing reminders:</strong> " . count($alumni_reminders) . "<br>
            <strong>Details:</strong><br>";
foreach (array_slice($alumni_reminders, 0, 3) as $alumni) {
    echo "&nbsp;&nbsp;- {$alumni['alumni_name']} ({$alumni['alumni_email']}) - {$alumni['submission_status']}<br>";
}
if (count($alumni_reminders) > 3) echo "&nbsp;&nbsp;... and " . (count($alumni_reminders) - 3) . " more";
echo "    </div>
    </div>";

echo "</div>";

// Test 3: Template-Specific Tests
echo "<div class='test-section'>
        <h2>3. Template-Specific Notification Tests</h2>
        <form method='POST'>
            <button type='submit' name='test_all_templates'>Test All Templates</button>
            <button type='submit' name='test_alumni_scenarios'>Test Alumni Scenarios</button>
            <button type='submit' name='test_admin_scenarios'>Test Admin Scenarios</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_all_templates'])) {
        test_all_templates();
    }
    if (isset($_POST['test_alumni_scenarios'])) {
        test_alumni_scenarios();
    }
    if (isset($_POST['test_admin_scenarios'])) {
        test_admin_scenarios();
    }
}

echo "</div>";

// Test 4: Database Integration Test
echo "<div class='test-section'>
        <h2>4. Database Integration Test</h2>";

// Test first-time submission detection
$test_user_id = 1; // Change to a real user ID from your database
$is_first_time = is_first_time_submission($conn, $test_user_id);
$was_rejected = was_submission_rejected($conn, $test_user_id);

echo "<div class='test-container'>
        <h3>Submission Status Detection</h3>
        <div class='test-result'>
            <strong>User ID $test_user_id:</strong><br>
            - First time submission: " . ($is_first_time ? 'Yes' : 'No') . "<br>
            - Previously rejected: " . ($was_rejected ? 'Yes' : 'No') . "
        </div>
    </div>";

echo "</div>";

// Test 5: Real User Action Simulation
echo "<div class='test-section'>
        <h2>5. Real User Action Simulation</h2>
        <form method='POST'>
            <input type='email' name='simulate_email' placeholder='Enter test email' value='test@example.com' required>
            <input type='text' name='simulate_name' placeholder='Enter test name' value='Test User' required>
            <input type='text' name='simulate_year' placeholder='Graduation year' value='2020' required>
            <br><br>
            <button type='submit' name='simulate_approval'>Simulate Approval</button>
            <button type='submit' name='simulate_rejection'>Simulate Rejection</button>
            <button type='submit' name='simulate_update'>Simulate Profile Update</button>
            <button type='submit' name='simulate_resubmission'>Simulate Resubmission</button>
        </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simulate_approval'])) {
        $email = $_POST['simulate_email'];
        $name = $_POST['simulate_name'];
        $year = $_POST['simulate_year'];
        
        $result = send_approval_notification($email, $name, $year, 'Software Engineer', 'Tech Company Inc.');
        show_test_result("Approval Notification", $result);
    }
    
    if (isset($_POST['simulate_rejection'])) {
        $email = $_POST['simulate_email'];
        $name = $_POST['simulate_name'];
        $year = $_POST['simulate_year'];
        
        $result = send_rejection_notification($email, $name, $year, 'Missing required documents');
        show_test_result("Rejection Notification", $result);
    }
    
    if (isset($_POST['simulate_update'])) {
        $email = $_POST['simulate_email'];
        $name = $_POST['simulate_name'];
        $year = $_POST['simulate_year'];
        
        // Send to first admin email
        $admin_emails = get_admin_emails($conn);
        if (!empty($admin_emails)) {
            $result = send_update_admin_notification($admin_emails[0], $name, $email, $year, 'Employed');
            show_test_result("Update Admin Notification", $result);
        }
    }
    
    if (isset($_POST['simulate_resubmission'])) {
        $email = $_POST['simulate_email'];
        $name = $_POST['simulate_name'];
        $year = $_POST['simulate_year'];
        
        // Send to first admin email
        $admin_emails = get_admin_emails($conn);
        if (!empty($admin_emails)) {
            $result = send_resubmission_admin_notification($admin_emails[0], $name, $email, $year, 'Incomplete information');
            show_test_result("Resubmission Admin Notification", $result);
        }
    }
}

echo "</div>";

echo "</body></html>";

// Test Functions
function test_all_templates() {
    global $conn;
    
    $test_email = "test@example.com";
    $templates = [
        ['template_one', 'Profile Update Reminder'],
        ['template_approved', 'Approval Notification'],
        ['template_rejected', 'Rejection Notification'],
        ['alum_resubmit_admin_notif', 'Resubmission Admin Notification'],
        ['alum_update_admin_notif', 'Update Admin Notification'],
        ['template_admin_notif', 'New Submission Admin Notification']
    ];
    
    echo "<div class='test-container'>
            <h3>All Template Test Results</h3>";
    
    foreach ($templates as $template) {
        $result = send_notification($template[0], $test_email, [
            'alumni_name' => 'Test User',
            'graduation_year' => '2020',
            'rejection_reason' => 'Test reason',
            'employment_status' => 'Employed'
        ]);
        
        echo "<div class='test-result " . ($result['success'] ? 'success' : 'error') . "'>
                <strong>{$template[1]}:</strong> " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "
              </div>";
        sleep(1); // Avoid rate limiting
    }
    
    echo "</div>";
}

function test_alumni_scenarios() {
    $test_email = "alumni_test@example.com";
    
    echo "<div class='test-container'>
            <h3>Alumni Scenario Tests</h3>";
    
    // Test 1: Profile update reminder
    $result1 = send_profile_update_reminder($test_email, 'John Alumni', '2020');
    show_test_result("Profile Update Reminder", $result1);
    
    // Test 2: Approval notification
    $result2 = send_approval_notification($test_email, 'John Alumni', '2020', 'Developer', 'Tech Corp');
    show_test_result("Approval Notification", $result2);
    
    // Test 3: Rejection notification
    $result3 = send_rejection_notification($test_email, 'John Alumni', '2020', 'Missing documents');
    show_test_result("Rejection Notification", $result3);
    
    echo "</div>";
}

function test_admin_scenarios() {
    global $conn;
    
    $admin_emails = get_admin_emails($conn);
    if (empty($admin_emails)) {
        echo "<div class='test-container error'><strong>No admin emails found for testing</strong></div>";
        return;
    }
    
    $admin_email = $admin_emails[0];
    
    echo "<div class='test-container'>
            <h3>Admin Scenario Tests</h3>";
    
    // Test 1: New submission notification
    $result1 = send_new_submission_admin_notification($admin_email, 'New Alumni', 'new@example.com', '2023', 'Student');
    show_test_result("New Submission Notification", $result1);
    
    // Test 2: Update notification
    $result2 = send_update_admin_notification($admin_email, 'Existing Alumni', 'existing@example.com', '2021', 'Employed');
    show_test_result("Update Notification", $result2);
    
    // Test 3: Resubmission notification
    $result3 = send_resubmission_admin_notification($admin_email, 'Rejected Alumni', 'rejected@example.com', '2022', 'Previous rejection reason');
    show_test_result("Resubmission Notification", $result3);
    
    echo "</div>";
}

function show_test_result($test_name, $result) {
    echo "<div class='test-result " . ($result['success'] ? 'success' : 'error') . "'>
            <strong>$test_name:</strong> " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "
            " . (!$result['success'] ? "<br><small>Error: " . $result['error'] . "</small>" : "") . "
          </div>";
}
?>