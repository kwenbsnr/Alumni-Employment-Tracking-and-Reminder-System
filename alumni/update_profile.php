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

function log_alumni_activity($conn, $user_id, $action_type, $description = '') {
    $stmt = $conn->prepare("INSERT INTO alumni_activity_log (user_id, action_type, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action_type, $description);
    $stmt->execute();
    $stmt->close();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];

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
        // ---- 1. Retrieve & sanitise personal information -----------------------
        $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $middle_name = htmlspecialchars(trim($_POST['middle_name'] ?? ''));
        $suffix = htmlspecialchars(trim($_POST['suffix'] ?? ''));
        $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''));
        $gender = htmlspecialchars(trim($_POST['gender'] ?? ''));
        $civil_status = htmlspecialchars(trim($_POST['civil_status'] ?? ''));
        $citizenship = htmlspecialchars(trim($_POST['citizenship'] ?? ''));
        $contact_number = htmlspecialchars(trim($_POST['contact_number'] ?? ''));
        $program = htmlspecialchars(trim($_POST['program'] ?? ''));
        $batch_year = htmlspecialchars(trim($_POST['batch_year'] ?? ''));
        
        // Address fields
        $street = htmlspecialchars(trim($_POST['street'] ?? ''));
        $city = htmlspecialchars(trim($_POST['city'] ?? ''));
        $state_province = htmlspecialchars(trim($_POST['state_province'] ?? ''));
        $country = htmlspecialchars(trim($_POST['country'] ?? ''));
        
        // ---- 2. BACKEND VALIDATION (Personal information only) ----------------
        $required_personal = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender',
            'program' => 'Program',
            'batch_year' => 'Batch Year',
            'contact_number' => 'Contact Number'
        ];
        
        $required_address = [
            'street' => 'Street Address',
            'city' => 'City',
            'state_province' => 'State/Province',
            'country' => 'Country'
        ];
        
        foreach ($required_personal as $field => $label) {
            if (empty($$field)) {
                throw new Exception("{$label} is required.");
            }
        }
        
        foreach ($required_address as $field => $label) {
            if (empty($$field)) {
                throw new Exception("{$label} is required.");
            }
        }
        
        // Validate batch year
        if (!preg_match('/^\d{4}$/', $batch_year) || $batch_year < 1900 || $batch_year > date('Y')) {
            throw new Exception("Invalid batch year.");
        }
        
        // Validate date of birth
        $dob_date = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        if (!$dob_date || $dob_date->format('Y-m-d') !== $date_of_birth) {
            throw new Exception("Invalid date of birth format.");
        }
        
        // Check if user is at least 16 years old
        $today = new DateTime();
        $age = $today->diff($dob_date)->y;
        if ($age < 16) {
            throw new Exception("You must be at least 16 years old.");
        }
        
        // Validate gender
        $valid_genders = ['Male', 'Female', 'Other'];
        if (!in_array($gender, $valid_genders)) {
            throw new Exception("Invalid gender selection.");
        }
        
        // Validate civil status if provided
        if (!empty($civil_status)) {
            $valid_civil_statuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
            if (!in_array($civil_status, $valid_civil_statuses)) {
                throw new Exception("Invalid civil status selection.");
            }
        }
        
        // Validate contact number
        if (!preg_match('/^[0-9]{5,15}$/', $contact_number)) {
            throw new Exception("Contact number must be 5-15 digits.");
        }
        
        // ---- 3. Handle Profile Photo Upload ------------------------------------
        $photo_path = null;
        
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Validate file size
            if ($file['size'] > $max_size) {
                throw new Exception("Profile photo exceeds the 2MB size limit.");
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
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
            
            // Generate unique filename
            $timestamp = time();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $surname . '_' . $user_id . '_' . $timestamp . '.' . $extension;
            
            // Upload directory
            $upload_dir = '../uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload profile photo. Please try again.");
            }
            
            // Store relative path in database
            $photo_path = 'uploads/profile_photos/' . $filename;
        }
        
        // ---- 4. Update users table --------------------------------------------
        $stmt = $conn->prepare("
            UPDATE users SET 
                first_name = ?,
                last_name = ?,
                middle_name = ?,
                suffix = ?,
                date_of_birth = ?,
                gender = ?,
                civil_status = ?,
                citizenship = ?,
                contact_number = ?,
                program = ?,
                batch_year = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param(
            "sssssssssssi",
            $first_name,
            $last_name,
            $middle_name,
            $suffix,
            $date_of_birth,
            $gender,
            $civil_status,
            $citizenship,
            $contact_number,
            $program,
            $batch_year,
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update personal information.");
        }
        $stmt->close();
        
        // ---- 5. Update alumni_profile table -----------------------------------
        if ($photo_path) {
            // Update with new photo
            $stmt = $conn->prepare("
                INSERT INTO alumni_profile (user_id, photo_path, last_profile_update)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    photo_path = VALUES(photo_path),
                    last_profile_update = NOW()
            ");
            $stmt->bind_param("is", $user_id, $photo_path);
        } else {
            // Update without changing photo
            $stmt = $conn->prepare("
                INSERT INTO alumni_profile (user_id, last_profile_update)
                VALUES (?, NOW())
                ON DUPLICATE KEY UPDATE last_profile_update = NOW()
            ");
            $stmt->bind_param("i", $user_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update profile information.");
        }
        $stmt->close();
        
        // ---- 6. Update alumni_address table -----------------------------------
        // Check if address exists
        $stmt = $conn->prepare("SELECT address_id FROM alumni_address WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $address_exists = $result->num_rows > 0;
        $stmt->close();
        
        if ($address_exists) {
            // Update existing address
            $stmt = $conn->prepare("
                UPDATE alumni_address SET 
                    street = ?,
                    city = ?,
                    state_province = ?,
                    country = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->bind_param(
                "ssssi",
                $street,
                $city,
                $state_province,
                $country,
                $user_id
            );
        } else {
            // Insert new address
            $stmt = $conn->prepare("
                INSERT INTO alumni_address 
                (user_id, street, city, state_province, country, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param(
                "issss",
                $user_id,
                $street,
                $city,
                $state_province,
                $country
            );
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update address information.");
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        $activity_desc = 'Updated personal information';
        if ($photo_path) {
            $activity_desc .= ' and uploaded new profile photo';
        }
        log_alumni_activity($conn, $user_id, 'profile_updated', $activity_desc);
        
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
?>