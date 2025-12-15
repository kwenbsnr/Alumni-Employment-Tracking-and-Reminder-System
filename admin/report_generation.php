<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Load TCPDF library
require_once '../tcpdf/tcpdf.php';
require_once __DIR__ . '/../api/utils/common_functions.php';

$page_title = "Generate Alumni Report";
$active_page = "report_generation";

// ====================== REPORT GENERATION LOGIC (FROM alumni_management.php) ======================
if (isset($_POST['generate_report'])) {
    $selected_batches = $_POST['selected_batches'] ?? [];
    $report_type      = $_POST['report_type'] ?? 'summary';

    if (!empty($selected_batches)) {
        generateAlumniReport($selected_batches, $report_type, $conn);
        // Exit after generating report to prevent further execution
        exit();
    } else {
        $_SESSION['error_message'] = "Please select at least one batch to generate a report.";
        // Redirect to clear POST data and show error
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch batches - count all alumni users for the form
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
} else {
    // Handle query error
    error_log("Batch query failed: " . $conn->error);
}

ob_start();
?>

<div class="space-y-6">

<?php 
$show_toast = false;
$toast_message = '';
$toast_type = 'success'; 

if (isset($_SESSION['success_message'])) {
    $show_toast = true;
    $toast_message = $_SESSION['success_message'];
    $toast_type = 'success';
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
    <i class="fas <?= $toast_type === 'success' ? 'fa-check' : 'fa-exclamation-triangle' ?> text-2xl"></i>
    <div>
        <div class="text-lg font-bold"><?= $toast_type === 'success' ? 'Report Generated' : 'Error' ?></div>
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

<div class="bg-gradient-to-br from-green-50 to-white p-6 rounded-xl shadow-lg border-2 border-green-200">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-3">
        <i class="fas fa-file-export text-green-600"></i> Customize Alumni Report
    </h2>
    <p class="text-gray-600 mb-6">Select the graduation batches and the type of report you wish to generate (Summary or Detailed List).</p>
    
    <form method="POST" action="" class="space-y-6" id="reportForm" target="_blank">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-5 rounded-xl border-2 border-gray-200">
                <h3 class="font-semibold text-xl mb-4 flex items-center gap-2 text-gray-700">
                    <i class="fas fa-layer-group text-blue-600"></i> Select Batches to Include
                </h3>
                <?php if (empty($all_batches)): ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                        <p>No alumni records found to generate a report.</p>
                    </div>
                <?php else: ?>
                <div class="max-h-80 overflow-y-auto space-y-2">
                    <?php foreach ($all_batches as $batch): ?>
                        <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 cursor-pointer transition">
                            <input type="checkbox" name="selected_batches[]" value="<?= $batch['batch_year'] ?>" checked class="h-5 w-5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="flex-1 text-lg">Batch <?= $batch['batch_year'] ?></span>
                            <span class="text-gray-500 text-sm">(<?= $batch['total_count'] ?> records)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="button" id="selectAll" class="text-sm bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition font-medium">Select All</button>
                    <button type="button" id="deselectAll" class="text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition font-medium">Deselect All</button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-white p-5 rounded-xl border-2 border-gray-200 space-y-6">
                <div>
                    <label class="block font-semibold mb-2 text-lg text-gray-700">Report Type</label>
                    <select name="report_type" class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-gray-800 focus:ring-green-500 focus:border-green-500 transition">
                        <option value="summary">Summary Report (Employment Statistics)</option>
                        <option value="detailed">Detailed Alumni List (Full Contact & Job Info)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-lg text-gray-700">Export Format</label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-3">
                            <input type="radio" name="format" value="pdf" checked class="h-5 w-5 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <span class="font-medium text-gray-800">PDF Document</span>
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 mt-1 pl-1">Reports will open in a new tab as PDF files</p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
            <a href="alumni_management.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                <i class="fas fa-arrow-left"></i> Back to Alumni Records
            </a>
            <button type="submit" name="generate_report" id="submitReportBtn" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 transition font-medium">
                <i class="fas fa-file-pdf"></i> Generate PDF Report
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAll');
    const deselectAll = document.getElementById('deselectAll');
    const submitBtn = document.getElementById('submitReportBtn');
    const reportForm = document.getElementById('reportForm');
    const checkboxes = document.querySelectorAll('input[name="selected_batches[]"]');
    
    function checkSelectedBatches() {
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.title = 'Please select at least one batch.';
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            submitBtn.title = '';
        }
    }

    selectAll.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = true);
        checkSelectedBatches();
    });
    
    deselectAll.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = false);
        checkSelectedBatches();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', checkSelectedBatches));
    
    // Initial check
    checkSelectedBatches();

    // Prevent form submission if no batches are selected (redundant due to PHP, but good for UX)
    reportForm.addEventListener('submit', (e) => {
        if (submitBtn.disabled) {
            e.preventDefault();
            alert('Please select at least one batch to generate a report.');
        }
    });
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>

<?php
// ====================== generateAlumniReport Function (FROM alumni_management.php) ======================
function generateAlumniReport($selected_batches, $report_type, $conn) {
    if (ob_get_length()) ob_clean();

    // make sure batches are integers (prevent SQL injection and type issues)
    $selected_batches = array_map('intval', $selected_batches);
    $count_batches = count($selected_batches);
    if ($count_batches === 0) {
        $_SESSION['error_message'] = "Please select at least one batch.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Build placeholders and types for bind_param
    $placeholders = implode(',', array_fill(0, $count_batches, '?'));
    $types = str_repeat('i', $count_batches); // batches are integers

    // Debug
    error_log("Selected batches for report: " . implode(', ', $selected_batches));

    // Use normalized employment_status (lower + trimmed) to avoid mismatches
    $queries = [
        'summary' => "SELECT 
            u.batch_year AS batch_year,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'employed' THEN 1 ELSE 0 END) AS employed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' THEN 1 ELSE 0 END) AS self_employed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'unemployed' THEN 1 ELSE 0 END) AS unemployed,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) = 'student' THEN 1 ELSE 0 END) AS student,
            SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed & student','employed and student') THEN 1 ELSE 0 END) AS employed_student,
            COUNT(*) AS total_alumni,
            CASE WHEN COUNT(*) > 0 THEN ROUND(100.0 * (
                SUM(CASE WHEN LOWER(TRIM(ap.employment_status)) IN ('employed','self-employed','employed & student','employed and student') THEN 1 ELSE 0 END)
            ) / COUNT(*), 2) ELSE 0 END AS employment_rate
        FROM alumni_profile ap
        INNER JOIN users u ON ap.user_id = u.user_id
        WHERE u.batch_year IN ($placeholders)
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
            COALESCE(ap.employment_status, 'Not Updated') as employment_status,
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
    FROM alumni_profile ap
    INNER JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
    LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
    WHERE u.batch_year IN ($placeholders)
    ORDER BY u.batch_year DESC, name"
    ];

    if (!isset($queries[$report_type])) {
        $_SESSION['error_message'] = "Invalid report type selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Prepare statement dynamically and bind params
    $stmt = $conn->prepare($queries[$report_type]);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error_message'] = "Failed to prepare report query.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Bind the batch params
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < $count_batches; $i++) {
        // Must pass by reference
        $bind_names[] = &$selected_batches[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    // Execute with error handling
    if (!$stmt->execute()) {
        error_log("Report generation failed: " . $stmt->error);
        $_SESSION['error_message'] = "Failed to generate report: " . $stmt->error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // ==================================================================
    // PDF GENERATION USING TCPDF (COMPLETE CODE)
    // ==================================================================
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Alumni Tracking System');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Alumni Report - ' . ucfirst($report_type));
    $pdf->SetSubject('Alumni Records Report');
    $pdf->SetHeaderData('', 0, 'ALUMNI MANAGEMENT SYSTEM', 'Alumni Report - ' . date('F j, Y'));
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($report_type) . ' ALUMNI REPORT', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Selected Batches: ' . implode(', ', $selected_batches), 0, 1);
    $pdf->Cell(0, 6, 'Total Records: ' . number_format(count($data)), 0, 1);
    $pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y \a\t g:i A'), 0, 1);
    $pdf->Ln(10);

    // Table configurations
    $table_config = [
        'summary' => [
            'headers' => ['Batch Year', 'Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed/Student', 'Total', 'Employment Rate'],
            'widths' => [30, 30, 35, 30, 30, 40, 20, 30], // Total width approx 245mm for landscape A4
            'data_keys' => ['batch_year', 'employed', 'self_employed', 'unemployed', 'student', 'employed_student', 'total_alumni', 'employment_rate'],
            'align' => ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'],
            'total_label' => 'Total Alumni:',
        ],
        'detailed' => [
            'headers' => ['Batch Year', 'Name', 'Email', 'Employment Status', 'Current Job', 'Current Employer'],
            'widths' => [25, 50, 60, 40, 40, 60], // Total width approx 275mm for landscape A4
            'data_keys' => ['batch_year', 'name', 'email', 'employment_status', 'current_job', 'current_employer'],
            'align' => ['C', 'L', 'L', 'L', 'L', 'L'],
            'total_label' => 'Total Records:',
        ]
    ];

    $config = $table_config[$report_type];

    // --- Draw Table Header ---
    $pdf->SetFillColor(230, 240, 255); // Light Blue for Header
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 9);

    for ($i = 0; $i < count($config['headers']); $i++) {
        $pdf->Cell($config['widths'][$i], 7, $config['headers'][$i], 1, 0, $config['align'][$i], 1);
    }
    $pdf->Ln();

    // --- Draw Table Body and Calculate Totals ---
    $pdf->SetFillColor(255);
    $pdf->SetFont('helvetica', '', 9);
    $totals = array_fill_keys($config['data_keys'], 0);
    $is_summary = $report_type === 'summary';

    foreach ($data as $row) {
        $pdf->SetFillColor(255);
        $pdf->SetTextColor(0);

        // Check for new page before drawing row
        if ($pdf->GetY() + 6 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            // Redraw header on new page
            $pdf->SetFillColor(230, 240, 255); // Light Blue for Header
            $pdf->SetTextColor(0);
            $pdf->SetDrawColor(150, 150, 150);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('helvetica', 'B', 9);

            for ($i = 0; $i < count($config['headers']); $i++) {
                $pdf->Cell($config['widths'][$i], 7, $config['headers'][$i], 1, 0, $config['align'][$i], 1);
            }
            $pdf->Ln();
            
            $pdf->SetFillColor(255);
            $pdf->SetFont('helvetica', '', 9);
        }

        for ($i = 0; $i < count($config['data_keys']); $i++) {
            $key = $config['data_keys'][$i];
            $value = $row[$key];
            $align = $config['align'][$i];
            $width = $config['widths'][$i];

            // Special formatting for summary report data and totals calculation
            if ($is_summary) {
                // Total calculation for numeric columns
                if (in_array($key, ['employed', 'self_employed', 'unemployed', 'student', 'employed_student', 'total_alumni'])) {
                    $totals[$key] += (int)$value;
                }
                // Format employment rate with percentage sign
                if ($key === 'employment_rate') {
                    $value .= '%';
                }
            }
            
            // Output the cell
            $pdf->Cell($width, 6, $value, 1, 0, $align, 1, '', 0, false, 'T', 'M');
        }
        $pdf->Ln();
    }
    
    // --- Draw Totals Row (Summary Report Only) ---
    if ($is_summary && !empty($data)) {
        // Check for new page before drawing total row
        if ($pdf->GetY() + 7 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
        }

        $pdf->SetFillColor(200, 215, 230); // Darker Blue for Footer
        $pdf->SetFont('helvetica', 'B', 9);
        $total_rate = 0;

        if ($totals['total_alumni'] > 0) {
            $total_employed = $totals['employed'] + $totals['self_employed'] + $totals['employed_student'];
            $total_rate = round(100.0 * $total_employed / $totals['total_alumni'], 2);
        }

        $summary_totals = [
            'batch_year' => 'GRAND TOTAL',
            'employed' => $totals['employed'],
            'self_employed' => $totals['self_employed'],
            'unemployed' => $totals['unemployed'],
            'student' => $totals['student'],
            'employed_student' => $totals['employed_student'],
            'total_alumni' => $totals['total_alumni'],
            'employment_rate' => $total_rate . '%',
        ];

        // Output totals cells
        for ($i = 0; $i < count($config['data_keys']); $i++) {
            $key = $config['data_keys'][$i];
            $value = $summary_totals[$key];
            $align = $config['align'][$i];
            $width = $config['widths'][$i];

            $pdf->Cell($width, 7, $value, 1, 0, $align, 1);
        }
        $pdf->Ln();
    }

    // Output the PDF
    $pdf_filename = strtoupper($report_type) . '_Alumni_Report_' . date('Ymd_His') . '.pdf';
    $pdf->Output($pdf_filename, 'I');
    exit;
}
?>