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

// Check documents
$has_documents = false;
$stmt_docs = $conn->prepare("SELECT 1 FROM alumni_documents WHERE user_id = ?");
if ($stmt_docs) {
    $stmt_docs->bind_param("i", $user_id);
    $stmt_docs->execute();
    $has_documents = $stmt_docs->get_result()->num_rows > 0;
    $stmt_docs->close();
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

// Profile is complete when all 4 basic sections are filled
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
ob_start();
?>
<!-- Dashboard Section -->
<div class="space-y-8">
    <?php if ($needs_profile_update): ?>
        <!-- Profile update warning would go here -->
    <?php endif; ?>

    <!-- Success Card -->
    <?php if (isset($_SESSION['profile_submission_success'])): ?>
        <div id="successCard" class="bg-gradient-to-r from-emerald-500 to-green-600 p-6 text-white flex items-center justify-between shadow-xl animate-fade-in rounded-2xl border border-emerald-400/30 backdrop-blur-sm">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 flex items-center justify-center rounded-xl backdrop-blur-sm">
                    <i class="fas fa-check text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold">Submission Successful!</h3>
                    <p class="text-emerald-100 text-opacity-90">Your profile has been submitted for review.</p>
                </div>
            </div>
            <button id="closeSuccessCard" class="text-white hover:text-emerald-200 text-2xl font-bold transition-all duration-300 hover:scale-110">×</button>
        </div>
        <?php unset($_SESSION['profile_submission_success']); ?>
    <?php endif; ?>

    <!-- Welcome Card -->
    <?php if (isset($_SESSION['show_welcome'])): ?>
        <div id="welcomeCard" class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-700 p-8 text-white shadow-2xl rounded-2xl border border-indigo-400/30 backdrop-blur-sm relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -translate-x-12 translate-y-12"></div>
            
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <h2 class="text-4xl font-bold mb-3 bg-gradient-to-r from-white to-blue-100 bg-clip-text text-transparent">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
                    <p class="text-blue-100 text-lg font-medium">Your network and resources are waiting. Check your quick stats below.</p>
                </div>
                <div class="w-20 h-20 bg-white/20 flex items-center justify-center rounded-2xl backdrop-blur-sm border border-white/30">
                    <i class="fas fa-graduation-cap text-3xl"></i>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['show_welcome']); ?>
    <?php endif; ?>

    <!-- MODERN DASHBOARD GRID -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 max-w-7xl mx-auto">
        
        <!-- LEFT COLUMN: Stats Cards -->
        <div class="xl:col-span-2 space-y-6">
            
            <!-- Profile Status Card - MODERN DESIGN -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-14 h-14 <?php
                                    echo $profile_status === 'Complete' ? 'bg-emerald-100 text-emerald-600' :
                                         ($profile_status === 'Pending Approval' ? 'bg-amber-100 text-amber-600' : 'bg-orange-100 text-orange-600');
                                ?> flex items-center justify-center rounded-xl backdrop-blur-sm border">
                                    <i class="fas fa-user-check text-xl"></i>
                                </div>
                                <div class="absolute -top-1 -right-1 w-6 h-6 <?php
                                    echo $profile_status === 'Complete' ? 'bg-emerald-500' :
                                         ($profile_status === 'Pending Approval' ? 'bg-amber-500' : 'bg-orange-500');
                                ?> rounded-full flex items-center justify-center border-2 border-white">
                                    <i class="fas <?php
                                        echo $profile_status === 'Complete' ? 'fa-check text-white text-xs' :
                                             ($profile_status === 'Pending Approval' ? 'fa-clock text-white text-xs' : 'fa-exclamation text-white text-xs');
                                    ?>"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Profile Status</h3>
                                <span class="text-sm font-semibold <?php
                                    echo $profile_status === 'Complete' ? 'text-emerald-600' :
                                         ($profile_status === 'Pending Approval' ? 'text-amber-600' : 'text-orange-600');
                                ?> px-3 py-1 rounded-full bg-opacity-10 <?php
                                    echo $profile_status === 'Complete' ? 'bg-emerald-500' :
                                         ($profile_status === 'Pending Approval' ? 'bg-amber-500' : 'bg-orange-500');
                                ?>">
                                    <?php echo $profile_status; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-700">Completion Progress</span>
                            <span class="text-lg font-bold <?php
                                echo $completion_percentage >= 90 ? 'text-emerald-600' :
                                     ($completion_percentage >= 70 ? 'text-amber-600' : 'text-orange-600');
                            ?>"><?php echo $completion_percentage; ?>%</span>
                        </div>
                        
                        <!-- Animated Progress Bar -->
                        <div class="relative">
                            <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full <?php
                                    echo $completion_percentage >= 90 ? 'bg-gradient-to-r from-emerald-500 to-green-600' :
                                         ($completion_percentage >= 70 ? 'bg-gradient-to-r from-amber-500 to-orange-600' : 'bg-gradient-to-r from-orange-500 to-red-600');
                                ?> transition-all duration-1000 ease-out rounded-full"
                                     style="width: <?php echo $completion_percentage; ?>%">
                                    <div class="h-full w-full animate-pulse bg-white bg-opacity-30"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Message -->
                        <p class="text-sm text-gray-600 leading-relaxed">
                            <?php
                            if ($profile_status === 'Complete') {
                                echo '🎉 Your profile is fully verified and complete. All features are now unlocked!';
                            } elseif ($profile_status === 'Pending Approval') {
                                echo '⏳ Profile submitted and under administrative review. You will be notified once approved.';
                            } else {
                                echo '📝 Complete your profile to unlock all platform features and connect with fellow alumni.';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Employment Card -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-2xl shadow-lg border border-blue-100 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-white text-blue-600 flex items-center justify-center rounded-xl shadow-sm border border-blue-200">
                                <i class="fas fa-briefcase text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Employment</h3>
                                <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                                    <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                        ? 'Current' : 'Not Set'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-md font-bold text-gray-800 <?php
                            echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                ? '' : 'italic text-gray-400';
                        ?>">
                            <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'NotSet'
                                ? htmlspecialchars($profile_info['employment_status'])
                                : 'No employment info'; ?>
                        </p>
                        
                        <?php if (!empty($profile_info['last_profile_update'])): ?>
                            <div class="flex items-center space-x-2 text-xs text-gray-500">
                                <i class="fas fa-clock"></i>
                                <span>Updated <?php echo date('M d, Y', strtotime($profile_info['last_profile_update'])); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php
                        $msg = '';
                        $status = $profile_info['employment_status'] ?? '';

                        if ($status === 'Unemployed') {
                            $msg = 'No documents required — you are currently unemployed.';
                        } elseif ($status === 'Employed') {
                            $msg = 'Works at the company entered.';
                        } elseif ($status === 'Student') {
                            $msg = 'Studies at the school entered.';
                        } elseif ($status === 'Employed' && $status === 'Student') {
                            $msg = 'Works at the company entered and studies at the school entered.';
                        }

                        if ($msg !== '' && $status !== 'Not Set'):
                        ?>
                            <div class="flex items-center space-x-2 text-xs text-blue-600 font-medium bg-blue-50 px-3 py-2 rounded-lg">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo $msg; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Document Review Card -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl shadow-lg border border-gray-200 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-white <?php
                                echo $document['submission_status'] === 'Approved' ? 'text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'text-amber-600' : 'text-gray-500'));
                            ?> flex items-center justify-center rounded-xl shadow-sm border <?php
                                echo $document['submission_status'] === 'Approved' ? 'border-emerald-200' :
                                     ($document['submission_status'] === 'Rejected' ? 'border-red-200' :
                                     ($document['submission_status'] === 'Under Review' ? 'border-amber-200' : 'border-gray-200'));
                            ?>">
                                <i class="fas fa-clipboard-check text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Document Review</h3>
                                <span class="text-xs font-semibold <?php
                                    echo $document['submission_status'] === 'Approved' ? 'text-emerald-600 bg-emerald-100' :
                                         ($document['submission_status'] === 'Rejected' ? 'text-red-600 bg-red-100' :
                                         ($document['submission_status'] === 'Under Review' ? 'text-amber-600 bg-amber-100' : 'text-gray-500 bg-gray-100'));
                                ?> px-2 py-1 rounded-full">
                                    <?php echo htmlspecialchars($document['submission_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium <?php
                            echo $document['submission_status'] === 'Approved' ? 'text-emerald-700' :
                                 ($document['submission_status'] === 'Rejected' ? 'text-red-700' :
                                 ($document['submission_status'] === 'Under Review' ? 'text-amber-700' : 'text-gray-600'));
                        ?>">
                            <?php echo htmlspecialchars($document['message']); ?>
                        </p>

                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div class="flex items-center space-x-2 text-xs font-semibold <?php
                                echo $document['submission_status'] === 'Approved' ? 'text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'text-amber-600' : 'text-gray-500'));
                            ?>">
                                <i class="fas fa-paperclip"></i>
                                <span><?php echo $document['document_count']; ?> files</span>
                            </div>

                            <div class="w-8 h-8 <?php
                                echo $document['submission_status'] === 'Approved' ? 'bg-emerald-100 text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500'));
                            ?> flex items-center justify-center rounded-lg border">
                                <?php if ($document['submission_status'] === 'Approved'): ?>
                                    <i class="fas fa-check text-sm"></i>
                                <?php elseif ($document['submission_status'] === 'Under Review'): ?>
                                    <i class="fas fa-hourglass-half text-sm"></i>
                                <?php elseif ($document['submission_status'] === 'Rejected'): ?>
                                    <i class="fas fa-times text-sm"></i>
                                <?php else: ?>
                                    <i class="fas fa-question text-sm"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uploaded Documents Card -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-100 rounded-2xl shadow-lg border border-purple-100 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-white text-purple-600 flex items-center justify-center rounded-xl shadow-sm border border-purple-200">
                                <i class="fas fa-cloud-upload-alt text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Documents</h3>
                                <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                                    <?php echo $document['document_count'] > 0 ? 'Active' : 'Empty'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-600 mb-1">
                                <?php echo $document['document_count']; ?>
                            </div>
                            <div class="text-xs text-purple-500 uppercase font-semibold">
                                Uploaded File<?php echo $document['document_count'] != 1 ? 's' : ''; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-purple-200">
                            <div class="flex items-center space-x-2 text-xs font-semibold <?php
                                echo $document['document_count'] > 0 ? 'text-emerald-600' : 'text-gray-500';
                            ?>">
                                <i class="fas fa-check-circle"></i>
                                <span>Ready: <?php echo $document['document_count'] > 0 ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="w-8 h-8 <?php echo $document['document_count'] > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-400'; ?> flex items-center justify-center rounded-lg border">
                                <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-check text-sm' : 'fa-times text-sm'; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS - MOVED BELOW THE 3 CARDS -->
            <div class="mt-6">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-8 py-6 border-b border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-bolt text-yellow-500 mr-3"></i>
                            Quick Actions
                        </h3>
                        <p class="text-gray-600 mt-1">Manage your profile and documents efficiently</p>
                    </div>

                    <!-- Three full-stretch buttons -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-0 divide-x divide-gray-200">
                        
                        <!-- 1. View Profile -->
                        <a href="alumni_profile.php" 
                           class="group flex items-center justify-between px-8 py-8 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-500 border-b md:border-b-0 md:border-r border-gray-200 last:border-b-0">
                            <div class="flex items-center space-x-6">
                                <div class="relative">
                                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center rounded-xl group-hover:scale-110 transition-transform duration-500 shadow-lg">
                                        <i class="fas fa-id-card text-xl"></i>
                                    </div>
                                    <div class="absolute -inset-1 bg-indigo-200 rounded-xl opacity-0 group-hover:opacity-100 blur transition-all duration-500 -z-10"></div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-lg">View Profile</p>
                                    <p class="text-sm text-gray-600 mt-1">See your complete information</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-indigo-500 text-xl group-hover:translate-x-3 transition-transform duration-500"></i>
                        </a>

                        <!-- 2. Update Profile -->
                        <a href="alumni_profile.php?edit=1" 
                           class="group flex items-center justify-between px-8 py-8 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 transition-all duration-500 border-b md:border-b-0 md:border-r border-gray-200 last:border-b-0">
                            <div class="flex items-center space-x-6">
                                <div class="relative">
                                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 text-white flex items-center justify-center rounded-xl group-hover:scale-110 transition-transform duration-500 shadow-lg">
                                        <i class="fas fa-user-pen text-xl"></i>
                                    </div>
                                    <div class="absolute -inset-1 bg-purple-200 rounded-xl opacity-0 group-hover:opacity-100 blur transition-all duration-500 -z-10"></div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-lg">Update Profile</p>
                                    <p class="text-sm text-gray-600 mt-1">Edit personal & work information</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-purple-500 text-xl group-hover:translate-x-3 transition-transform duration-500"></i>
                        </a>

                        <!-- 3. Upload Files -->
                        <a href="alumni_profile.php#documents" 
                           class="group flex items-center justify-between px-8 py-8 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-green-50 transition-all duration-500">
                            <div class="flex items-center space-x-6">
                                <div class="relative">
                                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-green-600 text-white flex items-center justify-center rounded-xl group-hover:scale-110 transition-transform duration-500 shadow-lg">
                                        <i class="fas fa-cloud-upload-alt text-xl"></i>
                                    </div>
                                    <div class="absolute -inset-1 bg-emerald-200 rounded-xl opacity-0 group-hover:opacity-100 blur transition-all duration-500 -z-10"></div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-lg">Upload Files</p>
                                    <p class="text-sm text-gray-600 mt-1">Add certificates & documents</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-emerald-500 text-xl group-hover:translate-x-3 transition-transform duration-500"></i>
                        </a>

                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Recent Activity - EXTENDED HEIGHT -->
        <div class="space-y-6">
            
            <!-- Recent Activity Card - EXTENDED HEIGHT -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1 overflow-hidden h-full min-h-[600px] flex flex-col">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center rounded-xl shadow-lg">
                                <i class="fas fa-history text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Recent Activity</h3>
                                <span class="text-sm font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">Last 30 Days</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Fetch recent activities
                $stmt_act = $conn->prepare("
                    SELECT action_type, description, created_at
                    FROM alumni_activity_log
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 8
                ");
                $stmt_act->bind_param("i", $user_id);
                $stmt_act->execute();
                $activities = $stmt_act->get_result();
                ?>

                <div class="flex-1 p-6 overflow-hidden">
                    <?php if ($activities->num_rows > 0): ?>
                        <div class="space-y-4 h-full overflow-hidden hover:overflow-y-auto no-scrollbar">
                            <?php while ($act = $activities->fetch_assoc()): ?>
                                <?php
                                // Default icon & color
                                $icon  = 'fa-circle-check';
                                $color = 'from-green-500 to-emerald-600';
                                $bgColor = 'bg-gradient-to-br from-green-50 to-emerald-100';
                                $desc  = strtolower($act['description'] ?? '');

                                // Smart icon mapping
                                switch ($act['action_type']) {
                                    case 'profile_updated':
                                    case 'profile_saved':
                                        $icon = 'fa-user-pen';
                                        $color = 'from-orange-500 to-amber-600';
                                        $bgColor = 'bg-gradient-to-br from-orange-50 to-amber-100';
                                        break;

                                    case 'profile_photo_updated':
                                        $icon = 'fa-image';
                                        $color = 'from-purple-500 to-pink-600';
                                        $bgColor = 'bg-gradient-to-br from-purple-50 to-pink-100';
                                        break;

                                    case 'profile_submitted':
                                        $icon = 'fa-paper-plane';
                                        $color = 'from-indigo-500 to-blue-600';
                                        $bgColor = 'bg-gradient-to-br from-indigo-50 to-blue-100';
                                        break;

                                    case 'profile_approved':
                                        $icon = 'fa-badge-check';
                                        $color = 'from-emerald-500 to-green-600';
                                        $bgColor = 'bg-gradient-to-br from-emerald-50 to-green-100';
                                        break;

                                    case 'profile_rejected':
                                        $icon = 'fa-circle-xmark';
                                        $color = 'from-red-500 to-pink-600';
                                        $bgColor = 'bg-gradient-to-br from-red-50 to-pink-100';
                                        break;

                                    case 'document_uploaded':
                                    case (strpos($act['action_type'], 'uploaded_') === 0):
                                        $icon = 'fa-file-arrow-up';
                                        $color = 'from-blue-500 to-cyan-600';
                                        $bgColor = 'bg-gradient-to-br from-blue-50 to-cyan-100';

                                        // Specific document icons based on description
                                        if (str_contains($desc, 'coe') || str_contains($desc, 'certificate of employment')) {
                                            $icon = 'fa-file-contract';
                                            $color = 'from-cyan-500 to-blue-600';
                                            $bgColor = 'bg-gradient-to-br from-cyan-50 to-blue-100';
                                        } elseif (str_contains($desc, 'tor') || str_contains($desc, 'transcript')) {
                                            $icon = 'fa-file-lines';
                                            $color = 'from-indigo-500 to-purple-600';
                                            $bgColor = 'bg-gradient-to-br from-indigo-50 to-purple-100';
                                        } elseif (str_contains($desc, 'diploma')) {
                                            $icon = 'fa-graduation-cap';
                                            $color = 'from-emerald-500 to-green-600';
                                            $bgColor = 'bg-gradient-to-br from-emerald-50 to-green-100';
                                        } elseif (str_contains($desc, 'resume') || str_contains($desc, 'cv')) {
                                            $icon = 'fa-file-user';
                                            $color = 'from-purple-500 to-pink-600';
                                            $bgColor = 'bg-gradient-to-br from-purple-50 to-pink-100';
                                        } elseif (str_contains($desc, 'id') || str_contains($desc, 'identification') || str_contains($desc, 'valid id')) {
                                            $icon = 'fa-id-card';
                                            $color = 'from-amber-500 to-orange-600';
                                            $bgColor = 'bg-gradient-to-br from-amber-50 to-orange-100';
                                        } elseif (str_contains($desc, '2x2') || str_contains($desc, 'photo')) {
                                            $icon = 'fa-image';
                                            $color = 'from-pink-500 to-rose-600';
                                            $bgColor = 'bg-gradient-to-br from-pink-50 to-rose-100';
                                        }
                                        break;

                                    case 'document_deleted':
                                        $icon = 'fa-file-slash';
                                        $color = 'from-red-500 to-pink-600';
                                        $bgColor = 'bg-gradient-to-br from-red-50 to-pink-100';
                                        break;

                                    case 'login':
                                    case 'logged_in':
                                        $icon = 'fa-right-to-bracket';
                                        $color = 'from-gray-500 to-gray-600';
                                        $bgColor = 'bg-gradient-to-br from-gray-50 to-gray-100';
                                        break;

                                    default:
                                        $icon = 'fa-bell';
                                        $color = 'from-teal-500 to-cyan-600';
                                        $bgColor = 'bg-gradient-to-br from-teal-50 to-cyan-100';
                                }
                                ?>

                                <div class="flex items-start space-x-4 p-3 rounded-xl <?php echo $bgColor; ?> border border-white hover:shadow-md transition-all duration-300">
                                    <div class="w-10 h-10 bg-gradient-to-br <?php echo $color; ?> text-white flex items-center justify-center rounded-lg flex-shrink-0 mt-1 shadow-sm">
                                        <i class="fas <?= $icon ?> text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 leading-snug">
                                            <?= htmlspecialchars($act['description'] ?: ucwords(str_replace('_', ' ', $act['action_type']))) ?>
                                        </p>
                                        <p class="text-xs text-gray-600 font-medium mt-1">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?= date('M j, Y \a\t g:i A', strtotime($act['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-400 h-full flex items-center justify-center">
                            <div>
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-history text-2xl opacity-40"></i>
                                </div>
                                <p class="text-sm font-semibold">No recent activity</p>
                                <p class="text-xs mt-1">Your actions will appear here</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php $stmt_act->close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help & Support Modal -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white border border-gray-200 max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0 rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 to-green-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-question-circle mr-3"></i>
                    Help & Support
                </h3>
                <button id="closeHelpModal" class="text-white hover:text-gray-200 text-xl font-bold transition-colors duration-200 hover:scale-110">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="space-y-6 p-6 bg-white">
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col items-center p-4 bg-emerald-50 text-center border-2 border-emerald-100 rounded-xl hover:shadow-md transition-all duration-300">
                    <div class="bg-emerald-100 p-3 mb-3 rounded-lg">
                        <i class="fas fa-envelope text-emerald-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Email Support</h4>
                    <p class="text-xs text-gray-600 mt-1">main@jhcsc.edu.ph</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-blue-50 text-center border-2 border-blue-100 rounded-xl hover:shadow-md transition-all duration-300">
                    <div class="bg-blue-100 p-3 mb-3 rounded-lg">
                        <i class="fas fa-phone text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Phone Support</h4>
                    <p class="text-xs text-gray-600 mt-1">0948 954 7078 - BSIT Faculty</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-purple-50 text-center border-2 border-purple-100 rounded-xl hover:shadow-md transition-all duration-300">
                    <div class="bg-purple-100 p-3 mb-3 rounded-lg">
                        <i class="fas fa-clock text-purple-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Support Hours</h4>
                    <p class="text-xs text-gray-600 mt-1">Mon - Fri: 9AM - 5PM EST</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-amber-50 text-center border-2 border-amber-100 rounded-xl hover:shadow-md transition-all duration-300">
                    <div class="bg-amber-100 p-3 mb-3 rounded-lg">
                        <i class="fas fa-life-ring text-amber-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">FAQs & Guides</h4>
                    <p class="text-xs text-gray-600 mt-1">Visit our knowledge base</p>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3 -mx-6 -mb-6 mt-6 border-t border-gray-200">
                <button id="cancelHelp" class="px-6 py-3 text-gray-600 hover:text-gray-800 font-semibold transition-colors duration-200 rounded-lg hover:bg-gray-100">
                    Close
                </button>
                <a href="mailto:support@alumniportal.edu" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold transition-all duration-300 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
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
            card.style.transform = 'translateY(-8px)';
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
</style>
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>