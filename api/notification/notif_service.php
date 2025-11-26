<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/config/notification_config.php';

use NotificationAPI\NotificationAPI;

/**
 * Unified Notification Service
 */
class NotificationService {
    private $notificationAPI;
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
        $this->notificationAPI = new NotificationAPI(
            NOTIFICATIONAPI_CLIENT_ID,
            NOTIFICATIONAPI_CLIENT_SECRET
        );
    }

    /**
     * Send notification using template mapping
     */
    public function sendNotification($templateKey, $recipientEmail, $recipientName = '', $parameters = []) {
        try {
            if (!isset(TEMPLATE_MAPPINGS[$templateKey])) {
                throw new Exception("Invalid template key: $templateKey");
            }

            $notificationType = TEMPLATE_MAPPINGS[$templateKey];
            
            $result = $this->notificationAPI->send([
                'notificationId' => $notificationType,
                'templateId' => $templateKey,
                'user' => [
                    'id' => md5($recipientEmail),
                    'email' => $recipientEmail,
                    'name' => $recipientName
                ],
                'mergeTags' => $parameters
            ]);

            $this->logNotification($recipientEmail, $templateKey, 'sent');
            return ['success' => true, 'data' => $result];

        } catch (Exception $e) {
            $this->logNotification($recipientEmail, $templateKey, 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send profile update reminder to alumni
     */
    public function sendProfileUpdateReminder($alumniEmail, $alumniName, $graduationYear, $portalLink = '#') {
        $parameters = [
            'alumni_name' => $alumniName,
            'graduation_year' => $graduationYear,
            'alumni_portal_link' => $portalLink,
            'name' => $alumniName
        ];

        return $this->sendNotification('template_one', $alumniEmail, $alumniName, $parameters);
    }

    /**
     * Send approval notification to alumni
     */
    public function sendApprovalNotification($alumniEmail, $alumniName, $graduationYear, $position = '', $company = '') {
        $parameters = [
            'alumni_name' => $alumniName,
            'graduation_year' => $graduationYear,
            'current_position' => $position,
            'current_company' => $company,
            'employment_status' => 'Approved',
            'name' => $alumniName
        ];

        return $this->sendNotification('template_approved', $alumniEmail, $alumniName, $parameters);
    }

    /**
     * Send rejection notification to alumni
     */
    public function sendRejectionNotification($alumniEmail, $alumniName, $graduationYear, $rejectionReason, $resubmissionLink = '#') {
        $parameters = [
            'alumni_name' => $alumniName,
            'graduation_year' => $graduationYear,
            'rejection_reason' => $rejectionReason,
            'resubmission_link' => $resubmissionLink,
            'name' => $alumniName
        ];

        return $this->sendNotification('template_rejected', $alumniEmail, $alumniName, $parameters);
    }

    /**
     * Send new submission notification to admin
     */
    public function sendNewSubmissionAdminNotification($adminEmail, $alumniName, $alumniEmail, $graduationYear, $reviewLink = '#') {
        $parameters = [
            'alumni_name' => $alumniName,
            'alumni_email' => $alumniEmail,
            'graduation_year' => $graduationYear,
            'admin_review_link' => $reviewLink,
            'name' => 'Administrator'
        ];

        return $this->sendNotification('template_admin_notif', $adminEmail, 'Administrator', $parameters);
    }

    /**
     * Log notification attempts
     */
    private function logNotification($email, $templateId, $status, $errorMessage = '') {
        if (!$this->conn) {
            error_log("Notification Log: $email | $templateId | $status | $errorMessage");
            return;
        }

        // Check if notification_logs table exists
        $tableCheck = $this->conn->query("SHOW TABLES LIKE 'notification_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $query = "INSERT INTO notification_logs (email, template_id, status, error_message, sent_at) 
                      VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ssss", $email, $templateId, $status, $errorMessage);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            error_log("Notification Log: $email | $templateId | $status | $errorMessage");
        }
    }

    /**
     * Get alumni who need reminders (haven't updated in 6 months)
     */
    public function getAlumniForReminders() {
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
        ";
        
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $alumni[] = $row;
            }
        }
        
        return $alumni;
    }
}

/**
 * Simple function wrapper for backward compatibility
 */
function sendProfileUpdateReminder($alumni_email, $alumni_name, $graduation_year, $portal_link = '#') {
    global $conn;
    $service = new NotificationService($conn);
    return $service->sendProfileUpdateReminder($alumni_email, $alumni_name, $graduation_year, $portal_link);
}
?>