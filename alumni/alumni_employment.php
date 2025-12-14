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
$page_title = "Employment Information";
$active_page = "employment";

// Fetch employment info ONLY
$stmt = $conn->prepare("
    SELECT ei.*, jt.title AS job_title, ei.business_type 
    FROM employment_info ei 
    LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id 
    WHERE ei.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employment = $result->fetch_assoc() ?: [];
$stmt->close();

// Fetch education info
$stmt = $conn->prepare("SELECT * FROM education_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$education = $result->fetch_assoc() ?: [];
$stmt->close();

// Process business_type for display
$business_type = $employment['business_type'] ?? '';
$business_type_other = '';
if (strpos($business_type, 'Others: ') === 0) {
    $business_type_other = substr($business_type, 8);
    $business_type = 'Others (Please specify)';
}

// Fetch employment status from alumni_profile (needed for display only)
$stmt = $conn->prepare("SELECT employment_status FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_status = $result->fetch_assoc() ?: [];
$stmt->close();

$employment_status = $profile_status['employment_status'] ?? '';

// Check if submission period is open
if (!function_exists('isSubmissionPeriodOpen')) {
    require_once dirname(__DIR__) . '/api/utils/deadline.php';
}
$submission_open = isSubmissionPeriodOpen($conn);

ob_start();
?>

<div class="space-y-6 mt-3 mb-5">
    <!-- Status Card for Employment Information -->
    <div id="updateEmploymentBtn" class="
        <?php if ($submission_open): ?>
            bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-300 hover:border-blue-400 shadow-sm hover:shadow-lg cursor-pointer border-t-blue-500
        <?php else: ?>
            bg-gray-50 border-gray-300 cursor-not-allowed opacity-80 border-t-gray-400
        <?php endif; ?>
        rounded-2xl p-5 transition-all duration-300 border-2 border-t-[6px]
    ">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <i class="fas fa-briefcase text-2xl <?php echo $submission_open ? 'text-blue-600' : 'text-gray-500'; ?>"></i>
                <h3 class="text-lg font-bold tracking-tight <?php echo $submission_open ? 'text-blue-900' : 'text-gray-700'; ?>">
                    <?php echo $submission_open ? 'Update Employment Information' : 'Employment Update Locked'; ?>
                </h3>
            </div>
            <?php if ($submission_open): ?>
                <i class="fas fa-arrow-right text-lg text-blue-600 opacity-80"></i>
            <?php endif; ?>
        </div>

        <?php if ($submission_open): ?>
            <div class="bg-blue-50 rounded-xl px-4 py-3 border border-blue-200">
                <p class="text-sm font-medium text-blue-900">Keep your employment information current</p>
                <p class="text-xs text-blue-700 mt-1">Update your job details, employment status, and educational pursuits</p>
            </div>
            <div class="mt-5">
                <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-base py-4 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-100">
                    <i class="fas fa-edit text-lg"></i>
                    <span class="tracking-tight">Update Employment Information</span>
                </button>
            </div>
        <?php else: ?>
            <div class="bg-gray-100 rounded-xl px-4 py-2.5">
                <p class="text-xs font-medium text-gray-700 text-center">Employment updates are temporarily closed by admin</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Employment Information Card -->
    <div class="bg-white rounded-xl shadow-lg border-l-4 border-purple-500">
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-briefcase text-purple-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Employment Information</h3>
            </div>
            
            <?php if (!empty($employment_status)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Employment Status -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-medium text-gray-500 mb-1">Employment Status</p>
                        <p class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($employment_status); ?></p>
                    </div>

                    <?php if (in_array($employment_status, ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
                        <?php if ($employment_status !== 'Self-Employed'): ?>
                            <!-- Employed Details -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Job Title</p>
                                <p class="font-semibold text-gray-800"><?php echo !empty($employment['job_title']) ? htmlspecialchars($employment['job_title']) : 'N/A'; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Company Name</p>
                                <p class="font-semibold text-gray-800"><?php echo !empty($employment['company_name']) ? htmlspecialchars($employment['company_name']) : 'N/A'; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Company Address</p>
                                <p class="font-semibold text-gray-800"><?php echo !empty($employment['company_address']) ? htmlspecialchars($employment['company_address']) : 'N/A'; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Salary Range</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></p>
                            </div>
                        <?php else: ?>
                            <!-- Self-Employed Details -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Business Type</p>
                                <p class="font-semibold text-gray-800">
                                    <?php
                                    $display_business_type = $employment['business_type'] ?? 'N/A';
                                    if (strpos($display_business_type, 'Others: ') === 0) {
                                        $display_business_type = 'Others: ' . substr($display_business_type, 8);
                                    }
                                    echo htmlspecialchars($display_business_type);
                                    ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-1">Monthly Income Range</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (in_array($employment_status, ['Student', 'Employed & Student'])): ?>
                        <!-- Student Details -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm font-medium text-gray-500 mb-1">School Name</p>
                            <p class="font-semibold text-gray-800"><?php echo !empty($education['school_name']) ? htmlspecialchars($education['school_name']) : 'N/A'; ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm font-medium text-gray-500 mb-1">Degree Pursued</p>
                            <p class="font-semibold text-gray-800"><?php echo !empty($education['degree_pursued']) ? htmlspecialchars($education['degree_pursued']) : 'N/A'; ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm font-medium text-gray-500 mb-1">Start Year</p>
                            <p class="font-semibold text-gray-800"><?php echo !empty($education['start_year']) ? htmlspecialchars($education['start_year']) : 'N/A'; ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm font-medium text-gray-500 mb-1">End Year (Expected)</p>
                            <p class="font-semibold text-gray-800"><?php echo !empty($education['end_year']) ? htmlspecialchars($education['end_year']) : 'N/A'; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($employment_status === 'Unemployed'): ?>
                        <div class="md:col-span-2 text-center py-4">
                            <div class="bg-gray-100 rounded-lg p-4">
                                <i class="fas fa-user-clock text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-600 font-medium">Currently seeking employment</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-briefcase text-gray-400 text-2xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No employment information available</p>
                    <p class="text-gray-400 text-sm mt-1">Update your employment information to get started</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Employment Update Modal -->
<div id="employmentUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-all duration-300 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Update Employment Information</h3>
                    <p class="text-gray-600 text-sm mt-0">Update your employment status and related details</p>
                </div>
                <button id="closeEmploymentModal" class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg p-2 transition duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <form id="employmentForm" class="space-y-6" action="update_employment.php" method="post" enctype="multipart/form-data">
                
                <!-- Employment Information -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-purple-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-briefcase text-purple-600 text-sm"></i>
                        </div>
                        Employment Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Employment Status <span class="text-red-500">*</span></label>
                            <select id="employmentStatusSelect" name="employment_status" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" required>
                                <option value="">Select Status</option>
                                <option value="Employed" <?php echo $employment_status === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                <option value="Self-Employed" <?php echo $employment_status === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                <option value="Unemployed" <?php echo $employment_status === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                <option value="Student" <?php echo $employment_status === 'Student' ? 'selected' : ''; ?>>Student</option>
                                <option value="Employed & Student" <?php echo $employment_status === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
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
                            <input type="text" name="company_name" value="<?php echo !empty($employment['company_name']) ? htmlspecialchars($employment['company_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="organization">
                        </div>
                        <div id="companyAddressField" class="hidden space-y-1 md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Company Address</label>
                            <input type="text" name="company_address" value="<?php echo !empty($employment['company_address']) ? htmlspecialchars($employment['company_address']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="street-address">
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
                            <input type="text" name="school_name" value="<?php echo !empty($education['school_name']) ? htmlspecialchars($education['school_name']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="organization">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-gray-700">Degree Pursued</label>
                            <input type="text" name="degree_pursued" value="<?php echo !empty($education['degree_pursued']) ? htmlspecialchars($education['degree_pursued']) : ''; ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm hover:border-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-200" autocomplete="off">
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

                <!-- Supporting Documents Section -->
                <div id="supportingDocumentsSection" class="hidden bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <div class="bg-red-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-file-alt text-red-600 text-sm"></i>
                        </div>
                        Supporting Documents
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- COE Field -->
                        <div id="coeField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                Certificate of Employment (COE)
                                <?php if ($submission_open): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="coe_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>

                        <!-- Business Certificate Field -->
                        <div id="businessCertField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-certificate text-green-500 mr-2"></i>
                                Business Certificate
                                <?php if ($submission_open): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="business_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>

                        <!-- COR Field -->
                        <div id="corField" class="hidden space-y-1">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-file-contract text-purple-500 mr-2"></i>
                                Certificate of Registration (COR)
                                <?php if ($submission_open): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition duration-200 bg-gray-50">
                                <input type="file" name="cor_file" accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">PDF format only</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex justify-between items-center">
                        <div class="text-gray-600">
                            <p class="text-sm font-medium">Ready to update employment information?</p>
                            <p class="text-xs text-gray-500 mt-1">Review all information before submitting</p>
                        </div>
                        <?php if ($submission_open): ?>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-200 shadow-sm hover:shadow flex items-center space-x-2 text-sm">
                                <i class="fas fa-paper-plane"></i>
                                <span>Update Employment Information</span>
                            </button>
                        <?php else: ?>
                            <button type="button" disabled class="bg-gray-400 text-white font-medium py-3 px-8 rounded-lg cursor-not-allowed flex items-center space-x-2 text-sm">
                                <i class="fas fa-lock"></i>
                                <span>Update (Not Available)</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Modal functionality
    const updateEmploymentBtn = document.getElementById('updateEmploymentBtn');
    const updateEmploymentModal = document.getElementById('employmentUpdateModal');
    const closeModalBtn = document.getElementById('closeEmploymentModal');
    
    if (updateEmploymentBtn && updateEmploymentModal) {
        const submissionOpen = <?php echo $submission_open ? 'true' : 'false'; ?>;
        
        if (submissionOpen) {
            updateEmploymentBtn.addEventListener('click', () => {
                updateEmploymentModal.classList.remove('hidden');
                updateEmploymentModal.classList.add('show', 'flex');
            });
        }
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (updateEmploymentModal) {
                updateEmploymentModal.classList.add('hidden');
                updateEmploymentModal.classList.remove('show', 'flex');
            }
        });
    }

    if (updateEmploymentModal) {
        updateEmploymentModal.addEventListener('click', (e) => {
            if (e.target === updateEmploymentModal) {
                updateEmploymentModal.classList.add('hidden');
                updateEmploymentModal.classList.remove('show', 'flex');
            }
        });
    }

    // Employment status toggle functionality
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

    // Form validation
    const employmentForm = document.getElementById('employmentForm');
    if (employmentForm) {
        employmentForm.addEventListener('submit', function(event) {
            if (!validateEmploymentForm()) {
                event.preventDefault();
            }
        });
    }

    // Student year options
    initializeStudentYearOptions();
});

function toggleEmploymentSections(status) {
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

function validateEmploymentForm() {
    const status = document.getElementById('employmentStatusSelect')?.value || '';
    
    if (!status) {
        alert('Employment Status is required.');
        return false;
    }

    // Employment validation
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
        
        const salary = document.querySelector('[name="salary_range"]');
        if (salary && !salary.value) {
            alert('Salary Range is required for this employment status.');
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

    // Education validation
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
    }

    // Document validation
    if (['Employed', 'Employed & Student'].includes(status)) {
        const coeFile = document.querySelector('[name="coe_file"]');
        if (coeFile && !coeFile.files.length) {
            alert('Certificate of Employment (COE) is required for this employment status.');
            return false;
        }
    }
    
    if (status === 'Self-Employed') {
        const businessFile = document.querySelector('[name="business_file"]');
        if (businessFile && !businessFile.files.length) {
            alert('Business Certificate is required for Self-Employed status.');
            return false;
        }
    }
    
    if (['Student', 'Employed & Student'].includes(status)) {
        const corFile = document.querySelector('[name="cor_file"]');
        if (corFile && !corFile.files.length) {
            alert('Certificate of Registration (COR) is required for student status.');
            return false;
        }
    }

    return true;
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

function updateStudentYearOptions() {
    const startYearSelect = document.querySelector('[name="start_year"]');
    const endYearSelect = document.querySelector('[name="end_year"]');
    const status = document.getElementById('employmentStatusSelect')?.value || '';
    
    if (startYearSelect && endYearSelect && ['Student', 'Employed & Student'].includes(status)) {
        const currentYear = new Date().getFullYear();
        
        // Update Start Year dropdown
        startYearSelect.innerHTML = '<option value="">Select Start Year</option>';
        for (let y = currentYear; y >= 2000; y--) {
            const option = document.createElement('option');
            option.value = y;
            option.textContent = y;
            startYearSelect.appendChild(option);
        }
        
        updateEndYearOptions();
    }
}

function updateEndYearOptions() {
    const startYearSelect = document.querySelector('[name="start_year"]');
    const endYearSelect = document.querySelector('[name="end_year"]');
    
    if (startYearSelect && endYearSelect && startYearSelect.value) {
        const startYear = parseInt(startYearSelect.value);
        const currentYear = new Date().getFullYear();
        
        endYearSelect.innerHTML = '<option value="">Select End Year</option>';
        for (let y = startYear + 1; y <= currentYear + 5; y++) {
            const option = document.createElement('option');
            option.value = y;
            option.textContent = y;
            endYearSelect.appendChild(option);
        }
    }
}
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>