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

// Check if employment submission is open
require_once dirname(__DIR__) . '/api/utils/deadline.php';
$submission_open = isEmploymentSubmissionOpen($conn);

// ---- Document Upload Helper -----------------------------------------------
function upload_employment_document($field, $user_id, $type) {
    global $conn;
    
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['size'] > $max_size) {
        $doc_name = $field === 'coe_file' ? 'Certificate of Employment' : 
                ($field === 'business_file' ? 'Business Certificate' : 
                ($field === 'cor_file' ? 'Certificate of Registration' : 'File'));
        throw new Exception("{$doc_name} exceeds the 2MB size limit.");
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mime_type !== 'application/pdf') {
        throw new Exception("Invalid file type. Only PDF files are allowed for documents.");
    }
    
    // Get user's lastname from database
    $stmt = $conn->prepare("SELECT last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User not found.");
    }
    
    $user_data = $result->fetch_assoc();
    $lastname = $user_data['last_name'];
    $stmt->close();
    
    // Clean the lastname: remove spaces, convert to lowercase, keep only letters/numbers
    $lastname = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $lastname));
    
    // If lastname is empty after cleaning, use a default
    if (empty($lastname)) {
        $lastname = 'user';
    }
    
    // Map document types to abbreviations
    $doc_type_map = [
        'coe' => 'coe',
        'business' => 'bcert', 
        'cor' => 'cor'
    ];
    
    $doc_type = $doc_type_map[$type] ?? $type;
    
    // Create uploads directory
    $upload_dir = '../uploads/documents/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception("Could not create upload directory.");
        }
    }

    // Generate unique filename with 5 random chars
    $filename = generate_unique_filename($upload_dir, $lastname, $doc_type);
    $target_path = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception("File upload failed. Please try again.");
    }
    
    // Return relative path
    return 'uploads/documents/' . $filename;
}

// Helper function to generate unique filename
function generate_unique_filename($upload_dir, $lastname, $doc_type) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max_attempts = 10;
    
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $random_chars = '';
        for ($i = 0; $i < 5; $i++) {
            $random_chars .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        $filename = $lastname . '_' . $doc_type . '_' . $random_chars . '.pdf';
        $target_path = $upload_dir . $filename;
        
        if (!file_exists($target_path)) {
            return $filename;
        }
        
        if ($attempt === $max_attempts - 1) {
            $filename = $lastname . '_' . $doc_type . '_' . $random_chars . '_' . time() . '.pdf';
            return $filename;
        }
    }
    
    $random_chars = substr(str_shuffle($characters), 0, 5);
    return $lastname . '_' . $doc_type . '_' . $random_chars . '_' . time() . '.pdf';
}

// ---- POST handling --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_development) {
        error_log("=== EMPLOYMENT UPDATE START ===");
        error_log("POST Data: " . print_r($_POST, true));
        error_log("FILES Data: " . print_r($_FILES, true));
        error_log("User ID: " . $user_id);
        error_log("Employment Submission Open: " . ($submission_open ? 'Yes' : 'No'));
    }
    
    // Double-check employment submission status before processing
    // This prevents bypassing frontend validation
    // Server-side validation - REJECT if employment submission is closed
    if (!$submission_open) {
        ob_end_clean();
        header("Location: alumni_employment.php?error=" . urlencode("Employment submission is currently locked by the administrator."));
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        // ---- 1. Retrieve & sanitize data ------------------------------------
        $employment_status = !empty($_POST['employment_status']) ? trim($_POST['employment_status']) : '';
        $job_title_input = !empty($_POST['job_title']) ? trim($_POST['job_title']) : '';
        $other_job_title = !empty($_POST['other_job_title']) ? trim($_POST['other_job_title']) : '';
        $company_name = !empty($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $company_address = !empty($_POST['company_address']) ? trim($_POST['company_address']) : '';
        $business_type = !empty($_POST['business_type']) ? trim($_POST['business_type']) : '';
        $business_type_other = !empty($_POST['business_type_other']) ? trim($_POST['business_type_other']) : '';
        $salary_range = !empty($_POST['salary_range']) ? trim($_POST['salary_range']) : '';
        $school_name = !empty($_POST['school_name']) ? trim($_POST['school_name']) : '';
        $degree_pursued = !empty($_POST['degree_pursued']) ? trim($_POST['degree_pursued']) : '';
        $start_year = !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null;
        $end_year = !empty($_POST['end_year']) ? (int)$_POST['end_year'] : null;

        // ---- 2. BACKEND VALIDATION ------------------------------------
        if (empty($employment_status)) {
            throw new Exception("Employment status is required.");
        }

        // Validate based on employment status
        switch ($employment_status) {
            case 'Employed':
                if (empty($job_title_input)) {
                    throw new Exception("Job title is required for employed status.");
                }
                if ($job_title_input === 'Other' && empty($other_job_title)) {
                    throw new Exception("Please specify job title if 'Other' is selected.");
                }
                if (empty($company_name)) {
                    throw new Exception("Company name is required for employed status.");
                }
                if (empty($company_address)) {
                    throw new Exception("Company address is required for employed status.");
                }
                if (empty($salary_range)) {
                    throw new Exception("Salary range is required for employed status.");
                }
                break;
                
            case 'Employed & Student':
                if (empty($job_title_input)) {
                    throw new Exception("Job title is required for 'Employed & Student' status.");
                }
                if ($job_title_input === 'Other' && empty($other_job_title)) {
                    throw new Exception("Please specify job title if 'Other' is selected.");
                }
                if (empty($company_name)) {
                    throw new Exception("Company name is required for 'Employed & Student' status.");
                }
                if (empty($company_address)) {
                    throw new Exception("Company address is required for 'Employed & Student' status.");
                }
                if (empty($salary_range)) {
                    throw new Exception("Salary range is required for 'Employed & Student' status.");
                }
                if (empty($school_name)) {
                    throw new Exception("School name is required for 'Employed & Student' status.");
                }
                if (empty($degree_pursued)) {
                    throw new Exception("Degree pursued is required for 'Employed & Student' status.");
                }
                if (empty($start_year) || empty($end_year)) {
                    throw new Exception("Start year and end year are required for 'Employed & Student' status.");
                }
                if ($end_year <= $start_year) {
                    throw new Exception("End year must be later than start year.");
                }
                break;
                
            case 'Self-Employed':
                if (empty($business_type)) {
                    throw new Exception("Business type is required for self-employed status.");
                }
                if ($business_type === 'Others (Please specify)' && empty($business_type_other)) {
                    throw new Exception("Please specify business type if 'Others' is selected.");
                }
                if (empty($salary_range)) {
                    throw new Exception("Salary range is required for self-employed status.");
                }
                break;
                
            case 'Student':
                if (empty($school_name)) {
                    throw new Exception("School name is required for student status.");
                }
                if (empty($degree_pursued)) {
                    throw new Exception("Degree pursued is required for student status.");
                }
                if (empty($start_year) || empty($end_year)) {
                    throw new Exception("Start year and end year are required for student status.");
                }
                if ($end_year <= $start_year) {
                    throw new Exception("End year must be later than start year.");
                }
                break;
                
            case 'Unemployed':
                break;
        }

        // ---- 3. Document Validation ----------------------------------------
        $required_docs = [];
        
        if (in_array($employment_status, ['Employed', 'Employed & Student'])) {
            $required_docs['coe_file'] = 'Certificate of Employment (COE)';
        }
        
        if ($employment_status === 'Self-Employed') {
            $required_docs['business_file'] = 'Business Certificate';
        }
        
        if (in_array($employment_status, ['Student', 'Employed & Student'])) {
            $required_docs['cor_file'] = 'Certificate of Registration (COR)';
        }
        
        foreach ($required_docs as $field => $doc_name) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("{$doc_name} is required for your employment status ({$employment_status}).");
            }
        }

        // ---- 4. Handle Job Titles ------------------------------------------
        $job_title_id = null;
        if (in_array($employment_status, ['Employed', 'Employed & Student']) && !empty($job_title_input)) {
            $final_job_title = ($job_title_input === 'Other') ? $other_job_title : $job_title_input;
            
            if (empty($final_job_title)) {
                throw new Exception("Job title is required for employment status.");
            }
            
            // Check if job title exists
            $stmt = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
            $stmt->bind_param("s", $final_job_title);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $job_title_id = $row['job_title_id'];
            } else {
                // Insert new job title
                $stmt = $conn->prepare("INSERT INTO job_titles (title) VALUES (?)");
                $stmt->bind_param("s", $final_job_title);
                $stmt->execute();
                $job_title_id = $stmt->insert_id;
            }
            $stmt->close();
        }

        // ---- 5. Update alumni_profile table with employment_status -----
        $stmt = $conn->prepare("SELECT user_id FROM alumni_profile WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile_exists = $result->num_rows > 0;
        $stmt->close();
        
        if ($profile_exists) {
            $stmt = $conn->prepare("UPDATE alumni_profile SET 
                employment_status = ?,
                last_profile_update = NOW(),
                submitted_at = NOW()
                WHERE user_id = ?");
            $stmt->bind_param("si", $employment_status, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO alumni_profile 
                (user_id, employment_status, last_profile_update, submitted_at)
                VALUES (?, ?, NOW(), NOW())");
            $stmt->bind_param("is", $user_id, $employment_status);
        }
        
        if (!$stmt->execute()) {
            error_log("Alumni profile update failed: " . $stmt->error);
            throw new Exception("Failed to update employment status in profile.");
        }
        $stmt->close();

        // ---- 6. Handle employment_info --------------------------------------
        $stmt = $conn->prepare("DELETE FROM employment_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        if (in_array($employment_status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
            $final_business_type = null;
            if ($employment_status === 'Self-Employed') {
                $final_business_type = ($business_type === 'Others (Please specify)') ? $business_type_other : $business_type;
                $company_name = '';
                $company_address = '';
            }
            
            $stmt = $conn->prepare("INSERT INTO employment_info 
                (user_id, job_title_id, company_name, salary_range, business_type, company_address) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", 
                $user_id, 
                $job_title_id, 
                $company_name, 
                $salary_range, 
                $final_business_type, 
                $company_address
            );
            
            if (!$stmt->execute()) {
                error_log("Employment info insert failed: " . $stmt->error);
                throw new Exception("Failed to save employment information.");
            }
            $stmt->close();
        }

        // ---- 7. Handle education_info --------------------------------------
        $stmt = $conn->prepare("DELETE FROM education_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        if (in_array($employment_status, ['Student', 'Employed & Student'])) {
            $stmt = $conn->prepare("INSERT INTO education_info 
                (user_id, school_name, degree_pursued, start_year, end_year) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", 
                $user_id, 
                $school_name, 
                $degree_pursued, 
                $start_year, 
                $end_year
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to save education information.");
            }
            $stmt->close();
        }

        // ---- 8. Handle Documents -------------------------------------------
        $stmt = $conn->prepare("DELETE FROM alumni_documents WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        if (in_array($employment_status, ['Employed', 'Employed & Student'])) {
            $coe_path = upload_employment_document('coe_file', $user_id, 'coe');
            if (!$coe_path) {
                throw new Exception("Failed to upload Certificate of Employment (COE).");
            }
            
            $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, 'COE', ?)");
            $stmt->bind_param("is", $user_id, $coe_path);
            $stmt->execute();
            $stmt->close();
        }
        
        if ($employment_status === 'Self-Employed') {
            $business_path = upload_employment_document('business_file', $user_id, 'business');
            if (!$business_path) {
                throw new Exception("Failed to upload Business Certificate.");
            }
            
            $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, 'B_CERT', ?)");
            $stmt->bind_param("is", $user_id, $business_path);
            $stmt->execute();
            $stmt->close();
        }
        
        if (in_array($employment_status, ['Student', 'Employed & Student'])) {
            $cor_path = upload_employment_document('cor_file', $user_id, 'cor');
            if (!$cor_path) {
                throw new Exception("Failed to upload Certificate of Registration (COR).");
            }
            
            $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, 'COR', ?)");
            $stmt->bind_param("is", $user_id, $cor_path);
            $stmt->execute();
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Log activity
        log_alumni_activity($conn, $user_id, 'employment_updated', 'Updated employment information');

        // Clear output buffer before redirect
        ob_end_clean();
        
        // Redirect with success message
        header("Location: alumni_employment.php?success=Employment information submitted successfully!");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Critical: Employment update failed for user $user_id - " . ($e->getMessage() ?? 'Unknown error'));
        
        // Clear output buffer before redirect
        ob_end_clean();
        
        header("Location: alumni_employment.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// If not POST request, redirect back
ob_end_clean();
header("Location: alumni_employment.php");
exit();