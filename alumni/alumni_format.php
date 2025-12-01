<?php
date_default_timezone_set('Asia/Manila');
// Fetch user data from users table - FIXED QUERY
$stmt = $conn->prepare("
    SELECT
        u.user_id, u.name as official_name, u.email, u.role,
        ap.photo_path, ap.contact_number, ap.employment_status, 
        ap.status, ap.submission_date, ap.employment_verified,
        ap.submission_status  -- This is the one used in dashboard & admin
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

// === DYNAMIC NOTIFICATIONS BASED ON REAL SUBMISSION STATUS (submission_status) ===
$notif_count = 0;
$notifications = [];

// Check submission period status from database
$submission_status_check = $conn->query("SELECT is_open, open_date, close_date FROM submission_status LIMIT 1");
$submission_period = $submission_status_check->fetch_assoc() ?? ['is_open' => 0];
$submissions_open = (bool)($submission_period['is_open'] ?? 0);
$open_date = $submission_period['open_date'] ?? null;
$close_date = $submission_period['close_date'] ?? null;

// Use the correct column: submission_status (from your dashboard & admin actions)
$submission_status = $profile['submission_status'] ?? null;
$submission_date   = $profile['submission_date'] ?? null;

// Notification 1: Submission period status (ALWAYS SHOW THIS)
$current_timestamp = date('Y-m-d H:i:s');
if (!$submissions_open) {
    $notifications[] = [
        'title'       => 'Submissions Currently Closed',
        'message'     => 'Profile submissions are currently closed. Please check back during the open submission period.',
        'timestamp'   => $current_timestamp,
        'type'        => 'error'
    ];
    $notif_count++;
} else {
    $notifications[] = [
        'title'       => 'Submissions Open',
        'message'     => 'Profile submissions are currently open. You can submit or update your alumni profile.',
        'timestamp'   => $current_timestamp,
        'type'        => 'success'
    ];
    $notif_count++;
}

// Add scheduled period information if available (when closed but scheduled)
if (!$submissions_open && $open_date && $close_date) {
    $from = date('M j, Y \a\t g:i A', strtotime($open_date));
    $to = date('M j, Y \a\t g:i A', strtotime($close_date));
    
    $notifications[] = [
        'title'       => 'Next Submission Period',
        'message'     => "Submissions will open from $from to $to",
        'timestamp'   => $current_timestamp,
        'type'        => 'info'
    ];
    $notif_count++;
}

// KEEP ALL EXISTING PROFILE NOTIFICATIONS (no conditions)
if ($submission_status) {
    if ($submission_status === 'Pending') {
        $notifications[] = [
            'title'       => 'Profile Under Review',
            'message'     => 'Your profile is currently being reviewed by the administrators.',
            'timestamp'   => $submission_date ?: $current_timestamp,
            'type'        => 'warning'
        ];
        $notif_count++;
    }
    elseif ($submission_status === 'Approved') {
        $notifications[] = [
            'title'       => 'Profile Approved',
            'message'     => 'Congratulations! Your alumni profile has been officially approved.',
            'timestamp'   => $submission_date ?: $current_timestamp,
            'type'        => 'success'
        ];
        // Optional: remove badge after approval (recommended)
        // Remove line below if you want badge to disappear after approval
        // $notif_count++;
    }
    elseif ($submission_status === 'Rejected') {
        $notifications[] = [
            'title'       => 'Action Required: Profile Rejected',
            'message'     => 'Your profile was rejected. Please review the feedback and resubmit.',
            'timestamp'   => $submission_date ?: $current_timestamp,
            'type'        => 'error'
        ];
        $notif_count++;
    }

    // Employment verification pending (only if not already approved)
    if (($profile['employment_verified'] ?? 0) == 0 && !empty($profile['employment_status'])) {
        $notifications[] = [
            'title'       => 'Employment Verification Pending',
            'message'     => 'Your employment details are still under review.',
            'timestamp'   => $current_timestamp,
            'type'        => 'warning'
        ];
        $notif_count++;
    }

} else {
    // No profile submitted yet
    $notifications[] = [
        'title'       => 'Complete Your Profile',
        'message'     => 'Welcome! Please fill out your alumni profile to get started.',
        'timestamp'   => $current_timestamp,
        'type'        => 'info'
    ];
    $notif_count++;
}

// Page title fallback
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
                    <div id="notifPopup" class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200 hidden z-50 transform transition-all duration-200 ease-in-out">
                        <div class="p-4 border-b border-gray-200 font-semibold text-gray-800 flex justify-between items-center text-sm bg-gray-50 rounded-t-xl">
                            <span>Notifications</span>
                            <?php if ($notif_count > 0): ?>
                                <button id="markReadBtn" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium">Mark all as read</button>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-96 overflow-y-auto text-sm">
                            <?php if (empty($notifications)): ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-2xl mb-3 text-gray-400"></i>
                                    <p class="text-gray-600">No notifications</p>
                                    <p class="text-xs text-gray-500 mt-1">You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $index => $notification): ?>
                                    <div class="p-4 hover:bg-gray-50 border-b border-gray-100 notification-item notification-<?php echo $notification['type']; ?>">
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
                                                <?php if ($notification['timestamp']): ?>
                                                    <p class="text-xs text-gray-500 mt-2">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($notification['timestamp'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification functionality
        const notifButton = document.getElementById('notificationBtn');
        const notifPopup = document.getElementById('notifPopup');
        const notifBadge = document.getElementById('notificationBadge');
        
        // Variables to track popup state
        let isPopupOpen = false;
        let isHoveringPopup = false;
        let closeTimeout = null;
        
        // Function to open popup
        function openPopup() {
            if (notifPopup) {
                notifPopup.classList.remove('hidden');
                isPopupOpen = true;
            }
        }
        
        // Function to close popup
        function closePopup() {
            if (notifPopup && isPopupOpen) {
                notifPopup.classList.add('hidden');
                isPopupOpen = false;
            }
        }
        
        // Function to schedule popup close (for auto-close when not hovered)
        function scheduleClose() {
            // Clear any existing timeout
            if (closeTimeout) {
                clearTimeout(closeTimeout);
            }
            
            // Set new timeout to close after 1 second if not hovering
            closeTimeout = setTimeout(() => {
                if (!isHoveringPopup) {
                    closePopup();
                }
            }, 1000);
        }
        
        // Notification button click handler
        if (notifButton && notifPopup) {
            notifButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle popup visibility
                if (isPopupOpen) {
                    closePopup();
                } else {
                    openPopup();
                }
            });

            // Track when mouse enters the popup
            notifPopup.addEventListener('mouseenter', function() {
                isHoveringPopup = true;
                
                // Clear any pending close timeout when hovering
                if (closeTimeout) {
                    clearTimeout(closeTimeout);
                }
            });
            
            // Track when mouse leaves the popup
            notifPopup.addEventListener('mouseleave', function() {
                isHoveringPopup = false;
                scheduleClose(); // Start close countdown
            });
            
            // Close popup when clicking outside
            document.addEventListener('click', function(e) {
                if (!notifButton.contains(e.target) && !notifPopup.contains(e.target)) {
                    closePopup();
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isPopupOpen) {
                    closePopup();
                }
            });

            // Mark all as read functionality
            const markReadBtn = document.getElementById('markReadBtn');
            if (markReadBtn) {
                markReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Hide notification badge
                    if (notifBadge) {
                        notifBadge.style.display = 'none';
                    }
                    
                    // Hide mark all as read button
                    markReadBtn.style.display = 'none';
                    
                    // Add visual feedback for read notifications
                    const notificationItems = document.querySelectorAll('.notification-item');
                    notificationItems.forEach(item => {
                        item.style.opacity = '0.6';
                        item.style.backgroundColor = '#f9fafb';
                    });
                    
                    // Show confirmation
                    const notificationHeader = notifPopup.querySelector('.p-4.border-b');
                    if (notificationHeader) {
                        const originalContent = notificationHeader.innerHTML;
                        notificationHeader.innerHTML = '<span class="text-green-600"><i class="fas fa-check mr-2"></i>All notifications marked as read</span>';
                        
                        setTimeout(() => {
                            notificationHeader.innerHTML = originalContent;
                            closePopup();
                        }, 1500);
                    } else {
                        setTimeout(() => {
                            closePopup();
                        }, 1000);
                    }
                });
            }
        }
    });
</script>
</body>
</html>