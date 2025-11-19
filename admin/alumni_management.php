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
    <div class="bg-gradient-to-br from-blue-50 to-white p-4 rounded-xl shadow-lg border-2 border-blue-200">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400 text-sm"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        class="w-full pl-10 pr-4 py-2 text-sm border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 placeholder-gray-400"
                        placeholder="Search alumni by name...">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 whitespace-nowrap font-medium text-sm shadow-sm hover:shadow flex items-center gap-2 flex-1 sm:flex-none justify-center">
                    <i class="fas fa-search text-xs"></i>
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="alumni_management.php" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 whitespace-nowrap font-medium text-sm shadow-sm hover:shadow flex items-center gap-2 flex-1 sm:flex-none justify-center">
                        <i class="fas fa-times text-xs"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (!empty($search)): ?>
        <div class="mt-3 p-2 bg-blue-100 border border-blue-300 rounded-lg">
            <div class="flex items-center gap-2 text-blue-800 text-xs">
                <i class="fas fa-info-circle"></i>
                <span class="font-medium">Showing results for:</span>
                <span class="font-bold">"<?php echo htmlspecialchars($search); ?>"</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
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
                class="bg-gradient-to-br from-amber-50 to-white p-6 rounded-xl shadow-lg border-2 border-amber-200 hover:shadow-xl hover:border-amber-400 transform hover:scale-105 transition-all duration-300 group cursor-pointer relative overflow-hidden">
                
                <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-bl from-amber-200/20 to-transparent rounded-bl-full"></div>
                
                <div class="text-center relative z-10">
                    <div class="mb-4">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-amber-100 to-amber-200 text-amber-700 rounded-2xl mb-3 group-hover:from-amber-200 group-hover:to-amber-300 transition-all duration-300 shadow-inner border border-amber-300/50">
                            <i class="fas fa-folder-open text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Graduation Batch</p>
                        <p class="text-2xl font-bold text-gray-800 bg-gradient-to-r from-gray-800 to-gray-900 bg-clip-text text-transparent"><?php echo $batch_year; ?></p>
                    </div>
                    
                    <div class="bg-white rounded-xl p-4 border-2 border-amber-100 group-hover:border-amber-200 transition-colors duration-300 shadow-sm mb-4">
                        <div class="flex items-center justify-center gap-2">
                            <i class="fas fa-users text-amber-500 text-lg"></i>
                            <p class="text-2xl font-bold text-amber-600"><?php echo $batch_stats['total_count']; ?></p>
                        </div>
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mt-1">Alumni Records</p>
                    </div>
                    
                    <div class="mt-2">
                        <div class="bg-gradient-to-r from-gray-800 to-gray-900 text-white py-3 px-4 rounded-xl text-sm font-semibold group-hover:from-amber-500 group-hover:to-amber-600 transition-all duration-300 shadow-md group-hover:shadow-lg inline-flex items-center gap-3 transform group-hover:-translate-y-0.5">
                            <i class="fas fa-eye text-xs"></i>
                            <span>View Records</span>
                            <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform duration-200"></i>
                        </div>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <?php if ($displayResult->num_rows === 0): ?>
    <div class="bg-gradient-to-br from-amber-50 to-white p-12 rounded-xl shadow-lg border-2 border-amber-200 text-center">
        <div class="max-w-md mx-auto">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-amber-100 text-amber-600 rounded-2xl mb-6 shadow-inner border border-amber-300/50">
                <i class="fas fa-folder-open text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-700 mb-3">
                <?php echo !empty($search) ? 'No Matching Batches Found' : 'No Alumni Batches Available'; ?>
            </h3>
            <p class="text-gray-500 text-lg mb-6">
                <?php echo !empty($search) 
                    ? 'No graduation batches found matching your search criteria.' 
                    : 'There are no graduation batches with alumni records in the system yet.'; ?>
            </p>
            <?php if (!empty($search)): ?>
                <a href="alumni_management.php" class="bg-gradient-to-r from-amber-500 to-amber-600 text-white px-8 py-3 rounded-xl hover:from-amber-600 hover:to-amber-700 transition-all duration-200 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 inline-flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i>
                    View All Batches
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>