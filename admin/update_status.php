<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$user_id = $_GET['user_id'] ?? 0;
$status = $_GET['status'] ?? '';
$reason = $_GET['reason'] ?? '';

if ($user_id && in_array($status, ['Approved', 'Rejected'])) {
    
    // Start transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Update alumni profile status WITHOUT deleting data
        if ($status === 'Rejected') {
            // Only update status and rejection reason - DO NOT DELETE DATA
            $updateQuery = "UPDATE alumni_profile 
                            SET submission_status = ?, 
                                rejection_reason = ?, 
                                rejected_at = NOW()
                            WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('ssi', $status, $reason, $user_id);
        } else {
            // For approval, clear rejection fields
            $updateQuery = "UPDATE alumni_profile 
                            SET submission_status = ?, 
                                rejection_reason = NULL, 
                                rejected_at = NULL 
                            WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('si', $status, $user_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update alumni profile");
        }
        $stmt->close();

        // Log the action
        $update_type = ($status === 'Approved') ? 'approve' : 'reject';
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, update_type) VALUES (?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('iis', $_SESSION['user_id'], $user_id, $update_type);
        
        if (!$logStmt->execute()) {
            throw new Exception("Failed to log admin action");
        }
        $logStmt->close();

        // Commit transaction
        $conn->commit();

        // Redirect back to all_alumni.php with success message
        header("Location: all_alumni.php?success=" . urlencode("Alumni profile " . strtolower($status) . " successfully"));
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Admin status update error: " . $e->getMessage());
        header("Location: all_alumni.php?error=Error updating alumni status: " . $e->getMessage());
        exit();
    }
    
} else {
    header("Location: all_alumni.php?error=Invalid parameters");
    exit();
}
// In update_status.php, add this case:
if ($status === 'Pending') {
    $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = 'Pending' WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Profile reverted to pending status successfully";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error reverting profile: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
}
?>