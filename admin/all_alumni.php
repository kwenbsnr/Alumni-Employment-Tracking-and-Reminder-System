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
$page_title = "All Alumni Management";
$active_page = "alumni_management";

// Get filter parameters from the URL
$search = $_GET['search'] ?? '';
$employment_status = $_GET['employment_status'] ?? '';
$batch_year = $_GET['batch_year'] ?? ''; // <--- BATCH FILTER VARIABLE

$per_page = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

// 3. Fetch Global Alumni Statistics (Total Count for Stats and Pagination)
$statsQuery = "SELECT
    COUNT(*) as total_alumni
    FROM users u
    WHERE u.role = 'alumni'";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$globalStats = $statsResult->fetch_assoc();

$total_alumni = $globalStats['total_alumni'] ?? 0;

// --- Fetch Distinct Batch Years for Filter Dropdown ---
$batchYearsQuery = "SELECT DISTINCT batch_year FROM users WHERE role = 'alumni' AND batch_year IS NOT NULL ORDER BY batch_year DESC";
$batchYearsResult = $conn->query($batchYearsQuery);
$availableBatchYears = $batchYearsResult ? $batchYearsResult->fetch_all(MYSQLI_ASSOC) : [];
// --- END Fetch Distinct Batch Years ---

// 4. Build Query with Filters (for filtered total count and paginated list)
$whereConditions = ["u.role = 'alumni'"];
$params = [];
$types = '';

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
// <--- NEW BATCH FILTER CONDITION --->
if (!empty($batch_year)) {
    $whereConditions[] = "u.batch_year = ?";
    $params[] = $batch_year;
    $types .= 's';
}
// <--- END NEW BATCH FILTER CONDITION --->

$whereClause = implode(" AND ", $whereConditions);

// --- TOTAL FILTERED COUNT FOR PAGINATION ---
$countQuery = "SELECT COUNT(*) as filtered_count FROM users u LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);

// Bind parameters dynamically for the count query
if (!empty($params)) {
    // Note: Temporary $bind_count_params is needed as $params will be modified later for LIMIT/OFFSET
    $bind_count_params = array_merge([$types], $params);
    $refs_count = [];
    foreach ($bind_count_params as $key => $value) {
        $refs_count[$key] = &$bind_count_params[$key];
    }
    call_user_func_array([$countStmt, 'bind_param'], $refs_count);
}

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


// --- START: NEW FETCH FILTERED STATISTICS ---

// Parameters for stats queries (same as count query, excluding LIMIT/OFFSET)
$tempTypes = $types;
$tempParams = $params;

if ($filteredCount > 0) {
    // 4.5. Fetch Filtered Employment Status Counts
    $empStatsQuery = "
        SELECT ap.employment_status, COUNT(*) as count 
        FROM users u 
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE $whereClause 
        GROUP BY ap.employment_status 
        ORDER BY count DESC
    ";
    $empStatsStmt = $conn->prepare($empStatsQuery);
    
    // Bind parameters dynamically for the statistics queries
    if (!empty($tempParams)) {
        $bind_emp_params = array_merge([$tempTypes], $tempParams);
        $refs_emp = [];
        foreach ($bind_emp_params as $key => $value) {
            $refs_emp[$key] = &$bind_emp_params[$key];
        }
        call_user_func_array([$empStatsStmt, 'bind_param'], $refs_emp);
    }

    $empStatsStmt->execute();
    $employmentStats = $empStatsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4.6. Fetch Filtered Batch Year Counts
    $batchStatsQuery = "
        SELECT u.batch_year, COUNT(*) as count 
        FROM users u 
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE $whereClause AND u.batch_year IS NOT NULL 
        GROUP BY u.batch_year 
        ORDER BY u.batch_year DESC
    ";
    $batchStatsStmt = $conn->prepare($batchStatsQuery);
    
    // Re-use $bind_emp_params, $refs_emp, and $tempTypes/$tempParams from the previous block
    if (!empty($tempParams)) {
        call_user_func_array([$batchStatsStmt, 'bind_param'], $refs_emp);
    }
    
    $batchStatsStmt->execute();
    $batchStats = $batchStatsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $employmentStats = [];
    $batchStats = [];
}
// --- END: NEW FETCH FILTERED STATISTICS ---


// --- PAGINATED LIST QUERY ---
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
        ap.photo_path
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

// Bind parameters dynamically (includes search/filter params + LIMIT/OFFSET)
$bind_params = array_merge([$types], $params);
$refs = [];
foreach ($bind_params as $key => $value) {
    $refs[$key] = &$bind_params[$key];
}

// Check if there are parameters to bind before calling bind_param
if (!empty($bind_params)) {
    call_user_func_array([$alumniStmt, 'bind_param'], $refs);
}

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
    
    <div class="bg-white p-4 rounded-2xl shadow-xl border-t-4 border-indigo-600">
        <div class="flex items-center space-x-3">
            <a href="alumni_management.php" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    All Alumni
                </h1>
                <p class="text-sm text-gray-600 mt-1">
                    Total: <span class="font-semibold"><?= $total_alumni ?></span> registered alumni
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-lg border-t border-gray-100">
        <form method="GET" action="" class="flex flex-col sm:flex-row flex-wrap gap-3 items-start sm:items-end">
            
            <div class="flex-1 min-w-0 w-full sm:w-auto">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Name/Email</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter name or email...">
            </div>
            
            <div class="w-full sm:w-40">
                <label for="batch_year" class="block text-sm font-medium text-gray-700 mb-1">Batch Year</label>
                <select id="batch_year" name="batch_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Batches</option>
                    <?php foreach ($availableBatchYears as $year): ?>
                        <option value="<?= htmlspecialchars($year['batch_year']) ?>" <?= $batch_year === $year['batch_year'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['batch_year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
            
            <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex-shrink-0">
                    <i class="fas fa-filter mr-1"></i> Apply
                </button>
                <a href="all_alumni.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors flex-shrink-0">
                    <i class="fas fa-eraser mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <?php if ($filteredCount > 0): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white p-4 rounded-xl shadow-lg border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-graduation-cap text-indigo-500 mr-2"></i> Batch Year Distribution
            </h3>
            <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                <?php foreach ($batchStats as $stat): ?>
                    <?php 
                        // Calculate percentage relative to the $filteredCount
                        $percentage = round(($stat['count'] / $filteredCount) * 100); 
                    ?>
                    <div class="flex items-center">
                        <span class="text-sm font-medium text-gray-700 w-20 flex-shrink-0"><?= htmlspecialchars($stat['batch_year'] ?? 'N/A') ?></span>
                        <div class="flex-grow bg-gray-200 rounded-full h-2.5 mx-3">
                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 w-12 text-right"><?= $stat['count'] ?></span>
                        <span class="text-xs text-gray-500 ml-1">(<?= $percentage ?>%)</span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($batchStats)): ?>
                    <p class="text-gray-500 text-sm">No batch year data found for the current filter.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-lg border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-briefcase text-green-500 mr-2"></i> Employment Status Distribution
            </h3>
            <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                <?php foreach ($employmentStats as $stat): ?>
                    <?php 
                        $status_name = empty($stat['employment_status']) ? 'No Recent Update' : htmlspecialchars($stat['employment_status']);
                        $percentage = round(($stat['count'] / $filteredCount) * 100);
                        $bar_color = getStatusBarColor($status_name); // Use a new helper function
                    ?>
                    <div class="flex items-center">
                        <span class="text-sm font-medium text-gray-700 w-40 flex-shrink-0 truncate" title="<?= $status_name ?>"><?= $status_name ?></span>
                        <div class="flex-grow bg-gray-200 rounded-full h-2.5 mx-3">
                            <div class="h-2.5 rounded-full <?= $bar_color ?>" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 w-12 text-right"><?= $stat['count'] ?></span>
                        <span class="text-xs text-gray-500 ml-1">(<?= $percentage ?>%)</span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($employmentStats)): ?>
                    <p class="text-gray-500 text-sm">No employment status data found for the current filter.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch Year</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents Uploaded</th> 
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($alumni = $alumniResult->fetch_assoc()): ?>
                        <?php
                        // Fetch documents for the current alumni
                        $docStmt = $conn->prepare("SELECT doc_id, document_type, file_path, document_status, rejection_reason FROM alumni_documents WHERE user_id = ?");
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
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($alumni['name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($alumni['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900 font-medium">
                                    <?= htmlspecialchars($alumni['batch_year'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $emp_status_text = empty($alumni['employment_status']) ? 'No Recent Update' : htmlspecialchars($alumni['employment_status']);
                                    $emp_class = $emp_status_text === 'No Recent Update' ? 'text-gray-400' : 'text-gray-900 font-medium';
                                ?>
                                <span class="text-sm <?= $emp_class ?>">
                                    <?= $emp_status_text ?>
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
                
                // Get color class for document status
                $status_class = getDocumentStatusColor($doc['document_status']);
                ?>
                <div class="flex items-center rounded px-2 py-1">
                    <span class="font-semibold text-gray-800 text-sm mr-2"><?= $name ?></span>
                    
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $status_class ?>">
                        <?= htmlspecialchars($doc['document_status']) ?>
                    </span>

                    <?php if (!empty($doc['file_path'])): ?>
                    <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="ml-2 text-indigo-600 hover:text-indigo-800 transition-colors" title="View Document">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php endif; ?>
                    </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="text-gray-400 text-sm">No recent uploads</span>
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
            <p class="text-gray-500 mt-1">Try adjusting your filters or check if any alumni have registered.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Placeholder for the function used in the table, as the original is in admin_format.php
function getDocumentStatusColor(status) { 
    switch (status) {
        case 'Approved': return 'bg-green-100 text-green-800 border border-green-200';
        case 'Pending': return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        case 'Rejected': return 'bg-red-100 text-red-800 border border-red-200';
        default: return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}
</script>

<?php

// 5. Helper Functions 

function getDocumentStatusColor($s) { 
    $colors = [
        'Approved'=>'bg-green-100 text-green-800 border border-green-200',
        'Pending'=>'bg-yellow-100 text-yellow-800 border border-yellow-200',
        'Rejected'=>'bg-red-100 text-red-800 border border-red-200'
    ];
    return $colors[$s] ?? 'bg-gray-100 text-gray-800 border border-gray-200'; 
}

// NEW HELPER FUNCTION for the status distribution bars
function getStatusBarColor($status) {
    switch ($status) {
        case 'Employed': return 'bg-green-600';
        case 'Self-Employed': return 'bg-blue-600';
        case 'Employed & Student': return 'bg-purple-600';
        case 'Student': return 'bg-yellow-600';
        case 'Unemployed': return 'bg-red-600';
        default: return 'bg-gray-400';
    }
}

// Capture the buffered content and include the main format file
$page_content = ob_get_clean();
include("admin_format.php"); 
?>