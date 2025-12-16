<?php
// Fetch user data from users table
$stmt = $conn->prepare("
    SELECT 
        u.user_id, 
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.email, u.role,
        u.contact_number,     
        ap.photo_path, 
        ap.employment_status, 
        ap.last_profile_update,
        ap.submitted_at,
        u.citizenship,
        u.civil_status
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Build full name - FIXED LOGIC
$full_name = 'Alumni';
if (!empty($profile['official_name'])) {
    $full_name = trim($profile['official_name']);
}

$user_email = $profile['email'] ?? '';
$photo_path = $profile['photo_path'] ?? null;

// Fetch notifications from alumni_notifications table
$notif_count = 0;
$notifications = [];

// Get unread notification count
$stmt_unread = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM alumni_notifications 
    WHERE user_id = ? AND is_read = FALSE
    ORDER BY created_at DESC
");
$stmt_unread->bind_param("i", $user_id);
$stmt_unread->execute();
$unread_result = $stmt_unread->get_result();
$unread_data = $unread_result->fetch_assoc();
$notif_count = $unread_data['unread_count'] ?? 0;
$stmt_unread->close();

// Get latest notifications (last 10)
$stmt_notif = $conn->prepare("
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
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notif_result = $stmt_notif->get_result();

while ($row = $notif_result->fetch_assoc()) {
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
    
    $notifications[] = [
        'id' => $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['type'],
        'is_read' => $row['is_read'],
        'related_type' => $row['related_type'],
        'related_id' => $row['related_id'],
        'timestamp' => $row['created_at'],
        'timestamp_display' => $timestamp_display
    ];
}
$stmt_notif->close();

// Get document status counts for legacy notifications (keep for backward compatibility)
$stmt_docs = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN document_status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN document_status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN document_status = 'Approved' THEN 1 ELSE 0 END) as approved
    FROM alumni_documents 
    WHERE user_id = ?
");
$stmt_docs->bind_param("i", $user_id);
$stmt_docs->execute();
$doc_result = $stmt_docs->get_result();
$doc_data = $doc_result->fetch_assoc() ?: [];
$stmt_docs->close();

$total_docs_count = $doc_data['total'] ?? 0;
$pending_docs_count = $doc_data['pending'] ?? 0;
$rejected_docs_count = $doc_data['rejected'] ?? 0;
$approved_docs_count = $doc_data['approved'] ?? 0;

// Legacy document rejection notifications (only if no system notification exists for this)
if ($rejected_docs_count > 0) {
    // Check if we already have a system notification for rejected docs
    $has_rejection_notif = false;
    foreach ($notifications as $notif) {
        if (strpos($notif['title'], 'Rejected') !== false && $notif['related_type'] === 'document') {
            $has_rejection_notif = true;
            break;
        }
    }
    
    if (!$has_rejection_notif) {
        // Get latest rejected document timestamp
        $stmt_rejected = $conn->prepare("
            SELECT MAX(rejected_at) as latest_rejected 
            FROM alumni_documents 
            WHERE user_id = ? AND document_status = 'Rejected'
        ");
        $stmt_rejected->bind_param("i", $user_id);
        $stmt_rejected->execute();
        $rejected_result = $stmt_rejected->get_result();
        $rejected_data = $rejected_result->fetch_assoc();
        $stmt_rejected->close();
        
        $latest_rejected = $rejected_data['latest_rejected'] ?? null;
        
        // Add as a system notification
        $stmt_insert = $conn->prepare("
            INSERT INTO alumni_notifications (user_id, title, message, type, related_type, related_id, created_at)
            VALUES (?, 'Documents Rejected', ?, 'error', 'document', ?, ?)
        ");
        $message = $rejected_docs_count . ' document(s) were rejected. Please review and resubmit.';
        $stmt_insert->bind_param("isss", $user_id, $message, $user_id, $latest_rejected);
        $stmt_insert->execute();
        $stmt_insert->close();
        
        // Refresh notifications
        $notif_count++;
        $notifications = array_merge([[
            'title' => 'Documents Rejected',
            'message' => $message,
            'type' => 'error',
            'timestamp' => $latest_rejected,
            'timestamp_display' => $latest_rejected ? date('M j, Y g:i A', strtotime($latest_rejected)) : 'Recently'
        ]], $notifications);
    }
}

// Check if profile is incomplete (only if no similar notification exists)
if (empty($profile['contact_number']) || empty($profile['employment_status'])) {
    $has_profile_notif = false;
    foreach ($notifications as $notif) {
        if (strpos($notif['title'], 'Complete Your Profile') !== false) {
            $has_profile_notif = true;
            break;
        }
    }
    
    if (!$has_profile_notif) {
        $notifications[] = [
            'title' => 'Complete Your Profile',
            'message' => 'Please fill in your contact number and employment status.',
            'type' => 'info',
            'timestamp' => null,
            'timestamp_display' => 'Pending'
        ];
        if ($notif_count < 10) $notif_count++;
    }
}

// Check if address is incomplete
$stmt_address = $conn->prepare("
    SELECT COUNT(*) as has_address 
    FROM alumni_address 
    WHERE user_id = ? AND country IS NOT NULL AND state_province IS NOT NULL AND city IS NOT NULL
");
$stmt_address->bind_param("i", $user_id);
$stmt_address->execute();
$address_result = $stmt_address->get_result();
$address_data = $address_result->fetch_assoc();
$stmt_address->close();

if (($address_data['has_address'] ?? 0) === 0) {
    $has_address_notif = false;
    foreach ($notifications as $notif) {
        if (strpos($notif['title'], 'Address Information') !== false) {
            $has_address_notif = true;
            break;
        }
    }
    
    if (!$has_address_notif) {
        $notifications[] = [
            'title' => 'Address Information',
            'message' => 'Please complete your address information.',
            'type' => 'info',
            'timestamp' => null,
            'timestamp_display' => 'Pending'
        ];
        if ($notif_count < 10) $notif_count++;
    }
}

// Check if profile photo is missing
if (empty($photo_path)) {
    $has_photo_notif = false;
    foreach ($notifications as $notif) {
        if (strpos($notif['title'], 'Profile Photo') !== false) {
            $has_photo_notif = true;
            break;
        }
    }
    
    if (!$has_photo_notif) {
        $notifications[] = [
            'title' => 'Profile Photo',
            'message' => 'Please upload your profile photo.',
            'type' => 'info',
            'timestamp' => null,
            'timestamp_display' => 'Pending'
        ];
        if ($notif_count < 10) $notif_count++;
    }
}

// Sort notifications by timestamp (newest first) and limit to 10
usort($notifications, function($a, $b) {
    if ($a['timestamp'] == $b['timestamp']) return 0;
    return ($a['timestamp'] > $b['timestamp']) ? -1 : 1;
});
$notifications = array_slice($notifications, 0, 10);

$page_title = $page_title ?? "Alumni Page";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Alumni Tracking System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #033803ff;
            --forest-green: #013501ff;
            --lime-green: #015301ff;
            --sea-green: #004200ff;
            --light-bg: #014707ff;
            --dark-text: #1C1C1C;
        }
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--forest-green) 100%);
        }
        .card-hover { 
            transition: all 0.3s ease; 
        }
        .sidebar-item {
            transition: all 0.3s ease;
            color: #fff;
            padding-left: 14px;
        }
        .sidebar-item:hover {
            background: rgba(50, 205, 50, 0.1);
            border-left: 4px solid var(--lime-green);
        }
        .sidebar-item.active {
            background: rgba(34, 139, 34, 0.3);
            border-left: 4px solid var(--lime-green);
            padding-left: 10px;
        }
        .profile-avatar {
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
        }
        .stats-card {
            background-color: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar-wrapper {
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }
        .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .hidden { 
            display: none; 
        }
        #profileUpdateModal {
            opacity: 0;
            transition: opacity 0.5s;
        }
        #profileUpdateModal.show { 
            opacity: 1; 
        }
        /* Sidebar Profile */
        .sidebar-profile {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }
        .sidebar-profile-avatar {
            width: 128px;
            height: 128px;
            border: 4px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 42px;
            color: white;
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
            text-transform: uppercase;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        .sidebar-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Very thin dark green line below header */
        .header-accent-line {
            height: 2px;
            background-color: #022502; /* Very dark green */
            width: 100%;
        }

        /* Notification styles */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Notification type colors */
        .notification-success {
            border-left: 4px solid #10B981;
        }
        .notification-warning {
            border-left: 4px solid #F59E0B;
        }
        .notification-error {
            border-left: 4px solid #EF4444;
        }
        .notification-info {
            border-left: 4px solid #3B82F6;
        }
        
        .notification-unread {
            background-color: #f0f9ff;
            border-left: 4px solid #3B82F6;
        }
        
        .notification-read {
            background-color: #f9fafb;
            opacity: 0.8;
        }
        
        #notifPopup.open {
            display: block !important;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(-10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        /* Notification timestamp */
        .notification-time {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }
        
        /* Auto-refresh indicator */
        .refreshing {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-72 gradient-bg text-white flex-shrink-0">
        <div class="sidebar-wrapper flex flex-col justify-between">
            <div class="p-6">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-10">
                    <div class="w-12 h-12 rounded-xl bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-2xl"></i>
                    </div>
                    <h2 class="font-bold text-2xl">Alumni Portal</h2>
                </div>

                <!-- User Profile -->
                <div class="sidebar-profile pb-6 mb-6">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="sidebar-profile-avatar">
                            <?php
                            if ($photo_path && file_exists("../" . $photo_path)) {
                                $photo_url = "../" . htmlspecialchars($photo_path);
                                $photo_url .= '?v=' . filemtime("../" . $photo_path);
                                echo '<img src="' . $photo_url . '" alt="Profile" class="w-full h-full object-cover">';
                            } else {
                                $initials = 'AL';
                                if ($full_name !== 'Alumni') {
                                    $parts = array_filter(explode(' ', $full_name));
                                    $initials = '';
                                    foreach ($parts as $part) {
                                        $initials .= strtoupper(substr(trim($part), 0, 1));
                                    }
                                    $initials = substr($initials, 0, 2);
                                }
                                echo '<div class="w-full h-full flex items-center justify-center text-5xl font-bold text-white">' . htmlspecialchars($initials) . '</div>';
                            }
                            ?>
                        </div>
                        <div class="w-full">
                            <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($full_name); ?></h3>
                            <p class="text-sm text-gray-200 truncate"><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="alumni_dashboard.php" class="sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="alumni_profile.php" class="sidebar-item <?php echo ($active_page ?? '') === 'profile' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-user w-5" aria-hidden="true"></i>
                        <span>Profile Management</span>
                    </a>
                    <a href="alumni_employment.php" class="sidebar-item <?php echo ($active_page ?? '') === 'employment' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-briefcase w-5" aria-hidden="true"></i>
                        <span>Employment Information</span>
                    </a>
                </nav>
            </div>

            <!-- Logout Section -->
            <div class="p-6">
                <hr class="border-gray-400 my-6">
                <a href="../login/logout.php" class="flex items-center space-x-3 text-white hover:text-red-500 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-100 py-3 px-6 flex items-center justify-between sticky top-0 z-40">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900">
                    <?php echo ($active_page ?? '') === 'profile' ? 'Profile Management' : 'Dashboard Overview'; ?>
                </h1>
                <p class="text-sm text-gray-600 mt-1">
                    <?php if (($active_page ?? '') === 'profile'): ?>
                        Review and update your personal and academic information.
                    <?php else: ?>
                        Welcome back, <span class="font-semibold text-green-700"><?php echo htmlspecialchars($full_name); ?></span>! All alumni records, notifications, and status information are accessible through this dashboard.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Header Actions -->
            <div class="flex items-center gap-3">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationBtn" class="relative p-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                        <i class="fas fa-bell text-lg text-gray-700"></i>
                        <?php if ($notif_count > 0): ?>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full border-2 border-white text-xs text-white flex items-center justify-center notification-badge font-semibold">
                                <?php echo $notif_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="notifPopup" class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200 hidden z-50">
                        <div class="p-4 border-b border-gray-200 font-semibold text-gray-800 flex justify-between items-center text-sm bg-gray-50 rounded-t-xl">
                            <span>Notifications</span>
                            <div class="flex items-center gap-2">
                                <button id="refreshNotifications" class="text-gray-500 hover:text-gray-700 p-1 rounded" title="Refresh notifications">
                                    <i class="fas fa-sync-alt text-xs"></i>
                                </button>
                                <?php if ($notif_count > 0): ?>
                                    <button id="markReadBtn" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium">Mark all as read</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="notificationsContainer" class="max-h-96 overflow-y-auto text-sm">
                            <?php if (empty($notifications)): ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-2xl mb-3 text-gray-400"></i>
                                    <p class="text-gray-600">No notifications</p>
                                    <p class="text-xs text-gray-500 mt-1">You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item p-4 hover:bg-gray-50 border-b border-gray-100 notification-<?php echo $notification['type']; ?> <?php echo empty($notification['is_read']) || $notification['is_read'] === false ? 'notification-unread' : 'notification-read'; ?>" 
                                         data-notification-id="<?php echo $notification['id'] ?? ''; ?>">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0 mt-1">
                                                <?php if ($notification['type'] === 'success'): ?>
                                                    <i class="fas fa-check-circle text-green-500 text-sm"></i>
                                                <?php elseif ($notification['type'] === 'warning'): ?>
                                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-sm"></i>
                                                <?php elseif ($notification['type'] === 'error'): ?>
                                                    <i class="fas fa-times-circle text-red-500 text-sm"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle text-blue-500 text-sm"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></p>
                                                <p class="text-gray-600 mt-1 text-sm"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <?php if (!empty($notification['timestamp_display'])): ?>
                                                    <p class="notification-time">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php echo htmlspecialchars($notification['timestamp_display']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (empty($notification['is_read']) || $notification['is_read'] === false): ?>
                                                <div class="flex-shrink-0">
                                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full" title="Unread"></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-xl text-center">
                            <a href="alumni_profile.php" class="text-xs text-green-600 hover:text-green-800 font-medium">
                                <i class="fas fa-cog mr-1"></i>Manage Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Help Button -->
                <button id="helpButton" class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-medium text-sm rounded-lg shadow-md hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                    <i class="fas fa-question-circle text-sm"></i>
                    <span>Help</span>
                </button>
            </div>
        </div>

        <!-- Thin dark green accent line below header -->
        <div class="header-accent-line"></div>

        <!-- Main Content -->
        <main class="flex-1 p-5 overflow-hidden">
            <?php echo $page_content ?? ''; ?>
        </main>
    </div>
    
    <!-- Notification Update Script -->
   <script>
document.addEventListener('DOMContentLoaded', function() {
    // Notification functionality
    const notifButton = document.getElementById('notificationBtn');
    const notifPopup = document.getElementById('notifPopup');
    const notifBadge = document.getElementById('notificationBadge');
    const notificationsContainer = document.getElementById('notificationsContainer');
    const refreshBtn = document.getElementById('refreshNotifications');
    
    let isPopupOpen = false;
    let refreshInterval = null;
    
    console.log('Notification system initialized');
    
    // Function to show popup
    function showPopup() {
        console.log('showPopup called');
        if (notifPopup) {
            notifPopup.classList.remove('hidden');
            notifPopup.style.display = 'block';
            // Force reflow for animation
            notifPopup.offsetHeight;
            notifPopup.classList.add('open');
            isPopupOpen = true;
            console.log('Popup shown, isPopupOpen:', isPopupOpen);
            
            // Start auto-refresh when popup is open
            startAutoRefresh();
        }
    }
    
    // Function to hide popup
    function hidePopup() {
        console.log('hidePopup called');
        if (notifPopup) {
            notifPopup.classList.remove('open');
            setTimeout(() => {
                notifPopup.classList.add('hidden');
                notifPopup.style.display = 'none';
                isPopupOpen = false;
                console.log('Popup hidden, isPopupOpen:', isPopupOpen);
                
                // Stop auto-refresh when popup is closed
                stopAutoRefresh();
            }, 200); // Match CSS transition duration
        }
    }
    
    // Function to toggle popup
    function togglePopup() {
        console.log('togglePopup called, isPopupOpen:', isPopupOpen);
        if (isPopupOpen) {
            hidePopup();
        } else {
            showPopup();
        }
    }
    
    // Start auto-refresh for notifications
    function startAutoRefresh() {
        // Clear any existing interval
        stopAutoRefresh();
        
        // Refresh every 30 seconds when popup is open
        refreshInterval = setInterval(() => {
            refreshNotifications();
        }, 30000); // 30 seconds
    }
    
    // Stop auto-refresh
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
    
    // Refresh notifications from server
    function refreshNotifications() {
        if (!isPopupOpen) return;
        
        console.log('Refreshing notifications...');
        
        // Show refreshing indicator
        if (refreshBtn) {
            refreshBtn.classList.add('refreshing');
        }
        
        // Fetch updated notifications
        fetch('get_notifications.php?refresh=true&t=' + new Date().getTime())
            .then(response => response.text())
            .then(html => {
                // Update notifications container
                if (notificationsContainer) {
                    notificationsContainer.innerHTML = html;
                }
                
                // Update badge count
                updateNotificationBadge();
                
                // Remove refreshing indicator
                if (refreshBtn) {
                    setTimeout(() => {
                        refreshBtn.classList.remove('refreshing');
                    }, 500);
                }
                
                console.log('Notifications refreshed');
            })
            .catch(error => {
                console.error('Error refreshing notifications:', error);
                if (refreshBtn) {
                    refreshBtn.classList.remove('refreshing');
                }
            });
    }
    
    // Update notification badge count
    function updateNotificationBadge() {
        fetch('get_notification_count.php?t=' + new Date().getTime())
            .then(response => response.json())
            .then(data => {
                if (notifBadge) {
                    if (data.count > 0) {
                        notifBadge.textContent = data.count;
                        notifBadge.style.display = 'flex';
                        
                        // Show mark all as read button
                        const markReadBtn = document.getElementById('markReadBtn');
                        if (markReadBtn) {
                            markReadBtn.style.display = 'block';
                        }
                    } else {
                        notifBadge.style.display = 'none';
                        
                        // Hide mark all as read button
                        const markReadBtn = document.getElementById('markReadBtn');
                        if (markReadBtn) {
                            markReadBtn.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error updating notification badge:', error);
            });
    }
    
    // Mark notification as read when clicked
    function markNotificationAsRead(notificationId) {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationElement) {
                    notificationElement.classList.remove('notification-unread');
                    notificationElement.classList.add('notification-read');
                    
                    // Remove unread dot
                    const unreadDot = notificationElement.querySelector('.flex-shrink-0:last-child');
                    if (unreadDot) {
                        unreadDot.remove();
                    }
                    
                    // Update badge count
                    updateNotificationBadge();
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
   // Mark all notifications as read
function markAllNotificationsAsRead() {
    console.log('Marking all notifications as read...');
    
    // Get user_id from PHP variable - we need to pass this
    const userId = <?php echo $user_id; ?>;
    
    fetch('mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + userId
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark all as read response:', data);
        
        if (data.success) {
            console.log('All notifications marked as read');
            
            // Hide notification badge
            if (notifBadge) {
                notifBadge.style.display = 'none';
            }
            
            // Hide mark all as read button
            const markReadBtn = document.getElementById('markReadBtn');
            if (markReadBtn) {
                markReadBtn.style.display = 'none';
            }
            
            // Update all notifications to read state in UI
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('notification-unread');
                item.classList.add('notification-read');
                
                // Remove unread dots
                const unreadDot = item.querySelector('.flex-shrink-0:last-child');
                if (unreadDot) {
                    const blueDot = unreadDot.querySelector('.bg-blue-500, .w-2.h-2');
                    if (blueDot) {
                        unreadDot.remove();
                    }
                }
            });
            
            // Show confirmation message
            showConfirmationMessage(data.message || 'All notifications marked as read');
            
        } else {
            console.error('Failed to mark all as read:', data.message);
            showConfirmationMessage(data.message || 'Failed to mark all as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showConfirmationMessage('Error: ' + error.message, 'error');
    });
}
    
    // Show confirmation message
    function showConfirmationMessage(message, type = 'success') {
        const notificationHeader = document.querySelector('.p-4.border-b.border-gray-200');
        if (notificationHeader) {
            const originalContent = notificationHeader.innerHTML;
            const colorClass = type === 'success' ? 'text-green-600' : 'text-red-600';
            const icon = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';
            
            notificationHeader.innerHTML = `<span class="${colorClass}"><i class="fas ${icon} mr-2"></i>${message}</span>`;
            
            setTimeout(() => {
                notificationHeader.innerHTML = originalContent;
            }, 2000);
        }
    }
    
    // Notification button click handler
    if (notifButton) {
        console.log('Adding click listener to notification button');
        notifButton.addEventListener('click', function(e) {
            console.log('Notification button CLICKED');
            e.stopPropagation(); // Prevent event from bubbling up
            e.preventDefault();
            togglePopup();
        });
    }
    
    // Refresh button click handler
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            refreshNotifications();
        });
    }
    
    // Mark all as read button click handler - FIXED
    const markReadBtn = document.getElementById('markReadBtn');
    if (markReadBtn) {
        console.log('Adding click listener to mark all as read button');
        markReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Mark all as read button clicked');
            markAllNotificationsAsRead();
        });
    }
    
    // Close popup when clicking outside
    document.addEventListener('click', function(e) {
        setTimeout(() => {
            if (isPopupOpen && notifPopup) {
                const clickedOnPopup = notifPopup.contains(e.target);
                const clickedOnButton = notifButton.contains(e.target);
                
                if (!clickedOnPopup && !clickedOnButton) {
                    console.log('Click outside detected, closing popup');
                    hidePopup();
                }
            }
        }, 10);
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isPopupOpen) {
            console.log('Escape key pressed, closing popup');
            hidePopup();
        }
    });
    
    // Mark individual notification as read when clicked
    document.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && isPopupOpen) {
            const notificationId = notificationItem.dataset.notificationId;
            if (notificationId) {
                markNotificationAsRead(notificationId);
            }
        }
    });
    
    // Prevent popup close when clicking inside popup
    if (notifPopup) {
        notifPopup.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('Clicked inside popup, preventing close');
        });
    }
    
    // Auto-refresh notification badge every 60 seconds
    setInterval(() => {
        if (!isPopupOpen) {
            updateNotificationBadge();
        }
    }, 60000);
    
    // Debug: Log current state
    console.log('Initial state - isPopupOpen:', isPopupOpen);
    console.log('Popup visibility:', notifPopup?.classList.contains('hidden') ? 'hidden' : 'visible');
    
    // Initial badge update
    updateNotificationBadge();
});
</script>
</body>
</html>