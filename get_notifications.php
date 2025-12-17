<?php
session_start();
include("../connect.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    echo '<div class="p-6 text-center text-gray-500">
            <i class="fas fa-exclamation-triangle text-2xl mb-3 text-red-400"></i>
            <p class="text-gray-600">Access denied</p>
        </div>';
    exit();
}

$user_id = $_SESSION["user_id"];

// Get notifications from alumni_notifications table
$stmt = $conn->prepare("
    SELECT 
        notification_id,
        title,
        message,
        type,
        is_read,
        related_type,
        related_id,
        created_at,
        TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago
    FROM alumni_notifications 
    WHERE user_id = ?
    ORDER BY created_at DESC, is_read ASC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="p-6 text-center text-gray-500">
            <i class="fas fa-bell-slash text-2xl mb-3 text-gray-400"></i>
            <p class="text-gray-600">No notifications</p>
            <p class="text-xs text-gray-500 mt-1">You\'re all caught up!</p>
          </div>';
} else {
    while ($row = $result->fetch_assoc()) {
        // Format timestamp display
        $timestamp_display = '';
        if ($row['minutes_ago'] < 1) {
            $timestamp_display = 'Just now';
        } elseif ($row['minutes_ago'] < 60) {
            $timestamp_display = $row['minutes_ago'] . ' minutes ago';
        } elseif ($row['minutes_ago'] < 1440) {
            $hours = floor($row['minutes_ago'] / 60);
            $timestamp_display = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $timestamp_display = date('M j, Y g:i A', strtotime($row['created_at']));
        }
        
        $read_class = $row['is_read'] ? 'notification-read' : 'notification-unread';
        
      echo '<div class="notification-item p-4 hover:bg-gray-50 border-b border-gray-100 notification-' . $row['type'] . ' ' . $read_class . '" 
      data-notification-id="' . $row['notification_id'] . '">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0 mt-1">';
        
        switch ($row['type']) {
            case 'success':
                echo '<i class="fas fa-check-circle text-green-500 text-sm"></i>';
                break;
            case 'warning':
                echo '<i class="fas fa-exclamation-triangle text-yellow-500 text-sm"></i>';
                break;
            case 'error':
                echo '<i class="fas fa-times-circle text-red-500 text-sm"></i>';
                break;
            default:
                echo '<i class="fas fa-info-circle text-blue-500 text-sm"></i>';
        }
        
        echo '</div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800">' . htmlspecialchars($row['title']) . '</p>
                        <p class="text-gray-600 mt-1 text-sm">' . htmlspecialchars($row['message']) . '</p>
                        <p class="notification-time">
                            <i class="fas fa-clock mr-1"></i>
                            ' . htmlspecialchars($timestamp_display) . '
                        </p>
                    </div>';
        
        if (!$row['is_read']) {
            echo '<div class="flex-shrink-0">
                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full" title="Unread"></span>
                  </div>';
        }
        
        echo '</div>
              </div>';
    }
}

$stmt->close();
?>