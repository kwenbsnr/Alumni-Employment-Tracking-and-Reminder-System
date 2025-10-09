<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="login.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

  <!-- Login Box -->
  <div class="login-box bg-white shadow-lg p-8 rounded-xl w-full max-w-md">
    <h2 class="text-2xl font-bold text-center mb-6">Login Portal</h2>

    <!-- Role Selection -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="role-selector" data-role="alumni">
        <i class="fas fa-user-graduate"></i>
        <p>Alumni</p>
      </div>
      <div class="role-selector" data-role="admin">
        <i class="fas fa-user-shield"></i>
        <p>Admin</p>
      </div>
    </div>

    <!-- Login Form -->
    <form id="loginForm" method="POST" action="auth.php" class="space-y-4">
      <input type="hidden" name="role" id="selectedRole">

      <div>
        <label>Email</label>
        <input type="email" name="email" required class="form-input">
      </div>

      <div>
        <label>Password</label>
        <input type="password" name="password" required class="form-input">
      </div>

      <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
        Sign In
      </button>
    </form>
  </div>

  <!-- FontAwesome -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
  <!-- Role Selection Script -->
  <script src="login.js"></script>
</body>
</html>
