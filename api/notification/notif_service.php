<?php
require_once '../../../vendor/autoload.php';
use NotificationAPI\NotificationAPI;

class NotificationService {
    private $notificationapi;
    private $base_url = "#"; 
    
    public function __construct() {
        $this->notificationapi = new NotificationAPI(
            "ls4kt1i6t2hhh7rxd51k00rjj3",
            "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
        );
    }
    
    /**
     * Send profile update reminder to alumni
     */
    public function sendAlumniUpdateReminder($alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $alumni_data['alumni_email'],
                    'email' => $alumni_data['alumni_email']
                ],
                'parameters' => $this->prepareAlumniParameters($alumni_data),
                'templateId' => 'template_one'
            ]);
            
            $this->logNotification('alumni_update_reminder', $alumni_data['alumni_email'], true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('alumni_update_reminder', $alumni_data['alumni_email'], false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send approval notification to alumni
     */
    public function sendApprovalNotification($alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $alumni_data['alumni_email'],
                    'email' => $alumni_data['alumni_email']
                ],
                'parameters' => $this->prepareAlumniParameters($alumni_data),
                'templateId' => 'template_approved'
            ]);
            
            $this->logNotification('alumni_approval', $alumni_data['alumni_email'], true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('alumni_approval', $alumni_data['alumni_email'], false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send rejection notification to alumni
     */
    public function sendRejectionNotification($alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $alumni_data['alumni_email'],
                    'email' => $alumni_data['alumni_email']
                ],
                'parameters' => $this->prepareAlumniParameters($alumni_data),
                'templateId' => 'template_rejected'
            ]);
            
            $this->logNotification('alumni_rejection', $alumni_data['alumni_email'], true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('alumni_rejection', $alumni_data['alumni_email'], false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send admin notification for alumni resubmission
     */
    public function sendAdminResubmissionAlert($admin_email, $alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $admin_email,
                    'email' => $admin_email
                ],
                'parameters' => $this->prepareAdminParameters($alumni_data),
                'templateId' => 'alum_resubmit_admin_notif'
            ]);
            
            $this->logNotification('admin_resubmission_alert', $admin_email, true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('admin_resubmission_alert', $admin_email, false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send admin notification for alumni update
     */
    public function sendAdminUpdateAlert($admin_email, $alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $admin_email,
                    'email' => $admin_email
                ],
                'parameters' => $this->prepareAdminParameters($alumni_data),
                'templateId' => 'alum_update_admin_notif'
            ]);
            
            $this->logNotification('admin_update_alert', $admin_email, true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('admin_update_alert', $admin_email, false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send admin notification for new alumni submission
     */
    public function sendAdminNewSubmissionAlert($admin_email, $alumni_data) {
        try {
            $result = $this->notificationapi->send([
                'type' => 'alumni_employment_tracking_update_your_profile',
                'to' => [
                    'id' => $admin_email,
                    'email' => $admin_email
                ],
                'parameters' => $this->prepareAdminParameters($alumni_data),
                'templateId' => 'template_admin_notif'
            ]);
            
            $this->logNotification('admin_new_submission', $admin_email, true);
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            $this->logNotification('admin_new_submission', $admin_email, false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Prepare parameters for alumni notifications
     */
    private function prepareAlumniParameters($alumni_data) {
        return [
            "alumni_name" => $alumni_data['alumni_name'] ?? '',
            "graduation_year" => $alumni_data['graduation_year'] ?? '',
            "original_rejection_date" => $alumni_data['original_rejection_date'] ?? '',
            "submission_date" => $alumni_data['submission_date'] ?? date('Y-m-d'),
            "current_position" => $alumni_data['current_position'] ?? '',
            "current_company" => $alumni_data['current_company'] ?? '',
            "alumni_email" => $alumni_data['alumni_email'] ?? '',
            "previous_rejection_reason" => $alumni_data['previous_rejection_reason'] ?? '',
            "admin_review_link" => $alumni_data['admin_review_link'] ?? $this->base_url . '/admin/batch_alumni.php',
            "employment_status" => $alumni_data['employment_status'] ?? '',
            "name" => $alumni_data['alumni_name'] ?? '',
            "alumni_portal_link" => $alumni_data['alumni_portal_link'] ?? $this->base_url . '/alumni/alumni_profile.php',
            "rejection_reason" => $alumni_data['rejection_reason'] ?? '',
            "resubmission_link" => $alumni_data['resubmission_link'] ?? $this->base_url . '/alumni/alumni_profile.php'
        ];
    }
    
    /**
     * Prepare parameters for admin notifications
     */
    private function prepareAdminParameters($alumni_data) {
        return [
            "alumni_name" => $alumni_data['alumni_name'] ?? '',
            "graduation_year" => $alumni_data['graduation_year'] ?? '',
            "alumni_email" => $alumni_data['alumni_email'] ?? '',
            "employment_status" => $alumni_data['employment_status'] ?? '',
            "admin_review_link" => $alumni_data['admin_review_link'] ?? $this->base_url . '/admin/batch_alumni.php?batch=' . urlencode($alumni_data['graduation_year'] ?? ''),
            "name" => $alumni_data['alumni_name'] ?? '',
            "previous_rejection_reason" => $alumni_data['previous_rejection_reason'] ?? ''
        ];
    }
    
    /**
     * Log notification attempts
     */
    private function logNotification($type, $recipient, $success, $error = '') {
        $log_entry = date('Y-m-d H:i:s') . " - {$type} to {$recipient} - " . 
                    ($success ? 'SUCCESS' : 'FAILED') . 
                    ($error ? " - Error: {$error}" : '');
        
        error_log($log_entry);
        
        // Optional: Write to dedicated log file
        file_put_contents('notification_log.txt', $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// Simple function wrappers
function sendAlumniUpdateReminder($alumni_data) {
    $service = new NotificationService();
    return $service->sendAlumniUpdateReminder($alumni_data);
}

function sendApprovalNotification($alumni_data) {
    $service = new NotificationService();
    return $service->sendApprovalNotification($alumni_data);
}

function sendRejectionNotification($alumni_data) {
    $service = new NotificationService();
    return $service->sendRejectionNotification($alumni_data);
}

function sendAdminResubmissionAlert($admin_email, $alumni_data) {
    $service = new NotificationService();
    return $service->sendAdminResubmissionAlert($admin_email, $alumni_data);
}

function sendAdminUpdateAlert($admin_email, $alumni_data) {
    $service = new NotificationService();
    return $service->sendAdminUpdateAlert($admin_email, $alumni_data);
}

function sendAdminNewSubmissionAlert($admin_email, $alumni_data) {
    $service = new NotificationService();
    return $service->sendAdminNewSubmissionAlert($admin_email, $alumni_data);
}
?>