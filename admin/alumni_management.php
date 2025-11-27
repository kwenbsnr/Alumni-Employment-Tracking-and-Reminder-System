<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Load TCPDF library
require_once '../tcpdf/tcpdf.php';
require_once '../api/notification/notif_service.php';

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter
$search = $_GET['search'] ?? '';

// Handle report generation
if (isset($_POST['generate_report'])) {
    $selected_batches = $_POST['selected_batches'] ?? [];
    $report_type      = $_POST['report_type'] ?? 'summary';

    if (!empty($selected_batches)) {
        generateAlumniReport($selected_batches, $report_type, $conn);
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
       
        // ==================== NOTIFICATION API INTEGRATION ====================
        // Notify alumni who need to update (haven't updated in 6 months)
        $alumni_to_notify = $conn->query("
            SELECT u.user_id, u.name as alumni_name, u.email as alumni_email, 
                u.batch_year as graduation_year, ap.employment_status,
                ap.last_profile_update, ap.submission_status
            FROM users u 
            INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
            WHERE u.role = 'alumni' 
            AND ap.submission_status != 'Approved'
            AND (ap.last_profile_update IS NULL OR 
                ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH))
        ");

        $notification_count = 0;
        while ($alumni = $alumni_to_notify->fetch_assoc()) {
            // Send profile update reminder using unified service
            $result = send_profile_update_reminder(
                $alumni['alumni_email'],
                $alumni['alumni_name'],
                $alumni['graduation_year']
            );
            
            if ($result['success']) {
                $notification_count++;
            }
        }
        
        // Add notification count to success message
        if ($notification_count > 0) {
            $_SESSION['success_message'] .= " Sent profile update reminders to {$notification_count} alumni.";
        }
         } elseif ($action === 'schedule') {
        $open_date_input  = $_POST['open_date'] ?? null;
        $close_date_input = $_POST['close_date'] ?? null;

        if ($open_date_input && $close_date_input && strtotime($open_date_input) < strtotime($close_date_input)) {
            $open_date  = date('Y-m-d H:i:s', strtotime($open_date_input));
            $close_date = date('Y-m-d H:i:s', strtotime($close_date_input));

            // Format for display in toast
            $from = date('M j, Y \a\t g:i A', strtotime($open_date));
            $to   = date('M j, Y \a\t g:i A', strtotime($close_date));

            $conn->query("UPDATE submission_status SET 
                open_date = '$open_date', 
                close_date = '$close_date', 
                manual_override = 0,
                is_open = 0");  

            $_SESSION['success_message'] = "Submissions scheduled — Opens: $from | Closes: $to";
            
            // ==================== NOTIFICATION API INTEGRATION FOR SCHEDULED OPENING ====================
            // Check if this is a new opening schedule (not just updating existing schedule)
            $is_new_schedule = true; // You might want to add logic to detect if this is a new schedule
            
            if ($is_new_schedule) {
                // Notify alumni who need to update when submissions open on schedule
                $alumni_to_notify = $conn->query("
                    SELECT u.user_id, u.name as alumni_name, u.email as alumni_email, 
                           u.batch_year as graduation_year, ap.employment_status,
                           ap.last_profile_update, ap.submission_status
                    FROM users u 
                    INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
                    WHERE u.role = 'alumni' 
                    AND ap.submission_status != 'Approved'
                    AND (ap.last_profile_update IS NULL OR 
                         ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH))
                ");
                
                $notification_count = 0;
                while ($alumni = $alumni_to_notify->fetch_assoc()) {
                    // Include the notification functions
                    require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
                    
                    // Send profile update reminder
                    $result = sendProfileUpdateReminder(
                        $alumni['alumni_email'],
                        $alumni['alumni_name'],
                        $alumni['graduation_year'],
                        '/alumni/alumni_dashboard.php'
                    );
                    
                    if ($result['success']) {
                        $notification_count++;
                    }
                }
                
                // Add notification count to success message
                if ($notification_count > 0) {
                    $_SESSION['success_message'] .= " Sent scheduled update reminders to {$notification_count} alumni.";
                }
            }
            
        } else {
            $_SESSION['error_message'] = "Invalid schedule: Closing date & time must be after opening date & time.";
        }
    }
    
    // ADD THIS REDIRECTION AFTER PROCESSING THE FORM
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
$statusRow = $conn->query("SELECT * FROM submission_status LIMIT 1")->fetch_assoc();
$is_open = (bool)$statusRow['is_open'];
$manual_override = (bool)$statusRow['manual_override'];
$open_date = $statusRow['open_date'];
$close_date = $statusRow['close_date'];

if (!$manual_override && $open_date && $close_date) {
    $now = date('Y-m-d H:i:s');
    $new_status = ($now >= $open_date && $now <= $close_date) ? 1 : 0;
    if ($new_status != $is_open) {
        $conn->query("UPDATE submission_status SET is_open = $new_status");
        $is_open = $new_status;

                // ==================== AUTOMATIC NOTIFICATION WHEN SCHEDULE OPENS ====================
        if ($new_status == 1) {
            // Notify alumni when submissions automatically open per schedule
            $alumni_to_notify = $conn->query("
                SELECT u.user_id, u.name as alumni_name, u.email as alumni_email, 
                       u.batch_year as graduation_year, ap.employment_status,
                       ap.last_profile_update, ap.submission_status
                FROM users u 
                INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
                WHERE u.role = 'alumni' 
                AND ap.submission_status != 'Approved'
                AND (ap.last_profile_update IS NULL OR 
                     ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH))
            ");
            
            $notification_count = 0;
            while ($alumni = $alumni_to_notify->fetch_assoc()) {
                // Include the notification functions
                require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
                
                // Send profile update reminder
                $result = sendProfileUpdateReminder(
                    $alumni['alumni_email'],
                    $alumni['alumni_name'],
                    $alumni['graduation_year'],
                    'http://localhost/Alumni-Employment-Tracking-and-Reminder-System/alumni/alumni_dashboard.php'
                );
                
                if ($result['success']) {
                    $notification_count++;
                }
            }
            
            // Log the automatic notifications
            if ($notification_count > 0) {
                error_log("[" . date('Y-m-d H:i:s') . "] Automatically sent profile update reminders to {$notification_count} alumni when submissions opened on schedule.");
            }
        }
    }
}

$status_text = $is_open ? "OPEN" : "CLOSED";
$status_color = $is_open ? "emerald" : "red";
$status_icon = $is_open ? "fa-unlock" : "fa-lock";

$schedule_info = "";
if (!$manual_override && $open_date && $close_date) {
    $from = date('M j, Y g:i A', strtotime($open_date));
    $to = date('M j, Y g:i A', strtotime($close_date));
    $schedule_info = "<span class='text-xs block text-gray-600'>Scheduled: $from – $to</span>";
}

// Fetch batches - FIXED
$batchQuery = "SELECT u.batch_year, COUNT(*) as total_count 
               FROM users u 
               INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
               WHERE u.batch_year IS NOT NULL 
               GROUP BY u.batch_year 
               ORDER BY u.batch_year DESC";
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

<div class="space-y-6">

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
                <div class="flex-1 relative">
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
                    class="bg-<?= $status_color ?>-600 hover:bg-<?= $status_color ?>-700 text-white px-5 py-2 rounded-lg font-medium flex items-center gap-2 shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 whitespace-nowrap group relative">
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
    <?php if (!empty($search)): ?>
        <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-800 flex items-center gap-2">
            <i class="fas fa-info-circle"></i>
            <span>Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
            <span class="ml-auto text-blue-600 font-medium">
                <?php
                $safe_search = $conn->real_escape_string($search);
                $searchCount = $conn->query("SELECT COUNT(*) as count FROM alumni_profile ap
                    INNER JOIN users u ON ap.user_id = u.user_id
                    WHERE u.name LIKE '%$safe_search%' 
                       OR u.email LIKE '%$safe_search%'")->fetch_assoc()['count'];
                echo "{$searchCount} result(s) found";
                ?>
            </span>
        </div>
    <?php endif; ?>
</div>

    <!-- Inline Report Form -->
    <div id="reportFormContainer" class="hidden bg-gradient-to-br from-green-50 to-white p-6 rounded-xl shadow-lg border-2 border-green-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-5 flex items-center gap-3">
            <i class="fas fa-file-export text-green-600"></i> Customize Alumni Report
        </h2>
        <form method="POST" action="" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                <div class="bg-white p-5 rounded-xl border-2 border-gray-200 space-y-5">
                   <!-- Inside the Report Form (dropdown) -->
<div>
    <label class="block font-medium mb-2">Report Type</label>
    <select name="report_type" class="w-full border-2 border-gray-300 rounded-lg px-4 py-2">
        <option value="summary">Summary Report</option>
        <option value="detailed">Detailed Alumni List</option>
        <!-- Removed: Contact Information & Employment Status -->
    </select>
</div>
                    <div>
                        <label class="block font-medium mb-2">Export Format</label>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="format" value="pdf" checked class="text-blue-600">
                                <span class="font-medium">PDF Document</span>
                            </label>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Reports will be generated as PDF files with professional formatting</p>
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
        <div class="flex items-center gap-3 px-2">
            <i class="fas fa-folder-open text-2xl text-amber-600"></i>
            <h2 class="text-xl font-bold text-gray-800">Alumni Records </h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            // Only show "All Alumni" card when NOT searching
            if (empty($search)):
                $totalQuery = "SELECT COUNT(*) as total FROM alumni_profile";
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
                $stmt = $conn->prepare("SELECT DISTINCT u.batch_year 
                                       FROM alumni_profile ap
                                       INNER JOIN users u ON ap.user_id = u.user_id 
                                       WHERE u.batch_year IS NOT NULL 
                                       AND u.name LIKE ?");
                $term = "%$search%";
                $stmt->bind_param('s', $term);
                $stmt->execute();
                $displayResult = $stmt->get_result();
            }

            if ($displayResult && $displayResult->num_rows > 0):
                while ($batch = $displayResult->fetch_assoc()):
                    $year = $batch['batch_year'];
                    $batch_index = array_search($year, array_column($all_batches, 'batch_year'));
                    $count = ($batch_index !== false) ? $all_batches[$batch_index]['total_count'] : 0;
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
function generateAlumniReport($selected_batches, $report_type, $conn) {
    if (ob_get_length()) ob_clean();
    // Build placeholders for IN clause
    $placeholders = str_repeat('?,', count($selected_batches) - 1) . '?';
    $types = str_repeat('i', count($selected_batches));

    // Debug: Check what batches we're querying
    error_log("Selected batches: " . implode(', ', $selected_batches));
    
    // Test query to see actual data
    $test_query = "SELECT 
        u.batch_year,
        ap.employment_status,
        COUNT(*) as count
        FROM alumni_profile ap
        INNER JOIN users u ON ap.user_id = u.user_id
        WHERE u.batch_year IN ($placeholders)
        GROUP BY u.batch_year, ap.employment_status
        ORDER BY u.batch_year, ap.employment_status";
    
    $test_stmt = $conn->prepare($test_query);
    $test_stmt->bind_param($types, ...$selected_batches);
    $test_stmt->execute();
    $test_result = $test_stmt->get_result();
    
    error_log("Employment status distribution:");
    while ($row = $test_result->fetch_assoc()) {
        error_log("Batch {$row['batch_year']}: {$row['employment_status']} - {$row['count']} records");
    }

    // Queries for each report type
    // Queries for each report type
// Inside generateAlumniReport() function - replace the $queries array
$queries = [
    'summary' => "SELECT 
            u.batch_year,
            COUNT(*) as total_alumni,
            SUM(CASE WHEN ap.employment_status = 'Employed' THEN 1 ELSE 0 END) as employed,
            SUM(CASE WHEN ap.employment_status = 'Self-Employed' THEN 1 ELSE 0 END) as self_employed,
            SUM(CASE WHEN ap.employment_status = 'Unemployed' THEN 1 ELSE 0 END) as unemployed,
            SUM(CASE WHEN ap.employment_status = 'Student' THEN 1 ELSE 0 END) as student,
            SUM(CASE WHEN ap.employment_status = 'Employed & Student' THEN 1 ELSE 0 END) as student_employed,
            CASE 
                WHEN COUNT(*) > 0 THEN 
                    ROUND(100.0 * SUM(CASE 
                        WHEN ap.employment_status IN ('Employed', 'Self-Employed', 'Employed & Student') 
                        THEN 1 ELSE 0 
                    END) / COUNT(*), 2)
                ELSE 0 
            END as employment_rate
          FROM alumni_profile ap
          INNER JOIN users u ON ap.user_id = u.user_id
          WHERE u.batch_year IN ($placeholders)
          GROUP BY u.batch_year
          ORDER BY u.batch_year DESC",

    'detailed' => "SELECT u.name, u.email, u.batch_year, 
                          COALESCE(ap.employment_status, 'Not Updated') as employment_status,
                          CASE 
                              WHEN ap.employment_status = 'Self-Employed' THEN 'Self-Employed'
                              WHEN jt.title IS NOT NULL THEN jt.title
                              ELSE '-'
                          END as current_job,
                          CASE 
                              WHEN ap.employment_status = 'Self-Employed' THEN COALESCE(ei.business_type, '-')
                              WHEN ei.company_name IS NOT NULL THEN ei.company_name
                              ELSE '-'
                          END as current_employer
                   FROM alumni_profile ap
                   INNER JOIN users u ON ap.user_id = u.user_id
                   LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
                   LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
                   WHERE u.batch_year IN ($placeholders)
                   ORDER BY u.batch_year DESC, u.name"
];

    if (!isset($queries[$report_type])) {
        $_SESSION['error_message'] = "Invalid report type selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $stmt = $conn->prepare($queries[$report_type]);
    $stmt->bind_param($types, ...$selected_batches);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);



    // ==================================================================
    // PDF GENERATION USING TCPDF
    // ==================================================================
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Alumni Tracking System');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Alumni Report - ' . ucfirst($report_type));
    $pdf->SetSubject('Alumni Records Report');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'ALUMNI MANAGEMENT SYSTEM', 'Alumni Report - ' . date('F j, Y'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));

    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Add a page
    $pdf->AddPage();

    // Set font for title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($report_type) . ' ALUMNI REPORT', 0, 1, 'C');
    $pdf->Ln(5);

    // Report information
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Selected Batches: ' . implode(', ', $selected_batches), 0, 1);
    $pdf->Cell(0, 6, 'Total Records: ' . number_format(count($data)), 0, 1);
    $pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y \a\t g:i A'), 0, 1);
    $pdf->Ln(10);

    // Define table configurations for different report types
$table_config = [
    'summary' => [
        'headers' => ['Batch Year', 'Total Alumni', 'Employed', 'Self-Employed', 'Unemployed', 'Student', 'Student & Employed', 'Employment Rate %'],
        'widths'  => [25, 25, 25, 30, 25, 25, 45, 40],
        'align'   => ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C']
    ],
    'detailed' => [
        'headers' => ['Name', 'Email', 'Batch', 'Status', 'Current Job', 'Current Employer'],
        'widths'  => [50, 60, 25, 40, 50, 50],
        'align'   => ['L', 'L', 'C', 'L', 'L', 'L']
    ],
];

    $cfg = $table_config[$report_type];

    // Create table header
    $pdf->SetFillColor(59, 130, 246); // Blue color
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(59, 130, 246);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 10);

    // Header row
    for ($i = 0; $i < count($cfg['headers']); $i++) {
        $pdf->Cell($cfg['widths'][$i], 8, $cfg['headers'][$i], 1, 0, 'C', true);
    }
    $pdf->Ln();

 // Table data - FIXED VERSION
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);
$pdf->SetFont('helvetica', '', 9);

$fill = false;
foreach ($data as $row) {
    $fill = !$fill;
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 245 : 255, $fill ? 255 : 255);

    for ($i = 0; $i < count($cfg['headers']); $i++) {
        $header_text = $cfg['headers'][$i];
        $header_key = strtolower(str_replace([' ', '%', '&'], ['_', '', '_'], $header_text));

        // Special handling for these exact header texts
        $key_map = [
            'batchyear'             => 'batch_year',
            'totalalumni'           => 'total_alumni',
            'employed'              => 'employed',
            'self-employed'         => 'self_employed',
            'unemployed'            => 'unemployed',
            'student'               => 'student',
            'student_employed'      => 'student_employed',  // direct match
            'employmentrate'        => 'employment_rate',
            'name'                  => 'name',
            'email'                 => 'email',
            'batch'                 => 'batch_year',
            'status'                => 'employment_status',
            'currentjob'            => 'current_job',
            'currentemployer'       => 'current_employer'
        ];

        $field_name = $key_map[$header_key] ?? $header_key;
        $value = $row[$field_name] ?? '';

        // Format numeric values and percentages
        if ($report_type === 'summary') {
            if (strpos($header_text, 'Rate') !== false) {
                $value = is_numeric($value) ? number_format((float)$value, 2) . '%' : '0.00%';
            } elseif (in_array($header_text, ['Total Alumni', 'Employed', 'Self-Employed', 'Unemployed', 'Student', 'Student & Employed'])) {
                $value = $value !== null && $value !== '' ? number_format((int)$value) : '0';
            }
        }

        $pdf->Cell($cfg['widths'][$i], 7, $value, 'LR', 0, $cfg['align'][$i], true);
    }
    $pdf->Ln();
}
    // Closing line
    $pdf->Cell(array_sum($cfg['widths']), 0, '', 'T');
    $pdf->Ln(10);

    // Add summary note for empty reports
    if (empty($data)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'No records found for the selected criteria.', 0, 1, 'C');
    }

    // Output PDF
    $filename = "Alumni_Report_" . ucfirst($report_type) . "_" . date('Y-m-d') . ".pdf";
    $pdf->Output($filename, 'D'); // Force download
    exit();
}

// Function to check if submissions are open
function isSubmissionsOpen($conn) {
    $statusCheck = $conn->query("SELECT is_open FROM submission_status LIMIT 1");
    if ($statusCheck->num_rows > 0) {
        $status = $statusCheck->fetch_assoc();
        return (bool)$status['is_open'];
    }
    return false; // Default to closed if no status found
}
?>