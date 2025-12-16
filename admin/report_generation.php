<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Load TCPDF library
require_once '../tcpdf/tcpdf.php';

$page_title = "Generate Alumni Report";
$active_page = "report_generation";

// ====================== REPORT GENERATION LOGIC ======================
if (isset($_POST['generate_report'])) {
    $selected_batches = $_POST['selected_batches'] ?? [];
    $report_type      = $_POST['report_type'] ?? 'summary';

    if (!empty($selected_batches)) {
        // The function will handle the PDF output and exit
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
if ($batchResult && $batchResult->num_rows > 0) {
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
                            <input type="checkbox" name="selected_batches[]" value="<?= htmlspecialchars($batch['batch_year']) ?>" checked class="h-5 w-5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="flex-1 text-lg">Batch <?= htmlspecialchars($batch['batch_year']) ?></span>
                            <span class="text-gray-500 text-sm">(<?= htmlspecialchars($batch['total_count']) ?> records)</span>
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

    // Prevent form submission if no batches are selected
    reportForm.addEventListener('submit', (e) => {
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        if (checkedCount === 0) {
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
// ====================== generateAlumniReport Function ======================
function generateAlumniReport($selected_batches, $report_type, $conn) {
    // Clear any output buffer to ensure only the PDF binary is sent
    if (ob_get_length()) ob_clean();

    // Validate and sanitize batches
    $selected_batches = array_map('intval', $selected_batches);
    $selected_batches = array_filter($selected_batches, function($batch) {
        return $batch > 0;
    });
    
    $count_batches = count($selected_batches);
    if ($count_batches === 0) {
        $_SESSION['error_message'] = "Please select at least one valid batch.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Build placeholders
    $placeholders = implode(',', array_fill(0, $count_batches, '?'));

    // Different queries for different report types
    if ($report_type === 'summary') {
        $sql = "SELECT 
            u.batch_year,
            COUNT(*) as total_alumni,
            SUM(CASE 
                WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) IN ('employed', 'employed & student', 'employed and student') 
                THEN 1 ELSE 0 
            END) as employed,
            SUM(CASE 
                WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) = 'self-employed' 
                THEN 1 ELSE 0 
            END) as self_employed,
            SUM(CASE 
                WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) = 'unemployed' 
                THEN 1 ELSE 0 
            END) as unemployed,
            SUM(CASE 
                WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) = 'student' 
                THEN 1 ELSE 0 
            END) as student,
            SUM(CASE 
                WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) IN ('employed & student','employed and student') 
                THEN 1 ELSE 0 
            END) as employed_student,
            CASE 
                WHEN COUNT(*) > 0 THEN 
                    ROUND(100.0 * (
                        SUM(CASE 
                            WHEN LOWER(TRIM(COALESCE(ap.employment_status, ''))) IN (
                                'employed', 
                                'self-employed', 
                                'employed & student',
                                'employed and student'
                            ) THEN 1 ELSE 0 
                        END)
                    ) / COUNT(*), 2)
                ELSE 0 
            END as employment_rate
        FROM users u
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
        WHERE u.role = 'alumni' 
        AND u.batch_year IN ($placeholders)
        GROUP BY u.batch_year
        ORDER BY u.batch_year DESC";
    } else { // detailed
        $sql = "SELECT 
            u.batch_year,
            CONCAT(
                COALESCE(u.first_name, ''),
                CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' 
                     THEN CONCAT(' ', u.middle_name) ELSE '' END,
                ' ',
                COALESCE(u.last_name, ''),
                CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' 
                     THEN CONCAT(' ', u.suffix) ELSE '' END
            ) as name,
            COALESCE(u.email, 'N/A') as email,
            COALESCE(
                CASE 
                    WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' THEN 'Self-Employed'
                    ELSE ap.employment_status
                END,
                'Not Updated'
            ) as employment_status,
            COALESCE(
                CASE 
                    WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' 
                    THEN COALESCE(ei.business_type, 'Self-Employed')
                    ELSE jt.title
                END,
                '-'
            ) as current_job,
            COALESCE(
                CASE 
                    WHEN LOWER(TRIM(ap.employment_status)) = 'self-employed' 
                    THEN COALESCE(ei.business_type, 'Personal Business')
                    ELSE ei.company_name
                END,
                '-'
            ) as current_employer
        FROM users u
        LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
        LEFT JOIN employment_info ei ON u.user_id = ei.user_id 
        LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
        WHERE u.role = 'alumni'
        AND u.batch_year IN ($placeholders)
        ORDER BY u.batch_year DESC, u.last_name, u.first_name";
    }

    // Prepare statement
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error_message'] = "Failed to prepare report query.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Build bind_param parameters
    $types = str_repeat('i', $count_batches);
    $bind_params = array_merge([$types], $selected_batches);
    
    // Create references for bind_param
    $refs = [];
    foreach ($bind_params as $key => $value) {
        $refs[$key] = &$bind_params[$key];
    }
    
    // Call bind_param with the references
    call_user_func_array([$stmt, 'bind_param'], $refs);

    // Execute
    if (!$stmt->execute()) {
        error_log("Report generation failed: " . $stmt->error);
        $_SESSION['error_message'] = "Failed to generate report: " . $stmt->error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $result = $stmt->get_result();
    
    // Check if we have data
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "No alumni data found for the selected batches.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // ==================================================================
    // PDF GENERATION
    // ==================================================================
    // 'L' for Landscape, 'mm' for unit, 'A4' for format
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
    if ($report_type === 'summary') {
        $headers = ['Batch Year', 'Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed/Student', 'Total', 'Employment Rate'];
        $widths = [30, 30, 35, 30, 30, 40, 20, 30];
        $data_keys = ['batch_year', 'employed', 'self_employed', 'unemployed', 'student', 'employed_student', 'total_alumni', 'employment_rate'];
        $align = ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];
    } else {
        $headers = ['Batch Year', 'Name', 'Email', 'Employment Status', 'Current Job', 'Current Employer'];
        $widths = [25, 50, 60, 40, 40, 60];
        $data_keys = ['batch_year', 'name', 'email', 'employment_status', 'current_job', 'current_employer'];
        $align = ['C', 'L', 'L', 'L', 'L', 'L'];
    }

    // Draw Table Header
    $pdf->SetFillColor(230, 240, 255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 9);

    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, $align[$i], 1);
    }
    $pdf->Ln();

    // Draw Table Body
    $pdf->SetFillColor(255);
    $pdf->SetFont('helvetica', '', 9);
    
    // Initialize totals for summary report
    if ($report_type === 'summary') {
        $totals = [
            'employed' => 0,
            'self_employed' => 0,
            'unemployed' => 0,
            'student' => 0,
            'employed_student' => 0,
            'total_alumni' => 0
        ];
    }
    
    foreach ($data as $row) {
        // Check for page break
        if ($pdf->GetY() + 8 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            // Redraw header
            $pdf->SetFillColor(230, 240, 255);
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, $align[$i], 1);
            }
            $pdf->Ln();
            $pdf->SetFillColor(255);
            $pdf->SetFont('helvetica', '', 9);
        }

        // Output row data
        for ($i = 0; $i < count($data_keys); $i++) {
            $key = $data_keys[$i];
            $value = $row[$key] ?? '';
            
            // Format values for summary report
            if ($report_type === 'summary') {
                // Update totals
                if (in_array($key, ['employed', 'self_employed', 'unemployed', 'student', 'employed_student', 'total_alumni'])) {
                    $totals[$key] += (int)$value;
                }
                
                // Format employment rate
                if ($key === 'employment_rate') {
                    $value = is_numeric($value) ? number_format($value, 2) . '%' : $value;
                }
            }
            
            // Truncate long text for detailed report
            if ($report_type === 'detailed' && strlen($value) > 50) {
                $value = substr($value, 0, 47) . '...';
            }
            
            $pdf->Cell($widths[$i], 6, $value, 1, 0, $align[$i], 1);
        }
        $pdf->Ln();
    }
    
    // Draw Totals Row for Summary Report
    if ($report_type === 'summary' && !empty($data)) {
        // Calculate overall employment rate
        $total_employed = $totals['employed'] + $totals['self_employed'] + $totals['employed_student'];
        $overall_rate = ($totals['total_alumni'] > 0) ? 
            round(100.0 * $total_employed / $totals['total_alumni'], 2) : 0;
        
        // Check for page break before totals
        if ($pdf->GetY() + 8 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
        }
        
        $pdf->SetFillColor(200, 215, 230);
        $pdf->SetFont('helvetica', 'B', 9);
        
        $total_data = [
            'GRAND TOTAL',
            $totals['employed'],
            $totals['self_employed'],
            $totals['unemployed'],
            $totals['student'],
            $totals['employed_student'],
            $totals['total_alumni'],
            number_format($overall_rate, 2) . '%'
        ];
        
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($widths[$i], 7, $total_data[$i] ?? '', 1, 0, 'C', 1);
        }
        $pdf->Ln();
    }

    // Output the PDF
    $pdf_filename = strtoupper($report_type) . '_Alumni_Report_' . date('Ymd_His') . '.pdf';
    // 'I' (Inline) is used, and the form's target="_blank" handles the new tab.
    $pdf->Output($pdf_filename, 'I'); 
    exit;
}
?>