<?php
require_once '../connect.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $open_date = $_POST['open_date'] ?? '';
    $close_date = $_POST['close_date'] ?? '';
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $manual_override = isset($_POST['manual_override']) ? 1 : 0;
    $admin_id = $_SESSION['user_id'];
    
    // Update or insert schedule
    $query = "
        INSERT INTO submission_status 
        (is_open, manual_override, open_date, close_date, created_by, last_updated_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        is_open = VALUES(is_open),
        manual_override = VALUES(manual_override),
        open_date = VALUES(open_date),
        close_date = VALUES(close_date),
        last_updated_by = VALUES(last_updated_by),
        updated_at = NOW()
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissii", $is_open, $manual_override, $open_date, $close_date, $admin_id, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Submission schedule updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update schedule.";
    }
    
    header('Location: admin_dashboard.php');
    exit();
}
?>