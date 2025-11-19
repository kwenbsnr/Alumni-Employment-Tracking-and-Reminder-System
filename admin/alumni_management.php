<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter for global search
$search = $_GET['search'] ?? '';

// Fetch distinct batch years with total counts
$batchQuery = "SELECT 
                year_graduated,
                COUNT(*) as total_count
                FROM alumni_profile 
                WHERE year_graduated IS NOT NULL 
                GROUP BY year_graduated 
                ORDER BY year_graduated DESC";
$batchResult = $conn->query($batchQuery);

ob_start();
?>

<div class="space-y-6">
    <!-- Global Search Bar -->
    <div class="bg-white p-4 rounded-xl shadow-lg">
        <h3 class="text-lg font-bold text-gray-800 mb-3">Search Alumni Across All Batches</h3>
        <form method="GET" action="" class="flex gap-3">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500" 
                        placeholder="Search by alumni name...">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="alumni_management.php" class="bg-gray-500 text-white px-5 py-2 rounded-lg hover:bg-gray-600 transition-colors whitespace-nowrap">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Batch Cards - 4 Column Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php 
        $filteredBatchResult = $batchResult;
        if (!empty($search)) {
            // If search is active, filter batches that have matching alumni
            $searchQuery = "SELECT DISTINCT year_graduated 
                            FROM alumni_profile 
                            WHERE year_graduated IS NOT NULL 
                            AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)
                            ORDER BY year_graduated DESC";
            $searchStmt = $conn->prepare($searchQuery);
            $searchTerm = "%$search%";
            $searchStmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
            $searchStmt->execute();
            $filteredBatchResult = $searchStmt->get_result();
            
            // Store original batch data for display
            $batchResult->data_seek(0);
            $batchData = [];
            while ($batch = $batchResult->fetch_assoc()) {
                $batchData[$batch['year_graduated']] = $batch;
            }
        }
        
        $displayResult = !empty($search) ? $filteredBatchResult : $batchResult;
        
        while ($batch = $displayResult->fetch_assoc()): 
            $batch_year = $batch['year_graduated'];
            $batch_stats = !empty($search) ? $batchData[$batch_year] : $batch;
        ?>
            <a href="batch_alumni.php?batch=<?php echo $batch_year; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                class="bg-gradient-to-br from-amber-50 to-white p-4 rounded-xl shadow-md hover:shadow-lg border-2 border-amber-200 transform hover:scale-105 hover:border-amber-400 transition-all duration-200 group cursor-pointer">
                <div class="text-center">
                    <!-- Folder Icon -->
                    <div class="mb-3">
                        <div class="inline-flex items-center justify-center w-14 h-14 bg-amber-100 text-[#76520e] rounded-lg mb-2 group-hover:bg-amber-200 transition-colors">
                            <i class="fas fa-folder text-4x2"></i>
                        </div>
                    </div>
                    
                    <!-- Batch Label and Year -->
                    <div class="space-y-1 mb-3">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Batch</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $batch_year; ?></p>
                    </div>
                    
                    <!-- Alumni Count -->
                    <div class="bg-white rounded-lg p-2 border border-gray-200 group-hover:border-amber-300 transition-colors mb-3">
                        <p class="text-lg font-bold text-[#ffaa00]"><?php echo $batch_stats['total_count']; ?></p>
                        <p class="text-xs text-gray-600 font-medium">Alumni Records</p>
                    </div>
                    
                    <!-- View Button -->
                    <div class="mt-2">
                        <div class="bg-[#372b2b] text-white py-2 px-3 rounded-lg text-sm font-medium group-hover:bg-[#e69900] transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-eye text-xs"></i>
                            <span>View Files</span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Empty State -->
    <?php if ($displayResult->num_rows === 0): ?>
    <div class="bg-white p-8 rounded-xl shadow-lg text-center">
        <i class="fas fa-folder-open text-5xl text-gray-300 mb-3"></i>
        <h3 class="text-lg font-bold text-gray-600 mb-2">
            <?php echo !empty($search) ? 'No Batches Found' : 'No Alumni Batches Found'; ?>
        </h3>
        <p class="text-gray-500 text-sm">
            <?php echo !empty($search) 
                ? 'No batches found matching your search criteria.' 
                : 'There are no graduation batches with alumni records yet.'; ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>