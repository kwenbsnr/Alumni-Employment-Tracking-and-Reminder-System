<?php

// Submission Deadline Management Utility Functions

/**
 * Get the current active deadline configuration from database
 * 
 * @param mysqli $conn Database connection
 * @return array|null Deadline configuration or null if not found
 */
function getCurrentDeadlineConfig($conn) {
    // First, try to get the latest configuration regardless of status
    $sql = "SELECT * FROM submission_status 
            ORDER BY submission_id DESC 
            LIMIT 1";
    
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
    
    $currentTime = date('Y-m-d H:i:s');
    
    // Manual override takes priority - if enabled, always open
    if ($config['manual_override'] == 1) {
        return true;
    }
    
    // If manual override is off, check date range
    if ($config['open_date'] && $config['close_date']) {
        $isWithinDateRange = ($currentTime >= $config['open_date'] && 
                            $currentTime <= $config['close_date']);
        
        // Also check is_open flag for additional control
        return $isWithinDateRange && ($config['is_open'] == 1);
    }
    
    // Fallback: just check is_open flag
    return ($config['is_open'] == 1);
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
    
    return [
        'config' => $config,
        'raw_date' => $deadlineDate,
        'formatted_date' => getFormattedDeadline($conn),
        'days_remaining' => $daysLeft,
        'is_open' => isSubmissionPeriodOpen($conn),
        'is_approaching' => isDeadlineApproaching($conn),
        'urgency_level' => getDeadlineUrgency($conn),
        'open_date' => $config ? $config['open_date'] : null,
        'close_date' => $config ? $config['close_date'] : null,
        'has_manual_override' => $config ? ($config['manual_override'] == 1) : false
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

function getSubmissionDeadline($conn, $fallback_days = 14) {
    $config = getCurrentDeadlineConfig($conn);
    
    if ($config && $config['close_date']) {
        return date('Y-m-d', strtotime($config['close_date']));
    }
    
    // Fallback to X days from now
    return date('Y-m-d', strtotime("+$fallback_days days"));
}