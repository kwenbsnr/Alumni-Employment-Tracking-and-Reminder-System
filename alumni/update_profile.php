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
require_once '../api/notification/notif_service.php';

function log_alumni_activity($conn, $user_id, $action_type, $description = '') {
    $stmt = $conn->prepare("INSERT INTO alumni_activity_log (user_id, action_type, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action_type, $description);
    $stmt->execute();
    $stmt->close();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: ../login/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ---- 1. Profile & Permissions ------------------------------------------------
$user_stmt = $conn->prepare("
    SELECT 
        CONCAT(
            first_name, 
            IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), ''),
            ' ',
            last_name,
            IF(suffix IS NOT NULL AND suffix != '', CONCAT(' ', suffix), '')
        ) as name,
        contact_number 
    FROM users 
    WHERE user_id = ?
");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$profile = $user_stmt->get_result()->fetch_assoc() ?: [];
$user_stmt->close();

// ---- 2. Get complete alumni profile data -------------------------------------
$alumni_stmt = $conn->prepare("
    SELECT ap.submission_status, ap.last_profile_update, ap.employment_status, ap.photo_path
    FROM alumni_profile ap 
    WHERE ap.user_id = ?
");
$alumni_stmt->bind_param("i", $user_id);
$alumni_stmt->execute();
$alumni_profile = $alumni_stmt->get_result()->fetch_assoc() ?: [];
$alumni_stmt->close();

// ---- 3. Profile Permissions --------------------------------------------------------
$is_profile_rejected = !empty($alumni_profile) && ($alumni_profile['submission_status'] ?? '') === 'Rejected';
$is_profile_pending = !empty($alumni_profile) && ($alumni_profile['submission_status'] ?? '') === 'Pending';

$can_update_semiannual = empty($alumni_profile) || 
                        ($alumni_profile['last_profile_update'] === null || 
                        strtotime($alumni_profile['last_profile_update'] . ' +6 months') <= time());

$can_update = $can_update_semiannual || $is_profile_rejected || $is_profile_pending;

// PERMISSION CHECK - PREVENT UNAUTHORIZED UPDATES
if (!$can_update) {
    header("Location: alumni_profile.php?error=" . urlencode(
        "You can only update every 6 months unless your submission was rejected."
    ));
    exit;
}

if ($is_profile_rejected && !isset($_SESSION['profile_rejected'])) {
    $_SESSION['profile_rejected'] = true;
}

// Check if address exists
$address_stmt = $conn->prepare("SELECT address_id FROM alumni_address WHERE user_id = ?");
$address_stmt->bind_param("i", $user_id);
$address_stmt->execute();
$address_result = $address_stmt->get_result();
$existing_address = $address_result->fetch_assoc();
$existing_alumni_address_id = $existing_address ? $existing_address['address_id'] : null;
$address_stmt->close();

$current_employment_status = $alumni_profile['employment_status'] ?? '';
$photo_path = $alumni_profile['photo_path'] ?? null;

// ---- 4. Helper: file upload --------------------------------------------------
function upload_file($field, $dir, $user_id, $type, $allowed = ['application/pdf']) {
    global $conn;
    
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        if ($_FILES[$field]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$field]['error'] === UPLOAD_ERR_FORM_SIZE) {
            $doc_name = $field === 'coe_file' ? 'Certificate of Employment' : 
                    ($field === 'business_file' ? 'Business Certificate' : 
                    ($field === 'cor_file' ? 'Certificate of Registration' : 'File'));
            throw new Exception("{$doc_name} is too large. Maximum allowed size is 2MB.");
        }
        return null;
    }

    $file = $_FILES[$field];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['size'] > $max_size) {
        $doc_name = $field === 'coe_file' ? 'Certificate of Employment' : 
                ($field === 'business_file' ? 'Business Certificate' : 
                ($field === 'cor_file' ? 'Certificate of Registration' : 'File'));
        throw new Exception("{$doc_name} exceeds the 2MB size limit. Please choose a smaller file.");
    }
    
    // Verify MIME type using finfo for better security
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed));
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

    // Map type codes to document type names for filename
    $doc_type_map = [
        'profile' => 'profile',
        'coe' => 'COE',
        'business' => 'B_CERT', 
        'cor' => 'COR'
    ];
    
    $doc_type = $doc_type_map[$type] ?? $type;
    
    // Get file extension from MIME type
    $extMap = [
        'image/jpeg' => 'jpg', 
        'image/jpg' => 'jpg',
        'image/png' => 'png', 
        'application/pdf' => 'pdf'
    ];
    
    $ext = $extMap[$mime_type] ?? 'file';

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            throw new Exception("Could not create upload directory.");
        }
    }

    // Generate filename: surname_docType.extension
    $name = $surname . '_' . $doc_type . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("File upload failed. Please try again.");
    }
    
    $return_path = str_replace('../', '', $target);
    return $return_path;
}

// ---- 5. Document handler (DRY) -----------------------------------------------
function handle_document($field, $dir, $user_id, $code) {
    global $conn;
    
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $new_path = upload_file($field, $dir, $user_id, strtolower($code), ['application/pdf']);
    
    if ($new_path) {
        // Delete old document if exists
        $stmt = $conn->prepare("DELETE FROM alumni_documents WHERE user_id = ? AND document_type = ?");
        $stmt->bind_param("is", $user_id, $code);
        $stmt->execute();
        $stmt->close();
        
        // Insert new document
        $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $code, $new_path);
        $stmt->execute();
        $stmt->close();
        
        return true;
    }
    return false;
}

// ---- 6. POST handling --------------------------------------------------------
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

        // ---- 3. Photo handling ---------------------------------------
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $max_size = 20 * 1024 * 1024;

            if ($file['size'] > $max_size) {
                throw new Exception("Photo is too large. Maximum allowed is 20MB.");
            }

            if (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])) {
                throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.");
            }

            $new_photo = upload_file('profile_photo', '../uploads/photos/', $user_id, 'profile', 
            ['image/jpeg','image/jpg','image/png','image/gif','image/webp']);

            if (!$new_photo) {
                throw new Exception("Photo upload failed. Please try again.");
            }

            // Delete old photo if exists and different
            if ($photo_path && $photo_path !== $new_photo && file_exists('../' . $photo_path)) {
                @unlink('../' . $photo_path);
            }

            $photo_path = $new_photo;
            
        } else {
            // No new photo uploaded
            if (empty($photo_path)) {
                throw new Exception("Profile photo is required.");
            }
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

        // === UPDATE alumni_profile TABLE WITH PHOTO PATH ONLY ==========
        // Check if alumni_profile record exists
        $check_stmt = $conn->prepare("SELECT user_id FROM alumni_profile WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $profile_exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
        
        if ($profile_exists) {
            // Update existing profile - only photo_path and timestamp
            $stmt = $conn->prepare("UPDATE alumni_profile SET 
                photo_path=?, last_profile_update=NOW()
                WHERE user_id=?");
            $stmt->bind_param("si", $photo_path, $user_id);
        } else {
            // Insert new profile - only user_id and photo_path
            $stmt = $conn->prepare("INSERT INTO alumni_profile 
                (user_id, photo_path, last_profile_update)
                VALUES (?,?,NOW())");
            $stmt->bind_param("is", $user_id, $photo_path);
        }

        if (!$stmt->execute()) {
            error_log("Alumni profile update failed: " . $stmt->error);
            throw new Exception("Failed to save profile information. Please try again.");
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

        // Activity logs
        if (!empty($_FILES['profile_photo']['name'])) {
            log_alumni_activity($conn, $user_id, 'profile_photo_updated', 'Updated profile picture');
        }

        log_alumni_activity($conn, $user_id, 'profile_updated', 'Updated personal information and address');

        // Clear any rejection session flags
        if (isset($_SESSION['profile_rejected'])) {
            unset($_SESSION['profile_rejected']);
        }

        // Set session flag to indicate successful submission
        $_SESSION['form_submitted'] = true;

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