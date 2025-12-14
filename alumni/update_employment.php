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

// Check submission period
if (!function_exists('isSubmissionPeriodOpen')) {
    require_once dirname(__DIR__) . '/api/utils/deadline.php';
}
$submission_open = isSubmissionPeriodOpen($conn);

if (!$submission_open) {
    header("Location: alumni_employment.php?error=" . urlencode("Employment updates are currently closed by administrator."));
    exit();
}

// Get current employment status
$stmt = $conn->prepare("SELECT employment_status FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_status = $result->fetch_assoc()['employment_status'] ?? '';
$stmt->close();

// ---- POST handling --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_development) {
        error_log("=== EMPLOYMENT UPDATE START ===");
        error_log("POST Data: " . print_r($_POST, true));
        error_log("FILES Data: " . print_r($_FILES, true));
        error_log("User ID: " . $user_id);
    }
    
    $conn->begin_transaction();
    
    try {
        // ---- 1. Retrieve & sanitise --------------------------------------------
        $status = htmlspecialchars(trim($_POST['employment_status'] ?? ''));

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

        // ---- 2. BACKEND VALIDATION ------------------------------------
        if (empty($status)) {
            throw new Exception("Employment status is required.");
        }

        // Employment-specific validation
        if (in_array($status, ['Employed', 'Employed & Student'])) {
            if (!$job_title) throw new Exception("Job title is required.");
            if (!$company) throw new Exception("Company name is required.");
            if (!$company_address) throw new Exception("Company address is required.");
        }
        
        if ($status === 'Self-Employed') {
            if (!$business_type) throw new Exception("Business type is required.");
            $company = '';
            $company_address = '';
        }

        // Education validation
        if (in_array($status, ['Student', 'Employed & Student'])) {
            if (!$school) throw new Exception("School name is required.");
            if (!$degree) throw new Exception("Degree pursued is required.");
            if (!$start_year) throw new Exception("Start year is required.");
            if (!$end_year) throw new Exception("End year is required.");
        }

        // ---- 3. Update alumni_profile with employment status only -----------
        $stmt = $conn->prepare("UPDATE alumni_profile SET employment_status = ?, last_profile_update = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $status, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update employment status.");
        }
        $stmt->close();

        // ---- 4. Employment info ---------------------------------------------
        // Delete existing employment info first
        $stmt = $conn->prepare("DELETE FROM employment_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert employment info ONLY for relevant statuses
        if (in_array($status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
            $job_title_id = null;
            
            // Handle job title for employed statuses
            if (in_array($status, ['Employed', 'Employed & Student']) && !empty($job_title)) {
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
            if ($status === 'Self-Employed') {
                $job_title_id = null;
                $company = '';
                $company_address = '';
                
                // Salary range is optional for Self-Employed
                if (empty($salary)) {
                    $salary = null;
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

        // ---- 5. Education -------------------------------------------------------
        // Delete existing education info first
        $stmt = $conn->prepare("DELETE FROM education_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert education info ONLY for relevant statuses
        if (in_array($status, ['Student', 'Employed & Student'])) {
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

        // ---- 6. Document handler -----------------------------------------------
        function handle_employment_document($field, $dir, $user_id, $code) {
            global $conn;
            
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                return null;
            }

            // File validation
            $file = $_FILES[$field];
            $max_size = 2 * 1024 * 1024; // 2MB

            if ($file['size'] > $max_size) {
                throw new Exception("File exceeds the 2MB size limit.");
            }
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mime_type !== 'application/pdf') {
                throw new Exception("Invalid file type. Only PDF files are allowed.");
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

            $doc_type_map = [
                'coe' => 'COE',
                'business' => 'B_CERT', 
                'cor' => 'COR'
            ];
            
            $doc_type = $doc_type_map[$code] ?? $code;
            $name = $surname . '_' . $doc_type . '.pdf';
            $target = rtrim($dir, '/') . '/' . $name;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception("File upload failed. Please try again.");
            }
            
            $return_path = str_replace('../', '', $target);
            return $return_path;
        }

        // Delete existing documents first
        $stmt = $conn->prepare("DELETE FROM alumni_documents WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Process each required document
        $required_docs = [];
        if (in_array($status, ['Employed', 'Employed & Student'])) {
            $required_docs['coe'] = 'coe_file';
        }
        if ($status === 'Self-Employed') {
            $required_docs['business'] = 'business_file';
        }
        if (in_array($status, ['Student', 'Employed & Student'])) {
            $required_docs['cor'] = 'cor_file';
        }
        
        foreach ($required_docs as $code => $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $dir = $code === 'coe' ? '../uploads/coe/' : 
                       ($code === 'business' ? '../uploads/business/' : '../uploads/cor/');
                
                $new_path = handle_employment_document($field, $dir, $user_id, $code);
                
                if ($new_path) {
                    // Insert new document
                    $doc_code = strtoupper($code === 'coe' ? 'COE' : ($code === 'business' ? 'B_CERT' : 'COR'));
                    $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $user_id, $doc_code, $new_path);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $doc_name = $code === 'coe' ? 'Certificate of Employment' : 
                           ($code === 'business' ? 'Business Certificate' : 'Certificate of Registration');
                throw new Exception("{$doc_name} is required for your employment status ({$status}).");
            }
        }

        // Commit transaction
        $conn->commit();
        
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

        log_alumni_activity($conn, $user_id, 'employment_updated', 'Updated employment information');

        // Set session flag to indicate successful submission
        $_SESSION['employment_submitted'] = true;

        // Redirect after successful submission
        header("Location: alumni_employment.php?success=Employment information updated successfully!");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Critical: Employment update failed for user $user_id - " . ($e->getMessage() ?? 'Unknown error'));
        header("Location: alumni_employment.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

$conn->close();
ob_end_flush();
?>