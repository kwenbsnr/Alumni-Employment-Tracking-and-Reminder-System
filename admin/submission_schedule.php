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
$control_text = $manual_override ? 'Manual Control' : 'Scheduled Period';
$control_icon = $manual_override ? 'fa-hand-pointer' : 'fa-calendar-check';
$control_color = $manual_override ? 'blue' : 'indigo';


// ====================== HTML OUTPUT START ======================
ob_start();
?>

<style>
    /* MODIFIED STYLES FOR FULL WIDTH AND SCROLLBAR REMOVAL/ADJUSTMENT 
    - Removed max-width from the main container in the HTML.
    - Removed all scrollbar and height constraints in the CSS to allow content to fully expand.
    - Preserved existing component styles (card-glow, option-card, etc.).
    */
    .card-glow {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid rgba(229, 231, 235, 0.8);
    }
    
    .card-glow:hover {
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }
    
    .option-card {
        transition: all 0.2s ease;
        border: 2px solid #e5e7eb;
        background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .option-card:hover {
        border-color: #9ca3af;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }
    
    .option-card.selected {
        border-color: #3b82f6;
        background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
        box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1), 0 4px 20px rgba(59, 130, 246, 0.1);
    }
    
    .option-card.selected::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(to bottom, #3b82f6, #60a5fa);
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
        letter-spacing: 0.025em;
        white-space: nowrap; /* Added to keep badges from wrapping */
    }
    
    .datetime-input {
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        transition: all 0.2s ease;
        background-color: white;
    }
    
    .datetime-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .datetime-input:disabled {
        background-color: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
        border-color: #d1d5db;
    }
    
    .control-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 1rem;
        border: none;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .control-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
    
    .control-btn:active {
        transform: translateY(0);
    }
    
    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
        100% {
            opacity: 1;
        }
    }
    
    /* REMOVED PREVIOUS SCROLLBAR CONTROLS (main-content-container, content-wrapper, no-scroll) */
</style>

<div class="main-content-container">
    <div class="content-wrapper">
        <div class="max-w-full px-6 pb-6">
            
            <?php if (isset($status_message)): ?>
                <div id="status-toast" 
                    class="fixed bottom-6 right-6 z-50 max-w-md w-full p-5 rounded-xl shadow-2xl text-white font-semibold flex items-center gap-4 animate-slide-up
                    <?= $status_type === 'success' ? 'bg-emerald-600' : 'bg-red-600' ?>">
                    <i class="fas <?= $status_type === 'success' ? 'fa-check' : 'fa-exclamation-triangle' ?> text-2xl"></i>
                    <div>
                        <div class="text-lg font-bold"><?= $status_type === 'success' ? 'Success' : 'Error' ?></div>
                        <div class="text-sm opacity-90"><?= htmlspecialchars($status_message) ?></div>
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
                    }, 4000);
                </script>
            <?php endif; ?>

            <div class="bg-gradient-to-br from-blue-50 to-white p-6 rounded-2xl card-glow mb-6">
                
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-1xl font-bold text-gray-800 mb-0 flex items-center gap-1">
                            Current Submission Status
                        </h2>
                        <p class="text-gray-600 text-sm">Real-time availability for alumni profile updates.</p>
                    </div>
                    
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="status-badge <?= $is_open ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
                            <span class="status-indicator <?= $is_open ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border-2 <?= $is_open ? 'border-emerald-200' : 'border-red-200' ?> flex flex-col md:flex-row items-start md:items-center justify-between">
                    
                    <div class="flex items-start gap-4 mb-4 md:mb-0 flex-grow">
                        <div class="w-12 h-12 rounded-full <?= $is_open ? 'bg-emerald-100' : 'bg-red-100' ?> flex items-center justify-center flex-shrink-0">
                            <i class="fas <?= $status_icon ?> text-xl <?= $is_open ? 'text-emerald-600' : 'text-red-600' ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-lg md:text-xl leading-snug">Submissions are <?= strtolower($status_text) ?></h3>
                            <p class="text-gray-500 text-sm mt-0.5">
                                <?= $is_open ? 'Alumni can currently submit and update their profiles.' : 'Alumni submissions are currently not accepted.' ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col items-start md:items-end gap-2 md:ml-4 flex-shrink-0">
                        <h4 class="font-semibold text-gray-700 mb-1 md:mb-0 text-sm">Control Mode</h4>
                        <span class="status-badge <?= 'bg-' . $control_color . '-100 text-' . $control_color . '-800 border border-' . $control_color . '-200' ?>">
                            <i class="fas <?= $control_icon ?> mr-2"></i>
                            <?= $control_text ?>
                        </span>
                        <?php if (!$manual_override && $open_date && $close_date): ?>
                            <div class="text-xs text-gray-600 text-right mt-1">
                                <p class="font-medium">Schedule: <span class="text-gray-500"><?= date('M j, Y g:i A', strtotime($open_date)) ?> - <?= date('M j, Y g:i A', strtotime($close_date)) ?></span></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-gray-50 to-white p-6 rounded-2xl card-glow">
                <h2 class="text-xl font-bold text-gray-800 mb-0 flex items-center gap-3">
                Submission Control Settings
                </h2>
                <p class="text-gray-600 mb-4">Define the availability for alumni profile submissions.</p>
                
                <form method="POST" class="space-y-6" onsubmit="return validateSubmissionDates()" id="scheduleForm">
                    <div class="space-y-4">
                        <p class="text-gray-700 font-medium">Choose Control Method:</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <label class="option-card p-5 rounded-xl <?= $is_open && $manual_override ? 'selected' : '' ?>">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="submission_action" value="open_now" 
                                            class="mt-1 w-5 h-5 text-blue-600 focus:ring-blue-500" 
                                            <?= $is_open && $manual_override ? 'checked' : '' ?>>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-unlock-alt text-emerald-500"></i>
                                            <h3 class="font-semibold text-gray-800">Open Indefinitely</h3>
                                        </div>
                                        <p class="text-sm text-gray-500">Alumni can submit profiles immediately without time restrictions.</p>
                                    </div>
                                </div>
                            </label>

                            <label class="option-card p-5 rounded-xl <?= !$is_open && $manual_override ? 'selected' : '' ?>">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="submission_action" value="close_now" 
                                            class="mt-1 w-5 h-5 text-red-600 focus:ring-red-500" 
                                            <?= !$is_open && $manual_override ? 'checked' : '' ?>>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-lock text-red-500"></i>
                                            <h3 class="font-semibold text-gray-800">Close Indefinitely</h3>
                                        </div>
                                        <p class="text-sm text-gray-500">All submissions are stopped until explicitly opened.</p>
                                    </div>
                                </div>
                            </label>

                            <label class="option-card p-5 rounded-xl <?= !$manual_override ? 'selected' : '' ?>">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="submission_action" value="schedule" 
                                            class="mt-1 w-5 h-5 text-blue-600 focus:ring-blue-500" 
                                            id="scheduleRadio" <?= !$manual_override ? 'checked' : '' ?>>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-clock text-blue-500"></i>
                                            <h3 class="font-semibold text-gray-800">Schedule Period</h3>
                                        </div>
                                        <p class="text-sm text-gray-500">Set specific open and close dates for submissions.</p>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div id="scheduleInputs" class="bg-white p-5 rounded-xl border-2 border-gray-200 mt-4 <?= !$manual_override ? '' : 'opacity-60' ?>">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-calendar-day text-blue-600"></i>
                                </div>
                                <h3 class="font-semibold text-gray-800">Schedule Settings</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Open Date & Time</label>
                                    <input type="datetime-local" name="open_date" id="open_date" 
                                            class="datetime-input w-full"
                                            value="<?= $open_date ? str_replace(' ', 'T', $open_date) : '' ?>"
                                            <?= $manual_override ? 'disabled' : '' ?>>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Close Date & Time</label>
                                    <input type="datetime-local" name="close_date" id="close_date" 
                                            class="datetime-input w-full"
                                            value="<?= $close_date ? str_replace(' ', 'T', $close_date) : '' ?>"
                                            <?= $manual_override ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            
                            <?php if (!$manual_override && $open_date && $close_date): ?>
                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center gap-2 text-blue-700">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="text-sm font-medium">Current Schedule Active</span>
                                    </div>
                                    <p class="text-sm text-blue-600 mt-1">
                                        Opens: <?= date('M j, Y \a\t g:i A', strtotime($open_date)) ?><br>
                                        Closes: <?= date('M j, Y \a\t g:i A', strtotime($close_date)) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="submissionError" class="hidden p-4 bg-amber-50 border border-amber-300 rounded-xl">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-500"></i>
                            <div>
                                <p class="font-medium text-amber-800" id="submissionErrorText"></p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <button type="submit" name="update_submission_status" 
                                class="control-btn flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Save Submission Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize option card selection
    const optionCards = document.querySelectorAll('.option-card');
    const scheduleRadio = document.getElementById('scheduleRadio');
    const scheduleInputs = document.getElementById('scheduleInputs');
    const dateInputs = document.querySelectorAll('#open_date, #close_date');
    
    // Update card selection on radio change
    document.querySelectorAll('input[name="submission_action"]').forEach(radio => {
        radio.addEventListener('change', function() {
            optionCards.forEach(card => card.classList.remove('selected'));
            const parentCard = this.closest('.option-card');
            if (parentCard) parentCard.classList.add('selected');
            
            // Toggle schedule inputs
            const isSchedule = this.value === 'schedule';
            scheduleInputs.classList.toggle('opacity-60', !isSchedule);
            dateInputs.forEach(input => {
                input.disabled = !isSchedule;
                input.style.backgroundColor = isSchedule ? 'white' : '#f9fafb';
            });
        });
    });
    
    // Initialize selection state
    document.querySelector('input[name="submission_action"]:checked').dispatchEvent(new Event('change'));
});

function validateSubmissionDates() {
    const scheduleRadio = document.getElementById('scheduleRadio');
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    
    // Reset error
    errorBox.classList.add('hidden');
    errorText.textContent = '';
    
    // Only validate if schedule option is selected
    if (!scheduleRadio.checked) return true;
    
    const openDateInput = document.getElementById('open_date');
    const closeDateInput = document.getElementById('close_date');
    
    if (!openDateInput.value || !closeDateInput.value) {
        showError('Both opening and closing dates are required for scheduling.', 'error');
        return false;
    }
    
    const openDate = new Date(openDateInput.value);
    const closeDate = new Date(closeDateInput.value);
    
    if (closeDate <= openDate) {
        showError('Closing time must be after opening time.', 'error');
        return false;
    }
    
    const now = new Date();
    if (openDate < now) {
        showError('Warning: Opening date is in the past. Submissions will open immediately.', 'warning');
    }
    
    return true;
}

function showError(message, type = 'error') {
    const errorBox = document.getElementById('submissionError');
    const errorText = document.getElementById('submissionErrorText');
    const icon = errorBox.querySelector('i');
    
    errorText.textContent = message;
    errorBox.classList.remove('hidden');
    
    if (type === 'error') {
        errorBox.className = 'p-4 bg-red-50 border border-red-300 rounded-xl';
        icon.className = 'fas fa-times-circle text-red-500';
        errorText.className = 'font-medium text-red-800';
    } else {
        errorBox.className = 'p-4 bg-amber-50 border border-amber-300 rounded-xl';
        icon.className = 'fas fa-exclamation-triangle text-amber-500';
        errorText.className = 'font-medium text-amber-800';
    }
    
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Auto-hide toast if present
setTimeout(() => {
    const toast = document.getElementById('status-toast');
    if (toast) {
        toast.style.transition = 'all 0.4s ease-out';
        toast.style.transform = 'translateY(150%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }
}, 4000);
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>