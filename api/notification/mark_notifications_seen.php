<?php
session_start();
require_once '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// Optional: You can implement "seen" vs "read" logic here
// For now, we'll just return success
echo json_encode(['success' => true]);
?>