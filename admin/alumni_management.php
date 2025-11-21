<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter
$search = $_GET['search'] ?? '';

// Handle report generation
if (isset($_POST['generate_report'])) {
    $selected_batches = $_POST['selected_batches'] ?? [];
    $report_type      = $_POST['report_type'] ?? 'summary';
    $format           = $_POST['format'] ?? 'csv';

    if (!empty($selected_batches)) {
        generateAlumniReport($selected_batches, $report_type, $format, $conn);
    } else {
        $_SESSION['error_message'] = "Please select at least one batch to generate a report.";
    }
}

// Fetch distinct batch years with total counts
$batchQuery = "SELECT 
                year_graduated,
                COUNT(*) as total_count
               FROM alumni_profile 
               WHERE year_graduated IS NOT NULL 
               GROUP BY year_graduated 
               ORDER BY year_graduated DESC";
$batchResult = $conn->query($batchQuery);

// Store batches for report form
$all_batches = [];
if ($batchResult) {
    $batchResult->data_seek(0);
    while ($row = $batchResult->fetch_assoc()) {
        $all_batches[] = $row;
    }
    $batchResult->data_seek(0); // reset for later use
}

ob_start();
?>

<div class="space-y-6">

    <!-- Search + Generate Report Button -->
    <div class="bg-gradient-to-br from-blue-50 to-white p-4 rounded-xl shadow-lg border-2 border-blue-200">
        <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
            <form method="GET" action="" class="flex-1 flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Search alumni by name...">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="alumni_management.php" class="bg-gray-600 text-white px-5 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <button id="toggleReportForm" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-2 rounded-lg hover:from-green-600 hover:to-green-700 flex items-center gap-2">
                <i class="fas fa-file-export"></i> Generate Report
            </button>
        </div>

        <?php if (!empty($search)): ?>
            <div class="mt-3 p-3 bg-blue-100 border border-blue-300 rounded-lg text-sm text-blue-800">
                <i class="fas fa-info-circle"></i>
                Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Inline Report Form (hidden by default) -->
    <div id="reportFormContainer" class="hidden bg-gradient-to-br from-green-50 to-white p-6 rounded-xl shadow-lg border-2 border-green-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-5 flex items-center gap-3">
            <i class="fas fa-file-export text-green-600"></i> Customize Alumni Report
        </h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-300 rounded-lg text-red-700">
                <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Batch Selection -->
                <div class="bg-white p-5 rounded-xl border-2 border-gray-200">
                    <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                        <i class="fas fa-layer-group text-blue-600"></i> Select Batches
                    </h3>
                    <div class="max-h-64 overflow-y-auto space-y-2">
                        <?php foreach ($all_batches as $batch): ?>
                            <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="selected_batches[]" value="<?= $batch['year_graduated'] ?>" checked
                                       class="h-4 w-4 text-green-600 rounded focus:ring-green-500">
                                <span class="flex-1">Batch <?= $batch['year_graduated'] ?></span>
                                <span class="text-gray-500 text-sm">(<?= $batch['total_count'] ?> records)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button type="button" id="selectAll" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200">Select All</button>
                        <button type="button" id="deselectAll" class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200">Deselect All</button>
                    </div>
                </div>

                <!-- Report Options -->
                <div class="bg-white p-5 rounded-xl border-2 border-gray-200 space-y-5">
                    <div>
                        <label class="block font-medium mb-2">Report Type</label>
                        <select name="report_type" class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Alumni List</option>
                            <option value="contact">Contact Information</option>
                            <option value="employment">Employment Status</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium mb-2">Export Format</label>
                        <div class="flex gap-6">
                            <label class="flex items-center"><input type="radio" name="format" value="csv" checked class="mr-2"> CSV</label>
                            <label class="flex items-center"><input type="radio" name="format" value="excel" class="mr-2"> Excel</label>
                            <label class="flex items-center"><input type="radio" name="format" value="pdf" class="mr-2"> PDF</label>
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="include_charts" checked class="h-4 w-4 text-green-600">
                            <span>Include Charts & Statistics (PDF only)</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-4 border-t">
                <button type="button" id="cancelReport" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" name="generate_report" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <i class="fas fa-download"></i> Generate & Download
                </button>
            </div>
        </form>
    </div>

    
<!-- Batch Cards Grid with Title + ALL ALUMNI CARD -->
<div class="space-y-4">
    <div class="flex items-center gap-3 px-2">
        <i class="fas fa-folder-open text-2xl text-amber-600"></i>
        <h2 class="text-xl font-bold text-gray-800">Alumni Records Per Batch</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- ====== ALL ALUMNI CARD (NEW) ====== -->
        <?php
        // Total alumni count (all batches)
        $totalQuery = "SELECT COUNT(*) as total FROM alumni_profile";
        $totalRes   = $conn->query($totalQuery);
        $totalAll   = $totalRes->fetch_assoc()['total'];
        ?>
        <a href="all_alumni.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>"
           class="bg-gradient-to-br from-purple-50 to-white p-6 rounded-xl shadow-lg border-2 border-purple-300 hover:shadow-xl hover:border-purple-500 transform hover:scale-105 transition-all duration-300 group text-center">
            <i class="fas fa-users text-4xl text-purple-600 mb-4"></i>
            <p class="text-xs uppercase tracking-wider text-gray-500">All Batches Combined</p>
            <p class="text-2xl font-bold text-gray-800">All Alumni</p>
            <div class="mt-4 bg-white rounded-xl p-4 border border-purple-100">
                <p class="text-3xl font-bold text-purple-600"><?= $totalAll ?></p>
                <p class="text-xs uppercase text-gray-600">Total Records</p>
            </div>
            <div class="mt-4 bg-purple-700 text-white py-2 px-4 rounded-lg text-sm font-medium group-hover:bg-purple-600 transition">
                View All Records →
            </div>
        </a>

        <!-- Existing Batch Cards -->
        <?php
        $displayResult = $batchResult;
        if (!empty($search)) {
            $stmt = $conn->prepare("SELECT DISTINCT year_graduated FROM alumni_profile WHERE year_graduated IS NOT NULL AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)");
            $term = "%$search%";
            $stmt->bind_param('sss', $term, $term, $term);
            $stmt->execute();
            $displayResult = $stmt->get_result();
        }

        if ($displayResult && $displayResult->num_rows > 0):
            while ($batch = $displayResult->fetch_assoc()):
                $year = $batch['year_graduated'];
                $count = $all_batches[array_search($year, array_column($all_batches, 'year_graduated'))]['total_count'] ?? 0;
        ?>
                <a href="batch_alumni.php?batch=<?= $year ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                   class="bg-gradient-to-br from-amber-50 to-white p-6 rounded-xl shadow-lg border-2 border-amber-200 hover:shadow-xl hover:border-amber-400 transform hover:scale-105 transition-all duration-300 group text-center">
                    <i class="fas fa-folder-open text-4xl text-amber-600 mb-4"></i>
                    <p class="text-xs uppercase tracking-wider text-gray-500">Graduation Batch</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $year ?></p>
                    <div class="mt-4 bg-white rounded-xl p-4 border border-amber-100">
                        <p class="text-3xl font-bold text-amber-600"><?= $count ?></p>
                        <p class="text-xs uppercase text-gray-600">Alumni Records</p>
                    </div>
                    <div class="mt-4 bg-gray-800 text-white py-2 px-4 rounded-lg text-sm font-medium group-hover:bg-amber-600 transition">
                        View Records →
                    </div>
                </a>
        <?php
            endwhile;
        else:
        ?>
            <div class="col-span-full text-center py-12 bg-amber-50 rounded-xl border-2 border-amber-200">
                <i class="fas fa-folder-open text-6xl text-amber-400 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-700">No Alumni found.</h3>
                <p class="text-gray-600 mt-2">
                    <?= !empty($search) ? 'No batches match your search.' : 'There are no alumni records yet.' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleReportForm');
    const container = document.getElementById('reportFormContainer');
    const cancelBtn = document.getElementById('cancelReport');
    const selectAll = document.getElementById('selectAll');
    const deselectAll = document.getElementById('deselectAll');

    toggleBtn.addEventListener('click', () => container.classList.toggle('hidden'));
    cancelBtn.addEventListener('click', () => container.classList.add('hidden'));

    selectAll.addEventListener('click', () => document.querySelectorAll('input[name="selected_batches[]"]').forEach(cb => cb.checked = true));
    deselectAll.addEventListener('click', () => document.querySelectorAll('input[name="selected_batches[]"]').forEach(cb => cb.checked = false));
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>

<?php
// ====================== REPORT GENERATION FUNCTION ======================
function generateAlumniReport($selected_batches, $report_type, $format, $conn) {
    // Sanitize batch list
    $placeholders = str_repeat('?,', count($selected_batches) - 1) . '?';
    $types = str_repeat('i', count($selected_batches));

    // Base queries per report type
    $queries = [
        'summary' => "SELECT year_graduated,
                             COUNT(*) as total,
                             SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed,
                             SUM(CASE WHEN employment_status = 'Unemployed' THEN 1 ELSE 0 END) as unemployed,
                             SUM(CASE WHEN employment_status = 'Self-employed' THEN 1 ELSE 0 END) as self_employed
                      FROM alumni_profile WHERE year_graduated IN ($placeholders) GROUP BY year_graduated",
        'detailed' => "SELECT first_name, middle_name, last_name, year_graduated, email, phone, current_job, employment_status, company
                       FROM alumni_profile WHERE year_graduated IN ($placeholders) ORDER BY year_graduated DESC, last_name",
        'contact' => "SELECT first_name, middle_name, last_name, year_graduated, email, phone, address
                      FROM alumni_profile WHERE year_graduated IN ($placeholders) ORDER BY year_graduated DESC, last_name",
        'employment' => "SELECT first_name, middle_name, last_name, year_graduated, current_job, employment_status, company, industry
                         FROM alumni_profile WHERE year_graduated IN ($placeholders) ORDER BY year_graduated DESC, last_name"
    ];

    $query = $queries[$report_type] ?? $queries['summary'];
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$selected_batches);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "No data found for the selected batches.";
        return;
    }

    // Currently only CSV is fully implemented
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alumni_report_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers based on report type
        $headers = [
            'summary' => ['Batch Year', 'Total', 'Employed', 'Unemployed', 'Self-Employed'],
            'detailed' => ['First Name', 'Middle Name', 'Last Name', 'Batch', 'Email', 'Phone', 'Job', 'Status', 'Company'],
            'contact' => ['First Name', 'Middle Name', 'Last Name', 'Batch', 'Email', 'Phone', 'Address'],
            'employment' => ['First Name', 'Middle Name', 'Last Name', 'Batch', 'Current Job', 'Status', 'Company', 'Industry']
        ];

        fputcsv($output, $headers[$report_type]);

        while ($row = $result->fetch_assoc()) {
            // Flatten and reorder for CSV
            fputcsv($output, array_values($row));
        }
        fclose($output);
        exit();
    }

    // Future: PDF / Excel support
    if ($format === 'pdf') {
        $_SESSION['error_message'] = "PDF generation is not yet implemented. CSV downloaded instead.";
        // You can integrate TCPDF or Dompdf here later
    }
    if ($format === 'excel') {
        $_SESSION['error_message'] = "Excel generation is not yet implemented. CSV downloaded instead.";
        // You can integrate PhpSpreadsheet here later
    }
}
?>