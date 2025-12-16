<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Load necessary files
// REMOVED: require_once '../api/notification/notif_service.php';
// REMOVED: require_once __DIR__ . '/../api/utils/common_functions.php';

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter
$search = $_GET['search'] ?? '';

// ====================== SUBMISSIONS CONTROL LOGIC (REMOVED) ======================
// The entire submission control block, form processing, and automatic status check are moved to submission_schedule.php.

// Fetch batches - count all alumni users (RETAINED)
$batchQuery = "SELECT u.batch_year, COUNT(*) as total_count 
               FROM users u 
               WHERE u.role = 'alumni' 
               AND u.batch_year IS NOT NULL 
               GROUP BY u.batch_year 
               ORDER BY u.batch_year DESC";
$batchResult = $conn->query($batchQuery);

$all_batches = [];
if ($batchResult && $batchResult !== false) {
    while ($row = $batchResult->fetch_assoc()) {
        $all_batches[] = $row;
    }
    if ($batchResult->num_rows > 0) {
        $batchResult->data_seek(0);
    }
} else {
    error_log("Batch query failed: " . $conn->error);
    $batchResult = null; 
}

// ====================== HTML OUTPUT START ======================
ob_start();
?>

<div class="space-y-6">

<?php 
// --- TOAST LOGIC (REMOVED - now handled by admin_format for generic toasts or submission_schedule.php for status toasts) ---
// All toast-related logic is removed from here.
?>

<div class="bg-gradient-to-br from-blue-50 to-white p-4 rounded-xl shadow-lg border-2 border-blue-200">
    <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
        
        <div class="flex-1 max-w-2xl">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           class="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                           placeholder="Search alumni by name or email">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg flex items-center gap-2 whitespace-nowrap transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="alumni_management.php" class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-lg flex items-center gap-2 whitespace-nowrap transition-all duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 lg:ml-auto">
            </div>
    </div>

    <?php if (!empty($search)): ?>
        <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-800 flex items-center gap-2">
            <i class="fas fa-info-circle"></i>
            <span>Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
            <span class="ml-auto text-blue-600 font-medium">
                <?php
                    // Calculate the number of batches that contain alumni matching the search term (RETAINED)
                    $stmt = $conn->prepare("SELECT COUNT(DISTINCT u.batch_year) AS batch_count
                                            FROM users u
                                            WHERE u.role = 'alumni' 
                                            AND u.batch_year IS NOT NULL 
                                            AND (CONCAT(
                                                u.first_name,
                                                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                                                ' ',
                                                u.last_name,
                                                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
                                            ) LIKE ? OR u.email LIKE ?)");
                    $term = "%$search%";
                    $stmt->bind_param('ss', $term, $term);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $batchCount = $result->fetch_assoc()['batch_count'] ?? 0;
                    echo $batchCount . " batch folder(s) found";
                ?>
            </span>
        </div>
    <?php endif; ?>
</div>

<div class="space-y-4">
        <div class="flex items-center gap-3 px-2">
            <i class="fas fa-folder-open text-2xl text-amber-600"></i>
            <h2 class="text-xl font-bold text-gray-800">Alumni Records </h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            $displayResult = $batchResult;
            if (!empty($search)) {
                $stmt = $conn->prepare("SELECT DISTINCT u.batch_year 
                                    FROM users u
                                    WHERE u.role = 'alumni' 
                                    AND u.batch_year IS NOT NULL 
                                    AND (CONCAT(
                                        u.first_name,
                                        IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                                        ' ',
                                        u.last_name,
                                        IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
                                    ) LIKE ? OR u.email LIKE ?)");
                $term = "%$search%";
                $stmt->bind_param('ss', $term, $term);
                $stmt->execute();
                $displayResult = $stmt->get_result();
            }

            if ($displayResult && $displayResult->num_rows > 0):
                while ($batch = $displayResult->fetch_assoc()):
                    $year = $batch['batch_year'];

                    $count = 0;
                    if (!empty($search)) {
                        $stmt_count = $conn->prepare("SELECT COUNT(*) AS count
                                                      FROM users u
                                                      WHERE u.role = 'alumni' 
                                                      AND u.batch_year = ?
                                                      AND (CONCAT(
                                                          u.first_name,
                                                          IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                                                          ' ',
                                                          u.last_name,
                                                          IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
                                                      ) LIKE ? OR u.email LIKE ?)");
                        $term = "%$search%";
                        $stmt_count->bind_param('iss', $year, $term, $term);
                        $stmt_count->execute();
                        $result_count = $stmt_count->get_result();
                        $count = $result_count->fetch_assoc()['count'] ?? 0;
                        $stmt_count->close();
                    } else {
                        $batch_index = array_search($year, array_column($all_batches, 'batch_year'));
                        $count = ($batch_index !== false) ? $all_batches[$batch_index]['total_count'] : 0;
                    }
            ?>
                    <a href="batch_alumni.php?batch=<?= $year ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="bg-gradient-to-br from-amber-50 to-white p-6 rounded-xl shadow-lg border-2 border-amber-200 hover:shadow-xl hover:border-amber-400 transform hover:scale-105 transition-all duration-300 group text-center">
                        <i class="fas fa-folder-open text-4xl text-amber-600 mb-4"></i>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Graduation Batch</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $year ?></p>
                        <div class="mt-4 bg-white rounded-xl p-4 border border-amber-100">
                            <p class="text-3xl font-bold text-amber-600"><?= $count ?></p>
                            <p class="text-xs uppercase text-gray-600"><?= !empty($search) ? 'Matching Records' : 'Alumni Records' ?></p>
                        </div>
                        <div class="mt-4 bg-gray-800 text-white py-2 px-4 rounded-lg text-sm font-medium group-hover:bg-amber-600 transition">View Records</div>
                    </a>
            <?php
                endwhile;
            else: ?>
                <div class="col-span-full text-center py-12 bg-amber-50 rounded-xl border-2 border-amber-200">
                    <i class="fas fa-folder-open text-6xl text-amber-400 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-700">No Alumni found.</h3>
                    <p class="text-gray-600 mt-2"><?= !empty($search) ? 'No batches match your search.' : 'There are no alumni records yet.' ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>