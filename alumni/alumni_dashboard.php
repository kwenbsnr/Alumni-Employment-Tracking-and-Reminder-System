<?php
// alumni_dashboard.php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Dashboard";
$active_page = "dashboard";
$user_id = $_SESSION["user_id"];

// ---- 1. UPDATE THE SQL QUERY (only the fields that really exist) ----
// ---- UPDATED SQL QUERY ----
$stmt = $conn->prepare("
    SELECT
        u.name as official_name,
        u.student_id,
        u.program,
        u.batch_year as year_graduated,
        ap.last_profile_update,
        ap.employment_status,
        ap.submission_status,
        ap.address_id,
        ap.contact_number,
        COUNT(ad.doc_id) as document_count
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN alumni_documents ad ON u.user_id = ad.user_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_info = $result->fetch_assoc() ?: [];
$stmt->close();

// Build full name from the new structure
$full_name = 'Alumni';
if (!empty($profile_info) && !empty($profile_info['official_name'])) {
    $full_name = htmlspecialchars($profile_info['official_name']);
}
// --- SIMPLIFIED PROFILE COMPLETION LOGIC ---
// Basic required fields that everyone needs
$has_basic_info = !empty($profile_info) && 
    !empty($profile_info['contact_number']) &&
    !empty($profile_info['employment_status']);

$has_address = !empty($profile_info) && !empty($profile_info['address_id']);

// Check photo
$has_photo = false;
$stmt_photo = $conn->prepare("SELECT photo_path FROM alumni_profile WHERE user_id = ?");
if ($stmt_photo) {
    $stmt_photo->bind_param("i", $user_id);
    $stmt_photo->execute();
    $photo_result = $stmt_photo->get_result();
    $photo_data = $photo_result->fetch_assoc();
    $stmt_photo->close();
    $has_photo = !empty($photo_data['photo_path']);
}

// Check documents - BUT if unemployed, documents are not required
$employment_status = $profile_info['employment_status'] ?? '';
$is_unemployed = $employment_status === 'Unemployed';

$has_documents = false;
if (!$is_unemployed) {
    // Only check documents if NOT unemployed
    $stmt_docs = $conn->prepare("SELECT 1 FROM alumni_documents WHERE user_id = ?");
    if ($stmt_docs) {
        $stmt_docs->bind_param("i", $user_id);
        $stmt_docs->execute();
        $has_documents = $stmt_docs->get_result()->num_rows > 0;
        $stmt_docs->close();
    }
} else {
    // If unemployed, consider documents requirement as met
    $has_documents = true;
}

// UPDATED: Adjust required sections based on employment status
if ($is_unemployed) {
    // Unemployed alumni only need 3 sections (documents not required)
    $required_sections = [
        $has_basic_info,    // Contact + employment status
        $has_address,       // Address
        $has_photo          // Profile photo
        // Documents excluded for unemployed
    ];
} else {
    // Employed/self-employed/student need all 4 sections
    $required_sections = [
        $has_basic_info,    // Contact + employment status
        $has_address,       // Address
        $has_photo,         // Profile photo
        $has_documents      // Supporting documents
    ];
}

// SIMPLIFIED: Everyone needs these 4 basic sections
$required_sections = [
    $has_basic_info,    // Contact + employment status
    $has_address,       // Address
    $has_photo,         // Profile photo
    $has_documents      // Supporting documents
];

$completed_count = count(array_filter($required_sections));
$total_required = count($required_sections);
$completion_percentage = $total_required > 0 ? round(($completed_count / $total_required) * 100) : 0;

// Profile is complete when all required sections are filled
$is_profile_complete = $completed_count === $total_required;

// Final display status - SIMPLIFIED
$submission_status = $profile_info['submission_status'] ?? 'Not Submitted';
$profile_status = 'Incomplete';

if ($is_profile_complete) {
    if ($submission_status === 'Approved') {
        $profile_status = 'Complete';
    } elseif ($submission_status === 'Pending') {
        $profile_status = 'Pending Approval';
    } elseif ($submission_status === 'Rejected') {
        $profile_status = 'Rejected';
    } else {
        $profile_status = 'Ready to Submit';
    }
} elseif ($submission_status === 'Rejected') {
    $profile_status = 'Rejected';
}

// Annual update check
$needs_annual_update = !empty($profile_info) &&
    ($profile_info['last_profile_update'] === null ||
     strtotime($profile_info['last_profile_update'] . ' +1 year') <= time());

$needs_profile_update = empty($profile_info) || !$is_profile_complete || $needs_annual_update;
// Profile & Document status
$profile = [
    'employment_status' => $profile_info['employment_status'] ?? 'Not Set',
    'submission_status' => $profile_info['submission_status'] ?? 'Not Submitted'
];
$document = [
    'submission_status' => $profile_info['submission_status'] ?? 'No Profile',
    'document_count' => $profile_info['document_count'] ?? 0
];
// Enhanced document status - FIXED for consistent rejection display
if (!empty($profile_info)) {
    $submission_status = $profile_info['submission_status'] ?? '';
    
    if ($submission_status === 'Approved') {
        $document['submission_status'] = 'Approved';
        $document['message'] = 'All documents approved';
    } elseif ($submission_status === 'Rejected') {
        $document['submission_status'] = 'Rejected';
        $document['message'] = 'Needs resubmission';
    } elseif ($submission_status === 'Pending') {
        $document['submission_status'] = 'Under Review';
        $document['message'] = 'Awaiting administrator review';
    } elseif ($document['document_count'] > 0) {
        $document['submission_status'] = 'Draft';
        $document['message'] = 'Ready for submission';
    } else {
        $document['submission_status'] = 'No Documents';
        $document['message'] = 'Upload required documents';
    }
} else {
    $document['submission_status'] = 'No Profile';
    $document['message'] = 'Complete your profile first';
}

// Fetch recent activities
$stmt_act = $conn->prepare("
    SELECT action_type, description, created_at
    FROM alumni_activity_log
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 6
");
$stmt_act->bind_param("i", $user_id);
$stmt_act->execute();
$activities = $stmt_act->get_result();

ob_start();
?>
<!-- Dashboard Section -->
<div class="min-h-screen bg-gray-50">
    <div class="space-y-6 max-w-7xl mx-auto px-4 py-6">
        <?php if ($needs_profile_update): ?>
            <!-- Profile update warning would go here -->
        <?php endif; ?>

        <!-- Success Card -->
        <?php if (isset($_SESSION['profile_submission_success'])): ?>
            <div id="successCard" class="bg-gradient-to-r from-emerald-500 to-green-600 p-4 text-white flex items-center justify-between shadow-xl animate-fade-in rounded-xl border border-emerald-400/30 backdrop-blur-sm">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 flex items-center justify-center rounded-lg backdrop-blur-sm">
                        <i class="fas fa-check text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Submission Successful!</h3>
                        <p class="text-emerald-100 text-opacity-90 text-sm">Your profile has been submitted for review.</p>
                    </div>
                </div>
                <button id="closeSuccessCard" class="text-white hover:text-emerald-200 text-xl font-bold transition-all duration-300 hover:scale-110">×</button>
            </div>
            <?php unset($_SESSION['profile_submission_success']); ?>
        <?php endif; ?>

        <!-- Welcome Card -->
        <?php if (isset($_SESSION['show_welcome'])): ?>
            <div id="welcomeCard" class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-700 p-6 text-white shadow-xl rounded-xl border border-indigo-400/30 backdrop-blur-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -translate-y-12 translate-x-12"></div>
                <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -translate-x-8 translate-y-8"></div>
                
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 bg-gradient-to-r from-white to-blue-100 bg-clip-text text-transparent">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
                        <p class="text-blue-100 text-sm font-medium">Your network and resources are waiting. Check your quick stats below.</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 flex items-center justify-center rounded-xl backdrop-blur-sm border border-white/30">
                        <i class="fas fa-graduation-cap text-2xl"></i>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['show_welcome']); ?>
        <?php endif; ?>

        <!-- Main Content Grid - IMPROVED LAYOUT -->
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
            <!-- Left Column - Profile Completion & Quick Actions -->
            <div class="xl:col-span-3 space-y-6">
                <!-- Profile Completion Card - IMPROVED SPACING -->
                <div class="bg-white rounded-xl shadow-2xl border border-indigo-100 overflow-hidden hover:shadow-3xl transition-all duration-500">
                    <div class="p-6 bg-gradient-to-br from-white to-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="md:col-span-2 flex items-center space-x-4">
                                <div class="relative">
                                    <div class="w-14 h-14 <?php
                                        echo $profile_status === 'Complete' ? 'bg-gradient-to-br from-emerald-500 to-green-600 text-white shadow-xl' :
                                            ($profile_status === 'Pending Approval' ? 'bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-xl' : 
                                            'bg-gradient-to-br from-red-500 to-pink-500 text-white shadow-xl');
                                        ?> flex items-center justify-center rounded-2xl">
                                        <i class="fas fa-user-check text-xl"></i> 
                                    </div>
                                    <div class="absolute -top-1 -right-1 w-6 h-6 <?php
                                        echo $profile_status === 'Complete' ? 'bg-emerald-500' :
                                            ($profile_status === 'Pending Approval' ? 'bg-amber-500' : 'bg-red-500');
                                        ?> rounded-full flex items-center justify-center border-3 border-white shadow-lg">
                                        <i class="fas <?php
                                            echo $profile_status === 'Complete' ? 'fa-check' :
                                                    ($profile_status === 'Pending Approval' ? 'fa-clock' : 'fa-exclamation');
                                            ?> text-white text-xs"></i>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold text-indigo-900">Profile Completion</h3> 
                                    <div class="flex items-center mt-1">
                                        <span class="text-sm font-extrabold <?php
                                            echo $profile_status === 'Complete' ? 'text-emerald-700 bg-emerald-100 border-2 border-emerald-400' :
                                                    ($profile_status === 'Pending Approval' ? 'text-amber-700 bg-amber-100 border-2 border-amber-400' :
                                                    'text-red-700 bg-red-100 border-2 border-red-400');
                                            ?> px-3 py-1 rounded-lg shadow-inner uppercase tracking-wider text-xs">
                                            <?php echo $profile_status; ?>
                                        </span>
                                        <?php if ($profile_status === 'Ready to Submit'): ?>
                                            <button class="ml-2 text-xs bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white px-3 py-1 rounded-lg font-bold shadow-md transform hover:scale-105 transition duration-300 animate-pulse">
                                                <i class="fas fa-paper-plane mr-1"></i> Submit Now
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col items-end justify-center">
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-gray-700">Overall Progress</span>
                                    <div class="flex items-center justify-end space-x-2 mt-1">
                                        <span class="text-xl font-extrabold <?php echo $completion_percentage >= 90 ? 'text-emerald-600' : ($completion_percentage >= 70 ? 'text-amber-600' : 'text-red-600'); ?>">
                                            <?php echo $completion_percentage; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden shadow-inner mt-2">
                                    <div class="h-full transition-all duration-1000 rounded-full relative <?php
                                        echo $completion_percentage >= 90 ? 'bg-gradient-to-r from-emerald-500 to-green-500' :
                                             ($completion_percentage >= 70 ? 'bg-gradient-to-r from-amber-500 to-orange-500' :
                                             'bg-gradient-to-r from-red-500 to-pink-500');
                                        ?>" style="width: <?php echo $completion_percentage; ?>%">
                                        <div class="absolute inset-0 bg-white/20"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subtle divider line -->
                        <div class="border-b border-gray-200 my-4"></div>

                        <div class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-xl p-4 shadow-md">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-bold text-indigo-900 flex items-center gap-2 text-sm">
                                            <i class="fas fa-briefcase text-indigo-500"></i> Employment Status
                                        </h4>
                                        <span class="text-xs font-bold <?php echo ($profile_info['employment_status'] ?? '') !== 'Not Set' ? 'text-indigo-700 bg-white shadow-sm' : 'text-gray-600 bg-gray-200'; ?> px-2.5 py-1 rounded-full">
                                            <?php echo $profile_info['employment_status'] ?? 'Not Set'; ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-indigo-800 font-medium leading-snug">
                                        <?php
                                        $empStatusDisplay = $profile_info['employment_status'] ?? 'Not specified';
                                        if ($empStatusDisplay === 'Employed') $empStatusDisplay = 'Currently working';
                                        if ($empStatusDisplay === 'Self-Employed') $empStatusDisplay = 'Running own business/freelance';
                                        if ($empStatusDisplay === 'Student') $empStatusDisplay = 'Currently enrolled in higher education';
                                        echo $empStatusDisplay;
                                        ?>
                                    </p>
                                    <?php if (!empty($profile_info['last_profile_update'])): ?>
                                        <p class="text-xs text-indigo-600 mt-1 italic">
                                            Last updated: <?php echo date('M d, Y', strtotime($profile_info['last_profile_update'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php
                                    $empMsg = '';
                                    $status = $profile_info['employment_status'] ?? '';
                                    if ($status === 'Employed') $empMsg = 'Verification needed.';
                                    elseif ($status === 'Self-Employed') $empMsg = 'Business docs needed.';
                                    elseif ($status === 'Student') $empMsg = 'Enrollment proof needed.';
                                    elseif ($status === 'Unemployed') $empMsg = 'No documents required.';
                                    if ($empMsg):
                                    ?>
                                        <div class="mt-2 text-xs bg-white/80 text-indigo-700 px-3 py-1.5 rounded-lg border border-indigo-100">
                                            <i class="fas fa-file-invoice mr-1"></i> Document requirement: <?php echo $empMsg; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-4 shadow-md">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-bold text-purple-900 flex items-center gap-2 text-sm">
                                            <i class="fas fa-clipboard-check text-purple-500"></i> Document Review
                                        </h4>
                                        <span class="text-xs font-bold <?php
                                            echo $document['submission_status'] === 'Approved' ? 'text-emerald-700 bg-white shadow-sm' :
                                                    ($document['submission_status'] === 'Under Review' ? 'text-amber-700 bg-white shadow-sm animate-pulse' :
                                                    ($document['submission_status'] === 'Rejected' ? 'text-red-700 bg-white shadow-sm' : 'text-gray-600 bg-gray-200'));
                                            ?> px-2.5 py-1 rounded-full">
                                            <?php echo $document['submission_status']; ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-purple-800 leading-snug">
                                        <?php
                                        $docMsg = $document['message'] ?? 'All required documents must be uploaded and approved.';
                                        if ($document['submission_status'] === 'Approved') $docMsg = 'All documents verified and approved!';
                                        echo $docMsg;
                                        ?>
                                    </p>
                                    <div class="flex items-center mt-3 text-sm font-bold text-purple-700">
                                        <i class="fas fa-file-alt mr-1"></i>
                                        <span class="text-purple-700">
                                            <?php echo $document['document_count']; ?> file<?php echo $document['document_count'] != 1 ? 's' : ''; ?> uploaded
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                                <p class="text-sm font-bold text-indigo-800 mb-3 flex items-center gap-2">
                                    <i class="fas fa-list-check text-indigo-500"></i> Completion Checklist
                                </p>
                                <p class="text-xs text-gray-700 mb-3">
                                    <?php
                                    echo $profile_status === 'Complete' ? '✅ Great job! Your profile is fully verified. Enjoy full access.'
                                                : ($profile_status === 'Pending Approval' ? '⏳ We are currently reviewing your information. This may take 1-2 business days.'
                                                : ($profile_status === 'Rejected' ? '⚠️ Action required! Please review the feedback provided and update the necessary sections.'
                                                : '🔔 Complete the following steps to submit your profile for verification.'));
                                    ?>
                                </p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                    <div class="flex items-center gap-2 font-medium <?php echo $has_basic_info ? 'text-emerald-600' : 'text-gray-600'; ?>">
                                        <i class="fas <?php echo $has_basic_info ? 'fa-check-circle' : 'fa-circle-dot'; ?> <?php echo $has_basic_info ? 'text-emerald-500' : 'text-gray-400'; ?>"></i>
                                        <span class="text-xs">Basic Info & Employment</span>
                                    </div>
                                    <div class="flex items-center gap-2 font-medium <?php echo $has_address ? 'text-emerald-600' : 'text-gray-600'; ?>">
                                        <i class="fas <?php echo $has_address ? 'fa-check-circle' : 'fa-circle-dot'; ?> <?php echo $has_address ? 'text-emerald-500' : 'text-gray-400'; ?>"></i>
                                        <span class="text-xs">Address & Contact</span>
                                    </div>
                                    <div class="flex items-center gap-2 font-medium <?php echo $has_photo ? 'text-emerald-600' : 'text-gray-600'; ?>">
                                        <i class="fas <?php echo $has_photo ? 'fa-check-circle' : 'fa-circle-dot'; ?> <?php echo $has_photo ? 'text-emerald-500' : 'text-gray-400'; ?>"></i>
                                        <span class="text-xs">Profile Photo</span>
                                    </div>
                                    <div class="flex items-center gap-2 font-medium <?php echo $has_documents ? 'text-emerald-600' : 'text-gray-600'; ?>">
                                        <i class="fas <?php echo $has_documents ? 'fa-check-circle' : 'fa-circle-dot'; ?> <?php echo $has_documents ? 'text-emerald-500' : 'text-gray-400'; ?>"></i>
                                        <span class="text-xs">Documents Uploaded</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card - IMPROVED SPACING -->
                <div class="bg-white rounded-xl shadow-lg border-2 border-indigo-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-3 border-b border-indigo-200">
                        <h3 class="text-lg font-extrabold text-indigo-800">🚀 Quick Actions</h3>
                        <p class="text-purple-700 text-xs mt-1 font-medium">Manage your profile and documents efficiently</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x divide-indigo-100">
                        <a href="alumni_profile.php"
                            class="group flex items-center justify-between px-4 py-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-blue-500 text-white flex items-center justify-center rounded-full shadow-md group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-id-card text-md"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">View Profile</p>
                                    <p class="text-xs text-gray-500 mt-0.5">See complete information</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-blue-500 text-md group-hover:translate-x-1 transition-transform duration-300"></i>
                        </a>

                        <a href="alumni_profile.php?edit=1"
                            class="group flex items-center justify-between px-4 py-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-purple-500 text-white flex items-center justify-center rounded-full shadow-md group-hover:bg-purple-600 transition-colors duration-300">
                                        <i class="fas fa-user-pen text-md"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Update Profile</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Edit personal & work info</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-purple-500 text-md group-hover:translate-x-1 transition-transform duration-300"></i>
                        </a>

                        <a href="alumni_profile.php#documents"
                            class="group flex items-center justify-between px-4 py-4 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 transition-all duration-300">
                            <div class="flex items-center space-x-3">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-emerald-500 text-white flex items-center justify-center rounded-full shadow-md group-hover:bg-emerald-600 transition-colors duration-300">
                                        <i class="fas fa-cloud-upload-alt text-md"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Upload Files</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Add certificates & docs</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-emerald-500 text-md group-hover:translate-x-1 transition-transform duration-300"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Recent Activity - ADJUSTED HEIGHT -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-b-4 border-indigo-300 overflow-hidden h-full flex flex-col transition-shadow duration-500 hover:shadow-xl">
                    <div class="p-5 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-indigo-500 text-white flex items-center justify-center rounded-full shadow-md">
                                    <i class="fas fa-history text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-extrabold text-indigo-800">Recent Activity ⚡</h3>
                                    <span class="text-xs font-bold text-purple-700 bg-purple-100 px-2 py-1 rounded-full border border-purple-200 shadow-sm">Last 30 Days</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 p-5 overflow-hidden">
                        <?php if ($activities->num_rows > 0): ?>
                            <div class="space-y-3 h-full overflow-hidden hover:overflow-y-auto no-scrollbar">
                                <?php while ($act = $activities->fetch_assoc()): ?>
                                    <?php
                                    // Default icon & color
                                    $icon  = 'fa-circle-info';
                                    $color = 'text-gray-500';
                                    $bgColor = 'bg-gray-50';
                                    $desc  = strtolower($act['description'] ?? '');

                                    // Smart icon mapping
                                    switch ($act['action_type']) {
                                        case 'profile_updated':
                                        case 'profile_saved':
                                            $icon = 'fa-user-pen';
                                            $color = 'text-amber-600';
                                            break;

                                        case 'profile_photo_updated':
                                            $icon = 'fa-image';
                                            $color = 'text-pink-600';
                                            break;

                                        case 'profile_submitted':
                                            $icon = 'fa-paper-plane';
                                            $color = 'text-blue-600';
                                            break;

                                        case 'profile_approved':
                                            $icon = 'fa-badge-check';
                                            $color = 'text-emerald-500';
                                            $bgColor = 'bg-emerald-50';
                                            break;

                                        case 'profile_rejected':
                                            $icon = 'fa-circle-xmark';
                                            $color = 'text-red-600';
                                            $bgColor = 'bg-red-50';
                                            break;

                                        case 'document_uploaded':
                                        case (strpos($act['action_type'], 'uploaded_') === 0):
                                            $icon = 'fa-file-arrow-up';
                                            $color = 'text-blue-500';

                                            // Specific document icons based on description
                                            if (str_contains($desc, 'coe') || str_contains($desc, 'certificate of employment')) {
                                                $icon = 'fa-file-contract';
                                                $color = 'text-cyan-500';
                                            } elseif (str_contains($desc, 'tor') || str_contains($desc, 'transcript')) {
                                                $icon = 'fa-file-lines';
                                                $color = 'text-indigo-500';
                                            } elseif (str_contains($desc, 'diploma')) {
                                                $icon = 'fa-graduation-cap';
                                                $color = 'text-emerald-500';
                                            } elseif (str_contains($desc, 'resume') || str_contains($desc, 'cv')) {
                                                $icon = 'fa-file-user';
                                                $color = 'text-purple-500';
                                            } elseif (str_contains($desc, 'id') || str_contains($desc, 'identification') || str_contains($desc, 'valid id')) {
                                                $icon = 'fa-id-card';
                                                $color = 'text-amber-500';
                                            } elseif (str_contains($desc, '2x2') || str_contains($desc, 'photo')) {
                                                $icon = 'fa-image';
                                                $color = 'text-pink-500';
                                            }
                                            break;

                                        case 'document_deleted':
                                            $icon = 'fa-file-slash';
                                            $color = 'text-red-500';
                                            break;

                                        case 'login':
                                        case 'logged_in':
                                            $icon = 'fa-right-to-bracket';
                                            $color = 'text-gray-600';
                                            break;

                                        default:
                                            $icon = 'fa-bell';
                                            $color = 'text-teal-500';
                                    }
                                    ?>

                                    <div class="flex items-start space-x-3 p-3 rounded-lg <?= $bgColor ?> border border-gray-200 hover:bg-gradient-to-r hover:from-white hover:to-indigo-50 hover:border-indigo-300 transition-all duration-300">
                                        <div class="w-8 h-8 <?= $color ?> flex items-center justify-center rounded-full flex-shrink-0 mt-0.5 border border-current bg-white shadow-sm">
                                            <i class="fas <?= $icon ?> text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 leading-snug">
                                                <?= htmlspecialchars($act['description'] ?: ucwords(str_replace('_', ' ', $act['action_type']))) ?>
                                            </p>
                                            <p class="text-xs text-gray-500 font-medium mt-1 italic">
                                                <i class="far fa-clock mr-1"></i>
                                                <?= date('M j, Y \a\t g:i A', strtotime($act['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10 text-gray-400 h-full flex items-center justify-center">
                                <div>
                                    <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-indigo-200 text-indigo-400">
                                        <i class="fas fa-box-open text-xl opacity-80"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-600">No Recent Activity</p>
                                    <p class="text-xs mt-1 text-gray-500">Your actions will appear here when you interact with your profile or documents.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Activity Summary Section -->
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex justify-between items-center text-sm">
                            <div>
                                <p class="font-semibold text-gray-700">Activity Summary</p>
                                <p class="text-xs text-gray-500">Last 30 days</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-indigo-700"><?php echo $activities->num_rows; ?> activities</p>
                                <p class="text-xs text-gray-500">Keep it up!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help & Support Modal -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white border border-gray-200 max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0 rounded-xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 to-green-600 p-5 text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold flex items-center">
                    <i class="fas fa-question-circle mr-2"></i>
                    Help & Support
                </h3>
                <button id="closeHelpModal" class="text-white hover:text-gray-200 text-lg font-bold transition-colors duration-200 hover:scale-110">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="space-y-5 p-5 bg-white">
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col items-center p-3 bg-emerald-50 text-center border-2 border-emerald-100 rounded-lg hover:shadow-sm transition-all duration-300">
                    <div class="bg-emerald-100 p-2 mb-2 rounded-md">
                        <i class="fas fa-envelope text-emerald-600 text-lg"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-xs">Email Support</h4>
                    <p class="text-xs text-gray-600 mt-1">main@jhcsc.edu.ph</p>
                </div>

                <div class="flex flex-col items-center p-3 bg-blue-50 text-center border-2 border-blue-100 rounded-lg hover:shadow-sm transition-all duration-300">
                    <div class="bg-blue-100 p-2 mb-2 rounded-md">
                        <i class="fas fa-phone text-blue-600 text-lg"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-xs">Phone Support</h4>
                    <p class="text-xs text-gray-600 mt-1">0948 954 7078 - BSIT Faculty</p>
                </div>

                <div class="flex flex-col items-center p-3 bg-purple-50 text-center border-2 border-purple-100 rounded-lg hover:shadow-sm transition-all duration-300">
                    <div class="bg-purple-100 p-2 mb-2 rounded-md">
                        <i class="fas fa-clock text-purple-600 text-lg"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-xs">Support Hours</h4>
                    <p class="text-xs text-gray-600 mt-1">Mon - Fri: 9AM - 5PM EST</p>
                </div>

                <div class="flex flex-col items-center p-3 bg-amber-50 text-center border-2 border-amber-100 rounded-lg hover:shadow-sm transition-all duration-300">
                    <div class="bg-amber-100 p-2 mb-2 rounded-md">
                        <i class="fas fa-life-ring text-amber-600 text-lg"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-xs">FAQs & Guides</h4>
                    <p class="text-xs text-gray-600 mt-1">Visit our knowledge base</p>
                </div>
            </div>

            <div class="bg-gray-50 px-5 py-3 flex justify-end space-x-2 -mx-5 -mb-5 mt-5 border-t border-gray-200">
                <button id="cancelHelp" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-semibold transition-colors duration-200 rounded-md hover:bg-gray-100 text-sm">
                    Close
                </button>
                <a href="mailto:support@alumniportal.edu" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold transition-all duration-300 rounded-md shadow-md hover:shadow-lg transform hover:-translate-y-0.5 text-sm">
                    Contact Now
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const welcomeCard = document.getElementById('welcomeCard');
    if (welcomeCard) {
        setTimeout(() => {
            welcomeCard.style.transition = 'opacity 1s ease-out, transform 1s ease-out';
            welcomeCard.style.opacity = '0';
            welcomeCard.style.transform = 'translateY(-20px)';
            setTimeout(() => welcomeCard.remove(), 1000);
        }, 5000);
    }

    // Success Card Auto-Hide & Close Button
    const successCard = document.getElementById('successCard');
    const closeSuccessBtn = document.getElementById('closeSuccessCard');

    if (successCard) {
        // Auto-hide after 4 seconds
        const autoHide = setTimeout(() => {
            successCard.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            successCard.style.opacity = '0';
            successCard.style.transform = 'translateY(-20px)';
            setTimeout(() => successCard.remove(), 600);
        }, 4000);

        // Manual close cancels auto-hide
        if (closeSuccessBtn) {
            closeSuccessBtn.addEventListener('click', () => {
                clearTimeout(autoHide);
                successCard.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                successCard.style.opacity = '0';
                successCard.style.transform = 'translateY(-20px)';
                setTimeout(() => successCard.remove(), 400);
            });
        }
    }

    // Help & Support Modal Functionality
    const helpButton = document.getElementById('helpButton');
    const helpModal = document.getElementById('helpModal');
    const closeHelpModal = document.getElementById('closeHelpModal');
    const cancelHelp = document.getElementById('cancelHelp');
    const modalContent = helpModal.querySelector('.bg-white');

    function showHelpModal() {
        helpModal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function hideHelpModal() {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            helpModal.classList.add('hidden');
        }, 300);
    }

    if (helpButton) {
        helpButton.addEventListener('click', showHelpModal);
    }
    if (closeHelpModal) {
        closeHelpModal.addEventListener('click', hideHelpModal);
    }
    if (cancelHelp) {
        cancelHelp.addEventListener('click', hideHelpModal);
    }

    // Close modal when clicking outside
    helpModal.addEventListener('click', (e) => {
        if (e.target === helpModal) {
            hideHelpModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !helpModal.classList.contains('hidden')) {
            hideHelpModal();
        }
    });

    // Add hover effects to all cards
    const cards = document.querySelectorAll('.bg-white');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-4px)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });
    });

    // Existing notification functionality (preserved)
    const notifButton = document.getElementById('notificationBtn');
    const notifPopup = document.getElementById('notifPopup');
    if (notifButton && notifPopup) {
        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            notifPopup.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!notifPopup.classList.contains('hidden') && !notifPopup.contains(e.target) && e.target !== notifButton) {
                notifPopup.classList.add('hidden');
            }
        });
        document.getElementById('markReadBtn').addEventListener('click', () => {
            notifButton.querySelector('span').classList.add('hidden');
        });
    }
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.6s ease-out;
}

/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}

/* Smooth transitions for all interactive elements */
* {
    transition-property: color, background-color, border-color, transform, box-shadow;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Enhanced focus states for accessibility */
button:focus, a:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Ensure no scroll bars on main dashboard */
.min-h-screen {
    overflow-x: hidden;
}

.bg-gray-50 {
    overflow: hidden;
}
</style>
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>