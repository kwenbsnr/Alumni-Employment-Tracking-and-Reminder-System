<?php

require_once '../../connect.php';
require_once '../../vendor/autoload.php';
require_once '../../config/notification_config.php';
require_once 'notif_service.php';

use NotificationAPI\NotificationAPI;

// Simple function to send profile update reminder
function sendProfileUpdateReminder($alumni_email, $alumni_name, $graduation_year, $reminder_type = 'initial') {
    try {
        $notificationapi = new NotificationAPI(
            "ls4kt1i6t2hhh7rxd51k00rjj3",
            "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
        );

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
                "alumni_portal_link" => "/alumni/alumni_dashboard.php",
                "name" => $alumni_name,
                "reminder_type" => $reminder_type
            ]
        ]);

        logNotification($alumni_email, 'template_one', 'sent');
        return ['success' => true, 'data' => $result];
        
    } catch (Exception $e) {
        logNotification($alumni_email, 'template_one', 'failed', $e->getMessage());
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

// Get submission schedule
function getSubmissionSchedule($conn) {
    $schedule = $conn->query("SELECT * FROM submission_status LIMIT 1");
    return $schedule->num_rows > 0 ? $schedule->fetch_assoc() : null;
}

// Check if submissions are open
function isSubmissionsOpen($conn) {
    $schedule = getSubmissionSchedule($conn);
    if (!$schedule) return false;

    if ($schedule['manual_override']) {
        return (bool)$schedule['is_open'];
    }

    if ($schedule['open_date'] && $schedule['close_date']) {
        $now = date('Y-m-d H:i:s');
        return ($now >= $schedule['open_date'] && $now <= $schedule['close_date']);
    }

    return false;
}

// Simple notification logger
function logNotification($email, $template_id, $status, $error_message = '') {
    global $conn;
    
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

// Main scheduled reminder function
function runScheduledReminders($conn) {
    $schedule = getSubmissionSchedule($conn);
    if (!$schedule) {
        return "No submission schedule found.";
    }

    $now = date('Y-m-d H:i:s');
    $is_open = isSubmissionsOpen($conn);
    $results = [];

    // 1. Check for 2-day closing reminder
    if ($schedule['close_date'] && !$schedule['manual_override']) {
        $two_days_before = date('Y-m-d H:i:s', strtotime($schedule['close_date'] . ' -2 days'));
        $one_day_before = date('Y-m-d H:i:s', strtotime($schedule['close_date'] . ' -1 day'));
        
        if ($now >= $two_days_before && $now < $one_day_before && $is_open) {
            $alumni = getAlumniForReminders($conn);
            $count = 0;
            foreach ($alumni as $a) {
                $result = sendProfileUpdateReminder($a['alumni_email'], $a['alumni_name'], $a['graduation_year'], '2-day');
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
                $result = sendProfileUpdateReminder($a['alumni_email'], $a['alumni_name'], $a['graduation_year'], '1-day');
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
            $result = sendProfileUpdateReminder($a['alumni_email'], $a['alumni_name'], $a['graduation_year'], 'semi-annual');
            if ($result['success']) $count++;
        }
        $results[] = "Sent {$count} semi-annual reminders";
    }

    return empty($results) ? "No reminders sent." : implode(" | ", $results);
}

// TEST FUNCTION - Run this file directly to test
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

// For cron job usage
if (isset($conn) && $conn) {
    $result = runScheduledReminders($conn);
    error_log("Scheduled reminders: " . $result);
    echo $result;
}
?>