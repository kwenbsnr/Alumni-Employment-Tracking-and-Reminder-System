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
        <div id="successCard" class="bg-gradient-to-r from-green-500 to-emerald-600 p-6 text-white flex items-center justify-between shadow-lg animate-fade-in">
            <div class="flex items-center space-x-4">
                <div class="w-10 h-10 bg-white bg-opacity-20 flex items-center justify-center rounded-lg">
                    <i class="fas fa-check text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Submission Successful!</h3>
                    <p class="text-green-100">Your profile has been submitted for review.</p>
                </div>
            </div>
            <button id="closeSuccessCard" class="text-white hover:text-green-200 text-xl font-bold transition-colors">×</button>
        </div>
        <?php unset($_SESSION['profile_submission_success']); ?>
    <?php endif; ?>

    <!-- Welcome Card -->
    <?php if (isset($_SESSION['show_welcome'])): ?>
        <div id="welcomeCard" class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold mb-2">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
                    <p class="text-blue-100 text-lg">Your network and resources are waiting. Check your quick stats below.</p>
                </div>
                <div class="w-16 h-16 bg-white bg-opacity-20 flex items-center justify-center rounded-xl">
                    <i class="fas fa-graduation-cap text-2xl"></i>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['show_welcome']); ?>
    <?php endif; ?>

<!-- MODERN DASHBOARD LAYOUT WITH SHARP EDGES -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
    <!-- LEFT: 4 Cards (2x2 Grid) -->
    <div class="lg:col-span-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            
            <!-- CARD 1: Profile Completion - MODERN DESIGN -->
            <div class="bg-white border-2 border-gray-100 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <!-- Status accent bar -->
                <div class="h-2 <?php
                    echo $profile_status === 'Complete' ? 'bg-gradient-to-r from-emerald-500 to-green-600' :
                         ($profile_status === 'Pending Approval' ? 'bg-gradient-to-r from-amber-500 to-orange-600' : 'bg-gradient-to-r from-orange-500 to-red-600');
                ?>"></div>
                
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 <?php
                                echo $profile_status === 'Complete' ? 'bg-emerald-100 text-emerald-600' :
                                     ($profile_status === 'Pending Approval' ? 'bg-amber-100 text-amber-600' : 'bg-orange-100 text-orange-600');
                            ?> flex items-center justify-center">
                                <i class="fas fa-user-check text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Profile Status</h3>
                                <span class="text-sm font-semibold <?php
                                    echo $profile_status === 'Complete' ? 'text-emerald-600' :
                                         ($profile_status === 'Pending Approval' ? 'text-amber-600' : 'text-orange-600');
                                ?>">
                                    <?php echo $profile_status; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                <?php
                                if ($profile_status === 'Complete') {
                                    echo 'Your profile is fully verified and complete.';
                                } elseif ($profile_status === 'Pending Approval') {
                                    echo 'Profile submitted and under administrative review.';
                                } else {
                                    echo 'Complete your profile to unlock all platform features.';
                                }
                                ?>
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between text-sm font-semibold text-gray-700">
                                <span>Completion Progress</span>
                                <span><?php echo $completion_percentage; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 h-3 relative">
                                <div class="absolute top-0 left-0 h-full <?php
                                    echo $completion_percentage >= 90 ? 'bg-gradient-to-r from-emerald-500 to-green-600' :
                                         ($completion_percentage >= 70 ? 'bg-gradient-to-r from-orange-500 to-amber-600' : 'bg-gradient-to-r from-red-500 to-orange-600');
                                ?> transition-all duration-1000 ease-out"
                                     style="width: <?php echo $completion_percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 2: Employment - MODERN DESIGN -->
            <div class="bg-white border-2 border-gray-100 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="h-2 <?php
                    echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                        ? 'bg-gradient-to-r from-blue-500 to-cyan-600' : 'bg-gradient-to-r from-gray-400 to-gray-500';
                ?>"></div>

                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 <?php
                                echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                    ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500';
                            ?> flex items-center justify-center">
                                <i class="fas fa-briefcase text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Employment</h3>
                                <span class="text-sm font-semibold <?php
                                    echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                        ? 'text-blue-600' : 'text-gray-500';
                                ?>">
                                    <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                        ? 'Current' : 'Not Set'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-lg font-bold text-gray-800 <?php
                                echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                    ? '' : 'italic text-gray-400';
                            ?>">
                                <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                    ? htmlspecialchars($profile_info['employment_status'])
                                    : 'No employment info'; ?>
                            </p>
                            <p class="text-sm <?php
                                echo !empty($profile_info['last_profile_update'])
                                    ? 'text-gray-500' : 'text-gray-400 italic';
                            ?>">
                                <?php echo !empty($profile_info['last_profile_update'])
                                    ? 'Updated ' . date('M d, Y', strtotime($profile_info['last_profile_update']))
                                    : 'Never updated'; ?>
                            </p>
                        </div>

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
                            <div class="flex items-center space-x-2 text-sm text-blue-600 font-medium">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo $msg; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CARD 3: Document Review - MODERN DESIGN -->
            <div class="bg-white border-2 border-gray-100 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="h-2 <?php
                    echo $document['submission_status'] === 'Approved' ? 'bg-gradient-to-r from-emerald-500 to-green-600' :
                         ($document['submission_status'] === 'Rejected' ? 'bg-gradient-to-r from-red-500 to-pink-600' :
                         ($document['submission_status'] === 'Under Review' ? 'bg-gradient-to-r from-amber-500 to-orange-600' : 'bg-gradient-to-r from-gray-400 to-gray-500'));
                ?>"></div>

                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 <?php
                                echo $document['submission_status'] === 'Approved' ? 'bg-emerald-100 text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500'));
                            ?> flex items-center justify-center">
                                <i class="fas fa-clipboard-check text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Document Review</h3>
                                <span class="text-sm font-semibold <?php
                                    echo $document['submission_status'] === 'Approved' ? 'text-emerald-600' :
                                         ($document['submission_status'] === 'Rejected' ? 'text-red-600' :
                                         ($document['submission_status'] === 'Under Review' ? 'text-amber-600' : 'text-gray-500'));
                                ?>">
                                    <?php echo htmlspecialchars($document['submission_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium <?php
                                echo $document['submission_status'] === 'Approved' ? 'text-emerald-700' :
                                     ($document['submission_status'] === 'Rejected' ? 'text-red-700' :
                                     ($document['submission_status'] === 'Under Review' ? 'text-amber-700' : 'text-gray-600'));
                            ?> leading-relaxed">
                                <?php echo htmlspecialchars($document['message']); ?>
                            </p>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div class="flex items-center space-x-2 text-sm font-semibold <?php
                                echo $document['submission_status'] === 'Approved' ? 'text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'text-amber-600' : 'text-gray-500'));
                            ?>">
                                <i class="fas fa-paperclip"></i>
                                <span>Files: <?php echo $document['document_count']; ?></span>
                            </div>

                            <div class="w-10 h-10 <?php
                                echo $document['submission_status'] === 'Approved' ? 'bg-emerald-100 text-emerald-600' :
                                     ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-600' :
                                     ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500'));
                            ?> flex items-center justify-center">
                                <?php if ($document['submission_status'] === 'Approved'): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($document['submission_status'] === 'Under Review'): ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php elseif ($document['submission_status'] === 'Rejected'): ?>
                                    <i class="fas fa-times"></i>
                                <?php else: ?>
                                    <i class="fas fa-question"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 4: Uploaded Documents - MODERN DESIGN -->
            <div class="bg-white border-2 border-gray-100 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="h-2 <?php
                    echo $document['document_count'] > 0 ? 'bg-gradient-to-r from-blue-500 to-cyan-600' : 'bg-gradient-to-r from-gray-400 to-gray-500';
                ?>"></div>

                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 <?php
                                echo $document['document_count'] > 0
                                    ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500';
                            ?> flex items-center justify-center">
                                <i class="fas fa-cloud-upload-alt text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Uploaded Documents</h3>
                                <span class="text-sm font-semibold <?php
                                    echo $document['document_count'] > 0
                                        ? 'text-blue-600' : 'text-gray-500';
                                ?>">
                                    <?php echo $document['document_count'] > 0 ? 'Active' : 'Empty'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold <?php echo $document['document_count'] > 0 ? 'text-blue-600' : 'text-gray-400'; ?>">
                                <?php echo $document['document_count']; ?>
                            </div>
                            <div class="text-xs <?php echo $document['document_count'] > 0 ? 'text-blue-500' : 'text-gray-400'; ?> uppercase font-semibold">
                                File<?php echo $document['document_count'] != 1 ? 's' : ''; ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium <?php
                            echo $document['document_count'] > 0
                                ? 'text-blue-700'
                                : 'text-gray-500 italic';
                        ?>">
                            <?php echo $document['document_count'] > 0
                                ? 'You have <strong>' . $document['document_count'] . '</strong> file' . ($document['document_count'] != 1 ? 's' : '') . ' ready.'
                                : 'No files uploaded yet. '; ?>
                        </p>

                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div class="flex items-center space-x-2 text-sm font-semibold <?php
                                echo $document['document_count'] > 0 ? 'text-emerald-600' : 'text-gray-500';
                            ?>">
                                <i class="fas fa-check-circle"></i>
                                <span>Ready: <?php echo $document['document_count'] > 0 ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="w-10 h-10 <?php echo $document['document_count'] > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-400'; ?> flex items-center justify-center">
                                <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-check' : 'fa-times'; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- RIGHT: Recent Activity Card - MODERN DESIGN -->
    <div class="bg-white border-2 border-gray-100 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 h-fit">
        <div class="h-2 bg-gradient-to-r from-indigo-500 to-purple-600"></div>

        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-indigo-100 text-indigo-600 flex items-center justify-center">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Recent Activity</h3>
                        <span class="text-sm font-semibold text-indigo-600">Last 30 Days</span>
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
                LIMIT 6
            ");
            $stmt_act->bind_param("i", $user_id);
            $stmt_act->execute();
            $activities = $stmt_act->get_result();
            ?>

            <?php if ($activities->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($act = $activities->fetch_assoc()): ?>
                        <?php
                        // Default icon & color
                        $icon  = 'fa-circle-check';
                        $color = 'text-green-500';
                        $desc  = strtolower($act['description'] ?? '');

                        // Smart icon mapping
                        switch ($act['action_type']) {
                            case 'profile_updated':
                            case 'profile_saved':
                                $icon = 'fa-user-pen';
                                $color = 'text-orange-500';
                                break;

                            case 'profile_photo_updated':
                                $icon = 'fa-image';
                                $color = 'text-purple-600';
                                break;

                            case 'profile_submitted':
                                $icon = 'fa-paper-plane';
                                $color = 'text-indigo-600';
                                break;

                            case 'profile_approved':
                                $icon = 'fa-badge-check';
                                $color = 'text-emerald-600';
                                break;

                            case 'profile_rejected':
                                $icon = 'fa-circle-xmark';
                                $color = 'text-red-600';
                                break;

                            case 'document_uploaded':
                            case (strpos($act['action_type'], 'uploaded_') === 0):
                                $icon = 'fa-file-arrow-up';
                                $color = 'text-blue-600';

                                // Specific document icons based on description
                                if (str_contains($desc, 'coe') || str_contains($desc, 'certificate of employment')) {
                                    $icon = 'fa-file-contract';
                                    $color = 'text-cyan-600';
                                } elseif (str_contains($desc, 'tor') || str_contains($desc, 'transcript')) {
                                    $icon = 'fa-file-lines';
                                    $color = 'text-indigo-600';
                                } elseif (str_contains($desc, 'diploma')) {
                                    $icon = 'fa-graduation-cap';
                                    $color = 'text-emerald-600';
                                } elseif (str_contains($desc, 'resume') || str_contains($desc, 'cv')) {
                                    $icon = 'fa-file-user';
                                    $color = 'text-purple-600';
                                } elseif (str_contains($desc, 'id') || str_contains($desc, 'identification') || str_contains($desc, 'valid id')) {
                                    $icon = 'fa-id-card';
                                    $color = 'text-amber-600';
                                } elseif (str_contains($desc, '2x2') || str_contains($desc, 'photo')) {
                                    $icon = 'fa-image';
                                    $color = 'text-pink-600';
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
                                $color = 'text-teal-600';
                        }
                        ?>

                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fas <?= $icon ?> <?= $color ?> text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 leading-snug">
                                    <?= htmlspecialchars($act['description'] ?: ucwords(str_replace('_', ' ', $act['action_type']))) ?>
                                </p>
                                <p class="text-xs text-gray-500 font-medium">
                                    <?= date('M j, Y \a\t g:i A', strtotime($act['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-history text-4xl mb-3 opacity-40"></i>
                    <p class="text-sm font-semibold">No recent activity</p>
                    <p class="text-xs mt-1">Your actions will appear here</p>
                </div>
            <?php endif; ?>

            <?php $stmt_act->close(); ?>

            <a href="activity_log.php" class="block text-center py-4 px-6 text-white text-sm font-bold tracking-wide bg-gradient-to-r from-indigo-600 to-purple-700 hover:from-indigo-700 hover:to-purple-800 transition-all duration-300 mt-6 flex items-center justify-center space-x-2">
                <span>View Full History</span>
                <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
            </a>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS - MODERN DESIGN -->
<div class="mt-8">
    <div class="bg-white border-2 border-gray-100 shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-8 py-6">
            <h3 class="text-xl font-bold text-white text-center">
                Quick Actions
            </h3>
        </div>

        <!-- Three full-stretch buttons -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-0 divide-x divide-gray-200">
            
            <!-- 1. View Profile -->
            <a href="alumni_profile.php" 
               class="group flex items-center justify-between px-8 py-6 hover:bg-gray-50 transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-indigo-100 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                        <i class="fas fa-id-card text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-gray-800">View Profile</p>
                        <p class="text-sm text-gray-600">See your complete info</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right text-indigo-500 group-hover:translate-x-2 transition-transform duration-300"></i>
            </a>

            <!-- 2. Update Profile -->
            <a href="alumni_profile.php?edit=1" 
               class="group flex items-center justify-between px-8 py-6 hover:bg-gray-50 transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                        <i class="fas fa-user-pen text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-gray-800">Update Profile</p>
                        <p class="text-sm text-gray-600">Edit personal & work info</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right text-purple-500 group-hover:translate-x-2 transition-transform duration-300"></i>
            </a>

            <!-- 3. Upload Files -->
            <a href="alumni_profile.php#documents" 
               class="group flex items-center justify-between px-8 py-6 hover:bg-gray-50 transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                        <i class="fas fa-cloud-upload-alt text-xl"></i>
                    </div>
                    <div>
                        <p class="font-bold text-gray-800">Upload Files</p>
                        <p class="text-sm text-gray-600">Add certificates & docs</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right text-emerald-500 group-hover:translate-x-2 transition-transform duration-300"></i>
            </a>

        </div>
    </div>
</div>

<!-- Help & Support Modal -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white border-2 border-gray-100 max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="bg-gradient-to-r from-green-600 to-emerald-700 p-6 text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-question-circle mr-3"></i>
                    Help & Support
                </h3>
                <button id="closeHelpModal" class="text-white hover:text-gray-200 text-xl font-bold transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="space-y-6 min-h-0 p-6 bg-white">
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col items-center p-4 bg-green-50 text-center border-2 border-green-100">
                    <div class="bg-green-100 p-3 mb-2">
                        <i class="fas fa-envelope text-green-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Email Support</h4>
                    <p class="text-xs text-gray-600">main@jhcsc.edu.ph</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-blue-50 text-center border-2 border-blue-100">
                    <div class="bg-blue-100 p-3 mb-2">
                        <i class="fas fa-phone text-blue-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Phone Support</h4>
                    <p class="text-xs text-gray-600">0948 954 7078 - BSIT Faculty</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-purple-50 text-center border-2 border-purple-100">
                    <div class="bg-purple-100 p-3 mb-2">
                        <i class="fas fa-clock text-purple-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">Support Hours</h4>
                    <p class="text-xs text-gray-600">Mon - Fri: 9AM - 5PM EST</p>
                </div>

                <div class="flex flex-col items-center p-4 bg-amber-50 text-center border-2 border-amber-100">
                    <div class="bg-amber-100 p-3 mb-2">
                        <i class="fas fa-life-ring text-amber-600 text-xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">FAQs & Guides</h4>
                    <p class="text-xs text-gray-600">Visit our knowledge base</p>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3 -mx-6 -mb-6 mt-6 border-t-2 border-gray-100">
                <button id="cancelHelp" class="px-6 py-2 text-gray-600 hover:text-gray-800 font-semibold transition-colors duration-200">
                    Close
                </button>
                <a href="mailto:support@alumniportal.edu" class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-semibold transition-all duration-300">
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
            welcomeCard.style.transition = 'opacity 1s ease-out';
            welcomeCard.style.opacity = '0';
            setTimeout(() => welcomeCard.remove(), 1000);
        }, 5000);
    }

    // Success Card Auto-Hide & Close Button
    const successCard = document.getElementById('successCard');
    const closeSuccessBtn = document.getElementById('closeSuccessCard');

    if (successCard) {
        // Auto-hide after 4 seconds
        const autoHide = setTimeout(() => {
            successCard.style.transition = 'opacity 0.6s ease-out';
            successCard.style.opacity = '0';
            setTimeout(() => successCard.remove(), 600);
        }, 4000);

        // Manual close cancels auto-hide
        if (closeSuccessBtn) {
            closeSuccessBtn.addEventListener('click', () => {
                clearTimeout(autoHide);
                successCard.style.transition = 'opacity 0.4s ease-out';
                successCard.style.opacity = '0';
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
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>