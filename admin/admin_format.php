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

<script>
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
    
    // Toggle user dropdown
    const userMenuButton = document.getElementById('userMenuButton');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', () => {
            userDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    }
});
</script>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <nav class="w-72 admin-gradient-bg text-white flex-shrink-0 flex flex-col h-screen justify-between sticky top-0">
            <div class="p-6 flex-grow flex flex-col">
                <!-- Logo/Brand -->
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-user-shield text-xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-xl">Admin Panel</h2>
                        <p class="text-sm text-blue-100">Management System</p>
                    </div>
                </div>
                
                <!-- Navigation -->
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
                   
                </ul>

                <!-- Logout -->
                <div class="pt-6">
                    <hr class="border-blue-400 my-4">
                    <a href="../login/logout.php" class="flex items-center space-x-3 text-blue-100 hover:text-white p-3 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Sidebar Footer -->
            
        </nav>

      <!-- Main Content -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Top Bar -->
    <header class="bg-white header-shadow z-10">
        <div class="flex items-center justify-between p-4">
            <!-- Page Title and Breadcrumb -->
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
                    } elseif ($active_page === 'activity_log') {
                        $welcome_text = "Track updates, approvals, rejections, and other admin activities.";
                    } elseif ($active_page === 'dashboard') {
                        $welcome_text = "Welcome back! Here's what's happening today.";
                    }
                    ?>
                    
                    <span class="text-gray-500"><?php echo htmlspecialchars($welcome_text, ENT_QUOTES, 'UTF-8'); ?></span>
                
                </nav>
            </div>
                    <!-- Right Side Actions -->
                    <div class="flex items-center space-x-4">
                       
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="relative text-gray-600 hover:text-blue-600 transition p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-bell text-xl"></i>
                                <span class="notification-badge"></span>
                            </button>
                        </div>
                        
                        <!-- User Menu -->
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

            <!-- Dynamic Content -->
            <main class="flex-1 p-6 overflow-auto bg-gray-50">
                <?php echo $page_content ?? ''; ?>
            </main>
        </div>
    </div>

    <!-- Custom Toast -->
    <div id="customToast" class="fixed bottom-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg flex items-center space-x-2 z-50 hidden">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script>
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