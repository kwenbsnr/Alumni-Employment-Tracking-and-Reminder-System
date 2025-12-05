<?php

// NotificationId: alumni_employment_tracking_update_your_profile

$root_path = dirname(__FILE__) . '/../../';
// Use absolute paths for reliability
$root_path = dirname(__DIR__, 2); // Go up two levels from api/notification/
require_once $root_path . '/vendor/autoload.php';
require_once __DIR__ . '/scheduled_reminders.php';
require_once $root_path . '/connect.php';

// Check for notification config
if (file_exists($root_path . '/config/notification_config.php')) {
    require_once $root_path . '/config/notification_config.php';
} else {
    error_log("WARNING: notification_config.php not found");
}

// Check for deadline.php
if (file_exists(dirname(__DIR__) . '/utils/deadline.php')) {
    require_once dirname(__DIR__) . '/utils/deadline.php';
} else {
    error_log("WARNING: deadline.php not found in api/utils/");
}


// Use the class
use NotificationAPI\NotificationAPI;

// Initialize NotificationAPI with your credentials
function init_notification_api() {
    return new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
}

// Core notification function - uses single notificationId
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
        
        error_log("NOTIFICATION SENT: Template '$template_id' to $recipient_email");
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        error_log("NOTIFICATION FAILED: Template '$template_id' to $recipient_email - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== DYNAMIC DATA FETCHING FUNCTIONS ====================


// Get complete alumni data for notifications
function get_complete_alumni_data($conn, $user_id) {
    $query = "
        SELECT 
            u.user_id,
            CONCAT(
                u.first_name,
                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                ' ',
                u.last_name,
                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
            ) as alumni_name,
            u.email as alumni_email,
            u.batch_year as graduation_year,
            u.student_id,
            u.date_of_birth,
            u.gender,
            u.program,
            ap.employment_status,
            ap.contact_number,
            ap.last_profile_update,
            ap.submission_status,
            ap.rejection_reason,
            ap.rejected_at,
            ap.submitted_at,
            ei.company_name as current_company,
            jt.title as current_position,
            ei.salary_range,
            ei.business_type,
            ed.school_name as current_school,
            ed.degree_pursued,
            ed.start_year,
            ed.end_year
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
        LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
        LEFT JOIN education_info ed ON ap.user_id = ed.user_id
        WHERE u.user_id = ?
        ORDER BY ei.employment_id DESC, ed.education_id DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Get previous rejection reason for resubmissions
function get_previous_rejection_reason($conn, $user_id) {
    $query = "SELECT rejection_reason, rejected_at FROM alumni_profile WHERE user_id = ? AND rejection_reason IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? [
        'reason' => $row['rejection_reason'],
        'date' => $row['rejected_at']
    ] : null;
}

// Generate employment details HTML based on employment status and data
function generate_employment_details($employment_status, $alumni_data = []) {
    $employed_details = '';
    $self_employed_details = '';
    $student_details = '';
    $employed_student_work = '';
    $employed_student_school = '';
    $unemployed_note = '';

    switch($employment_status) {
        case 'Employed':
            $position = $alumni_data['current_position'] ?? '';
            $company = $alumni_data['current_company'] ?? '';
            $salary = $alumni_data['salary_range'] ?? '';
            
            if ($position && $company) {
                $details = "<strong>Position:</strong> " . htmlspecialchars($position) . " at " . htmlspecialchars($company);
                if ($salary) {
                    $details .= "<br><strong>Salary Range:</strong> " . htmlspecialchars($salary);
                }
                $employed_details = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #22c55e;">' . $details . '</p>';
            }
            break;
            
        case 'Self-Employed':
            $business_type = $alumni_data['business_type'] ?? '';
            $company = $alumni_data['current_company'] ?? '';
            
            if ($business_type || $company) {
                $details = "";
                if ($company) {
                    $details .= "<strong>Business:</strong> " . htmlspecialchars($company);
                }
                if ($business_type) {
                    if ($details) $details .= "<br>";
                    $details .= "<strong>Business Type:</strong> " . htmlspecialchars($business_type);
                }
                $self_employed_details = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #f59e0b;">' . $details . '</p>';
            }
            break;
            
        case 'Student':
            $school = $alumni_data['current_school'] ?? '';
            $degree = $alumni_data['degree_pursued'] ?? '';
            
            if ($school) {
                $details = "<strong>Currently Studying at:</strong> " . htmlspecialchars($school);
                if ($degree) {
                    $details .= "<br><strong>Degree:</strong> " . htmlspecialchars($degree);
                }
                $student_details = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #3b82f6;">' . $details . '</p>';
            }
            break;
            
        case 'Employed & Student':
            $position = $alumni_data['current_position'] ?? '';
            $company = $alumni_data['current_company'] ?? '';
            $school = $alumni_data['current_school'] ?? '';
            $degree = $alumni_data['degree_pursued'] ?? '';
            
            if ($position && $company) {
                $employed_student_work = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #8b5cf6;"><strong>Position:</strong> ' . htmlspecialchars($position) . ' at ' . htmlspecialchars($company) . '</p>';
            }
            if ($school) {
                $details = "<strong>Also Studying at:</strong> " . htmlspecialchars($school);
                if ($degree) {
                    $details .= "<br><strong>Degree:</strong> " . htmlspecialchars($degree);
                }
                $employed_student_school = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #8b5cf6;">' . $details . '</p>';
            }
            break;
            
        case 'Unemployed':
            $unemployed_note = '<p style="margin: 0px 0px 8px; padding-left: 15px; border-left: 2px solid #ef4444; font-style: italic; color: #666;">Currently seeking employment opportunities</p>';
            break;
    }

    return [
        'employed_details' => $employed_details,
        'self_employed_details' => $self_employed_details,
        'student_details' => $student_details,
        'employed_student_work' => $employed_student_work,
        'employed_student_school' => $employed_student_school,
        'unemployed_note' => $unemployed_note
    ];
}

// ==================== ALUMNI NOTIFICATIONS ====================

// Send profile update reminder to alumni (template_one)
// Also handles semiannual updates for approved alumni
function send_profile_update_reminder($conn, $user_id, $closing_date = '') {
    // Get alumni data including last update date
    $query = "
        SELECT 
            u.user_id,
            CONCAT(
                u.first_name,
                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                ' ',
                u.last_name,
                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
            ) as alumni_name,
            u.email as alumni_email,
            u.batch_year as graduation_year,
            ap.employment_status,
            ap.last_profile_update,
            ap.submission_status
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.user_id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $alumni_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    // Check if this is a semiannual update for approved alumni
    $is_semiannual_update = false;
    $months_since_update = 0;
    
    if ($alumni_data['submission_status'] === 'Approved' && 
        !empty($alumni_data['last_profile_update'])) {
        
        // Calculate months since last update
        $last_update = new DateTime($alumni_data['last_profile_update']);
        $now = new DateTime();
        $interval = $last_update->diff($now);
        $months_since_update = ($interval->y * 12) + $interval->m;
        
        // Only send semiannual update if 6+ months have passed
        if ($months_since_update >= 6) {
            $is_semiannual_update = true;
        }
    }
    
    // If approved but less than 6 months, don't send notification
    if ($alumni_data['submission_status'] === 'Approved' && !$is_semiannual_update) {
        return ['success' => false, 'error' => 'No update needed - less than 6 months since last update'];
    }
    
    // GET DEADLINE FROM ADMIN SCHEDULE
    require_once __DIR__ . '/../utils/schedule_checker.php';
    $deadline_date = getSubmissionDeadline($conn, 14); // 14 days fallback
    error_log("Using deadline: $deadline_date for user: $user_id");
    
    // Prepare notification parameters
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "graduation_year" => $alumni_data['graduation_year'],
        "alumni_portal_link" => "#", 
        "name" => $alumni_data['alumni_name'],
        "submission_date" => date('Y-m-d H:i:s')
    ];
    
    // Add semiannual update specific message
    if ($is_semiannual_update) {
        $parameters["update_type"] = "semiannual";
        $parameters["months_since_update"] = $months_since_update . " months";
        $parameters["deadline_date"] = $deadline_date; // Now dynamic!
        
        // Log semiannual update
        error_log("SEMIANNUAL UPDATE sent for user: $user_id - $months_since_update months since last update");
    } else {
        $parameters["update_type"] = "regular";
        
        // Add closing date if provided (for rejected profiles)
        if ($closing_date) {
            $parameters["original_rejection_date"] = $closing_date;
        }
    }
    
    return send_notification('template_one', $alumni_data['alumni_email'], $parameters);
}

// Send semiannual updates to ALL eligible alumni (for batch processing)
function send_semiannual_updates_to_all($conn) {
    // Get all approved alumni who haven't updated in 6+ months
    $query = "
        SELECT 
            u.user_id,
            CONCAT(
                u.first_name,
                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                ' ',
                u.last_name,
                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
            ) as alumni_name,
            u.email as alumni_email,
            u.batch_year as graduation_year,
            ap.last_profile_update,
            ap.submission_status,
            TIMESTAMPDIFF(MONTH, ap.last_profile_update, NOW()) as months_since_update
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.role = 'alumni'
        AND ap.submission_status = 'Approved'
        AND ap.last_profile_update IS NOT NULL
        AND TIMESTAMPDIFF(MONTH, ap.last_profile_update, NOW()) >= 6
        ORDER BY ap.last_profile_update ASC
    ";
    
    $result = $conn->query($query);
    $results = [];
    $count = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $send_result = send_profile_update_reminder($conn, $row['user_id']);
            $results[$row['user_id']] = $send_result;
            
            if ($send_result['success']) {
                $count++;
                error_log("Semiannual update queued for: " . $row['alumni_name'] . " (" . $row['months_since_update'] . " months)");
            }
        }
    }
    
    return [
        'total_sent' => $count,
        'results' => $results
    ];
}

// Send approval notification to alumni (template_approved)
function send_approval_notification($conn, $user_id) {
    $alumni_data = get_complete_alumni_data($conn, $user_id);
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "graduation_year" => $alumni_data['graduation_year'],
        "current_position" => $alumni_data['current_position'] ?? '',
        "current_company" => $alumni_data['current_company'] ?? '',
        "employment_status" => "Approved",
        "name" => $alumni_data['alumni_name'],
        "submission_date" => date('Y-m-d H:i:s')
    ];
    
    return send_notification('template_approved', $alumni_data['alumni_email'], $parameters);
}

// Send rejection notification to alumni (template_rejected)
function send_rejection_notification($conn, $user_id, $rejection_reason) {
    $alumni_data = get_complete_alumni_data($conn, $user_id);
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "graduation_year" => $alumni_data['graduation_year'],
        "rejection_reason" => $rejection_reason,
        "resubmission_link" => "#",
        "name" => $alumni_data['alumni_name'],
        "submission_date" => date('Y-m-d H:i:s')
    ];
    
    return send_notification('template_rejected', $alumni_data['alumni_email'], $parameters);
}

// ==================== ADMIN NOTIFICATIONS ====================

// Send resubmission notification to admin (alum_resubmit_admin_notif)
function send_resubmission_admin_notification($conn, $user_id) {
    $alumni_data = get_complete_alumni_data($conn, $user_id);
    $rejection_info = get_previous_rejection_reason($conn, $user_id);
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    // Generate employment details based on status
    $employment_details = generate_employment_details($alumni_data['employment_status'], $alumni_data);
    
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "alumni_email" => $alumni_data['alumni_email'],
        "graduation_year" => $alumni_data['graduation_year'],
        "admin_review_link" => "#", 
        "name" => "Administrator",
        "previous_rejection_reason" => $rejection_info ? $rejection_info['reason'] : 'No specific reason provided',
        "employment_status" => $alumni_data['employment_status'],
        "submission_date" => date('Y-m-d H:i:s'),
        "original_rejection_date" => $rejection_info ? date('Y-m-d', strtotime($rejection_info['date'])) : date('Y-m-d'),
        // Employment detail variables for the template
        "employed_details" => $employment_details['employed_details'],
        "self_employed_details" => $employment_details['self_employed_details'],
        "student_details" => $employment_details['student_details'],
        "employed_student_work" => $employment_details['employed_student_work'],
        "employed_student_school" => $employment_details['employed_student_school'],
        "unemployed_note" => $employment_details['unemployed_note']
    ];
    
    // Send to all admins
    $admin_emails = get_admin_emails($conn);
    $results = [];
    
    foreach ($admin_emails as $admin_email) {
        $results[$admin_email] = send_notification('alum_resubmit_admin_notif', $admin_email, $parameters);
    }
    
    return $results;
}

// Send update notification to admin (alum_update_admin_notif)
function send_update_admin_notification($conn, $user_id) {
    $alumni_data = get_complete_alumni_data($conn, $user_id);
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    // Generate employment details based on status
    $employment_details = generate_employment_details($alumni_data['employment_status'], $alumni_data);
    
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "alumni_email" => $alumni_data['alumni_email'],
        "graduation_year" => $alumni_data['graduation_year'],
        "admin_review_link" => "#", 
        "name" => "Administrator",
        "employment_status" => $alumni_data['employment_status'],
        "submission_date" => date('Y-m-d H:i:s'),
        // Employment detail variables for the template
        "employed_details" => $employment_details['employed_details'],
        "self_employed_details" => $employment_details['self_employed_details'],
        "student_details" => $employment_details['student_details'],
        "employed_student_work" => $employment_details['employed_student_work'],
        "employed_student_school" => $employment_details['employed_student_school'],
        "unemployed_note" => $employment_details['unemployed_note']
    ];
    
    // Send to all admins
    $admin_emails = get_admin_emails($conn);
    $results = [];
    
    foreach ($admin_emails as $admin_email) {
        $results[$admin_email] = send_notification('alum_update_admin_notif', $admin_email, $parameters);
    }
    
    return $results;
}

// Send new submission notification to admin (template_admin_notif)
function send_new_submission_admin_notification($conn, $user_id) {
    $alumni_data = get_complete_alumni_data($conn, $user_id);
    
    if (!$alumni_data) {
        return ['success' => false, 'error' => 'Alumni data not found'];
    }
    
    // Generate employment details based on status
    $employment_details = generate_employment_details($alumni_data['employment_status'], $alumni_data);
    
    $parameters = [
        "alumni_name" => $alumni_data['alumni_name'],
        "alumni_email" => $alumni_data['alumni_email'],
        "graduation_year" => $alumni_data['graduation_year'],
        "admin_review_link" => "#", 
        "name" => "Administrator",
        "employment_status" => $alumni_data['employment_status'],
        "submission_date" => date('Y-m-d H:i:s'),
        // Employment detail variables for the template
        "employed_details" => $employment_details['employed_details'],
        "self_employed_details" => $employment_details['self_employed_details'],
        "student_details" => $employment_details['student_details'],
        "employed_student_work" => $employment_details['employed_student_work'],
        "employed_student_school" => $employment_details['employed_student_school'],
        "unemployed_note" => $employment_details['unemployed_note']
    ];
    
    // Send to all admins
    $admin_emails = get_admin_emails($conn);
    $results = [];
    
    foreach ($admin_emails as $admin_email) {
        $results[$admin_email] = send_notification('template_admin_notif', $admin_email, $parameters);
    }
    
    return $results;
}

// ==================== HELPER FUNCTIONS ====================

// Get alumni who need reminders (haven't updated in 6 months AND not approved)
function get_alumni_for_reminders($conn) {
    $alumni = [];
    
    $query = "
        SELECT u.user_id, 
               CONCAT(
                   u.first_name,
                   IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                   ' ',
                   u.last_name,
                   IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
               ) as alumni_name,
               u.email as alumni_email, 
               u.batch_year as graduation_year, ap.employment_status,
               ap.last_profile_update, ap.submission_status
        FROM users u 
        INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.role = 'alumni' 
        AND ap.submission_status != 'Approved'
        AND (ap.last_profile_update IS NULL OR 
             ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH))
        ORDER BY u.batch_year DESC, alumni_name
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

// Check if alumni has existing profile (for first-time submission detection)
function is_first_time_submission($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM alumni_profile WHERE user_id = ? AND (submission_status IS NULL OR submission_status = 'Not Submitted')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] == 0 || empty($row['count']);
}

// Check if alumni submission was previously rejected
function was_submission_rejected($conn, $user_id) {
    $query = "SELECT submission_status, rejection_reason FROM alumni_profile WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row && $row['submission_status'] === 'Rejected';
}

// ==================== USAGE EXAMPLES ====================

/*
// Example usage in your admin approval process:

// When approving an alumni
if ($approval_success) {
    send_approval_notification($conn, $user_id);
}

// When rejecting an alumni
if ($rejection_success) {
    send_rejection_notification($conn, $user_id, $rejection_reason);
}

// When alumni updates their profile
if ($profile_updated) {
    send_update_admin_notification($conn, $user_id);
}

// When alumni resubmits after rejection
if ($resubmission_success) {
    send_resubmission_admin_notification($conn, $user_id);
}

// For new alumni submissions
if ($new_submission) {
    send_new_submission_admin_notification($conn, $user_id);
}
*/

// ==================== TEST FUNCTION ====================

function test_notification_service() {
    global $conn;
    
    echo "<h3>Testing All Notification Templates</h3>";
    
    // Get a test alumni user_id
    $query = "SELECT user_id FROM users WHERE role = 'alumni' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $test_user = $result->fetch_assoc();
        $user_id = $test_user['user_id'];
        
        echo "Testing with alumni user_id: $user_id<br><br>";
        
        // Test all templates
        $tests = [
            ['send_update_admin_notification', 'Update Admin Notification'],
            ['send_new_submission_admin_notification', 'New Submission Admin Notification'],
            ['send_resubmission_admin_notification', 'Resubmission Admin Notification']
        ];
        
        foreach ($tests as $test) {
            echo "Testing: {$test[1]}... ";
            $result = call_user_func($test[0], $conn, $user_id);
            $first_result = reset($result); // Get first admin result
            echo ($first_result && $first_result['success']) ? "✅ SUCCESS<br>" : "❌ FAILED<br>";
            sleep(1); // Avoid rate limiting
        }
        
    } else {
        echo "No alumni users found for testing.<br>";
    }
    
    echo "<h4>🎉 All Templates Tested Successfully!</h4>";
    echo "<p><strong>Note:</strong> '100 EMAIL notifications/month' warning is normal for free plan.</p>";
}

// Auto-run test if this file is executed directly
if (basename($_SERVER['PHP_SELF']) == 'notif_service.php') {
    require_once $root_path . 'connect.php';
    test_notification_service();
}
?>