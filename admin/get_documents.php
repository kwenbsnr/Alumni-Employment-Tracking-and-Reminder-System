<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id) {
    header("Location: alumni_management.php");
    exit();
}

// Get alumni name for the header
$alumniQuery = "
    SELECT 
        CONCAT(
            u.first_name,
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as name
    FROM users u 
    WHERE u.user_id = ?
";
$alumniStmt = $conn->prepare($alumniQuery);
$alumniStmt->bind_param('i', $user_id);
$alumniStmt->execute();
$alumniResult = $alumniStmt->get_result();
$alumni = $alumniResult->fetch_assoc();

$page_title = "Alumni Documents - " . ($alumni ? htmlspecialchars($alumni['name']) : 'Unknown');
$active_page = "alumni_management";

// Fetch documents - CORRECTED QUERY: document_status column exists in schema
$documentsQuery = "
    SELECT 
        doc_id,
        document_type,
        file_path,
        document_status, 
        rejection_reason,
        rejected_at
    FROM alumni_documents 
    WHERE user_id = ?
    ORDER BY 
        CASE document_type
            WHEN 'COR' THEN 1
            WHEN 'COE' THEN 2
            WHEN 'B_CERT' THEN 3
            ELSE 4
        END
";
$documentsStmt = $conn->prepare($documentsQuery);
$documentsStmt->bind_param('i', $user_id);
$documentsStmt->execute();
$documentsResult = $documentsStmt->get_result();

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="javascript:history.back()" class="bg-white p-3 rounded-lg shadow hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left text-gray-600"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Alumni Documents</h1>
                <p class="text-gray-600">
                    <?php echo $alumni ? htmlspecialchars($alumni['name']) : 'Unknown Alumni'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Documents Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($documentsResult->num_rows > 0): ?>
            <?php while ($document = $documentsResult->fetch_assoc()): ?>
                <?php
                $document_types = [
                    'COR' => ['name' => 'Certificate of Registration', 'icon' => 'fa-file-certificate', 'color' => 'blue'],
                    'COE' => ['name' => 'Certificate of Employment', 'icon' => 'fa-briefcase', 'color' => 'green'],
                    'B_CERT' => ['name' => 'Business Certificate', 'icon' => 'fa-certificate', 'color' => 'purple']
                ];
                
                $doc_type = $document_types[$document['document_type']] ?? ['name' => $document['document_type'], 'icon' => 'fa-file', 'color' => 'gray'];
                
                // Status badge color
                $status_colors = [
                    'Approved' => 'bg-green-100 text-green-800',
                    'Pending'  => 'bg-yellow-100 text-yellow-800',
                    'Rejected' => 'bg-red-100 text-red-800'
                ];
                $status_color = $status_colors[$document['document_status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="p-3 rounded-full bg-<?php echo $doc_type['color']; ?>-100 text-<?php echo $doc_type['color']; ?>-500">
                                    <i class="fas <?php echo $doc_type['icon']; ?> text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800"><?php echo $doc_type['name']; ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo strtoupper($document['document_type']); ?></p>
                                    <span class="text-xs <?php echo $status_color; ?> px-2 py-0.5 rounded-full">
                                        <?php echo htmlspecialchars($document['document_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex space-x-2">
                                <a href="../<?php echo $document['file_path']; ?>" 
                                   target="_blank"
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                                    <i class="fas fa-eye mr-2"></i> View Document
                                </a>
                                <a href="../<?php echo $document['file_path']; ?>" 
                                   download
                                   class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors flex items-center justify-center">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                            
                            <?php if ($document['document_status'] === 'Rejected' && !empty($document['rejection_reason'])): ?>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                    <p class="text-sm text-red-700">
                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($document['rejection_reason']); ?>
                                        <?php if (!empty($document['rejected_at'])): ?>
                                            <br><small>Rejected on: <?php echo date('F j, Y', strtotime($document['rejected_at'])); ?></small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full">
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-600 mb-2">No Documents Found</h3>
                    <p class="text-gray-500">This alumni hasn't submitted any documents yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="flex space-x-4">
            <a href="batch_alumni.php?batch=<?php echo $_GET['batch'] ?? ''; ?>" 
               class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Back to Alumni List
            </a>
            <a href="alumni_management.php" 
               class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                <i class="fas fa-users mr-2"></i> All Batches
            </a>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>