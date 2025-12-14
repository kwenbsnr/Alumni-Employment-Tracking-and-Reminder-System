<?php
ob_start();
session_start();

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
        // ---- 6.1 Retrieve & sanitise --------------------------------------------
        $contact = !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
        $status = htmlspecialchars(trim($_POST['employment_status'] ?? ''));

        // Address fields
        $country = !empty($_POST['country']) ? trim($_POST['country']) : '';
        $state_province = !empty($_POST['state_province']) ? trim($_POST['state_province']) : '';
        $city = !empty($_POST['city']) ? trim($_POST['city']) : '';
        $street = !empty($_POST['street']) ? trim($_POST['street']) : '';

        // Employment fields
        $job_title = !empty($_POST['job_title']) ? trim($_POST['job_title']) : '';
        if ($job_title === 'Other') {
            if (empty(trim($_POST['other_job_title'] ?? ''))) {
                throw new Exception("Please specify your job title in the 'Other Job Title' field.");
            }
            $job_title = trim($_POST['other_job_title']);
        }

        $company = !empty($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $company_address = !empty($_POST['company_address']) ? trim($_POST['company_address']) : '';
        $salary = !empty($_POST['salary_range']) ? trim($_POST['salary_range']) : '';

        $business_type = !empty($_POST['business_type']) ? trim($_POST['business_type']) : '';
        if ($business_type === 'Others (Please specify)') {
            $business_type = 'Others: ' . (!empty($_POST['business_type_other']) ? trim($_POST['business_type_other']) : '');
        }

        // Education fields
        $school = !empty($_POST['school_name']) ? trim($_POST['school_name']) : '';
        $degree = !empty($_POST['degree_pursued']) ? trim($_POST['degree_pursued']) : '';
        $start_year = !empty($_POST['start_year']) ? trim($_POST['start_year']) : '';
        $end_year = !empty($_POST['end_year']) ? trim($_POST['end_year']) : '';

        // Validate year format for student statuses
        if (in_array($status, ['Student', 'Employed & Student'])) {
            $current_year = date('Y');
            
            if (!preg_match('/^\d{4}$/', $start_year) || $start_year < 2000) {
                throw new Exception("Invalid start year format.");
            }
            
            if (!preg_match('/^\d{4}$/', $end_year) || $end_year < 2000) {
                throw new Exception("Invalid end year format.");
            }
            
            if ($end_year <= $start_year) {
                throw new Exception("End Year (Expected Graduation) must be later than Start Year.");
            }
            
            if ($end_year > ($current_year + 10)) {
                throw new Exception("End Year seems too far in the future. Please verify your expected graduation year.");
            }
        }

        // Address validation - REQUIRED FIELDS
        if (empty($country) || empty($state_province) || empty($city)) {
            throw new Exception("Address information is required (Country, State/Province, and City).");
        }

        // ---- 6.2 BACKEND VALIDATION - MUST HAPPEN FIRST ------------------------------------
        if ($can_update) {
            $original_status = trim($_POST['employment_status'] ?? '');
            
            // Required personal fields
            if (empty($contact) || empty($original_status)) {
                throw new Exception("Contact number and employment status are required.");
            }

            // Employment-specific validation
            if (in_array($original_status, ['Employed', 'Employed & Student'])) {
                if (!$job_title) throw new Exception("Job title is required.");
                if (!$company) throw new Exception("Company name is required.");
                if (!$company_address) throw new Exception("Company address is required.");
            }
            
            if ($original_status === 'Self-Employed') {
                if (!$business_type) throw new Exception("Business type is required.");
                $company = '';
                $company_address = '';
            }

            // Education validation
            if (in_array($original_status, ['Student', 'Employed & Student'])) {
                if (!$school) throw new Exception("School name is required.");
                if (!$degree) throw new Exception("Degree pursued is required.");
                if (!$start_year) throw new Exception("Start year is required.");
                if (!$end_year) throw new Exception("End year is required.");
            }
        }

        // ---- 6.3 Photo handling ---------------------------------------
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

        // === DOCUMENT VALIDATION - Check BEFORE any database inserts ===
        $original_status = trim($_POST['employment_status'] ?? '');
        
        // Check required documents based on status
        $required_docs = [];
        $doc_errors = [];
        
        if (in_array($original_status, ['Employed', 'Employed & Student'])) {
            $required_docs['COE'] = 'coe_file';
        }
        if ($original_status === 'Self-Employed') {
            $required_docs['B_CERT'] = 'business_file';
        }
        if (in_array($original_status, ['Student', 'Employed & Student'])) {
            $required_docs['COR'] = 'cor_file';
        }
        
        // Validate each required document
        foreach ($required_docs as $code => $field) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                        ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                    throw new Exception("{$doc_name} is required for your employment status ({$original_status}).");
                } elseif (isset($_FILES[$field]['error'])) {
                    throw new Exception("{$doc_name} upload error (code: {$_FILES[$field]['error']}). Please try again.");
                } else {
                    throw new Exception("{$doc_name} is required for your employment status ({$original_status}).");
                }
            }
            
            // Additional validation for uploaded files
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                // Check file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES[$field]['tmp_name']);
                finfo_close($finfo);
                
                if ($mime_type !== 'application/pdf') {
                    $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                            ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                    throw new Exception("{$doc_name} must be a PDF file.");
                }
                
                // Check file size (2MB limit)
                if ($_FILES[$field]['size'] > (2 * 1024 * 1024)) {
                    $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                            ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                    throw new Exception("{$doc_name} exceeds 2MB size limit.");
                }
            }
        }

        // === PROCEED WITH DB OPERATIONS ===

        // ---- 6.4 Profile INSERT / UPDATE  ----------------------------------------
        if ($can_update) {
            $original_status = trim($_POST['employment_status'] ?? '');
            
            // Check if alumni_profile record exists
            $check_stmt = $conn->prepare("SELECT user_id FROM alumni_profile WHERE user_id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $profile_exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($profile_exists) {
                // Update existing profile - REMOVED contact_number from update
                $stmt = $conn->prepare("UPDATE alumni_profile SET 
                    employment_status=?, photo_path=?, last_profile_update=NOW(),
                    submission_status='Pending', submitted_at=NOW()
                    WHERE user_id=?");
                $stmt->bind_param("ssi", $original_status, $photo_path, $user_id);  
            } else {
                // Insert new profile - REMOVED contact_number from insert
                $stmt = $conn->prepare("INSERT INTO alumni_profile 
                    (user_id, employment_status, photo_path, last_profile_update, submission_status, submitted_at)
                    VALUES (?,?,?,NOW(),'Pending',NOW())");
                $stmt->bind_param("iss", $user_id, $original_status, $photo_path);  
            }

            if (!$stmt->execute()) {
                error_log("Alumni profile update failed: " . $stmt->error);
                throw new Exception("Failed to save profile information. Please try again.");
            }
            $stmt->close();
        }

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
            // Insert new address - NOW alumni_profile exists!
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

        // ---- 6.6 Employment ------------------------------------------------------
        if ($can_update) {
            // Delete existing employment info first
            $stmt = $conn->prepare("DELETE FROM employment_info WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $original_status = trim($_POST['employment_status'] ?? '');
            
            // Insert employment info ONLY for relevant statuses
            if (in_array($original_status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
                $job_title_id = null;
                
                // Handle job title for employed statuses
                if (in_array($original_status, ['Employed', 'Employed & Student']) && !empty($job_title)) {
                    $stmt = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
                    $stmt->bind_param("s", $job_title);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $job_title_id = $row['job_title_id'];
                    } else {
                        $ins = $conn->prepare("INSERT INTO job_titles (title) VALUES (?)");
                        $ins->bind_param("s", $job_title);
                        $ins->execute();
                        $job_title_id = $conn->insert_id;
                        $ins->close();
                    }
                    $stmt->close();
                }

                // For Self-Employed, ensure company fields are empty and job_title_id is null
                if ($original_status === 'Self-Employed') {
                    $job_title_id = null;
                    $company = '';
                    $company_address = '';
                    
                    // Salary range is optional for Self-Employed
                    if (empty($salary)) {
                        $salary = null;
                    }
                } else {
                    // For other employment statuses, salary range is required
                    if (empty($salary)) {
                        throw new Exception("Salary range is required.");
                    }
                }

                // Insert employment info
                $stmt = $conn->prepare("
                    INSERT INTO employment_info 
                    (user_id, job_title_id, company_name, company_address, business_type, salary_range)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $job_title_id,
                    $company,
                    $company_address,
                    $business_type,
                    $salary
                );

                if (!$stmt->execute()) {
                    error_log("Employment info insertion failed: " . $stmt->error);
                    throw new Exception("Failed to save employment information. Please try again.");
                }
                $stmt->close();
            }
        }

        // ---- 6.7 Education -------------------------------------------------------
        if ($can_update) {
            // Delete existing education info first
            $stmt = $conn->prepare("DELETE FROM education_info WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $original_status = trim($_POST['employment_status'] ?? '');
            
            // Insert education info ONLY for relevant statuses
            if (in_array($original_status, ['Student', 'Employed & Student'])) {
                $stmt = $conn->prepare("INSERT INTO education_info 
                    (user_id, school_name, degree_pursued, start_year, end_year)
                    VALUES (?,?,?,?,?)");
                $stmt->bind_param("issss", $user_id, $school, $degree, $start_year, $end_year);

                if (!$stmt->execute()) {
                    error_log("Education info insertion failed: " . $stmt->error);
                    throw new Exception("Failed to save education information. Please try again.");
                }
                $stmt->close();
            }
        }

        // ---- 6.8 Documents – ACTUAL UPLOAD (After validation passed) ----------------------------
        // Delete ALL existing documents first when status changes
        $stmt = $conn->prepare("DELETE FROM alumni_documents WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Process each required document
        foreach ($required_docs as $code => $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $dir = $code === 'COE' ? '../uploads/coe/' : 
                       ($code === 'B_CERT' ? '../uploads/business/' : '../uploads/cor/');
                
                if (!handle_document($field, $dir, $user_id, $code)) {
                    $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                               ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                    throw new Exception("{$doc_name} upload failed. Please try again.");
                }
            }
        }

        // Commit transaction
        $conn->commit();

        /*
        // === SCHEDULED REMINDERS CHECK ===
        if (file_exists(dirname(__DIR__) . '/api/scheduled_reminders.php')) {
            require_once dirname(__DIR__) . '/api/scheduled_reminders.php';
        }
        

        // === SCHEDULE CHECK ===
        if (!function_exists('isSubmissionPeriodOpen')) {
            require_once dirname(__DIR__) . '/api/utils/deadline.php';
        }

        // === NOTIFICATION INTEGRATION ===
        // Only send notifications if submissions are open
        if (isSubmissionPeriodOpen($conn)) {
            // Check if this is first-time submission
            $is_first_time = is_first_time_submission($conn, $user_id);
            
            // Check if this is a resubmission after rejection
            $is_resubmission = was_submission_rejected($conn, $user_id);
            
            // Send appropriate notifications to admins
            if ($is_first_time) {
                $result = send_new_submission_admin_notification($conn, $user_id);
                error_log("First-time submission notification sent for user: $user_id");
            } elseif ($is_resubmission) {
                $result = send_resubmission_admin_notification($conn, $user_id);
                error_log("Resubmission notification sent for user: $user_id");
            } else {
                $result = send_update_admin_notification($conn, $user_id);
                error_log("Regular update notification sent for user: $user_id");
            }
        } else {
            error_log("Notifications not sent: Submission period closed for user: $user_id");
        }
        */

        // TEMPORARILY DISABLE NOTIFICATIONS WHILE FIXING SQL ERROR IN notif_service.php
        // TEMPORARY LOGGING ONLY
        error_log("Notifications temporarily disabled while fixing SQL error in notif_service.php for user: $user_id");

        // Activity logs
        if (in_array($status, ['Employed', 'Employed & Student'])) {
            log_alumni_activity($conn, $user_id, 'document_uploaded', 'Uploaded Certificate of Employment (COE)');
        }
        if ($status === 'Self-Employed') {
            log_alumni_activity($conn, $user_id, 'document_uploaded', 'Uploaded Business Certificate');
        }
        if (in_array($status, ['Student', 'Employed & Student'])) {
            log_alumni_activity($conn, $user_id, 'document_uploaded', 'Uploaded Certificate of Registration (COR)');
        }

        if (!empty($_FILES['profile_photo']['name'])) {
            log_alumni_activity($conn, $user_id, 'profile_photo_updated', 'Updated profile picture');
        }

        log_alumni_activity($conn, $user_id, 'profile_updated', 'Updated personal information and address');

        // Clear any rejection session flags
        if (isset($_SESSION['profile_rejected'])) {
            unset($_SESSION['profile_rejected']);
        }

        // Set success flag for dashboard
        $_SESSION['profile_submission_success'] = true;

        // Set session flag to indicate successful submission
        $_SESSION['form_submitted'] = true;

        // Redirect after successful submission
        header("Location: alumni_profile.php?success=Profile updated successfully!");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        
        // For critical errors, always log regardless of environment
        error_log("Critical: Alumni profile update failed for user $user_id - " . ($e->getMessage() ?? 'Unknown error'));
        
        header("Location: alumni_profile.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}                                                                                                    

$conn->close();
ob_end_flush();