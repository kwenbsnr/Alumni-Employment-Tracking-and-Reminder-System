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
        // Read-only fields (preserved from database)
        $student_id = htmlspecialchars(trim($_POST['student_id'] ?? ''));
        $email = htmlspecialchars(trim($_POST['email'] ?? ''));
        $program = htmlspecialchars(trim($_POST['program'] ?? ''));
        $batch_year = htmlspecialchars(trim($_POST['batch_year'] ?? ''));
        $citizenship = htmlspecialchars(trim($_POST['citizenship'] ?? 'Filipino'));
        $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''));
        $gender = htmlspecialchars(trim($_POST['gender'] ?? ''));
        
        // Editable Name fields
        $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $middle_name = htmlspecialchars(trim($_POST['middle_name'] ?? ''));
        $suffix = htmlspecialchars(trim($_POST['suffix'] ?? ''));
        
        // Editable fields
        $civil_status = htmlspecialchars(trim($_POST['civil_status'] ?? ''));
        $contact_number = htmlspecialchars(trim($_POST['contact_number'] ?? ''));
        
        // Address fields (editable)
        $street = htmlspecialchars(trim($_POST['street'] ?? ''));
        $city = htmlspecialchars(trim($_POST['city'] ?? ''));
        $state_province = htmlspecialchars(trim($_POST['state_province'] ?? ''));
        $country = htmlspecialchars(trim($_POST['country'] ?? ''));
        
        // ---- 2. BACKEND VALIDATION --------------------------------------------
        // Validate required editable fields
        $required_fields = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'contact_number' => 'Contact Number',
            'street' => 'Street Address',
            'city' => 'City',
            'state_province' => 'State/Province',
            'country' => 'Country'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($$field)) {
                throw new Exception("{$label} is required.");
            }
        }
        
        // Validate name lengths according to database schema
        if (strlen($first_name) > 100) {
            throw new Exception("First name cannot exceed 100 characters.");
        }
        
        if (strlen($last_name) > 100) {
            throw new Exception("Last name cannot exceed 100 characters.");
        }
        
        if (strlen($middle_name) > 100) {
            throw new Exception("Middle name cannot exceed 100 characters.");
        }
        
        if (strlen($suffix) > 10) {
            throw new Exception("Suffix cannot exceed 10 characters.");
        }
        
        // Validate contact number
        if (!preg_match('/^[0-9]{5,15}$/', $contact_number)) {
            throw new Exception("Contact number must be 5-15 digits.");
        }
        
        // Validate civil status if provided
        if (!empty($civil_status)) {
            $valid_civil_statuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
            if (!in_array($civil_status, $valid_civil_statuses)) {
                throw new Exception("Invalid civil status selection.");
            }
        }
        
        // Validate address field lengths according to database schema
        if (strlen($street) > 255) {
            throw new Exception("Street address cannot exceed 255 characters.");
        }
        
        if (strlen($city) > 100) {
            throw new Exception("City cannot exceed 100 characters.");
        }
        
        if (strlen($state_province) > 100) {
            throw new Exception("State/Province cannot exceed 100 characters.");
        }
        
        if (strlen($country) > 100) {
            throw new Exception("Country cannot exceed 100 characters.");
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
            
            // Get user's last name for filename
            $user_stmt = $conn->prepare("SELECT last_name FROM users WHERE user_id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_stmt->close();

            $surname = 'unknown';
            if ($user_data && !empty($user_data['last_name'])) {
                // Clean and format last name - lowercase, remove special characters
                $surname = preg_replace("/[^a-zA-Z0-9]/", "", $user_data['last_name']);
                $surname = strtolower($surname);
            }
            
            // Generate simple filename: lastname_profile_last5charssauniqid.extension
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $surname . '_profile_' . substr(uniqid(), -5) . '.' . $extension;
            
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
        
        // ---- 4. Update users table (editable fields only) ----------------------
        $stmt = $conn->prepare("
            UPDATE users SET 
                first_name = ?,
                last_name = ?,
                middle_name = ?,
                suffix = ?,
                civil_status = ?,
                contact_number = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param(
            "ssssssi",
            $first_name,
            $last_name,
            $middle_name,
            $suffix,
            $civil_status,
            $contact_number,
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
        
        // ---- 6. Update alumni_address table (UPDATE ONLY, no INSERT) ---------
        // First, verify that an address record exists (it should always exist)
        $stmt = $conn->prepare("SELECT address_id FROM alumni_address WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // This should never happen, but create a default if missing
            $stmt = $conn->prepare("
                INSERT INTO alumni_address 
                (user_id, country, state_province, city, street, created_at, updated_at)
                VALUES (?, 'Philippines', '', '', '', NOW(), NOW())
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Re-fetch to get the new address_id
            $stmt = $conn->prepare("SELECT address_id FROM alumni_address WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $stmt->close();
        
        // Get existing address to preserve unchanged fields
        $stmt = $conn->prepare("SELECT country, state_province, city, street FROM alumni_address WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_address = $result->fetch_assoc();
        $stmt->close();
        
        // Use new values if provided, otherwise keep existing
        $update_country = !empty($country) ? $country : ($existing_address['country'] ?? 'Philippines');
        $update_state = !empty($state_province) ? $state_province : ($existing_address['state_province'] ?? '');
        $update_city = !empty($city) ? $city : ($existing_address['city'] ?? '');
        $update_street = !empty($street) ? $street : ($existing_address['street'] ?? '');
        
        // Always update (even if all fields are blank, they keep existing values)
        $stmt = $conn->prepare("
            UPDATE alumni_address SET 
                country = ?,
                state_province = ?,
                city = ?,
                street = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param(
            "ssssi",
            $update_country,
            $update_state,
            $update_city,
            $update_street,
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update address information.");
        }
        $stmt->close();
        
         // Commit transaction
        $conn->commit();
        
        // ---- 7. Fetch existing profile data for comparison BEFORE logging ----
        $stmt = $conn->prepare("
            SELECT u.first_name, u.last_name, u.middle_name, u.suffix, 
                   u.civil_status, u.contact_number, aa.street
            FROM users u
            LEFT JOIN alumni_address aa ON u.user_id = aa.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_profile = $result->fetch_assoc() ?: [];
        $stmt->close();
        
        // ---- 8. Log activity with comparison to existing data ----------------
        $activity_desc = 'Updated personal information';
        $changes = [];
        
        // Compare with existing data
        if ($first_name !== ($existing_profile['first_name'] ?? '')) $changes[] = 'first name';
        if ($last_name !== ($existing_profile['last_name'] ?? '')) $changes[] = 'last name';
        if ($middle_name !== ($existing_profile['middle_name'] ?? '')) $changes[] = 'middle name';
        if ($suffix !== ($existing_profile['suffix'] ?? '')) $changes[] = 'suffix';
        if ($civil_status !== ($existing_profile['civil_status'] ?? '')) $changes[] = 'civil status';
        if ($contact_number !== ($existing_profile['contact_number'] ?? '')) $changes[] = 'contact number';
        if ($street !== ($existing_profile['street'] ?? '')) $changes[] = 'address';
        
        if (!empty($changes)) {
            $activity_desc .= ': ' . implode(', ', $changes);
        }
        
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