<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Alumni Management";

// Initialize variables
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : '';
$employment_filter = isset($_GET['employment_status']) ? trim($_GET['employment_status']) : 'All';
$req_status_filter = isset($_GET['requirement_status']) ? trim($_GET['requirement_status']) : 'All';
$local_search = isset($_GET['local_search']) ? trim($_GET['local_search']) : $search_term;

// Fetch distinct graduation years and counts
$years_query = "
    SELECT year_graduated, COUNT(*) AS alumni_count
    FROM alumni_profile
    WHERE year_graduated IS NOT NULL";
if ($search_term) {
    $years_query .= " AND (CONCAT(first_name, ' ', last_name) LIKE ? OR user_id IN (SELECT id FROM users WHERE email LIKE ?))";
}
if ($employment_filter !== 'All') {
    $years_query .= " AND employment_status = ?";
}
$years_query .= " GROUP BY year_graduated ORDER BY year_graduated DESC";
$stmt = $conn->prepare($years_query);
if ($search_term && $employment_filter !== 'All') {
    $like_term = "%$search_term%";
    $stmt->bind_param("sss", $like_term, $like_term, $employment_filter);
} elseif ($search_term) {
    $like_term = "%$search_term%";
    $stmt->bind_param("ss", $like_term, $like_term);
} elseif ($employment_filter !== 'All') {
    $stmt->bind_param("s", $employment_filter);
}
$stmt->execute();
$years_result = $stmt->get_result();
$batches = [];
while ($row = $years_result->fetch_assoc()) {
    $batches[] = $row;
}

// Fetch alumni data
$alumni = [];
if ($search_term || $selected_year) {
    $query = "
        SELECT ap.alumni_id, ap.first_name, ap.last_name, ap.employment_status, ap.year_graduated, u.email,
               COUNT(ad.doc_id) AS total_docs,
               SUM(CASE WHEN ad.document_status = 'Pending' THEN 1 ELSE 0 END) AS pending_docs,
               SUM(CASE WHEN ad.document_status = 'Approved' THEN 1 ELSE 0 END) AS approved_docs,
               SUM(CASE WHEN ad.document_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_docs
        FROM alumni_profile ap
        LEFT JOIN users u ON ap.user_id = u.id
        LEFT JOIN alumni_documents ad ON ap.alumni_id = ad.alumni_id
        WHERE 1=1";
    if ($selected_year) {
        $query .= " AND ap.year_graduated = ?";
    }
    if ($search_term || $local_search) {
        $query .= " AND (CONCAT(ap.first_name, ' ', ap.last_name) LIKE ? OR u.email LIKE ?)";
    }
    if ($employment_filter !== 'All') {
        $query .= " AND ap.employment_status = ?";
    }
    $query .= " GROUP BY ap.alumni_id";
    if ($req_status_filter !== 'All') {
        $query .= " HAVING ";
        if ($req_status_filter === 'Pending') {
            $query .= "SUM(CASE WHEN ad.document_status = 'Pending' THEN 1 ELSE 0 END) > 0";
        } elseif ($req_status_filter === 'Rejected') {
            $query .= "SUM(CASE WHEN ad.document_status = 'Rejected' THEN 1 ELSE 0 END) > 0";
        } elseif ($req_status_filter === 'Approved') {
            $query .= "COUNT(ad.doc_id) = SUM(CASE WHEN ad.document_status = 'Approved' THEN 1 ELSE 0 END) AND COUNT(ad.doc_id) > 0";
        } elseif ($req_status_filter === 'None') {
            $query .= "COUNT(ad.doc_id) = 0";
        }
    }
    $stmt = $conn->prepare($query);
    if ($selected_year && ($search_term || $local_search) && $employment_filter !== 'All') {
        $like_term = "%" . ($local_search ?: $search_term) . "%";
        $stmt->bind_param("ssss", $selected_year, $like_term, $like_term, $employment_filter);
    } elseif ($selected_year && ($search_term || $local_search)) {
        $like_term = "%" . ($local_search ?: $search_term) . "%";
        $stmt->bind_param("sss", $selected_year, $like_term, $like_term);
    } elseif ($selected_year && $employment_filter !== 'All') {
        $stmt->bind_param("ss", $selected_year, $employment_filter);
    } elseif ($selected_year) {
        $stmt->bind_param("s", $selected_year);
    } elseif (($search_term || $local_search) && $employment_filter !== 'All') {
        $like_term = "%" . ($local_search ?: $search_term) . "%";
        $stmt->bind_param("sss", $like_term, $like_term, $employment_filter);
    } elseif ($search_term || $local_search) {
        $like_term = "%" . ($local_search ?: $search_term) . "%";
        $stmt->bind_param("ss", $like_term, $like_term);
    } elseif ($employment_filter !== 'All') {
        $stmt->bind_param("s", $employment_filter);
    }
} else {
    // Default view: minimal data for JavaScript (document modal)
    $query = "
        SELECT ap.alumni_id, ap.first_name, ap.last_name, ap.employment_status, ap.year_graduated
        FROM alumni_profile ap
    ";
    $stmt = $conn->prepare($query);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Derive requirement_status only for search or detailed view
    if ($search_term || $selected_year) {
        $row['requirement_status'] = 'None';
        $pending_docs = isset($row['pending_docs']) ? $row['pending_docs'] : 0;
        $rejected_docs = isset($row['rejected_docs']) ? $row['rejected_docs'] : 0;
        $approved_docs = isset($row['approved_docs']) ? $row['approved_docs'] : 0;
        $total_docs = isset($row['total_docs']) ? $row['total_docs'] : 0;
        if ($pending_docs > 0) {
            $row['requirement_status'] = 'Pending';
        } elseif ($rejected_docs > 0) {
            $row['requirement_status'] = 'Rejected';
        } elseif ($approved_docs == $total_docs && $total_docs > 0) {
            $row['requirement_status'] = 'Approved';
        }
    }
    $alumni[] = $row;
}

ob_start();
?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>

<div class="container mx-auto px-4">
    <?php if ($selected_year || $search_term): ?>
        <!-- Detailed Batch View or Search Results -->
        <div class="mb-6">
            <?php if ($selected_year): ?>
                <a href="alumni_management.php<?php echo $search_term ? '?search=' . urlencode($search_term) . ($employment_filter !== 'All' ? '&employment_status=' . urlencode($employment_filter) : '') . ($req_status_filter !== 'All' ? '&requirement_status=' . urlencode($req_status_filter) : '') : ''; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Batch View
                </a>
                <h2 class="text-2xl font-bold">Alumni List for Year <?php echo htmlspecialchars($selected_year); ?></h2>
            <?php else: ?>
                <h2 class="text-2xl font-bold mb-4">Search Results for "<?php echo htmlspecialchars($search_term); ?>"</h2>
            <?php endif; ?>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search within <?php echo $selected_year ? 'Batch' : 'Results'; ?></label>
                    <input type="text" id="localSearch" value="<?php echo htmlspecialchars($local_search); ?>" placeholder="Search by name or email" class="w-full border rounded-lg p-2">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employment Status</label>
                    <select id="employmentFilter" class="w-full border rounded-lg p-2">
                        <option value="All" <?php echo $employment_filter === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="Employed" <?php echo $employment_filter === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                        <option value="Self-Employed" <?php echo $employment_filter === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                        <option value="Unemployed" <?php echo $employment_filter === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                        <option value="Student" <?php echo $employment_filter === 'Student' ? 'selected' : ''; ?>>Student</option>
                        <option value="Employed & Student" <?php echo $employment_filter === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Requirement Status</label>
                    <select id="reqStatusFilter" class="w-full border rounded-lg p-2">
                        <option value="All" <?php echo $req_status_filter === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="Pending" <?php echo $req_status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $req_status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $req_status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="None" <?php echo $req_status_filter === 'None' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requirement Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($alumni as $a): ?>
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($a['email'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($a['requirement_status'] ?? 'None'); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?php echo (isset($a['total_docs']) ? $a['total_docs'] : 0) . ' (Pending: ' . (isset($a['pending_docs']) ? $a['pending_docs'] : 0) . ')'; ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($a['employment_status'] ?? 'Not Set'); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <button onclick="showDocs(<?php echo $a['alumni_id']; ?>)" class="text-blue-600 hover:text-blue-800">View Documents</button>
                                <a href="edit_alumni.php?id=<?php echo $a['alumni_id']; ?>" class="ml-4 text-green-600 hover:text-green-800">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Batch Card View -->
        <div class="mb-6">
            <form action="alumni_management.php" method="GET" class="flex flex-col md:flex-row items-center gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by name or email" class="w-full border rounded-lg p-2">
                </div>
                <div class="flex-1">
                    <select name="employment_status" class="w-full border rounded-lg p-2">
                        <option value="All" <?php echo $employment_filter === 'All' ? 'selected' : ''; ?>>All Employment Status</option>
                        <option value="Employed" <?php echo $employment_filter === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                        <option value="Self-Employed" <?php echo $employment_filter === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                        <option value="Unemployed" <?php echo $employment_filter === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                        <option value="Student" <?php echo $employment_filter === 'Student' ? 'selected' : ''; ?>>Student</option>
                        <option value="Employed & Student" <?php echo $employment_filter === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                    </select>
                </div>
                <div class="flex-1">
                    <select name="requirement_status" class="w-full border rounded-lg p-2">
                        <option value="All" <?php echo $req_status_filter === 'All' ? 'selected' : ''; ?>>All Requirement Status</option>
                        <option value="Pending" <?php echo $req_status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $req_status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $req_status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="None" <?php echo $req_status_filter === 'None' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Search</button>
            </form>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($batches as $batch): ?>
                <div class="bg-white p-6 rounded-xl shadow-lg cursor-pointer hover:shadow-xl transition-shadow" onclick="window.location.href='alumni_management.php?year=<?php echo urlencode($batch['year_graduated']); ?><?php echo $search_term ? '&local_search=' . urlencode($search_term) : ''; ?><?php echo $employment_filter !== 'All' ? '&employment_status=' . urlencode($employment_filter) : ''; ?><?php echo $req_status_filter !== 'All' ? '&requirement_status=' . urlencode($req_status_filter) : ''; ?>'">
                    <h3 class="text-xl font-semibold mb-2">Batch <?php echo htmlspecialchars($batch['year_graduated']); ?></h3>
                    <p class="text-gray-600"><?php echo $batch['alumni_count']; ?> Alumni</p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Documents Modal -->
<div id="docsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-xl shadow-lg max-w-4xl w-full">
        <h2 class="text-xl font-bold mb-4">Alumni Documents</h2>
        <div id="docsContent" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200"></tbody>
            </table>
        </div>
        <button onclick="closeDocsModal()" class="mt-4 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Close</button>
    </div>
</div>

<script>
const alumniData = <?php echo json_encode($alumni); ?>;

function showDocs(alumniId) {
    const alumni = alumniData.find(a => a.alumni_id == alumniId);
    if (!alumni) return;
    const modal = document.getElementById("docsModal");
    const content = document.getElementById("docsContent").querySelector("tbody");
    fetch(`get_documents.php?alumni_id=${alumniId}`)
        .then(response => response.json())
        .then(docs => {
            content.innerHTML = docs.map(doc => `
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">${doc.document_type}</td>
                    <td class="px-4 py-2 max-w-xs truncate"><a href="../Uploads/${doc.file_path}" target="_blank" title="${doc.file_path}">${doc.file_path}</a></td>
                    <td class="px-4 py-2 whitespace-nowrap">${doc.document_status}</td>
                    <td class="px-4 py-2 max-w-xs break-words">${doc.rejection_reason || ''}</td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <select onchange="updateStatus(${doc.doc_id}, this.value, '${doc.document_type}', ${alumniId}, this)">
                            <option value="Pending" ${doc.document_status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Approved" ${doc.document_status === 'Approved' ? 'selected' : ''}>Approved</option>
                            <option value="Rejected" ${doc.document_status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                        <input type="text" id="reason_${doc.doc_id}" placeholder="Enter rejection reason" value="${doc.rejection_reason || ''}" class="border rounded-lg p-1 mt-2 w-full ${doc.document_status === 'Rejected' ? '' : 'hidden'}">
                    </td>
                </tr>
            `).join('');
            modal.classList.remove("hidden");
        });
}

function closeDocsModal() {
    document.getElementById("docsModal").classList.add("hidden");
}

function updateStatus(docId, status, docType, alumniId, selectElement) {
    const reasonInput = document.getElementById(`reason_${docId}`);
    const reason = status === 'Rejected' ? (reasonInput.value || 'No reason provided') : '';
    const reasonInputs = document.querySelectorAll(`#docsModal input[id^="reason_"]`);
    reasonInputs.forEach(input => {
        input.classList.add('hidden');
        if (input.id === `reason_${docId}` && status === 'Rejected') {
            input.classList.remove('hidden');
        }
    });
    window.location.href = `update_status.php?id=${docId}&status=${status}&reason=${encodeURIComponent(reason)}&alumni_id=${alumniId}&year=<?php echo urlencode($selected_year ?? ''); ?>&doc_type=${encodeURIComponent(docType)}`;
}

document.addEventListener("DOMContentLoaded", () => {
    const localSearch = document.getElementById("localSearch");
    const employmentFilter = document.getElementById("employmentFilter");
    const reqStatusFilter = document.getElementById("reqStatusFilter");

    function updateFilters() {
        const params = new URLSearchParams();
        <?php if ($selected_year): ?>
            params.set('year', '<?php echo addslashes($selected_year); ?>');
        <?php endif; ?>
        if (localSearch?.value) params.set('local_search', localSearch.value);
        if (employmentFilter?.value !== 'All') params.set('employment_status', employmentFilter.value);
        if (reqStatusFilter?.value !== 'All') params.set('requirement_status', reqStatusFilter.value);
        window.location.href = 'alumni_management.php?' + params.toString();
    }

    localSearch?.addEventListener("input", () => {
        clearTimeout(localSearch.timeout);
        localSearch.timeout = setTimeout(updateFilters, 500);
    });
    employmentFilter?.addEventListener("change", updateFilters);
    reqStatusFilter?.addEventListener("change", updateFilters);
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>