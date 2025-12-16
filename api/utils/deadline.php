<?php

// Submission Deadline Management Utility Functions

/**
 * Get the current active deadline configuration from database
 * 
 * @param mysqli $conn Database connection
 * @return array|null Deadline configuration or null if not found
 */
function getCurrentDeadlineConfig($conn) {
    // Ensure the table exists with correct structure
    $conn->query("CREATE TABLE IF NOT EXISTS submission_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_open TINYINT(1) DEFAULT 0,
        manual_override TINYINT(1) DEFAULT 0,
        open_date DATETIME NULL,
        close_date DATETIME NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    
    // Ensure a record exists
    $check = $conn->query("SELECT COUNT(*) as count FROM submission_status");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO submission_status (is_open, manual_override) VALUES (0, 0)");
    }
    
    // Get the current configuration
    $sql = "SELECT * FROM submission_status LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get the actual deadline date for submissions
 * 
 * @param mysqli $conn Database connection
 * @return string Deadline datetime in 'Y-m-d H:i:s' format
 */
function getDeadlineDate($conn) {
    $config = getCurrentDeadlineConfig($conn);
    
    if ($config && $config['close_date']) {
        return $config['close_date'];
    }
    
    // Default to end of current year if no deadline set
    return date('Y-12-31 23:59:59');
}

/**
 * Get formatted deadline for display
 * 
 * @param mysqli $conn Database connection
 * @return string Formatted deadline (e.g., "December 31, 2025")
 */
function getFormattedDeadline($conn) {
    $deadline = getDeadlineDate($conn);
    return date('F j, Y', strtotime($deadline));
}

/**
 * Check if the submission system is currently open
 * 
 * @param mysqli $conn Database connection
 * @return bool True if submissions are accepted
 */
function isSubmissionPeriodOpen($conn) {
    $config = getCurrentDeadlineConfig($conn);
    
    if (!$config) {
        return false; // No schedule configured at all
    }
    
    // Manual override takes priority - if enabled, use manual status
    if ($config['manual_override'] == 1) {
        return $config['is_open'] == 1;
    }
    
    // If manual override is off, calculate based on schedule
    $timezone = 'Asia/Manila';
    date_default_timezone_set($timezone);
    
    $now = time();
    $is_open = false;
    
    if ($config['open_date']) {
        $open_timestamp = strtotime($config['open_date']);
        
        if ($open_timestamp <= $now) {
            if ($config['close_date']) {
                $close_timestamp = strtotime($config['close_date']);
                if ($close_timestamp > $now) {
                    $is_open = true;
                }
            } else {
                // No close date means indefinitely open
                $is_open = true;
            }
        }
    }
    
    // Update the status in database if it differs from calculated
    if ($config['manual_override'] == 0 && $config['is_open'] != ($is_open ? 1 : 0)) {
        $new_status = $is_open ? 1 : 0;
        $stmt = $conn->prepare("UPDATE submission_status SET is_open = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $config['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    return $is_open;
}

/**
 * Check if EMPLOYMENT submission is specifically open
 * 
 * @param mysqli $conn Database connection
 * @return bool True if employment submissions are accepted
 */
function isEmploymentSubmissionOpen($conn) {
    // For employment submission, we use the same logic as general submission
    // since we now have only one submission control system
    return isSubmissionPeriodOpen($conn);
}

/**
 * Calculate days remaining until deadline
 * 
 * @param mysqli $conn Database connection
 * @return int Positive = days left, Negative = days past deadline
 */
function calculateDaysUntilDeadline($conn) {
    $deadline = getDeadlineDate($conn);
    $current = date('Y-m-d');
    $deadlineDate = date('Y-m-d', strtotime($deadline));
    
    $datetime1 = date_create($current);
    $datetime2 = date_create($deadlineDate);
    $interval = date_diff($datetime1, $datetime2);
    
    return (int)$interval->format('%R%a');
}

/**
 * Check if deadline is approaching (within warning period)
 * 
 * @param mysqli $conn Database connection
 * @param int $warningDays Days to consider as "approaching" (default: 7)
 * @return bool True if deadline is within warning period
 */
function isDeadlineApproaching($conn, $warningDays = 7) {
    $daysLeft = calculateDaysUntilDeadline($conn);
    return ($daysLeft > 0 && $daysLeft <= $warningDays);
}

/**
 * Get deadline urgency level
 * 
 * @param mysqli $conn Database connection
 * @return string 'passed', 'critical', 'warning', 'normal', or 'none'
 */
function getDeadlineUrgency($conn) {
    $daysLeft = calculateDaysUntilDeadline($conn);
    
    if ($daysLeft < 0) {
        return 'passed';
    } elseif ($daysLeft <= 3) {
        return 'critical';
    } elseif ($daysLeft <= 7) {
        return 'warning';
    } elseif ($daysLeft <= 30) {
        return 'normal';
    }
    
    return 'none';
}

/**
 * Get all deadline information in one array
 * 
 * @param mysqli $conn Database connection
 * @return array Complete deadline information
 */
function getAllDeadlineInfo($conn) {
    $config = getCurrentDeadlineConfig($conn);
    $deadlineDate = getDeadlineDate($conn);
    $daysLeft = calculateDaysUntilDeadline($conn);
    $is_open = isSubmissionPeriodOpen($conn);
    
    return [
        'config' => $config,
        'raw_date' => $deadlineDate,
        'formatted_date' => getFormattedDeadline($conn),
        'days_remaining' => $daysLeft,
        'is_open' => $is_open,
        'is_employment_open' => $is_open, // Same as is_open for new system
        'is_approaching' => isDeadlineApproaching($conn),
        'urgency_level' => getDeadlineUrgency($conn),
        'open_date' => $config ? $config['open_date'] : null,
        'close_date' => $config ? $config['close_date'] : null,
        'has_manual_override' => $config ? ($config['manual_override'] == 1) : false,
        'current_status' => $config ? ($config['is_open'] ? 'OPEN' : 'CLOSED') : 'CLOSED',
        'mode' => $config ? ($config['manual_override'] ? 'Manual Override' : 'Scheduled Control') : 'Not Configured'
    ];
}

/**
 * Get alumni who haven't submitted and are past deadline
 * 
 * @param mysqli $conn Database connection
 * @return array Alumni records past deadline
 */
function getAlumniPastDeadline($conn) {
    $deadline = getDeadlineDate($conn);
    
    $sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, 
                   u.batch_year, ap.submission_status, ap.submitted_at,
                   CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM users u
            JOIN alumni_profile ap ON u.user_id = ap.user_id
            WHERE u.role = 'alumni'
            AND ap.submission_status = 'Pending'
            AND (ap.submitted_at IS NULL OR ap.submitted_at > ?)
            ORDER BY u.batch_year DESC, u.last_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $deadline);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alumni = [];
    while ($row = $result->fetch_assoc()) {
        $alumni[] = $row;
    }
    
    return $alumni;
}

/**
 * Get count of alumni who submitted on time
 * 
 * @param mysqli $conn Database connection
 * @return int Number of alumni who submitted before deadline
 */
function getOnTimeSubmissionsCount($conn) {
    $deadline = getDeadlineDate($conn);
    
    $sql = "SELECT COUNT(*) as count
            FROM users u
            JOIN alumni_profile ap ON u.user_id = ap.user_id
            WHERE u.role = 'alumni'
            AND ap.submission_status != 'Pending'
            AND ap.submitted_at IS NOT NULL
            AND ap.submitted_at <= ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $deadline);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    return (int)$row['count'];
}

/**
 * Validate if a submission date is before deadline
 * 
 * @param string $submissionDate Submission date in 'Y-m-d H:i:s' format
 * @param mysqli $conn Database connection
 * @return bool True if submission is before deadline
 */
function isSubmissionBeforeDeadline($submissionDate, $conn) {
    $deadline = getDeadlineDate($conn);
    return strtotime($submissionDate) <= strtotime($deadline);
}

/**
 * Get submission deadline date
 * 
 * @param mysqli $conn Database connection
 * @param int $fallback_days Days to add if no deadline set
 * @return string Deadline date in 'Y-m-d' format
 */
function getSubmissionDeadline($conn, $fallback_days = 14) {
    $config = getCurrentDeadlineConfig($conn);
    
    if ($config && $config['close_date']) {
        return date('Y-m-d', strtotime($config['close_date']));
    }
    
    // Fallback to X days from now
    return date('Y-m-d', strtotime("+$fallback_days days"));
}