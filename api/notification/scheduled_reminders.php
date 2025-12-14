<?php

// Debug: Log when this file is included
error_log("SCHEDULED_REMINDERS.PHP included from: " . $_SERVER['PHP_SELF']);

// Use absolute paths for reliability
$root_path = dirname(__DIR__, 2);
require_once $root_path . '/vendor/autoload.php';
require_once $root_path . '/config/notification_config.php';
require_once __DIR__ . '/../../connect.php';
require_once __DIR__ . '/../utils/deadline.php'; // Updated path
require_once __DIR__ . '/notif_service.php';
require_once __DIR__ . '/../utils/common_functions.php';

// Simple function to send profile update reminder - FIXED SIGNATURE
function sendProfileUpdateReminder($conn, $alumni_email, $alumni_name, $graduation_year, $user_id, $reminder_type = 'initial') {
    try {
        // Use the unified notification service with correct signature
        $result = send_profile_update_reminder($conn, $user_id);
        
        logNotification($conn, $alumni_email, 'template_one', 'sent');
        return $result;
        
    } catch (Exception $e) {
        logNotification($conn, $alumni_email, 'template_one', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get alumni who need reminders
function getAlumniForReminders($conn) {
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
               u.contact_number, 
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

// Get submission schedule with admin info
function getSubmissionSchedule($conn) {
    $query = "
        SELECT ss.*, 
               u.email as admin_email,
               CONCAT(u.first_name, ' ', u.last_name) as admin_name
        FROM submission_status ss
        LEFT JOIN users u ON ss.created_by = u.user_id
        LIMIT 1
    ";
    $schedule = $conn->query($query);
    return $schedule->num_rows > 0 ? $schedule->fetch_assoc() : null;
}

// Simple notification logger - FIXED WITH CONN PARAMETER
function logNotification($conn, $email, $template_id, $status, $error_message = '') {
    if (!$conn) {
        error_log("Notification: $email | $template_id | $status | $error_message");
        return;
    }
    
    $table_check = $conn->query("SHOW TABLES LIKE 'notification_logs'");
    if ($table_check && $table_check->num_rows > 0) {
        $query = "INSERT INTO notification_logs (email, template_id, status, error_message, sent_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssss", $email, $template_id, $status, $error_message);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        error_log("Notification: $email | $template_id | $status | $error_message");
    }
}

// Main scheduled reminder function - FIXED FUNCTION CALLS
function runScheduledReminders($conn) {
    $schedule = getSubmissionSchedule($conn);
    if (!$schedule) {
        return "No submission schedule found.";
    }

    $now = date('Y-m-d H:i:s');
    
    // Use the deadline.php function to check if submissions are open
    if (!function_exists('isSubmissionPeriodOpen')) {
        require_once dirname(__DIR__) . '/utils/deadline.php';
    }
    $is_open = isSubmissionPeriodOpen($conn);
    
    $results = [];

    // 1. Check for 2-day closing reminder
    if ($schedule['close_date'] && !$schedule['manual_override']) {
        $two_days_before = date('Y-m-d H:i:s', strtotime($schedule['close_date'] . ' -2 days'));
        $one_day_before = date('Y-m-d H:i:s', strtotime($schedule['close_date'] . ' -1 day'));
        
        if ($now >= $two_days_before && $now < $one_day_before && $is_open) {
            $alumni = getAlumniForReminders($conn);
            $count = 0;
            foreach ($alumni as $a) {
                $result = sendProfileUpdateReminder($conn, $a['alumni_email'], $a['alumni_name'], $a['graduation_year'], $a['user_id'], '2-day');
                if ($result['success']) $count++;
            }
            $results[] = "Sent {$count} 2-day closing reminders";
        }
    }

    // 2. Check for 1-day closing reminder
    if ($schedule['close_date'] && !$schedule['manual_override']) {
        $one_day_before = date('Y-m-d H:i:s', strtotime($schedule['close_date'] . ' -1 day'));
        
        if ($now >= $one_day_before && $now < $schedule['close_date'] && $is_open) {
            $alumni = getAlumniForReminders($conn);
            $count = 0;
            foreach ($alumni as $a) {
                $result = sendProfileUpdateReminder($conn, $a['alumni_email'], $a['alumni_name'], $a['graduation_year'], $a['user_id'], '1-day');
                if ($result['success']) $count++;
            }
            $results[] = "Sent {$count} 1-day closing reminders";
        }
    }

    // 3. Semi-annual reminder (runs on 1st and 15th of each month)
    $day_of_month = date('j');
    if (in_array($day_of_month, [1, 15])) {
        $alumni = getAlumniForReminders($conn);
        $count = 0;
        foreach ($alumni as $a) {
            $result = sendProfileUpdateReminder($conn, $a['alumni_email'], $a['alumni_name'], $a['graduation_year'], $a['user_id'], 'semi-annual');
            if ($result['success']) $count++;
        }
        $results[] = "Sent {$count} semi-annual reminders";
    }

    return empty($results) ? "No reminders sent." : implode(" | ", $results);
}

// TEST FUNCTION - Run file directly to test
function testScheduledReminders() {
    global $conn;
    echo "=== Testing Scheduled Reminders ===\n";
    echo "Current time: " . date('Y-m-d H:i:s') . "\n";
    
    $schedule = getSubmissionSchedule($conn);
    if ($schedule) {
        echo "Submission Schedule:\n";
        echo "- Open: " . ($schedule['is_open'] ? 'YES' : 'NO') . "\n";
        echo "- Manual Override: " . ($schedule['manual_override'] ? 'YES' : 'NO') . "\n";
        echo "- Open Date: " . $schedule['open_date'] . "\n";
        echo "- Close Date: " . $schedule['close_date'] . "\n";
        echo "- Created By: " . ($schedule['admin_name'] ?? 'N/A') . " (" . ($schedule['admin_email'] ?? 'N/A') . ")\n";
        echo "- Created At: " . $schedule['created_at'] . "\n";
        echo "- Updated At: " . $schedule['updated_at'] . "\n";
    } else {
        echo "No submission schedule found.\n";
    }
    
    $alumni_count = count(getAlumniForReminders($conn));
    echo "Alumni needing reminders: {$alumni_count}\n";
    
    $result = runScheduledReminders($conn);
    echo "Result: {$result}\n";
    echo "=== Test Complete ===\n";
}

// Auto-run if executed directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    testScheduledReminders();
}