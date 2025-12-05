<?php

function getCurrentSchedule($conn) {
    $query = "SELECT * FROM submission_status ORDER BY submission_id DESC LIMIT 1";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function isSubmissionPeriodOpen($conn) {
    $schedule = getCurrentSchedule($conn);
    if (!$schedule) return false;
    
    // Manual override takes precedence
    if ($schedule['manual_override']) {
        return (bool)$schedule['is_open'];
    }
    
    // Check date range
    $now = date('Y-m-d H:i:s');
    if (!empty($schedule['open_date']) && !empty($schedule['close_date'])) {
        return ($now >= $schedule['open_date'] && $now <= $schedule['close_date']);
    }
    
    return (bool)$schedule['is_open'];
}

function shouldSendNotifications($conn) {
    $schedule = getCurrentSchedule($conn);
    if (!$schedule) return false;
    
    // Send notifications if submission period is open OR manual override is on
    return isSubmissionPeriodOpen($conn) || ($schedule['manual_override'] && $schedule['is_open']);
}

function getSubmissionDeadline($conn, $fallback_days = 14) {
    $schedule = getCurrentSchedule($conn);
    
    if ($schedule && !empty($schedule['close_date']) && $schedule['close_date'] != '0000-00-00 00:00:00') {
        return date('F j, Y', strtotime($schedule['close_date']));
    }
    
    // Fallback to X days from now
    return date('F j, Y', strtotime("+$fallback_days days"));
}
?>