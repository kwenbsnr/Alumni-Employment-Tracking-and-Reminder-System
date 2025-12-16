<?php

include("connect.php");

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get current status
$result = $conn->query("SELECT * FROM submission_status LIMIT 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Only auto-update if not in manual override mode
    if (!$row['manual_override'] && $row['open_date'] && $row['close_date']) {
        $now = time();
        $open_ts = strtotime($row['open_date']);
        $close_ts = strtotime($row['close_date']);
        
        $new_status = ($now >= $open_ts && $now <= $close_ts) ? 1 : 0;
        
        // Update only if status changed
        if ($new_status != $row['is_open']) {
            $stmt = $conn->prepare("UPDATE submission_status SET is_open = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $new_status, $row['id']);
            $stmt->execute();
            $stmt->close();
            
            // Log the change
            $log_entry = date('Y-m-d H:i:s') . " - Submission status changed to: " . ($new_status ? 'OPEN' : 'CLOSED') . 
                        " (Was: " . ($row['is_open'] ? 'OPEN' : 'CLOSED') . ")\n";
            file_put_contents('logs/submission_status.log', $log_entry, FILE_APPEND);
            
            // Email notification to admin if status changed (optional)
            if ($new_status) {
                mail('admin@example.com', 'Submission System Opened', 
                     "Submission system opened automatically at " . date('Y-m-d H:i:s'));
            } else {
                mail('admin@example.com', 'Submission System Closed', 
                     "Submission system closed automatically at " . date('Y-m-d H:i:s'));
            }
        }
    }
}

$conn->close();
?>