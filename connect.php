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
?>