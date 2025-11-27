<?php
session_start();
require_once 'connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Notification Logs & Monitoring</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log-entry { background: white; padding: 10px; margin: 5px 0; border-radius: 4px; border-left: 4px solid #3B82F6; }
        .error-log { border-left-color: #EF4444; background: #FEF2F2; }
    </style>
</head>
<body>
    <h1>📊 Notification Logs & Monitoring</h1>";

// Check if notification_logs table exists and show recent activity
$table_check = $conn->query("SHOW TABLES LIKE 'notification_logs'");
if ($table_check && $table_check->num_rows > 0) {
    $recent_logs = $conn->query("SELECT * FROM notification_logs ORDER BY sent_at DESC LIMIT 20");
    
    echo "<h2>Recent Notification Logs</h2>";
    while ($log = $recent_logs->fetch_assoc()) {
        $status_class = $log['status'] === 'failed' ? 'error-log' : '';
        echo "<div class='log-entry $status_class'>
                <strong>Email:</strong> {$log['email']} | 
                <strong>Template:</strong> {$log['template_id']} | 
                <strong>Status:</strong> {$log['status']} | 
                <strong>Time:</strong> {$log['sent_at']}
                " . ($log['error_message'] ? "<br><small>Error: {$log['error_message']}</small>" : "") . "
              </div>";
    }
} else {
    echo "<p>Notification logs table not found. Using error_log() for tracking.</p>";
}

// Show recent error logs (if accessible)
echo "<h2>Recent System Logs (Last 10 lines)</h2>";
echo "<div style='background: #1F2937; color: #E5E7EB; padding: 15px; border-radius: 4px; font-family: monospace;'>";
$log_file = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/error_log';
if (file_exists($log_file)) {
    $logs = `tail -n 10 "$log_file"`;
    echo nl2br(htmlspecialchars($logs));
} else {
    echo "Error log file not found or not accessible.";
}
echo "</div>";

echo "</body></html>";
?>