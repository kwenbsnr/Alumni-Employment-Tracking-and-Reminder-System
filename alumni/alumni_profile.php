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

// Get personal info from users table, address from alumni_address, and photo from alumni_profile
$stmt = $conn->prepare("
    SELECT
        u.email,
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.student_id,
        u.program,
        u.batch_year as year_graduated,
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

        <!-- Status-Specific Message -->
        <div class="bg-blue-100 rounded-xl px-4 py-3 border border-blue-200">
            <p class="text-sm font-medium text-blue-900">Update your personal information, photo, and address</p>
            <p class="text-xs text-blue-700 mt-1">Your contact details, profile photo, and location information</p>
        </div>

        <!-- Action Button - Always Available -->
        <div class="mt-5">
            <button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-300 text-white font-semibold text-base py-4 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-100">
                <i class="fas fa-edit text-lg"></i>
                <span class="tracking-tight">Update Personal Information</span>
            </button>
            <p class="text-center text-xs text-gray-500 mt-3 font-medium">
                Keep your profile photo, contact and address details up to date
            </p>
        </div>
    </div>

    <!-- Show profile cards only when personal data exists -->
    <?php if ($has_personal_data): ?>
        <!-- Personal Information Cards in a single row -->
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
                        <div class="space-y-1">
                            <dt class="font-medium text-gray-500 text-sm mb-1">Date of Birth</dt>
                            <dd class="font-semibold text-gray-700 text-base">
                                <?php echo !empty($profile['date_of_birth']) && $profile['date_of_birth'] != '0000-00-00' ? 
                                    date('M j, Y', strtotime($profile['date_of_birth'])) : 'N/A'; ?>
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="font-medium text-gray-500 text-sm mb-1">Gender</dt>
                            <dd class="font-semibold text-gray-700 text-base"><?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></dd>
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
                    
                    <?php if (!empty($profile['city']) || !empty($profile['street']) || !empty($profile['state_province']) || !empty($profile['country'])): ?>
                        <div class="space-y-4">
                            <!-- Location Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Country</p>
                                    <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['country'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">State/Province</p>
                                    <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['state_province'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 md:col-span-2">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">City</p>
                                    <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($profile['city'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 md:col-span-2">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Street Address</p>
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
                        Location details
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Show empty state when no personal data exists -->
        <div class="bg-white p-8 rounded-xl shadow-lg border-2 border-dashed border-gray-300 text-center">
            <i class="fas fa-user-circle text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-bold text-gray-600 mb-2">Profile Information Unavailable Yet</h3>
            <p class="text-gray-500 mb-4">Your profile information will appear here once you add personal and address details.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Profile Update Modal - Personal Information Only -->
<div id="profileUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-all duration-300 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
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
                
                <!-- Profile Picture Upload Section -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-purple-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-camera text-purple-600 text-sm"></i>
                        </div>
                        Profile Photo
                        <?php if (!empty($profile['photo_path'])): ?>
                            <span class="text-xs font-normal text-green-600 ml-2 bg-green-50 px-2 py-1 rounded flex items-center">
                                <i class="fas fa-check-circle mr-1"></i>Current photo will be replaced
                            </span>
                        <?php endif; ?>
                    </h3>
                    
                    <div class="flex flex-col md:flex-row items-center gap-6">
                        <!-- Photo Preview -->
                        <div class="flex-shrink-0">
                            <div class="relative inline-block">
                                <div class="w-32 h-32 rounded-full overflow-hidden mx-auto border-4 border-gray-300 bg-gray-100">
                                    <img id="profilePreview" src="<?php 
                                        echo !empty($profile['photo_path']) ? 
                                        '../' . htmlspecialchars($profile['photo_path']) . '?v=' . time() : 
                                        'https://placehold.co/128x128/eeeeee/333333?text=Upload+Photo'; 
                                    ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                                <?php if (!empty($profile['photo_path'])): ?>
                                <div class="absolute bottom-2 right-2 bg-green-500 rounded-full p-2 shadow-md">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Upload Controls -->
                        <div class="flex-1">
                            <div class="space-y-3">
                                <div>
                                    <button type="button" id="uploadPictureBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2.5 rounded-lg transition duration-200 shadow-sm hover:shadow flex items-center gap-2">
                                        <i class="fas fa-upload"></i>
                                        <span>Choose Photo</span>
                                    </button>
                                    <input type="file" id="profilePictureInput" name="profile_photo" accept="image/jpeg,image/png,image/jpg" class="hidden">
                                </div>
                                
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                        JPG or PNG format, maximum 2MB
                                    </p>
                                    <p class="flex items-center">
                                        <i class="fas fa-user-circle text-purple-500 mr-2"></i>
                                        Photo will appear in sidebar and profile
                                    </p>
                                    <?php if (empty($profile['photo_path'])): ?>
                                    <p class="flex items-center text-amber-600">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        No profile photo uploaded yet
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-blue-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-user text-blue-600 text-sm"></i>
                        </div>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Personal Details -->
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="<?php echo !empty($profile['first_name']) ? htmlspecialchars($profile['first_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="given-name">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="<?php echo !empty($profile['last_name']) ? htmlspecialchars($profile['last_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="family-name">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" value="<?php echo !empty($profile['middle_name']) ? htmlspecialchars($profile['middle_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="additional-name">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Suffix</label>
                            <input type="text" name="suffix" value="<?php echo !empty($profile['suffix']) ? htmlspecialchars($profile['suffix']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="honorific-suffix">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="date_of_birth" value="<?php echo !empty($profile['date_of_birth']) ? htmlspecialchars($profile['date_of_birth']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="bday">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($profile['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($profile['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($profile['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
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
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Citizenship</label>
                            <input type="text" name="citizenship" value="<?php echo !empty($profile['citizenship']) ? htmlspecialchars($profile['citizenship']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="country">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                            <input type="tel" name="contact_number" value="<?php echo !empty($profile['contact_number']) ? htmlspecialchars($profile['contact_number']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="tel" pattern="[0-9]{5,15}" title="Contact number must be 5-15 digits">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Program <span class="text-red-500">*</span></label>
                            <input type="text" name="program" value="<?php echo !empty($profile['program']) ? htmlspecialchars($profile['program']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="organization">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Batch Year <span class="text-red-500">*</span></label>
                            <input type="number" name="batch_year" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo !empty($profile['batch_year']) ? htmlspecialchars($profile['batch_year']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required>
                        </div>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-green-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-home text-green-600 text-sm"></i>
                        </div>
                        Address Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1 md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Street Address <span class="text-red-500">*</span></label>
                            <input type="text" name="street" value="<?php echo !empty($profile['street']) ? htmlspecialchars($profile['street']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="street-address">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                            <input type="text" name="city" value="<?php echo !empty($profile['city']) ? htmlspecialchars($profile['city']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="address-level2">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">State/Province <span class="text-red-500">*</span></label>
                            <input type="text" name="state_province" value="<?php echo !empty($profile['state_province']) ? htmlspecialchars($profile['state_province']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="address-level1">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                            <input type="text" name="country" value="<?php echo !empty($profile['country']) ? htmlspecialchars($profile['country']) : 'Philippines'; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required autocomplete="country-name">
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
                            <span>Save Personal Information</span>
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