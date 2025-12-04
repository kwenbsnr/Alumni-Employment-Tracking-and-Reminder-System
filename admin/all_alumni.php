<?php
// all_alumni.php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

$page_title = "All Alumni Records";
$active_page = "alumni_management";

// Get search parameter
$search = $_GET['search'] ?? '';
$employment_status = $_GET['employment_status'] ?? '';
$submission_status = $_GET['submission_status'] ?? '';

// Fetch all alumni records with filters - UPDATED to include ALL alumni users
$whereConditions = ["u.role = 'alumni'"]; // Only alumni role users
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term]);
    $types .= 'ss';
}

if (!empty($employment_status)) {
    $whereConditions[] = "ap.employment_status = ?";
    $params[] = $employment_status;
    $types .= 's';
}

if (!empty($submission_status)) {
    $whereConditions[] = "ap.submission_status = ?";
    $params[] = $submission_status;
    $types .= 's';
}

$whereClause = implode(" AND ", $whereConditions);

$query = "
    SELECT 
        u.user_id,
        CONCAT(
            u.first_name,
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as name,
        u.batch_year,
        ap.employment_status,
        ap.submission_status,
        ap.photo_path,
        u.email,
        COUNT(ad.doc_id) as document_count
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    LEFT JOIN alumni_documents ad ON u.user_id = ad.user_id
    WHERE $whereClause
    GROUP BY u.user_id
    ORDER BY u.batch_year DESC, name ASC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Group alumni by batch year - FIXED
$alumniByBatch = [];
$batchYears = [];

while ($alumni = $result->fetch_assoc()) {
    $batchYear = $alumni['batch_year'] ?? 'Unknown';
    if (!isset($alumniByBatch[$batchYear])) {
        $alumniByBatch[$batchYear] = [];
        $batchYears[] = $batchYear;
    }
    $alumniByBatch[$batchYear][] = $alumni;
}

// Sort batch years in descending order (newest first)
rsort($batchYears);

ob_start();
?>

<div class="space-y-4">
 <!-- Fixed Header -->
<div class="bg-white p-3 rounded-xl shadow border sticky top-4 z-40 mb-5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="alumni_management.php" class="bg-gray-500 text-white p-2 rounded-lg hover:bg-gray-600">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Complete Alumni Database</h1>
                <p class="text-gray-600">Viewing all alumni records in the system</p>
            </div>
        </div>
        <div class="bg-purple-100 text-purple-800 px-4 py-2 rounded-lg font-semibold">
            Total Records: <?= $result->num_rows ?>
        </div>
    </div>
</div>
    <!-- Search and Filters -->
    <div class="bg-white p-4 rounded-xl shadow border">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            
            <!-- Search -->
            <div class="flex-1 min-w-0">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Name or Email</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="Enter name or email...">
            </div>
            
            <!-- Employment Status -->
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select name="employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="Unemployed" <?= $employment_status == 'Unemployed' ? 'selected' : '' ?>>Unemployed</option>
                    <option value="Self-Employed" <?= $employment_status == 'Self-Employed' ? 'selected' : '' ?>>Self-Employed</option>
                    <option value="Employed" <?= $employment_status == 'Employed' ? 'selected' : '' ?>>Employed</option>
                    <option value="Student" <?= $employment_status == 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="Employed & Student" <?= $employment_status == 'Employed & Student' ? 'selected' : '' ?>>Employed & Student</option>
                </select>
            </div>
            
            <!-- Submission Status -->
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Submission Status</label>
                <select name="submission_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="Pending" <?= $submission_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Approved" <?= $submission_status == 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Rejected" <?= $submission_status == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                    Apply Filters
                </button>
                <a href="all_alumni.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors whitespace-nowrap">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Alumni Records Grouped by Batch -->
    <?php if (count($batchYears) > 0): ?>
        <?php foreach ($batchYears as $batchYear): ?>
            <?php $batchAlumni = $alumniByBatch[$batchYear]; ?>
            <div class="bg-white rounded-xl shadow border overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">
                        Batch <?= $batchYear ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (<?= count($batchAlumni) ?> alumni)
                        </span>
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumni</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($batchAlumni as $alumni): ?>
                                <?php
                                // Fetch documents for this alumni
                                $docQuery = "SELECT document_type, file_path FROM alumni_documents WHERE user_id = ?";
                                $docStmt = $conn->prepare($docQuery);
                                $docStmt->bind_param('i', $alumni['user_id']);
                                $docStmt->execute();
                                $docResult = $docStmt->get_result();
                                $documents = [];
                                while ($doc = $docResult->fetch_assoc()) {
                                    $documents[] = $doc;
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <?php if (!empty($alumni['photo_path'])): ?>
                                                    <img class="h-10 w-10 rounded-full object-cover" src="../<?= $alumni['photo_path'] ?>" alt="">
                                                <?php else: ?>
                                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-500"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 alumni-name-hover" 
                                                     data-user-id="<?= $alumni['user_id'] ?>">
                                                    <?= htmlspecialchars($alumni['name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($alumni['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                   <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full 
                                            <?= empty($alumni['employment_status']) ? 'bg-gray-100 text-gray-800 border border-gray-200' : getEmploymentStatusColor($alumni['employment_status']) ?> 
                                            <?= empty($alumni['employment_status']) ? 'border-gray-200' : getEmploymentStatusBorder($alumni['employment_status']) ?>
                                            shadow-sm">
                                            <i class="<?= empty($alumni['employment_status']) ? 'fas fa-user-clock text-gray-600' : getEmploymentStatusIcon($alumni['employment_status']) ?> mr-2 mt-0.5"></i>
                                            <?= empty($alumni['employment_status']) ? 'No Profile' : $alumni['employment_status'] ?>
                                        </span>
                                    </div>
                                    </td><td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full 
                                            <?= empty($alumni['submission_status']) ? 'bg-gray-100 text-gray-800 border border-gray-200' : getSubmissionStatusColor($alumni['submission_status']) ?> 
                                            <?= empty($alumni['submission_status']) ? 'border-gray-200' : getSubmissionStatusBorder($alumni['submission_status']) ?>
                                            shadow-sm">
                                            <i class="<?= empty($alumni['submission_status']) ? 'fas fa-user-clock text-gray-600' : getSubmissionStatusIcon($alumni['submission_status']) ?> mr-2 mt-0.5"></i>
                                            <?= empty($alumni['submission_status']) ? 'No Profile' : $alumni['submission_status'] ?>
                                        </span>
                                    </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php if (!empty($documents)): ?>
                                            <div class="space-y-1">
                                                <?php foreach ($documents as $doc): ?>
                                                    <?php
                                                    $doc_types = [
                                                        'COR' => 'Certificate of Registration',
                                                        'COE' => 'Certificate of Employment', 
                                                        'B_CERT' => 'Business Certificate'
                                                    ];
                                                    $doc_name = $doc_types[$doc['document_type']] ?? $doc['document_type'];
                                                    ?>
                                                    <div class="flex items-center hover:bg-gray-50 rounded px-2 py-1 transition-colors">
                                                        <span class="font-semibold text-gray-800 text-sm"><?= $doc_name ?></span>
                                                        <a href="../<?= $doc['file_path'] ?>" target="_blank" 
                                                        class="text-blue-600 hover:text-blue-800 flex items-center text-sm font-semibold ml-2">
                                                            <i class="fas fa-external-link-alt mr-1"></i> View
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">No documents</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if (empty($alumni['submission_status']) || $alumni['submission_status'] == 'No Profile'): ?>
                                            <div class="flex justify-left">
                                                <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200 shadow-sm">
                                                    <i class="fas fa-user-clock mr-2 mt-0.5 text-gray-600"></i>
                                                    No Profile
                                                </span>
                                            </div>
                                        <?php elseif ($alumni['submission_status'] == 'Pending'): ?>
                                            <div class="flex gap-2">
                                                <button onclick="showApproveModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name']) ?>')" 
                                                        class="text-green-600 hover:text-green-900 px-3 py-1 border border-green-600 rounded-lg hover:bg-green-50 transition-colors">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button onclick="showRejectModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name']) ?>', '<?= $alumni['employment_status'] ?>')" 
                                                        class="text-red-600 hover:text-red-900 px-3 py-1 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex justify-left">
                                                <button onclick="showRevertModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name']) ?>')" 
                                                        class="text-orange-600 hover:text-orange-900 px-3 py-1 border border-orange-600 rounded-lg hover:bg-orange-50 transition-colors">
                                                    <i class="fas fa-undo mr-1"></i> Undo
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <div class="px-6 py-12 text-center">
                <i class="fas fa-users text-4xl text-gray-400 mb-3"></i>
                <h3 class="text-lg font-medium text-gray-900">No alumni records found</h3>
                <p class="text-gray-500 mt-1">
                    <?= (!empty($search) || !empty($employment_status) || !empty($submission_status)) ? 'No records match your search criteria.' : 'There are no alumni records in the system yet.' ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Approval Confirmation Modal -->
<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Confirm Approval</h3>
            <p class="text-gray-600 text-center mb-6">
                Are you sure you want to approve <span id="approveAlumniName" class="font-semibold"></span>'s profile?
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="closeApproveModal()" 
                        class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="processApproval()" 
                        class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">
                    Confirm Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-times text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Reject Profile</h3>
            <p class="text-gray-600 text-center mb-4">
                Please select a reason for rejecting <span id="rejectAlumniName" class="font-semibold"></span>'s profile:
            </p>
            
            <form id="rejectForm">
                <input type="hidden" id="rejectUserId" name="user_id">
                
                <!-- Common Reasons -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Common Issues</label>
                    <div class="space-y-2" id="commonReasons">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Custom Reason -->
                <div class="mb-4">
                    <label for="customReason" class="block text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
                    <textarea id="customReason" name="custom_reason" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                              placeholder="Add any additional notes or specific reasons..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeRejectModal()" 
                            class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Revert to Pending Modal -->
<div id="revertModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-orange-100 rounded-full mb-4">
                <i class="fas fa-undo text-orange-600 text-xl"></i>
           </div>
<h3 class="text-lg font-bold text-gray-900 text-center mb-2">Undo Action</h3>
<p class="text-gray-600 text-center mb-6">
    Are you sure you want to undo this action and return <span id="revertAlumniName" class="font-semibold"></span>'s profile to its previous status?
</p>

            <div class="flex gap-3">
                <button type="button" onclick="closeRevertModal()" 
                        class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="processRevert()" 
                        class="flex-1 bg-orange-600 text-white py-2 px-4 rounded-lg hover:bg-orange-700 transition-colors">
                    Confirm Action
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alumni Details Modal -->
<div id="alumniModal" class="fixed inset-0 flex items-center justify-center hidden z-50 pointer-events-none">
    <div class="pointer-events-auto max-w-4xl w-full mx-4">
        <div id="alumniModalContent" class="bg-white rounded-xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
// Global variables
let currentUserId = null;
let hoverTimeout;
let isModalHovered = false;

// Employment status specific rejection reasons
const rejectionReasons = {
    'Unemployed': [
    ],
    'Self-Employed': [
        'Missing Business permit document',
        'Incorrect document submitted',
        'Unclear business description',
    ],
    'Employed': [
        'Missing Certificate of Employment document',
        'Incomplete company information',
        'Job position details unclear',
    ],
    'Student': [
        'Missing Certificate of Registration document',
        'Incomplete institution details',
        'Degree pursued information unclear',
    ],
    'Employed & Student': [
        'Missing COE or COR documents',
        'Insufficient/incorrect supporting documents for both statuses'
    ]
};

// Approval Modal Functions
function showApproveModal(userId, alumniName) {
    currentUserId = userId;
    document.getElementById('approveAlumniName').textContent = alumniName;
    document.getElementById('approveModal').classList.remove('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    currentUserId = null;
}

function processApproval() {
    if (currentUserId) {
        window.location.href = `update_status.php?user_id=${currentUserId}&status=Approved`;
    }
}

// Rejection Modal Functions
function showRejectModal(userId, alumniName, employmentStatus) {
    currentUserId = userId;
    document.getElementById('rejectAlumniName').textContent = alumniName;
    document.getElementById('rejectUserId').value = userId;
    
    // Populate rejection reasons based on employment status
    const commonReasonsContainer = document.getElementById('commonReasons');
    const customReason = document.getElementById('customReason');
    
    commonReasonsContainer.innerHTML = '';
    
    // Special handling for Unemployed status - show only textarea
    if (employmentStatus === 'Unemployed') {
        commonReasonsContainer.style.display = 'none';
        document.querySelector('label[for="customReason"]').textContent = 'Reason for rejection:';
        customReason.placeholder = 'Please specify the reason for rejection...';
        customReason.required = true;
    } else {
        commonReasonsContainer.style.display = 'block';
        document.querySelector('label[for="customReason"]').textContent = 'Additional Notes (Optional)';
        customReason.placeholder = 'Add any additional notes or specific reasons...';
        customReason.required = false;
        
        const reasons = rejectionReasons[employmentStatus] || rejectionReasons['Unemployed'];
        
        reasons.forEach((reason, index) => {
            const reasonId = `reason_${index}`;
            const reasonHtml = `
                <div class="flex items-start">
                    <input type="radio" id="${reasonId}" name="rejection_reason" value="${reason}" 
                           class="mt-1 mr-3 text-red-600 focus:ring-red-500">
                    <label for="${reasonId}" class="text-sm text-gray-700 cursor-pointer">${reason}</label>
                </div>
            `;
            commonReasonsContainer.innerHTML += reasonHtml;
        });
        
        // Add custom reason option
        const customReasonId = 'reason_custom';
        commonReasonsContainer.innerHTML += `
            <div class="flex items-start">
                <input type="radio" id="${customReasonId}" name="rejection_reason" value="custom" 
                       class="mt-1 mr-3 text-red-600 focus:ring-red-500">
                <label for="${customReasonId}" class="text-sm text-gray-700 cursor-pointer">Other (specify in notes)</label>
            </div>
        `;
    }
    
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectForm').reset();
    // Reset form visibility
    document.getElementById('commonReasons').style.display = 'block';
    document.querySelector('label[for="customReason"]').textContent = 'Additional Notes (Optional)';
    document.getElementById('customReason').placeholder = 'Add any additional notes or specific reasons...';
    document.getElementById('customReason').required = false;
    currentUserId = null;
}

// Auto-select "Other" option when typing in custom reason
document.getElementById('customReason').addEventListener('input', function(e) {
    if (this.value.trim() !== '') {
        const otherRadio = document.getElementById('reason_custom');
        if (otherRadio) {
            otherRadio.checked = true;
        }
    }
});

// Handle rejection form submission
document.getElementById('rejectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const employmentStatus = document.querySelector('input[name="employment_status"]')?.value || '';
    const formData = new FormData(this);
    const rejectionReason = formData.get('rejection_reason');
    const customReason = formData.get('custom_reason');
    
    // Special validation for Unemployed status
    if (employmentStatus === 'Unemployed') {
        if (!customReason) {
            alert('Please provide a reason for rejection.');
            return;
        }
        let finalReason = customReason;
        if (currentUserId) {
            window.location.href = `update_status.php?user_id=${currentUserId}&status=Rejected&reason=${encodeURIComponent(finalReason)}`;
        }
        return;
    }
    
    // Validation for other statuses
    if (!rejectionReason) {
        alert('Please select a rejection reason.');
        return;
    }
    
    let finalReason = rejectionReason;
    if (rejectionReason === 'custom' && customReason) {
        finalReason = customReason;
    } else if (rejectionReason === 'custom' && !customReason) {
        alert('Please provide a reason in the additional notes when selecting "Other".');
        return;
    }
    
    if (currentUserId) {
        window.location.href = `update_status.php?user_id=${currentUserId}&status=Rejected&reason=${encodeURIComponent(finalReason)}`;
    }
});

// Revert Modal Functions
function showRevertModal(userId, alumniName) {
    currentUserId = userId;
    document.getElementById('revertAlumniName').textContent = alumniName;
    document.getElementById('revertModal').classList.remove('hidden');
}

function closeRevertModal() {
    document.getElementById('revertModal').classList.add('hidden');
    currentUserId = null;
}

function processRevert() {
    if (currentUserId) {
        // Send the status as 'Pending' to revert back to pending status
        window.location.href = `update_status.php?user_id=${currentUserId}&status=Pending`;
    }
}

// Alumni details hover functionality
document.addEventListener('DOMContentLoaded', function() {
    const alumniNames = document.querySelectorAll('.alumni-name-hover');
    
    alumniNames.forEach(name => {
        name.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            const userId = this.getAttribute('data-user-id');
            showAlumniDetails(userId);
        });
        
        name.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                if (!isModalHovered) {
                    closeAlumniModal();
                }
            }, 300);
        });
    });
});

function showAlumniDetails(userId) {
    const modal = document.getElementById('alumniModal');
    const modalContent = document.getElementById('alumniModalContent');
    
    modal.style.position = 'fixed';
    modal.style.top = '50%';
    modal.style.left = '50%';
    modal.style.transform = 'translate(-50%, -50%)';
    modal.style.margin = '0';
    modal.style.backgroundColor = 'transparent';
    modal.style.boxShadow = 'none';
    
    fetch(`get_alumni_details.php?user_id=${userId}`)
        .then(response => response.text())
        .then(data => {
            modalContent.innerHTML = data;
            modal.classList.remove('hidden');
            
            modalContent.addEventListener('mouseenter', function() {
                isModalHovered = true;
                clearTimeout(hoverTimeout);
            });
            
            modalContent.addEventListener('mouseleave', function() {
                isModalHovered = false;
                hoverTimeout = setTimeout(() => {
                    closeAlumniModal();
                }, 300);
            });
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="text-center py-8 bg-white rounded-xl"><p class="text-red-500">Error loading alumni details.</p></div>';
            modal.classList.remove('hidden');
        });
}

function closeAlumniModal() {
    const modal = document.getElementById('alumniModal');
    modal.classList.add('hidden');
    isModalHovered = false;
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const approveModal = document.getElementById('approveModal');
    const rejectModal = document.getElementById('rejectModal');
    const revertModal = document.getElementById('revertModal');
    
    if (e.target === approveModal) {
        closeApproveModal();
    }
    if (e.target === rejectModal) {
        closeRejectModal();
    }
    if (e.target === revertModal) {
        closeRevertModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApproveModal();
        closeRejectModal();
        closeRevertModal();
    }
});
</script>

<?php
// Helper functions for status colors and icons
function getEmploymentStatusColor($status) {
    if (empty($status)) return 'bg-gray-100 text-gray-800';
    switch ($status) {
        case 'Unemployed': return 'bg-red-100 text-red-800';
        case 'Self-Employed': return 'bg-blue-100 text-blue-800';
        case 'Employed': return 'bg-green-100 text-green-800';
        case 'Student': return 'bg-purple-100 text-purple-800';
        case 'Employed & Student': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getEmploymentStatusBorder($status) {
    if (empty($status)) return 'border-gray-200';
    switch ($status) {
        case 'Unemployed': return 'border-red-200';
        case 'Self-Employed': return 'border-blue-200';
        case 'Employed': return 'border-green-200';
        case 'Student': return 'border-purple-200';
        case 'Employed & Student': return 'border-yellow-200';
        default: return 'border-gray-200';
    }
}

function getEmploymentStatusIcon($status) {
    if (empty($status)) return 'fas fa-question text-gray-600';
    switch ($status) {
        case 'Unemployed': return 'fas fa-user-slash text-red-600';
        case 'Self-Employed': return 'fas fa-briefcase text-blue-600';
        case 'Employed': return 'fas fa-building text-green-600';
        case 'Student': return 'fas fa-graduation-cap text-purple-600';
        case 'Employed & Student': return 'fas fa-user-graduate text-yellow-600';
        default: return 'fas fa-question text-gray-600';
    }
}

function getSubmissionStatusColor($status) {
    if (empty($status)) return 'bg-gray-100 text-gray-800';
    switch ($status) {
        case 'Approved': return 'bg-green-100 text-green-800';
        case 'Pending': return 'bg-yellow-100 text-yellow-800';
        case 'Rejected': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getSubmissionStatusBorder($status) {
    if (empty($status)) return 'border-gray-200';
    switch ($status) {
        case 'Approved': return 'border-green-200';
        case 'Pending': return 'border-yellow-200';
        case 'Rejected': return 'border-red-200';
        default: return 'border-gray-200';
    }
}

function getSubmissionStatusIcon($status) {
    if (empty($status)) return 'fas fa-question text-gray-600';
    switch ($status) {
        case 'Approved': return 'fas fa-check-circle text-green-600';
        case 'Pending': return 'fas fa-clock text-yellow-600';
        case 'Rejected': return 'fas fa-times-circle text-red-600';
        default: return 'fas fa-question text-gray-600';
    }
}

$page_content = ob_get_clean();
include("admin_format.php");
?>