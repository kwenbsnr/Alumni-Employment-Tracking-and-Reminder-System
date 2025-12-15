<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
// Load TCPDF library
date_default_timezone_set('Asia/Manila');   // Change to your actual timezone

// REMOVED: require_once '../tcpdf/tcpdf.php'; // Moved to report generation only
// REMOVED: require_once '../api/notification/notif_service.php';
// REMOVED: require_once __DIR__ . '/../api/utils/common_functions.php';

$page_title = "Alumni Records";
$active_page = "alumni_management";
// Get search parameter
$search = $_GET['search'] ?? '';
// Handle report generation
if (isset($_POST['generate_report'])) {
    // LOAD TCPDF ONLY WHEN NEEDED
    require_once '../tcpdf/tcpdf.php';
    
    $selected_batches = $_POST['selected_batches'] ?? [];
    $report_type = $_POST['report_type'] ?? 'summary';
    $selected_statuses = $_POST['selected_statuses'] ?? [];
    if (!empty($selected_batches)) {
        generateAlumniReport($selected_batches, $report_type, $selected_statuses, $conn);
        // Exit after generating report to prevent further execution
        exit();
    } else {
        $_SESSION['error_message'] = "Please select at least one batch to generate a report.";
        // Redirect to clear POST data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
// ====================== SUBMISSIONS CONTROL LOGIC ======================
$conn->query("CREATE TABLE IF NOT EXISTS submission_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    is_open TINYINT(1) DEFAULT 0,
    manual_override TINYINT(1) DEFAULT 0,
    open_date DATETIME NULL,
    close_date DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$statusCheck = $conn->query("SELECT * FROM submission_status LIMIT 1");
if ($statusCheck->num_rows == 0) {
    $conn->query("INSERT INTO submission_status (is_open, manual_override) VALUES (1, 1)");
}
if (isset($_POST['update_submission_status'])) {
    $action = $_POST['submission_action'] ?? '';
    
    if ($action === 'open_now') {
        $conn->query("UPDATE submission_status SET
            is_open = 1,
            manual_override = 1,
            open_date = NULL,
            close_date = NULL");
        $_SESSION['success_message'] = "Alumni submissions are now OPEN indefinitely.";
        
        // REMOVED SLOW NOTIFICATION SENDING
        // require_once __DIR__ . '/notifications_simple.php';
        // $alumniResult = $conn->query("SELECT user_id FROM users WHERE role = 'alumni'");
        // $notification_count = 0;
        // while ($alumni = $alumniResult->fetch_assoc()) {
        //     $result = sendSubmissionStatusNotification($conn, $alumni['user_id'], 'open');
        //     if ($result) {
        //         $notification_count++;
        //     }
        // }
        // if ($notification_count > 0) {
        //     $_SESSION['success_message'] .= " (Notification sent)";
        // }
        
    } elseif ($action === 'close_now') {
        $conn->query("UPDATE submission_status SET
            is_open = 0,
            manual_override = 1,
            open_date = NULL,
            close_date = NULL");
        $_SESSION['success_message'] = "Alumni submissions are now CLOSED indefinitely.";
        
        // REMOVED SLOW NOTIFICATION SENDING
        // require_once __DIR__ . '/notifications_simple.php';
        // $alumniResult = $conn->query("SELECT user_id FROM users WHERE role = 'alumni'");
        // $notification_count = 0;
        // while ($alumni = $alumniResult->fetch_assoc()) {
        //     $result = sendSubmissionStatusNotification($conn, $alumni['user_id'], 'closed');
        //     if ($result) {
        //         $notification_count++;
        //     }
        // }
        // if ($notification_count > 0) {
        //     $_SESSION['success_message'] .= " (Notification sent)";
        // }
        
       } elseif ($action === 'schedule') {
    $open_date_input = $_POST['open_date'] ?? null;
    $close_date_input = $_POST['close_date'] ?? null;

    if ($open_date_input && $close_date_input && strtotime($open_date_input) < strtotime($close_date_input)) {
        $open_date = date('Y-m-d H:i:s', strtotime($open_date_input));
        $close_date = date('Y-m-d H:i:s', strtotime($close_date_input));

        // Format for notification and toast
        $open_formatted = date('F j, Y \a\t g:i A', strtotime($open_date));
        $close_formatted = date('F j, Y \a\t g:i A', strtotime($close_date));

        $conn->query("UPDATE submission_status SET
            open_date = '$open_date',
            close_date = '$close_date',
            manual_override = 0,
            is_open = 0");

        $_SESSION['success_message'] = "Submissions scheduled — Opens: $open_formatted | Closes: $close_formatted";

        // REMOVED SLOW NOTIFICATION SENDING
        // require_once __DIR__ . '/notifications_simple.php';
        // $alumniResult = $conn->query("SELECT user_id FROM users WHERE role = 'alumni'");
        // $notification_count = 0;
        // $title = "Profile Submission Schedule Updated";
        // $message = "Alumni profile submissions will be open from <strong>$open_formatted</strong> until <strong>$close_formatted</strong>. Please update your profile during this period.";
        // while ($alumni = $alumniResult->fetch_assoc()) {
        //     $result = sendSubmissionStatusNotification($conn, $alumni['user_id'], 'scheduled', [
        //         'open_date' => $open_formatted,
        //         'close_date' => $close_formatted
        //     ]);
        //     if ($result) {
        //         $notification_count++;
        //     }
        // }
        // if ($notification_count > 0) {
        //     $_SESSION['success_message'] .= " (Scheduled notification sent to $notification_count alumni)";
        // }

    } else {
        $_SESSION['error_message'] = "Invalid schedule: Closing date & time must be after opening date & time.";
    }
}
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
$statusRow = $conn->query("SELECT * FROM submission_status LIMIT 1")->fetch_assoc();
$is_open = (bool)$statusRow['is_open'];
$manual_override = (bool)$statusRow['manual_override'];
$open_date = $statusRow['open_date'];
$close_date = $statusRow['close_date'];

// REMOVED AUTOMATIC STATUS CHECKING - DO THIS ON DEMAND OR WITH CRON
// if (!$manual_override && $open_date && $close_date) {
//     $now = new DateTime();
//     $open_dt = new DateTime($open_date);
//     $close_dt = new DateTime($close_date);
//     $now->setTimezone(new DateTimeZone('Asia/Manila'));
//     $open_dt->setTimezone(new DateTimeZone('Asia/Manila'));
//     $close_dt->setTimezone(new DateTimeZone('Asia/Manila'));
// 
//     $new_status = ($now >= $open_dt && $now <= $close_dt) ? 1 : 0;
// 
//     if ($new_status != $is_open) {
//         $conn->query("UPDATE submission_status SET is_open = " . (int)$new_status);
// 
//         // REMOVED SLOW NOTIFICATION SENDING
//         // require_once __DIR__ . '/notifications_simple.php';
//         // $action = $new_status ? 'open' : 'closed';
//         // $alumniResult = $conn->query("SELECT user_id FROM users WHERE role = 'alumni'");
//         // while ($alumni = $alumniResult->fetch_assoc()) {
//         //     sendSubmissionStatusNotification($conn, $alumni['user_id'], $action);
//         // }
//     }
//     $is_open = (bool)$new_status;
// }


$status_text = $is_open ? "OPEN" : "CLOSED";
$status_color = $is_open ? "emerald" : "red";
$status_icon = $is_open ? "fa-unlock" : "fa-lock";
$schedule_tooltip = "";

if (!$manual_override && $open_date && $close_date) {
    $from = date('M j, Y g:i A', strtotime($open_date));
    $to = date('M j, Y g:i A', strtotime($close_date));
    $schedule_tooltip = " title='Scheduled: $from – $to' ";
}
// Fetch batches - count all alumni users
$batchQuery = "SELECT u.batch_year, COUNT(*) as total_count
               FROM users u
               WHERE u.role = 'alumni'
               AND u.batch_year IS NOT NULL
               GROUP BY u.batch_year
               ORDER BY u.batch_year DESC
               LIMIT 50"; // ADDED LIMIT FOR SPEED
$batchResult = $conn->query($batchQuery);
$all_batches = [];
if ($batchResult && $batchResult !== false) {
    while ($row = $batchResult->fetch_assoc()) {
        $all_batches[] = $row;
    }
    // Reset pointer only if we have results
    if ($batchResult->num_rows > 0) {
        $batchResult->data_seek(0);
    }
} else {
    // Handle query error
    error_log("Batch query failed: " . $conn->error);
    $batchResult = null; // Ensure it's not undefined
}
ob_start();
?>
<!-- ADD THIS: Prevents layout shift when toast appears -->
<div class="space-y-6 pb-32">
    <div class="min-h-screen">
<?php
$show_toast = false;
$toast_message = '';
$toast_type = 'success'; // success = green, closed/error = red
if (isset($_SESSION['success_message'])) {
    $msg = $_SESSION['success_message'];
    if (str_contains($msg, 'OPEN') || str_contains($msg, 'schedule')) {
        $show_toast = true;
        $toast_message = $msg;
        $toast_type = 'success';
    } elseif (str_contains($msg, 'CLOSED')) {
        $show_toast = true;
        $toast_message = $msg;
        $toast_type = 'closed';
    }
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $show_toast = true;
    $toast_message = $_SESSION['error_message'];
    $toast_type = 'error';
    unset($_SESSION['error_message']);
}
?>
<?php if ($show_toast): ?>
<div id="status-toast"
     class="fixed bottom-6 right-6 z-50 max-w-md w-full p-5 rounded-xl shadow-2xl text-white font-semibold flex items-center gap-4 animate-slide-up
     <?= $toast_type === 'success' ? 'bg-emerald-600' : 'bg-red-600' ?>">
    <i class="fas <?= $toast_type === 'success' ? 'fa-unlock' : 'fa-lock' ?> text-2xl"></i>
    <div>
        <div class="text-lg font-bold">Submission Status</div>
        <div class="text-sm opacity-90"><?= htmlspecialchars($toast_message) ?></div>
    </div>
</div>
<script>
    setTimeout(() => {
        const toast = document.getElementById('status-toast');
        if (toast) {
            toast.style.transition = 'all 0.4s ease-out';
            toast.style.transform = 'translateY(150%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }
    }, 3000);
</script>
<?php endif; ?>
<!-- Enhanced Search Bar with Action Buttons (Updated Layout) -->
<div class="bg-gradient-to-br from-blue-50 to-white p-4 rounded-xl shadow-lg border-2 border-blue-200">
    <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
       
        <!-- Search Form (Left side - takes most space) -->
        <div class="flex-1 max-w-2xl">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           class="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                           placeholder="Search alumni by name, email, or batch...">
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
        <!-- Right-side Action Buttons (Submissions + Generate Report) -->
        <div class="flex flex-col sm:flex-row gap-3 lg:ml-auto">
            <!-- Submissions Control Button -->
            <button id="toggleSubmissionModal"
                    class="bg-<?= $status_color ?>-600 hover:bg-<?= $status_color ?>-700 text-white px-5 py-2 rounded-lg font-medium flex items-center gap-2 shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 whitespace-nowrap group relative"<?= $schedule_tooltip ?>>
                <i class="fas <?= $status_icon ?>"></i>
                <span>Submissions</span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-white text-<?= $status_color ?>-600 ml-2">
                    <?= $status_text ?>
                </span>
                <div class="absolute -top-2 -right-2 w-3 h-3 bg-<?= $status_color ?>-400 rounded-full animate-pulse"></div>
            </button>
            <!-- Report Generator Button -->
            <button id="toggleReportForm"
                    class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-5 py-2 rounded-lg flex items-center gap-2 whitespace-nowrap transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105 group">
                <i class="fas fa-file-export group-hover:rotate-12 transition-transform duration-200"></i>
                <span>Generate Report</span>
            </button>
        </div>
    </div>
    <!-- Search results info (if any) -->
<?php
// Fix for the search count - line ~140 in your original code
if (!empty($search)): ?>
    <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-800 flex items-center gap-2">
        <i class="fas fa-info-circle"></i>
        <span>Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
        <span class="ml-auto text-blue-600 font-medium">
            <?php
            // FIXED: Count all alumni users (not just those with profiles)
            $searchStmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM users u
                WHERE u.role = 'alumni'
                AND (
                    CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
                    OR u.email LIKE ? 
                    OR u.batch_year LIKE ?
                )
                LIMIT 1
            ");
            $term = "%$search%";
            $searchStmt->bind_param('sss', $term, $term, $term);
            $searchStmt->execute();
            $searchResult = $searchStmt->get_result();
            $searchCount = $searchResult->fetch_assoc()['total'];
            $searchStmt->close();
            
            echo $searchCount . " result(s) found";
            ?>
        </span>
    </div>
<?php endif; ?>

<!-- Inline Report Form -->
<div id="reportFormContainer" class="hidden bg-gradient-to-br from-green-50 to-white p-6 rounded-xl shadow-lg border-2 border-green-200">
    <h2 class="text-2xl font-bold text-gray-800 mb-5 flex items-center gap-3">
        <i class="fas fa-file-export text-green-600"></i> Customize Alumni Report
    </h2>
    <form method="POST" action="" class="space-y-6" id="reportForm" target="_blank">
        <!-- Your existing form content remains the same -->
                 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Batch selection section -->
            <div class="bg-white p-5 rounded-xl border-2 border-gray-200">
                <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                    <i class="fas fa-layer-group text-blue-600"></i> Select Batches
                </h3>
                <div class="max-h-64 overflow-y-auto space-y-2">
                    <?php foreach ($all_batches as $batch): ?>
                        <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="selected_batches[]" value="<?= $batch['batch_year'] ?>" checked class="h-4 w-4 text-green-600 rounded">
                            <span class="flex-1">Batch <?= $batch['batch_year'] ?></span>
                            <span class="text-gray-500 text-sm">(<?= $batch['total_count'] ?> records)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="button" id="selectAll" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200">Select All</button>
                    <button type="button" id="deselectAll" class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200">Deselect All</button>
                </div>
            </div>
           
            <!-- Report options section -->
            <div class="bg-white p-5 rounded-xl border-2 border-gray-200 space-y-5">
                <div>
                    <label class="block font-medium mb-2">Report Type</label>
                    <select name="report_type" class="w-full border-2 border-gray-300 rounded-lg px-4 py-2">
                        <option value="summary">Summary Report</option>
                        <option value="detailed">Detailed Alumni List</option>
                    </select>
                </div>
                <!-- New: Employment Status Filter -->
                <div>
                    <label class="block font-medium mb-2">Filter by Employment Status (Optional)</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="selected_statuses[]" value="employed" class="h-4 w-4 text-blue-600 rounded">
                            <span>Employed</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="selected_statuses[]" value="self-employed" class="h-4 w-4 text-blue-600 rounded">
                            <span>Self-Employed</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="selected_statuses[]" value="unemployed" class="h-4 w-4 text-blue-600 rounded">
                            <span>Unemployed</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="selected_statuses[]" value="student" class="h-4 w-4 text-blue-600 rounded">
                            <span>Student</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="selected_statuses[]" value="employed & student" class="h-4 w-4 text-blue-600 rounded">
                            <span>Employed & Student</span>
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">Leave unchecked to include all statuses.</p>
                </div>
                <div>
                    <label class="block font-medium mb-2">Export Format</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="format" value="pdf" checked class="text-blue-600">
                            <span class="font-medium">PDF Document</span>
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">Reports will open in a new tab as PDF files</p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-4 pt-4 border-t">
            <button type="button" id="cancelReport" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">Cancel</button>
            <button type="submit" name="generate_report" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                <i class="fas fa-file-pdf"></i> Generate PDF Report
            </button>
        </div>
    </form>
</div>
    <!-- Batch Cards Grid (updated to hide "All Alumni" during search) -->
    <div class="space-y-4">
        <div class="flex items-center gap-3 px-2 mt-6">
            <i class="fas fa-folder-open text-2xl text-amber-600"></i>
            <h2 class="text-xl font-bold text-gray-800">Alumni Records </h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            // Only show "All Alumni" card when NOT searching
            if (empty($search)):
                $totalQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'alumni'";
                $totalAll = $conn->query($totalQuery)->fetch_assoc()['total'];
            ?>
                <a href="all_alumni.php" class="bg-gradient-to-br from-purple-50 to-white p-6 rounded-xl shadow-lg border-2 border-purple-300 hover:shadow-xl hover:border-purple-500 transform hover:scale-105 transition-all duration-300 group text-center">
                    <i class="fas fa-users text-4xl text-purple-600 mb-4"></i>
                    <p class="text-xs uppercase tracking-wider text-gray-500">All Batches Combined</p>
                    <p class="text-2xl font-bold text-gray-800">All Alumni</p>
                    <div class="mt-4 bg-white rounded-xl p-4 border border-purple-100">
                        <p class="text-3xl font-bold text-purple-600"><?= $totalAll ?></p>
                        <p class="text-xs uppercase text-gray-600">Total Records</p>
                    </div>
                    <div class="mt-4 bg-purple-700 text-white py-2 px-4 rounded-lg text-sm font-medium group-hover:bg-purple-600 transition">View All Records</div>
                </a>
            <?php endif; ?>
          <?php
    $displayResult = $batchResult;
    if (!empty($search)) {
        // FIXED: Find batches that have alumni users (regardless of profile data)
        $stmt = $conn->prepare("SELECT DISTINCT u.batch_year
                                FROM users u
                                WHERE u.role = 'alumni'
                                AND u.batch_year IS NOT NULL
                                AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
                                     OR u.email LIKE ? 
                                     OR u.batch_year LIKE ?)
                                LIMIT 50"); // ADDED LIMIT
        $term = "%$search%";
        $stmt->bind_param('sss', $term, $term, $term);
        $stmt->execute();
        $displayResult = $stmt->get_result();
    }
    if ($displayResult && $displayResult->num_rows > 0):
        while ($batch = $displayResult->fetch_assoc()):
            $year = $batch['batch_year'];
            // FIXED: Count all alumni users in this batch (not just those with profiles)
            $countStmt = $conn->prepare("SELECT COUNT(*) as total_count 
                                         FROM users 
                                         WHERE role = 'alumni' 
                                         AND batch_year = ?");
            $countStmt->bind_param('i', $year);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $count = $countRow['total_count'];
            $countStmt->close();
?>
                    <a href="batch_alumni.php?batch=<?= $year ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="bg-gradient-to-br from-amber-50 to-white p-6 rounded-xl shadow-lg border-2 border-amber-200 hover:shadow-xl hover:border-amber-400 transform hover:scale-105 transition-all duration-300 group text-center">
                        <i class="fas fa-folder-open text-4xl text-amber-600 mb-4"></i>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Graduation Batch</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $year ?></p>
                        <div class="mt-4 bg-white rounded-xl p-4 border border-amber-100">
                            <p class="text-3xl font-bold text-amber-600"><?= $count ?></p>
                            <p class="text-xs uppercase text-gray-600">Alumni Records</p>
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
<!-- Submission Modal -->
<div id="submissionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8 relative">
        <button id="closeModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl">
            <i class="fas fa-times"></i>
        </button>
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
            <i class="fas fa-cog text-<?= $status_color ?>-600"></i> Manage Submission Period
        </h2>
        <form method="POST" class="space-y-6" onsubmit="return validateSubmissionDates()">
            <div class="space-y-4">
                <!-- Open Now -->
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="open_now" class="text-emerald-600" <?= $is_open && $manual_override ? 'checked' : '' ?>>
                        <span class="font-medium">Open Submissions Now</span>
                    </label>
                </div>
                <!-- Close Now -->
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="close_now" class="text-red-600" <?= !$is_open && $manual_override ? 'checked' : '' ?>>
                        <span class="font-medium">Close Submissions Now</span>
                    </label>
                </div>
                <!-- Schedule -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="schedule" class="text-blue-600" id="scheduleRadio" <?= !$manual_override ? 'checked' : '' ?>>
                        <span class="font-medium">Schedule Open/Close Period</span>
                    </label>
                    <div class="mt-4 grid grid-cols-1 gap-4 ml-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Open Date & Time</label>
                            <input type="datetime-local" name="open_date" id="open_date" class="mt-1 w-full border-gray-300 rounded-md" value="<?= $open_date ? str_replace(' ', 'T', $open_date) : '' ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Close Date & Time</label>
                            <input type="datetime-local" name="close_date" id="close_date" class="mt-1 w-full border-gray-300 rounded-md" value="<?= $close_date ? str_replace(' ', 'T', $close_date) : '' ?>">
                        </div>
                    </div>
                </div>
            </div>
            <!-- Inline Error Message (Inside Modal) -->
            <div id="submissionError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4" role="alert">
                <span id="submissionErrorText"></span>
            </div>
            <!-- Buttons -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" id="cancelModal" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" name="update_submission_status" class="px-6 py-2 bg-<?= $status_color ?>-600 text-white rounded-lg hover:bg-<?= $status_color ?>-700 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Validation Script -->
<script>
function validateSubmissionDates() {
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    errorBox.classList.add('hidden');
    errorText.textContent = '';
    const scheduleRadio = document.getElementById('scheduleRadio');
    if (!scheduleRadio.checked) return true;
    const openDateInput = document.getElementById('open_date');
    const closeDateInput = document.getElementById('close_date');
    const openDate = new Date(openDateInput.value);
    const closeDate = new Date(closeDateInput.value);
    const now = new Date();
    now.setSeconds(0, 0);
    if (!openDateInput.value || !closeDateInput.value) {
        showError('Both opening and closing dates are required.');
        return false;
    }
    if (closeDate <= openDate) {
        showError('Closing date & time must come after the opening date & time.');
        return false;
    }
    // Improved message – no longer says "cannot be in the past" unless truly invalid
    if (openDate < now) {
        const formatted = openDate.toLocaleString();
        showError(`Opening date is in the past (${formatted}). Submissions would open immediately if you save this schedule.`);
        // We still allow saving – just inform the admin
    }
    return true;
}
function showError(message) {
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    errorText.textContent = message;
    errorBox.classList.remove('hidden');
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleReport = document.getElementById('toggleReportForm');
    const reportContainer = document.getElementById('reportFormContainer');
    const cancelReport = document.getElementById('cancelReport');
    const selectAll = document.getElementById('selectAll');
    const deselectAll = document.getElementById('deselectAll');
    const modal = document.getElementById('submissionModal');
    const openModal = document.getElementById('toggleSubmissionModal');
    const closeModal = document.getElementById('closeModal');
    const cancelModal = document.getElementById('cancelModal');
    toggleReport.addEventListener('click', () => reportContainer.classList.toggle('hidden'));
    cancelReport.addEventListener('click', () => reportContainer.classList.add('hidden'));
    selectAll.addEventListener('click', () => document.querySelectorAll('input[name="selected_batches[]"]').forEach(cb => cb.checked = true));
    deselectAll.addEventListener('click', () => document.querySelectorAll('input[name="selected_batches[]"]').forEach(cb => cb.checked = false));
    openModal.addEventListener('click', () => modal.classList.remove('hidden'));
    closeModal.addEventListener('click', () => modal.classList.add('hidden'));
    cancelModal.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
});
</script>
<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>
<?php
function generateAlumniReport($selected_batches, $report_type, $selected_statuses, $conn) {
    if (ob_get_length()) ob_clean();

    $selected_batches = array_map('intval', $selected_batches);
    $count_batches = count($selected_batches);

    if ($count_batches === 0) {
        $_SESSION['error_message'] = "Please select at least one batch.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Normalize selected statuses
    $selected_statuses = array_map('strtolower', array_map('trim', $selected_statuses));

    $status_placeholders = '';
    $status_types = '';
    $status_params = [];

    if (!empty($selected_statuses)) {
        $normalized_statuses = [];
        foreach ($selected_statuses as $status) {
            switch ($status) {
                case 'employed':
                case 'self-employed':
                case 'unemployed':
                case 'student':
                    $normalized_statuses[] = $status;
                    break;
                case 'employed & student':
                case 'employed and student':
                    $normalized_statuses[] = 'employed & student';
                    break;
            }
        }

        if (!empty($normalized_statuses)) {
            $status_placeholders = ' AND (ap.employment_status IS NOT NULL AND LOWER(TRIM(ap.employment_status)) IN (' .
                implode(',', array_fill(0, count($normalized_statuses), '?')) . '))';
            $status_types = str_repeat('s', count($normalized_statuses));
            $status_params = $status_params = $normalized_statuses;
        }
    }

    $placeholders = implode(',', array_fill(0, $count_batches, '?'));
    $types = str_repeat('i', $count_batches) . $status_types;

    $queries = [
        'summary' => "SELECT
            u.batch_year AS batch_year,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'employed' THEN 1 ELSE 0 END) AS employed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' THEN 1 ELSE 0 END) AS self_employed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'unemployed' THEN 1 ELSE 0 END) AS unemployed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'student' THEN 1 ELSE 0 END) AS student,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed & student','employed and student') THEN 1 ELSE 0 END) AS employed_student,
            SUM(CASE WHEN ap.employment_status IS NULL OR ap.employment_status = '' OR LOWER(TRIM(ap.employment_status)) NOT IN ('employed','self-employed','unemployed','student','employed & student','employed and student') THEN 1 ELSE 0 END) AS not_updated,
            COUNT(DISTINCT u.user_id) AS total_alumni,
            CASE WHEN SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed','self-employed','unemployed','student','employed & student','employed and student') THEN 1 ELSE 0 END) > 0
                THEN ROUND(100.0 * (
                    SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed','self-employed','employed & student','employed and student') THEN 1 ELSE 0 END)
                ) / SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed','self-employed','unemployed','student','employed & student','employed and student') THEN 1 ELSE 0 END), 2)
                ELSE 0
            END AS employment_rate
        FROM users u
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
        WHERE u.role = 'alumni'
        AND u.batch_year IN ($placeholders)$status_placeholders
        GROUP BY u.batch_year
        ORDER BY u.batch_year DESC",

        'detailed' => "SELECT
            CONCAT(
                u.first_name,
                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                ' ',
                u.last_name,
                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
            ) as name,
            u.email,
            u.batch_year,
            CASE
                WHEN ap.employment_status IS NULL OR ap.employment_status = '' THEN 'Not Updated'
                ELSE ap.employment_status
            END as employment_status,
            CASE
                WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' THEN 'Self-Employed'
                WHEN jt.title IS NOT NULL THEN jt.title
                ELSE '-'
            END as current_job,
            CASE
                WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' THEN COALESCE(ei.business_type, '-')
                WHEN ei.company_name IS NOT NULL THEN ei.company_name
                ELSE '-'
            END as current_employer
        FROM users u
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
        LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
        LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
        WHERE u.role = 'alumni'
        AND u.batch_year IN ($placeholders)$status_placeholders
        ORDER BY u.batch_year DESC, name"
    ];

    $stmt = $conn->prepare($queries[$report_type]);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error_message'] = "Database error while preparing report.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $all_params = array_merge($selected_batches, $status_params);

    if (!empty($all_params)) {
        $bind_names = [$types];
        foreach ($all_params as &$param) {
            $bind_names[] = &$param;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // ALWAYS generate PDF — even if no data
    $has_real_data = !empty($data);

    if (!$has_real_data) {
        if ($report_type === 'summary') {
            $data = [[
                'batch_year'       => '—',
                'employed'         => 0,
                'self_employed'    => 0,
                'unemployed'       => 0,
                'student'          => 0,
                'employed_student' => 0,
                'not_updated'      => 0,
                'total_alumni'     => 0,
                'employment_rate'  => '0%'
            ]];
        } else {
            $data = [[
                'batch_year'        => '—',
                'name'              => 'No alumni found matching the selected filters',
                'email'             => '',
                'employment_status' => '',
                'current_job'       => '',
                'current_employer'  => ''
            ]];
        }
    }

    // ==================================================================
    // PDF GENERATION
    // ==================================================================
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Alumni Tracking System');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Alumni Report - ' . ucfirst($report_type));
    $pdf->SetSubject('Alumni Records Report');

    $status_filter_text = !empty($selected_statuses)
        ? ' (Filtered by: ' . implode(', ', array_map('ucfirst', $selected_statuses)) . ')'
        : '';
    $pdf->SetHeaderData('', 0, 'ALUMNI MANAGEMENT SYSTEM', 'Alumni Report - ' . date('F j, Y') . $status_filter_text);

    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($report_type) . ' ALUMNI REPORT' . $status_filter_text, 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Selected Batches: ' . implode(', ', $selected_batches), 0, 1);
    $pdf->Cell(0, 6, 'Total Records Found: ' . ($has_real_data ? number_format(count($data)) : '0'), 0, 1);
    $pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y \a\t g:i A'), 0, 1);
    $pdf->Ln(8);

    $table_config = [
        'summary' => [
            'headers' => ['Batch', 'Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed & Student', 'Not Updated', 'Total', 'Employment Rate'],
            'widths'  => [25, 25, 30, 25, 25, 35, 30, 20, 35],
            'data_keys' => ['batch_year', 'employed', 'self_employed', 'unemployed', 'student', 'employed_student', 'not_updated', 'total_alumni', 'employment_rate'],
            'align'   => ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'],
        ],
        'detailed' => [
            'headers' => ['Batch', 'Name', 'Email', 'Status', 'Job', 'Employer'],
            'widths'  => [20, 50, 60, 35, 45, 60],
            'data_keys' => ['batch_year', 'name', 'email', 'employment_status', 'current_job', 'current_employer'],
            'align'   => ['C', 'L', 'L', 'L', 'L', 'L'],
        ]
    ];

    $config = $table_config[$report_type];

    // Header
    $pdf->SetFillColor(230, 240, 255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 8);

    foreach ($config['headers'] as $i => $header) {
        $pdf->Cell($config['widths'][$i], 6, $header, 1, 0, $config['align'][$i], 1);
    }
    $pdf->Ln();

    // Body
    $pdf->SetFont('helvetica', '', 8);
    $is_summary = $report_type === 'summary';
    $totals = array_fill_keys($config['data_keys'], 0);

    foreach ($data as $row) {
        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
            // Redraw header
            $pdf->SetFillColor(230, 240, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            foreach ($config['headers'] as $i => $header) {
                $pdf->Cell($config['widths'][$i], 6, $header, 1, 0, $config['align'][$i], 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }

        foreach ($config['data_keys'] as $i => $key) {
            $value = $row[$key] ?? '';

            if ($is_summary) {
                if (in_array($key, ['employed','self_employed','unemployed','student','employed_student','not_updated','total_alumni'])) {
                    $totals[$key] += (int)$value;
                }
                if ($key === 'employment_rate') {
                    $value = is_numeric($value) ? $value . '%' : '0%';
                }
            }

            if ($value === null || $value === '') $value = '-';

            // Red text for "Not Updated"
            if (($is_summary && $key === 'not_updated' && $value > 0) ||
                (!$is_summary && $value === 'Not Updated')) {
                $pdf->SetTextColor(200, 0, 0);
            } else {
                $pdf->SetTextColor(0);
            }

            $pdf->Cell($config['widths'][$i], 5, $value, 1, 0, $config['align'][$i], 1);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);
    }

    // Show clear message if no real data
    if (!$has_real_data) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(200, 0, 0);
        $pdf->Cell(0, 10, 'NO RECORDS FOUND FOR THE SELECTED BATCHES AND FILTERS', 0, 1, 'C');
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'Try adjusting the batch selection or employment status filters.', 0, 1, 'C');
    }

    // Grand Total Row (only for summary with real data)
    if ($is_summary && $has_real_data && $totals['total_alumni'] > 0) {
        $pdf->SetFillColor(200, 215, 230);
        $pdf->SetFont('helvetica', 'B', 8);

        $total_with_data = $totals['employed'] + $totals['self_employed'] + $totals['unemployed'] +
                           $totals['student'] + $totals['employed_student'];
        $total_employed = $totals['employed'] + $totals['self_employed'] + $totals['employed_student'];
        $total_rate = $total_with_data > 0 ? round(100.0 * $total_employed / $total_with_data, 2) : 0;

        $summary_totals = [
            'batch_year'       => 'GRAND TOTAL',
            'employed'         => $totals['employed'],
            'self_employed'    => $totals['self_employed'],
            'unemployed'       => $totals['unemployed'],
            'student'          => $totals['student'],
            'employed_student' => $totals['employed_student'],
            'not_updated'      => $totals['not_updated'],
            'total_alumni'     => $totals['total_alumni'],
            'employment_rate'  => $total_rate . '%',
        ];

        foreach ($config['data_keys'] as $i => $key) {
            $value = $summary_totals[$key];
            if ($key === 'not_updated' && $value > 0) {
                $pdf->SetTextColor(200, 0, 0);
            } else {
                $pdf->SetTextColor(0);
            }
            $pdf->Cell($config['widths'][$i], 5, $value, 1, 0, $config['align'][$i], 1);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);
    }

    // Footer note
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Ln(5);
    $pdf->Cell(0, 6, 'Report generated with filters: ' .
        (!empty($selected_statuses) ? 'Status: ' . implode(', ', array_map('ucfirst', $selected_statuses)) . ' | ' : '') .
        'Batches: ' . implode(', ', $selected_batches), 0, 1);

    $pdf_filename = strtoupper($report_type) . '_ALUMNI_REPORT_' . date('Ymd_His') . '.pdf';
    $pdf->Output($pdf_filename, 'I');
    exit;
}
?>