function runScheduledReminders($conn) {
    $schedule = getSubmissionSchedule($conn);
    if (!$schedule) {
        return "No submission schedule found.";
    }

    $now = date('Y-m-d H:i:s');
    $is_open = isSubmissionsOpen($conn);
    $results = [];

    // Debug output
    error_log("=== REMINDER CHECK ===");
    error_log("Current time: $now");
    error_log("Schedule found: " . json_encode($schedule));
    error_log("Is open: " . ($is_open ? 'YES' : 'NO'));
    
