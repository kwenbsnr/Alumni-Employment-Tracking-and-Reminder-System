<?php
session_start();
// 1. Access Control: Redirects non-admin users or unauthenticated users.
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
// Include database connection.
include("../connect.php");

// 2. Initial Setup and Parameter Handling
$batch_year = $_GET['batch'] ?? '';
if (empty($batch_year)) {
    // Redirect if batch year is not specified
    header("Location: alumni_management.php");
    exit();
}

$page_title = "Batch $batch_year Alumni";
$active_page = "alumni_management";

// Get filter parameters from the URL
$search = $_GET['search'] ?? '';
$employment_status = $_GET['employment_status'] ?? '';
$submission_status = $_GET['submission_status'] ?? '';

// --- PAGINATION SETUP ---
$per_page = 5; // Fixed number of alumni per page as requested
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;
// --- END PAGINATION SETUP ---

// 3. Fetch Batch Statistics (Total Count for Stats and Pagination)
// Fixed: Remove ap.submission_status references since it doesn't exist in alumni_profile
$statsQuery = "SELECT
    COUNT(*) as total_alumni,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Approved') THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Pending') THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Rejected') THEN 1 ELSE 0 END) as rejected_count,
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
// Total alumni who have submitted a profile (regardless of status)
$with_profiles = ($batchStats['approved_count'] ?? 0) + ($batchStats['pending_count'] ?? 0) + ($batchStats['rejected_count'] ?? 0);
$completion_rate = $total_alumni > 0 ? round(($with_profiles / $total_alumni) * 100, 1) : 0;

// 4. Build Query with Filters (for filtered total count and paginated list)
$whereConditions = ["u.role = 'alumni'", "u.batch_year = ?"];
$params = [$batch_year];
$types = 's';

if (!empty($search)) {
    // Search by concatenated full name including middle name and suffix
    $whereConditions[] = "(CONCAT(
        u.first_name,
        IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
        ' ',
        u.last_name,
        IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
    ) LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
if (!empty($employment_status)) {
    $whereConditions[] = "ap.employment_status = ?";
    $params[] = $employment_status;
    $types .= 's';
}
// Note: submission_status filter removed since it doesn't exist in alumni_profile

$whereClause = implode(" AND ", $whereConditions);

// --- TOTAL FILTERED COUNT FOR PAGINATION ---
$countQuery = "SELECT COUNT(*) as filtered_count FROM users u LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($types, ...$params); 
$countStmt->execute();
$countResult = $countStmt->get_result();
$filteredCount = $countResult->fetch_assoc()['filtered_count'];
$totalPages = ceil($filteredCount / $per_page);

// Adjust current page if it exceeds total pages after filtering
if ($current_page > $totalPages && $totalPages > 0) {
    $current_page = $totalPages;
    $offset = ($current_page - 1) * $per_page;
} elseif ($totalPages === 0) {
    $current_page = 1;
    $offset = 0;
}
// --- END TOTAL FILTERED COUNT ---

$alumniQuery = "
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
        u.email,
        ap.employment_status, 
        ap.photo_path,
        (
            SELECT ad.document_status 
            FROM alumni_documents ad 
            WHERE ad.user_id = u.user_id 
            ORDER BY ad.doc_id DESC 
            LIMIT 1
        ) as latest_doc_status,
        CASE 
            WHEN ap.user_id IS NULL THEN 'No Profile'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Rejected') THEN 'Rejected'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Approved') THEN 'Approved'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Pending') THEN 'Pending'
            ELSE 'No Documents'
        END as submission_status
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE $whereClause
    ORDER BY name ASC
    LIMIT ? OFFSET ?
";

// Prepare the statement for the paginated list, adding LIMIT and OFFSET parameters
$alumniStmt = $conn->prepare($alumniQuery);

// Add integer types for LIMIT and OFFSET
$types .= 'ii';
$params[] = $per_page;
$params[] = $offset;

// Need to create a new array for binding parameters including the limit/offset
// We use a small trick to pass array to bind_param for dynamic parameter count
$bind_params = array_merge([$types], $params);
$refs = [];
foreach ($bind_params as $key => $value) {
    $refs[$key] = &$bind_params[$key];
}

call_user_func_array([$alumniStmt, 'bind_param'], $refs);

$alumniStmt->execute();
$alumniResult = $alumniStmt->get_result();

// Get the current URL query string for pagination links
$queryParams = $_GET;
unset($queryParams['page']); // Remove 'page' parameter for base URL
$queryString = http_build_query($queryParams);

// Start output buffering to capture content before including admin_format.php
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
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-blue-50 text-blue-700 shadow-sm border border-blue-200 transition duration-150 ease-in-out hover:bg-blue-100">
                    <i class="fas fa-users mr-2 text-base"></i> 
                    Total: <span class="ml-1 font-bold"><?= $total_alumni ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-200 transition duration-150 ease-in-out hover:bg-emerald-100">
                    <i class="fas fa-check-circle mr-2 text-base"></i> 
                    Approved: <span class="ml-1 font-bold"><?= $batchStats['approved_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-amber-50 text-amber-700 shadow-sm border border-amber-200 transition duration-150 ease-in-out hover:bg-amber-100">
                    <i class="fas fa-user-clock mr-2 text-base"></i> 
                    Pending: <span class="ml-1 font-bold"><?= $batchStats['pending_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-rose-50 text-rose-700 shadow-sm border border-rose-200 transition duration-150 ease-in-out hover:bg-rose-100">
                    <i class="fas fa-times-circle mr-2 text-base"></i> 
                    Rejected: <span class="ml-1 font-bold"><?= $batchStats['rejected_count'] ?? 0 ?></span>
                </span>
                
                <?php if (($batchStats['no_profile_count'] ?? 0) > 0): ?>
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gray-50 text-gray-700 shadow-sm border border-gray-200 transition duration-150 ease-in-out hover:bg-gray-100">
                    <i class="fas fa-user-times mr-2 text-base"></i> 
                    No Profile: <span class="ml-1 font-bold"><?= $batchStats['no_profile_count'] ?? 0 ?></span>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-lg border-t border-gray-100">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <input type="hidden" name="batch" value="<?= htmlspecialchars($batch_year) ?>">
            
            <div class="flex-1 min-w-0 w-full">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Name</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter name...">
            </div>
            
            <div class="w-full sm:w-48">
                <label for="employment_status" class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select id="employment_status" name="employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="Unemployed" <?= $employment_status === 'Unemployed' ? 'selected' : '' ?>>Unemployed</option>
                    <option value="Self-Employed" <?= $employment_status === 'Self-Employed' ? 'selected' : '' ?>>Self-Employed</option>
                    <option value="Employed" <?= $employment_status === 'Employed' ? 'selected' : '' ?>>Employed</option>
                    <option value="Student" <?= $employment_status === 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="Employed & Student" <?= $employment_status === 'Employed & Student' ? 'selected' : '' ?>>Employed & Student</option>
                </select>
            </div>
            
            <!-- Removed submission_status filter since it's not in the alumni_profile table -->
            
            <div class="flex gap-2 w-full sm:w-auto">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex-shrink-0">
                    <i class="fas fa-filter mr-1"></i> Apply
                </button>
                <a href="batch_alumni.php?batch=<?= urlencode($batch_year) ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors flex-shrink-0">
                    <i class="fas fa-eraser mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <?php if ($filteredCount > 0): ?>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">
                Alumni Records <span class="text-sm font-normal text-gray-600 ml-2">(<?= $filteredCount ?> total found)</span>
            </h3>
            <span class="text-sm font-medium text-gray-700">
                Page <?= $current_page ?> of <?= $totalPages ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
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
                        // Fetch documents for the current alumni
                        $docStmt = $conn->prepare("SELECT document_type, file_path, document_status FROM alumni_documents WHERE user_id = ?");
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
                                        <div class="text-sm font-medium text-gray-900 alumni-name-hover cursor-pointer hover:text-indigo-600 transition-colors" data-user-id="<?= $alumni['user_id'] ?>">
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
                                    <?= htmlspecialchars($alumni['submission_status']) ?>
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php if (!empty($documents)): ?>
                                    <div class="space-y-1">
                                        <?php foreach ($documents as $doc): ?>
                                            <?php
                                            // Mapping for document type codes to full names
                                            $doc_names = ['COR' => 'Certificate of Registration', 'COE' => 'Certificate of Employment', 'B_CERT' => 'Business Certificate'];
                                            $name = $doc_names[$doc['document_type']] ?? $doc['document_type'];
                                            ?>
                                            <div class="flex items-center hover:bg-gray-50 rounded px-2 py-1 transition-colors">
                                                <span class="font-semibold text-gray-800 text-sm"><?= $name ?></span>
                                                <span class="ml-2 text-xs px-2 py-1 rounded-full <?= getDocumentStatusColor($doc['document_status']) ?>">
                                                    <?= htmlspecialchars($doc['document_status']) ?>
                                                </span>
                                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center text-sm font-semibold ml-2">
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
                                <?php if ($alumni['submission_status'] === 'No Profile'): ?>
                                    <div class="flex justify-left">
                                        <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200 shadow-sm">
                                            <i class="fas fa-user-clock mr-2 text-gray-600"></i>
                                            No Profile
                                        </span>
                                    </div>
                                <?php elseif ($alumni['submission_status'] === 'Pending'): ?>
                                    <div class="flex gap-2">
                                        <button onclick="showApproveModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>')"
                                                class="text-green-600 hover:text-green-900 px-3 py-1 border border-green-600 rounded-lg hover:bg-green-50 transition-colors">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button onclick="showRejectModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>', '<?= $alumni['employment_status'] ?>')"
                                                class="text-red-600 hover:text-red-900 px-3 py-1 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                <?php elseif ($alumni['submission_status'] === 'Approved' || $alumni['submission_status'] === 'Rejected'): ?>
                                    <button onclick="showRevertModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>')"
                                            class="text-orange-600 hover:text-orange-900 px-3 py-1 border border-orange-600 rounded-lg hover:bg-orange-50 transition-colors">
                                        <i class="fas fa-undo mr-1"></i> Undo
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No action available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t bg-white flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
                <a href="?page=<?= max(1, $current_page - 1) ?>&<?= htmlspecialchars($queryString) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $current_page == 1 ? 'pointer-events-none opacity-50' : '' ?>">
                    Previous
                </a>
                <a href="?page=<?= min($totalPages, $current_page + 1) ?>&<?= htmlspecialchars($queryString) ?>"
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $current_page == $totalPages ? 'pointer-events-none opacity-50' : '' ?>">
                    Next
                </a>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing
                        <span class="font-medium"><?= $offset + 1 ?></span>
                        to
                        <span class="font-medium"><?= min($offset + $per_page, $filteredCount) ?></span>
                        of
                        <span class="font-medium"><?= $filteredCount ?></span>
                        results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <a href="?page=<?= max(1, $current_page - 1) ?>&<?= htmlspecialchars($queryString) ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page == 1 ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left h-5 w-5"></i>
                        </a>
                        
                        <?php 
                        // Simplified pagination links (show max 5 pages centered around current)
                        $startPage = max(1, $current_page - 2);
                        $endPage = min($totalPages, $current_page + 2);

                        if ($startPage > 1) {
                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                        }

                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?page=<?= $i ?>&<?= htmlspecialchars($queryString) ?>"
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium 
                               <?= $i == $current_page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; 
                        
                        if ($endPage < $totalPages) {
                             echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                        }
                        ?>

                        <a href="?page=<?= min($totalPages, $current_page + 1) ?>&<?= htmlspecialchars($queryString) ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page == $totalPages ? 'pointer-events-none opacity-50' : '' ?>">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right h-5 w-5"></i>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow border text-center py-12">
            <i class="fas fa-users text-4xl text-gray-400 mb-3"></i>
            <h3 class="text-lg font-medium text-gray-900">No alumni found</h3>
            <p class="text-gray-500 mt-1">Try adjusting your filters or check if the batch has registered alumni.</p>
        </div>
    <?php endif; ?>
</div>

<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-check text-green-600 text-xl"></i></div>
        <h3 class="text-lg font-bold text-center mb-2">Confirm Approval</h3>
        <p class="text-gray-600 text-center mb-6">Approve <span id="approveAlumniName" class="font-semibold"></span>'s profile?</p>
        <div class="flex gap-3">
            <button onclick="closeApproveModal()" class="flex-1 bg-gray-300 py-2 rounded-lg hover:bg-gray-400 transition-colors">Cancel</button>
            <button onclick="processApproval()" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-colors">Confirm</button>
        </div>
    </div>
</div>

<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-times text-red-600 text-xl"></i>
        </div>
        <h3 class="text-lg font-bold text-center mb-2">Reject Profile</h3>
        <p class="text-gray-600 text-center mb-4">
            Reason for rejecting <span id="rejectAlumniName" class="font-semibold"></span>:
        </p>
        <form id="rejectForm">
            <input type="hidden" id="rejectUserId" name="user_id">
            <div class="mb-4">
                <label id="commonReasonsLabel" class="block text-sm font-medium text-gray-700 mb-2">Common Reasons:</label>
                <div id="commonReasons" class="space-y-2">
                    </div>
            </div>
            <div class="mb-4">
                <label for="customReason" class="block text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
                <textarea id="customReason" name="custom_reason" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                          placeholder="Add any additional notes or specific reasons..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors">
                    Reject
                </button>
            </div>
        </form>
    </div>
</div>

<div id="revertModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-undo text-orange-600 text-xl"></i></div>
       <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Undo Action</h3>
        <p class="text-gray-600 text-center mb-6">
            Are you sure you want to undo the action and set <span id="revertAlumniName" class="font-semibold"></span>'s profile status back to **Pending**?
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
        <div id="alumniModalContent" class="bg-white rounded-xl shadow-2xl max-h-[90vh] overflow-y-auto">
            </div>
    </div>
</div>

<script>
let currentUserId = null;
let hoverTimeout = null;
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

/** Approve Modal Logic **/
function showApproveModal(id, name) { 
    currentUserId = id; 
    document.getElementById('approveAlumniName').textContent = name; 
    document.getElementById('approveModal').classList.remove('hidden'); 
}

function closeApproveModal() { 
    document.getElementById('approveModal').classList.add('hidden'); 
    currentUserId = null; 
}

// Redirects to update_status.php to process approval
function processApproval() { 
    if (currentUserId) window.location.href = `update_status.php?user_id=${currentUserId}&status=Approved&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`; 
}

/** Reject Modal Logic **/
function showRejectModal(id, name, empStatus) {
    currentUserId = id;
    
    // Set the alumni name and user ID
    document.getElementById('rejectAlumniName').textContent = name;
    document.getElementById('rejectUserId').value = id;
    
    const container = document.getElementById('commonReasons');
    const containerLabel = document.getElementById('commonReasonsLabel');
    const customReason = document.getElementById('customReason');
    
    container.innerHTML = '';
    
    // Special handling for Unemployed status - show only textarea
    if (empStatus === 'Unemployed' || !rejectionReasons[empStatus] || rejectionReasons[empStatus].length === 0) {
        container.style.display = 'none';
        containerLabel.style.display = 'none';
        
        // Update the custom reason label/placeholder for single input
        const customLabel = document.querySelector('label[for="customReason"]');
        if (customLabel) customLabel.textContent = 'Reason for rejection:';
        customReason.placeholder = 'Please specify the reason for rejection (required)...';
        customReason.required = true;
    } else {
        container.style.display = 'block';
        containerLabel.style.display = 'block';

        // Reset custom reason label/placeholder
        const customLabel = document.querySelector('label[for="customReason"]');
        if (customLabel) customLabel.textContent = 'Additional Notes (Optional)';
        customReason.placeholder = 'Add any additional notes or specific reasons...';
        customReason.required = false;
        
        // Populate common reasons
        const reasons = rejectionReasons[empStatus];
        reasons.forEach((r, i) => {
            container.innerHTML += `
                <div class="flex items-start">
                    <input type="radio" name="rejection_reason" value="${r}" id="r${i}" class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300">
                    <label for="r${i}" class="text-sm cursor-pointer">${r}</label>
                </div>
            `;
        });
        
        // Add "Other" option
        container.innerHTML += `
            <div class="flex items-start">
                <input type="radio" name="rejection_reason" value="custom" id="rcustom" class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300">
                <label for="rcustom" class="text-sm cursor-pointer">Other (specify below)</label>
            </div>
        `;
    }
    
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() { 
    document.getElementById('rejectModal').classList.add('hidden'); 
    document.getElementById('rejectForm').reset();
    
    // Reset form elements to their default state
    const container = document.getElementById('commonReasons');
    const containerLabel = document.getElementById('commonReasonsLabel');
    const customReason = document.getElementById('customReason');
    
    container.style.display = 'block';
    containerLabel.style.display = 'block';
    
    const customLabel = document.querySelector('label[for="customReason"]');
    if (customLabel) customLabel.textContent = 'Additional Notes (Optional)';
    customReason.placeholder = 'Add any additional notes or specific reasons...';
    customReason.required = false;
    
    currentUserId = null; 
}

// Event listener for the rejection form submission
document.addEventListener('DOMContentLoaded', function() {
    const rejectForm = document.getElementById('rejectForm');
    const customReason = document.getElementById('customReason');
    
    if (customReason) {
        // Auto-select "Other" radio button when user starts typing
        customReason.addEventListener('input', function(e) {
            if (this.value.trim() !== '') {
                const otherRadio = document.getElementById('rcustom');
                if (otherRadio) {
                    otherRadio.checked = true;
                }
            }
        });
    }

    if (rejectForm) {
        rejectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selected = document.querySelector('input[name="rejection_reason"]:checked');
            const customValue = document.getElementById('customReason').value.trim();
            const isUnemployedMode = document.getElementById('commonReasons').style.display === 'none';
            let finalReason = '';
            
            if (isUnemployedMode) {
                // Unemployed/No-Reason-Defined mode
                if (!customValue) {
                    alert('Please provide a reason for rejection.');
                    return;
                }
                finalReason = customValue;
            } else {
                // Standard mode with common reasons
                if (!selected) {
                    alert('Please select a rejection reason.');
                    return;
                }
                
                if (selected.value === 'custom') {
                    if (!customValue) {
                        alert('Please provide a reason in the additional notes when selecting "Other".');
                        return;
                    }
                    finalReason = customValue;
                } else {
                    // Combine selected reason with optional custom note
                    finalReason = selected.value;
                    if (customValue) {
                        finalReason += ` | Note: ${customValue}`;
                    }
                }
            }
            
            // Redirect to update_status.php to process rejection
            window.location.href = `update_status.php?user_id=${currentUserId}&status=Rejected&reason=${encodeURIComponent(finalReason)}&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
        });
    }
});

/** Revert Modal Logic **/
function showRevertModal(id, name) { 
    currentUserId = id; 
    document.getElementById('revertAlumniName').textContent = name; 
    document.getElementById('revertModal').classList.remove('hidden'); 
}

function closeRevertModal() { 
    document.getElementById('revertModal').classList.add('hidden'); 
    currentUserId = null; 
}

// Redirects to update_status.php to process revert (set status to Pending)
function processRevert() { 
    if (currentUserId) window.location.href = `update_status.php?user_id=${currentUserId}&status=Pending&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`; 
}

/** Hover Details Modal Logic **/
document.addEventListener('DOMContentLoaded', () => {
    // Add event listeners to all alumni names for hover effect
    document.querySelectorAll('.alumni-name-hover').forEach(el => {
        el.addEventListener('mouseenter', () => { 
            clearTimeout(hoverTimeout); 
            showAlumniDetails(el.dataset.userId); 
        });
        el.addEventListener('mouseleave', () => { 
            // Start a timer to close the modal after a delay if the mouse doesn't enter the modal
            hoverTimeout = setTimeout(() => { 
                if (!isModalHovered) closeAlumniModal(); 
            }, 300); 
        });
    });
});

function showAlumniDetails(id) {
    const modal = document.getElementById('alumniModal');
    const content = document.getElementById('alumniModalContent');
    
    // Fetch detailed profile content via AJAX
    fetch(`get_alumni_details.php?user_id=${id}`) // NOTE: get_alumni_details.php is a separate file not included here.
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
            modal.classList.remove('hidden');
            
            // Keep the modal open if the mouse enters it
            content.onmouseenter = () => { 
                isModalHovered = true; 
                clearTimeout(hoverTimeout); 
            };
            // Close the modal when mouse leaves it
            content.onmouseleave = () => { 
                isModalHovered = false; 
                hoverTimeout = setTimeout(closeAlumniModal, 300); 
            };
        })
        .catch(error => {
            console.error('Error loading alumni details:', error);
            content.innerHTML = '<div class="text-center py-8 bg-white rounded-xl"><p class="text-red-500">Error loading alumni details.</p></div>';
            modal.classList.remove('hidden');
        });
}

function closeAlumniModal() { 
    document.getElementById('alumniModal').classList.add('hidden'); 
}

// Close on outside click / ESC for primary modals
document.addEventListener('click', e => { 
    // Check if the click is on the modal backdrop (the element with 'fixed' class)
    if (e.target.classList.contains('fixed') && e.target.id !== 'alumniModal') { 
        closeApproveModal(); 
        closeRejectModal(); 
        closeRevertModal(); 
    } 
});

document.addEventListener('keydown', e => { 
    // Close on ESC key press
    if (e.key === 'Escape') { 
        closeApproveModal(); 
        closeRejectModal(); 
        closeRevertModal(); 
        closeAlumniModal();
    } 
});
</script>

<?php

// 5. Helper Functions for Status Badges (Color, Border, Icon)
function getEmploymentStatusColor($s) { 
    if (empty($s)) return 'bg-gray-100 text-gray-800';
    $colors = [
        'Unemployed'=>'bg-red-100 text-red-800',
        'Self-Employed'=>'bg-blue-100 text-blue-800',
        'Employed'=>'bg-green-100 text-green-800',
        'Student'=>'bg-purple-100 text-purple-800',
        'Employed & Student'=>'bg-yellow-100 text-yellow-800'
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800'; 
}

function getEmploymentStatusBorder($s) { 
    if (empty($s)) return 'border-gray-200';
    $borders = [
        'Unemployed'=>'border-red-200',
        'Self-Employed'=>'border-blue-200',
        'Employed'=>'border-green-200',
        'Student'=>'border-purple-200',
        'Employed & Student'=>'border-yellow-200'
    ];
    return $borders[$s] ?? 'border-gray-200'; 
}

function getEmploymentStatusIcon($s) { 
    if (empty($s)) return 'fas fa-user-clock text-gray-600';
    $icons = [
        'Unemployed'=>'fas fa-user-slash text-red-600',
        'Self-Employed'=>'fas fa-briefcase text-blue-600',
        'Employed'=>'fas fa-building text-green-600',
        'Student'=>'fas fa-graduation-cap text-purple-600',
        'Employed & Student'=>'fas fa-user-graduate text-yellow-600'
    ];
    return $icons[$s] ?? 'fas fa-user-clock text-gray-600'; 
}

function getSubmissionStatusColor($s) { 
    $colors = [
        'Approved'=>'bg-green-100 text-green-800',
        'Pending'=>'bg-yellow-100 text-yellow-800',
        'Rejected'=>'bg-red-100 text-red-800',
        'No Profile'=>'bg-gray-100 text-gray-800',
        'No Documents'=>'bg-gray-100 text-gray-800'
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800'; 
}

function getSubmissionStatusBorder($s) { 
    $borders = [
        'Approved'=>'border-green-200',
        'Pending'=>'border-yellow-200',
        'Rejected'=>'border-red-200',
        'No Profile'=>'border-gray-200',
        'No Documents'=>'border-gray-200'
    ];
    return $borders[$s] ?? 'border-gray-200'; 
}

function getSubmissionStatusIcon($s) { 
    $icons = [
        'Approved'=>'fas fa-check-circle text-green-600',
        'Pending'=>'fas fa-clock text-yellow-600',
        'Rejected'=>'fas fa-times-circle text-red-600',
        'No Profile'=>'fas fa-user-clock text-gray-600',
        'No Documents'=>'fas fa-file-alt text-gray-600'
    ];
    return $icons[$s] ?? 'fas fa-user-clock text-gray-600'; 
}

function getDocumentStatusColor($s) { 
    $colors = [
        'Approved'=>'bg-green-100 text-green-800',
        'Pending'=>'bg-yellow-100 text-yellow-800',
        'Rejected'=>'bg-red-100 text-red-800'
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800'; 
}

// Capture the buffered content and include the main format file
$page_content = ob_get_clean();
include("admin_format.php"); 
?>