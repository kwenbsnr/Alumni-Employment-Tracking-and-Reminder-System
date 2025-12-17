<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is alumni
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Include database connection
$conn = null;
try {
    // Try different paths for the connect.php file
    $possible_paths = [
        __DIR__ . '/../connect.php',
        __DIR__ . '/../../connect.php',
        dirname(__DIR__) . '/connect.php'
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            include($path);
            break;
        }
    }
    
    if (!$conn) {
        throw new Exception("Database connection file not found");
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

$user_id = $_SESSION["user_id"];

// Log for debugging
error_log("DEBUG: Mark all as read called for user: $user_id");

// Check if the alumni_notifications table exists
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'alumni_notifications'");
    if (!$table_check) {
        throw new Exception("Table check query failed: " . $conn->error);
    }
    
    if ($table_check->num_rows === 0) {
        error_log("ERROR: alumni_notifications table does not exist");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Notifications table not found']);
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

try {
    // Count unread notifications before update
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM alumni_notifications WHERE user_id = ? AND is_read = FALSE");
    if (!$count_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $count_stmt->bind_param("i", $user_id);
    if (!$count_stmt->execute()) {
        throw new Exception("Execute failed: " . $count_stmt->error);
    }
    
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    $unread_count = $count_data['count'] ?? 0;
    $count_stmt->close();
    
    error_log("DEBUG: Found $unread_count unread notifications for user $user_id");
    
    if ($unread_count > 0) {
        // Update all unread notifications
        $update_stmt = $conn->prepare("UPDATE alumni_notifications SET is_read = TRUE, is_read = NOW() WHERE user_id = ? AND is_read = FALSE");
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $update_stmt->bind_param("i", $user_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Update failed: " . $update_stmt->error);
        }
        
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        error_log("DEBUG: Successfully marked $affected_rows notifications as read");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => "Marked $affected_rows notifications as read",
            'affected' => $affected_rows
        ]);
        
    } else {
        // No unread notifications
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'No unread notifications to mark',
            'affected' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log("ERROR: Failed to mark all as read - " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>