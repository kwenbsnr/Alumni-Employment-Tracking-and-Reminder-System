<?php


// Fetch user data from users table - FIXED QUERY
$stmt = $conn->prepare("
    SELECT 
        u.user_id, u.name as official_name, u.email, u.role,
        ap.photo_path, ap.contact_number, ap.employment_status
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
            --primary-green: #034f03;
            --forest-green: #026a02;
            --lime-green: #016801;
            --sea-green: #1d681d;
            --light-bg: #06690e;
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
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-72 gradient-bg text-white flex-shrink-0">
        <div class="sidebar-wrapper flex flex-col justify-between">
            <div class="p-6">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-lg" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-bold text-lg">Alumni Portal</h2>
                </div>

                <!-- User Profile -->
                <div class="sidebar-profile pb-6 mb-6">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="sidebar-profile-avatar">
                            <?php
                            if ($photo_path && file_exists("../" . $photo_path)) {
                                $photo_url = "../" . htmlspecialchars($photo_path);
                                // Add timestamp to prevent browser caching
                                $photo_url .= '?v=' . filemtime("../" . $photo_path);
                                echo '<img src="' . $photo_url . '" alt="Profile" class="w-full h-full object-cover">';
                            } else {
                                // Show initials
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
                        Welcome back, <span class="font-semibold text-green-700"><?php echo htmlspecialchars($full_name); ?></span>!
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Header Actions -->
            <div class="flex items-center gap-3">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationBtn" class="relative p-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                        <i class="fas fa-bell text-lg text-gray-700"></i>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                    <div id="notifPopup" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 hidden z-50">
                        <div class="p-4 border-b font-semibold text-gray-800 flex justify-between items-center text-sm">
                            Notifications
                            <button id="markReadBtn" class="text-xs text-blue-600 hover:underline">Mark all as read</button>
                        </div>
                        <div class="max-h-96 overflow-y-auto text-sm">
                            <div class="p-4 hover:bg-gray-50 border-b">
                                <p class="font-medium">New Job Fair Event</p>
                                <p class="text-xs text-gray-600">Join us on Sept 15, 2025</p>
                            </div>
                            <div class="p-4 hover:bg-gray-50 border-b">
                                <p class="font-medium">Alumni Homecoming</p>
                                <p class="text-xs text-gray-600">Oct 10, 2025 – Save the date!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Button -->
                <button id="helpButton" class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-medium text-sm rounded-lg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-question-circle text-sm"></i>
                    <span>Help</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 p-5 overflow-hidden">
            <?php echo $page_content ?? ''; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Notification functionality
            const notifButton = document.getElementById('notificationBtn');
            const notifPopup = document.getElementById('notifPopup');
            
            if (notifButton && notifPopup) {
                notifButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifPopup.classList.toggle('hidden');
                });

                document.addEventListener('click', (e) => {
                    if (!notifPopup.classList.contains('hidden') && 
                        !notifPopup.contains(e.target) && 
                        e.target !== notifButton) {
                        notifPopup.classList.add('hidden');
                    }
                });

                const markReadBtn = document.getElementById('markReadBtn');
                if (markReadBtn) {
                    markReadBtn.addEventListener('click', () => {
                        const notifBadge = notifButton.querySelector('span');
                        if (notifBadge) {
                            notifBadge.classList.add('hidden');
                        }
                    });
                }
            }

            // Help button functionality
            const helpButton = document.getElementById('helpButton');
            if (helpButton) {
                helpButton.addEventListener('click', () => {
                    alert('Need help? Contact the alumni support team at alumtrak@jhcsc.edu.ph');
                });
            }
        });
    </script>
</body>
</html>