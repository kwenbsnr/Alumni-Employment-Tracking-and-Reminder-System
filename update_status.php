<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$reason = isset($_GET['reason']) ? trim(urldecode($_GET['reason'])) : '';
$alumni_id = isset($_GET['alumni_id']) ? intval($_GET['alumni_id']) : 0;
$doc_type = isset($_GET['doc_type']) ? trim(urldecode($_GET['doc_type'])) : '';
$year = isset($_GET['year']) ? trim($_GET['year']) : '';

if (!$doc_id || !$status || !in_array($status, ['Pending', 'Approved', 'Rejected']) || !$alumni_id || !$doc_type) {
    header("Location: alumni_management.php?error=Invalid request");
    exit;
}

// Fetch user_id for notification
$stmt = $conn->prepare("SELECT user_id FROM alumni_profile WHERE alumni_id = ?");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$user_id = $result->fetch_assoc()['user_id'] ?? 0;
if (!$user_id) {
    header("Location: alumni_management.php?error=Alumni not found");
    exit;
}

// Update document status
$needs_reupload = ($status === 'Rejected') ? 1 : 0;
$rejection_reason = ($status === 'Rejected') ? $reason : null;
$sql = "UPDATE alumni_documents SET document_status = ?, rejection_reason = ?, needs_reupload = ? WHERE doc_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $status, $rejection_reason, $needs_reupload, $doc_id);
if (!$stmt->execute()) {
    header("Location: alumni_management.php?error=Database error");
    exit;
}

// Log update
$log_sql = "INSERT INTO update_log (alumni_id, updated_by, update_type, update_details) VALUES (?, ?, 'Document Status Update', ?)";
$log_stmt = $conn->prepare($log_sql);
$update_details = "Changed $doc_type status to $status" . ($reason ? " with reason: $reason" : "");
$log_stmt->bind_param("iis", $alumni_id, $_SESSION['user_id'], $update_details);
$log_stmt->execute();

// Notify alumnus
$notify_sql = "INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())";
$notify_stmt = $conn->prepare($notify_sql);
$message = $status === 'Rejected' ? "Your $doc_type document has been rejected. Reason: $reason. Please re-upload a corrected version." : "Your $doc_type document status has been updated to $status.";
$notify_stmt->bind_param("is", $user_id, $message);
$notify_stmt->execute();

$conn->close();
header("Location: alumni_management.php?year=" . urlencode($year) . "&success=Document status updated");
exit;
?>