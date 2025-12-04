<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");
require_once '../api/notification/notif_service.php';

// Get referrer to determine which page the action came from
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$came_from_batch = strpos($referrer, 'batch_alumni.php') !== false;
$came_from_all = strpos($referrer, 'all_alumni.php') !== false;

if (isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = intval($_GET['user_id']);
    $status = $_GET['status'];
    $reason = $_GET['reason'] ?? '';
    $admin_id = $_SESSION["user_id"];

    // Validate status
    $valid_statuses = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['message'] = "Invalid status parameter";
        $_SESSION['message_type'] = "error";
    } else {
        // Start transaction for atomic operations
        $conn->begin_transaction();
        
        try {
            // Get current status before update for undo context
            $currentStatusQuery = $conn->prepare("SELECT submission_status FROM alumni_profile WHERE user_id = ?");
            $currentStatusQuery->bind_param("i", $user_id);
            $currentStatusQuery->execute();
            $currentStatusResult = $currentStatusQuery->get_result();
            $currentStatus = $currentStatusResult->fetch_assoc()['submission_status'] ?? '';
            $currentStatusQuery->close();

            // Update the alumni profile status with rejection reason and timestamp
            if ($status === 'Rejected') {
                $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = ?, rejection_reason = ?, rejected_at = NOW() WHERE user_id = ?");
                $stmt->bind_param("ssi", $status, $reason, $user_id);
            } else {
                // For approved or pending, clear rejection data
                $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE user_id = ?");
                $stmt->bind_param("si", $status, $user_id);
            }

            if ($stmt->execute()) {
                // LOG THE ACTION - Enhanced with better context
                $update_type = '';
                $details = '';
                
                if ($status === 'Approved') {
                    $update_type = 'approve';
                    $details = "Approved alumni profile";
                } elseif ($status === 'Rejected') {
                    $update_type = 'reject';
                    $details = "Rejected alumni profile";
                    if (!empty($reason)) {
                        $details .= " - Reason: {$reason}";
                    }
                } elseif ($status === 'Pending') {
                    $update_type = 'update';
                    // Provide context for undo action
                    if ($currentStatus === 'Approved') {
                        $details = "Undo approval - Reverted to pending status";
                    } elseif ($currentStatus === 'Rejected') {
                        $details = "Undo rejection - Reverted to pending status";
                    } else {
                        $details = "Changed status to pending";
                    }
                }

                // Also update submitted_at timestamp when status changes to Approved
                if ($status === 'Approved') {
                    $updateTimestampStmt = $conn->prepare("UPDATE alumni_profile SET submitted_at = NOW() WHERE user_id = ?");
                    $updateTimestampStmt->bind_param("i", $user_id);
                    $updateTimestampStmt->execute();
                    $updateTimestampStmt->close();
                }
                
                // Insert into update_log
                $logStmt = $conn->prepare("INSERT INTO update_log (updated_by, updated_id, update_type, update_details) VALUES (?, ?, ?, ?)");
                $logStmt->bind_param("iiss", $admin_id, $user_id, $update_type, $details);
                $logStmt->execute();
                $logStmt->close();
                
                // Commit both operations
                $conn->commit();
                
                if ($status === 'Pending') {
                    $_SESSION['message'] = "Profile reverted to pending successfully";
                } elseif ($status === 'Approved') {
                    $_SESSION['message'] = "Profile approved successfully";
                } else {
                    $_SESSION['message'] = "Profile rejected successfully" . ($reason ? " - Reason: " . htmlspecialchars($reason) : "");
                }
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception("Database update error: " . $conn->error);
            }
            $stmt->close();
            
            // === NOTIFICATION INTEGRATION ===
            if ($status === 'Approved' || $status === 'Rejected') {
                if ($status === 'Approved') {
                    // Send approval notification to alumni - USING UPDATED FUNCTION
                    $result = send_approval_notification($conn, $user_id);
                } elseif ($status === 'Rejected') {
                    // Send rejection notification to alumni - USING UPDATED FUNCTION
                    $result = send_rejection_notification($conn, $user_id, $reason);
                }
                
                // Log notification results
                error_log("Notification sent for user $user_id, status: $status");
            }

        } catch (Exception $e) {
            // Rollback on any error
            $conn->rollback();
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
} else {
    $_SESSION['message'] = "Invalid request parameters";
    $_SESSION['message_type'] = "error";
}

// === SMART REDIRECT BACK TO ORIGINAL PAGE WITH ALL FILTERS ===

$redirect_url = "all_alumni.php"; // default fallback

if ($came_from_batch && isset($_GET['batch'])) {
    // Rebuild batch page URL with all current GET parameters
    $batch = $_GET['batch'];
    $query_params = [
        'batch' => $batch,
        'search' => $_GET['search'] ?? '',
        'employment_status' => $_GET['employment_status'] ?? '',
        'submission_status' => $_GET['submission_status'] ?? ''
    ];
    // Remove empty values
    $query_params = array_filter($query_params, function($v) { return $v !== ''; });
    $redirect_url = "batch_alumni.php?" . http_build_query($query_params);
}
elseif ($came_from_all) {
    // Rebuild all_alumni.php with filters
    $query_params = [
        'search' => $_GET['search'] ?? '',
        'employment_status' => $_GET['employment_status'] ?? '',
        'submission_status' => $_GET['submission_status'] ?? ''
    ];
    $query_params = array_filter($query_params, function($v) { return $v !== ''; });
    $redirect_url = "all_alumni.php" . ($query_params ? "?" . http_build_query($query_params) : "");
}

header("Location: $redirect_url");
exit();
?>