<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
use NotificationAPI\NotificationAPI;

/**
 * Initialize NotificationAPI with your credentials
 */
function initNotificationAPI() {
    return new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3", // Client ID
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av" // Client Secret
    );
}

/**
 * Send notification to alumni to update profile
 */
function sendProfileUpdateReminder($alumni_email, $alumni_name, $graduation_year, $alumni_portal_link) {
    $notificationapi = initNotificationAPI();
    
    try {
        $result = $notificationapi->send([
            'type' => 'alumni_employment_tracking_update_your_profile',
            'to' => [
                'id' => $alumni_email,
                'email' => $alumni_email
            ],
            'parameters' => [
                "alumni_name" => $alumni_name,
                "graduation_year" => $graduation_year,
                "alumni_portal_link" => $alumni_portal_link,
                "name" => $alumni_name
            ],
            'templateId' => 'template_one'
        ]);
        
        logNotification($alumni_email, 'template_one', 'sent', 'Profile update reminder sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($alumni_email, 'template_one', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Log notification attempts
 */
function logNotification($email, $template_id, $status, $message = '') {
    global $conn;
    
    if (!$conn) {
        error_log("Notification Log: $email | $template_id | $status | $message");
        return;
    }
    
    // Check if notification_logs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notification_logs'");
    if ($table_check && $table_check->num_rows > 0) {
        $query = "INSERT INTO notification_logs (email, template_id, status, error_message, sent_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        
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
?>