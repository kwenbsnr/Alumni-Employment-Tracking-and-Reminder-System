<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? "Admin Dashboard"; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin_format.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <nav class="w-64 admin-gradient-bg text-white flex-shrink-0">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-user-shield text-lg" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-bold text-lg">Admin</h2>
                </div>
                <ul class="space-y-2">
                    <li><a href="admin_dashboard.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'admin_dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="alumni_management.php" class="sidebar-item admin-sidebar-item <?php echo ($active_page ?? '') === 'alumni_management' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-users w-5" aria-hidden="true"></i>
                        <span>Alumni Management</span>
                    </a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b p-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php echo $page_title ?? "Admin Dashboard"; ?>
                    </h1>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-3">
                            <div class="admin-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">
                                AD
                            </div>
                            <div class="hidden md:block">
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION["email"]); ?></p>
                            </div>
                        </div>
                        <a href="../login/logout.php" class="text-gray-600 hover:text-red-600 transition-colors flex items-center space-x-2">
                            <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Dynamic Content -->
            <main class="flex-1 p-6 overflow-auto">
                <?php echo $page_content ?? ''; ?>
            </main>
        </div>
    </div>
</body>
</html>