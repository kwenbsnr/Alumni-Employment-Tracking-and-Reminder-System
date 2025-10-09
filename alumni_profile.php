<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$user_id = $_SESSION['user_id'];

// Fetch profile data
$stmt = $conn->prepare("
    SELECT ap.*, u.email, 
           GROUP_CONCAT(CASE WHEN ad.document_status = 'Rejected' THEN ad.rejection_reason END) AS rejection_reasons
    FROM alumni_profile ap
    LEFT JOIN users u ON ap.user_id = u.id
    LEFT JOIN alumni_documents ad ON ap.alumni_id = ad.alumni_id
    WHERE ap.user_id = ?
    GROUP BY ap.alumni_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

// Check if re-upload is allowed
$can_reupload = false;
$rejected_docs = [];
if ($profile) {
    $stmt = $conn->prepare("
        SELECT document_type, file_path, rejection_reason
        FROM alumni_documents
        WHERE alumni_id = ? AND document_status = 'Rejected' AND needs_reupload = 1
    ");
    $stmt->bind_param("i", $profile['alumni_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($doc = $result->fetch_assoc()) {
        $rejected_docs[] = $doc;
        $can_reupload = true;
    }
}

// Check yearly update restriction
$can_update = !$profile || ($profile && ($profile['last_profile_update'] === null || strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$can_update = $can_update || $can_reupload;

ob_start();
?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 p-4 rounded mb-4">Profile updated successfully!</div>
<?php endif; ?>

<div class="container mx-auto px-4">
    <h2 class="text-2xl font-bold mb-6">Your Alumni Profile</h2>
    <?php if ($profile): ?>
        <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
            <h3 class="text-xl font-semibold mb-4">Personal Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($profile['first_name'] . ' ' . ($profile['middle_name'] ? $profile['middle_name'] . ' ' : '') . $profile['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($profile['contact_number'] ?? 'Not set'); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($profile['barangay'] . ', ' . $profile['city'] . ', ' . $profile['province'] . ' ' . $profile['zip_code']); ?></p>
            <p><strong>Year Graduated:</strong> <?php echo htmlspecialchars($profile['year_graduated']); ?></p>
            <p><strong>Employment Status:</strong> <?php echo htmlspecialchars($profile['employment_status'] ?? 'Not set'); ?></p>
            <?php if ($profile['photo_path']): ?>
                <p><strong>Profile Photo:</strong> <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Profile Photo" class="w-32 h-32 object-cover rounded"></p>
            <?php endif; ?>
        </div>

        <?php if ($rejected_docs): ?>
            <div class="bg-yellow-100 p-4 rounded mb-6">
                <h3 class="text-lg font-semibold mb-2">Rejected Documents</h3>
                <p>The following documents were rejected. Please re-upload corrected versions:</p>
                <ul class="list-disc pl-6">
                    <?php foreach ($rejected_docs as $doc): ?>
                        <li><?php echo htmlspecialchars($doc['document_type']); ?>: <?php echo htmlspecialchars($doc['rejection_reason']); ?> (<a href="../Uploads/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">View</a>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-red-600">No profile found. Please complete your profile.</p>
    <?php endif; ?>

    <?php if ($can_update): ?>
        <a href="update_profile.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Update Profile</a>
    <?php else: ?>
        <p class="text-gray-600">You can update your profile again on <?php echo date('F j, Y', strtotime($profile['last_profile_update'] . ' +1 year')); ?>.</p>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>