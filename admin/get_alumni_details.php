<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id || !is_numeric($user_id)) {
    echo '<div class="text-center py-8 bg-white rounded-xl"><p class="text-red-500 text-lg">Invalid user ID.</p></div>';
    exit();
}

$query = "
    SELECT
        u.name as official_name,
        u.batch_year,
        u.email,
        u.student_id,
        u.date_of_birth,
        u.gender,
        u.program,
        ap.contact_number,
        ap.employment_status,
        ap.photo_path,
        ap.submission_status,
        ap.last_profile_update,
        ei.company_name,
        ei.salary_range,
        jt.title as job_title,
        ei.business_type,
        ei.company_address,
        edu.school_name,
        edu.degree_pursued,
        edu.start_year,
        edu.end_year,
        CONCAT(tb.barangay_name, ', ', tm.municipality_name, ', ', tp.province_name, ', ', tr.region_name) as full_address,
        ad1.file_path as cor_path,
        ad2.file_path as coe_path,
        ad3.file_path as b_cert_path
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN employment_info ei ON u.user_id = ei.user_id
    LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
    LEFT JOIN education_info edu ON u.user_id = edu.user_id
    LEFT JOIN address a ON ap.address_id = a.address_id
    LEFT JOIN table_barangay tb ON a.barangay_id = tb.barangay_id
    LEFT JOIN table_municipality tm ON tb.municipality_id = tm.municipality_id
    LEFT JOIN table_province tp ON tm.province_id = tp.province_id
    LEFT JOIN table_region tr ON tp.region_id = tr.region_id
    LEFT JOIN alumni_documents ad1 ON u.user_id = ad1.user_id AND ad1.document_type = 'COR'
    LEFT JOIN alumni_documents ad2 ON u.user_id = ad2.user_id AND ad2.document_type = 'COE'
    LEFT JOIN alumni_documents ad3 ON u.user_id = ad3.user_id AND ad3.document_type = 'B_CERT'
    WHERE u.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni = $result->fetch_assoc();

// If no profile found
if (!$alumni) {
    echo '<div class="text-center py-8 bg-white rounded-xl"><p class="text-red-500 text-lg">Alumni profile not found.</p></div>';
    exit();
}

// Set safe defaults to prevent any "undefined array key" warnings
$alumni = array_merge([
    'official_name'      => '',
    'email'              => '—',
    'student_id'         => '—',
    'date_of_birth'      => '—',
    'gender'             => '—',
    'program'            => '—',
    'batch_year'         => '—',
    'contact_number'     => '',
    'full_address'       => '',
    'employment_status'  => 'Unemployed',
    'submission_status'  => 'Pending',
    'photo_path'         => '',
    'job_title'          => '',
    'company_name'       => '',
    'salary_range'       => '',
    'company_address'    => '',
    'business_type'      => '',
    'school_name'        => '',
    'degree_pursued'     => '',
    'start_year'         => '',
    'end_year'           => '',
    'cor_path'           => '',
    'coe_path'           => '',
    'b_cert_path'        => '',
    'last_profile_update'=> null
], $alumni);

// Format date of birth if exists
if ($alumni['date_of_birth'] && $alumni['date_of_birth'] !== '—') {
    $alumni['date_of_birth'] = date('F j, Y', strtotime($alumni['date_of_birth']));
}

// Build submitted documents list
$submitted_docs = [];
$employment_status = $alumni['employment_status'] ?? 'Unemployed';

// COR → Student / Employed & Student
if (!empty($alumni['cor_path']) && in_array($employment_status, ['Student', 'Employed & Student'])) {
    $submitted_docs[] = ['type' => 'Certificate of Registration (COR)', 'path' => $alumni['cor_path']];
}

// COE → Employed / Employed & Student
if (!empty($alumni['coe_path']) && in_array($employment_status, ['Employed', 'Employed & Student'])) {
    $submitted_docs[] = ['type' => 'Certificate of Employment (COE)', 'path' => $alumni['coe_path']];
}

// Business Certificate → Self-Employed
if (!empty($alumni['b_cert_path']) && $employment_status === 'Self-Employed') {
    $submitted_docs[] = ['type' => 'Business Certificate', 'path' => $alumni['b_cert_path']];
}

// Birth Certificate → Unemployed (optional)
if (!empty($alumni['b_cert_path']) && $employment_status === 'Unemployed') {
    $submitted_docs[] = ['type' => 'Birth Certificate', 'path' => $alumni['b_cert_path']];
}
?>

<div class="max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 p-6 text-white">
        <div class="flex items-center space-x-6">
            <!-- Profile Photo -->
            <div class="flex-shrink-0">
                <?php if (!empty($alumni['photo_path'])): ?>
                    <img class="h-20 w-20 rounded-full object-cover border-4 border-white/30 shadow-lg"
                         src="../<?php echo htmlspecialchars($alumni['photo_path']); ?>"
                         alt="Profile Photo">
                <?php else: ?>
                    <div class="h-20 w-20 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/30 shadow-lg">
                        <i class="fas fa-user text-white text-2xl"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Name and Basic Info -->
            <div class="flex-1">
                <h1 class="text-2xl font-bold mb-2">
                    <?php echo !empty($alumni['official_name']) ? htmlspecialchars($alumni['official_name']) : 'Name Not Provided'; ?>
                </h1>
                <div class="flex flex-wrap items-center gap-6 text-sm">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($alumni['email']); ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Batch <?php echo htmlspecialchars($alumni['batch_year']); ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo getSubmissionStatusColor($alumni['submission_status']); ?>">
                            <?php echo htmlspecialchars($alumni['submission_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
        
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- School Information -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="fas fa-graduation-cap text-blue-600"></i>
                    <h3 class="text-lg font-semibold text-gray-800">School Information</h3>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Student ID</label>
                            <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-mono">
                                <?php echo htmlspecialchars($alumni['student_id']); ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Program</label>
                            <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                <?php echo htmlspecialchars($alumni['program']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Date of Birth</label>
                            <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                <?php echo htmlspecialchars($alumni['date_of_birth']); ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Gender</label>
                            <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                <?php echo htmlspecialchars($alumni['gender']); ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Year Graduated</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-semibold">
                            <?php echo htmlspecialchars($alumni['batch_year']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="bg-gradient-to-br from-green-50 to-teal-50 rounded-xl p-6 border border-green-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="fas fa-user-circle text-green-600"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Personal Information</h3>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center py-2 border-b border-green-100">
                        <span class="font-medium text-gray-600">Employment Status:</span>
                        <span class="text-gray-800 font-semibold"><?php echo htmlspecialchars($alumni['employment_status']); ?></span>
                    </div>
                    <?php if (!empty($alumni['contact_number'])): ?>
                    <div class="flex justify-between items-center py-2 border-b border-green-100">
                        <span class="font-medium text-gray-600">Contact Number:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($alumni['contact_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($alumni['full_address'])): ?>
                    <div class="flex justify-between items-start py-2">
                        <span class="font-medium text-gray-600">Address:</span>
                        <span class="text-gray-800 text-right max-w-xs"><?php echo htmlspecialchars($alumni['full_address']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Employment Information -->
            <?php if (in_array($alumni['employment_status'], ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="fas fa-briefcase text-purple-600"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Employment Information</h3>
                </div>
                <div class="space-y-3 text-sm">
                    <?php if (in_array($alumni['employment_status'], ['Employed', 'Employed & Student'])): ?>
                        <?php if (!empty($alumni['job_title'])): ?>
                        <div class="flex justify-between items-center py-2 border-b border-purple-100">
                            <span class="font-medium text-gray-600">Job Title:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['job_title']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($alumni['company_name'])): ?>
                        <div class="flex justify-between items-center py-2 border-b border-purple-100">
                            <span class="font-medium text-gray-600">Company Name:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['company_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($alumni['employment_status'] === 'Self-Employed' && !empty($alumni['business_type'])): ?>
                    <div class="flex justify-between items-center py-2 border-b border-purple-100">
                        <span class="font-medium text-gray-600">Business Type:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($alumni['business_type']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($alumni['salary_range'])): ?>
                    <div class="flex justify-between items-center py-2 border-b border-purple-100">
                        <span class="font-medium text-gray-600">Salary Range:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($alumni['salary_range']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($alumni['company_address'])): ?>
                    <div class="flex justify-between items-start py-2">
                        <span class="font-medium text-gray-600">
                            <?php echo $alumni['employment_status'] === 'Self-Employed' ? 'Business Address:' : 'Company Address:'; ?>
                        </span>
                        <span class="text-gray-800 text-right max-w-xs"><?php echo htmlspecialchars($alumni['company_address']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Education Information -->
            <?php if (in_array($alumni['employment_status'], ['Student', 'Employed & Student']) && !empty($alumni['school_name'])): ?>
            <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-6 border border-orange-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="fas fa-university text-orange-600"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Education Information</h3>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center py-2 border-b border-orange-100">
                        <span class="font-medium text-gray-600">School Name:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($alumni['school_name']); ?></span>
                    </div>
                    <?php if (!empty($alumni['degree_pursued'])): ?>
                    <div class="flex justify-between items-center py-2 border-b border-orange-100">
                        <span class="font-medium text-gray-600">Degree Pursued:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($alumni['degree_pursued']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($alumni['start_year']) || !empty($alumni['end_year'])): ?>
                    <div class="flex justify-between items-center py-2">
                        <span class="font-medium text-gray-600">Academic Period:</span>
                        <span class="text-gray-800">
                            <?php echo htmlspecialchars($alumni['start_year']); ?> - <?php echo !empty($alumni['end_year']) ? htmlspecialchars($alumni['end_year']) : 'Present'; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Unemployed Message -->
            <?php if ($alumni['employment_status'] === 'Unemployed'): ?>
            <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-6 border border-gray-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <i class="fas fa-search text-gray-600"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Employment Status</h3>
                </div>
                <div class="text-center py-4">
                    <p class="text-gray-600 font-medium">Currently seeking employment opportunities</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submitted Documents -->
    <?php if (!empty($submitted_docs)): ?>
    <div class="border-t border-gray-200">
        <div class="p-6">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-file-alt text-yellow-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Submitted Documents</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($submitted_docs as $doc): ?>
                <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-lg p-4 border border-yellow-200 hover:border-yellow-300 transition-all duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-pdf text-red-500"></i>
                            <span class="font-medium text-gray-700 text-sm"><?php echo htmlspecialchars($doc['type']); ?></span>
                        </div>
                        <a href="../<?php echo htmlspecialchars($doc['path']); ?>" target="_blank"
                           class="text-blue-600 hover:text-blue-800 flex items-center text-sm bg-white px-3 py-1 rounded-lg border border-blue-200 hover:bg-blue-50 transition-colors shadow-sm">
                            <i class="fas fa-eye mr-2"></i> View
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Last Update -->
    <?php if (!empty($alumni['last_profile_update'])): ?>
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <p class="text-xs text-gray-500 text-center">
            <i class="fas fa-clock mr-1"></i>
            Last updated: <?php echo date('F j, Y g:i A', strtotime($alumni['last_profile_update'])); ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper function for status badge color
function getSubmissionStatusColor($status) {
    return match($status) {
        'Approved' => 'bg-green-100 text-green-800',
        'Pending'  => 'bg-yellow-100 text-yellow-800',
        'Rejected' => 'bg-red-100 text-red-800',
        default    => 'bg-gray-100 text-gray-800',
    };
}
?>