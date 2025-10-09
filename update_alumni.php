<?php
session_start();
include("../connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

function upload_file($field, $dir, $surname, $type, $allowed_types = ['application/pdf']) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($_FILES[$field]['type'], $allowed_types)) return null;
    if ($_FILES[$field]['size'] > 2097152) return null; // 2MB limit
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Determine file extension based on MIME type
    $ext = '.pdf';
    if (in_array($_FILES[$field]['type'], ['image/jpeg', 'image/png'])) {
        $ext = $_FILES[$field]['type'] === 'image/jpeg' ? '.jpg' : '.png';
    }
    $file_name = $surname . '_' . $type . $ext;
    $target = $dir . $file_name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) return str_replace('../', '', $target);
    return null;
}

$alumni_id = intval($_POST['alumni_id']);

// Fetch user_id and last_name
$stmt = $conn->prepare("SELECT user_id, last_name FROM alumni_profile WHERE alumni_id = ?");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni_row = $result->fetch_assoc();
if (!$alumni_row) {
    $_SESSION['error'] = "Alumni not found";
    header("Location: alumni_management.php?error=1");
    exit;
}
$user_id = $alumni_row['user_id'];
$last_name = $alumni_row['last_name'];

// Update alumni_profile
$first = trim($_POST['first_name']);
$middle = trim($_POST['middle_name'] ?? null);
$last = trim($_POST['last_name']);
$email = trim($_POST['email']);
$contact = trim($_POST['contact_number']);
$barangay = trim($_POST['barangay']);
$city = trim($_POST['city']);
$province = trim($_POST['province']);
$zip = trim($_POST['zip_code']);
$year = trim($_POST['year_graduated']);
$status = trim($_POST['employment_status']);
$photo = upload_file('profile_photo', '../Uploads/photos/', $last, 'photo', ['image/jpeg', 'image/png']);

$sql = "UPDATE alumni_profile SET first_name=?, middle_name=?, last_name=?, contact_number=?, barangay=?, city=?, province=?, zip_code=?, year_graduated=?, employment_status=?, photo_path=COALESCE(?, photo_path), last_updated=NOW() WHERE alumni_id=?";
$q = $conn->prepare($sql);
$q->bind_param("ssssssssssi", $first, $middle, $last, $contact, $barangay, $city, $province, $zip, $year, $status, $photo, $alumni_id);
if (!$q->execute()) {
    $_SESSION['error'] = $conn->error;
    header("Location: edit_alumni.php?id=$alumni_id&error=1");
    exit;
}

// Update users (email)
$email_sql = "UPDATE users SET email=? WHERE id=?";
$email_stmt = $conn->prepare($email_sql);
$email_stmt->bind_param("si", $email, $user_id);
$email_stmt->execute();

// Handle employment/academic details
$job_title = trim($_POST['job_title'] ?? null);
$school_name = trim($_POST['school_name'] ?? null);
if ($job_title || $school_name) {
    $job_title_id = null;
    if ($job_title) {
        $jt_stmt = $conn->prepare("INSERT IGNORE INTO job_titles (title) VALUES (?)");
        $jt_stmt->bind_param("s", $job_title);
        $jt_stmt->execute();
        $job_title_id = $conn->insert_id;
        if (!$job_title_id) {
            $jt_fetch = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
            $jt_fetch->bind_param("s", $job_title);
            $jt_fetch->execute();
            $jt_result = $jt_fetch->get_result();
            $job_title_id = $jt_result->fetch_assoc()['job_title_id'];
        }
    }

    $company = trim($_POST['company_name'] ?? null);
    $salary = trim($_POST['salary_range'] ?? null);
    $school_address = trim($_POST['school_address'] ?? null);
    $degree = trim($_POST['degree_pursued'] ?? null);
    $ei_sql = "INSERT INTO employment_info (alumni_id, job_title_id, company_name, salary_range, school_name, school_address, degree_pursued) 
               VALUES (?, ?, ?, ?, ?, ?, ?) 
               ON DUPLICATE KEY UPDATE 
               job_title_id=VALUES(job_title_id), company_name=VALUES(company_name), salary_range=VALUES(salary_range),
               school_name=VALUES(school_name), school_address=VALUES(school_address), degree_pursued=VALUES(degree_pursued)";
    $ei_stmt = $conn->prepare($ei_sql);
    $ei_stmt->bind_param("iisssss", $alumni_id, $job_title_id, $company, $salary, $school_name, $school_address, $degree);
    $ei_stmt->execute();
}

// Handle documents
$coe = upload_file('coe_file', '../Uploads/coe/', $last, 'COE', ['application/pdf']);
$business = upload_file('business_file', '../Uploads/business/', $last, 'B_CERT', ['application/pdf']);
$cor = upload_file('cor_file', '../Uploads/cor/', $last, 'COR', ['application/pdf']);

function save_doc($conn, $alumni_id, $user_id, $type, $path) {
    if (!$path) return;
    $sql = "INSERT INTO alumni_documents (alumni_id, user_id, document_type, file_path, document_status) 
            VALUES (?, ?, ?, ?, 'Pending') 
            ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), document_status='Pending', uploaded_at=NOW(), rejection_reason=NULL";
    $st = $conn->prepare($sql);
    $st->bind_param("iiss", $alumni_id, $user_id, $type, $path);
    if (!$st->execute()) {
        $_SESSION['error'] = $conn->error;
    }
}
if ($coe) save_doc($conn, $alumni_id, $user_id, "COE", $coe);
if ($business) save_doc($conn, $alumni_id, $user_id, "B_CERT", $business);
if ($cor) save_doc($conn, $alumni_id, $user_id, "COR", $cor);

// Log update
$log_sql = "INSERT INTO update_log (alumni_id, updated_by, update_type, update_details) VALUES (?, ?, 'Profile Update', 'Updated profile by admin')";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("ii", $alumni_id, $_SESSION['user_id']);
$log_stmt->execute();

$conn->close();
header("Location: edit_alumni.php?id=$alumni_id&success=1");
exit;
?>