<?php
// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

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

// Get personal info from users table and address from alumni_address
$stmt = $conn->prepare("
    SELECT
        u.email,
        u.student_id,
        u.program,
        u.batch_year,
        u.citizenship,
        u.civil_status,
        u.contact_number,
        u.date_of_birth,
        u.gender,
        u.first_name,
        u.last_name,
        u.middle_name,
        u.suffix,
        ap.photo_path,
        aa.city, 
        aa.state_province, 
        aa.street, 
        aa.country
    FROM users u
    LEFT JOIN alumni_address aa ON u.user_id = aa.user_id
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Helper function to display N/A for empty values
function displayValue($value) {
    return !empty($value) && $value !== '0000-00-00' ? htmlspecialchars($value) : 'N/A';
}

// Calculate age from date of birth
function calculateAge($date_of_birth) {
    if (empty($date_of_birth) || $date_of_birth === '0000-00-00') {
        return 'N/A';
    }
    
    $birth_date = new DateTime($date_of_birth);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    return $age . ' years old';
}

// Build official name for display
$official_name = '';
if (!empty($profile['first_name'])) {
    $official_name = $profile['first_name'];
    if (!empty($profile['middle_name'])) {
        $official_name .= ' ' . $profile['middle_name'];
    }
    $official_name .= ' ' . $profile['last_name'];
    if (!empty($profile['suffix'])) {
        $official_name .= ' ' . $profile['suffix'];
    }
}

// Check if profile has personal data for display 
$has_personal_data = !empty($profile) && 
                     (!empty($profile['contact_number']) || 
                      !empty($profile['city']) || 
                      !empty($profile['country']));

ob_start();
?>

<!-- Success/Error Messages -->
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
    const url = new URL(window.location);
    if (url.searchParams.has('success') || url.searchParams.has('error')) {
        url.searchParams.delete('success');
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    }
});
</script>
<div class="space-y-6 mt-3 mb-5">
    <!-- Profile Management Card - Always Editable -->
    <div id="updateProfileBtn" class="bg-gradient-to-br from-green-50 to-emerald-50 border-green-300 hover:border-green-400 shadow-sm hover:shadow-lg cursor-pointer rounded-2xl p-5 transition-all duration-300 border-2 border-t-[6px] border-t-green-500">
        <!-- Header: Icon + Title -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <i class="fas fa-user-edit text-green-600 text-2xl"></i>
                <h3 class="text-lg font-bold tracking-tight text-green-900">
                    Manage Personal Information
                </h3>
            </div>
            <i class="fas fa-arrow-right text-lg text-green-600 opacity-80"></i>
        </div>

        <!-- Status-Specific Message - Moved below the title -->
        <div class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg mb-4">
            <svg class="w-5 h-5 text-blue-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
            <div>
                <p class="text-sm font-semibold text-blue-900">Update Profile Details</p>
                <p class="text-xs text-blue-600 mt-0.5">Photo, contact, and address</p>
            </div>
        </div>

        <!-- Button with extended width -->
        <div class="mt-4">
            <button type="button" class="w-full bg-emerald-500 hover:bg-emerald-600 focus:ring-emerald-300 text-white font-medium text-sm py-2 px-4 rounded-lg transition-all duration-200 shadow-md flex items-center justify-center gap-2 transform hover:scale-[1.01] active:scale-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="tracking-tight">Update Personal Info</span>
            </button>
        </div>
    </div>
</div>

 <div class="alumnus-profile-container">
    <?php if ($has_personal_data): ?>
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 transition-all duration-300 hover:shadow-2xl flex flex-col h-full">
            <div class="p-6 flex-1">
                <div class="flex items-center space-x-3 mb-6 pb-3 border-b border-gray-100">
                    <i class="fas fa-id-card text-2xl text-blue-600"></i>
                    <h3 class="text-2xl font-bold text-gray-800">Alumnus Profile Overview</h3>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-8">
                        <div>
                            <h4 class="text-lg font-bold text-gray-700 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                                Personal & Contact Details
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                <?php
                                $personal_fields = [
                                    ['label' => 'Full Name', 'value' => !empty($official_name) ? $official_name : 'N/A', 'icon' => 'fas fa-id-badge'],
                                    ['label' => 'Student ID', 'value' => $profile['student_id'] ?? '', 'icon' => 'fas fa-fingerprint'],
                                    ['label' => 'Email Address', 'value' => $profile['email'] ?? '', 'icon' => 'fas fa-envelope'],
                                    ['label' => 'Contact Number', 'value' => $profile['contact_number'] ?? '', 'icon' => 'fas fa-phone'],
                                    ['label' => 'Date of Birth', 'value' => !empty($profile['date_of_birth']) && $profile['date_of_birth'] != '0000-00-00' ? date('M j, Y', strtotime($profile['date_of_birth'])) : 'N/A', 'icon' => 'fas fa-birthday-cake'],
                                    ['label' => 'Age', 'value' => calculateAge($profile['date_of_birth'] ?? ''), 'icon' => 'fas fa-hourglass-half'],
                                    ['label' => 'Gender', 'value' => $profile['gender'] ?? '', 'icon' => 'fas fa-venus-mars'],
                                    ['label' => 'Civil Status', 'value' => $profile['civil_status'] ?? '', 'icon' => 'fas fa-ring'],
                                    ['label' => 'Citizenship', 'value' => $profile['citizenship'] ?? '', 'icon' => 'fas fa-globe'],
                                ];

                                foreach ($personal_fields as $field):
                                ?>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <dt class="font-medium text-gray-500 text-sm flex items-center">
                                        <?php echo $field['label']; ?>
                                    </dt>
                                    <dd class="font-semibold text-gray-800 text-base mt-1">
                                        <?php echo displayValue($field['value']); ?>
                                    </dd>
                                </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>

                        <div>
                            <h4 class="text-lg font-bold text-gray-700 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class="fas fa-graduation-cap text-green-500 mr-2"></i>
                                Academic Record
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <dt class="font-medium text-gray-500 text-sm flex items-center">
                                        Program
                                    </dt>
                                    <dd class="font-semibold text-gray-800 text-base mt-1">
                                        <?php echo displayValue($profile['program'] ?? ''); ?>
                                    </dd>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <dt class="font-medium text-gray-500 text-sm flex items-center">
                                        Batch Year
                                    </dt>
                                    <dd class="font-semibold text-gray-800 text-base mt-1">
                                        <?php echo displayValue($profile['batch_year'] ?? ''); ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div class="lg:col-span-1 border-t lg:border-t-0 lg:border-l border-gray-200 lg:pl-6 pt-6 lg:pt-0">
                        <h4 class="text-lg font-bold text-gray-700 mb-4 pb-2 border-b border-gray-100 flex items-center">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            Current Address
                        </h4>
                        
                        <?php if (!empty($profile['city']) || !empty($profile['street']) || !empty($profile['state_province']) || !empty($profile['country'])): ?>
                            <div class="space-y-4">
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <p class="text-sm font-bold text-red-700 mb-2 flex items-center">
                                        <i class="fas fa-location-dot text-red-500 mr-2"></i>
                                        Full Location
                                    </p>
                                    <p class="text-gray-800 font-medium leading-relaxed">
                                        <?php 
                                            $address_parts = array_filter([
                                                displayValue($profile['street'] ?? ''),
                                                displayValue($profile['city'] ?? ''),
                                                displayValue($profile['state_province'] ?? ''),
                                                displayValue($profile['country'] ?? '')
                                            ], function($v) { return $v !== 'N/A'; });
                                            echo empty($address_parts) ? 'Address not specified' : implode(', ', $address_parts);
                                        ?>
                                    </p>
                                </div>
                                
                                <dl class="grid grid-cols-1 gap-3">
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Country</dt>
                                        <dd class="text-gray-800 font-semibold"><?php echo displayValue($profile['country'] ?? ''); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">State/Province</dt>
                                        <dd class="text-gray-800 font-semibold"><?php echo displayValue($profile['state_province'] ?? ''); ?></dd>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">City/Municipality</dt>
                                        <dd class="text-gray-800 font-semibold"><?php echo displayValue($profile['city'] ?? ''); ?></dd>
                                    </div>
                                    <?php if (!empty($profile['street'])): ?>
                                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                            <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Street/Barangay</dt>
                                            <dd class="text-gray-800 font-semibold"><?php echo displayValue($profile['street'] ?? ''); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <i class="fas fa-map-marked-alt text-gray-400 text-3xl mb-3"></i>
                                <p class="text-gray-500 font-medium">No address recorded</p>
                                <p class="text-gray-400 text-sm mt-1">Please use the 'Update Profile' button to add your current location.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                <p class="text-xs text-gray-600 flex items-center justify-between">
                    <span>
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Information displayed is based on your records.
                    </span>
                    <span class="text-right">
                        Last updated: <span class="font-medium text-gray-800">
                        <?php 
                        // Note: A 'last_updated' field would be ideal here, but using current time as a placeholder for a 'fresh' look.
                        echo date('Y-m-d'); 
                        ?>
                        </span>
                    </span>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white p-10 rounded-xl shadow-lg border-2 border-dashed border-blue-300 text-center">
            <i class="fas fa-user-slash text-blue-400 text-7xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-700 mb-2">Profile Data Incomplete</h3>
            <p class="text-gray-500 mb-4">You have not completed your personal and address profile yet.</p>
            <p class="text-gray-600 font-semibold bg-blue-50 inline-block px-4 py-2 rounded-lg border border-blue-200">
                Please click **Manage Personal Information** above to fill in the required details.
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Profile Update Modal - Personal Information Only -->
<div id="profileUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-all duration-300 p-4">
    <div class="bg-white rounded-xl shadow-0xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Enhanced Header -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Update Personal Information</h3>
                    <p class="text-gray-600 text-sm mt-0">Update your profile photo, contact and address details</p>
                </div>
                <button id="closeProfileModal" class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg p-2 transition duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <form id="alumniProfileForm" class="space-y-6" action="update_profile.php" method="post" enctype="multipart/form-data">
                
           <div class="bg-white p-6 md:p-8 rounded-xl border border-gray-100 shadow-10">
    <h3 class="text-l font-bold text-gray-900 mb-6 flex items-center border-b pb-4">
        <div class="bg-indigo-50 rounded-full p-3 mr-4 ring-2 ring-indigo-100">
            <i class="fas fa-camera text-indigo-600 text-lg"></i>
        </div>
        Profile Photo Management
        <?php if (!empty($profile['photo_path'])): ?>
            <span class="text-xs font-semibold text-green-700 ml-4 bg-green-100 px-3 py-1.5 rounded-full flex items-center transition-all duration-300">
                <i class="fas fa-sync-alt mr-1.5 animate-spin-slow"></i>Current Photo will be replaced
            </span>
        <?php endif; ?>
    </h3>
    
    <div class="flex flex-col lg:flex-row items-center gap-8">
        <div class="flex-shrink-0">
            <div class="relative inline-block group">
                <div class="w-36 h-36 md:w-40 md:h-40 rounded-full overflow-hidden mx-auto border-4 border-indigo-300 bg-gray-50 shadow-inner ring-4 ring-indigo-50 transition-all duration-300 group-hover:border-indigo-500">
                    <img id="profilePreview" src="<?php 
                        echo !empty($profile['photo_path']) ? 
                        '../' . htmlspecialchars($profile['photo_path']) . '?v=' . time() : 
                        'https://placehold.co/160x160/f0f4ff/4338ca?text=Upload+Photo'; 
                    ?>" alt="Profile Picture" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                </div>
                
                <?php if (!empty($profile['photo_path'])): ?>
                <div class="absolute bottom-1 right-1 bg-green-600 rounded-full p-2.5 shadow-xl border-2 border-white transform translate-x-1 translate-y-1 transition-all duration-300 group-hover:scale-110">
                    <i class="fas fa-check text-white text-sm"></i>
                </div>
                <?php else: ?>
                <div class="absolute bottom-1 right-1 bg-amber-500 rounded-full p-2.5 shadow-xl border-2 border-white transform translate-x-1 translate-y-1 transition-all duration-300 group-hover:scale-110">
                    <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex-1 w-full mt-4 lg:mt-0">
            <div class="space-y-4">
                
                <div>
                    <button type="button" id="uploadPictureBtn" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl transition duration-300 shadow-md hover:shadow-lg transform hover:scale-[1.01] flex items-center justify-center gap-3 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                        <i class="fas fa-cloud-upload-alt text-lg"></i>
                        <span>Select New Photo</span>
                    </button>
                    <input type="file" id="profilePictureInput" name="profile_photo" accept="image/jpeg,image/png,image/jpg" class="hidden">
                </div>
                
                <div class="text-sm text-gray-700 space-y-2.5 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    
                    <p class="flex items-start">
                       
                        <span class="font-medium">Format & Size:</span> JPG, PNG, or JPEG allowed. Maximum file size is <span class="font-semibold text-gray-900">2MB</span>.
                    </p>
                    
                
                </div>
            </div>
        </div>
    </div>
</div>


                <!-- Read-Only Student Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-gray-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-graduation-cap text-gray-600 text-sm"></i>
                        </div>
                        Student Information
                        <span class="text-xs font-normal text-gray-600 ml-2 bg-gray-100 px-2 py-1 rounded">Read-Only</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $readonly_fields = [
                            ['label' => 'Student ID', 'value' => displayValue($profile['student_id'] ?? ''), 'name' => 'student_id'],
                            ['label' => 'Email', 'value' => displayValue($profile['email'] ?? ''), 'name' => 'email'],
                            ['label' => 'Program', 'value' => displayValue($profile['program'] ?? ''), 'name' => 'program'],
                            ['label' => 'Batch Year', 'value' => displayValue($profile['batch_year'] ?? ''), 'name' => 'batch_year'],
                            ['label' => 'Citizenship', 'value' => displayValue($profile['citizenship'] ?? ''), 'name' => 'citizenship'],
                            ['label' => 'Date of Birth', 'value' => !empty($profile['date_of_birth']) && $profile['date_of_birth'] != '0000-00-00' ? 
                                date('M j, Y', strtotime($profile['date_of_birth'])) : 'N/A', 'name' => 'date_of_birth'],
                            ['label' => 'Gender', 'value' => displayValue($profile['gender'] ?? ''), 'name' => 'gender'],
                            ['label' => 'Age', 'value' => calculateAge($profile['date_of_birth'] ?? ''), 'name' => 'age_display']
                        ];
                        
                        foreach ($readonly_fields as $field):
                        ?>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700"><?php echo $field['label']; ?></label>
                            <div class="bg-gray-50 rounded-lg p-3 border border-gray-300">
                                <?php if ($field['name'] !== 'age_display'): ?>
                                <input type="hidden" name="<?php echo $field['name']; ?>" value="<?php echo htmlspecialchars($profile[$field['name']] ?? ''); ?>">
                                <?php endif; ?>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($field['value']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-gray-700 text-xs flex items-center">
                            <i class="fas fa-info-circle text-gray-500 mr-2"></i>
                            These fields cannot be modified as they are part of your official student record
                        </p>
                    </div>
                </div>

                <!-- Editable Personal Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-blue-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-user-edit text-blue-600 text-sm"></i>
                        </div>
                        Editable Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Editable Name Fields -->
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="<?php echo !empty($profile['first_name']) ? htmlspecialchars($profile['first_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="given-name">
                            <!--
                            <p class="text-xs text-gray-500 mt-1">Your legal first name</p>
                            -->
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" value="<?php echo !empty($profile['middle_name']) ? htmlspecialchars($profile['middle_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="additional-name">
                            <!--
                            <p class="text-xs text-gray-500 mt-1">Your middle name or maiden name</p>
                            -->
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="<?php echo !empty($profile['last_name']) ? htmlspecialchars($profile['last_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="family-name">
                            <p class="text-xs text-gray-500 mt-1">Your last name (may change after marriage)</p>
                        </div>
                        
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Suffix</label>
                            <input type="text" name="suffix" value="<?php echo !empty($profile['suffix']) ? htmlspecialchars($profile['suffix']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="honorific-suffix" placeholder="e.g., Jr., Sr., III">
                            <!--
                            <p class="text-xs text-gray-500 mt-1">Optional suffix (Jr., Sr., III, etc.)</p>
                            -->
                        </div>
                        
                        <!-- Editable Contact Information -->
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                            <input type="tel" name="contact_number" 
                                   value="<?php echo !empty($profile['contact_number']) ? htmlspecialchars($profile['contact_number']) : ''; ?>" 
                                   class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" 
                                   required autocomplete="tel" 
                                   pattern="[0-9]{5,15}" 
                                   title="Contact number must be 5-15 digits">
                            <p class="text-xs text-gray-500 mt-1">Enter your current phone number</p>
                        </div>
                        
                        <!-- Editable Civil Status -->
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Civil Status</label>
                            <select name="civil_status" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200">
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo ($profile['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($profile['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($profile['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo ($profile['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                <option value="Divorced" <?php echo ($profile['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Your current civil status</p>
                        </div>
                    </div>
                </div>
                
                <!-- Editable Address Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-green-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-home text-green-600 text-sm"></i>
                        </div>
                        Address Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Country</label>
                            <input type="text" name="country" value="<?php echo !empty($profile['country']) ? htmlspecialchars($profile['country']) : 'Philippines'; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="country-name">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current (default: Philippines)</p>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">State/Province</label>
                            <input type="text" name="state_province" value="<?php echo !empty($profile['state_province']) ? htmlspecialchars($profile['state_province']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="address-level1" placeholder="State or Province">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current</p>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" name="city" value="<?php echo !empty($profile['city']) ? htmlspecialchars($profile['city']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="address-level2" placeholder="City or Municipality">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current</p>
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Street Address</label>
                            <input type="text" name="street" value="<?php echo !empty($profile['street']) ? htmlspecialchars($profile['street']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="street-address" placeholder="House No., Street, Barangay">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current address</p>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex justify-between items-center">
                        <div class="text-gray-600">
                            <p class="text-sm font-medium">Ready to update your personal information?</p>
                            <p class="text-xs text-gray-500 mt-1">Review all information before submitting</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-200 shadow-sm hover:shadow flex items-center space-x-2 text-sm">
                            <i class="fas fa-paper-plane"></i>
                            <span>Validate Personal Information</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
            const validTypes = ["image/jpeg", "image/jpg", "image/png"];
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
    
    // Always allow opening the modal for personal info updates
    updateProfileBtn.addEventListener('click', () => {
        updateProfileModal.classList.remove('hidden');
        updateProfileModal.classList.add('show', 'flex');
    });

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
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>