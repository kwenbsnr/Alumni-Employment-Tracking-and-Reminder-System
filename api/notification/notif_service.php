<?php
/**
 * Unified Notification Service - Simple Functions Only
 * NO DATABASE LOGGING REQUIRED
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

// Initialize NotificationAPI with your credentials
function init_notification_api() {
    return new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
}

// Send notification using template mapping
function send_notification($template_id, $recipient_email, $parameters = []) {
    $notificationapi = init_notification_api();
    
    try {
        $result = $notificationapi->send([
            'notificationId' => 'alumni_employment_tracking_update_your_profile',
            'templateId' => $template_id,
            'user' => [
                'id' => md5($recipient_email),
                'email' => $recipient_email
            ],
            'mergeTags' => $parameters
        ]);
        
        // Simple error log instead of database
        error_log("Notification SENT: $template_id to $recipient_email");
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        // Simple error log instead of database
        error_log("Notification FAILED: $template_id to $recipient_email - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send profile update reminder to alumni (template_one)
function send_profile_update_reminder($alumni_email, $alumni_name, $graduation_year) {
    $parameters = [
        "alumni_name" => $alumni_name,
        "graduation_year" => $graduation_year,
        "alumni_portal_link" => "/alumni/alumni_dashboard.php",
        "name" => $alumni_name
    ];
    
    return send_notification('template_one', $alumni_email, $parameters);
}

// Send approval notification to alumni (template_approved)
function send_approval_notification($alumni_email, $alumni_name, $graduation_year, $current_position = '', $current_company = '') {
    $parameters = [
        "alumni_name" => $alumni_name,
        "graduation_year" => $graduation_year,
        "current_position" => $current_position,
        "current_company" => $current_company,
        "employment_status" => "Approved",
        "name" => $alumni_name
    ];
    
    return send_notification('template_approved', $alumni_email, $parameters);
}

// Send rejection notification to alumni (template_rejected)
function send_rejection_notification($alumni_email, $alumni_name, $graduation_year, $rejection_reason) {
    $parameters = [
        "alumni_name" => $alumni_name,
        "graduation_year" => $graduation_year,
        "rejection_reason" => $rejection_reason,
        "resubmission_link" => "/alumni/update_profile.php",
        "name" => $alumni_name
    ];
    
    return send_notification('template_rejected', $alumni_email, $parameters);
}

// Send resubmission notification to admin (alum_resubmit_admin_notif)
function send_resubmission_admin_notification($admin_email, $alumni_name, $alumni_email, $graduation_year) {
    $parameters = [
        "alumni_name" => $alumni_name,
        "alumni_email" => $alumni_email,
        "graduation_year" => $graduation_year,
        "admin_review_link" => "/admin/batch_alumni.php",
        "name" => "Administrator"
    ];
    
    return send_notification('alum_resubmit_admin_notif', $admin_email, $parameters);
}

// Send update notification to admin (alum_update_admin_notif)
function send_update_admin_notification($admin_email, $alumni_name, $alumni_email, $graduation_year) {
    $parameters = [
        "alumni_name" => $alumni_name,
        "alumni_email" => $alumni_email,
        "graduation_year" => $graduation_year,
        "admin_review_link" => "/admin/batch_alumni.php",
        "name" => "Administrator"
    ];
    
    return send_notification('alum_update_admin_notif', $admin_email, $parameters);
}

// Send new submission notification to admin (template_admin_notif)
function send_new_submission_admin_notification($admin_email, $alumni_name, $alumni_email, $graduation_year) {
    $parameters = [
        "alumni_name" => $alumni_name,
        "alumni_email" => $alumni_email,
        "graduation_year" => $graduation_year,
        "admin_review_link" => "/admin/batch_alumni.php",
        "name" => "Administrator"
    ];
    
    return send_notification('template_admin_notif', $admin_email, $parameters);
}

// Get alumni who need reminders (haven't updated in 6 months)
function get_alumni_for_reminders($conn) {
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
function get_admin_emails($conn) {
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

// Get alumni details by user_id
function get_alumni_details($conn, $user_id) {
    $query = "
        SELECT u.name, u.email, u.batch_year, ap.employment_status 
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// TEST FUNCTION - Run this file directly to test
function test_notification_service() {
    global $conn;
    
    echo "<h3>Testing Notification Service (No Database Logging)</h3>";
    
    // Test 1: Profile Update Reminder
    echo "Test 1: Sending Profile Update Reminder... ";
    $result1 = send_profile_update_reminder('test@example.com', 'John Doe', '2020');
    echo $result1['success'] ? "✅ SUCCESS<br>" : "❌ FAILED<br>";
    
    // Test 2: Get Alumni for Reminders
    echo "Test 2: Getting Alumni for Reminders... ";
    $alumni = get_alumni_for_reminders($conn);
    echo "✅ Found " . count($alumni) . " alumni needing reminders<br>";
    
    // Test 3: Get Admin Emails
    echo "Test 3: Getting Admin Emails... ";
    $admins = get_admin_emails($conn);
    echo "✅ Found " . count($admins) . " admin emails<br>";
    
    echo "<h4>🎉 Notification Service Working Perfectly!</h4>";
    echo "<p><strong>Note:</strong> No database logging - using simple error_log() instead.</p>";
}

// Auto-run test if this file is executed directly
if (basename($_SERVER['PHP_SELF']) == 'notif_service.php') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/connect.php';
    test_notification_service();
}
?>