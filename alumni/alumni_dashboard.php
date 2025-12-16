<?php

session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Dashboard";
$active_page = "dashboard";
$user_id = $_SESSION["user_id"];

// ---- 1. UPDATED FETCH SQL QUERY ----
$stmt = $conn->prepare("
    SELECT 
        u.user_id, 
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.email, u.role,
        u.contact_number,     
        ap.photo_path, 
        ap.employment_status, 
        ap.last_profile_update,
        ap.submitted_at,
        u.citizenship,
        u.civil_status,
        aa.city, aa.state_province, aa.street, aa.country
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN alumni_address aa ON u.user_id = aa.user_id
    WHERE u.user_id = ?
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

// --- UPDATED PROFILE COMPLETION LOGIC ---
// Basic required fields that everyone needs
$has_basic_info = !empty($profile_info) && 
    !empty($profile_info['contact_number']) &&
    !empty($profile_info['employment_status']) &&
    !empty($profile_info['citizenship']) &&
    !empty($profile_info['civil_status']);

// Check worldwide address
$has_address = !empty($profile_info) && 
    !empty($profile_info['country']) && 
    !empty($profile_info['state_province']) && 
    !empty($profile_info['city']);

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

// --- NEW COMBINED CHECKLIST ITEMS FOR NEW REQUIREMENT ---
// This item represents the complete Personal Profile (Basic, Address, Photo)
$has_profile_management = $has_basic_info && $has_address && $has_photo;

// This item represents Employment status set AND documents uploaded (if required)
$has_employment_documents = !empty($profile_info['employment_status']) && $has_documents;


// Profile is complete when all required sections are filled
$is_profile_complete = $has_basic_info && $has_address && $has_photo && $has_documents;

// ---- DOCUMENT STATUS CALCULATION ----
$stmt_doc_status = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN document_status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN document_status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN document_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM alumni_documents 
    WHERE user_id = ?
");
$stmt_doc_status->bind_param("i", $user_id);
$stmt_doc_status->execute();
$doc_result = $stmt_doc_status->get_result();
$doc_data = $doc_result->fetch_assoc() ?: [];
$stmt_doc_status->close();

$total_docs = $doc_data['total'] ?? 0;
$approved_docs = $doc_data['approved'] ?? 0;
$pending_docs = $doc_data['pending'] ?? 0;
$rejected_docs = $doc_data['rejected'] ?? 0;

// Calculate overall document status
if ($total_docs === 0) {
    $document_status = 'No Documents';
    $document_message = 'Upload required documents';
} elseif ($rejected_docs > 0) {
    $document_status = 'Rejected';
    $document_message = 'Needs resubmission';
} elseif ($approved_docs === $total_docs) {
    $document_status = 'Approved';
    $document_message = 'All documents approved';
} elseif ($pending_docs > 0) {
    $document_status = 'Under Review';
    $document_message = 'Awaiting administrator review';
} else {
    $document_status = 'Submitted';
    $document_message = 'Ready for review';
}

// Use document status for profile status
$submission_status = $document_status;
$profile_status = 'Incomplete';

if ($document_status === 'Rejected') {
    $profile_status = 'Rejected';
    
    // FORCE DOCUMENTS TO BE UNCHECKED WHEN REJECTED (since rejection is usually document-related)
    if (!$is_unemployed) {
        $has_documents = false;
        $is_profile_complete = false;
    }
} elseif ($is_profile_complete) {
    if ($document_status === 'Approved') {
        $profile_status = 'Complete';
    } elseif ($document_status === 'Under Review' || $document_status === 'Submitted') {
        $profile_status = 'Pending Approval';
    } else {
        $profile_status = 'Ready to Submit';
    }
}

// Annual update check
$needs_semiannual_update = !empty($profile_info) &&
    ($profile_info['last_profile_update'] === null ||
     strtotime($profile_info['last_profile_update'] . ' +6 months') <= time());

$needs_profile_update = empty($profile_info) || !$is_profile_complete || $needs_semiannual_update;

// Profile & Document status arrays for display
$profile = [
    'employment_status' => $profile_info['employment_status'] ?? 'Not Set'
];

$document = [
    'submission_status' => $document_status,
    'message' => $document_message,
    'document_count' => $total_docs
];

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

// Fetch submission status for the new card
require_once dirname(__DIR__) . '/api/utils/deadline.php';
$submission_open = isEmploymentSubmissionOpen($conn);
$deadline_info = getAllDeadlineInfo($conn);

// Determine status text and styling for submission card (FOR THE NEW CARD)
$status_text = '';
$status_icon = '';
$status_color = '';
$bg_color_class = '';
$border_color_class = '';
$details_text = '';

if ($deadline_info['has_manual_override']) {
    $status_text = $submission_open ? "Open" : "Closed";
    $status_icon = $submission_open ? "fa-unlock" : "fa-lock";
    $status_color = $submission_open ? "text-green-800" : "text-red-800";
    $bg_color_class = $submission_open ? "bg-green-100 border-green-300" : "bg-red-100 border-red-300";
    $details_text = $submission_open ? "Submissions are currently OPEN for all alumni. Update your profile and employment information now." : "Submissions are currently CLOSED by the administrator. Please wait for further announcements or instructions before attempting to submit again.";
} else {
    $now = time();
    $open_ts = $deadline_info['open_date'] ? strtotime($deadline_info['open_date']) : 0;
    $close_ts = $deadline_info['close_date'] ? strtotime($deadline_info['close_date']) : 0;
    
    if ($open_ts > $now) {
        $status_text = "Scheduled";
        $status_icon = "fa-clock";
        $status_color = "text-amber-800";
        $bg_color_class = "bg-amber-100 border-amber-300";
        $details_text = "Submissions will open on ". date('M j, Y \a\t g:i A', $open_ts) . ". Please check back at the scheduled time to submit your information.";
    } elseif ($close_ts < $now) {
        $status_text = "Closed";
        $status_icon = "fa-calendar-times";
        $status_color = "text-red-800";
        $bg_color_class = "bg-red-100 border-red-300";
        $details_text = "The deadline for submission was ". date('M j, Y \a\t g:i A', $close_ts) . ". Submissions are no longer being accepted.";
    } else {
        $status_text = "Open";
        $status_icon = "fa-calendar-check";
        $status_color = "text-green-800";
        $bg_color_class = "bg-green-100 border-green-300";
        $details_text = "Submissions are open until " . date('M j, Y \a\t g:i A', $close_ts) . ". Please complete profile update before the deadline.";
    }
}


ob_start();
?>
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

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
            <div class="xl:col-span-3 space-y-4">
                
                <div class="p-5 rounded-xl shadow-lg border-2 border-dashed transition-all duration-500 hover:shadow-xl <?php echo $bg_color_class; ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i class="fas <?php echo $status_icon; ?> <?php echo $status_color; ?> text-2xl"></i>
                            <div>
                                <h3 class="text-lg font-extrabold text-gray-900 leading-none">Submission Period Status</h3>
                                <span class="inline-block mt-1 font-extrabold text-sm uppercase tracking-wider px-3 py-1 rounded-full shadow-inner 
                                    <?php
                                        if ($status_text === 'Open') {
                                            echo 'text-green-800 bg-green-200 border border-green-400';
                                        } elseif ($status_text === 'Closed') {
                                            echo 'text-red-800 bg-red-200 border border-red-400';
                                        } else { // Scheduled
                                            echo 'text-amber-800 bg-amber-200 border border-amber-400';
                                        }
                                    ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-gray-200">
                         <p class="text-sm text-gray-700 font-medium">
                            <?php echo $details_text; ?>
                        </p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-b-4 border-indigo-300 overflow-hidden transition-all duration-500 hover:shadow-xl">
                    
                    <div class="p-6 pb-4 bg-gradient-to-r from-indigo-50 to-purple-50 relative overflow-hidden">
                        <div class="absolute inset-0 opacity-20 bg-gradient-to-br from-white to-indigo-100"></div>
                        <div class="relative z-10 flex items-start justify-between">
                            
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center shadow-xl transform rotate-[-5deg] 
                                    <?php
                                        $iconClass = 'fas ';
                                        if ($profile_status === 'Complete') {
                                            echo 'bg-gradient-to-br from-emerald-500 to-green-600 text-white';
                                            $iconClass .= 'fa-medal';
                                        } elseif ($profile_status === 'Pending Approval') {
                                            echo 'bg-gradient-to-br from-amber-500 to-orange-500 text-white';
                                            $iconClass .= 'fa-hourglass-half';
                                        } elseif ($profile_status === 'Rejected') {
                                            echo 'bg-gradient-to-br from-red-500 to-pink-500 text-white';
                                            $iconClass .= 'fa-triangle-exclamation';
                                        } else {
                                            echo 'bg-gradient-to-br from-indigo-500 to-blue-600 text-white';
                                            $iconClass .= 'fa-user-pen';
                                        }
                                    ?>">
                                    <i class="<?= $iconClass ?> text-2xl"></i> 
                                </div>
                                
                                <div>
                                    <h3 class="text-2xl font-extrabold text-indigo-900 leading-none">Your Profile Status</h3> 
                                    <span class="inline-block mt-1 font-extrabold text-sm uppercase tracking-wider px-3 py-1 rounded-full shadow-inner 
                                        <?php
                                            if ($profile_status === 'Complete') {
                                                echo 'text-emerald-800 bg-emerald-100 border border-emerald-300';
                                            } elseif ($profile_status === 'Pending Approval') {
                                                echo 'text-amber-800 bg-amber-100 border border-amber-300';
                                            } elseif ($profile_status === 'Rejected') {
                                                echo 'text-red-800 bg-red-100 border border-red-300';
                                            } else {
                                                echo 'text-gray-800 bg-gray-100 border border-gray-300';
                                            }
                                        ?>">
                                        <?php echo $profile_status; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($profile_status === 'Ready to Submit'): ?>
                                <button class="mt-2 text-sm bg-gradient-to-r from-indigo-600 to-blue-700 hover:from-indigo-700 hover:to-blue-800 text-white px-4 py-2 rounded-xl font-bold shadow-lg transform hover:scale-105 transition duration-300 animate-pulse flex items-center gap-2">
                                    <i class="fas fa-paper-plane"></i> Submit Profile
                                </button>
                            <?php endif; ?>
                            
                        </div>
                        
                        <div class="mt-4 pt-3 border-t border-indigo-100 relative z-10">
                             <p class="text-sm text-indigo-800 font-medium">
                                <?php
                                echo $profile_status === 'Complete' ? 'Fantastic! Your profile is complete and verified. Enjoy full access to all alumni features.'
                                            : ($profile_status === 'Pending Approval' ? 'Review in Progress: Your submission is currently being reviewed by the administrator.'
                                            : ($profile_status === 'Rejected' ? 'Urgent: Please review the rejection feedback in the Documents section and resubmit.'
                                            : 'Action Required: Complete the checklist below and click "Submit Profile" to begin the verification process.'));
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 border-dashed">Key Information Summary</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            
                            <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-200 shadow-md transition duration-300 hover:shadow-lg hover:border-indigo-300">
                                <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1 flex items-center gap-2">
                                    <i class="fas fa-briefcase text-sm"></i> Employment Status
                                </p>
                                <span class="text-xl font-extrabold text-indigo-900 block">
                                    <?php echo $profile_info['employment_status'] ?? 'Not Set'; ?>
                                </span>
                                <p class="text-xs text-gray-600 mt-1 truncate">
                                    <?php 
                                        $empStatusDisplay = $profile_info['employment_status'] ?? 'Not specified';
                                        if ($empStatusDisplay === 'Employed') $empStatusDisplay = 'Currently working for an organization';
                                        if ($empStatusDisplay === 'Self-Employed') $empStatusDisplay = 'Running own business or freelance work';
                                        if ($empStatusDisplay === 'Student') $empStatusDisplay = 'Currently enrolled in higher education';
                                        if ($empStatusDisplay === 'Unemployed') $empStatusDisplay = 'Currently seeking employment';
                                        echo $empStatusDisplay; 
                                    ?>
                                </p>
                            </div>

                            <!-- Document Status Card -->
                            <div class="p-4 rounded-xl shadow-md transition duration-300 hover:shadow-lg 
                                <?php 
                                    if ($document_status === 'Approved') {
                                        echo 'bg-emerald-50 border border-emerald-200 hover:border-emerald-300';
                                        $docTextClass = 'text-emerald-700';
                                        $docIcon = 'fa-check-circle';
                                        $docMessage = 'All documents approved and verified';
                                    } elseif ($document_status === 'Rejected') {
                                        echo 'bg-red-50 border border-red-200 hover:border-red-300';
                                        $docTextClass = 'text-red-700';
                                        $docIcon = 'fa-times-circle';
                                        
                                        // Fetch rejection reason if any document is rejected
                                        $rejection_stmt = $conn->prepare("SELECT rejection_reason FROM alumni_documents WHERE user_id = ? AND document_status = 'Rejected' LIMIT 1");
                                        $rejection_stmt->bind_param("i", $user_id);
                                        $rejection_stmt->execute();
                                        $rejection_result = $rejection_stmt->get_result();
                                        $rejection_data = $rejection_result->fetch_assoc();
                                        $rejection_stmt->close();
                                        
                                        $rejection_reason = $rejection_data['rejection_reason'] ?? 'No reason provided';
                                        $docMessage = 'Rejected: ' . htmlspecialchars($rejection_reason);
                                    } elseif ($document_status === 'No Documents') {
                                        echo 'bg-gray-50 border border-gray-200 hover:border-gray-300';
                                        $docTextClass = 'text-gray-700';
                                        $docIcon = 'fa-file-circle-question';
                                        $docMessage = 'Upload required documents';
                                    } else {
                                        echo 'bg-amber-50 border border-amber-200 hover:border-amber-300';
                                        $docTextClass = 'text-amber-700';
                                        $docIcon = 'fa-clock';
                                        $docMessage = 'Awaiting administrator review';
                                    }
                                ?>">
                                <p class="text-xs font-semibold <?= $docTextClass ?> uppercase tracking-wider mb-1 flex items-center gap-2">
                                    <i class="fas <?= $docIcon ?> text-sm"></i> Document Review
                                </p>
                                <span class="text-xl font-extrabold <?= $docTextClass ?> block">
                                    <?php echo $document_status; ?>
                                </span>
                                <p class="text-xs text-gray-600 mt-1 truncate">
                                    <?php echo $document['document_count']; ?> file<?php echo $document['document_count'] != 1 ? 's' : ''; ?> uploaded
                                </p>
                                <?php if ($document_status === 'Rejected' && isset($rejection_reason)): ?>
                                    <div class="mt-2 p-2 bg-red-100 border border-red-200 rounded-lg">
                                        <p class="text-xs font-semibold text-red-800 mb-1">Rejection Reason:</p>
                                        <p class="text-xs text-red-700 whitespace-pre-wrap"><?php echo htmlspecialchars($rejection_reason); ?></p>
                                    </div>
                                <?php elseif ($document_status === 'Approved'): ?>
                                    <div class="mt-2 p-2 bg-emerald-100 border border-emerald-200 rounded-lg">
                                        <p class="text-xs font-semibold text-emerald-800 mb-1">Status:</p>
                                        <p class="text-xs text-emerald-700">Your documents have been approved successfully!</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 rounded-xl shadow-md transition duration-300 hover:shadow-lg 
                                <?php 
                                    if ($needs_semiannual_update) {
                                        echo 'bg-red-50 border border-red-200 hover:border-red-300';
                                        $updateTextClass = 'text-red-600';
                                        $updateMsg = 'Update overdue (6 months). Please update now.';
                                    } else {
                                        echo 'bg-green-50 border border-green-200 hover:border-green-300';
                                        $updateTextClass = 'text-green-800';
                                        $updateMsg = 'Up-to-date. Keep your information current.';
                                    }
                                ?>">
                                <p class="text-xs font-semibold <?= $updateTextClass ?> uppercase tracking-wider mb-1 flex items-center gap-2">
                                    <i class="fas fa-calendar-check text-sm"></i> Last Updated
                                </p>
                                <span class="text-xl font-extrabold <?= $updateTextClass ?> block">
                                    <?php 
                                        if (!empty($profile_info['last_profile_update'])) {
                                            echo date('M d, Y', strtotime($profile_info['last_profile_update']));
                                        } else {
                                            echo 'Never';
                                        }
                                    ?>
                                </span>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?php echo $needs_semiannual_update ? 'Update overdue (6 months). Please update now.' : 'Up-to-date. Keep your information current.'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-indigo-50 rounded-b-xl border-t border-indigo-200">
                        <h4 class="text-lg font-bold text-indigo-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-list-check text-indigo-600"></i> Verification Checklist
                        </h4>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4 text-sm">
                            
                            <?php 
                            function renderChecklistItem($condition, $label) {
                                $icon = $condition ? 'fa-check-circle' : 'fa-circle-dot';
                                $color = $condition ? 'text-emerald-600' : 'text-gray-500';
                                $borderColor = $condition ? 'border-emerald-500' : 'border-indigo-300';
                                
                                // Used bg-white for list items to contrast with the indigo-50 footer, 
                                // and a strong border-2 to match the activity list item design language.
                                echo "<div class='flex items-center p-3 bg-white rounded-lg border-2 $borderColor shadow-md transition-all duration-300 hover:shadow-lg hover:scale-[1.02]'>";
                                echo "<i class='fas $icon $color text-lg mr-3'></i>";
                                echo "<span class='text-xs font-semibold text-gray-700'>$label</span>";
                                echo "</div>";
                            }
                            ?>

                            <?php 
                            // 1. Profile Management (Requires Basic Info, Address, and Photo)
                            renderChecklistItem($has_profile_management, 'Profile Management (Personal, Contact, Photo)'); 
                            
                            // 2. Employment Information & Documents (Requires employment status and documents upload if not 'Unemployed')
                            renderChecklistItem($has_employment_documents, 'Employment Information & Documents'); 
                            ?>

                        </div>
                    </div>
                    
                    </div>
            </div>

            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-b-4 border-indigo-300 overflow-hidden h-full flex flex-col transition-shadow duration-500 hover:shadow-xl">
                    <div class="p-5 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                              
                                <div>
                                    <h3 class="text-lg font-extrabold text-indigo-800">Recent Activity</h3>
                                    <span class="text-xs font-bold text-purple-700 bg-purple-100 px-2 py-1 rounded-full border border-purple-200 shadow-sm">Last 30 Days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 p-5 overflow-hidden">
                        <?php if ($activities->num_rows > 0): ?>
                            <div class="space-y-3 h-96 overflow-y-auto pr-2">
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
                            <div class="text-center py-10 text-gray-400 h-96 flex items-center justify-center">
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

<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
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
<div class="space-y-6 min-h-0 p-6 bg-white rounded-2xl shadow-xl">
    <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col items-center p-3 bg-green-50 rounded-lg text-center border border-green-200 hover:shadow-md transition-shadow duration-300">
            <div class="bg-green-100 p-2 rounded-full mb-1">
                <i class="fas fa-envelope text-green-600 text-lg"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm whitespace-nowrap">Email Support</h4>
            <p class="text-xs text-gray-600 truncate max-w-full">alumtrak@gmail.com</p>
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
    -ms-overflow-style: none;  
    scrollbar-width: none;  
}

* {
    transition-property: color, background-color, border-color, transform, box-shadow;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

button:focus, a:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

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