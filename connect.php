<?php
$host = "localhost";   
$user = "root";        
$pass = "";            
$db   = "alumtrak_experiment"; 

$conn = new mysqli($host, $user, $pass, $db);

// Comment by Jaafar
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FIX: Set character set to UTF-8 to handle apostrophes and special characters
$conn->set_charset("utf8mb4");

// Alternative issues are encountered:
// if (!$conn->set_charset("utf8mb4")) {
//     printf("Error loading character set utf8mb4: %s\n", $conn->error);
//     exit();
// }
?>