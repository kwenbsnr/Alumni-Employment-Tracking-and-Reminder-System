<?php
// api/notification/mark_notification_read.php
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Start output buffering to prevent any accidental output
ob_start();

// Get the correct path to connect.php (adjust based on your structure)
$root_path = dirname(__DIR__, 2); // Go up two levels from api/notification/
require_once $root_path . '/connect.php';

// Log session info for debugging
error_log("=== MARK NOTIFICATION READ API CALLED ===");
error_log("Session ID: " . session_id());
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session role: " . ($_SESSION['role'] ?? 'NOT SET'));

// Check admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("ERROR: Access denied - Not admin");
    header('HTTP/1.1 403 Forbidden');
    ob_end_clean(); // Clear any output
    echo json_encode(['success' => false, 'error' => 'Access denied. Please log in as admin.']);
    exit();
}

// Get JSON input
$raw_input = file_get_contents('php://input');
error_log("Raw input: " . $raw_input);

$data = json_decode($raw_input, true);
$notification_id = $data['notification_id'] ?? null;

error_log("Parsed notification_id: " . ($notification_id ?? 'NULL'));

if (!$notification_id || !is_numeric($notification_id)) {
    error_log("ERROR: Invalid notification ID");
    header('Content-Type: application/json');
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Valid notification ID required']);
    exit();
}

try {
    // Update the notification
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE notification_id = ?");
    
    if (!$stmt) {
        error_log("ERROR: Prepare failed - " . $conn->error);
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $notification_id);
    
    if (!$stmt->execute()) {
        error_log("ERROR: Execute failed - " . $stmt->error);
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    error_log("SUCCESS: Updated $affected_rows row(s) for notification_id $notification_id");
    
    // Clear output buffer and send response
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'affected_rows' => $affected_rows,
        'message' => 'Notification marked as read'
    ]);
    
} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    
    // Clear output buffer and send error
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

error_log("=== END OF API CALL ===");
?>