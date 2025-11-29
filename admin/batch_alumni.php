<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$batch_year = $_GET['batch'] ?? '';
if (empty($batch_year)) {
    header("Location: alumni_management.php");
    exit();
}

$page_title = "Batch $batch_year Alumni";
$active_page = "alumni_management";

// Get filter parameters
$search = $_GET['search'] ?? '';
$employment_status = $_GET['employment_status'] ?? '';
$submission_status = $_GET['submission_status'] ?? '';

// Fetch batch statistics - include all alumni
$statsQuery = "SELECT
    COUNT(*) as total_alumni,
    SUM(CASE WHEN ap.submission_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN ap.submission_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN ap.submission_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN ap.user_id IS NULL THEN 1 ELSE 0 END) as no_profile_count
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.role = 'alumni' AND u.batch_year = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param('s', $batch_year);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$batchStats = $statsResult->fetch_assoc();

// Calculate profile completion rate
$total_alumni = $batchStats['total_alumni'] ?? 0;
$with_profiles = ($batchStats['approved_count'] ?? 0) + ($batchStats['pending_count'] ?? 0) + ($batchStats['rejected_count'] ?? 0);
$completion_rate = $total_alumni > 0 ? round(($with_profiles / $total_alumni) * 100, 1) : 0;

// Build query with filters - UPDATED to include ALL alumni
$whereConditions = ["u.role = 'alumni'", "u.batch_year = ?"];
$params = [$batch_year];
$types = 's';

if (!empty($search)) {
    $whereConditions[] = "u.name LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $types .= 's';
}
if (!empty($employment_status)) {
    $whereConditions[] = "ap.employment_status = ?";
    $params[] = $employment_status;
    $types .= 's';
}
if (!empty($submission_status)) {
    if ($submission_status === 'No Profile') {
        $whereConditions[] = "ap.user_id IS NULL";
    } else {
        $whereConditions[] = "ap.submission_status = ?";
        $params[] = $submission_status;
        $types .= 's';
    }
}

$whereClause = implode(" AND ", $whereConditions);

$alumniQuery = "
    SELECT 
        u.user_id, 
        u.name, 
        u.batch_year,
        u.email,
        ap.employment_status, 
        ap.submission_status, 
        ap.photo_path
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE $whereClause
    ORDER BY u.name ASC
";

$stmt = $conn->prepare($alumniQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$alumniResult = $stmt->get_result();

ob_start();
?>
<div class="space-y-6">
    <div class="bg-white p-4 rounded-2xl shadow-xl border-t-4 border-yellow-600">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center space-x-3">
                <a href="alumni_management.php" class="p-3 rounded-full text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition duration-150 ease-in-out group focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-arrow-left text-lg"></i>
                </a>
                
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                        Batch <span class="text-indigo-600"><?= htmlspecialchars($batch_year) ?></span> Alumni
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        Total: <span class="font-semibold"><?= $total_alumni ?></span> graduates • 
                        Profile Completion: <span class="font-semibold <?= $completion_rate >= 80 ? 'text-green-600' : ($completion_rate >= 50 ? 'text-yellow-600' : 'text-red-600') ?>"><?= $completion_rate ?>%</span>
                    </p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center space-x-3">
                <!-- Total Alumni Badge -->
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-blue-50 text-blue-700 shadow-sm border border-blue-200 transition duration-150 ease-in-out hover:bg-blue-100">
                    <i class="fas fa-users mr-2 text-base"></i> 
                    Total: <span class="ml-1 font-bold"><?= $total_alumni ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-200 transition duration-150 ease-in-out hover:bg-emerald-100">
                    <i class="fas fa-check-circle mr-2 text-base"></i> 
                    Approved: <span class="ml-1 font-bold"><?= $batchStats['approved_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-amber-50 text-amber-700 shadow-sm border border-amber-200 transition duration-150 ease-in-out hover:bg-amber-100">
                    <i class="fas fa-clock mr-2 text-base"></i> 
                    Pending: <span class="ml-1 font-bold"><?= $batchStats['pending_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-rose-50 text-rose-700 shadow-sm border border-rose-200 transition duration-150 ease-in-out hover:bg-rose-100">
                    <i class="fas fa-times-circle mr-2 text-base"></i> 
                    Rejected: <span class="ml-1 font-bold"><?= $batchStats['rejected_count'] ?? 0 ?></span>
                </span>
                
                <?php if (($batchStats['no_profile_count'] ?? 0) > 0): ?>
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gray-50 text-gray-700 shadow-sm border border-gray-200 transition duration-150 ease-in-out hover:bg-gray-100">
                    <i class="fas fa-user-clock mr-2 text-base"></i> 
                    No Profile: <span class="ml-1 font-bold"><?= $batchStats['no_profile_count'] ?? 0 ?></span>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white p-2 rounded-xl shadow-lg">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <input type="hidden" name="batch" value="<?= htmlspecialchars($batch_year) ?>">
            <div class="flex-1 min-w-0">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Name</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter name...">
            </div>
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select name="employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="Unemployed" <?= $employment_status === 'Unemployed' ? 'selected' : '' ?>>Unemployed</option>
                    <option value="Self-Employed" <?= $employment_status === 'Self-Employed' ? 'selected' : '' ?>>Self-Employed</option>
                    <option value="Employed" <?= $employment_status === 'Employed' ? 'selected' : '' ?>>Employed</option>
                    <option value="Student" <?= $employment_status === 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="Employed & Student" <?= $employment_status === 'Employed & Student' ? 'selected' : '' ?>>Student & Employed</option>
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Submission Status</label>
                <select name="submission_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="Pending" <?= $submission_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Approved" <?= $submission_status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Rejected" <?= $submission_status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="No Profile" <?= $submission_status === 'No Profile' ? 'selected' : '' ?>>No Profile</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Apply Filters</button>
                <a href="batch_alumni.php?batch=<?= urlencode($batch_year) ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Clear</a>
            </div>
        </form>
    </div>

    <?php if ($alumniResult->num_rows > 0): ?>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">
                Alumni Records <span class="text-sm font-normal text-gray-600 ml-2">(<?= $alumniResult->num_rows ?> found)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumni</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($alumni = $alumniResult->fetch_assoc()): ?>
                        <?php
                        // Fetch documents
                        $docStmt = $conn->prepare("SELECT document_type, file_path FROM alumni_documents WHERE user_id = ?");
                        $docStmt->bind_param('i', $alumni['user_id']);
                        $docStmt->execute();
                        $docResult = $docStmt->get_result();
                        $documents = $docResult->fetch_all(MYSQLI_ASSOC);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <?php if (!empty($alumni['photo_path'])): ?>
                                            <img class="h-10 w-10 rounded-full object-cover" src="../<?= htmlspecialchars($alumni['photo_path']) ?>" alt="">
                                        <?php else: ?>
                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 alumni-name-hover" data-user-id="<?= $alumni['user_id'] ?>">
                                            <?= htmlspecialchars($alumni['name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($alumni['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full <?= getEmploymentStatusColor($alumni['employment_status']) ?> border <?= getEmploymentStatusBorder($alumni['employment_status']) ?> shadow-sm">
                                    <i class="<?= getEmploymentStatusIcon($alumni['employment_status']) ?> mr-2"></i>
                                    <?= empty($alumni['employment_status']) ? 'No Profile' : htmlspecialchars($alumni['employment_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full <?= getSubmissionStatusColor($alumni['submission_status']) ?> border <?= getSubmissionStatusBorder($alumni['submission_status']) ?> shadow-sm">
                                    <i class="<?= getSubmissionStatusIcon($alumni['submission_status']) ?> mr-2"></i>
                                    <?= empty($alumni['submission_status']) ? 'No Profile' : htmlspecialchars($alumni['submission_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php if (!empty($documents)): ?>
                                    <div class="space-y-1">
                                        <?php foreach ($documents as $doc): ?>
                                            <?php
                                            $doc_names = ['COR' => 'Registration', 'COE' => 'Employment Cert', 'B_CERT' => 'Business Cert'];
                                            $name = $doc_names[$doc['document_type']] ?? $doc['document_type'];
                                            ?>
                                            <div class="flex items-center hover:bg-gray-50 rounded px-2 py-1">
                                                <span class="text-sm font-medium"><?= $name ?></span>
                                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 ml-2 text-sm">
                                                    <i class="fas fa-external-link-alt"></i> View
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No documents</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if (empty($alumni['submission_status']) || $alumni['submission_status'] === 'Not Started'): ?>
                                    <div class="flex justify-left">
                                        <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200 shadow-sm">
                                            <i class="fas fa-clock mr-2 text-gray-600"></i>
                                            No Profile
                                        </span>
                                    </div>
                                <?php elseif ($alumni['submission_status'] === 'Pending'): ?>
                                    <div class="flex gap-2">
                                        <button onclick="showApproveModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>')"
                                                class="text-green-600 hover:text-green-900 px-3 py-1 border border-green-600 rounded-lg hover:bg-green-50">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button onclick="showRejectModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>', '<?= $alumni['employment_status'] ?>')"
                                                class="text-red-600 hover:text-red-900 px-3 py-1 border border-red-600 rounded-lg hover:bg-red-50">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button onclick="showRevertModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>')"
                                            class="text-orange-600 hover:text-orange-900 px-3 py-1 border border-orange-600 rounded-lg hover:bg-orange-50">
                                        <i class="fas fa-undo mr-1"></i> Undo
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow border text-center py-12">
            <i class="fas fa-users text-4xl text-gray-400 mb-3"></i>
            <h3 class="text-lg font-medium text-gray-900">No alumni found</h3>
            <p class="text-gray-500 mt-1">Try adjusting your filters.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-check text-green-600 text-xl"></i></div>
        <h3 class="text-lg font-bold text-center mb-2">Confirm Approval</h3>
        <p class="text-gray-600 text-center mb-6">Approve <span id="approveAlumniName" class="font-semibold"></span>?</p>
        <div class="flex gap-3">
            <button onclick="closeApproveModal()" class="flex-1 bg-gray-300 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
            <button onclick="processApproval()" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Confirm</button>
        </div>
    </div>
</div>

<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-times text-red-600 text-xl"></i></div>
        <h3 class="text-lg font-bold text-center mb-2">Reject Profile</h3>
        <p class="text-gray-600 text-center mb-4">Reason for rejecting <span id="rejectAlumniName" class="font-semibold"></span>:</p>
        <form id="rejectForm">
            <input type="hidden" id="rejectUserId">
            <div class="mb-4"><div id="commonReasons" class="space-y-2"></div></div>
            <div class="mb-4">
                <textarea id="customReason" name="custom_reason" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Additional notes (optional)"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-300 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">Reject</button>
            </div>
        </form>
    </div>
</div>

<div id="revertModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-undo text-orange-600 text-xl"></i></div>
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

<div id="alumniModal" class="fixed inset-0 flex items-center justify-center hidden z-50 pointer-events-none">
    <div class="pointer-events-auto max-w-4xl w-full mx-4">
        <div id="alumniModalContent" class="bg-white rounded-xl shadow-2xl max-h-[90vh] overflow-y-auto"></div>
    </div>
</div>

<script>
let currentUserId = null;
let hoverTimeout = null;
let isModalHovered = false;

const rejectionReasons = {
    'Unemployed': ['Incomplete employment history', 'Missing job search proof', 'Unclear plans'],
    'Self-Employed': ['Missing business docs', 'Unclear business proof', 'Verification needed'],
    'Employed': ['Missing COE', 'Incomplete company info', 'Verification needed'],
    'Student': ['Missing enrollment', 'No student ID', 'Verification needed'],
    'Employed & Student': ['Missing both docs', 'Conflict unclear', 'Verification needed']
};

function showApproveModal(id, name) { currentUserId = id; document.getElementById('approveAlumniName').textContent = name; document.getElementById('approveModal').classList.remove('hidden'); }
function closeApproveModal() { document.getElementById('approveModal').classList.add('hidden'); currentUserId = null; }
function processApproval() { if (currentUserId) window.location.href = `update_status.php?user_id=${currentUserId}&status=Approved&${new URLSearchParams(window.location.search)}`; }

function showRejectModal(id, name, empStatus) {
    currentUserId = id;
    document.getElementById('rejectAlumniName').textContent = name;
    document.getElementById('rejectUserId').value = id;
    const container = document.getElementById('commonReasons');
    container.innerHTML = '';
    const reasons = rejectionReasons[empStatus] || rejectionReasons['Unemployed'];
    reasons.forEach((r, i) => {
        container.innerHTML += `<div class="flex items-start"><input type="radio" name="rejection_reason" value="${r}" id="r${i}" class="mt-1 mr-2"><label for="r${i}" class="text-sm cursor-pointer">${r}</label></div>`;
    });
    container.innerHTML += `<div class="flex items-start"><input type="radio" name="rejection_reason" value="custom" id="rcustom" class="mt-1 mr-2"><label for="rcustom" class="text-sm cursor-pointer">Other (specify below)</label></div>`;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); document.getElementById('rejectForm').reset(); currentUserId = null; }
document.getElementById('rejectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const selected = document.querySelector('input[name="rejection_reason"]:checked');
    if (!selected) return alert('Please select a reason');
    let reason = selected.value === 'custom' ? document.getElementById('customReason').value.trim() || 'No reason given' : selected.value;
    window.location.href = `update_status.php?user_id=${currentUserId}&status=Rejected&reason=${encodeURIComponent(reason)}&${new URLSearchParams(window.location.search)}`;
});

function showRevertModal(id, name) { currentUserId = id; document.getElementById('revertAlumniName').textContent = name; document.getElementById('revertModal').classList.remove('hidden'); }
function closeRevertModal() { document.getElementById('revertModal').classList.add('hidden'); currentUserId = null; }
function processRevert() { if (currentUserId) window.location.href = `update_status.php?user_id=${currentUserId}&status=Pending&${new URLSearchParams(window.location.search)}`; }

// Hover details
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alumni-name-hover').forEach(el => {
        el.addEventListener('mouseenter', () => { clearTimeout(hoverTimeout); showAlumniDetails(el.dataset.userId); });
        el.addEventListener('mouseleave', () => { hoverTimeout = setTimeout(() => { if (!isModalHovered) closeAlumniModal(); }, 300); });
    });
});
function showAlumniDetails(id) {
    const modal = document.getElementById('alumniModal');
    const content = document.getElementById('alumniModalContent');
    fetch(`get_alumni_details.php?user_id=${id}`).then(r => r.text()).then(html => {
        content.innerHTML = html;
        modal.classList.remove('hidden');
        content.onmouseenter = () => { isModalHovered = true; clearTimeout(hoverTimeout); };
        content.onmouseleave = () => { isModalHovered = false; hoverTimeout = setTimeout(closeAlumniModal, 300); };
    });
}
function closeAlumniModal() { document.getElementById('alumniModal').classList.add('hidden'); }

// Close on outside click / ESC
document.addEventListener('click', e => { if (e.target.classList.contains('fixed')) { closeApproveModal(); closeRejectModal(); closeRevertModal(); } });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeApproveModal(); closeRejectModal(); closeRevertModal(); } });
</script>

<?php

// Enhanced helper functions for "No Profile" status
function getEmploymentStatusColor($s) { 
    if (empty($s)) return 'bg-gray-100 text-gray-800';
    return ['Unemployed'=>'bg-red-100 text-red-800','Self-Employed'=>'bg-blue-100 text-blue-800','Employed'=>'bg-green-100 text-green-800','Student'=>'bg-purple-100 text-purple-800','Employed & Student'=>'bg-yellow-100 text-yellow-800'][$s] ?? 'bg-gray-100 text-gray-800'; 
}

function getEmploymentStatusBorder($s) { 
    if (empty($s)) return 'border-gray-200';
    return ['Unemployed'=>'border-red-200','Self-Employed'=>'border-blue-200','Employed'=>'border-green-200','Student'=>'border-purple-200','Employed & Student'=>'border-yellow-200'][$s] ?? 'border-gray-200'; 
}

function getEmploymentStatusIcon($s) { 
    if (empty($s)) return 'fas fa-user-clock text-gray-600';
    return ['Unemployed'=>'fas fa-user-slash text-red-600','Self-Employed'=>'fas fa-briefcase text-blue-600','Employed'=>'fas fa-building text-green-600','Student'=>'fas fa-graduation-cap text-purple-600','Employed & Student'=>'fas fa-user-graduate text-yellow-600'][$s] ?? 'fas fa-user-clock text-gray-600'; 
}

function getSubmissionStatusColor($s) { 
    if (empty($s)) return 'bg-gray-100 text-gray-800';
    return ['Approved'=>'bg-green-100 text-green-800','Pending'=>'bg-yellow-100 text-yellow-800','Rejected'=>'bg-red-100 text-red-800'][$s] ?? 'bg-gray-100 text-gray-800'; 
}

function getSubmissionStatusBorder($s) { 
    if (empty($s)) return 'border-gray-200';
    return ['Approved'=>'border-green-200','Pending'=>'border-yellow-200','Rejected'=>'border-red-200'][$s] ?? 'border-gray-200'; 
}

function getSubmissionStatusIcon($s) { 
    if (empty($s)) return 'fas fa-user-clock text-gray-600';
    return ['Approved'=>'fas fa-check-circle text-green-600','Pending'=>'fas fa-clock text-yellow-600','Rejected'=>'fas fa-times-circle text-red-600'][$s] ?? 'fas fa-user-clock text-gray-600'; 
}

$page_content = ob_get_clean();
include("admin_format.php");
?>