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

// CORRECTED QUERY - Fixed table joins and removed MAX() on document_status
$query = "
    SELECT
        CONCAT(
            u.first_name,
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.batch_year,
        u.email,
        u.student_id,
        u.date_of_birth,
        u.gender,
        u.program,
        u.contact_number,
        u.citizenship,
        u.civil_status,
        ap.employment_status,
        ap.photo_path,
        ap.last_profile_update,
        ap.submitted_at,
        -- Get latest employment info (if exists)
        (SELECT company_name FROM employment_info WHERE user_id = u.user_id ORDER BY employment_id DESC LIMIT 1) as company_name,
        (SELECT salary_range FROM employment_info WHERE user_id = u.user_id ORDER BY employment_id DESC LIMIT 1) as salary_range,
        (SELECT business_type FROM employment_info WHERE user_id = u.user_id ORDER BY employment_id DESC LIMIT 1) as business_type,
        (SELECT company_address FROM employment_info WHERE user_id = u.user_id ORDER BY employment_id DESC LIMIT 1) as company_address,
        -- Get job title through join
        (SELECT jt.title FROM employment_info ei 
         LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id 
         WHERE ei.user_id = u.user_id ORDER BY ei.employment_id DESC LIMIT 1) as job_title,
        -- Get latest education info (if exists)
        (SELECT school_name FROM education_info WHERE user_id = u.user_id ORDER BY education_id DESC LIMIT 1) as school_name,
        (SELECT degree_pursued FROM education_info WHERE user_id = u.user_id ORDER BY education_id DESC LIMIT 1) as degree_pursued,
        (SELECT start_year FROM education_info WHERE user_id = u.user_id ORDER BY education_id DESC LIMIT 1) as start_year,
        (SELECT end_year FROM education_info WHERE user_id = u.user_id ORDER BY education_id DESC LIMIT 1) as end_year,
        -- Get address info
        (SELECT city FROM alumni_address WHERE user_id = u.user_id LIMIT 1) as city,
        (SELECT state_province FROM alumni_address WHERE user_id = u.user_id LIMIT 1) as state_province,
        (SELECT country FROM alumni_address WHERE user_id = u.user_id LIMIT 1) as country,
        (SELECT street FROM alumni_address WHERE user_id = u.user_id LIMIT 1) as street,
        -- Get overall document status (check if any are pending)
        CASE 
            WHEN EXISTS (SELECT 1 FROM alumni_documents WHERE user_id = u.user_id AND document_status = 'Rejected') THEN 'Rejected'
            WHEN EXISTS (SELECT 1 FROM alumni_documents WHERE user_id = u.user_id AND document_status = 'Pending') THEN 'Pending'
            WHEN EXISTS (SELECT 1 FROM alumni_documents WHERE user_id = u.user_id AND document_status = 'Approved') THEN 'Approved'
            ELSE 'No Documents'
        END as document_status
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
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

// Get documents separately
$docQuery = "SELECT document_type, file_path, document_status FROM alumni_documents WHERE user_id = ?";
$docStmt = $conn->prepare($docQuery);
$docStmt->bind_param('i', $user_id);
$docStmt->execute();
$docResult = $docStmt->get_result();
$documents = $docResult->fetch_all(MYSQLI_ASSOC);

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
    'citizenship'        => '—',
    'civil_status'       => '—',
    'city'               => '',
    'state_province'     => '',
    'country'            => '',
    'street'             => '',
    'employment_status'  => 'Unemployed',
    'document_status'    => 'Pending',
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
    'last_profile_update'=> null,
    'submitted_at'       => null
], $alumni);

// --- Age Calculation and Date Formatting ---

$age = '—';
$formatted_dob = '—';

if ($alumni['date_of_birth'] && $alumni['date_of_birth'] !== '—') {
    $dob_date = new DateTime($alumni['date_of_birth']);
    $today = new DateTime('today');

    // Calculate Age
    $age_interval = $dob_date->diff($today);
    $age = $age_interval->y;

    // Format Date of Birth
    $formatted_dob = $dob_date->format('F j, Y');
}
$alumni['age'] = $age;
$alumni['date_of_birth'] = $formatted_dob; // Update for display

// --- Address Formatting ---
$address_parts = [];
if (!empty($alumni['street'])) $address_parts[] = $alumni['street'];
if (!empty($alumni['city'])) $address_parts[] = $alumni['city'];
if (!empty($alumni['state_province'])) $address_parts[] = $alumni['state_province'];
if (!empty($alumni['country'])) $address_parts[] = $alumni['country'];
$formatted_address = implode(', ', $address_parts);
?>

<div class="max-w-4xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
    <div class="bg-gray-700 p-6 text-white">
        <div class="flex justify-between items-start">
            <div class="flex items-center space-x-6">
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

                <div class="flex-1">
                    <h1 class="text-2xl font-bold mb-1">
                        <?php echo !empty($alumni['official_name']) ? htmlspecialchars($alumni['official_name']) : 'Name Not Provided'; ?>
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="flex items-center space-x-1">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($alumni['email']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end text-right">
                <div class="text-3xl font-bold text-white/90 mb-2">
                    Batch <?php echo htmlspecialchars($alumni['batch_year']); ?>
                </div>
                <div class="px-3 py-1 text-sm font-semibold rounded-full <?php echo getSubmissionStatusColor($alumni['document_status']); ?>">
                    <?php echo htmlspecialchars($alumni['document_status']); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6 p-6">

        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-user-circle text-blue-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Personal & Contact Information</h3>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Full Name</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-semibold">
                            <?php echo !empty($alumni['official_name']) ? htmlspecialchars($alumni['official_name']) : '—'; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Student ID</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-mono">
                            <?php echo htmlspecialchars($alumni['student_id']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Citizenship</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                            <?php echo htmlspecialchars($alumni['citizenship']); ?>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Date of Birth</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                            <?php echo htmlspecialchars($alumni['date_of_birth']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Age</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-semibold">
                            <?php echo htmlspecialchars($alumni['age']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Gender</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                            <?php echo htmlspecialchars($alumni['gender']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Civil Status</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                            <?php echo htmlspecialchars($alumni['civil_status']); ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($alumni['contact_number'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Contact Number</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['contact_number']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-50 to-cyan-50 rounded-xl p-6 border border-teal-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-graduation-cap text-teal-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Alumni Academic Record</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Program Graduated</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-semibold">
                        <?php echo htmlspecialchars($alumni['program']); ?>
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
        
        <?php if (!empty($formatted_address)): ?>
        <div class="bg-gradient-to-br from-green-50 to-lime-50 rounded-xl p-6 border border-green-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-address-card text-green-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Address Information</h3>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-600 mb-1">Complete Address</label>
                <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                    <?php echo htmlspecialchars($formatted_address); ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if (!empty($alumni['street'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Street</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['street']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($alumni['city'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">City</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['city']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($alumni['state_province'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">State/Province</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['state_province']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($alumni['country'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Country</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['country']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-briefcase text-purple-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Employment Information</h3>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Employment Status</label>
                        <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800 font-semibold">
                            <?php echo htmlspecialchars($alumni['employment_status']); ?>
                        </div>
                    </div>
                </div>

                <?php if (in_array($alumni['employment_status'], ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php if (in_array($alumni['employment_status'], ['Employed', 'Employed & Student']) && !empty($alumni['job_title'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Job Title</label>
                                <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                    <?php echo htmlspecialchars($alumni['job_title']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($alumni['employment_status'], ['Employed', 'Employed & Student']) && !empty($alumni['company_name'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Company Name</label>
                                <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                    <?php echo htmlspecialchars($alumni['company_name']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($alumni['employment_status'] === 'Self-Employed' && !empty($alumni['business_type'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Business Type</label>
                                <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                    <?php echo htmlspecialchars($alumni['business_type']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($alumni['salary_range'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Salary Range</label>
                                <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                    <?php echo htmlspecialchars($alumni['salary_range']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($alumni['company_address'])): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">
                                <?php echo $alumni['employment_status'] === 'Self-Employed' ? 'Business Address' : 'Company Address'; ?>
                            </label>
                            <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                                <?php echo htmlspecialchars($alumni['company_address']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($alumni['employment_status'] === 'Unemployed'): ?>
                    <div class="text-center py-4 bg-white rounded-lg border border-gray-200">
                        <p class="text-gray-600 font-medium">Currently seeking employment opportunities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($alumni['employment_status'], ['Student', 'Employed & Student']) && !empty($alumni['school_name'])): ?>
        <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-6 border border-orange-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-university text-orange-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Latest Education Information</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">School Name</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['school_name']); ?>
                    </div>
                </div>
                <?php if (!empty($alumni['degree_pursued'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Degree Pursued</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['degree_pursued']); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($alumni['start_year']) || !empty($alumni['end_year'])): ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Academic Period</label>
                    <div class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo htmlspecialchars($alumni['start_year']); ?> - <?php echo !empty($alumni['end_year']) ? htmlspecialchars($alumni['end_year']) : 'Present'; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($documents)): ?>
        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-6 border border-yellow-200 shadow-sm">
            <div class="flex items-center space-x-2 mb-4">
                <i class="fas fa-file-alt text-yellow-600"></i>
                <h3 class="text-lg font-semibold text-gray-800">Submitted Documents</h3>
            </div>
            <div class="space-y-3">
                <?php foreach ($documents as $doc): ?>
                <div class="bg-white rounded-lg p-4 border border-yellow-200 hover:border-yellow-300 transition-all duration-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file-pdf text-red-500 text-xl"></i>
                            <div>
                                <span class="font-medium text-gray-700 text-base">
                                    <?php 
                                    $doc_names = ['COR' => 'Certificate of Registration', 'COE' => 'Certificate of Employment', 'B_CERT' => 'Business Certificate'];
                                    echo htmlspecialchars($doc_names[$doc['document_type']] ?? $doc['document_type']);
                                    ?>
                                </span>
                                <div class="text-xs <?= getSubmissionStatusColor($doc['document_status']) ?> px-2 py-0.5 rounded-full inline-block mt-1">
                                    <?php echo htmlspecialchars($doc['document_status']); ?>
                                </div>
                            </div>
                        </div>
                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank"
                           class="text-blue-600 hover:text-blue-800 flex items-center text-sm bg-blue-50 px-3 py-1 rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors">
                            <i class="fas fa-eye mr-2"></i> View
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php if (!empty($alumni['last_profile_update'])): ?>
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <p class="text-xs text-gray-500 text-center">
            <i class="fas fa-clock mr-1"></i>
            Last updated: <?php echo date('F j, Y g:i A', strtotime($alumni['last_profile_update'])); ?>
            <?php if (!empty($alumni['submitted_at'])): ?>
                <br><i class="fas fa-paper-plane mr-1"></i>
                Submitted: <?php echo date('F j, Y g:i A', strtotime($alumni['submitted_at'])); ?>
            <?php endif; ?>
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