<?php
session_start();
include("../connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Check yearly update restriction and re-upload eligibility
$stmt = $conn->prepare("SELECT last_profile_update, last_name, alumni_id, photo_path FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$can_update = !$profile || ($profile && ($profile['last_profile_update'] === null || strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$alumni_id = $profile ? $profile['alumni_id'] : null;
$last_name = $profile['last_name'] ?? '';

// Check for rejected documents needing re-upload
$can_reupload = false;
$rejected_docs = [];
if ($alumni_id) {
    $stmt = $conn->prepare("SELECT document_type FROM alumni_documents WHERE alumni_id = ? AND document_status = 'Rejected' AND needs_reupload = 1");
    $stmt->bind_param("i", $alumni_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($doc = $result->fetch_assoc()) {
        $rejected_docs[] = $doc['document_type'];
        $can_reupload = true;
    }
}
if (!$can_update && !$can_reupload) {
    $_SESSION['error'] = "You can only update your profile once per year, unless re-uploading rejected documents.";
    header("Location: alumni_profile.php?error=1");
    exit;
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch or insert alumni_id
    $stmt = $conn->prepare("SELECT alumni_id, photo_path FROM alumni_profile WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumni_row = $result->fetch_assoc();
    $alumni_id = $alumni_row ? $alumni_row['alumni_id'] : null;
    $old_photo_path = $alumni_row ? $alumni_row['photo_path'] : null;

    // Update/Insert alumni_profile (only if full update is allowed)
    if ($can_update) {
        $first = trim($_POST['first_name']);
        $middle = trim($_POST['middle_name'] ?? null);
        $last = trim($_POST['last_name']);
        $contact = trim($_POST['contact_number']);
        $barangay = trim($_POST['barangay']);
        $city = trim($_POST['city']);
        $province = trim($_POST['province']);
        $zip = trim($_POST['zip_code']);
        $year = trim($_POST['year_graduated']);
        $status = trim($_POST['employment_status']);
        $photo = upload_file('profile_photo', '../Uploads/photos/', $last, 'photo', ['image/jpeg', 'image/png']);

        if ($alumni_id) {
            $sql = "UPDATE alumni_profile SET first_name=COALESCE(?, first_name), middle_name=?, last_name=?, contact_number=?, barangay=?, city=?, province=?, zip_code=?, year_graduated=?, employment_status=?, photo_path=COALESCE(?, photo_path), last_updated=NOW(), last_profile_update=NOW() WHERE user_id=?";
            $q = $conn->prepare($sql);
            $q->bind_param("sssssssssssi", $first, $middle, $last, $contact, $barangay, $city, $province, $zip, $year, $status, $photo, $user_id);
        } else {
            $sql = "INSERT INTO alumni_profile (user_id, first_name, middle_name, last_name, contact_number, barangay, city, province, zip_code, year_graduated, employment_status, photo_path, last_profile_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $q = $conn->prepare($sql);
            $q->bind_param("isssssssssss", $user_id, $first, $middle, $last, $contact, $barangay, $city, $province, $zip, $year, $status, $photo);
        }
        if (!$q->execute()) {
            $_SESSION['error'] = $conn->error;
            header("Location: alumni_profile.php?error=1");
            exit;
        }
        $alumni_id = $alumni_id ?: $conn->insert_id;

        // Update session with new photo_path
        if ($photo) {
            $_SESSION['photo_path'] = $photo;
            // Delete old photo if it exists
            if ($old_photo_path && file_exists('../' . $old_photo_path)) {
                unlink('../' . $old_photo_path);
            }
        }

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
    }

    // Handle documents (always allow re-upload of rejected documents)
    $coe = upload_file('coe_file', '../Uploads/coe/', $last_name, 'COE', ['application/pdf']);
    $business = upload_file('business_file', '../Uploads/business/', $last_name, 'B_CERT', ['application/pdf']);
    $cor = upload_file('cor_file', '../Uploads/cor/', $last_name, 'COR', ['application/pdf']);

    function save_doc($conn, $alumni_id, $user_id, $type, $path) {
        if (!$path) return;
        $sql = "INSERT INTO alumni_documents (alumni_id, user_id, document_type, file_path, document_status, needs_reupload) 
                VALUES (?, ?, ?, ?, 'Pending', 0) 
                ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), document_status='Pending', uploaded_at=NOW(), rejection_reason=NULL, needs_reupload=0";
        $st = $conn->prepare($sql);
        $st->bind_param("iiss", $alumni_id, $user_id, $type, $path);
        if (!$st->execute()) {
            $_SESSION['error'] = $conn->error;
        }
    }
    if ($coe && ($can_update || in_array('COE', $rejected_docs))) save_doc($conn, $alumni_id, $user_id, "COE", $coe);
    if ($business && ($can_update || in_array('B_CERT', $rejected_docs))) save_doc($conn, $alumni_id, $user_id, "B_CERT", $business);
    if ($cor && ($can_update || in_array('COR', $rejected_docs))) save_doc($conn, $alumni_id, $user_id, "COR", $cor);

    // Log update
    $log_sql = "INSERT INTO update_log (alumni_id, updated_by, update_type, update_details) VALUES (?, ?, 'Profile Update', 'Updated profile by alumni')";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("ii", $alumni_id, $user_id);
    $log_stmt->execute();

    // Schedule notification for next year (if full update)
    if ($can_update) {
        $notify_sql = "INSERT INTO notifications (user_id, message, created_at) 
                       VALUES (?, 'Please update your profile after one year.', DATE_ADD(NOW(), INTERVAL 1 YEAR))";
        $notify_stmt = $conn->prepare($notify_sql);
        $notify_stmt->bind_param("i", $user_id);
        $notify_stmt->execute();
    }

    $conn->close();
    header("Location: alumni_profile.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - JHCSC BSIT Alumni System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Update Your Profile</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-xl font-semibold mb-4">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Barangay</label>
                    <input type="text" name="barangay" value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">City/Municipality</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Province</label>
                    <input type="text" name="province" value="<?php echo htmlspecialchars($profile['province'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Zip Code</label>
                    <input type="text" name="zip_code" value="<?php echo htmlspecialchars($profile['zip_code'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Year Graduated</label>
                    <input type="text" name="year_graduated" value="<?php echo htmlspecialchars($profile['year_graduated'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Employment Status</label>
                    <select name="employment_status" class="w-full border rounded-lg p-2">
                        <option value="Employed" <?php echo ($profile['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                        <option value="Self-Employed" <?php echo ($profile['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                        <option value="Unemployed" <?php echo ($profile['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                        <option value="Student" <?php echo ($profile['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                        <option value="Employed & Student" <?php echo ($profile['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png" class="w-full border rounded-lg p-2">
                <?php if ($profile['photo_path']): ?>
                    <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Current Photo" class="w-32 h-32 object-cover rounded mt-2">
                <?php endif; ?>
            </div>

            <?php if ($can_update): ?>
                <h3 class="text-xl font-semibold mb-4">Employment/Academic Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Job Title</label>
                        <input type="text" name="job_title" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Company Name</label>
                        <input type="text" name="company_name" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Salary Range</label>
                        <input type="text" name="salary_range" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">School Name</label>
                        <input type="text" name="school_name" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">School Address</label>
                        <input type="text" name="school_address" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Degree Pursued</label>
                        <input type="text" name="degree_pursued" class="w-full border rounded-lg p-2">
                    </div>
                </div>
            <?php endif; ?>

            <h3 class="text-xl font-semibold mb-4">Documents</h3>
            <?php if ($can_update || in_array('COE', $rejected_docs)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Certificate of Employment (COE)</label>
                    <input type="file" name="coe_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                </div>
            <?php endif; ?>
            <?php if ($can_update || in_array('B_CERT', $rejected_docs)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Business Certificate</label>
                    <input type="file" name="business_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                </div>
            <?php endif; ?>
            <?php if ($can_update || in_array('COR', $rejected_docs)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Certificate of Registration (COR)</label>
                    <input type="file" name="cor_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                </div>
            <?php endif; ?>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Submit</button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>