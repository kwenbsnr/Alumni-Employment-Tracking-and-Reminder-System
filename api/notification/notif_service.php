<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/config/notification_config.php';

use NotificationAPI\NotificationAPI;

// Initialize NotificationAPI
function initNotificationAPI() {
    return new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
}

// Send profile update reminder to alumni
function sendProfileUpdateReminder($alumni_email, $alumni_name, $graduation_year, $portal_link = '/alumni/alumni_dashboard.php') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_update_your_profile',
            'templateId' => 'template_one',
            'user' => [
                'id' => md5($alumni_email),
                'email' => $alumni_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "graduation_year" => $graduation_year,
                "alumni_portal_link" => $portal_link,
                "name" => $alumni_name
            ]
        ]);
        
        logNotification($alumni_email, 'template_one', 'sent', 'Profile update reminder sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($alumni_email, 'template_one', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send approval notification to alumni
function sendApprovalNotification($alumni_email, $alumni_name, $graduation_year, $current_position = '', $current_company = '') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_profile_approved',
            'templateId' => 'template_approved',
            'user' => [
                'id' => md5($alumni_email),
                'email' => $alumni_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "graduation_year" => $graduation_year,
                "current_position" => $current_position,
                "current_company" => $current_company,
                "employment_status" => "Approved",
                "name" => $alumni_name
            ]
        ]);
        
        logNotification($alumni_email, 'template_approved', 'sent', 'Approval notification sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($alumni_email, 'template_approved', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send rejection notification to alumni
function sendRejectionNotification($alumni_email, $alumni_name, $graduation_year, $rejection_reason, $resubmission_link = '/alumni/update_profile.php') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_profile_rejected',
            'templateId' => 'template_rejected',
            'user' => [
                'id' => md5($alumni_email),
                'email' => $alumni_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "graduation_year" => $graduation_year,
                "rejection_reason" => $rejection_reason,
                "resubmission_link" => $resubmission_link,
                "name" => $alumni_name
            ]
        ]);
        
        logNotification($alumni_email, 'template_rejected', 'sent', 'Rejection notification sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($alumni_email, 'template_rejected', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send resubmission notification to admin
function sendResubmissionAdminNotification($admin_email, $alumni_name, $alumni_email, $graduation_year, $admin_review_link = '/admin/batch_alumni.php') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_resubmission_admin',
            'templateId' => 'alum_resubmit_admin_notif',
            'user' => [
                'id' => md5($admin_email),
                'email' => $admin_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "alumni_email" => $alumni_email,
                "graduation_year" => $graduation_year,
                "admin_review_link" => $admin_review_link,
                "name" => "Administrator"
            ]
        ]);
        
        logNotification($admin_email, 'alum_resubmit_admin_notif', 'sent', 'Resubmission admin notification sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($admin_email, 'alum_resubmit_admin_notif', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send update notification to admin
function sendUpdateAdminNotification($admin_email, $alumni_name, $alumni_email, $graduation_year, $admin_review_link = '/admin/batch_alumni.php') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_annual_update_admin',
            'templateId' => 'alum_submit_update_admin_notif',
            'user' => [
                'id' => md5($admin_email),
                'email' => $admin_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "alumni_email" => $alumni_email,
                "graduation_year" => $graduation_year,
                "admin_review_link" => $admin_review_link,
                "name" => "Administrator"
            ]
        ]);
        
        logNotification($admin_email, 'alum_submit_update_admin_notif', 'sent', 'Update admin notification sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($admin_email, 'alum_submit_update_admin_notif', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send new submission notification to admin
function sendNewSubmissionAdminNotification($admin_email, $alumni_name, $alumni_email, $graduation_year, $admin_review_link = '/admin/batch_alumni.php') {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_new_submission_admin',
            'templateId' => 'template_admin_notif',
            'user' => [
                'id' => md5($admin_email),
                'email' => $admin_email
            ],
            'mergeTags' => [
                "alumni_name" => $alumni_name,
                "alumni_email" => $alumni_email,
                "graduation_year" => $graduation_year,
                "admin_review_link" => $admin_review_link,
                "name" => "Administrator"
            ]
        ]);
        
        logNotification($admin_email, 'template_admin_notif', 'sent', 'New submission admin notification sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($admin_email, 'template_admin_notif', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get alumni who need reminders
function getAlumniForReminders($conn) {
    $alumni = [];
    
    $query = "
        SELECT u.user_id, u.name as alumni_name, u.email as alumni_email, 
               u.batch_year as graduation_year, ap.employment_status,
               ap.last_profile_update, ap.submission_status
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.role = 'alumni' 
        AND ap.submission_status != 'Approved'
        AND (ap.last_profile_update IS NULL OR 
             ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH))
        ORDER BY u.batch_year DESC, u.name
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alumni[] = $row;
        }
    }
    
    return $alumni;
}

// Get admin emails from database
function getAdminEmails($conn) {
    $emails = [];
    $query = "SELECT email FROM users WHERE role = 'admin'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
    }
    
    return $emails;
}

// Log notification attempts
function logNotification($email, $template_id, $status, $message = '') {
    global $conn;
    
    if (!$conn) {
        error_log("Notification Log: $email | $template_id | $status | $message");
        return;
    }
    
    $table_check = $conn->query("SHOW TABLES LIKE 'notification_logs'");
    if ($table_check && $table_check->num_rows > 0) {
        $query = "INSERT INTO notification_logs (email, template_id, status, error_message, sent_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssss", $email, $template_id, $status, $message);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        error_log("Notification Log: $email | $template_id | $status | $message");
    }
}

// Test function - run this file directly to test
function testNotificationService() {
    global $conn;
    
    echo "<h3>Testing Notification Service</h3>";
    
    // Test 1: Profile Update Reminder
    echo "Test 1: Sending Profile Update Reminder... ";
    $result1 = sendProfileUpdateReminder('test@example.com', 'John Doe', '2020');
    echo $result1['success'] ? "✅ SUCCESS<br>" : "❌ FAILED: " . $result1['error'] . "<br>";
    
    // Test 2: Approval Notification
    echo "Test 2: Sending Approval Notification... ";
    $result2 = sendApprovalNotification('test@example.com', 'John Doe', '2020', 'Developer', 'Tech Co');
    echo $result2['success'] ? "✅ SUCCESS<br>" : "❌ FAILED: " . $result2['error'] . "<br>";
    
    // Test 3: Get Alumni for Reminders
    echo "Test 3: Getting Alumni for Reminders... ";
    $alumni = getAlumniForReminders($conn);
    echo "✅ Found " . count($alumni) . " alumni needing reminders<br>";
    
    // Test 4: Get Admin Emails
    echo "Test 4: Getting Admin Emails... ";
    $admins = getAdminEmails($conn);
    echo "✅ Found " . count($admins) . " admin emails<br>";
    
    echo "<h4>🎉 Notification Service Test Complete!</h4>";
}

// Auto-run test if this file is executed directly
if (basename($_SERVER['PHP_SELF']) == 'notif_service.php') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/connect.php';
    testNotificationService();
}
?>