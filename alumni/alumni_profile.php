<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];
$page_title = "Profile Management";
$active_page = "profile";

// Fetch profile data WITH WORLDWIDE ADDRESS 
$stmt = $conn->prepare("
    SELECT 
        u.user_id, u.email, u.student_id, u.date_of_birth, u.gender, u.program, 
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.batch_year,
        u.first_name, u.middle_name, u.last_name, u.suffix,
        ap.contact_number, 
        ap.employment_status, ap.photo_path,
        ap.submission_status, ap.last_profile_update, ap.rejection_reason,
        wa.city, wa.state_province, wa.country, wa.latitude, wa.longitude, wa.formatted_address
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN worldwide_address wa ON u.user_id = wa.user_id  -- Changed join condition
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Fetch employment info
$stmt = $conn->prepare("SELECT ei.*, jt.title AS job_title, ei.business_type FROM employment_info ei LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id WHERE ei.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employment = $result->fetch_assoc() ?: [];
$stmt->close();

// Process business_type for display
$business_type = $employment['business_type'] ?? '';
$business_type_other = '';
if (strpos($business_type, 'Others: ') === 0) {
    $business_type_other = substr($business_type, 8);
    $business_type = 'Others (Please specify)';
}

// Fetch education info
$stmt = $conn->prepare("SELECT * FROM education_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$education = $result->fetch_assoc() ?: [];
$stmt->close();

// Fetch documents
$stmt = $conn->prepare("SELECT document_type, file_path FROM alumni_documents WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$docs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// FIXED: Profile permissions logic
$submission_status = $profile['submission_status'] ?? null;
$last_profile_update = $profile['last_profile_update'] ?? null;

// Enhanced permission logic
$is_profile_new = empty($profile) || $submission_status === null;
$is_profile_rejected = !empty($profile) && $submission_status === 'Rejected';
$is_profile_approved = !empty($profile) && $submission_status === 'Approved';
$is_profile_pending = !empty($profile) && $submission_status === 'Pending';

// FIXED: Check if profile has personal data for display - UPDATED criteria
$has_personal_data = !empty($profile) && (!empty($profile['contact_number']) || !empty($profile['employment_status']));
// === CHECK SUBMISSION STATUS FROM DATABASE (GLOBAL CONTROL) ===
$submission_open = false;
$statusCheck = $conn->query("SELECT is_open FROM submission_status LIMIT 1");
if ($statusCheck && $row = $statusCheck->fetch_assoc()) {
    $submission_open = (bool)$row['is_open'];
}

// Semiannual update allowed only if previously approved and 6 months passed
$can_update_semiannual = $is_profile_approved && (
    $last_profile_update === null || 
    strtotime($last_profile_update . ' +6 months') <= time()
);

// User can update ONLY if:
// 1. Submissions are globally OPEN, AND
// 2. (It's a new profile OR rejected OR pending OR semiannual update is due)
$can_update = $submission_open && (
    $is_profile_new || 
    $is_profile_rejected || 
    $is_profile_pending || 
    $can_update_semiannual
);

// FIXED: Auto-modal opening logic - only open when there's data to edit
$auto_open_modal = isset($_SESSION['profile_rejected']) && $_SESSION['profile_rejected'] && $has_personal_data;
if ($auto_open_modal) {
    unset($_SESSION['profile_rejected']);
}

ob_start();
?>

<!-- Enhanced Success/Error Messages with Better Styling -->
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 relative" role="alert">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <strong class="font-bold">Success! </strong>
            <span class="block sm:inline ml-1"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 relative" role="alert">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <strong class="font-bold">Error! </strong>
            <span class="block sm:inline ml-1"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
<?php endif; ?>

<!-- Clear URL parameters without page reload -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove success/error parameters from URL without reloading
    const url = new URL(window.location);
    if (url.searchParams.has('success') || url.searchParams.has('error')) {
        url.searchParams.delete('success');
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    }
});
</script>

<div class="space-y-6 mt-3 mb-5">
<!-- Status Card - Modern, Professional & Perfectly Balanced (2025 Design) -->

<div id="updateProfileBtn" class="

    <?php

    if ($is_profile_rejected) {

        echo 'bg-red-50 border-red-200 hover:border-red-300 shadow-sm hover:shadow-md cursor-pointer';

    } elseif ($is_profile_approved) {

        echo 'bg-emerald-50 border-emerald-200 cursor-not-allowed opacity-95';

    } elseif ($is_profile_pending) {

        echo 'bg-amber-50 border-amber-200 cursor-not-allowed opacity-95';

    } elseif ($can_update || $is_profile_new) {

        echo 'bg-gradient-to-br from-green-50 to-emerald-50 border-green-300 hover:border-green-400 shadow-sm hover:shadow-lg cursor-pointer';

    } else {

        echo 'bg-gray-50 border-gray-300 cursor-not-allowed opacity-80';

    }

    ?>

    rounded-2xl p-5 transition-all duration-300 border-2 border-t-[6px]

    <?php

    if ($is_profile_rejected) echo 'border-t-red-500';

    elseif ($is_profile_approved) echo 'border-t-emerald-500';

    elseif ($is_profile_pending) echo 'border-t-amber-500';

    elseif ($can_update || $is_profile_new) echo 'border-t-green-500';

    else echo 'border-t-gray-400';

    ?>

">

    <!-- Header: Icon + Title -->

    <div class="flex items-center justify-between mb-3">

        <div class="flex items-center gap-3">

            <i class="fas text-2xl

                <?php

                if ($is_profile_rejected) echo 'fa-exclamation-triangle text-red-600';

                elseif ($is_profile_approved) echo 'fa-check-circle text-emerald-600';

                elseif ($is_profile_pending) echo 'fa-clock text-amber-600';

                elseif ($can_update || $is_profile_new) echo 'fa-user-edit text-green-600';

                else echo 'fa-lock text-gray-500';

                ?>

            "></i>

            <h3 class="text-lg font-bold tracking-tight

                <?php

                if ($is_profile_rejected) echo 'text-red-900';

                elseif ($is_profile_approved) echo 'text-emerald-900';

                elseif ($is_profile_pending) echo 'text-amber-900';

                elseif ($can_update || $is_profile_new) echo 'text-green-900';

                else echo 'text-gray-700';

                ?>">

                <?php

                if ($is_profile_rejected) echo 'Profile Rejected';

                elseif ($is_profile_approved) echo 'Profile Approved';

                elseif ($is_profile_pending) echo 'Pending Review';

                elseif ($is_profile_new) echo 'Create Your Profile';

                elseif ($can_update) echo 'Update Profile';

                else echo 'Editing Locked';

                ?>

            </h3>

        </div>

        <?php if ($is_profile_rejected || $can_update || $is_profile_new): ?>

            <i class="fas fa-arrow-right text-lg <?php echo $is_profile_rejected ? 'text-red-500' : 'text-green-600'; ?> opacity-80"></i>

        <?php endif; ?>

    </div>

    <!-- Status-Specific Message (Clean & Modern) -->

    <?php if ($is_profile_rejected): ?>

        <div class="bg-red-100 rounded-xl px-4 py-3 border border-red-200">

            <p class="text-sm font-semibold text-red-900">Your profile was rejected</p>

            <?php if (!empty($profile['rejection_reason'])): ?>

                <p class="text-xs text-red-700 mt-1 leading-relaxed">

                    <span class="font-medium">Reason:</span> <?php echo htmlspecialchars($profile['rejection_reason']); ?>

                </p>

            <?php endif; ?>

            <p class="text-xs font-medium text-red-800 mt-2">Please fix the issues and resubmit</p>

        </div>

    <?php elseif ($is_profile_pending): ?>

        <div class="bg-amber-100 rounded-xl px-4 py-3 border border-amber-200">

            <p class="text-sm font-medium text-amber-900 flex items-center gap-2">

                <i class="fas fa-spinner fa-pulse text-amber-600"></i>

                Your profile is under review

            </p>

            <p class="text-xs text-amber-700 mt-2">We'll notify you via email once approved (usually within 24-48 hours)</p>

        </div>

    <?php elseif ($is_profile_new): ?>

        <div class="bg-blue-50 rounded-xl px-4 py-3 border border-blue-200">

            <p class="text-sm font-medium text-blue-900">Complete your profile to get verified</p>

            <p class="text-xs text-blue-700 mt-1">Unlock full access and visibility</p>

        </div>

    <?php elseif (!$submission_open): ?>

        <div class="bg-gray-100 rounded-xl px-4 py-2.5">

            <p class="text-xs font-medium text-gray-700 text-center">Profile updates are temporarily closed by admin</p>

        </div>

    <?php elseif ($is_profile_approved): ?>

        <?php

        $last_update = $profile['last_profile_update'] ?? null;

        $next_update = $last_update ? date('F j, Y', strtotime($last_update . ' +6 months')) : 'six months from approval';

        ?>

        <p class="text-sm text-emerald-800 font-medium">

            Approved ✓ — Next update available: <span class="font-bold"><?php echo $next_update; ?></span>

        </p>

    <?php endif; ?>

 <!-- Action Button - Only when editable (Prominent & Modern) -->
<?php if ($is_profile_rejected || $can_update || $is_profile_new): ?>
    <div class="mt-5">
        <button type="button" class="w-full <?php
            echo $is_profile_rejected ? 'bg-red-600 hover:bg-red-700 focus:ring-red-300' : 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-300';
        ?> text-white font-semibold text-base py-4 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-100">
            <i class="fas <?php echo $is_profile_rejected ? 'fa-tools' : 'fa-edit'; ?> text-lg"></i>
            <span class="tracking-tight">
                <?php echo $is_profile_rejected ? 'Edit & Resubmit Profile' : ($is_profile_new ? 'Create My Profile Now' : 'Update Profile Information'); ?>
            </span>
        </button>
        <p class="text-center text-xs text-gray-500 mt-3 font-medium">
            <?php echo $is_profile_rejected ? 'Make corrections to get approved quickly' : 'Keep your information up to date'; ?>
        </p>
    </div>
<?php endif; ?>

</div>
    <!-- FIXED: Show profile cards only when personal data exists -->
    <?php if ($has_personal_data): ?>
        <!-- Consistent 2x2 Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">         
          <!-- Personal Information Card -->
          <div class="bg-white rounded-xl shadow-lg border-l-4 border-blue-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:bg-blue-50 flex flex-col h-full">
            <div class="p-6 flex-1">
              <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                  <i class="fas fa-user text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Personal Information</h3>
              </div>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Name</dt>
                  <dd class="font-semibold text-gray-700 text-base">
                    <?php echo !empty($profile['official_name']) ? htmlspecialchars($profile['official_name'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                  </dd>
                </div>
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Email</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></dd>
                </div>
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Contact Number</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['contact_number'] ?? 'N/A'); ?></dd>
                </div>
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Program</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['program'] ?? 'N/A'); ?></dd>
                </div>
              </dl>
            </div>
            <div class="p-4 bg-blue-50 border-t border-blue-100 rounded-b-xl">
              <p class="text-xs text-blue-600 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                Basic identification information
              </p>
            </div>
          </div>

          <!-- Address Information Card -->
          <div class="bg-white rounded-xl shadow-lg border-l-4 border-green-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:bg-green-50 flex flex-col h-full">
            <div class="p-6 flex-1">
              <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                  <i class="fas fa-map-marker-alt text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Address Information</h3>
              </div>
              
              <?php if (!empty($profile['city']) || !empty($profile['formatted_address'])): ?>
                <div class="space-y-4">
                  <!-- Address Summary -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <p class="font-medium text-gray-700 mb-2">
                      <i class="fas fa-map-signs text-green-500 mr-2"></i>
                      Complete Address
                    </p>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($profile['formatted_address'] ?? 'N/A'); ?></p>
                  </div>
                  
                  <!-- Location Details -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">City</p>
                      <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['city'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">State/Province</p>
                      <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['state_province'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Country</p>
                      <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['country'] ?? 'N/A'); ?></p>
                    </div>
                  </div>
                  
                  <!-- Coordinates -->
                  <?php if (!empty($profile['latitude']) && !empty($profile['longitude'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <p class="text-xs font-semibold text-blue-500 uppercase tracking-wide mb-1">Latitude</p>
                        <p class="text-blue-700 font-mono font-medium"><?php echo htmlspecialchars($profile['latitude']); ?></p>
                      </div>
                      <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <p class="text-xs font-semibold text-blue-500 uppercase tracking-wide mb-1">Longitude</p>
                        <p class="text-blue-700 font-mono font-medium"><?php echo htmlspecialchars($profile['longitude']); ?></p>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="text-center py-6">
                  <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-map-marker-alt text-gray-400 text-2xl"></i>
                  </div>
                  <p class="text-gray-500 font-medium">No address information</p>
                  <p class="text-gray-400 text-sm mt-1">Update your profile to add address details</p>
                </div>
              <?php endif; ?>
            </div>
            <div class="p-4 bg-green-50 border-t border-green-100 rounded-b-xl">
              <p class="text-xs text-green-600 flex items-center">
                <i class="fas fa-globe-americas mr-2"></i>
                Worldwide location details
              </p>
            </div>
          </div>

          <!-- Employment/Academic Details Card -->
          <div class="bg-white rounded-xl shadow-lg border-l-4 border-purple-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:bg-purple-50 flex flex-col h-full">
            <div class="p-6 flex-1">
              <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                  <i class="fas fa-briefcase text-purple-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Employment/Academic Details</h3>
              </div>
              
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Employment Status -->
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Employment Status</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['employment_status'] ?? 'Not Set'); ?></dd>
                </div>

                <?php
                $current_status = $profile['employment_status'] ?? '';
                
                if (in_array($current_status, ['Employed', 'Self-Employed', 'Employed & Student'])): 
                  if ($current_status !== 'Self-Employed'): ?>
                    <!-- Employed Details -->
                    <div class="space-y-1">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Job Title</dt>
                      <dd class="font-semibold text-gray-700 text-base">
                        <?php echo !empty($employment['job_title']) ? htmlspecialchars($employment['job_title'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                      </dd>
                    </div>
                    <div class="space-y-1">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Company Name</dt>
                      <dd class="font-semibold text-gray-700 text-base">
                        <?php echo !empty($employment['company_name']) ? htmlspecialchars($employment['company_name'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                      </dd>
                    </div>
                    <div class="space-y-1 md:col-span-2">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Company Address</dt>
                      <dd class="font-semibold text-gray-700 text-base">
                        <?php echo !empty($employment['company_address']) ? htmlspecialchars($employment['company_address'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                      </dd>
                    </div>
                    <div class="space-y-1">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Salary Range</dt>
                      <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></dd>
                    </div>
                  <?php else: ?>
                    <!-- Self-Employed Details -->
                    <div class="space-y-1">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Business Type</dt>
                      <dd class="font-semibold text-gray-700 text-base">
                        <?php
                        $display_business_type = $employment['business_type'] ?? 'N/A';
                        if (strpos($display_business_type, 'Others: ') === 0) {
                          $display_business_type = 'Others: ' . substr($display_business_type, 8);
                        }
                        echo !empty($display_business_type) ? htmlspecialchars($display_business_type, ENT_QUOTES, 'UTF-8') : 'N/A';
                        ?>
                      </dd>
                    </div>
                    <div class="space-y-1">
                      <dt class="font-medium text-gray-500 text-sm mb-1">Monthly Income Range</dt>
                      <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></dd>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>

                <?php if (in_array($current_status, ['Student', 'Employed & Student'])): ?>
                  <!-- Student Details -->
                  <div class="space-y-1">
                    <dt class="font-medium text-gray-500 text-sm mb-1">School Name</dt>
                    <dd class="font-semibold text-gray-700 text-base">
                      <?php echo !empty($education['school_name']) ? htmlspecialchars($education['school_name'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                    </dd>
                  </div>
                  <div class="space-y-1">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Degree Pursued</dt>
                    <dd class="font-semibold text-gray-700 text-base">
                      <?php echo !empty($education['degree_pursued']) ? htmlspecialchars($education['degree_pursued'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                    </dd>
                  </div>
                  <div class="space-y-1">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Start Year</dt>
                    <dd class="font-semibold text-gray-700 text-base">
                      <?php echo !empty($education['start_year']) ? htmlspecialchars($education['start_year']) : 'N/A'; ?>
                    </dd>
                  </div>
                  <div class="space-y-1">
                    <dt class="font-medium text-gray-500 text-sm mb-1">End Year (Expected)</dt>
                    <dd class="font-semibold text-gray-700 text-base">
                      <?php echo !empty($education['end_year']) ? htmlspecialchars($education['end_year']) : 'N/A'; ?>
                    </dd>
                  </div>
                <?php endif; ?>

                <?php if ($current_status === 'Unemployed'): ?>
                  <div class="md:col-span-2 text-center py-4">
                    <div class="bg-gray-100 rounded-lg p-4">
                      <i class="fas fa-user-clock text-gray-400 text-2xl mb-2"></i>
                      <p class="text-gray-600 font-medium">Currently seeking employment</p>
                    </div>
                  </div>
                <?php endif; ?>
              </dl>
            </div>
            <div class="p-4 bg-purple-50 border-t border-purple-100 rounded-b-xl">
              <p class="text-xs text-purple-600 flex items-center">
                <i class="fas fa-chart-line mr-2"></i>
                Professional and educational background
              </p>
            </div>
          </div>

          <!-- Documents Card -->
          <div class="bg-white rounded-xl shadow-lg border-l-4 border-orange-500 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:bg-orange-50 flex flex-col h-full">
            <div class="p-6 flex-1">
              <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                  <i class="fas fa-file-alt text-orange-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Documents</h3>
              </div>
              
              <?php if (empty($docs)): ?>
                <div class="text-center py-8">
                  <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-folder-open text-gray-400 text-2xl"></i>
                  </div>
                  <p class="text-gray-500 font-medium">No documents uploaded</p>
                  <p class="text-gray-400 text-sm mt-1">Documents will appear here after submission</p>
                </div>
              <?php else: ?>
                <div class="space-y-3">
                  <?php
                  $doc_icons = [
                    'COE' => ['icon' => 'fa-file-certificate', 'color' => 'text-red-500', 'bg' => 'bg-red-50', 'border' => 'border-red-200'],
                    'B_CERT' => ['icon' => 'fa-file-contract', 'color' => 'text-green-500', 'bg' => 'bg-green-50', 'border' => 'border-green-200'],
                    'COR' => ['icon' => 'fa-file-contract', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50', 'border' => 'border-blue-200']
                  ];
                  
                  foreach ($docs as $doc):
                    $doc_type_name = $doc['document_type'] === 'COE' ? 'Certificate of Employment' :
                                    ($doc['document_type'] === 'B_CERT' ? 'Business Certificate' :
                                    ($doc['document_type'] === 'COR' ? 'Certificate of Registration' : $doc['document_type']));
                    
                    $icon_config = $doc_icons[$doc['document_type']] ?? ['icon' => 'fa-file-pdf', 'color' => 'text-gray-500', 'bg' => 'bg-gray-50', 'border' => 'border-gray-200'];
                    $file_path = '../' . htmlspecialchars($doc['file_path']);
                    $file_name = basename($doc['file_path']);
                    $file_size = file_exists($file_path) ? round(filesize($file_path) / 1024, 1) . ' KB' : 'Unknown size';
                  ?>
                    <div class="<?php echo $icon_config['bg']; ?> rounded-lg border <?php echo $icon_config['border']; ?> p-4 hover:shadow-md transition-shadow">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                          <div class="<?php echo $icon_config['color']; ?> text-xl">
                            <i class="fas <?php echo $icon_config['icon']; ?>"></i>
                          </div>
                          <div>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($doc_type_name); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                              <i class="fas fa-file mr-1"></i><?php echo htmlspecialchars($file_name); ?>
                              <span class="mx-2">•</span>
                              <i class="fas fa-weight-hanging mr-1"></i><?php echo $file_size; ?>
                            </p>
                          </div>
                        </div>
                        <div class="flex space-x-2">
                          <a href="<?php echo $file_path; ?>" target="_blank" 
                             class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition duration-150 flex items-center space-x-1">
                            <i class="fas fa-eye text-xs"></i>
                            <span>View</span>
                          </a>
                          <a href="<?php echo $file_path; ?>" download 
                             class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition duration-150 flex items-center space-x-1">
                            <i class="fas fa-download text-xs"></i>
                            <span>Download</span>
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="p-4 bg-orange-50 border-t border-orange-100 rounded-b-xl">
              <p class="text-xs text-orange-600 flex items-center">
                <i class="fas fa-shield-alt mr-2"></i>
                Verified supporting documents
              </p>
            </div>
          </div>
        </div>
        <?php else: ?>

        <!-- Show empty state when no personal data exists -->
                <div class="bg-white p-8 rounded-xl shadow-lg border-2 border-dashed border-gray-300 text-center">
            <i class="fas fa-user-circle text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-bold text-gray-600 mb-2">No Profile Information</h3>
            <p class="text-gray-500 mb-4">Your profile information will appear here once you complete and submit it.</p>
            
            <?php if ($is_profile_rejected): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md text-left max-w-md mx-auto">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-exclamation-circle text-red-600 text-lg mt-0.5 flex-shrink-0"></i>
                        <div class="flex-1">
                            <p class="font-bold text-red-900 text-base">Your previous profile was rejected</p>
                            <p class="text-red-700 mt-1 text-sm">Please complete your profile again and resubmit for approval.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Profile Update Modal (Hidden by default) - ENHANCED DESIGN -->
<div id="profileUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-all duration-300 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Enhanced Header -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Update Your Profile</h3>
                    <p class="text-gray-600 text-sm mt-0">Complete your alumni information for verification</p>
                </div>
                <button id="closeProfileModal" class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg p-2 transition duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <form id="alumniProfileForm" class="space-y-6" action="update_profile.php" method="post" enctype="multipart/form-data">
                
                <!-- Profile Picture + School Info - ENHANCED -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Profile Picture - NOW CIRCULAR -->
                    <div class="lg:col-span-1 bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                        <div class="text-center">
                            <div class="relative inline-block mb-4">
                                <div class="w-32 h-32 rounded-full overflow-hidden mx-auto border-2 border-gray-300 bg-gray-100">
                                    <img id="profilePreview" src="<?php echo !empty($profile['photo_path']) ? '../' . htmlspecialchars($profile['photo_path']) : 'https://placehold.co/128x128/eeeeee/333333?text=Upload+Photo'; ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                                <div class="absolute bottom-2 right-2 bg-blue-500 rounded-full p-2 shadow-md">
                                    <i class="fas fa-camera text-white text-xs"></i>
                                </div>
                            </div>
                            <button type="button" id="uploadPictureBtn" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition duration-200 w-full mb-2 shadow-sm hover:shadow">
                                <i class="fas fa-upload mr-2"></i>Choose Photo
                            </button>
                            <input type="file" id="profilePictureInput" name="profile_photo" accept="image/jpeg,image/png" class="hidden">
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>JPG or PNG, max 2MB
                            </p>
                            <div id="profilePictureFeedback" class="text-xs text-green-600 mt-1 hidden">
                                <i class="fas fa-check mr-1"></i>Photo selected
                            </div>
                        </div>
                    </div>

                    <!-- School Information - ENHANCED with bold text -->
                    <div class="lg:col-span-3 bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="bg-blue-100 rounded-lg p-2 mr-3">
                                <i class="fas fa-graduation-cap text-blue-600 text-sm"></i>
                            </div>
                            School Information
                            <span class="text-xs font-normal text-blue-600 ml-2 bg-blue-50 px-2 py-1 rounded">Auto-filled</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php
                            $schoolFields = [
                                ['label' => 'Student ID', 'value' => $profile['student_id'] ?? 'Not set'],
                                ['label' => 'Full Name', 'value' => $profile['official_name'] ?? 'Not set'],
                                ['label' => 'Date of Birth', 'value' => !empty($profile['date_of_birth']) && $profile['date_of_birth'] != '0000-00-00' ? date('M j, Y', strtotime($profile['date_of_birth'])) : 'Not set'],
                                ['label' => 'Gender', 'value' => $profile['gender'] ?? 'Not set'],
                                ['label' => 'Program', 'value' => $profile['program'] ?? 'BSIT'],
                                ['label' => 'Year Graduated', 'value' => $profile['batch_year'] ?? 'Not set']
                            ];
                            
                            foreach ($schoolFields as $field):
                            ?>
                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide"><?php echo $field['label']; ?></label>
                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                    <span class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($field['value']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 bg-blue-50 rounded-lg p-3 border border-blue-200">
                            <p class="text-blue-700 text-xs flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Automatically filled from student records
                            </p>
                        </div>
                    </div>
                </div>

                               <!-- Address Section - ENHANCED WITH ALL REQUIRED FIELDS -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>
                        Address Information (Worldwide)
                    </h3>

                    <!-- Map Container -->
                    <div class="mb-4">
                        <div id="address-map" style="height: 350px; width: 100%;" class="border border-gray-300 rounded-md"></div>
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-mouse-pointer mr-1"></i>
                                Click on map to set location
                            </p>
                            <button type="button" id="use-current-location-btn" 
                                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                <i class="fas fa-location-crosshairs mr-1"></i>
                                Use Current Location
                            </button>
                        </div>
                    </div>

                    <!-- Address Form Fields - ALL REQUIRED FIELDS -->
                    <div class="space-y-4">
                        <!-- City, State, Country Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-city mr-1"></i>
                                    City *
                                </label>
                                <input type="text" id="city" name="city" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 address-field"
                                    value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>"
                                    placeholder="City or town" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-landmark mr-1"></i>
                                    State/Province *
                                </label>
                                <input type="text" id="state-province" name="state_province" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 address-field"
                                    value="<?php echo htmlspecialchars($profile['state_province'] ?? ''); ?>"
                                    placeholder="State, province, or region" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-globe mr-1"></i>
                                    Country *
                                </label>
                                <input type="text" id="country" name="country" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 address-field"
                                    value="<?php echo htmlspecialchars($profile['country'] ?? ''); ?>"
                                    placeholder="Country" required>
                            </div>
                        </div>
                        
                        <!-- Latitude & Longitude Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-latitude mr-1"></i>
                                    Latitude *
                                </label>
                                <input type="text" id="latitude" name="latitude" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                                    value="<?php echo htmlspecialchars($profile['latitude'] ?? ''); ?>" 
                                    required readonly>
                                <p class="text-xs text-gray-500 mt-1">Automatically set from map</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-longitude mr-1"></i>
                                    Longitude *
                                </label>
                                <input type="text" id="longitude" name="longitude" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                                    value="<?php echo htmlspecialchars($profile['longitude'] ?? ''); ?>" 
                                    required readonly>
                                <p class="text-xs text-gray-500 mt-1">Automatically set from map</p>
                            </div>
                        </div>
                        
                        <!-- Formatted Address Preview -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-map-signs mr-1"></i>
                                Full Address (Auto-generated) *
                            </label>
                            <div id="formatted-address-preview" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 min-h-[40px]">
                                <?php echo !empty($profile['formatted_address']) ? htmlspecialchars($profile['formatted_address']) : 'Address will be generated from fields above...'; ?>
                            </div>
                            <input type="hidden" id="formatted-address" name="formatted_address" 
                                value="<?php echo htmlspecialchars($profile['formatted_address'] ?? ''); ?>" required>
                            <p class="text-xs text-gray-500 mt-1">Automatically generated from city, state, and country</p>
                        </div>
                    </div>
                    
                    <!-- Address Validation Status -->
                    <div id="address-validation" class="mt-4 hidden">
                        <div class="flex items-center p-3 rounded-md" id="address-validation-message">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span id="validation-text"></span>
                        </div>
                    </div>
                </div>

                <!-- Employment Information - ENHANCED -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-purple-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-briefcase text-purple-600 text-sm"></i>
                        </div>
                        Employment Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                            <input type="tel" name="contact_number" autocomplete="tel" value="<?php echo !empty($profile['contact_number']) ? htmlspecialchars($profile['contact_number']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required pattern="[0-9]{10,11}" title="Contact number must be 11 digits">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Employment Status</label>
                            <select id="employmentStatusSelect" name="employment_status" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required>
                                <option value="">Select Status</option>
                                <option value="Employed" <?php echo ($profile['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                <option value="Self-Employed" <?php echo ($profile['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                <option value="Unemployed" <?php echo ($profile['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                <option value="Student" <?php echo ($profile['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                                <option value="Employed & Student" <?php echo ($profile['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Employment Details Section -->
                <div id="employmentDetailsSection" class="hidden bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-orange-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-building text-orange-600 text-sm"></i>
                        </div>
                        Employment Details
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div id="jobTitleField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Job Title</label>
                            <select id="jobTitleSelect" name="job_title" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="organization-title">
                                <option value="">Select Job Title</option>
                                <?php
                                $stmt_titles = $conn->prepare("SELECT title FROM job_titles ORDER BY title ASC");
                                $stmt_titles->execute();
                                $result_titles = $stmt_titles->get_result();
                                $existing_title = $employment['job_title'] ?? '';
                                $is_other = true;
                                while ($row_title = $result_titles->fetch_assoc()) {
                                    $title = $row_title['title'];
                                    $selected = ($existing_title === $title) ? 'selected' : '';
                                    if ($selected) $is_other = false;
                                    echo '<option value="' . htmlspecialchars($title) . '" ' . $selected . '>' . htmlspecialchars($title) . '</option>';
                                }
                                $stmt_titles->close();
                                ?>
                                <option value="Other" <?php if ($is_other && $existing_title) echo 'selected'; ?>>Other (Please specify)</option>
                            </select>
                            <div id="otherJobTitleDiv" class="mt-2" style="display: <?php echo ($is_other && $existing_title) ? 'block' : 'none'; ?>;">
                                <input type="text" id="otherJobTitleInput" name="other_job_title" placeholder="Enter custom job title" value="<?php echo ($is_other && $existing_title) ? htmlspecialchars($existing_title) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm">
                            </div>
                        </div>
                        <div id="companyField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Company Name</label>
                            <input type="text" name="company_name" value="<?php echo !empty($employment['company_name']) ? htmlspecialchars($employment['company_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="organization">
                        </div>
                        <div id="companyAddressField" class="hidden space-y-1 md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Company Address</label>
                            <input type="text" name="company_address" value="<?php echo !empty($employment['company_address']) ? htmlspecialchars($employment['company_address'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="street-address">
                        </div>
                        <div id="businessTypeField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Business Type</label>
                            <select id="businessTypeSelect" name="business_type" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200">
                                <option value="">Select Business Type</option>
                                <option value="Food Service / Catering" <?php echo $business_type === 'Food Service / Catering' ? 'selected' : ''; ?>>Food Service / Catering</option>
                                <option value="Retail / Online Selling" <?php echo $business_type === 'Retail / Online Selling' ? 'selected' : ''; ?>>Retail / Online Selling</option>
                                <option value="Freelancer" <?php echo $business_type === 'Freelancer' ? 'selected' : ''; ?>>Freelancer</option>
                                <option value="Marketing / Advertising" <?php echo $business_type === 'Marketing / Advertising' ? 'selected' : ''; ?>>Marketing / Advertising</option>
                                <option value="Education / Tutoring" <?php echo $business_type === 'Education / Tutoring' ? 'selected' : ''; ?>>Education / Tutoring</option>
                                <option value="Construction / Carpentry / Electrical" <?php echo $business_type === 'Construction / Carpentry / Electrical' ? 'selected' : ''; ?>>Construction / Carpentry / Electrical</option>
                                <option value="Delivery Services" <?php echo $business_type === 'Delivery Services' ? 'selected' : ''; ?>>Delivery Services</option>
                                <option value="Event Planning / Photography" <?php echo $business_type === 'Event Planning / Photography' ? 'selected' : ''; ?>>Event Planning / Photography</option>
                                <option value="Real Estate / Property Leasing" <?php echo $business_type === 'Real Estate / Property Leasing' ? 'selected' : ''; ?>>Real Estate / Property Leasing</option>
                                <option value="Others (Please specify)" <?php echo $business_type === 'Others (Please specify)' ? 'selected' : ''; ?>>Others (Please specify)</option>
                            </select>
                            <div id="businessTypeOtherDiv" class="mt-2" style="display: <?php echo ($business_type === 'Others (Please specify)') ? 'block' : 'none'; ?>;">
                                <input type="text" id="businessTypeOtherInput" name="business_type_other" value="<?php echo htmlspecialchars($business_type_other); ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm" placeholder="Specify Business Type">
                            </div>
                        </div>
                        <div id="salaryField" class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Salary Range</label>
                            <select name="salary_range" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200">
                                <option value="">Select Salary Range</option>
                                <option value="Below ₱10,000" <?php echo ($employment['salary_range'] ?? '') === 'Below ₱10,000' ? 'selected' : ''; ?>>Below ₱10,000</option>
                                <option value="₱10,000–₱20,000" <?php echo ($employment['salary_range'] ?? '') === '₱10,000–₱20,000' ? 'selected' : ''; ?>>₱10,000–₱20,000</option>
                                <option value="₱20,000–₱30,000" <?php echo ($employment['salary_range'] ?? '') === '₱20,000–₱30,000' ? 'selected' : ''; ?>>₱20,000–₱30,000</option>
                                <option value="₱30,000–₱40,000" <?php echo ($employment['salary_range'] ?? '') === '₱30,000–₱40,000' ? 'selected' : ''; ?>>₱30,000–₱40,000</option>
                                <option value="₱40,000–₱50,000" <?php echo ($employment['salary_range'] ?? '') === '₱40,000–₱50,000' ? 'selected' : ''; ?>>₱40,000–₱50,000</option>
                                <option value="Above ₱50,000" <?php echo ($employment['salary_range'] ?? '') === 'Above ₱50,000' ? 'selected' : ''; ?>>Above ₱50,000</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Student Details Section -->
                <div id="studentDetailsSection" class="hidden bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-indigo-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-user-graduate text-indigo-600 text-sm"></i>
                        </div>
                        Student Details
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">School Name</label>
                            <input type="text" name="school_name" value="<?php echo !empty($education['school_name']) ? htmlspecialchars($education['school_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="organization">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Degree Pursued</label>
                            <input type="text" name="degree_pursued" value="<?php echo !empty($education['degree_pursued']) ? htmlspecialchars($education['degree_pursued'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="off">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Start Year</label>
                            <select name="start_year" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200">
                                <option value="">Select Start Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= 2000; $y--) {
                                    $selected = ($education['start_year'] ?? '') == $y ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">End Year (Expected)</label>
                            <select name="end_year" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200">
                                <option value="">Select End Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear + 5; $y >= 2000; $y--) {
                                    $selected = ($education['end_year'] ?? '') == $y ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Supporting Documents Section - ENHANCED with clear file attachment styling -->
                <div id="supportingDocumentsSection" class="hidden bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-red-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-file-alt text-red-600 text-sm"></i>
                        </div>
                        Supporting Documents
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- COE Field - CLEAR FILE ATTACHMENT -->
                        <div id="coeField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                Certificate of Employment (COE)
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="coe_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>

                        <!-- Business Certificate Field - CLEAR FILE ATTACHMENT -->
                        <div id="businessCertField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-certificate text-green-500 mr-2"></i>
                                Business Certificate
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="business_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>

                        <!-- COR Field - CLEAR FILE ATTACHMENT -->
                        <div id="corField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-contract text-purple-500 mr-2"></i>
                                Certificate of Registration (COR)
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="cor_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button - ENHANCED -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex justify-between items-center">
                        <div class="text-gray-600">
                            <p class="text-sm font-medium">Ready to submit your profile?</p>
                            <p class="text-xs text-gray-500 mt-1">Review all information before submitting</p>
                        </div>
                        <?php if ($can_update): ?>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-200 shadow-sm hover:shadow flex items-center space-x-2 text-sm">
                                <i class="fas fa-paper-plane"></i>
                                <span>Submit Profile Update</span>
                            </button>
                        <?php else: ?>
                            <button type="button" disabled class="bg-gray-400 text-white font-medium py-3 px-8 rounded-lg cursor-not-allowed flex items-center space-x-2 text-sm">
                                <i class="fas fa-lock"></i>
                                <span>Submit (Not Available)</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-close modal if form was just submitted
<?php if (isset($_SESSION['form_submitted']) && $_SESSION['form_submitted']): ?>
    <?php unset($_SESSION['form_submitted']); // Clear the flag ?>
    const modal = document.getElementById('profileUpdateModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show', 'flex');
    }
<?php endif; ?>

// FIXED: Auto-open modal for rejected profiles with existing data
<?php if ($auto_open_modal): ?>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('profileUpdateModal');
    if (modal) {
        // Small delay to ensure page is fully loaded
        setTimeout(() => {
            modal.classList.remove('hidden');
            modal.classList.add('show', 'flex');
            initMap();
        }, 100);
    }
});
<?php endif; ?>

document.addEventListener('DOMContentLoaded', () => {
    // Modal and form elements
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const updateProfileModal = document.getElementById('profileUpdateModal');
    const closeModalBtn = document.getElementById('closeProfileModal');
    const employmentStatusSelect = document.getElementById('employmentStatusSelect');
    const employmentDetailsSection = document.getElementById('employmentDetailsSection');
    const jobTitleField = document.getElementById('jobTitleField');
    const jobTitleSelect = document.getElementById('jobTitleSelect');
    const otherJobTitleDiv = document.getElementById('otherJobTitleDiv');
    const companyField = document.getElementById('companyField');
    const businessTypeField = document.getElementById('businessTypeField');
    const businessTypeSelect = document.getElementById('businessTypeSelect');
    const businessTypeOtherDiv = document.getElementById('businessTypeOtherDiv');
    const studentDetailsSection = document.getElementById('studentDetailsSection');
    const coeField = document.getElementById('coeField');
    const businessCertField = document.getElementById('businessCertField');
    const corField = document.getElementById('corField');
    const supportingDocumentsSection = document.getElementById('supportingDocumentsSection');
    const companyAddressField = document.getElementById('companyAddressField');
    const salaryField = document.getElementById('salaryField');

    // Track loading state
    let isAddressLoading = false;

    // Modal toggle - ONLY if user can update
    if (updateProfileBtn) {
        const canUpdate = <?php echo $can_update ? 'true' : 'false'; ?>;
        
        if (canUpdate) {
            updateProfileBtn.addEventListener('click', () => {
                if (updateProfileModal) {
                    updateProfileModal.classList.remove('hidden');
                    updateProfileModal.classList.add('show', 'flex');
                    // Small delay to ensure DOM is ready
                    setTimeout(() => {
                        if (typeof initMap === 'function') {
                            initMap();
                        }
                    }, 100);
                }
            });
        } else {
            // Make it visually clear the button is disabled
            updateProfileBtn.style.cursor = 'not-allowed';
            updateProfileBtn.style.opacity = '0.6';
            
            updateProfileBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (<?php echo !empty($profile) ? 'true' : 'false'; ?>) {
                    const status = '<?php echo $profile['submission_status'] ?? ''; ?>';
                    if (status === 'Approved') {
                        alert('Your profile is approved. You can update again after 6 months from your last update.');
                    } else if (status === 'Pending') {
                        alert('Your profile is currently under review. Please wait for administrator approval.');
                    } else {
                        alert('Profile update is not available at this time.');
                    }
                } else {
                    alert('Profile update is not available at this time.');
                }
            });
        }
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (updateProfileModal) {
                updateProfileModal.classList.add('hidden');
                updateProfileModal.classList.remove('show', 'flex');
            }
        });
    }

    if (updateProfileModal) {
        updateProfileModal.addEventListener('click', (e) => {
            if (e.target === updateProfileModal) {
                updateProfileModal.classList.add('hidden');
                updateProfileModal.classList.remove('show', 'flex');
            }
        });
    }

    // Profile picture upload and preview
    const uploadBtn = document.getElementById("uploadPictureBtn");
    const fileInput = document.getElementById("profilePictureInput");
    const previewImg = document.getElementById("profilePreview");

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener("click", () => {
            fileInput.click();
        });
    }

    if (fileInput && previewImg) {
        fileInput.addEventListener("change", function () {
            const file = this.files[0];
            if (!file) return;

            // Validate type
            const validTypes = ["image/jpeg", "image/png"];
            if (!validTypes.includes(file.type)) {
                alert("Only JPG and PNG files are allowed.");
                this.value = "";
                return;
            }

            // Validate size (≤ 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert("File size exceeds 2MB.");
                this.value = "";
                return;
            }

            // Live preview
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // Job title toggle for "Other"
    if (jobTitleSelect && otherJobTitleDiv) {
        jobTitleSelect.addEventListener('change', () => {
            otherJobTitleDiv.style.display = jobTitleSelect.value === 'Other' ? 'block' : 'none';
        });
    }

    // Business type toggle
    if (businessTypeSelect && businessTypeOtherDiv) {
        businessTypeSelect.addEventListener('change', () => {
            businessTypeOtherDiv.style.display = businessTypeSelect.value === 'Others (Please specify)' ? 'block' : 'none';
        });
    }

    // Employment status toggle
    function toggleEmploymentSections(status) {
        console.log('Toggling employment sections for:', status);
        
        // Hide all sections first
        if (employmentDetailsSection) employmentDetailsSection.classList.add('hidden');
        if (studentDetailsSection) studentDetailsSection.classList.add('hidden');
        if (supportingDocumentsSection) supportingDocumentsSection.classList.add('hidden');
        
        // Hide individual fields
        if (jobTitleField) jobTitleField.classList.add('hidden');
        if (companyField) companyField.classList.add('hidden');
        if (companyAddressField) companyAddressField.classList.add('hidden');
        if (businessTypeField) businessTypeField.classList.add('hidden');
        if (salaryField) salaryField.classList.add('hidden');
        if (coeField) coeField.classList.add('hidden');
        if (businessCertField) businessCertField.classList.add('hidden');
        if (corField) corField.classList.add('hidden');

        // Show relevant sections based on status
        switch(status) {
            case 'Employed':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (jobTitleField) jobTitleField.classList.remove('hidden');
                if (companyField) companyField.classList.remove('hidden');
                if (companyAddressField) companyAddressField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (coeField) coeField.classList.remove('hidden');
                break;
                
            case 'Self-Employed':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (businessTypeField) businessTypeField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (businessCertField) businessCertField.classList.remove('hidden');
                break;
                
            case 'Unemployed':
                // No additional sections for unemployed
                break;
                
            case 'Student':
                if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (corField) corField.classList.remove('hidden');
                break;
                
            case 'Employed & Student':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (jobTitleField) jobTitleField.classList.remove('hidden');
                if (companyField) companyField.classList.remove('hidden');
                if (companyAddressField) companyAddressField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (coeField) coeField.classList.remove('hidden');
                if (corField) corField.classList.remove('hidden');
                break;
                
            default:
                // Hide everything for unknown status
                break;
        }
    }

    // Initialize employment sections
    if (employmentStatusSelect) {
        toggleEmploymentSections(employmentStatusSelect.value);
        
        employmentStatusSelect.addEventListener('change', () => {
            toggleEmploymentSections(employmentStatusSelect.value);
        });
    }

    // ENHANCED: Dynamic filtering for years based on graduation year and current year
    function updateStudentYearOptions() {
        const graduationYear = <?php echo !empty($profile['batch_year']) ? $profile['batch_year'] : 'null'; ?>;
        const startYearSelect = document.querySelector('[name="start_year"]');
        const endYearSelect = document.querySelector('[name="end_year"]');
        const status = employmentStatusSelect ? employmentStatusSelect.value : '';
        
        if (startYearSelect && endYearSelect && ['Student', 'Employed & Student'].includes(status)) {
            const currentYear = new Date().getFullYear();
            
            // Store current selections
            const currentStartYear = startYearSelect.value;
            const currentEndYear = endYearSelect.value;
            
            // Update Start Year dropdown: graduation year + 1 to current year
            startYearSelect.innerHTML = '<option value="">Select Start Year</option>';
            
            // Start year can be from the year after graduation up to current year
            const minStartYear = graduationYear ? parseInt(graduationYear) + 1 : currentYear - 10;
            const maxStartYear = currentYear;
            
            for (let y = minStartYear; y <= maxStartYear; y++) {
                const option = document.createElement('option');
                option.value = y;
                option.textContent = y;
                if (currentStartYear && y === parseInt(currentStartYear)) {
                    option.selected = true;
                }
                startYearSelect.appendChild(option);
            }
            
            // Update End Year dropdown based on selected start year
            updateEndYearOptions();
        }
    }

    function updateEndYearOptions() {
        const startYearSelect = document.querySelector('[name="start_year"]');
        const endYearSelect = document.querySelector('[name="end_year"]');
        const currentYear = new Date().getFullYear();
        
        if (startYearSelect && endYearSelect && startYearSelect.value) {
            const startYear = parseInt(startYearSelect.value);
            const currentEndYear = endYearSelect.value;
            
            // End Year: start year + 1 to current year + 5 (max 5 years in future)
            endYearSelect.innerHTML = '<option value="">Select End Year</option>';
            for (let y = startYear + 1; y <= currentYear + 5; y++) {
                const option = document.createElement('option');
                option.value = y;
                option.textContent = y;
                if (currentEndYear && y === parseInt(currentEndYear)) {
                    option.selected = true;
                }
                endYearSelect.appendChild(option);
            }
        }
    }

    // Event listeners for dynamic year filtering
    if (employmentStatusSelect) {
        employmentStatusSelect.addEventListener('change', updateStudentYearOptions);
    }

    // Update formatted address when fields change
    const startYearSelect = document.querySelector('[name="start_year"]');
    if (startYearSelect) {
        startYearSelect.addEventListener('change', updateEndYearOptions);
    }

    // Update formatted address when fields change
    const addressFields = document.querySelectorAll('.address-field');
    addressFields.forEach(field => {
        field.addEventListener('input', function() {
            updateFormattedAddress();
            debounceGeocode();
        });
    });

    // Initialize formatted address on load
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to ensure all elements are loaded
        setTimeout(() => {
            updateFormattedAddress();
            
            // Load existing address data if any
            loadExistingAddress();
        }, 300);
    });

    // Student year validation function
    function validateStudentYears() {
        const status = employmentStatusSelect ? employmentStatusSelect.value : '';
        
        if (['Student', 'Employed & Student'].includes(status)) {
            const startYear = document.querySelector('[name="start_year"]').value;
            const endYear = document.querySelector('[name="end_year"]').value;
            
            if (!startYear || !endYear) {
                alert('Both Start Year and End Year are required for student status.');
                return false;
            }
            
            if (parseInt(endYear) <= parseInt(startYear)) {
                alert('End Year must be later than Start Year.');
                return false;
            }
            
            const currentYear = new Date().getFullYear();
            if (parseInt(endYear) > (currentYear + 10)) {
                alert('End Year seems too far in the future. Please verify your expected graduation year.');
                return false;
            }
        }
        
        return true;
    }

    // SIMPLIFIED Form validation - FIXED VERSION
    const alumniProfileForm = document.getElementById('alumniProfileForm');
    if (alumniProfileForm) {
        alumniProfileForm.addEventListener('submit', function(event) {
            // Prevent address loading interference
            if (isAddressLoading) {
                alert('Address data is still loading. Please wait.');
                event.preventDefault();
                return;
            }
            
            // Validate student years
            if (!validateStudentYears()) {
                event.preventDefault();
                return;
            }

            // Profile photo validation - FIXED
            const profilePhotoInput = document.getElementById('profilePictureInput');
            const hasExistingPhoto = '<?php echo !empty($profile['photo_path']) ? 'true' : 'false'; ?>';

            // Only require photo if no existing photo
            if (!profilePhotoInput.files.length && hasExistingPhoto === 'false') {
                alert('Please upload your profile picture before submitting.');
                event.preventDefault();
                return;
            }

            // UPDATED: Only contact number and employment status are required
            const requiredFields = [
                { field: 'contact_number', message: 'Contact Number is required.' },
                { field: 'employment_status', message: 'Employment Status is required.' }
            ];

            let isValid = true;

            for (const { field, message } of requiredFields) {
                const element = document.querySelector(`[name="${field}"]`);
                if (element && !element.value.trim()) {
                    alert(message);
                    isValid = false;
                    break;
                }
            }

            if (!isValid) {
                event.preventDefault();
                return;
            }

            // Worldwide address validation
            const addressFields = ['city', 'state_province', 'country', 'latitude', 'longitude', 'formatted_address'];
            const addressMessages = ['City', 'State/Province', 'Country', 'Latitude', 'Longitude', 'Formatted Address'];

            for (let i = 0; i < addressFields.length; i++) {
                const element = document.querySelector(`[name="${addressFields[i]}"]`);
                if (element && !element.value.trim()) {
                    alert(addressMessages[i] + ' is required for address.');
                    isValid = false;
                    break;
                }
            }

            if (!isValid) {
                event.preventDefault();
                return;
            }

            // Employment validation
            const status = employmentStatusSelect ? employmentStatusSelect.value : '';
            
            if (['Employed', 'Employed & Student'].includes(status)) {
                if (jobTitleSelect && !jobTitleSelect.value) {
                    alert('Job Title is required for this employment status.');
                    isValid = false;
                } else if (jobTitleSelect && jobTitleSelect.value === 'Other') {
                    const otherTitle = document.querySelector('[name="other_job_title"]');
                    if (otherTitle && !otherTitle.value.trim()) {
                        alert('Please specify job title if "Other" is selected.');
                        isValid = false;
                    }
                }
                
                const companyName = document.querySelector('[name="company_name"]');
                if (companyName && !companyName.value.trim()) {
                    alert('Company Name is required for this employment status.');
                    isValid = false;
                }
                
                const companyAddress = document.querySelector('[name="company_address"]');
                if (companyAddress && !companyAddress.value.trim()) {
                    alert('Company Address is required for this employment status.');
                    isValid = false;
                }
            }

            // Self-Employed validation
            if (status === 'Self-Employed') {
                if (businessTypeSelect && !businessTypeSelect.value) {
                    alert('Business Type is required for Self-Employed status.');
                    isValid = false;
                } else if (businessTypeSelect && businessTypeSelect.value === 'Others (Please specify)') {
                    const businessTypeOther = document.querySelector('[name="business_type_other"]');
                    if (businessTypeOther && !businessTypeOther.value.trim()) {
                        alert('Please specify business type if "Others" is selected.');
                        isValid = false;
                    }
                }
            }

            if (!isValid) {
                event.preventDefault();
                return;
            }
        });
    }

    // Initialize student year options when modal opens
    if (updateProfileBtn) {
        const canUpdate = <?php echo $can_update ? 'true' : 'false'; ?>;
        
        if (canUpdate) {
            updateProfileBtn.addEventListener('click', () => {
                if (updateProfileModal) {
                    updateProfileModal.classList.remove('hidden');
                    updateProfileModal.classList.add('show', 'flex');
                    // Initialize student year options based on graduation year
                    setTimeout(updateStudentYearOptions, 100);
                }
            });
        }
    }

    // Also initialize for auto-opening modal
    <?php if ($auto_open_modal): ?>
    setTimeout(() => {
        updateStudentYearOptions();
    }, 200);
    <?php endif; ?>
});

// LEAFLET MAP FUNCTIONS - Fixed with proper 2-way sync
let map;
let marker;
let selectedLatLng;
let debounceTimer;
let geocodingInProgress = false;

// Initialize map
function initMap() {
    // Try to get current location, fallback to default
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const defaultCenter = [position.coords.latitude, position.coords.longitude];
                setupMap(defaultCenter, 13);
            },
            () => {
                // Default center (Philippines) if location access denied
                const defaultCenter = [12.8797, 121.7740];
                setupMap(defaultCenter, 6);
            }
        );
    } else {
        const defaultCenter = [12.8797, 121.7740];
        setupMap(defaultCenter, 6);
    }
}

function setupMap(center, zoom) {
    // Check if map container exists
    const mapContainer = document.getElementById('address-map');
    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }
    
    // Clear any existing map
    if (map) {
        map.remove();
    }
    
    map = L.map('address-map').setView(center, zoom);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add scale control
    L.control.scale().addTo(map);
    
    // Click event on map
    map.on('click', function(e) {
        selectedLatLng = e.latlng;
        updateMarker(selectedLatLng);
        reverseGeocode(selectedLatLng.lat, selectedLatLng.lng);
    });
    
    // Load existing address if any
    loadExistingAddress();
}

// Update marker position
function updateMarker(latlng) {
    if (marker) {
        map.removeLayer(marker);
    }
    marker = L.marker(latlng).addTo(map)
        .bindPopup('Selected Location')
        .openPopup();
    
    // Center map on marker with smooth animation
    map.flyTo(latlng, 15, {
        duration: 0.5
    });
    
    document.getElementById('latitude').value = latlng.lat.toFixed(6);
    document.getElementById('longitude').value = latlng.lng.toFixed(6);
    
    // Validate address after marker update
    validateAddress();
}

// Reverse geocode (coordinates to address) - FIXED FUNCTION
async function reverseGeocode(lat, lon) {
    try {
        const response = await fetch(`../api/geocode.php?action=reverse&lat=${lat}&lon=${lon}&zoom=18`);
        const result = await response.json();
        
        if (result && result.address) {
            const address = result.address;
            
            // Populate the address fields with reverse geocoding data
            document.getElementById('city').value = address.city || address.town || address.village || address.county || '';
            document.getElementById('state-province').value = address.state || address.region || '';
            document.getElementById('country').value = address.country || '';
            
            // Update formatted address
            updateFormattedAddress();
            
            showValidation('Address updated from map location!', 'success');
        }
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        showValidation('Could not get address details for this location.', 'warning');
    }
}

// Update formatted address preview
function updateFormattedAddress() {
    const city = document.getElementById('city').value.trim();
    const state = document.getElementById('state-province').value.trim();
    const country = document.getElementById('country').value.trim();
    
    const parts = [];
    if (city) parts.push(city);
    if (state) parts.push(state);
    if (country) parts.push(country);
    
    const formattedAddress = parts.join(', ');
    
    // Update preview
    const previewElement = document.getElementById('formatted-address-preview');
    if (previewElement) {
        previewElement.textContent = formattedAddress || 'Address will be generated from fields above...';
    }
    
    // Update hidden input for form submission
    const hiddenInput = document.getElementById('formatted-address');
    if (hiddenInput) {
        hiddenInput.value = formattedAddress;
    }
    
    return formattedAddress;
}

// Validate all required address fields
function validateAddress() {
    const requiredFields = [
        { id: 'city', name: 'City' },
        { id: 'state-province', name: 'State/Province' },
        { id: 'country', name: 'Country' },
        { id: 'latitude', name: 'Latitude' },
        { id: 'longitude', name: 'Longitude' }
    ];
    
    let missingFields = [];
    
    requiredFields.forEach(field => {
        const fieldElement = document.getElementById(field.id);
        if (!fieldElement || !fieldElement.value.trim()) {
            missingFields.push(field.name);
        }
    });
    
    if (missingFields.length > 0) {
        showValidation(`Missing required fields: ${missingFields.join(', ')}`, 'error');
        return false;
    }
    
    // Validate latitude/longitude format
    const lat = parseFloat(document.getElementById('latitude').value);
    const lng = parseFloat(document.getElementById('longitude').value);
    
    if (isNaN(lat) || isNaN(lng)) {
        showValidation('Invalid coordinates. Please select a location on the map.', 'error');
        return false;
    }
    
    if (lat < -90 || lat > 90) {
        showValidation('Latitude must be between -90 and 90 degrees.', 'error');
        return false;
    }
    
    if (lng < -180 || lng > 180) {
        showValidation('Longitude must be between -180 and 180 degrees.', 'error');
        return false;
    }
    
    showValidation('Address is complete and ready!', 'success');
    return true;
}

// Show validation message
function showValidation(message, type) {
    const container = document.getElementById('address-validation');
    const messageEl = document.getElementById('address-validation-message');
    const textEl = document.getElementById('validation-text');
    
    if (!container || !messageEl || !textEl) return;
    
    // Clear previous classes
    messageEl.className = 'flex items-center p-3 rounded-md';
    
    // Add type-specific classes
    switch(type) {
        case 'success':
            messageEl.classList.add('bg-green-100', 'text-green-800', 'border', 'border-green-200');
            break;
        case 'error':
            messageEl.classList.add('bg-red-100', 'text-red-800', 'border', 'border-red-200');
            break;
        case 'warning':
            messageEl.classList.add('bg-yellow-100', 'text-yellow-800', 'border', 'border-yellow-200');
            break;
        case 'info':
            messageEl.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-200');
            break;
    }
    
    textEl.textContent = message;
    container.classList.remove('hidden');
    
    // Auto-hide success messages after 3 seconds
    if (type === 'success') {
        setTimeout(() => {
            container.classList.add('hidden');
        }, 3000);
    }
}

// Load existing address data
function loadExistingAddress() {
    const existingLat = document.getElementById('latitude').value;
    const existingLng = document.getElementById('longitude').value;
    
    if (existingLat && existingLng) {
        const latLng = L.latLng(parseFloat(existingLat), parseFloat(existingLng));
        updateMarker(latlng);
    }
}

// Use current location
function useCurrentLocation() {
    if (navigator.geolocation) {
        showValidation('Getting your current location...', 'info');
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                selectedLatLng = L.latLng(lat, lon);
                updateMarker(selectedLatLng);
                reverseGeocode(lat, lon);
            },
            (error) => {
                showValidation('Could not get your location. Please allow location access.', 'error');
                console.error('Geolocation error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    } else {
        showValidation('Geolocation is not supported by your browser', 'error');
    }
}

// Geocode from form fields (2-way sync when user types in address fields)
async function geocodeFromForm() {
    const city = document.getElementById('city').value.trim();
    const state = document.getElementById('state-province').value.trim();
    const country = document.getElementById('country').value.trim();
    
    if (!city || !state || !country) {
        showValidation('Please fill in city, state/province, and country first', 'warning');
        return;
    }
    
    const address = `${city}, ${state}, ${country}`;
    
    if (geocodingInProgress) return;
    geocodingInProgress = true;
    
    try {
        showValidation('Searching for address...', 'info');
        
        const response = await fetch(`../api/geocode.php?action=geocode&address=${encodeURIComponent(address)}`);
        const results = await response.json();
        
        if (results && results.length > 0) {
            const firstResult = results[0];
            const lat = parseFloat(firstResult.lat);
            const lon = parseFloat(firstResult.lon);
            
            selectedLatLng = L.latLng(lat, lon);
            updateMarker(selectedLatLng);
            showValidation('Address found and located on map!', 'success');
        } else {
            showValidation('Address not found. Please try different city/state/country combination.', 'warning');
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        showValidation('Error searching address. Please try again.', 'error');
    } finally {
        geocodingInProgress = false;
    }
}

// Debounced geocoding for field changes
function debounceGeocode() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const city = document.getElementById('city').value.trim();
        const state = document.getElementById('state-province').value.trim();
        const country = document.getElementById('country').value.trim();
        
        if (city && state && country) {
            geocodeFromForm();
        }
    }, 1000); // Wait 1 second after last keystroke
}

// Event listeners for form submission validation
document.addEventListener('DOMContentLoaded', function() {
    // Update formatted address when fields change
    const addressFields = document.querySelectorAll('.address-field');
    addressFields.forEach(field => {
        field.addEventListener('input', function() {
            updateFormattedAddress();
            debounceGeocode();
        });
    });

    // Use current location button
    const useCurrentLocationBtn = document.getElementById('use-current-location-btn');
    if (useCurrentLocationBtn) {
        useCurrentLocationBtn.addEventListener('click', useCurrentLocation);
    }

    // Form submission validation
    const alumniProfileForm = document.getElementById('alumniProfileForm');
    if (alumniProfileForm) {
        alumniProfileForm.addEventListener('submit', function(event) {
            if (!validateAddress()) {
                event.preventDefault();
                showValidation('Please complete all address fields and select a location on the map.', 'error');
                return false;
            }
            return true;
        });
    }
    
    // Initialize formatted address on load
    updateFormattedAddress();
});
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>