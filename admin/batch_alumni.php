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

$per_page = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

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
        ap.submitted_at, 
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
            ELSE 'No Recent Uploads'
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
                   
                    Total: <span class="ml-1 font-bold"><?= $total_alumni ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-200 transition duration-150 ease-in-out hover:bg-emerald-100">
                    
                    Approved: <span class="ml-1 font-bold"><?= $batchStats['approved_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-amber-50 text-amber-700 shadow-sm border border-amber-200 transition duration-150 ease-in-out hover:bg-amber-100">
                   
                    Pending: <span class="ml-1 font-bold"><?= $batchStats['pending_count'] ?? 0 ?></span>
                </span>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-rose-50 text-rose-700 shadow-sm border border-rose-200 transition duration-150 ease-in-out hover:bg-rose-100">
                  
                    Rejected: <span class="ml-1 font-bold"><?= $batchStats['rejected_count'] ?? 0 ?></span>
                </span>
                
                <?php if (($batchStats['no_profile_count'] ?? 0) > 0): ?>
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gray-50 text-gray-700 shadow-sm border border-gray-200 transition duration-150 ease-in-out hover:bg-gray-100">
                   
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($alumni = $alumniResult->fetch_assoc()): ?>
                        <?php
                        // Fetch documents for the current alumni
                        // IMPORTANT: For the new modal, we need doc_id, not just other fields
                        $docStmt = $conn->prepare("SELECT doc_id, document_type, file_path, document_status FROM alumni_documents WHERE user_id = ?");
                        $docStmt->bind_param('i', $alumni['user_id']);
                        $docStmt->execute();
                        $docResult = $docStmt->get_result();
                        $documents = $docResult->fetch_all(MYSQLI_ASSOC);
                        
                        // Format submitted_at timestamp
                        $submitted_at = $alumni['submitted_at'] ?? null;
                        $submitted_date = '—';
                        $submitted_time = '';
                        if ($submitted_at) {
                            $date = new DateTime($submitted_at);
                            $submitted_date = $date->format('M j, Y'); // e.g., "Mar 15, 2024"
                            $submitted_time = $date->format('g:i A'); // e.g., "2:30 PM"
                        }
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
                                  
                                    <?= empty($alumni['employment_status']) ? 'No recent update' : htmlspecialchars($alumni['employment_status']) // MODIFIED: Changed from 'No Profile' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full <?= getSubmissionStatusColor($alumni['submission_status']) ?> border <?= getSubmissionStatusBorder($alumni['submission_status']) ?> shadow-sm">
                                   
                                    <?= htmlspecialchars($alumni['submission_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($submitted_at): ?>
                                    <div class="text-sm text-gray-900">
                                        <div class="font-medium"><?= $submitted_date ?></div>
                                        <div class="text-xs text-gray-500"><?= $submitted_time ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">—</span>
                                <?php endif; ?>
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
                    
                    <a href="javascript:void(0)" 
                       onclick="openDocumentModal(<?= $doc['doc_id'] ?>, '<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>', '<?= $alumni['user_id'] ?>', '<?= $doc['document_type'] ?>', '<?= $doc['document_status'] ?>')"
                       class="text-blue-600 hover:text-blue-800 flex items-center text-sm font-semibold ml-2">
                        <i class="fas fa-eye mr-1"></i> View
                    </a>
                    
                    <span class="ml-2 px-2 py-0.5 inline-flex text-xs font-semibold rounded-full <?= getDocumentStatusColor($doc['document_status']) ?>">
                        <?= htmlspecialchars($doc['document_status']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="text-gray-400 text-sm">No recent uploads</span>
    <?php endif; ?>
</td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php 
                                $status = $alumni['submission_status'];
                                if ($status === 'Pending'): ?>
                                    <div class="flex gap-2">
                                        <button onclick="showApproveModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>')"
                                                class="text-green-600 hover:text-green-900 px-3 py-1 border border-green-600 rounded-lg hover:bg-green-50 transition-colors">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button onclick="showRejectModal(<?= $alumni['user_id'] ?>, '<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>', '<?= $alumni['employment_status'] ?>', 'profile')"
                                                class="text-red-600 hover:text-red-900 px-3 py-1 border border-red-600 rounded-lg hover:bg-red-50 transition-colors">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                <?php elseif ($status === 'Approved' || $status === 'Rejected'): ?>
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
        <h3 class="text-lg font-bold text-center mb-2" id="rejectModalTitle">Reject Profile</h3>
        <p class="text-gray-600 text-center mb-4">
            Reason for rejecting <span id="rejectAlumniName" class="font-semibold"></span>:
        </p>
        <form id="rejectForm">
            <input type="hidden" id="rejectUserId" name="user_id">
            <input type="hidden" id="rejectDocId" name="doc_id"> <input type="hidden" id="rejectType" name="reject_type"> <div class="mb-4">
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

<div id="documentModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-[60]">
    <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full mx-4 h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-xl font-bold text-gray-900" id="docModalTitle">Document View</h3>
            <div class="flex items-center space-x-3">
                <span id="docCurrentStatus" class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full"></span>
                <button onclick="closeDocumentModal()" class="text-gray-500 hover:text-gray-800 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="flex-1 overflow-hidden p-2">
            <iframe id="documentViewer" src="" frameborder="0" class="w-full h-full rounded-lg bg-gray-100"></iframe>
        </div>
        
        <div id="docModalFooter" class="p-4 border-t flex justify-center space-x-4">
            </div>
    </div>
</div>


<script>
let currentUserId = null;
let currentDocId = null; // New variable for Document ID
let currentDocType = null; // New variable for Document Type
let currentAlumniName = null; // New variable for Alumni Name (used in modals)
let hoverTimeout = null;
let isModalHovered = false;

// Employment status specific rejection reasons (Unchanged)
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

// Document type specific rejection reasons (New)
const documentRejectionReasons = <?= json_encode(getDocumentRejectionReasons()) ?>;

/** Approve Modal Logic (Profile) **/
function showApproveModal(id, name) { 
    currentUserId = id; 
    currentDocId = null; // Important: Clear doc ID
    currentAlumniName = name;
    document.getElementById('approveAlumniName').textContent = name; 
    document.getElementById('approveModal').classList.remove('hidden'); 
}

function closeApproveModal() { 
    document.getElementById('approveModal').classList.add('hidden'); 
    currentUserId = null; 
    currentAlumniName = null;
}

// Redirects to update_status.php to process profile approval
function processApproval() { 
    if (currentUserId) {
        window.location.href = `update_status.php?user_id=${currentUserId}&status=Approved&type=profile&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`; 
    }
}

/** Reject Modal Logic (Modified for Dual Use: Profile/Document) **/
function showRejectModal(id, name, typeValue, statusOrDocType, docId = null) {
    currentUserId = id;
    currentDocId = docId;
    currentAlumniName = name;
    
    // Set form hidden fields
    document.getElementById('rejectUserId').value = id;
    document.getElementById('rejectDocId').value = docId || '';
    document.getElementById('rejectType').value = typeValue; // 'profile' or 'document'
    
    const isProfile = typeValue === 'profile';
    const reasonSource = isProfile ? rejectionReasons : documentRejectionReasons;
    const key = isProfile ? statusOrDocType : statusOrDocType; // statusOrDocType is employment_status for profile, doc_type for document
    
    document.getElementById('rejectModalTitle').textContent = isProfile ? 'Reject Profile' : 'Reject Document';
    
    const container = document.getElementById('commonReasons');
    const containerLabel = document.getElementById('commonReasonsLabel');
    const customReason = document.getElementById('customReason');
    
    container.innerHTML = '';
    
    // Check if there are common reasons for the current context
    const hasCommonReasons = reasonSource[key] && reasonSource[key].length > 0;

    if (!hasCommonReasons) {
        container.style.display = 'none';
        containerLabel.style.display = 'none';
        
        // Unemployed/No-Reason-Defined mode: require custom reason
        const customLabel = document.querySelector('label[for="customReason"]');
        if (customLabel) customLabel.textContent = isProfile ? 'Reason for profile rejection:' : 'Reason for document rejection:';
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
        const reasons = reasonSource[key];
        reasons.forEach((r, i) => {
            // Ensure unique IDs across document/profile rejections
            const radioId = isProfile ? `pr${i}` : `dr${i}`; 
            container.innerHTML += `
                <div class="flex items-start">
                    <input type="radio" name="rejection_reason" value="${r}" id="${radioId}" class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300">
                    <label for="${radioId}" class="text-sm cursor-pointer">${r}</label>
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
    // Ensure the document modal is closed if it's open (e.g., rejecting from doc modal)
    closeDocumentModal(false); 
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
    currentDocId = null;
    currentAlumniName = null;
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
            const rejectType = document.getElementById('rejectType').value;
            
            const isUnemployedMode = document.getElementById('commonReasons').style.display === 'none';
            let finalReason = '';
            
            if (isUnemployedMode) {
                if (!customValue) {
                    alert('Please provide a reason for rejection.');
                    return;
                }
                finalReason = customValue;
            } else {
                if (!selected) {
                    alert('Please select a rejection reason or specify in the notes.');
                    return;
                }
                
                if (selected.value === 'custom') {
                    if (!customValue) {
                        alert('Please provide a reason in the additional notes when selecting "Other".');
                        return;
                    }
                    finalReason = customValue;
                } else {
                    finalReason = selected.value;
                    if (customValue) {
                        finalReason += ` | Note: ${customValue}`;
                    }
                }
            }
            
            const userId = document.getElementById('rejectUserId').value;
            const docId = document.getElementById('rejectDocId').value;
            
            let redirectUrl = `update_status.php?user_id=${userId}&status=Rejected&type=${rejectType}&reason=${encodeURIComponent(finalReason)}&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
            
            if (rejectType === 'document' && docId) {
                redirectUrl += `&doc_id=${docId}`;
            }

            // Redirect to update_status.php to process rejection
            window.location.href = redirectUrl;
        });
    }
});

/** Revert Modal Logic (Unchanged) **/
function showRevertModal(id, name) { 
    currentUserId = id; 
    currentAlumniName = name;
    document.getElementById('revertAlumniName').textContent = name; 
    document.getElementById('revertModal').classList.remove('hidden'); 
}

function closeRevertModal() { 
    document.getElementById('revertModal').classList.add('hidden'); 
    currentUserId = null; 
    currentAlumniName = null;
}

// Redirects to update_status.php to process revert (set profile status to Pending)
function processRevert() { 
    if (currentUserId) window.location.href = `update_status.php?user_id=${currentUserId}&status=Pending&type=profile&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`; 
}

/** Document Modal Logic (New) **/
function openDocumentModal(docId, filePath, docName, alumniName, userId, docType, currentStatus) {
    const modal = document.getElementById('documentModal');
    const viewer = document.getElementById('documentViewer');
    const title = document.getElementById('docModalTitle');
    const statusEl = document.getElementById('docCurrentStatus');
    const footer = document.getElementById('docModalFooter');

    currentDocId = docId;
    currentUserId = userId;
    currentDocType = docType;
    currentAlumniName = alumniName;
    
    // 1. Set Modal Title and Status
    title.innerHTML = `${docName} for <span class="text-indigo-600">${alumniName}</span>`;
    
    let statusClass = getStatusColorClass(currentStatus);
    statusEl.className = `px-3 py-1.5 inline-flex text-sm font-semibold rounded-full ${statusClass}`;
    statusEl.textContent = currentStatus;

    // 2. Load Document
    // NOTE: For security and cross-origin issues, we should ideally use an AJAX/PHP endpoint
    // to load the document content, especially for PDFs/other files.
    // However, for this example, we'll use an iframe pointing to the file path.
    viewer.src = `../${filePath}`;
    
    // 3. Set Action Buttons
    footer.innerHTML = '';
    
    if (currentStatus === 'Pending') {
        footer.innerHTML = `
            <button onclick="processDocumentApproval()" 
                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors flex-shrink-0">
                <i class="fas fa-check mr-1"></i> Approve Document
            </button>
            <button onclick="showRejectModal(${userId}, '${alumniName.replace(/'/g, '\\\'')}', 'document', '${docType}', ${docId})" 
                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors flex-shrink-0">
                <i class="fas fa-times mr-1"></i> Reject Document
            </button>
        `;
    } else {
        // Option to revert status if approved/rejected
         footer.innerHTML = `
            <span class="text-sm font-medium text-gray-700">Document status is: <b>${currentStatus}</b>.</span>
            <button onclick="processDocumentRevert()" 
                    class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition-colors flex-shrink-0">
                <i class="fas fa-undo mr-1"></i> Revert to Pending
            </button>
        `;
    }

    // 4. Show Modal
    modal.classList.remove('hidden');
}

function closeDocumentModal(resetState = true) {
    document.getElementById('documentModal').classList.add('hidden');
    document.getElementById('documentViewer').src = ''; // Clear iframe content
    
    if (resetState) {
        currentDocId = null;
        currentUserId = null;
        currentDocType = null;
        currentAlumniName = null;
    }
}

function processDocumentApproval() {
    if (currentDocId && currentUserId) {
        window.location.href = `update_status.php?user_id=${currentUserId}&doc_id=${currentDocId}&status=Approved&type=document&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
    }
}

function processDocumentRevert() {
    if (currentDocId && currentUserId) {
        window.location.href = `update_status.php?user_id=${currentUserId}&doc_id=${currentDocId}&status=Pending&type=document&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
    }
}

// Helper function to get the status color class for the badge
function getStatusColorClass(status) {
    switch (status) {
        case 'Approved': return 'bg-green-100 text-green-800 border border-green-200';
        case 'Pending': return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        case 'Rejected': return 'bg-red-100 text-red-800 border border-red-200';
        default: return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}


/** Hover Details Modal Logic (Unchanged) **/
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
        closeDocumentModal();
    } 
});

document.addEventListener('keydown', e => { 
    // Close on ESC key press
    if (e.key === 'Escape') { 
        closeApproveModal(); 
        closeRejectModal(); 
        closeRevertModal(); 
        closeAlumniModal();
        closeDocumentModal();
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
    // MODIFIED: 'No Profile' icon logic changed to use 'No recent update' text
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
        'No Recent Uploads'=>'bg-gray-100 text-gray-800' 
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800'; 
}

function getSubmissionStatusBorder($s) { 
    $borders = [
        'Approved'=>'border-green-200',
        'Pending'=>'border-yellow-200',
        'Rejected'=>'border-red-200',
        'No Profile'=>'border-gray-200',
        'No Recent Uploads'=>'border-gray-200'
    ];
    return $borders[$s] ?? 'border-gray-200'; 
}

function getSubmissionStatusIcon($s) { 
    $icons = [
        'Approved'=>'fas fa-check-circle text-green-600',
        'Pending'=>'fas fa-clock text-yellow-600',
        'Rejected'=>'fas fa-times-circle text-red-600',
        'No Profile'=>'fas fa-user-clock text-gray-600',
        'No Recent Uploads'=>'fas fa-file-alt text-gray-600'
    ];
    return $icons[$s] ?? 'fas fa-user-clock text-gray-600'; 
}

function getDocumentStatusColor($s) { 
    $colors = [
        'Approved'=>'bg-green-100 text-green-800 border border-green-200',
        'Pending'=>'bg-yellow-100 text-yellow-800 border border-yellow-200',
        'Rejected'=>'bg-red-100 text-red-800 border border-red-200'
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800 border border-gray-200'; 
}

function getDocumentRejectionReasons() {
    return [
        'COR' => [
            'Document is blurry or illegible.',
            'Required document is missing a signature or stamp.',
            'Information on document does not match profile details.'
        ],
        'COE' => [
            'Document is expired or not current.',
            'Employment dates are not clearly visible.',
            'Missing company letterhead or contact information.'
        ],
        'B_CERT' => [
            'Business certificate is expired.',
            'Business name on document does not match profile information.',
            'Document type is incorrect for the business status.'
        ]
    ];
}


// Capture the buffered content and include the main format file
$page_content = ob_get_clean();
include("admin_format.php"); 
?>