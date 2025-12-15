<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Load necessary files
require_once '../api/notification/notif_service.php';
require_once __DIR__ . '/../api/utils/common_functions.php';

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter
$search = $_GET['search'] ?? '';

// Report generation logic (originally here) has been MOVED to report_generation.php.
// The block below is intentionally commented/removed.
/*
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
*/

// ====================== SUBMISSIONS CONTROL LOGIC (RETAINED) ======================
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

    if ($action === 'open_now' || $action === 'close_now') { // Combined open_now and close_now for manual override
        $new_is_open = $action === 'open_now' ? 1 : 0;
        $conn->query("UPDATE submission_status SET 
            is_open = $new_is_open, 
            manual_override = 1, 
            open_date = NULL, 
            close_date = NULL");
        
        $_SESSION['success_message'] = $new_is_open ? "Alumni submissions are now OPEN indefinitely." : "Alumni submissions are now CLOSED indefinitely.";
       
        if ($new_is_open) {
            // ==================== NOTIFICATION API INTEGRATION (Open Now) ====================
            // Notify alumni who need to update (haven't updated in 6 months OR no profile)
            $alumni_to_notify = $conn->query("
                SELECT u.user_id 
                FROM users u 
                LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
                WHERE u.role = 'alumni' 
                AND (
                    ap.user_id IS NULL 
                    OR ap.submission_status = 'Pending' 
                    OR ap.submission_status IS NULL
                    OR ap.last_profile_update IS NULL 
                    OR ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH)
                )
            ");

            $notification_count = 0;
            while ($alumni = $alumni_to_notify->fetch_assoc()) {
                // Send profile update reminder using CORRECT function signature
                $result = send_profile_update_reminder($conn, $alumni['user_id']);
                
                if ($result['success']) {
                    $notification_count++;
                }
            }
            
            // Add notification count to success message
            if ($notification_count > 0) {
                $_SESSION['success_message'] .= " Sent profile update reminders to {$notification_count} alumni.";
            }
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

            // Determine if submissions are currently open based on the schedule
            $now = date('Y-m-d H:i:s');
            $new_is_open = ($now >= $open_date && $now <= $close_date) ? 1 : 0;

            $conn->query("UPDATE submission_status SET 
                open_date = '$open_date', 
                close_date = '$close_date', 
                manual_override = 0,
                is_open = $new_is_open");  

            $_SESSION['success_message'] = "Submissions scheduled — Opens: $from | Closes: $to";
            
            // ==================== NOTIFICATION API INTEGRATION FOR SCHEDULED OPENING ====================
            // Notify alumni who need to update when submissions open on schedule
            $alumni_to_notify = $conn->query("
            SELECT u.user_id 
            FROM users u 
            LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
            WHERE u.role = 'alumni' 
            AND (
                ap.user_id IS NULL 
                OR ap.submission_status = 'Pending' 
                OR ap.submission_status IS NULL
                OR ap.last_profile_update IS NULL 
                OR ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH)
            )
        ");
            
            // Add notification count to success message
            $notification_count = 0;
            while ($alumni = $alumni_to_notify->fetch_assoc()) {
                // Send profile update reminder using CORRECT function signature
                $result = send_profile_update_reminder($conn, $alumni['user_id']);
                
                if ($result['success']) {
                    $notification_count++;
                }
            }

            // Add notification count to success message
            if ($notification_count > 0) {
                $_SESSION['success_message'] .= " Sent scheduled update reminders to {$notification_count} alumni.";
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
                SELECT u.user_id 
                FROM users u 
                LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
                WHERE u.role = 'alumni' 
                AND (
                    ap.user_id IS NULL 
                    OR ap.submission_status = 'Pending' 
                    OR ap.submission_status IS NULL
                    OR ap.last_profile_update IS NULL 
                    OR ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH)
                )
            ");
            
            // Log the automatic notifications
            $notification_count = 0;
            while ($alumni = $alumni_to_notify->fetch_assoc()) {
                // Send profile update reminder using CORRECT function signature
                $result = send_profile_update_reminder($conn, $alumni['user_id']);
                
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

        <div class="flex flex-col sm:flex-row gap-3 lg:ml-auto">
            <button id="toggleSubmissionModal" 
                    class="bg-<?= $status_color ?>-600 hover:bg-<?= $status_color ?>-700 text-white px-5 py-2 rounded-lg font-medium flex items-center gap-2 shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 whitespace-nowrap group relative">
                <i class="fas <?= $status_icon ?>"></i>
                <span>Submissions</span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-white text-<?= $status_color ?>-600 ml-2">
                    <?= $status_text ?>
                </span>
                <div class="absolute -top-2 -right-2 w-3 h-3 bg-<?= $status_color ?>-400 rounded-full animate-pulse"></div>
            </button>
        </div>
    </div>

    <?php if (!empty($search)): ?>
        <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-800 flex items-center gap-2">
            <i class="fas fa-info-circle"></i>
            <span>Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
            <span class="ml-auto text-blue-600 font-medium">
                <?php
                    // Calculate the number of batches that contain alumni matching the search term
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
            // The 'All Alumni' card has been removed as requested.
            ?>

            <?php
            $displayResult = $batchResult;
            if (!empty($search)) {
                // Filter the batches shown only to those containing matches
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

                    // --- FIX APPLIED HERE: Calculate count based on search status ---
                    $count = 0;
                    if (!empty($search)) {
                        // Calculate the filtered count for this specific batch and search term
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
                        // Use the full count if no search is active
                        $batch_index = array_search($year, array_column($all_batches, 'batch_year'));
                        $count = ($batch_index !== false) ? $all_batches[$batch_index]['total_count'] : 0;
                    }
                    // -----------------------------------------------------------------
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
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="open_now" class="text-emerald-600" <?= $is_open && $manual_override ? 'checked' : '' ?>>
                        <span class="font-medium">Open Submissions Now</span>
                    </label>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="close_now" class="text-red-600" <?= !$is_open && $manual_override ? 'checked' : '' ?>>
                        <span class="font-medium">Close Submissions Now</span>
                    </label>
                </div>

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

            <div id="submissionError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4" role="alert">
                <span id="submissionErrorText"></span>
            </div>

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

    const modal = document.getElementById('submissionModal');
    const openModal = document.getElementById('toggleSubmissionModal');
    const closeModal = document.getElementById('closeModal');
    const cancelModal = document.getElementById('cancelModal');

    openModal.addEventListener('click', () => modal.classList.remove('hidden'));
    closeModal.addEventListener('click', () => modal.classList.add('hidden'));
    cancelModal.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
// Removed generateAlumniReport function as it's moved to report_generation.php
?>