<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

class NotificationHelper {
    private $conn;
    private $notificationAPI;

    public function __construct($connection) {
        $this->conn = $connection;
        
        // Initialize NotificationAPI with your credentials
        $this->notificationAPI = new NotificationAPI\NotificationAPI(
            "YOUR_PROJECT_ID",  // Replace with your actual project ID
            "YOUR_SECRET_KEY"   // Replace with your actual secret key
        );
    }

    /**
     * Get admin emails from database
     */
    public function getAdminEmails() {
        $emails = [];
        $query = "SELECT email FROM users WHERE role = 'admin' AND status = 'active'";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $emails[] = $row['email'];
            }
        }
        
        return $emails;
    }

    /**
     * Get alumni emails that need reminders
     */
    public function getAlumniForReminders() {
        $alumni = [];
        
        // Example: Get alumni who haven't updated their profile in the last year
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        
        $query = "SELECT email, full_name, last_profile_update 
                  FROM alumni 
                  WHERE (last_profile_update IS NULL OR last_profile_update < ?) 
                  AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $oneYearAgo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $alumni[] = $row;
            }
        }
        
        return $alumni;
    }

    /**
     * Send notification using NotificationAPI
     */
    public function sendNotification($templateId, $notificationId, $email, $parameters = []) {
        try {
            $result = $this->notificationAPI->send([
                'notificationId' => $notificationId,
                'templateId' => $templateId,
                'user' => [
                    'id' => md5($email), // Unique user ID
                    'email' => $email
                ],
                'mergeTags' => $parameters
            ]);
            
            // Log the notification
            $this->logNotification($email, $templateId, $notificationId, 'sent');
            
            return $result;
            
        } catch (Exception $e) {
            // Log the error
            $this->logNotification($email, $templateId, $notificationId, 'failed', $e->getMessage());
            
            error_log("Notification failed for $email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log notification attempts
     */
    private function logNotification($email, $templateId, $notificationId, $status, $error = null) {
        $query = "INSERT INTO notification_logs (email, template_id, notification_id, status, error_message, sent_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssss", $email, $templateId, $notificationId, $status, $error);
        $stmt->execute();
    }

    /**
     * Send bulk notifications to alumni
     */
    public function sendBulkAlumniReminders() {
        $alumni = $this->getAlumniForReminders();
        $results = [
            'success' => 0,
            'failed' => 0,
            'total' => count($alumni)
        ];

        foreach ($alumni as $alumnus) {
            $params = [
                'alumni_name' => $alumnus['full_name'],
                'graduation_year' => date('Y'), // You might want to get this from DB
                'update_link' => 'http://localhost/Alumni-Employment-Tracking-and-Reminder-System/update-profile.php'
            ];

            $result = $this->sendNotification(
                'template_one', // Your template ID
                'alumni_annual_reminder', // Your notification ID
                $alumnus['email'],
                $params
            );

            if ($result) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Test connection to NotificationAPI
     */
    public function testConnection() {
        try {
            // Simple test - try to get templates (this will validate credentials)
            $templates = $this->notificationAPI->getTemplates();
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>