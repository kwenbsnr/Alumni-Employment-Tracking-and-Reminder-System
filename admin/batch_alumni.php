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
// NOTE: completion rate is calculated based on 'alumni_documents' presence, not just 'alumni_profile'
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
            WHEN ap.user_id IS NULL THEN 'No Recent Update'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Rejected') THEN 'Rejected'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Approved') THEN 'Approved'
            WHEN EXISTS (SELECT 1 FROM alumni_documents ad WHERE ad.user_id = u.user_id AND ad.document_status = 'Pending') THEN 'Pending'
            ELSE 'No Recent Update' 
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
                
                <?php 
                // Display 'No Recent Update' count, which is effectively the no_profile_count
                $no_update_count = $batchStats['no_profile_count'] ?? 0;
                // Add the count for users who have a profile but no uploads (this is not easy to get with the current single stats query)
                // For simplicity, we stick to 'no_profile_count' for the stats badge.
                if ($no_update_count > 0): ?>
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gray-50 text-gray-700 shadow-sm border border-gray-200 transition duration-150 ease-in-out hover:bg-gray-100">
                   
                    No Recent Update: <span class="ml-1 font-bold"><?= $no_update_count ?></span>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumni Lists</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents Uploaded</th> 
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Submitted</th> </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($alumni = $alumniResult->fetch_assoc()): ?>
                        <?php
                        // Fetch documents for the current alumni
                        $docStmt = $conn->prepare("SELECT doc_id, document_type, file_path, document_status, rejection_reason FROM alumni_documents WHERE user_id = ? ORDER BY doc_id ASC");
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
                        
                        // Check for 'No Recent Update' status
                        $is_no_update = $alumni['submission_status'] === 'No Recent Update';

                        // Check if ANY documents exist.
                        $has_documents = !empty($documents);
                        
                        // Encode ALL documents into a JSON string for the modal function
                        $documents_json = htmlspecialchars(json_encode($documents), ENT_QUOTES, 'UTF-8');

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
                                <?php 
                                    $emp_status_text = empty($alumni['employment_status']) ? 'No Recent Update' : htmlspecialchars($alumni['employment_status']);
                                    // Keep all employment status text black/default (text-gray-900), except 'No Recent Update' which is gray (text-gray-400)
                                    $emp_class = $emp_status_text === 'No Recent Update' ? 'text-gray-400' : 'text-gray-900 font-medium';
                                ?>
                                <span class="text-sm <?= $emp_class ?>">
                                    <?= $emp_status_text ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $status = $alumni['submission_status'];
                                    // getSubmissionStatusColor now returns the correct text-color class (green/yellow/red/gray)
                                    $color_class = getSubmissionStatusColor($status);
                                ?>
                                <span class="text-sm font-semibold <?= $color_class ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php if ($has_documents): ?>
                                    <div class="space-y-1">
                                        <?php foreach ($documents as $doc): ?>
                                            <?php
                                            // Mapping for document type codes to full names
                                            $doc_names = ['COR' => 'Certificate of Registration', 'COE' => 'Certificate of Employment', 'B_CERT' => 'Business Certificate'];
                                            $name = $doc_names[$doc['document_type']] ?? $doc['document_type'];
                                            ?>
                                            <div class="flex items-center rounded px-2 py-1">
                                                <span class="font-semibold text-gray-800 text-sm"><?= $name ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No recent uploads</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($has_documents): ?>
                                    <button 
                                        onclick="openUnifiedDocumentModal('<?= htmlspecialchars($alumni['name'], ENT_QUOTES) ?>', '<?= $alumni['user_id'] ?>', '<?= $alumni['employment_status'] ?>', '<?= $documents_json ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 px-3 py-1 border border-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors inline-flex items-center">
                                        <i class="fas fa-eye mr-1"></i> View Documents
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No Document Action</span>
                                <?php endif; ?>
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
</div><div id="unifiedDocumentModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-[60]">
    

<div class="bg-white rounded-xl shadow-2xl max-w-7xl w-[95vw] mx-4 h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b bg-gray-300">
             <i class="fas fa-tasks mr-2"></i> 
            <h3 class="text-xl font-bold text-gray-900" id="unifiedModalTitle">Document Review</h3>
            <button onclick="closeUnifiedDocumentModal()" class="text-gray-500 hover:text-gray-800 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-hidden flex">
            <div class="w-3/5 flex flex-col p-2 border-r border-gray-200">
                <div class="mb-2">
                    <div id="unifiedDocTabs" class="flex border-b overflow-x-auto">
                    </div>
                </div>
                
                <div id="unifiedViewerContent">
                    <div class="flex items-center justify-between mb-4">
                        <span id="unifiedDocCurrentStatus" class="px-3 py-1.5 inline-flex text-sm font-semibold rounded-full"></span>
                        <div id="unifiedDocumentCountBadge" class="text-sm text-gray-600"></div>
                    </div>
                    <div id="unifiedDocRejectionDetails" class="mb-4"></div>
                    <div class="flex-1 bg-gray-100 rounded-lg overflow-hidden h-[calc(90vh-280px)]">
                        <iframe id="unifiedDocumentViewer" src="" frameborder="0" class="w-full h-full"></iframe>
                    </div>
                </div>
            </div>
            
            <div class="w-2/5 flex flex-col border-l border-gray-200">
                <div class="p-4 border-b bg-gray-50">
                    <h4 class="text-lg font-bold text-gray-900">
                       Document Verification
                    </h4>
                    <p class="text-sm text-gray-600 mt-1">Review and verify all documents submitted by <span id="unifiedAlumniName" class="font-semibold"></span></p>
                </div>
                
                <div class="flex-1 overflow-y-auto p-2">
                    <div id="unifiedDocumentList" class="space-y-3 mb-6">
                    </div>
                    
                    <div id="bulkActionsSection" class="border-t pt-2">
                        <h5 class="text-md font-bold text-gray-800 mb-3">Bulk Actions</h5>
                        
                        <div class="mb-4">
                            <button onclick="processBulkApproval()" id="bulkApproveBtn" 
                                    class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-check-circle mr-2"></i> Approve All Documents
                            </button>
                            <p class="text-xs text-gray-500 mt-1 text-center">All documents will be marked as Approved</p>
                        </div>
                        
                        <div class="mb-4">
                            <button onclick="openBulkRejectionPanel()" id="bulkRejectBtn"
                                    class="w-full bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-times-circle mr-2"></i> Reject Selected Documents
                            </button>
                            <p class="text-xs text-gray-500 mt-1 text-center">Selected documents will be rejected with individual reasons</p>
                        </div>
                        
                        <div>
                            <button onclick="processBulkRevert()" id="bulkRevertBtn"
                                    class="w-full bg-orange-600 text-white px-4 py-3 rounded-lg hover:bg-orange-700 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-undo mr-2"></i> Revert All to Pending
                            </button>
                            <p class="text-xs text-gray-500 mt-1 text-center">All document statuses will be set back to Pending</p>
                        </div>
                    </div>

                    <div id="bulkRejectionPanel" class="border-t pt-4 hidden">
                        <div class="w-full bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-700"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-md font-bold text-red-800">Reject Selected Documents</h3>
                                    <p class="text-sm text-red-700">Provide reasons for <span id="rejectCount" class="font-semibold">0</span> selected document(s).</p>
                                </div>
                            </div>
                        </div>

                        <form id="bulkRejectForm">
                            <input type="hidden" id="bulkRejectUserId" name="user_id">
                            
                            <div id="bulkRejectionDocuments" class="space-y-4 mb-6 max-h-96 overflow-y-auto pr-2">
                            </div>
                            
                            <div class="flex gap-3 border-t pt-4">
                                <button type="button" onclick="closeBulkRejectionPanel()" 
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
        </div>
        <div class="h-2 bg-gray-100 rounded-b-xl border-t border-gray-200"></div>
    </div>
</div>

<div id="individualActionModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-[70]">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div id="individualActionContent">
            </div>
    </div>
</div>

<script>
let currentUserId = null;
let currentAlumniName = null;
let currentEmploymentStatus = null;
let allDocuments = [];
let currentActiveDoc = null;

// Document type to full name map
const docTypeMap = {
    'COR': 'Certificate of Registration', 
    'COE': 'Certificate of Employment', 
    'B_CERT': 'Business Certificate'
};

// Document type specific rejection reasons
const documentRejectionReasons = <?= json_encode(getDocumentRejectionReasons()) ?>;

// Helper function to get the status color class
function getStatusColorClass(status, isDocument = false) {
    const docPrefix = isDocument ? 'border border-' : '';
    switch (status) {
        case 'Approved': return `${docPrefix}green-200 bg-green-100 text-green-800`;
        case 'Pending': return `${docPrefix}yellow-200 bg-yellow-100 text-yellow-800`;
        case 'Rejected': return `${docPrefix}red-200 bg-red-100 text-red-800`;
        default: return `${docPrefix}gray-200 bg-gray-100 text-gray-800`;
    }
}

// Helper function to check if all documents are approved
function allDocumentsApproved() {
    return allDocuments.every(doc => doc.document_status === 'Approved');
}

// Helper function to check if any documents are pending
function anyDocumentsPending() {
    return allDocuments.some(doc => doc.document_status === 'Pending');
}

// Helper function to check if any documents are rejected
function anyDocumentsRejected() {
    return allDocuments.some(doc => doc.document_status === 'Rejected');
}

// Helper function to update bulk action buttons state
function updateBulkActionButtons() {
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    const bulkRevertBtn = document.getElementById('bulkRevertBtn');
    
    const allApproved = allDocumentsApproved();
    const anyPending = anyDocumentsPending();
    const anyRejected = anyDocumentsRejected();
    
    // Update button states
    bulkApproveBtn.disabled = allApproved || !anyPending;
    bulkRejectBtn.disabled = !anyPending;
    bulkRevertBtn.disabled = !(allApproved || anyRejected);
}

// Main function to open unified document modal
function openUnifiedDocumentModal(alumniName, userId, employmentStatus, documentsJson) {
    const modal = document.getElementById('unifiedDocumentModal');
    const tabsContainer = document.getElementById('unifiedDocTabs');
    
    currentUserId = userId;
    currentAlumniName = alumniName;
    currentEmploymentStatus = employmentStatus;
    allDocuments = JSON.parse(documentsJson);
    
    if (!allDocuments || allDocuments.length === 0) {
        alert("No documents found for this alumni.");
        return;
    }
    
    // Update Modal Title and Alumni Name
    document.getElementById('unifiedModalTitle').innerHTML = `Document Review for <span class="text-indigo-600">${alumniName}</span>`;
    document.getElementById('unifiedAlumniName').textContent = alumniName;
    
    // Update document count badge
    document.getElementById('unifiedDocumentCountBadge').textContent = 
        `${allDocuments.length} document${allDocuments.length > 1 ? 's' : ''} total`;
    
    // 1. Generate Tabs
    tabsContainer.innerHTML = '';
    allDocuments.forEach((doc, index) => {
        const docName = docTypeMap[doc.document_type] || doc.document_type;
        const statusClass = getStatusColorClass(doc.document_status);
        
        // Add tab button
        tabsContainer.innerHTML += `
            <button 
                id="unified-tab-${doc.doc_id}"
                onclick="switchUnifiedDocumentTab(${doc.doc_id})"
                class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-700 hover:text-indigo-600 hover:border-indigo-500 transition-colors duration-150 flex items-center whitespace-nowrap">
                <i class="fas fa-file-${doc.document_type === 'COR' ? 'certificate' : (doc.document_type === 'COE' ? 'contract' : 'building')} mr-2"></i>
                <span>${docName}</span>
                <span id="unified-tab-status-${doc.doc_id}" class="ml-2 text-xs font-bold ${statusClass.replace('bg-', 'text-').replace('-100', '-600')}">(${doc.document_status})</span>
            </button>
        `;
    });
    
    // 2. Generate Document List in right panel
    updateDocumentList();
    
    // 3. Load the first document
    switchUnifiedDocumentTab(allDocuments[0].doc_id);
    
    // 4. Update bulk action buttons state and ensure default panel is shown
    updateBulkActionButtons();
    closeBulkRejectionPanel(false); // Ensure rejection panel is hidden on open
    
    // 5. Show Modal
    modal.classList.remove('hidden');
}

// Switch between document tabs
function switchUnifiedDocumentTab(docId) {
    const doc = allDocuments.find(d => d.doc_id == docId);
    
    if (!doc) return;
    
    currentActiveDoc = doc;
    
    // 1. Update Tabs visual state
    document.querySelectorAll('#unifiedDocTabs button').forEach(btn => {
        btn.classList.remove('border-indigo-600', 'text-indigo-600');
        btn.classList.add('border-transparent', 'text-gray-700');
    });
    
    const activeTab = document.getElementById(`unified-tab-${docId}`);
    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-700');
        activeTab.classList.add('border-indigo-600', 'text-indigo-600');
    }
    
    // 2. Update Document Viewer Content
    const statusEl = document.getElementById('unifiedDocCurrentStatus');
    let statusClass = getStatusColorClass(doc.document_status, true);
    statusEl.className = `px-3 py-1.5 inline-flex text-sm font-semibold rounded-full ${statusClass}`;
    statusEl.textContent = doc.document_status;
    
    // Rejection Details
    const rejectionDetailsEl = document.getElementById('unifiedDocRejectionDetails');
    rejectionDetailsEl.innerHTML = '';
    if (doc.document_status === 'Rejected' && doc.rejection_reason && doc.rejection_reason.trim() !== '') {
        rejectionDetailsEl.innerHTML = `
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                <h4 class="text-sm font-bold text-red-700 mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Rejection Reason:</h4>
                <p class="text-sm text-red-600 whitespace-pre-wrap">${doc.rejection_reason}</p>
            </div>
        `;
    }
    
    // Iframe Source
    document.getElementById('unifiedDocumentViewer').src = `../${doc.file_path}`;
    
    // 3. Update the document list selection
    updateDocumentListSelection(docId);
}

// Update document list in right panel
function updateDocumentList() {
    const container = document.getElementById('unifiedDocumentList');
    container.innerHTML = '';
    
    allDocuments.forEach(doc => {
        const docName = docTypeMap[doc.document_type] || doc.document_type;
        const statusClass = getStatusColorClass(doc.document_status);
        
        container.innerHTML += `
            <div class="document-list-item flex items-start justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                <div class="flex items-start space-x-3 flex-1">
                    <div class="mt-1">
                        <input type="checkbox" 
                               id="doc-check-${doc.doc_id}" 
                               value="${doc.doc_id}"
                               onchange="handleDocumentSelection(${doc.doc_id})"
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                               ${doc.document_status === 'Pending' ? '' : 'disabled'}>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <label for="doc-check-${doc.doc_id}" class="text-sm font-medium text-gray-700 cursor-pointer">
                                <i class="fas fa-file-${doc.document_type === 'COR' ? 'certificate' : (doc.document_type === 'COE' ? 'contract' : 'building')} mr-2 text-gray-400"></i>
                                ${docName}
                            </label>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">${doc.document_status}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            ${doc.document_status === 'Rejected' && doc.rejection_reason ? 
                                `<div class="mt-1 text-red-600"><i class="fas fa-info-circle mr-1"></i>${doc.rejection_reason.substring(0, 60)}${doc.rejection_reason.length > 60 ? '...' : ''}</div>` : 
                                ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
}

// Update document list selection
function updateDocumentListSelection(docId) {
    // Remove active class from all
    document.querySelectorAll('.document-list-item').forEach(div => {
        div.classList.remove('border-indigo-300', 'bg-indigo-50');
    });
    
    // Add active class to selected
    const checkbox = document.querySelector(`.document-list-item input[value="${docId}"]`);
    if (checkbox) {
        const selectedDiv = checkbox.closest('.document-list-item');
        selectedDiv.classList.add('border-indigo-300', 'bg-indigo-50');
    }
}

// Handle document checkbox selection
function handleDocumentSelection(docId) {
    const checkbox = document.getElementById(`doc-check-${docId}`);
    const parentDiv = checkbox.closest('.document-list-item');
    
    if (checkbox.checked) {
        parentDiv.classList.add('border-red-300', 'bg-red-50');
    } else {
        parentDiv.classList.remove('border-red-300', 'bg-red-50');
    }
    
    // If the rejection panel is open, update the count immediately
    const rejectCountEl = document.getElementById('rejectCount');
    if (rejectCountEl) {
        rejectCountEl.textContent = getSelectedDocumentIds().length;
    }
}

// Get selected document IDs
function getSelectedDocumentIds() {
    const selected = [];
    document.querySelectorAll('#unifiedDocumentList input[type="checkbox"]:checked').forEach(checkbox => {
        selected.push(parseInt(checkbox.value));
    });
    return selected;
}

// Process bulk approval
function processBulkApproval() {
    if (allDocumentsApproved()) {
        alert('All documents are already approved.');
        return;
    }
    
    if (!confirm(`Approve all ${allDocuments.length} documents for ${currentAlumniName}?`)) {
        return;
    }
    
    // Redirect to update_status.php with bulk approval
    const docIds = allDocuments.map(doc => doc.doc_id).join(',');
    window.location.href = `update_status.php?user_id=${currentUserId}&doc_ids=${docIds}&status=Approved&type=document_bulk&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
}

// Process bulk revert
function processBulkRevert() {
    if (!confirm(`Revert all documents to Pending status for ${currentAlumniName}?`)) {
        return;
    }
    
    // Redirect to update_status.php with bulk revert
    const docIds = allDocuments.map(doc => doc.doc_id).join(',');
    window.location.href = `update_status.php?user_id=${currentUserId}&doc_ids=${docIds}&status=Pending&type=document_bulk&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
}

// Open bulk rejection panel (INLINE)
function openBulkRejectionPanel() {
    const selectedIds = getSelectedDocumentIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one document to reject.');
        return;
    }
    
    document.getElementById('bulkActionsSection').classList.add('hidden');
    document.getElementById('bulkRejectionPanel').classList.remove('hidden');
    
    const container = document.getElementById('bulkRejectionDocuments');
    
    // Set user ID
    document.getElementById('bulkRejectUserId').value = currentUserId;
    document.getElementById('rejectCount').textContent = selectedIds.length;
    
    // Clear previous content
    container.innerHTML = '';
    
    // Add rejection reason inputs for each selected document
    selectedIds.forEach(docId => {
        const doc = allDocuments.find(d => d.doc_id == docId);
        if (!doc) return;
        
        const docName = docTypeMap[doc.document_type] || doc.document_type;
        const reasons = documentRejectionReasons[doc.document_type] || [];
        
        container.innerHTML += `
            <div class="p-4 border border-gray-200 rounded-lg bg-white" data-doc-id="${docId}">
                <h5 class="font-bold text-red-700 mb-2">${docName}</h5>
                
                ${reasons.length > 0 ? `
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Common Reasons:</label>
                        <div class="space-y-2 text-sm">
                            ${reasons.map((reason, index) => `
                                <div class="flex items-start">
                                    <input type="radio" 
                                           name="rejection_reason_${docId}" 
                                           value="${reason}" 
                                           id="reason_${docId}_${index}"
                                           class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300"
                                           onchange="handleReasonSelection(${docId}, '${reason.replace(/'/g, "\\'")}')">
                                    <label for="reason_${docId}_${index}" class="cursor-pointer">${reason}</label>
                                </div>
                            `).join('')}
                            <div class="flex items-start">
                                <input type="radio" 
                                       name="rejection_reason_${docId}" 
                                       value="custom" 
                                       id="reason_${docId}_custom"
                                       class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300"
                                       onchange="handleReasonSelection(${docId}, 'custom')">
                                <label for="reason_${docId}_custom" class="cursor-pointer">Other (specify below)</label>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="mb-3">
                        <p class="text-sm text-gray-600 mb-2">No common reasons defined for this document type.</p>
                    </div>
                `}
                
                <div>
                    <label for="custom_reason_${docId}" class="block text-sm font-medium text-gray-700 mb-2">
                        ${reasons.length > 0 ? 'Additional Notes (Required if selecting "Other")' : 'Reason for rejection (Required)'}
                    </label>
                    <textarea 
                        id="custom_reason_${docId}" 
                        name="custom_reason_${docId}"
                        rows="2"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                        placeholder="${reasons.length > 0 ? 'Specify your reason here...' : 'Please provide a reason for rejection...'}"
                        oninput="handleCustomReasonInput(${docId})"></textarea>
                    <div id="error_${docId}" class="text-red-600 text-sm mt-1 hidden">Please provide a rejection reason.</div>
                </div>
            </div>
        `;
    });
}

// Handle reason selection in bulk rejection
function handleReasonSelection(docId, reason) {
    const textarea = document.getElementById(`custom_reason_${docId}`);
    const errorElement = document.getElementById(`error_${docId}`);
    
    if (reason !== 'custom') {
        textarea.required = false;
        errorElement.classList.add('hidden');
    } else {
        textarea.required = true;
    }
}

// Handle custom reason input
function handleCustomReasonInput(docId) {
    const textarea = document.getElementById(`custom_reason_${docId}`);
    const customRadio = document.getElementById(`reason_${docId}_custom`);
    
    if (textarea.value.trim() !== '' && customRadio) {
        customRadio.checked = true;
    }
}

// Close bulk rejection panel
function closeBulkRejectionPanel(resetCheckboxes = true) {
    document.getElementById('bulkRejectionPanel').classList.add('hidden');
    document.getElementById('bulkActionsSection').classList.remove('hidden');
    document.getElementById('bulkRejectForm').reset();

    if (resetCheckboxes) {
        // Clear all rejection panel related visual selections
        document.querySelectorAll('#unifiedDocumentList input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        document.querySelectorAll('.document-list-item').forEach(div => {
            div.classList.remove('border-red-300', 'bg-red-50');
        });
    }
}

// Submit bulk rejection
document.getElementById('bulkRejectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const selectedIds = getSelectedDocumentIds();
    let allValid = true;
    const rejectionData = [];
    
    // Validate each selected document
    selectedIds.forEach(docId => {
        const doc = allDocuments.find(d => d.doc_id == docId);
        if (!doc) return;
        
        const reasons = documentRejectionReasons[doc.document_type] || [];
        const hasCommonReasons = reasons.length > 0;
        
        let finalReason = '';
        const selectedReason = document.querySelector(`input[name="rejection_reason_${docId}"]:checked`);
        const customReason = document.getElementById(`custom_reason_${docId}`).value.trim();
        const errorElement = document.getElementById(`error_${docId}`);
        
        // Assume failure until proven otherwise
        let docValid = false; 

        if (!hasCommonReasons) {
            // No common reasons - custom reason is required
            if (customReason) {
                finalReason = customReason;
                docValid = true;
            }
        } else {
            // Has common reasons
            if (selectedReason) {
                if (selectedReason.value === 'custom') {
                    if (customReason) {
                        finalReason = customReason;
                        docValid = true;
                    }
                } else {
                    finalReason = selectedReason.value;
                    if (customReason) {
                        finalReason += ` | Note: ${customReason}`;
                    }
                    docValid = true;
                }
            } else if (customReason) {
                // Only custom reason provided, without selecting 'Other' radio button
                finalReason = customReason;
                docValid = true;
            }
        }
        
        if (!docValid) {
            errorElement.textContent = 'A rejection reason is required for this document.';
            errorElement.classList.remove('hidden');
            allValid = false;
        } else {
            // Clear error if valid
            errorElement.classList.add('hidden');
            
            // Add to rejection data
            rejectionData.push({
                doc_id: docId,
                reason: finalReason
            });
        }
    });
    
    if (!allValid) return;
    
    // Create URL with all rejection data
    const baseUrl = `update_status.php?user_id=${currentUserId}&type=document_bulk_reject&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
    const params = rejectionData.map(data => `rejections[]=${encodeURIComponent(JSON.stringify(data))}`).join('&');
    
    window.location.href = `${baseUrl}&${params}`;
});

// Close unified document modal
function closeUnifiedDocumentModal() {
    document.getElementById('unifiedDocumentModal').classList.add('hidden');
    document.getElementById('unifiedDocumentViewer').src = '';
    
    // Reset state
    currentUserId = null;
    currentAlumniName = null;
    currentEmploymentStatus = null;
    allDocuments = [];
    currentActiveDoc = null;
    closeBulkRejectionPanel(true);
}

// Individual document actions (fallback if needed)
function openIndividualActionModal(docId, action) {
    const doc = allDocuments.find(d => d.doc_id == docId);
    if (!doc) return;
    
    const docName = docTypeMap[doc.document_type] || doc.document_type;
    const modal = document.getElementById('individualActionModal');
    const content = document.getElementById('individualActionContent');
    
    if (action === 'approve') {
        content.innerHTML = `
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-center mb-2">Approve Document</h3>
            <p class="text-gray-600 text-center mb-6">Approve <span class="font-semibold">${docName}</span> for ${currentAlumniName}?</p>
            <div class="flex gap-3">
                <button onclick="closeIndividualActionModal()" class="flex-1 bg-gray-300 py-2 rounded-lg hover:bg-gray-400 transition-colors">Cancel</button>
                <button onclick="processIndividualApproval(${docId})" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-colors">Confirm</button>
            </div>
        `;
    } else if (action === 'reject') {
        const reasons = documentRejectionReasons[doc.document_type] || [];
        const hasCommonReasons = reasons.length > 0;
        
        content.innerHTML = `
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-times text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-center mb-2">Reject Document</h3>
            <p class="text-gray-600 text-center mb-4">Reason for rejecting <span class="font-semibold">${docName}</span>:</p>
            <form onsubmit="return submitIndividualRejection(${docId})">
                ${hasCommonReasons ? `
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Common Reasons:</label>
                        <div class="space-y-2" id="individualReasons">
                            ${reasons.map((reason, index) => `
                                <div class="flex items-start">
                                    <input type="radio" name="rejection_reason" value="${reason}" id="ind_reason_${index}" class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300">
                                    <label for="ind_reason_${index}" class="text-sm cursor-pointer">${reason}</label>
                                </div>
                            `).join('')}
                            <div class="flex items-start">
                                <input type="radio" name="rejection_reason" value="custom" id="ind_reason_custom" class="mt-1 mr-2 text-red-600 focus:ring-red-500 border-gray-300">
                                <label for="ind_reason_custom" class="text-sm cursor-pointer">Other (specify below)</label>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">No common reasons defined for this document type.</p>
                    </div>
                `}
                <div class="mb-6">
                    <label for="individual_custom_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        ${hasCommonReasons ? 'Additional Notes (Required if selecting "Other")' : 'Reason for rejection (Required)'}
                    </label>
                    <textarea id="individual_custom_reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"></textarea>
                    <div id="individual_error" class="text-red-600 text-sm mt-1 hidden"></div>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeIndividualActionModal()" class="flex-1 bg-gray-300 py-2 rounded-lg hover:bg-gray-400 transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors">Reject</button>
                </div>
            </form>
        `;
    }
    
    modal.classList.remove('hidden');
}

function closeIndividualActionModal() {
    document.getElementById('individualActionModal').classList.add('hidden');
}

function processIndividualApproval(docId) {
    window.location.href = `update_status.php?user_id=${currentUserId}&doc_id=${docId}&status=Approved&type=document&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
}

function submitIndividualRejection(docId) {
    const doc = allDocuments.find(d => d.doc_id == docId);
    if (!doc) return false;
    
    const reasons = documentRejectionReasons[doc.document_type] || [];
    const hasCommonReasons = reasons.length > 0;
    
    let finalReason = '';
    const selectedReason = document.querySelector('input[name="rejection_reason"]:checked');
    const customReason = document.getElementById('individual_custom_reason').value.trim();
    const errorElement = document.getElementById('individual_error');

    let docValid = false; 
    
    if (!hasCommonReasons) {
        if (customReason) {
            finalReason = customReason;
            docValid = true;
        }
    } else {
        if (selectedReason) {
            if (selectedReason.value === 'custom') {
                if (customReason) {
                    finalReason = customReason;
                    docValid = true;
                }
            } else {
                finalReason = selectedReason.value;
                if (customReason) {
                    finalReason += ` | Note: ${customReason}`;
                }
                docValid = true;
            }
        } else if (customReason) {
            finalReason = customReason;
            docValid = true;
        }
    }
    
    if (!docValid) {
        errorElement.textContent = 'A rejection reason is required.';
        errorElement.classList.remove('hidden');
        return false;
    }

    window.location.href = `update_status.php?user_id=${currentUserId}&doc_id=${docId}&status=Rejected&type=document&reason=${encodeURIComponent(finalReason)}&<?= htmlspecialchars($queryString) ?>&page=<?= $current_page ?>`;
    return false;
}

// Event listeners for closing modals
document.addEventListener('click', e => {
    // Only check for closing the unified modal
    if (e.target.id === 'unifiedDocumentModal' || e.target.id === 'individualActionModal') {
        if (e.target.id === 'unifiedDocumentModal') closeUnifiedDocumentModal();
        if (e.target.id === 'individualActionModal') closeIndividualActionModal();
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (!document.getElementById('unifiedDocumentModal').classList.contains('hidden')) {
            // If rejection panel is open, close it first
            if (!document.getElementById('bulkRejectionPanel').classList.contains('hidden')) {
                closeBulkRejectionPanel(false); // Close panel, but keep checkboxes checked
            } else {
                closeUnifiedDocumentModal();
            }
        } else if (!document.getElementById('individualActionModal').classList.contains('hidden')) {
            closeIndividualActionModal();
        }
    }
});

// Hover modal functions (keep existing)
let hoverTimeout = null;
let isModalHovered = false;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alumni-name-hover').forEach(el => {
        el.addEventListener('mouseenter', () => { 
            clearTimeout(hoverTimeout); 
            showAlumniDetails(el.dataset.userId); 
        });
        el.addEventListener('mouseleave', () => { 
            hoverTimeout = setTimeout(() => { 
                if (!isModalHovered) closeAlumniModal(); 
            }, 300); 
        });
    });
});

function showAlumniDetails(id) {
    const modal = document.getElementById('alumniModal');
    const content = document.getElementById('alumniModalContent');
    
    fetch(`get_alumni_details.php?user_id=${id}`)
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
            modal.classList.remove('hidden');
            
            content.onmouseenter = () => { 
                isModalHovered = true; 
                clearTimeout(hoverTimeout); 
            };
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
</script>

<?php

// 5. Helper Functions for Status Badges (Color, Border, Icon)

function getEmploymentStatusColor($s) { 
    return '';
}

function getEmploymentStatusBorder($s) { 
    return '';
}

function getEmploymentStatusIcon($s) { 
    if ($s === 'No Recent Update' || empty($s)) return 'fas fa-user-clock text-gray-400';
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
    if ($s === 'No Recent Update') return 'text-gray-400';
    $colors = [
        'Approved'=>'text-green-600',
        'Pending'=>'text-yellow-600',
        'Rejected'=>'text-red-600'
    ];
    return $colors[$s] ?? 'text-gray-600'; 
}

function getSubmissionStatusBorder($s) { 
    return '';
}

function getSubmissionStatusIcon($s) { 
    if ($s === 'No Recent Update') return 'fas fa-user-clock text-gray-400';
    $icons = [
        'Approved'=>'fas fa-check-circle text-green-600',
        'Pending'=>'fas fa-clock text-yellow-600',
        'Rejected'=>'fas fa-times-circle text-red-600'
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