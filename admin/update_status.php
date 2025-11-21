<?php
// update_status.php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

// Get referrer to determine which page the action came from
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$came_from_batch = strpos($referrer, 'batch_alumni.php') !== false;
$came_from_all = strpos($referrer, 'all_alumni.php') !== false;

if (isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = intval($_GET['user_id']);
    $status = $_GET['status'];
    $reason = $_GET['reason'] ?? '';

    // Validate status
    $valid_statuses = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['message'] = "Invalid status parameter";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt = $conn->prepare("UPDATE alumni_profile SET submission_status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $status, $user_id);

        if ($stmt->execute()) {
            if ($status === 'Pending') {
                $_SESSION['message'] = "Profile reverted to pending successfully";
            } elseif ($status === 'Approved') {
                $_SESSION['message'] = "Profile approved successfully";
            } else {
                $_SESSION['message'] = "Profile rejected successfully" . ($reason ? " - Reason: " . htmlspecialchars($reason) : "");
            }
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Database error: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
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