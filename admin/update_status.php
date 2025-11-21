<?php
// update_status.php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

if (isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = intval($_GET['user_id']);
    $status = $_GET['status'];
    $reason = $_GET['reason'] ?? '';

    // Validate status
    $valid_statuses = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['message'] = "Invalid status parameter";
        $_SESSION['message_type'] = "error";
        header("Location: all_alumni.php");
        exit();
    }

    // Update the alumni profile status
    if ($status === 'Pending') {
        // For reverting to pending, just update the status
        $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = 'Pending' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    } elseif ($status === 'Approved') {
        // For approval, update status and clear any rejection reason
        $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = 'Approved' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    } elseif ($status === 'Rejected') {
        // For rejection, update status and store the reason
        $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = 'Rejected' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        // You might want to store the rejection reason in a separate table
        // For now, we'll just update the status
    }

    if ($stmt->execute()) {
        if ($status === 'Pending') {
            $_SESSION['message'] = "Profile reverted to pending status successfully";
        } elseif ($status === 'Approved') {
            $_SESSION['message'] = "Profile approved successfully";
        } else {
            $_SESSION['message'] = "Profile rejected successfully";
        }
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating profile: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }

    $stmt->close();
} else {
    $_SESSION['message'] = "Invalid parameters";
    $_SESSION['message_type'] = "error";
}

header("Location: all_alumni.php");
exit();
?>