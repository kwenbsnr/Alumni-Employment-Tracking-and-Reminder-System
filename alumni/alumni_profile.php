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

$stmt = $conn->prepare("
    SELECT 
        u.user_id, u.email, u.student_id, u.date_of_birth, u.gender, u.program, 
        u.contact_number, u.citizenship, u.civil_status,  -- Added new fields from users table
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.batch_year,
        u.first_name, u.middle_name, u.last_name, u.suffix,
        ap.employment_status, ap.photo_path, 
        ap.submission_status, ap.last_profile_update, ap.rejection_reason,
        aa.city, aa.state_province, aa.street, aa.country
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN alumni_address aa ON u.user_id = aa.user_id
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

//  Check if profile has personal data for display 
$has_personal_data = !empty($profile) && (!empty($profile['contact_number']) || !empty($profile['employment_status']));

if (!function_exists('isSubmissionPeriodOpen')) {
    require_once dirname(__DIR__) . '/api/utils/deadline.php';
}
$submission_open = isSubmissionPeriodOpen($conn);

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

                elseif ($is_profile_new) echo 'Validate Your Profile';

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

            <p class="text-xs text-amber-700 mt-2">We'll notify you via email once approved</p>

        </div>

    <?php elseif ($is_profile_new): ?>
        <!--
        <div class="bg-blue-50 rounded-xl px-4 py-3 border border-blue-200">
            <p class="text-sm font-medium text-blue-900">Validate alumni profile and update employment information</p>
            <p class="text-xs text-blue-700 mt-1">View and validate your preloaded personal and academic information.</p>
        </div>
        -->
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
                <?php echo $is_profile_rejected ? 'Edit & Resubmit Profile' : ($is_profile_new ? 'Validate My Profile Now' : 'Update Profile Information'); ?>
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
                <?php if (!empty($profile['citizenship'])): ?>
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Citizenship</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['citizenship']); ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($profile['civil_status'])): ?>
                <div class="space-y-1">
                  <dt class="font-medium text-gray-500 text-sm mb-1">Civil Status</dt>
                  <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['civil_status']); ?></dd>
                </div>
                <?php endif; ?>
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
                    <?php
                    $address_parts = array_filter([
                        $profile['street'] ?? '',
                        $profile['city'] ?? '',
                        $profile['state_province'] ?? '',
                        $profile['country'] ?? ''
                    ]);

                    $formatted_address = !empty($address_parts) ? implode(', ', $address_parts) : 'N/A';
                    ?>

                    <!-- Address Summary -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-signs text-green-500 mr-2"></i>
                            Complete Address
                        </p>
                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($formatted_address); ?></p>
                    </div>

                    <!-- Location Details -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Country</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['country'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">State/Province</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['state_province'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">City</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['city'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Street</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['street'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
   
              <?php else: ?>
                <div class="text-center py-6">
                  <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-map-marker-alt text-gray-400 text-2xl"></i>
                  </div>
                  <p class="text-gray-500 font-medium">No address information</p>
                  <p class="text-gray-400 text-sm mt-1">Update by adding address details</p>
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
            <h3 class="text-xl font-bold text-gray-600 mb-2">Profile Information Unavailable Yet</h3>
            <p class="text-gray-500 mb-4">Your profile information will appear here once you validate and submit it.</p>
            
            <?php if ($is_profile_rejected): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md text-left max-w-md mx-auto">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-exclamation-circle text-red-600 text-lg mt-0.5 flex-shrink-0"></i>
                        <div class="flex-1">
                            <p class="font-bold text-red-900 text-base">Your previous profile was rejected</p>
                            <p class="text-red-700 mt-1 text-sm">Please review your submission for approval.</p>
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
                                ['label' => 'Year Graduated', 'value' => $profile['batch_year'] ?? 'Not set'],
                                ['label' => 'Citizenship', 'value' => $profile['citizenship'] ?? 'Not set'],
                                ['label' => 'Civil Status', 'value' => $profile['civil_status'] ?? 'Not set']
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

                <!-- Address Section -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>
                        Address Information
                    </h3>

                    <!-- Address Form Fields -->
                    <div class="space-y-4">
                        <!-- Country, State/Province Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Country <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="country" name="country" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo htmlspecialchars($profile['country'] ?? ''); ?>"
                                    placeholder="Country (e.g., Philippines)" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    State/Province <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="state-province" name="state_province" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo htmlspecialchars($profile['state_province'] ?? ''); ?>"
                                    placeholder="State or Province (e.g., Zamboanga del Sur)" required>
                            </div>
                        </div>
                        
                        <!-- City and Street Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    City/Municipality <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="city" name="city" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>"
                                    placeholder="City or Municipality (e.g., Dumingag)" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Street/Barangay
                                </label>
                                <input type="text" id="street" name="street" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo htmlspecialchars($profile['street'] ?? ''); ?>"
                                    placeholder="Street, Barangay, or Village">
                            </div>
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
// ===== SINGLE DOMContentLoaded HANDLER =====
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded and parsed');
    
    // Remove success/error parameters from URL without reloading
    const url = new URL(window.location);
    if (url.searchParams.has('success') || url.searchParams.has('error')) {
        url.searchParams.delete('success');
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    }
    
    // Initialize all components
    initializeProfilePicture();
    initializeModal();
    initializeFormValidation();
    initializeAddressFields();
    initializeStudentYearOptions();
});

// ===== PROFILE PICTURE HANDLING =====
function initializeProfilePicture() {
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
}

// ===== MODAL INITIALIZATION =====
function initializeModal() {
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const updateProfileModal = document.getElementById('profileUpdateModal');
    const closeModalBtn = document.getElementById('closeProfileModal');
    
    if (!updateProfileBtn || !updateProfileModal) return;
    
    const canUpdate = <?php echo $can_update ? 'true' : 'false'; ?>;
    
    if (canUpdate) {
        updateProfileBtn.addEventListener('click', () => {
            updateProfileModal.classList.remove('hidden');
            updateProfileModal.classList.add('show', 'flex');
            
            // Update student year options when modal opens
            updateStudentYearOptions();
        });
    } else {
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
}

// ===== FORM VALIDATION INITIALIZATION =====
function initializeFormValidation() {
    const employmentStatusSelect = document.getElementById('employmentStatusSelect');
    const jobTitleSelect = document.getElementById('jobTitleSelect');
    const otherJobTitleDiv = document.getElementById('otherJobTitleDiv');
    const businessTypeSelect = document.getElementById('businessTypeSelect');
    const businessTypeOtherDiv = document.getElementById('businessTypeOtherDiv');
    
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
    if (employmentStatusSelect) {
        toggleEmploymentSections(employmentStatusSelect.value);
        employmentStatusSelect.addEventListener('change', () => {
            toggleEmploymentSections(employmentStatusSelect.value);
        });
    }

    // Form submission validation
    const alumniProfileForm = document.getElementById('alumniProfileForm');
    if (alumniProfileForm) {
        alumniProfileForm.addEventListener('submit', function(event) {
            if (!validateFormSubmission()) {
                event.preventDefault();
            }
        });
    }
}

// ===== EMPLOYMENT SECTION TOGGLE =====
function toggleEmploymentSections(status) {
    console.log('Toggling employment sections for:', status);
    
    const sections = {
        employmentDetails: document.getElementById('employmentDetailsSection'),
        studentDetails: document.getElementById('studentDetailsSection'),
        supportingDocuments: document.getElementById('supportingDocumentsSection'),
        jobTitleField: document.getElementById('jobTitleField'),
        companyField: document.getElementById('companyField'),
        companyAddressField: document.getElementById('companyAddressField'),
        businessTypeField: document.getElementById('businessTypeField'),
        salaryField: document.getElementById('salaryField'),
        coeField: document.getElementById('coeField'),
        businessCertField: document.getElementById('businessCertField'),
        corField: document.getElementById('corField')
    };
    
    // Hide all sections first
    Object.values(sections).forEach(section => {
        if (section) section.classList.add('hidden');
    });
    
    // Show relevant sections based on status
    switch(status) {
        case 'Employed':
            if (sections.employmentDetails) sections.employmentDetails.classList.remove('hidden');
            if (sections.supportingDocuments) sections.supportingDocuments.classList.remove('hidden');
            if (sections.jobTitleField) sections.jobTitleField.classList.remove('hidden');
            if (sections.companyField) sections.companyField.classList.remove('hidden');
            if (sections.companyAddressField) sections.companyAddressField.classList.remove('hidden');
            if (sections.salaryField) sections.salaryField.classList.remove('hidden');
            if (sections.coeField) sections.coeField.classList.remove('hidden');
            break;
            
        case 'Self-Employed':
            if (sections.employmentDetails) sections.employmentDetails.classList.remove('hidden');
            if (sections.supportingDocuments) sections.supportingDocuments.classList.remove('hidden');
            if (sections.businessTypeField) sections.businessTypeField.classList.remove('hidden');
            if (sections.salaryField) sections.salaryField.classList.remove('hidden');
            if (sections.businessCertField) sections.businessCertField.classList.remove('hidden');
            break;
            
        case 'Student':
            if (sections.studentDetails) sections.studentDetails.classList.remove('hidden');
            if (sections.supportingDocuments) sections.supportingDocuments.classList.remove('hidden');
            if (sections.corField) sections.corField.classList.remove('hidden');
            break;
            
        case 'Employed & Student':
            if (sections.employmentDetails) sections.employmentDetails.classList.remove('hidden');
            if (sections.studentDetails) sections.studentDetails.classList.remove('hidden');
            if (sections.supportingDocuments) sections.supportingDocuments.classList.remove('hidden');
            if (sections.jobTitleField) sections.jobTitleField.classList.remove('hidden');
            if (sections.companyField) sections.companyField.classList.remove('hidden');
            if (sections.companyAddressField) sections.companyAddressField.classList.remove('hidden');
            if (sections.salaryField) sections.salaryField.classList.remove('hidden');
            if (sections.coeField) sections.coeField.classList.remove('hidden');
            if (sections.corField) sections.corField.classList.remove('hidden');
            break;
    }
}

// ===== FORM VALIDATION =====
function validateFormSubmission() {
    // Validate student years
    if (!validateStudentYears()) {
        return false;
    }

    // Profile photo validation
    const profilePhotoInput = document.getElementById('profilePictureInput');
    const hasExistingPhoto = '<?php echo !empty($profile['photo_path']) ? 'true' : 'false'; ?>';

    if (!profilePhotoInput.files.length && hasExistingPhoto === 'false') {
        alert('Please upload your profile picture before submitting.');
        return false;
    }

    // Required fields
    const requiredFields = [
        { field: 'contact_number', message: 'Contact Number is required.' },
        { field: 'employment_status', message: 'Employment Status is required.' },
        { field: 'city', message: 'City is required.' },
        { field: 'state_province', message: 'State/Province is required.' },
        { field: 'country', message: 'Country is required.' },
        { field: 'formatted_address', message: 'Complete Address is required.' }
    ];

    for (const { field, message } of requiredFields) {
        const element = document.querySelector(`[name="${field}"]`);
        if (element && !element.value.trim()) {
            alert(message);
            return false;
        }
    }

    // Employment validation
    const status = document.getElementById('employmentStatusSelect')?.value || '';
    
    if (['Employed', 'Employed & Student'].includes(status)) {
        const jobTitleSelect = document.getElementById('jobTitleSelect');
        if (jobTitleSelect && !jobTitleSelect.value) {
            alert('Job Title is required for this employment status.');
            return false;
        } else if (jobTitleSelect && jobTitleSelect.value === 'Other') {
            const otherTitle = document.querySelector('[name="other_job_title"]');
            if (otherTitle && !otherTitle.value.trim()) {
                alert('Please specify job title if "Other" is selected.');
                return false;
            }
        }
        
        const companyName = document.querySelector('[name="company_name"]');
        if (companyName && !companyName.value.trim()) {
            alert('Company Name is required for this employment status.');
            return false;
        }
        
        const companyAddress = document.querySelector('[name="company_address"]');
        if (companyAddress && !companyAddress.value.trim()) {
            alert('Company Address is required for this employment status.');
            return false;
        }
    }

    // Self-Employed validation
    if (status === 'Self-Employed') {
        const businessTypeSelect = document.getElementById('businessTypeSelect');
        if (businessTypeSelect && !businessTypeSelect.value) {
            alert('Business Type is required for Self-Employed status.');
            return false;
        } else if (businessTypeSelect && businessTypeSelect.value === 'Others (Please specify)') {
            const businessTypeOther = document.querySelector('[name="business_type_other"]');
            if (businessTypeOther && !businessTypeOther.value.trim()) {
                alert('Please specify business type if "Others" is selected.');
                return false;
            }
        }
    }

    return true;
}

// ===== STUDENT YEAR FUNCTIONS =====
function updateStudentYearOptions() {
    const graduationYear = <?php echo !empty($profile['batch_year']) ? $profile['batch_year'] : 'null'; ?>;
    const startYearSelect = document.querySelector('[name="start_year"]');
    const endYearSelect = document.querySelector('[name="end_year"]');
    const status = document.getElementById('employmentStatusSelect')?.value || '';
    
    if (startYearSelect && endYearSelect && ['Student', 'Employed & Student'].includes(status)) {
        const currentYear = new Date().getFullYear();
        
        // Store current selections
        const currentStartYear = startYearSelect.value;
        const currentEndYear = endYearSelect.value;
        
        // Update Start Year dropdown
        startYearSelect.innerHTML = '<option value="">Select Start Year</option>';
        
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

function initializeStudentYearOptions() {
    const employmentStatusSelect = document.getElementById('employmentStatusSelect');
    const startYearSelect = document.querySelector('[name="start_year"]');
    
    if (employmentStatusSelect) {
        employmentStatusSelect.addEventListener('change', updateStudentYearOptions);
    }
    
    if (startYearSelect) {
        startYearSelect.addEventListener('change', updateEndYearOptions);
    }
}

function validateStudentYears() {
    const status = document.getElementById('employmentStatusSelect')?.value || '';
    
    if (['Student', 'Employed & Student'].includes(status)) {
        const startYear = document.querySelector('[name="start_year"]')?.value;
        const endYear = document.querySelector('[name="end_year"]')?.value;
        
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

// ===== ADDRESS FIELD INITIALIZATION =====
function initializeAddressFields() {
    // Auto-generate formatted address when fields change
    const cityField = document.getElementById('city');
    const stateField = document.getElementById('state-province');
    const countryField = document.getElementById('country');
    const addressField = document.getElementById('formatted-address');
    
    function updateFormattedAddress() {
        const city = cityField?.value.trim() || '';
        const state = stateField?.value.trim() || '';
        const country = countryField?.value.trim() || '';
        
        if (city && state && country) {
            // Provide a suggested format
            addressField.value = `${city}, ${state}, ${country}`;
        }
    }
    
    if (cityField) cityField.addEventListener('blur', updateFormattedAddress);
    if (stateField) stateField.addEventListener('blur', updateFormattedAddress);
    if (countryField) countryField.addEventListener('blur', updateFormattedAddress);
}

// Auto-open modal for rejected profiles
<?php if ($auto_open_modal): ?>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('profileUpdateModal');
    if (modal) {
        setTimeout(() => {
            modal.classList.remove('hidden');
            modal.classList.add('show', 'flex');
        }, 100);
    }
});
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>