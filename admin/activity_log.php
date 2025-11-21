<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Activity Log";
$active_page = "activity_log";

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

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM update_log ul $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch activity data
// Fetch activity data
$activityQuery = "
    SELECT 
        ul.log_id,
        ul.updated_by,
        ul.updated_id,
        ul.update_type,
        ul.updated_at,
        u.name as admin_name,
        ap.first_name,
        ap.last_name,
        u_updated.name as user_name_fallback
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN alumni_profile ap ON ul.updated_id = ap.user_id
    LEFT JOIN users u_updated ON ul.updated_id = u_updated.user_id
    $whereClause
    ORDER BY ul.updated_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$activityStmt = $conn->prepare($activityQuery);
if ($params) {
    $activityStmt->bind_param($types, ...$params);
}
$activityStmt->execute();
$activityResult = $activityStmt->get_result();

ob_start();
// ... rest of the code ...
?><div class="space-y-8">

    <!-- HEADER -->

        <a href="admin_dashboard.php"
            class="inline-flex items-center gap-3 px-6 py-2.5 bg-green-600 text-white rounded-xl shadow-sm hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <!-- FILTERS -->
    <div class="rounded-2xl bg-white p-5 shadow-md shadow-black/5 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filters</h3>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                <select name="type"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-purple-500 focus:bg-white transition">
                    <option value="">All</option>
                    <option value="update" <?= $filter_type === 'update' ? 'selected' : '' ?>>Update</option>
                    <option value="approve" <?= $filter_type === 'approve' ? 'selected' : '' ?>>Approve</option>
                    <option value="reject" <?= $filter_type === 'reject' ? 'selected' : '' ?>>Reject</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="date" value="<?= $filter_date ?>"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-purple-500 focus:bg-white transition">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-purple-500 text-white rounded-xl shadow hover:brightness-110 transition">
                    <i class="fas fa-filter mr-1"></i> Apply
                </button>

                <a href="activity_log.php"
                    class="px-4 py-2.5 bg-gray-100 text-gray-800 rounded-xl hover:bg-gray-200 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- MAIN TABLE -->
    <div class="bg-white rounded-2xl shadow-md shadow-black/5 border border-gray-100 overflow-hidden">

        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">System Activities</h3>
            <p class="text-sm text-gray-500">Total <?= $totalRows ?> records found</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="sticky top-0 bg-gray-100 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-6 py-3">Action</th>
                        <th class="px-6 py-3">Details</th>
                        <th class="px-6 py-3">Admin</th>
                        <th class="px-6 py-3">Date & Time</th>
                    </tr>
                </thead>

                <tbody class="text-sm text-gray-700">
                    <?php if ($activityResult->num_rows > 0): ?>
                        <?php while ($activity = $activityResult->fetch_assoc()): ?>
                            <tr class="odd:bg-white even:bg-gray-50 hover:bg-purple-50/40 transition-all duration-200">
                                
                                <!-- Action -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <!-- Icon Circle -->
                                        <div class="p-2.5 rounded-full <?= getActivityColor($activity['update_type']) ?> shadow-sm">
                                            <i class="text-sm fas fa-<?= getActivityIcon($activity['update_type']) ?>"></i>
                                        </div>

                                        <div>
                                            <span class="px-2 py-1.5 rounded-lg text-xs font-medium <?= getActivityBadgeColor($activity['update_type']) ?>">
                                                <?= ucfirst($activity['update_type']) ?>
                                            </span>

                                            <p class="text-xs text-gray-500 mt-1">Alumni Profile</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Details -->
                                <td class="px-6 py-4"><?= getEnhancedActivityText($activity) ?></td>

                                <!-- Admin -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-medium"><?= htmlspecialchars($activity['admin_name']) ?></span>
                                </td>

                                <!-- Date -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span><?= date('M j, Y g:i A', strtotime($activity['updated_at'])) ?></span>
                                        <span class="text-xs text-gray-500"><?= time_elapsed_string($activity['updated_at']) ?></span>
                                    </div>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p class="text-lg font-medium">No activity records found</p>
                                <p class="text-sm text-gray-400">Try adjusting the filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-5 border-t border-gray-200 flex justify-between items-center">

                <p class="text-sm text-gray-600">
                    Showing <?= ($offset + 1) ?>–<?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?>
                </p>

                <div class="flex items-center gap-2">

                    <!-- Prev -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-100 transition">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                            class="px-3 py-1.5 rounded-lg text-sm 
                            <?= $i == $page ? 'bg-purple-600 text-white shadow' : 'bg-white border border-gray-300 hover:bg-gray-100' ?> transition">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-100 transition">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                </div>

            </div>
        <?php endif; ?>

    </div>

</div>


<?php
// Include the same helper functions from dashboard
function getActivityIcon($update_type) {
    switch ($update_type) {
        case 'approve': return 'check-circle';
        case 'reject': return 'times-circle';
        case 'update': return 'edit';
        default: return 'sync';
    }
}

function getActivityColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-500';
        case 'reject': return 'bg-red-100 text-red-500';
        case 'update': return 'bg-blue-100 text-blue-500';
        default: return 'bg-purple-100 text-purple-500';
    }
}

function getActivityBadgeColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-800';
        case 'reject': return 'bg-red-100 text-red-800';
        case 'update': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getEnhancedActivityText($activity) {
    $name = '';
    
    // 1. Try to get the name from alumni_profile (First Name + Last Name)
    if (!empty($activity['first_name']) && !empty($activity['last_name'])) {
        $name = htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']);
    } 
    // 2. Fallback to the name from the users table (single 'name' field)
    else if (!empty($activity['user_name_fallback'])) {
        $name = htmlspecialchars($activity['user_name_fallback']);
    }
    // 3. Default name if all else fails
    else {
        $name = "Alumni";
    }
    
    $actions = [
        'approve' => 'Approved',
        'reject' => 'Rejected', 
        'update' => 'Updated'
    ];
    
    $action = $actions[$activity['update_type']] ?? 'Modified';
    
    return "{$action} {$name}'s profile";
}
function time_elapsed_string($datetime, $full = false) {
    // Ensure we work with DateTime in the correct timezone
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    
    // If datetime came from MySQL without timezone, adjust it
    if ($ago->getTimezone()->getName() !== 'Asia/Manila') {
        $ago->setTimezone(new DateTimeZone('Asia/Manila'));
    }

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
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>