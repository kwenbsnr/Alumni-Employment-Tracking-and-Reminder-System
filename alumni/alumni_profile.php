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

// Fetch profile data
$stmt = $conn->prepare("
    SELECT ap.*, u.email, a.street_details, a.zip_code, 
           tb.barangay_name, tm.municipality_name, tp.province_name, tr.region_name,
           tr.region_id, tp.province_id, tm.municipality_id, tb.barangay_id
    FROM alumni_profile ap
    LEFT JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN address a ON ap.address_id = a.address_id
    LEFT JOIN table_barangay tb ON a.barangay_id = tb.barangay_id
    LEFT JOIN table_municipality tm ON tb.municipality_id = tm.municipality_id
    LEFT JOIN table_province tp ON tm.province_id = tp.province_id
    LEFT JOIN table_region tr ON tp.region_id = tr.region_id
    WHERE ap.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];

// Fetch employment info
$stmt = $conn->prepare("SELECT ei.*, jt.title AS job_title, ei.business_type FROM employment_info ei LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id WHERE ei.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employment = $result->fetch_assoc() ?: [];

// Process business_type for display
$business_type = $employment['business_type'] ?? '';
$business_type_other = '';
if (strpos($business_type, 'Others: ') === 0) {
    $business_type_other = substr($business_type, 8);
    $business_type = 'Others (Please specify)';
} elseif ($business_type === 'Others') {
    $business_type = 'Others (Please specify)';
    $business_type_other = '';
}

// Fetch education info
$stmt = $conn->prepare("SELECT * FROM education_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$education = $result->fetch_assoc() ?: [];

// Fetch documents
$docs = [];
$rejected_docs = [];
$can_reupload = false;
if (!empty($profile['user_id'])) {
    $stmt = $conn->prepare("SELECT document_type, file_path, document_status, rejection_reason, needs_reupload FROM alumni_documents WHERE user_id = ?");
    $stmt->bind_param("i", $profile['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($doc = $result->fetch_assoc()) {
        $docs[] = $doc;
        if ($doc['document_status'] === 'Rejected' && $doc['needs_reupload']) {
            $rejected_docs[] = $doc['document_type'];
            $can_reupload = true;
        }
    }
}

// Check yearly update restriction
$can_update = empty($profile) || ($profile && ($profile['last_profile_update'] === null || strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$can_update = $can_update || $can_reupload;

$full_name = 'Alumni';
if (!empty($profile)) {
    $full_name = trim(
        ($profile['first_name'] ?? '') . ' ' .
        ($profile['middle_name'] ?? '') . ' ' .
        ($profile['last_name'] ?? '')
    );
    if (empty($full_name)) {
        $full_name = 'Alumni';
    }
}

ob_start();
?>

<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Update Profile Box -->
    <div id="updateProfileBtn" class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-green-500 cursor-pointer">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-600">Update Profile</h3>
            <i class="fas fa-user-edit text-xl text-green-500"></i>
        </div>
        <p class="text-sm text-gray-500">Click to edit your personal, employment, and educational details.</p>
    </div>

    <?php if (!empty($profile)): ?>
        <!-- Personal Information Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Personal Information</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Full Name</dt>
                    <dd><?php echo htmlspecialchars($full_name); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Email</dt>
                    <dd><?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Contact Number</dt>
                    <dd><?php echo htmlspecialchars($profile['contact_number'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Year Graduated</dt>
                    <dd><?php echo htmlspecialchars($profile['year_graduated'] ?? 'N/A'); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Address Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Address</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Address</dt>
                    <dd><?php echo htmlspecialchars(
                        ($profile['street_details'] ?? '') . ', ' .
                        ($profile['barangay_name'] ?? '') . ', ' .
                        ($profile['municipality_name'] ?? '') . ', ' .
                        ($profile['province_name'] ?? '') . ', ' .
                        ($profile['region_name'] ?? '') . ' ' .
                        ($profile['zip_code'] ?? '')
                    ); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Employment/Academic Details Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Employment/Academic Details</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Employment Status</dt>
                    <dd><?php echo htmlspecialchars($profile['employment_status'] ?? 'Not Set'); ?></dd>
                </div>
                <?php if (in_array($profile['employment_status'] ?? '', ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
                    <?php if ($profile['employment_status'] !== 'Self-Employed'): ?>
                        <div class="flex justify-between">
                            <dt class="font-medium">Job Title</dt>
                            <dd><?php echo htmlspecialchars($employment['job_title'] ?? 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Company Name</dt>
                            <dd><?php echo htmlspecialchars($employment['company_name'] ?? 'N/A'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($profile['employment_status'] === 'Self-Employed'): ?>
                        <div class="flex justify-between">
                            <dt class="font-medium">Business Type</dt>
                            <dd><?php echo htmlspecialchars($employment['business_type'] ?? 'N/A'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <dt class="font-medium"><?php echo ($profile['employment_status'] === 'Self-Employed') ? 'Monthly Income Range' : 'Salary Range'; ?></dt>
                        <dd><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (in_array($profile['employment_status'] ?? '', ['Student', 'Employed & Student'])): ?>
                    <div class="flex justify-between">
                        <dt class="font-medium">School Name</dt>
                        <dd><?php echo htmlspecialchars($education['school_name'] ?? 'N/A'); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium">Degree Pursued</dt>
                        <dd><?php echo htmlspecialchars($education['degree_pursued'] ?? 'N/A'); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (($profile['employment_status'] ?? '') === 'Unemployed'): ?>
                    <dd>Currently Unemployed</dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Documents Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Documents</h3>
            <?php if (empty($docs)): ?>
                <p class="text-sm text-gray-500">No documents uploaded.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($docs as $doc): ?>
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="font-medium"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                            <span class="text-sm <?php echo $doc['document_status'] === 'Approved' ? 'text-green-600' : ($doc['document_status'] === 'Rejected' ? 'text-red-600' : 'text-yellow-600'); ?>"><?php echo htmlspecialchars($doc['document_status']); ?></span>
                            <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline">View</a>
                        </div>
                        <?php if ($doc['document_status'] === 'Rejected' && $doc['rejection_reason']): ?>
                            <p class="text-sm text-red-600">Rejection Reason: <?php echo htmlspecialchars($doc['rejection_reason']); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Profile Update Modal (Hidden by default) -->
<div id="profileUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white p-8 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Update Your Profile</h3>
            <button id="closeProfileModal" class="text-gray-600 hover:text-red-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <!-- Profile Form -->
        <form id="alumniProfileForm" class="space-y-6" action="update_profile.php" method="post" enctype="multipart/form-data">
            <!-- Profile Picture + Personal Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col items-center">
                    <div class="w-32 h-32 rounded-full overflow-hidden mb-4 border-4 border-gray-200">
                        <img id="profilePreview" src="<?php echo !empty($profile['photo_path']) ? '../' . htmlspecialchars($profile['photo_path']) : 'https://placehold.co/128x128/eeeeee/333333?text=Profile'; ?>" alt="Profile Picture" class="w-full h-full object-cover">
                    </div>
                    <button type="button" id="uploadPictureBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                        Upload New Picture
                    </button>
                    <input type="file" id="profilePictureInput" name="profile_photo" accept="image/jpeg,image/png" class="hidden required">
                </div>

                <!-- Personal Information -->
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name
                            <input type="text" name="first_name" autocomplete="given-name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" class="w-full border rounded-lg p-2"  <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Middle Name
                            <input type="text" name="middle_name" autocomplete="additional-name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name
                            <input type="text" name="last_name" autocomplete="family-name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" class="w-full border rounded-lg p-2"  <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contact Number
                            <input type="text" name="contact_number" autocomplete="tel" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" class="w-full border rounded-lg p-2"  <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year Graduated 
                            <select name="year_graduated" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= 2000; $y--) {
                                    echo "<option value=\"$y\" " . (($profile['year_graduated'] ?? '') == $y ? 'selected' : '') . ">$y</option>";
                                }
                                ?>
                            </select></label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employment Status
                            <select id="employmentStatusSelect" name="employment_status" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <option value="Employed" <?php echo ($profile['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                <option value="Self-Employed" <?php echo ($profile['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                <option value="Unemployed" <?php echo ($profile['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                <option value="Student" <?php echo ($profile['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                                <option value="Employed & Student" <?php echo ($profile['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                            </select></label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <?php if ($can_update): ?>
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Address</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Region
                            <select id="regionSelect" name="region_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <option value="">Select Region</option>
                            </select></label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Province
                            <select id="provinceSelect" name="province_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <option value="">Select Province</option>
                            </select></label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Municipality
                            <select id="municipalitySelect" name="municipality_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <option value="">Select Municipality</option>
                            </select></label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Barangay
                            <select id="barangaySelect" name="barangay_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                <option value="">Select Barangay</option>
                            </select></label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Street Details
                            <input type="text" name="street_details" autocomplete="street-address" value="<?php echo htmlspecialchars($profile['street_details'] ?? ''); ?>" class="w-full border rounded-lg p-2"  <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Zip Code
                            <input type="text" name="zip_code" autocomplete="postal-code" value="<?php echo htmlspecialchars($profile['zip_code'] ?? ''); ?>" class="w-full border rounded-lg p-2"  <?php if (!$can_update) echo 'disabled'; ?>> </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Employment Details Section -->
            <?php if ($can_update): ?>
                <div id="employmentDetailsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Employment Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div id="jobTitleField" class="hidden">
                            <label id="jobTitleLabel" class="block text-sm font-medium text-gray-700">Job Title
                            <input type="text" name="job_title" value="<?php echo htmlspecialchars($employment['job_title'] ?? ''); ?>" class="w-full border rounded-lg p-2"  autocomplete="organization-title"> </label>
                        </div>
                        <div id="companyField" class="hidden">
                            <label id="companyLabel" class="block text-sm font-medium text-gray-700">Company Name
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($employment['company_name'] ?? ''); ?>" class="w-full border rounded-lg p-2"  autocomplete="organization"> </label>
                        </div>
                        <div id="businessTypeField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">Business Type
                            <select id="businessTypeSelect" name="business_type" class="w-full border rounded-lg p-2">
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
                            </select></label>
                            <div id="businessTypeOther" class="hidden mt-2">
                                <label class="block text-sm font-medium text-gray-700">Specify Business Type
                                <input type="text" id="businessTypeOtherInput" name="business_type_other" value="<?php echo htmlspecialchars($business_type_other); ?>" class="w-full border rounded-lg p-2" > </label>
                            </div>
                        </div>
                        <div id="salaryField">
                            <label id="salaryLabel" class="block text-sm font-medium text-gray-700">Salary Range
                            <select name="salary_range" class="w-full border rounded-lg p-2">
                                <option value="Below ₱10,000" <?php echo ($employment['salary_range'] ?? '') === 'Below ₱10,000' ? 'selected' : ''; ?>>Below ₱10,000</option>
                                <option value="₱10,000–₱20,000" <?php echo ($employment['salary_range'] ?? '') === '₱10,000–₱20,000' ? 'selected' : ''; ?>>₱10,000–₱20,000</option>
                                <option value="₱20,000–₱30,000" <?php echo ($employment['salary_range'] ?? '') === '₱20,000–₱30,000' ? 'selected' : ''; ?>>₱20,000–₱30,000</option>
                                <option value="₱30,000–₱40,000" <?php echo ($employment['salary_range'] ?? '') === '₱30,000–₱40,000' ? 'selected' : ''; ?>>₱30,000–₱40,000</option>
                                <option value="₱40,000–₱50,000" <?php echo ($employment['salary_range'] ?? '') === '₱40,000–₱50,000' ? 'selected' : ''; ?>>₱40,000–₱50,000</option>
                                <option value="Above ₱50,000" <?php echo ($employment['salary_range'] ?? '') === 'Above ₱50,000' ? 'selected' : ''; ?>>Above ₱50,000</option>
                            </select></label>
                        </div>
                    </div>
                </div>

                <!-- Unemployed Section -->
                <div id="unemployed_section" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <p>You are currently marked as unemployed.</p>
                </div>



                <!-- Student Details Section -->
                <div id="studentDetailsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Student Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">School Name
                            <input type="text" name="school_name" value="<?php echo htmlspecialchars($education['school_name'] ?? ''); ?>" class="w-full border rounded-lg p-2"  autocomplete="organization"> </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Degree Pursued
                            <input type="text" name="degree_pursued" value="<?php echo htmlspecialchars($education['degree_pursued'] ?? ''); ?>" class="w-full border rounded-lg p-2"  autocomplete="off"> </label>
                        </div>
                    </div>
                </div>

                <!-- Supporting Documents Section -->
                <div id="supportingDocuments" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Supporting Documents</h3>

                    
                <!-- COE Section -->
                <div id="coe_section" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Certificate of Employment (COE)
                    <input type="file" name="coe_file" accept="application/pdf" class="w-full border rounded-lg p-2" > </label>
                </div>

                <!-- Business Section -->
                <div id="business_section" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Business Certificate
                    <input type="file" name="business_file" accept="application/pdf" class="w-full border rounded-lg p-2" > </label>
                </div>

                <!-- COR Section -->
                <div id="cor_section" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Certificate of Registration (COR)
                    <input type="file" name="cor_file" accept="application/pdf" class="w-full border rounded-lg p-2"></label>
                </div>
                
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($can_update || in_array('COE', $rejected_docs)): ?>
                            <div id="coeField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Certificate of Employment (COE)
                                <input type="file" name="coe_file" accept="application/pdf" class="w-full border rounded-lg p-2" ></label>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_update || in_array('B_CERT', $rejected_docs)): ?>
                            <div id="businessCertField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Business Certificate
                                <input type="file" name="business_file" accept="application/pdf" class="w-full border rounded-lg p-2" ></label>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_update || in_array('COR', $rejected_docs)): ?>
                            <div id="corField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Certificate of Registration (COR)
                                <input type="file" name="cor_file" accept="application/pdf" class="w-full border rounded-lg p-2" ></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <div class="flex justify-end">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">Submit</button>
        </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {


    // Modal and form elements
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const updateProfileModal = document.getElementById('profileUpdateModal');
    const closeModalBtn = document.getElementById('closeProfileModal');
    const employmentStatus = document.getElementById('employmentStatusSelect');
    const employedSection = document.getElementById('employmentDetailsSection');
    const selfEmployedSection = document.getElementById('businessTypeField');
    const businessTypeSelect = document.getElementById('businessTypeSelect');
    const businessTypeOtherDiv = document.getElementById('businessTypeOther');
    const unemployedSection = document.getElementById('unemployed_section');
    const studentSection = document.getElementById('studentDetailsSection');
    const coeSection = document.getElementById('coe_section');
    const businessSection = document.getElementById('business_section');
    const corSection = document.getElementById('cor_section');
    const supportingDocsSection = document.getElementById('supportingDocuments');
    const regionSelect = document.getElementById('regionSelect');
    const provinceSelect = document.getElementById('provinceSelect');
    const municipalitySelect = document.getElementById('municipalitySelect');
    const barangaySelect = document.getElementById('barangaySelect');



    // Modal toggle
    updateProfileBtn?.addEventListener('click', () => {
        updateProfileModal?.classList.remove('hidden');
        updateProfileModal?.classList.add('show', 'flex');
    });

    closeModalBtn?.addEventListener('click', () => {
        updateProfileModal?.classList.add('hidden');
        updateProfileModal?.classList.remove('show', 'flex');
    });

    updateProfileModal?.addEventListener('click', (e) => {
        if (e.target === updateProfileModal) {
            updateProfileModal.classList.add('hidden');
            updateProfileModal.classList.remove('show', 'flex');
        }
    });
    

    // Other business type toggle
    businessTypeSelect?.addEventListener('change', () => {
        if (businessTypeSelect.value === 'Others (Please specify)') {
            businessTypeOtherDiv.classList.remove('hidden');
        } else {
            businessTypeOtherDiv.classList.add('hidden');
        }
    });

    // Show the correct state on page load
    if (businessTypeSelect?.value === 'Others (Please specify)') {
        businessTypeOtherDiv.classList.remove('hidden');
    }


    // Employment status toggle
    const jobTitleField = document.getElementById('jobTitleField');
    const companyField = document.getElementById('companyField');

    function toggleEmploymentSections(status) {
    // Hide all sections first
    employedSection?.classList.add('hidden');
    unemployedSection?.classList.add('hidden');
    studentSection?.classList.add('hidden');
    jobTitleField?.classList.add('hidden');
    companyField?.classList.add('hidden');
    selfEmployedSection?.classList.add('hidden');
    coeSection?.classList.add('hidden');
    businessSection?.classList.add('hidden');
    corSection?.classList.add('hidden');

    // Show relevant sections
    if (status === 'Employed') {
        employedSection?.classList.remove('hidden');
        jobTitleField?.classList.remove('hidden');
        companyField?.classList.remove('hidden');
        coeSection?.classList.remove('hidden');
    } else if (status === 'Self-Employed') {
        employedSection?.classList.remove('hidden');
        selfEmployedSection?.classList.remove('hidden');
        businessSection?.classList.remove('hidden');
    } else if (status === 'Unemployed') {
        unemployedSection?.classList.remove('hidden');
    } else if (status === 'Student') {
        studentSection?.classList.remove('hidden');
        corSection?.classList.remove('hidden');
    } else if (status === 'Employed & Student') {
        employedSection?.classList.remove('hidden');
        studentSection?.classList.remove('hidden');
        jobTitleField?.classList.remove('hidden');
        companyField?.classList.remove('hidden');
        coeSection?.classList.remove('hidden');
        corSection?.classList.remove('hidden');
    }

    // Show Supporting Documents if employment status requires it
    if (['Employed', 'Self-Employed', 'Student', 'Employed & Student'].includes(status)) {
        supportingDocsSection?.classList.remove('hidden');
    } else {
        supportingDocsSection?.classList.add('hidden');
    }

}

    

    employmentStatus?.addEventListener('change', () => {
    toggleEmploymentSections(employmentStatus.value);
    });


        // Show sections for default selected value
    if (employmentStatus) {
        toggleEmploymentSections(employmentStatus.value);
    }



    // Address dropdown population
    const basePath = '../assets/js/phil-address/json/'; // Relative from alumni/
    let regionsData, provincesData, citiesMunData, barangaysData;

    async function loadAddressData() {
        try {
            [regionsData, provincesData, citiesMunData, barangaysData] = await Promise.all([
                fetch(basePath + 'regions.json').then(res => {
                    if (!res.ok) throw new Error('Failed to load regions.json: ' + res.status);
                    return res.json();
                }),
                fetch(basePath + 'provinces.json').then(res => {
                    if (!res.ok) throw new Error('Failed to load provinces.json: ' + res.status);
                    return res.json();
                }),
                fetch(basePath + 'city-mun.json').then(res => {
                    if (!res.ok) throw new Error('Failed to load city-mun.json: ' + res.status);
                    return res.json();
                }),
                fetch(basePath + 'barangays.json').then(res => {
                    if (!res.ok) throw new Error('Failed to load barangays.json: ' + res.status);
                    return res.json();
                })
            ]);
            console.log('yeeyy!! Address JSON loaded successfully');
            populateRegions();
        } catch (e) {
            console.error('Error loading address JSON:', e);
            alert('Failed to load address data. Please check console for details or verify JSON file paths in assets/js/phil-address/json/.');
        }
    }

    loadAddressData();

    function populateRegions() {
        if (!regionSelect || !regionsData) return;
        regionSelect.innerHTML = '<option value="">Select Region</option>';
        regionsData.forEach(region => {
            const option = document.createElement('option');
            option.value = region.reg_code;
            option.textContent = region.name;
            regionSelect.appendChild(option);
        });
        <?php if (!empty($profile['region_id'])): ?>
            regionSelect.value = '<?php echo htmlspecialchars($profile['region_id']); ?>';
            filterProvinces();
        <?php endif; ?>
    }

    function filterProvinces() {
        if (!provinceSelect || !provincesData) return;
        provinceSelect.innerHTML = '<option value="">Select Province</option>';
        const regionCode = regionSelect.value;
        provincesData.filter(prov => prov.reg_code === regionCode)
            .forEach(prov => {
                const option = document.createElement('option');
                option.value = prov.prov_code;
                option.textContent = prov.name;
                provinceSelect.appendChild(option);
            });
        <?php if (!empty($profile['province_id'])): ?>
            provinceSelect.value = '<?php echo htmlspecialchars($profile['province_id']); ?>';
            filterMunicipalities();
        <?php endif; ?>
    }

    function filterMunicipalities() {
        if (!municipalitySelect || !citiesMunData) return;
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        const provinceCode = provinceSelect.value;
        citiesMunData.filter(mun => mun.prov_code === provinceCode)
            .forEach(mun => {
                const option = document.createElement('option');
                option.value = mun.mun_code;
                option.textContent = mun.name;
                municipalitySelect.appendChild(option);
            });
        <?php if (!empty($profile['municipality_id'])): ?>
            municipalitySelect.value = '<?php echo htmlspecialchars($profile['municipality_id']); ?>';
            filterBarangays();
        <?php endif; ?>
    }

    function filterBarangays() {
    if (!barangaySelect || !barangaysData) return;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    const municipalityCode = municipalitySelect.value;
    let brgys = barangaysData.filter(brgy => brgy.mun_code === municipalityCode)
        .map(brgy => brgy.name);
    brgys = [...new Set(brgys)].sort(); // Remove duplicates and sort
    brgys.forEach((brgyName, index) => {
        const option = document.createElement('option');
        const brgyId = municipalityCode + String(index + 1).padStart(3, '0');
        option.value = brgyId;
        option.textContent = brgyName;
        barangaySelect.appendChild(option);
        console.log(`Generated barangay_id: ${brgyId} for ${brgyName}`);
    });
    <?php if (!empty($profile['barangay_id'])): ?>
        barangaySelect.value = '<?php echo htmlspecialchars($profile['barangay_id']); ?>';
        if (barangaySelect.value !== '<?php echo htmlspecialchars($profile['barangay_id']); ?>') {
            console.warn('Barangay ID <?php echo htmlspecialchars($profile['barangay_id']); ?> not found in options.');
        }
    <?php endif; ?>
    }


    // Event listeners for cascade
    regionSelect?.addEventListener('change', filterProvinces);
    provinceSelect?.addEventListener('change', filterMunicipalities);
    municipalitySelect?.addEventListener('change', filterBarangays);

    // Profile picture upload button
    const uploadPictureBtn = document.getElementById('uploadPictureBtn');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const profilePreview = document.getElementById('profilePreview');

    uploadPictureBtn?.addEventListener('click', () => {
        profilePictureInput.click(); // Trigger hidden file input
    });

    profilePictureInput?.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                profilePreview.src = e.target.result; // Show preview
            };
            reader.readAsDataURL(file);
        }
    });

    // Custom form validation on submit
   document.getElementById('alumniProfileForm').addEventListener('submit', function(event) {
    const barangaySelect = document.getElementById('barangay');
    const barangayId = barangaySelect.value;
    console.log(`Submitting form with barangay_id: '${barangayId}' (hex: ${Array.from(new TextEncoder().encode(barangayId)).map(b => b.toString(16).padStart(2, '0')).join('')})`);
    console.log('All barangay options:', Array.from(barangaySelect.options).map(opt => `${opt.value}: ${opt.textContent}`));
    const employmentStatus = document.getElementById('employment_status').value;
    console.log(`Employment status: ${employmentStatus}`);

    let isValid = true;
    const status = employmentStatus;
    const streetDetails = document.querySelector('[name="street_details"]').value.trim();
    const zipCode = document.querySelector('[name="zip_code"]').value.trim();

    // Validate address fields for Employed, Self-Employed, Employed & Student
    if (['Employed', 'Self-Employed', 'Employed & Student'].includes(status)) {
        if (!barangayId || !streetDetails || !zipCode) {
            alert('Barangay, Street Details, and Zip Code are required for this employment status.');
            isValid = false;
        }
        // Check if barangay_id is a valid option
        const validOptions = Array.from(barangaySelect.options).map(opt => opt.value);
        if (barangayId && !validOptions.includes(barangayId)) {
            alert('Selected Barangay is invalid. Please choose a valid option.');
            isValid = false;
        }
    }

    // Validate employment fields
    if (['Employed', 'Self-Employed', 'Employed & Student'].includes(status)) {
        const jobTitle = document.querySelector('[name="job_title"]').value.trim();
        const companyName = document.querySelector('[name="company_name"]').value.trim();
        if (!jobTitle || !companyName) {
            alert('Job Title and Company Name are required for this employment status.');
            isValid = false;
        }
    }

    // Validate education fields
    if (['Student', 'Employed & Student'].includes(status)) {
        const schoolName = document.querySelector('[name="school_name"]').value.trim();
        const degreePursued = document.querySelector('[name="degree_pursued"]').value.trim();
        if (!schoolName || !degreePursued) {
            alert('School Name and Degree Pursued are required for this status.');
            isValid = false;
        }
    }

    // Validate business type for Self-Employed
    if (status === 'Self-Employed') {
        const businessType = document.querySelector('[name="business_type"]').value;
        const businessTypeOther = document.querySelector('[name="business_type_other"]').value.trim();
        if (businessType === 'Others' && !businessTypeOther) {
            alert('Please specify business type if "Others" selected.');
            isValid = false;
        }
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Log barangay options on municipality change and verify Adams

municipalitySelect?.addEventListener('change', () => {
    filterBarangays();
    const options = Array.from(barangaySelect.options).map(opt => `${opt.value}: ${opt.textContent}`);
    console.log('Barangay options after change:', options);
    if (!options.some(opt => opt.includes('Adams (Pob.)'))) {
        console.warn('Adams (Pob.) not found for mun_code: ' + municipalitySelect.value);
    }
});
    });
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>