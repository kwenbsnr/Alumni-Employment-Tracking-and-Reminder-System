<?php
ob_start(); // Buffer output to allow clean headers
session_start();
include("../connect.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $role = trim($_POST["role"] ?? '');

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo "Invalid email format.";
        exit;
    }

    // Check if email exists (prepared statement for security)
    $stmt = $conn->prepare("SELECT user_id, email, password, role FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID for security (prevent fixation)
            session_regenerate_id(true);
            
            // Save session
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            $stmt->close();
            $conn->close();
            ob_end_clean(); // Flush buffer before redirect

            // Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: ../admin/admin_dashboard.php");
            } else {
                header("Location: ../alumni/alumni_dashboard.php");
            }
            exit();
        } else {
            error_log("Failed login attempt for email: $email"); // Basic logging
            ob_end_clean();
            echo "Invalid password.";
        }
    } else {
        error_log("User not found or role mismatch for email: $email");
        ob_end_clean();
        echo "User not found or role mismatch.";
    }
    $stmt->close();
}
$conn->close();
ob_end_clean(); // Ensure clean exit
?>
