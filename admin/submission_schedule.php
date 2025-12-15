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

$page_title = "Submission Schedule";
$active_page = "submission_schedule";

// ====================== SUBMISSIONS CONTROL LOGIC ======================
// Ensure the table exists
$conn->query("CREATE TABLE IF NOT EXISTS submission_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    is_open TINYINT(1) DEFAULT 0,
    manual_override TINYINT(1) DEFAULT 0,
    open_date DATETIME NULL,
    close_date DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Ensure a single record exists
$statusCheck = $conn->query("SELECT * FROM submission_status LIMIT 1");
if ($statusCheck->num_rows == 0) {
    // Default to closed and manually overridden
    $conn->query("INSERT INTO submission_status (is_open, manual_override) VALUES (0, 1)");
}

// --- Function to fetch alumni needing updates ---
function get_alumni_for_update_notification($conn) {
    return $conn->query("
        SELECT u.user_id 
        FROM users u 
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
        WHERE u.role = 'alumni' 
        AND (
            ap.user_id IS NULL 
            OR ap.last_profile_update IS NULL 
            OR ap.last_profile_update < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        )
    ");
}

// ====================== FORM SUBMISSION PROCESSING ======================
if (isset($_POST['update_submission_status'])) {
    $action = $_POST['submission_action'] ?? '';
    $notification_count = 0; // Initialize counter for notifications sent
    $update_successful = false;

    if ($action === 'open_now' || $action === 'close_now') { 
        
        $new_is_open = $action === 'open_now' ? 1 : 0;
        
        // Use prepared statement for safety
        $stmt = $conn->prepare("UPDATE submission_status SET 
            is_open = ?, 
            manual_override = 1, 
            open_date = NULL, 
            close_date = NULL");
        $stmt->bind_param('i', $new_is_open);
        $update_successful = $stmt->execute();
        $stmt->close();

        if ($update_successful) {
            $status_message = $new_is_open ? "Alumni submissions are now OPEN indefinitely." : "Alumni submissions are now CLOSED indefinitely.";
            $status_type = 'success';
        
            if ($new_is_open) {
                // NOTIFICATION API INTEGRATION (Open Now)
                $alumni_to_notify = get_alumni_for_update_notification($conn);
                
                while ($alumni = $alumni_to_notify->fetch_assoc()) {
                    $result = send_profile_update_reminder($conn, $alumni['user_id']);
                    if ($result['success']) {
                        $notification_count++;
                    }
                }
                
                if ($notification_count > 0) {
                    $status_message .= " Sent profile update reminders to {$notification_count} alumni.";
                }
            }
        } else {
            $status_message = "Database error: Could not update status.";
            $status_type = 'error';
        }

    } elseif ($action === 'schedule') {
        $open_date_input  = $_POST['open_date'] ?? null;
        $close_date_input = $_POST['close_date'] ?? null;

        // Validation: Ensure both dates are set and close date is after open date
        if ($open_date_input && $close_date_input && strtotime($open_date_input) < strtotime($close_date_input)) {
            
            $open_date  = date('Y-m-d H:i:s', strtotime($open_date_input));
            $close_date = date('Y-m-d H:i:s', strtotime($close_date_input));

            // Format for display in toast
            $from = date('M j, Y \a\t g:i A', strtotime($open_date));
            $to   = date('M j, Y \a\t g:i A', strtotime($close_date));

            // Determine if submissions are currently open based on the schedule
            $now = date('Y-m-d H:i:s');
            $new_is_open = ($now >= $open_date && $now <= $close_date) ? 1 : 0;

            // Use prepared statement for security
            $stmt = $conn->prepare("UPDATE submission_status SET 
                open_date = ?, 
                close_date = ?, 
                manual_override = 0,
                is_open = ?");
            $stmt->bind_param('ssi', $open_date, $close_date, $new_is_open);
            $update_successful = $stmt->execute();
            $stmt->close();

            if ($update_successful) {
                $status_message = "Submissions scheduled — Opens: $from | Closes: $to";
                $status_type = 'success';
                
                // NOTIFICATION API INTEGRATION FOR SCHEDULED OPENING (if currently open)
                if ($new_is_open) {
                    $alumni_to_notify = get_alumni_for_update_notification($conn);
                    
                    while ($alumni = $alumni_to_notify->fetch_assoc()) {
                        $result = send_profile_update_reminder($conn, $alumni['user_id']);
                        if ($result['success']) {
                            $notification_count++;
                        }
                    }

                    if ($notification_count > 0) {
                        $status_message .= " Sent scheduled update reminders to {$notification_count} alumni.";
                    }
                }
            } else {
                $status_message = "Database error: Could not update schedule.";
                $status_type = 'error';
            }
            
        } else {
            $status_message = "Invalid schedule: Closing date & time must be after opening date & time.";
            $status_type = 'error';
        }
    }
}

// ====================== LOAD & AUTOMATIC STATUS CHECK ======================
$statusRow = $conn->query("SELECT * FROM submission_status LIMIT 1")->fetch_assoc();
$is_open = (bool)($statusRow['is_open'] ?? 0);
$manual_override = (bool)($statusRow['manual_override'] ?? 1);
$open_date = $statusRow['open_date'] ?? null;
$close_date = $statusRow['close_date'] ?? null;

// Check and update status if not manually overridden and scheduled dates exist
if (!$manual_override && $open_date && $close_date) {
    $now = date('Y-m-d H:i:s');
    $new_status = ($now >= $open_date && $now <= $close_date) ? 1 : 0;
    
    // Only update DB if the status has actually changed
    if ($new_status != $is_open) {
        
        $stmt = $conn->prepare("UPDATE submission_status SET is_open = ?");
        $stmt->bind_param('i', $new_status);
        $stmt->execute();
        $stmt->close();
        
        $is_open = $new_status;

        // AUTOMATIC NOTIFICATION WHEN SCHEDULE OPENS
        if ($new_status == 1) {
            // Notify alumni when submissions automatically open per schedule
            $alumni_to_notify = get_alumni_for_update_notification($conn);
            
            $notification_count = 0;
            while ($alumni = $alumni_to_notify->fetch_assoc()) {
                $result = send_profile_update_reminder($conn, $alumni['user_id']);
                if ($result['success']) {
                    $notification_count++;
                }
            }

            if ($notification_count > 0) {
                error_log("[" . date('Y-m-d H:i:s') . "] Automatically sent profile update reminders to {$notification_count} alumni when submissions opened on schedule.");
            }
        }
    }
}

// ====================== STATUS DISPLAY VARIABLES ======================
$status_text = $is_open ? "OPEN" : "CLOSED";
$status_color = $is_open ? 'emerald' : 'red';
$status_icon = $is_open ? "fa-unlock-alt" : "fa-lock";

// ====================== HTML OUTPUT START ======================
ob_start();
?>

<div class="max-w-6xl mx-auto space-y-6">

<?php if (isset($status_message)): ?>
    <div class="p-4 rounded-lg border <?= $status_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
        <div class="flex items-center gap-2">
            <i class="fas <?= $status_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
            <span><?= htmlspecialchars($status_message) ?></span>
        </div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Submission Status</h2>
        <p class="text-gray-600 text-sm mt-1">Manage when alumni can submit profile updates</p>
    </div>
    
    <div class="p-6">
        <div class="flex items-center justify-between p-5 rounded-lg bg-gray-50 border border-gray-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full <?= $is_open ? 'bg-emerald-100' : 'bg-red-100' ?> flex items-center justify-center">
                    <i class="fas <?= $status_icon ?> text-xl <?= $is_open ? 'text-emerald-600' : 'text-red-600' ?>"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Submissions are currently</p>
                    <p class="text-2xl font-bold <?= $is_open ? 'text-emerald-600' : 'text-red-600' ?>"><?= $status_text ?></p>
                </div>
            </div>
            
            <div class="text-right">
                <?php if ($manual_override): ?>
                    <div class="text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 px-2 py-1 rounded">
                            <i class="fas fa-hand-pointer text-xs"></i>
                            Manual Control
                        </span>
                        <p class="mt-1">Will remain <?= strtolower($status_text) ?> until changed</p>
                    </div>
                <?php else: ?>
                    <div class="text-sm">
                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded">
                            <i class="fas fa-clock text-xs"></i>
                            Scheduled
                        </span>
                        <div class="mt-2 space-y-1">
                            <p><span class="font-medium">Opens:</span> <?= date('M j, Y g:i A', strtotime($open_date)) ?></p>
                            <p><span class="font-medium">Closes:</span> <?= date('M j, Y g:i A', strtotime($close_date)) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Control Settings</h2>
        <p class="text-gray-600 text-sm mt-1">Choose how to manage submission availability</p>
    </div>
    
    <div class="p-6">
        <form method="POST" class="space-y-6" onsubmit="return validateSubmissionDates()">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-start gap-3 p-4 border border-gray-300 rounded-lg hover:border-emerald-400 hover:bg-emerald-50 transition-all cursor-pointer <?= $is_open && $manual_override ? 'border-emerald-400 bg-emerald-50' : '' ?>">
                        <input type="radio" name="submission_action" value="open_now" class="mt-1 text-emerald-600" <?= $is_open && $manual_override ? 'checked' : '' ?>>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">Open Indefinitely</div>
                            <div class="text-sm text-gray-600 mt-1">Alumni can submit profiles immediately</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border border-gray-300 rounded-lg hover:border-red-400 hover:bg-red-50 transition-all cursor-pointer <?= !$is_open && $manual_override ? 'border-red-400 bg-red-50' : '' ?>">
                        <input type="radio" name="submission_action" value="close_now" class="mt-1 text-red-600" <?= !$is_open && $manual_override ? 'checked' : '' ?>>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">Close Indefinitely</div>
                            <div class="text-sm text-gray-600 mt-1">Stop all alumni submissions</div>
                        </div>
                    </label>
                </div>

                <div class="border border-gray-300 rounded-lg p-4 <?= !$manual_override ? 'border-blue-400 bg-blue-50' : '' ?>">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="submission_action" value="schedule" class="mt-1 text-blue-600" id="scheduleRadio" <?= !$manual_override ? 'checked' : '' ?>>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">Schedule Period</div>
                            <div class="text-sm text-gray-600">Set specific open/close dates</div>
                        </div>
                    </label>
                    
                    <div class="mt-4 pl-7 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Open Date & Time</label>
                                <input type="datetime-local" name="open_date" id="open_date" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition"
                                    value="<?= $open_date ? str_replace(' ', 'T', $open_date) : '' ?>"
                                    <?= $manual_override ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Close Date & Time</label>
                                <input type="datetime-local" name="close_date" id="close_date" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition"
                                    value="<?= $close_date ? str_replace(' ', 'T', $close_date) : '' ?>"
                                    <?= $manual_override ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="submissionError" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-red-700">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="submissionErrorText"></span>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-200">
                <button type="submit" name="update_submission_status" 
                    class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
function validateSubmissionDates() {
    const scheduleRadio = document.getElementById('scheduleRadio');
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    
    // Reset error display
    errorBox.classList.add('hidden');
    errorText.textContent = '';

    // Only validate dates if the schedule option is selected
    if (!scheduleRadio.checked) return true; 

    const openDateInput = document.getElementById('open_date');
    const closeDateInput = document.getElementById('close_date');
    const openDate = new Date(openDateInput.value);
    const closeDate = new Date(closeDateInput.value);
    const now = new Date();
    
    // Normalize 'now' to nearest minute for comparison
    now.setSeconds(0, 0); 
    now.setMilliseconds(0);

    if (!openDateInput.value || !closeDateInput.value) {
        showError('Both opening and closing dates are required for scheduling.');
        return false;
    }

    if (closeDate <= openDate) {
        showError('Closing time must be after opening time.');
        return false;
    }

    // Allow opening date in the past, but show a warning
    if (openDate < now) {
        const formatted = openDate.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
        showError('Warning: Opening date is in the past. Submissions will open immediately.');
    }

    return true;
}

function showError(message) {
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    errorText.textContent = message;
    errorBox.classList.remove('hidden');
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Toggle schedule inputs based on radio selection
document.addEventListener('DOMContentLoaded', function () {
    const radioButtons = document.querySelectorAll('input[name="submission_action"]');
    const scheduleRadio = document.getElementById('scheduleRadio');
    const scheduleInputs = document.querySelectorAll('#open_date, #close_date');
    
    function toggleScheduleInputs() {
        const isSchedule = scheduleRadio.checked;
        scheduleInputs.forEach(input => {
            input.disabled = !isSchedule;
            input.style.backgroundColor = isSchedule ? 'white' : '#f9fafb';
        });
    }

    // Initial setup
    toggleScheduleInputs();

    // Add change listeners
    radioButtons.forEach(radio => {
        radio.addEventListener('change', toggleScheduleInputs);
    });
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>