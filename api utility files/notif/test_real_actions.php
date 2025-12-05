<?php
session_start();
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

// Simulate real user actions
echo "<!DOCTYPE html>
<html>
<head>
    <title>Real Action Notification Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .action { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #10B981; }
        .error { border-left: 4px solid #EF4444; }
        button { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <h1>🎯 Real Action Notification Test</h1>
    
    <div class='action'>
        <h3>Test 1: Admin Opens Submissions</h3>
        <form method='POST'>
            <button type='submit' name='admin_open_submissions'>Simulate Admin Opening Submissions</button>
        </form>
    </div>

    <div class='action'>
        <h3>Test 2: Alumni Submits Profile</h3>
        <form method='POST'>
            <select name='submission_type'>
                <option value='first_time'>First Time Submission</option>
                <option value='resubmission'>Resubmission (After Rejection)</option>
                <option value='regular_update'>Regular Update</option>
            </select>
            <button type='submit' name='alumni_submit_profile'>Simulate Alumni Submission</button>
        </form>
    </div>

    <div class='action'>
        <h3>Test 3: Admin Reviews Submission</h3>
        <form method='POST'>
            <select name='review_action'>
                <option value='approve'>Approve Submission</option>
                <option value='reject'>Reject Submission</option>
            </select>
            <input type='text' name='rejection_reason' placeholder='Rejection reason (if rejecting)'>
            <button type='submit' name='admin_review'>Simulate Admin Review</button>
        </form>
    </div>

    <div class='action'>
        <h3>Test 4: Scheduled Reminders</h3>
        <form method='POST'>
            <button type='submit' name='test_reminders'>Test Scheduled Reminders</button>
        </form>
    </div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_open_submissions'])) {
        test_admin_opens_submissions();
    }
    
    if (isset($_POST['alumni_submit_profile'])) {
        test_alumni_submits_profile($_POST['submission_type']);
    }
    
    if (isset($_POST['admin_review'])) {
        test_admin_reviews_submission($_POST['review_action'], $_POST['rejection_reason'] ?? '');
    }
    
    if (isset($_POST['test_reminders'])) {
        test_scheduled_reminders();
    }
}

echo "</body></html>";

function test_admin_opens_submissions() {
    global $conn;
    
    echo "<div class='action success'>";
    echo "<h4>🔓 Admin Opening Submissions - Notification Flow</h4>";
    
    // Get alumni who need reminders
    $alumni_to_notify = get_alumni_for_reminders($conn);
    $notification_count = 0;
    
    foreach ($alumni_to_notify as $alumni) {
        $result = send_profile_update_reminder(
            $alumni['alumni_email'],
            $alumni['alumni_name'],
            $alumni['graduation_year']
        );
        
        if ($result['success']) {
            $notification_count++;
            echo "<p>✅ Sent to: {$alumni['alumni_name']} ({$alumni['alumni_email']})</p>";
        } else {
            echo "<p>❌ Failed for: {$alumni['alumni_name']} - {$result['error']}</p>";
        }
    }
    
    echo "<p><strong>Total notifications sent:</strong> $notification_count</p>";
    echo "</div>";
}

function test_alumni_submits_profile($submission_type) {
    global $conn;
    
    echo "<div class='action success'>";
    echo "<h4>📝 Alumni Profile Submission - Notification Flow</h4>";
    
    // Get a real alumni from database for testing
    $alumni_query = $conn->query("SELECT u.user_id, u.name, u.email, u.batch_year 
                                 FROM users u 
                                 WHERE u.role = 'alumni' 
                                 LIMIT 1");
    
    if ($alumni_query->num_rows > 0) {
        $alumni = $alumni_query->fetch_assoc();
        $admin_emails = get_admin_emails($conn);
        
        foreach ($admin_emails as $admin_email) {
            switch ($submission_type) {
                case 'first_time':
                    $result = send_new_submission_admin_notification(
                        $admin_email,
                        $alumni['name'],
                        $alumni['email'],
                        $alumni['batch_year'],
                        'Employed'
                    );
                    $action = "First-time submission";
                    break;
                    
                case 'resubmission':
                    $result = send_resubmission_admin_notification(
                        $admin_email,
                        $alumni['name'],
                        $alumni['email'],
                        $alumni['batch_year'],
                        'Previous rejection reason'
                    );
                    $action = "Resubmission after rejection";
                    break;
                    
                case 'regular_update':
                    $result = send_update_admin_notification(
                        $admin_email,
                        $alumni['name'],
                        $alumni['email'],
                        $alumni['batch_year'],
                        'Self-Employed'
                    );
                    $action = "Regular update";
                    break;
            }
            
            if ($result['success']) {
                echo "<p>✅ $action notification sent to admin: $admin_email</p>";
            } else {
                echo "<p>❌ Failed to send $action notification to $admin_email - {$result['error']}</p>";
            }
        }
    } else {
        echo "<p>❌ No alumni found in database for testing</p>";
    }
    
    echo "</div>";
}

function test_admin_reviews_submission($action, $rejection_reason) {
    global $conn;
    
    echo "<div class='action success'>";
    echo "<h4>📋 Admin Review - Notification Flow</h4>";
    
    // Get a real alumni from database for testing
    $alumni_query = $conn->query("SELECT u.user_id, u.name, u.email, u.batch_year 
                                 FROM users u 
                                 WHERE u.role = 'alumni' 
                                 LIMIT 1");
    
    if ($alumni_query->num_rows > 0) {
        $alumni = $alumni_query->fetch_assoc();
        
        if ($action === 'approve') {
            $result = send_approval_notification(
                $alumni['email'],
                $alumni['name'],
                $alumni['batch_year'],
                'Software Engineer',
                'Tech Company Inc.'
            );
            $action_text = "approval";
        } else {
            $result = send_rejection_notification(
                $alumni['email'],
                $alumni['name'],
                $alumni['batch_year'],
                $rejection_reason ?: 'Missing required information'
            );
            $action_text = "rejection";
        }
        
        if ($result['success']) {
            echo "<p>✅ $action_text notification sent to alumni: {$alumni['name']} ({$alumni['email']})</p>";
        } else {
            echo "<p>❌ Failed to send $action_text notification - {$result['error']}</p>";
        }
    } else {
        echo "<p>❌ No alumni found in database for testing</p>";
    }
    
    echo "</div>";
}

function test_scheduled_reminders() {
    echo "<div class='action success'>";
    echo "<h4>⏰ Scheduled Reminders Test</h4>";
    
    // Include and test the scheduled reminders
    require_once 'api/notification/scheduled_reminders.php';
    
    global $conn;
    $result = runScheduledReminders($conn);
    
    echo "<p><strong>Scheduled Reminders Result:</strong> $result</p>";
    echo "<p><em>Note: This tests the logic for 2-day, 1-day, and semi-annual reminders based on current schedule</em></p>";
    echo "</div>";
}
?>