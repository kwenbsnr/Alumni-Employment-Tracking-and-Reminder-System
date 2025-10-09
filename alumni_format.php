<?php
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

$user_id = $_SESSION["user_id"];

// Fetch alumni info
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];

$full_name = $profile ? trim($profile['first_name'] . ' ' . ($profile['middle_name'] ?? '') . ' ' . $profile['last_name']) : 'Alumni User';

// Set default page title
$page_title = $page_title ?? "Alumni Page";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="alumni_format.css">
  <script>
    function toggleFields() {
      const status = document.getElementById("employment_status")?.value;
      document.getElementById("coe")?.classList.toggle("hidden", status !== "Employed");
      document.getElementById("business_cert")?.classList.toggle("hidden", status !== "Self-Employed");
      document.getElementById("cor")?.classList.toggle("hidden", status !== "Student");
      document.getElementById("employment_details")?.classList.toggle("hidden", status !== "Employed");
    }
  </script>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex">
  <!-- Sidebar -->
  <div class="w-72 gradient-bg text-white flex-shrink-0">
    <div class="p-6">
      <div class="flex items-center space-x-3 mb-8">
        <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
            <i class="fas fa-graduation-cap text-lg" aria-hidden="true"></i>
        </div>
        <h2 class="font-bold text-lg">Alumni</h2>
      </div>
      <nav class="space-y-2">
        <a href="alumni_dashboard.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
          <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
          <span>Dashboard</span>
        </a>
        <a href="alumni_profile.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
          <i class="fas fa-user w-5" aria-hidden="true"></i>
          <span>Alumni Profile</span>
        </a>
      </nav>
      <div class="mt-auto pt-4">
        <a href="../login/logout.php" class="flex items-center space-x-3 text-gray-300 hover:text-red-500 p-3 rounded-lg">
          <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col">
    <!-- Top Bar -->
    <header class="bg-white shadow-sm border-b p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">
            <?php echo htmlspecialchars($page_title); ?>
        </h1>
        <div class="flex items-center space-x-3">
          <div class="profile-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">
            ALU
          </div>
          <div class="hidden md:block">
            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($full_name); ?></p>
            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 p-6 overflow-auto">
    <?php 
    if (isset($page_content)) {
        echo $page_content;
    }
    ?>
    </main>
  </div>
</div>
</body>
</html>