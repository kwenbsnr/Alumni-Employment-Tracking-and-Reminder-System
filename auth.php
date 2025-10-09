<?php
session_start();
include("../connect.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $role = trim($_POST["role"]);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format.";
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['password'])) {
            // Save session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            // Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: ../admin/admin_dashboard.php");
            } else {
                header("Location: ../alumni/alumni_dashboard.php");
            }
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "User not found or role mismatch.";
    }
}
?>