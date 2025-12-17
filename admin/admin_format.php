<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

// Include database connection for admin data
include("../connect.php");

// Helper functions for consistent styling across admin pages
if (!function_exists('getEmploymentStatusColor')) {
    function getEmploymentStatusColor($status) {
        if (empty($status) || $status === 'Not Started') {
            return 'bg-gray-100 text-gray-800 border-gray-200';
        }
        switch ($status) {
            case 'Unemployed': return 'bg-red-100 text-red-800 border-red-200';
            case 'Self-Employed': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'Employed': return 'bg-green-100 text-green-800 border-green-200';
            case 'Student': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'Employed & Student': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    }
}

if (!function_exists('getSubmissionStatusColor')) {
    function getSubmissionStatusColor($status) {
        if (empty($status) || $status === 'Not Started') {
            return 'bg-gray-100 text-gray-800 border-gray-200';
        }
        switch ($status) {
            case 'Approved': return 'bg-green-100 text-green-800 border-green-200';
            case 'Pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'Rejected': return 'bg-red-100 text-red-800 border-red-200';
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    }
}

if (!function_exists('getEmploymentStatusIcon')) {
    function getEmploymentStatusIcon($status) {
        if (empty($status)) return 'fas fa-user-clock text-gray-600';
        switch ($status) {
            case 'Unemployed': return 'fas fa-user-slash text-red-600';
            case 'Self-Employed': return 'fas fa-briefcase text-blue-600';
            case 'Employed': return 'fas fa-building text-green-600';
            case 'Student': return 'fas fa-graduation-cap text-purple-600';
            case 'Employed & Student': return 'fas fa-user-graduate text-yellow-600';
            default: return 'fas fa-user-clock text-gray-600';
        }
    }
}

if (!function_exists('getSubmissionStatusIcon')) {
    function getSubmissionStatusIcon($status) {
        if (empty($status)) return 'fas fa-user-clock text-gray-600';
        switch ($status) {
            case 'Approved': return 'fas fa-check-circle text-green-600';
            case 'Pending': return 'fas fa-clock text-yellow-600';
            case 'Rejected': return 'fas fa-times-circle text-red-600';
            default: return 'fas fa-user-clock text-gray-600';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? "Admin Dashboard", ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom CSS for modern design */
        :root {
            --primary-blue: #3b404aff;
            --primary-blue-light: #9ca3af;
            --primary-blue-dark: #22262cff;
        }

        .header-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border-bottom: 2px solid var(--primary-blue);
        }

        .admin-gradient-bg {
            background: linear-gradient(180deg, var(--primary-blue-dark) 50%, var(--primary-blue-dark) 100%);
        }

        .admin-sidebar-item {
            transition: all 0.3s ease;
            position: relative;
            font-size: 1.1rem;
        }
        
        .admin-sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .admin-sidebar-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .admin-sidebar-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: white;
            border-radius: 0 4px 4px 0;
        }
        
        .admin-avatar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
            box-shadow: 0 4px 6px -1px rgba(7, 42, 200, 0.2);
        }
        
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        #customToast {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        #customToast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .search-container {
            position: relative;
        }
        
        .search-input {
            padding-left: 40px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(7, 42, 200, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        .user-dropdown {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        
        .user-dropdown a {
            font-size: 1rem;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <nav class="w-72 admin-gradient-bg text-white flex-shrink-0 flex flex-col h-screen justify-between sticky top-0">
            <div class="p-6 flex-grow flex flex-col">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-user-shield text-xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-xl">Admin Panel</h2>
                        <p class="text-sm text-blue-100">Management System</p>
                    </div>
                </div>
                
                <ul class="space-y-2 flex-grow">
                    <li>
                        <a href="admin_dashboard.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                            <i class="fas fa-tachometer-alt w-6 text-lg" aria-hidden="true"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="alumni_management.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'alumni_management' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                            <i class="fas fa-users w-6 text-lg" aria-hidden="true"></i>
                            <span>Alumni Records</span>
                        </a>
                    </li>
                   <li>
                        <a href="report_generation.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'report_generation' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                            <i class="fas fa-file-export w-6 text-lg" aria-hidden="true"></i>
                            <span>Generate Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="submission_schedule.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'submission_schedule' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                            <i class="fas fa-calendar-alt w-6 text-lg" aria-hidden="true"></i>
                            <span>Submission Schedule</span>
                        </a>
                    </li>
                  <li>
    <a href="submission_review.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'submission_review' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
        <i class="fas fa-clipboard-check w-6 text-lg" aria-hidden="true"></i>
        <span>Submission Review</span>
    </a>
</li>
                   <li>
                        <a href="activity_log.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'activity_log' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                            <i class="fas fa-history w-6 text-lg" aria-hidden="true"></i>
                            <span>Activity Log</span>
                        </a>
                    </li>
                </ul>

                <div class="pt-6">
                    <hr class="border-blue-400 my-4">
                    <a href="../login/logout.php" class="flex items-center space-x-3 text-blue-100 hover:text-white p-3 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white header-shadow sticky top-0 z-20"> 
                <div class="flex items-center justify-between p-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo htmlspecialchars($page_title ?? "Admin Dashboard", ENT_QUOTES, 'UTF-8'); ?>
                        </h1>
                        <nav class="flex text-base text-gray-500 mt-1">
                         
<?php
$active_page = $active_page ?? 'dashboard';
$welcome_text = "Welcome back! Here's what's happening today.";

if ($active_page === 'alumni_management') {
    $welcome_text = "Manage and view all alumni records in the system.";
} elseif ($active_page === 'report_generation') {
    $welcome_text = "Generate comprehensive reports on alumni data.";
} elseif ($active_page === 'submission_schedule') {
    $welcome_text = "Set and manage the alumni profile submission period.";
} elseif ($active_page === 'activity_log') {
    $welcome_text = "Track updates, approvals, rejections, and other admin activities.";
} elseif ($active_page === 'submission_review') {
    $welcome_text = "Review recent alumni employment submissions and notifications.";
} elseif ($active_page === 'dashboard') {
    $welcome_text = "Welcome back! Here's what's happening today.";
}
?> <span class="text-gray-500"><?php echo htmlspecialchars($welcome_text, ENT_QUOTES, 'UTF-8'); ?></span>
                        </nav>
                    </div>
                   <!-- Update the header section of admin_format.php --><div class="flex items-center space-x-4">
    <!-- Notification Bell -->
    <div class="relative">
        <button id="notificationBell" class="relative text-gray-600 hover:text-blue-600 transition p-2 rounded-full hover:bg-gray-100">
            <i class="fas fa-bell text-xl"></i>
            <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center hidden">
                0
            </span>
        </button>
        
        <!-- Notification Dropdown -->
        <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-hidden">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Notifications</h3>
                    <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Mark all as read
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Recent alumni submissions</p>
            </div>
            
            <div id="notificationList" class="overflow-y-auto max-h-80">
                <!-- Notifications will be loaded here -->
                <div class="p-8 text-center">
                    <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-sm">No new notifications</p>
                </div>
            </div>
            
            <div class="p-3 border-t border-gray-200 bg-gray-50 text-center">
                <a href="submission_review.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    View all submissions →
                </a>
            </div>
        </div>
    </div>
    
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none">
                                <?php 
                                // Fetch admin name from users table
                                $stmt = $conn->prepare("SELECT first_name, last_name, middle_name, suffix FROM users WHERE user_id = ? AND role = 'admin'");
                                $stmt->bind_param("i", $_SESSION["user_id"]);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $admin_data = $result->fetch_assoc();
                                $admin_name = 'AD';
                                $full_admin_name = $_SESSION["email"]; // Default to email

                                if ($admin_data) {
                                    // Build full name from individual fields
                                    $name_parts = [];
                                    if (!empty($admin_data['first_name'])) $name_parts[] = $admin_data['first_name'];
                                    if (!empty($admin_data['middle_name'])) $name_parts[] = $admin_data['middle_name'];
                                    if (!empty($admin_data['last_name'])) $name_parts[] = $admin_data['last_name'];
                                    if (!empty($admin_data['suffix'])) $name_parts[] = $admin_data['suffix'];
                                    
                                    $full_admin_name = implode(' ', $name_parts);
                                    $admin_name = strtoupper(substr($admin_data['first_name'] ?? '', 0, 1) . substr($admin_data['last_name'] ?? '', 0, 1));
                                }
                                $stmt->close();
                                ?>
                                
                                <div class="admin-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="hidden md:block text-left">
                                    <p class="font-medium text-gray-800 text-lg">
                                        <?php echo htmlspecialchars($full_admin_name, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                    <p class="text-gray-500 text-base">
                                        <?php echo htmlspecialchars($_SESSION["email"], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-auto bg-gray-50">
                <?php echo $page_content ?? ''; ?>
            </main>
        </div>
    </div>

    <div id="customToast" class="fixed bottom-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg flex items-center space-x-2 z-50 hidden">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script>// Update the script section in admin_format.php
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
    
// Notification system
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationBadge = document.getElementById('notificationBadge');
const notificationList = document.getElementById('notificationList');
const markAllReadBtn = document.getElementById('markAllRead');

// Load notifications on page load
loadNotifications();

// Poll for new notifications every 30 seconds
setInterval(loadNotifications, 30000);

// Toggle notification dropdown
if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle('hidden');
        
        // Mark as read when opened
        if (!notificationDropdown.classList.contains('hidden')) {
            markNotificationsAsSeen();
        }
    });
}

// Mark all as read
if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        await markAllNotificationsAsRead();
        loadNotifications();
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (notificationDropdown && !notificationDropdown.contains(e.target) && 
        notificationBell && !notificationBell.contains(e.target)) {
        notificationDropdown.classList.add('hidden');
    }
});

// Load notification data
async function loadNotifications() {
    try {
        const response = await fetch('api/get_notifications.php');
        const data = await response.json();
        
        updateNotificationBadge(data.unread_count);
        renderNotificationList(data.notifications);
    } catch (error) {
        console.error('Failed to load notifications:', error);
    }
}

function updateNotificationBadge(count) {
    if (!notificationBadge) return;
    
    if (count > 0) {
        notificationBadge.textContent = count > 99 ? '99+' : count;
        notificationBadge.classList.remove('hidden');
        
        // Add animation for new notifications
        if (count > parseInt(notificationBadge.textContent || '0')) {
            notificationBadge.classList.add('animate-pulse');
            setTimeout(() => {
                notificationBadge.classList.remove('animate-pulse');
            }, 2000);
        }
    } else {
        notificationBadge.classList.add('hidden');
    }
}

function renderNotificationList(notifications) {
    if (!notificationList) return;
    
    if (!notifications || notifications.length === 0) {
        notificationList.innerHTML = `
            <div class="p-8 text-center">
                <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                <p class="text-gray-500 text-sm">No new notifications</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="divide-y divide-gray-100">';
    
    notifications.forEach(notification => {
        const timeAgo = getTimeAgo(notification.submission_time);
        const isUnread = !notification.is_read;
        const bgClass = isUnread ? 'bg-blue-50' : 'bg-white';
        const dotClass = isUnread ? 'bg-blue-500' : 'bg-gray-300';
        
        // Determine icon based on notification type
        let icon = 'fa-briefcase';
        let iconColor = 'text-blue-500';
        
        switch(notification.notification_type) {
            case 'new_submission':
                icon = 'fa-user-plus';
                iconColor = 'text-green-500';
                break;
            case 'resubmission':
                icon = 'fa-redo';
                iconColor = 'text-orange-500';
                break;
            case 'update':
                icon = 'fa-sync-alt';
                iconColor = 'text-purple-500';
                break;
        }
        
        html += `
            <div class="p-4 ${bgClass} hover:bg-gray-50 transition-colors cursor-pointer notification-item" 
                 data-id="${notification.notification_id}">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mr-3">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas ${icon} ${iconColor}"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                ${escapeHtml(notification.alumni_name)}
                            </p>
                            <span class="flex-shrink-0 ml-2">
                                <span class="inline-block w-2 h-2 rounded-full ${dotClass}"></span>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            ${getNotificationMessage(notification.notification_type, notification.employment_status)}
                        </p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-500">
                                Batch ${notification.batch_year || 'N/A'}
                            </span>
                            <span class="text-xs text-gray-500">
                                ${timeAgo}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    notificationList.innerHTML = html;
    
    // Add click handlers to notification items
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', async function() {
            const notificationId = this.getAttribute('data-id');
            await markNotificationAsRead(notificationId);
            window.location.href = 'submission_review.php';
        });
    });
}

function getNotificationMessage(type, status) {
    const messages = {
        'new_submission': `Submitted employment information (${status})`,
        'resubmission': `Resubmitted after rejection (${status})`,
        'update': `Updated employment information (${status})`
    };
    return messages[type] || 'New submission';
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diffMs = now - past;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return past.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// API calls
async function markNotificationsAsSeen() {
    try {
        await fetch('api/mark_notifications_seen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    } catch (error) {
        console.error('Failed to mark notifications as seen:', error);
    }
}

async function markNotificationAsRead(notificationId) {
    try {
        await fetch('api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        });
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

async function markAllNotificationsAsRead() {
    try {
        await fetch('api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    }
}
    function showToast(message, type = 'success') {
        const toast = document.getElementById('customToast');
        const toastMessage = document.getElementById('toastMessage');
        const icon = toast.querySelector('i');
        toastMessage.textContent = message;
        
        // Remove all existing color classes
        toast.classList.remove('bg-green-500', 'bg-red-500');
        icon.classList.remove('fa-check-circle', 'fa-times-circle');
        
        if (type === 'error') {
            toast.classList.add('bg-red-500');
            icon.classList.add('fa-times-circle');
        } else {
            toast.classList.add('bg-green-500');
            icon.classList.add('fa-check-circle');
        }
        
        toast.classList.remove('hidden');
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 300);
        }, 3000);
    }
    </script>
</body>
</html>