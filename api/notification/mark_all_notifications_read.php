<?php
// api/notification/mark_all_notifications_read.php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

$root_path = dirname(__DIR__, 2);
require_once $root_path . '/connect.php';

error_log("=== MARK ALL NOTIFICATIONS READ API CALLED ===");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session role: " . ($_SESSION['role'] ?? 'NOT SET'));

// Check admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("ERROR: Access denied - Not admin");
    header('HTTP/1.1 403 Forbidden');
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Access denied. Please log in as admin.']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE is_read = FALSE");
    
    if (!$stmt) {
        error_log("ERROR: Prepare failed - " . $conn->error);
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    if (!$stmt->execute()) {
        error_log("ERROR: Execute failed - " . $stmt->error);
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    error_log("SUCCESS: Marked $affected_rows notifications as read");
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'marked_read' => $affected_rows,
        'message' => 'All notifications marked as read'
    ]);
    
} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

error_log("=== END OF API CALL ===");
?>