<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Activity Log";
$active_page = "activity_log";

// === UNDO HANDLER (commented out since Undo column is removed) ===
// if (isset($_POST['undo_action']) && isset($_POST['log_id'])) {
//     $log_id = $_POST['log_id'];
//     ... your undo logic ...
// }


// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if ($filter_type) {
    $whereConditions[] = "ul.update_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_date) {
    $whereConditions[] = "DATE(ul.updated_at) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM update_log ul $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch activities
$activityQuery = "
    SELECT 
        ul.log_id,
        ul.updated_by,
        ul.updated_id,
        ul.update_type,
        ul.update_details,
        ul.updated_at,
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as admin_name,
        CONCAT(
            u_updated.first_name, 
            IF(u_updated.middle_name IS NOT NULL AND u_updated.middle_name != '', CONCAT(' ', u_updated.middle_name), ''),
            ' ',
            u_updated.last_name,
            IF(u_updated.suffix IS NOT NULL AND u_updated.suffix != '', CONCAT(' ', u_updated.suffix), '')
        ) as alumni_name,
        u_updated.batch_year
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN users u_updated ON ul.updated_id = u_updated.user_id
    $whereClause
    ORDER BY ul.updated_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$activityStmt = $conn->prepare($activityQuery);
if ($params) $activityStmt->bind_param($types, ...$params);
$activityStmt->execute();
$activityResult = $activityStmt->get_result();

ob_start();
?>
<div class="space-y-6">
    <!-- HEADER & FILTERS (unchanged) -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex-1 md:flex md:justify-start">
            <div class="rounded-2xl bg-gradient-to-r from-purple-50 to-white p-4 shadow-lg border border-purple-100">
                <form method="GET" class="flex items-center gap-3">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex-1 min-w-[144px] max-w-[144px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Filter Action</label>
                            <select name="type" class="w-full px-2 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 transition text-sm">
                                <option value="">All Actions</option>
                                <option value="update" <?= $filter_type === 'update' ? 'selected' : '' ?>>Update</option>
                                <option value="approve" <?= $filter_type === 'approve' ? 'selected' : '' ?>>Approve</option>
                                <option value="reject" <?= $filter_type === 'reject' ? 'selected' : '' ?>>Reject</option>
                                <option value="undo" <?= $filter_type === 'undo' ? 'selected' : '' ?>>Undo</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[144px] max-w-[144px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="date" value="<?= $filter_date ?>" class="w-full px-2 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 transition text-sm">
                        </div>

                        <div class="flex gap-3 flex-shrink-0">
                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl shadow-lg hover:scale-105 hover:brightness-110 transition-all duration-200 text-sm font-semibold flex items-center gap-2">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="activity_log.php" class="px-6 py-3 bg-gray-100 text-gray-800 rounded-2xl shadow hover:scale-105 hover:bg-gray-200 transition-all duration-200 text-sm font-semibold flex items-center gap-2">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <a href="admin_dashboard.php" class="inline-flex items-center gap-2 px-5 py-3 bg-gray-600 text-white font-medium rounded-xl shadow-lg hover:bg-black transition-all duration-200">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    <!-- Table Header -->
    <div class="px-6 py-4 border-b border-blue-200 bg-gradient-to-r from-blue-500 to-blue-300 text-white rounded-t-xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-bold">Recent Activities</h3>
                <p class="mt-1 text-blue-100">All system actions and modifications</p>
            </div>
            <div class="mt-2 sm:mt-0">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-database mr-1.5"></i>
                    Total <?= $totalRows ?> records
                </span>
            </div>
        </div>
    </div>

    <!-- Table Content -->
    <div class="overflow-x-auto bg-white rounded-b-xl shadow-lg">
        <table class="w-full">
            <thead class="bg-gray-50 sticky top-0 border-b border-gray-200">
                <tr class="text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                    <th class="pl-6 pr-3 py-4">Action</th>
                    <th class="px-3 py-4">Details</th>
                    <th class="px-3 py-4">Admin</th>
                    <th class="px-3 py-4">Time</th>
                    <!-- Undo column removed -->
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200/60">
                <?php if ($activityResult->num_rows > 0): ?>
                    <?php while ($activity = $activityResult->fetch_assoc()): ?>
                        <tr class="group hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-white transition-all duration-200">
                            <!-- Action -->
                            <td class="pl-6 pr-3 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <div class="p-3 rounded-2xl <?= getActivityColor($activity['update_type']) ?> shadow-sm group-hover:scale-110 transition-transform duration-200">
                                            <i class="text-lg fas fa-<?= getActivityIcon($activity['update_type']) ?>"></i>
                                        </div>
                                        <?php if ($activity['update_type'] === 'approve'): ?>
                                            <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold <?= getActivityBadgeColor($activity['update_type']) ?>">
                                            <?= ucfirst($activity['update_type']) ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1.5 truncate">
                                            <?= getActivityCategory($activity['update_type']) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Details -->
                            <td class="px-3 py-5">
                                <div class="max-w-xs">
                                    <p class="font-medium text-gray-900 text-sm leading-6">
                                        <?= getConciseActivityText($activity) ?>
                                    </p>
                                    <?php if (!empty($activity['batch_year'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">Batch <?= htmlspecialchars($activity['batch_year']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Admin -->
                            <td class="px-3 py-5 whitespace-nowrap">
                                <div class="flex flex-col leading-tight">
                                    <span class="font-semibold text-gray-900"><?= htmlspecialchars($activity['admin_name']) ?></span>
                                    <span class="text-xs text-gray-500">Administrator</span>
                                </div>
                            </td>

                            <!-- Time -->
                            <td class="px-3 py-5 whitespace-nowrap">
                                <div class="text-left">
                                    <div class="flex flex-col items-start">
                                        <span class="font-medium text-gray-900 text-sm">
                                            <?= date('M j, Y', strtotime($activity['updated_at'])) ?>
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($activity['updated_at'])) ?>
                                        </span>
                                        <span class="text-xs text-blue-600 font-medium mt-1.5 flex items-center gap-1">
                                            <i class="fas fa-clock opacity-60"></i>
                                            <?= time_elapsed_string($activity['updated_at']) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Undo column completely removed -->
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center"> <!-- colspan changed from 5 → 4 -->
                            <div class="max-w-md mx-auto">
                                <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-inbox text-2xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">No activities found</h3>
                                <p class="text-gray-500 mb-4">No activity records match your current filters.</p>
                                <a href="activity_log.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200 text-sm font-medium">
                                    <i class="fas fa-refresh"></i> Reset filters
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION (unchanged) -->
    <?php if ($totalPages > 1): ?>
        <div class="px-6 py-5 border-t border-gray-200 bg-gray-50/50 rounded-b-xl">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <p class="text-sm text-gray-700">
                    Showing <span class="font-semibold"><?= ($offset + 1) ?></span>–<span class="font-semibold"><?= min($offset + $limit, $totalRows) ?></span> 
                    of <span class="font-semibold"><?= $totalRows ?></span> results
                </p>

                <div class="flex items-center gap-2">
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                       class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 font-medium <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <i class="fas fa-chevron-left text-xs"></i> Previous
                    </a>

                    <div class="flex items-center gap-1">
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $startPage + 4);
                        $startPage = max(1, $endPage - 4);
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                               class="min-w-[40px] h-10 flex items-center justify-center rounded-xl text-sm font-medium transition-all duration-200
                               <?= $i == $page ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                       class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 font-medium <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        Next <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Helper functions (unchanged)
function getActivityIcon($update_type) {
    switch ($update_type) {
        case 'approve': return 'check-circle';
        case 'reject':  return 'times-circle';
        case 'update':  return 'edit';
        case 'undo':    return 'undo';
        default:        return 'sync';
    }
}

function getActivityColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-600';
        case 'reject':  return 'bg-red-100 text-red-600';
        case 'update':  return 'bg-blue-100 text-blue-600';
        case 'undo':    return 'bg-orange-100 text-orange-600';
        default:        return 'bg-purple-100 text-purple-600';
    }
}

function getActivityBadgeColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-800';
        case 'reject':  return 'bg-red-100 text-red-800';
        case 'update':  return 'bg-blue-100 text-blue-800';
        case 'undo':    return 'bg-orange-100 text-orange-800';
        default:        return 'bg-gray-100 text-gray-800';
    }
}

function getActivityCategory($update_type) {
    switch ($update_type) {
        case 'approve':
        case 'reject':  return 'Profile Review';
        case 'update':  return 'Profile Update';
        case 'undo':    return 'Action Reversal';
        default:        return 'System Action';
    }
}

function getConciseActivityText($activity) {
    $name = !empty($activity['alumni_name']) ? htmlspecialchars($activity['alumni_name']) : "Alumni";
    switch ($activity['update_type']) {
        case 'approve': return "Approved {$name}'s profile";
        case 'reject':  return "Rejected {$name}'s profile";
        case 'update':  return "Updated {$name}'s information";
        case 'undo':    return "Undid previous action for {$name}";
        default:        return "Modified {$name}'s profile";
    }
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        else unset($string[$k]);
    }

    if (!$full) $string = array_slice($string, 0, 1);
    
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>