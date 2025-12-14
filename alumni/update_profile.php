<?php
// Development error reporting only
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: ../login/login.php");
    exit();
}

$is_development = $_SERVER['SERVER_NAME'] === 'localhost' || 
                  $_SERVER['SERVER_NAME'] === '127.0.0.1' || 
                  (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development');

include("../connect.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: ../login/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ---- 1. Profile Photo Upload Helper -----------------------------------------
function upload_profile_photo($user_id) {
    global $conn;
    
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES['profile_photo'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['size'] > $max_size) {
        throw new Exception("Profile photo is too large. Maximum allowed is 2MB.");
    }

    // Validate file type
    $valid_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $valid_types)) {
        throw new Exception("Invalid file type. Only JPG and PNG files are allowed.");
    }

    // Get user's surname for filename
    $user_stmt = $conn->prepare("SELECT last_name FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();

    $surname = 'Unknown';
    if ($user_data && !empty($user_data['last_name'])) {
        $surname = preg_replace("/[^a-zA-Z0-9]/", "", $user_data['last_name']);
        $surname = ucfirst($surname);
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/photos/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("Could not create upload directory.");
        }
    }

    // Generate unique filename
    $timestamp = time();
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $surname . '_profile_' . $timestamp . '.' . $ext;
    $target_path = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception("Failed to upload profile photo. Please try again.");
    }
    
    // Return relative path for database storage
    return 'uploads/photos/' . $filename;
}

// ---- POST handling --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_development) {
        error_log("=== PROFILE UPDATE START ===");
        error_log("POST Data: " . print_r($_POST, true));
        error_log("FILES Data: " . print_r($_FILES, true));
        error_log("User ID: " . $user_id);
    }
    
    $conn->begin_transaction();
    
    try {
        // ---- 1. Retrieve & sanitise PERSONAL DATA ONLY -----------------------
        $contact = !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

        // Address fields
        $country = !empty($_POST['country']) ? trim($_POST['country']) : '';
        $state_province = !empty($_POST['state_province']) ? trim($_POST['state_province']) : '';
        $city = !empty($_POST['city']) ? trim($_POST['city']) : '';
        $street = !empty($_POST['street']) ? trim($_POST['street']) : '';

        // ---- 2. BACKEND VALIDATION - PERSONAL DATA ONLY --------------------
        if (empty($contact)) {
            throw new Exception("Contact number is required.");
        }

        // Address validation - REQUIRED FIELDS
        if (empty($country) || empty($state_province) || empty($city)) {
            throw new Exception("Address information is required (Country, State/Province, and City).");
        }

        // ---- 3. Profile Photo Handling ---------------------------------------
        $photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo_path = upload_profile_photo($user_id);
        } else {
            // Keep existing photo if no new photo uploaded
            $stmt = $conn->prepare("SELECT photo_path FROM alumni_profile WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_photo = $result->fetch_assoc();
            $stmt->close();
            
            $photo_path = $existing_photo['photo_path'] ?? null;
        }

        // === UPDATE USERS TABLE WITH CONTACT NUMBER ==========
        if (!empty($contact)) {
            $stmt = $conn->prepare("UPDATE users SET contact_number = ? WHERE user_id = ?");
            $stmt->bind_param("si", $contact, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update contact number in user profile.");
            }
            $stmt->close();
        }

        // === UPDATE alumni_profile TABLE WITH PHOTO PATH ==========
        // Check if alumni_profile record exists
        $check_stmt = $conn->prepare("SELECT user_id FROM alumni_profile WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $profile_exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
        
        if ($profile_exists) {
            // Update existing profile - photo_path and timestamp
            $stmt = $conn->prepare("UPDATE alumni_profile SET 
                photo_path = ?, 
                last_profile_update = NOW()
                WHERE user_id = ?");
            $stmt->bind_param("si", $photo_path, $user_id);
        } else {
            // Insert new profile - user_id and photo_path
            $stmt = $conn->prepare("INSERT INTO alumni_profile 
                (user_id, photo_path, last_profile_update)
                VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $user_id, $photo_path);
        }

        if (!$stmt->execute()) {
            error_log("Alumni profile update failed: " . $stmt->error);
            throw new Exception("Failed to save profile photo. Please try again.");
        }
        $stmt->close();

        // ---- Address handling ----
        $stmt = $conn->prepare("SELECT address_id FROM alumni_address WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_address = $result->fetch_assoc();
        $stmt->close();

        if ($existing_address) {
            // Update existing address
            $stmt = $conn->prepare("UPDATE alumni_address SET 
                city = ?, state_province = ?, street = ?, country = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?");
            $stmt->bind_param("ssssi", 
                $city, $state_province, $street, $country, $user_id
            );
        } else {
            // Insert new address
            $stmt = $conn->prepare("INSERT INTO alumni_address 
                (user_id, city, state_province, street, country) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", 
                $user_id, $city, $state_province, $street, $country
            );
        }

        if (!$stmt->execute()) {
            error_log("Address save error: " . $stmt->error);
            throw new Exception("Failed to save address information. Please try again.");
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Update session with new photo path for sidebar display
        if ($photo_path) {
            $_SESSION['user_photo'] = $photo_path;
        }

        // Redirect after successful submission
        header("Location: alumni_profile.php?success=Personal information updated successfully!");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Critical: Profile update failed for user $user_id - " . ($e->getMessage() ?? 'Unknown error'));
        header("Location: alumni_profile.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}                                                                                        

$conn->close();
ob_end_flush();