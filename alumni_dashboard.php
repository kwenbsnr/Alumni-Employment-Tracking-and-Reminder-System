<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");
$page_title = "Dashboard";

$user_id = $_SESSION["user_id"];

// Fetch profile
$stmt = $conn->prepare("SELECT employment_status FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: ['employment_status' => 'Not Set'];

// Fetch latest doc status
$docSql = "SELECT document_status 
           FROM alumni_documents 
           WHERE alumni_id = (SELECT alumni_id FROM alumni_profile WHERE user_id = ?)
           ORDER BY uploaded_at DESC 
           LIMIT 1";
$stmt2 = $conn->prepare($docSql);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$docResult = $stmt2->get_result();
$document = $docResult->fetch_assoc() ?: ['document_status' => 'No Document'];

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- Employment Status -->
    <div class="stats-card p-6 rounded-xl shadow-lg flex flex-col items-start">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                <i class="fas fa-briefcase text-green-700 text-lg" aria-hidden="true"></i>
            </div>
            <h3 class="text-sm font-semibold text-gray-700 uppercase">Employment Status</h3>
        </div>
        <p class="text-2xl font-bold text-green-700">
            <?php echo htmlspecialchars($profile['employment_status']); ?>
        </p>
    </div>

    <!-- Document Status -->
    <div class="stats-card p-6 rounded-xl shadow-lg flex flex-col items-start">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-700 text-lg" aria-hidden="true"></i>
            </div>
            <h3 class="text-sm font-semibold text-gray-700 uppercase">Latest Document Status</h3>
        </div>
        <?php
        $status_styles = [
            "Approved" => ["bg" => "bg-green-100", "text" => "text-green-700"],
            "Pending" => ["bg" => "bg-yellow-100", "text" => "text-yellow-700"],
            "Rejected" => ["bg" => "bg-red-100", "text" => "text-red-700"],
            "No Document" => ["bg" => "bg-gray-100", "text" => "text-gray-700"]
        ];
        $style = $status_styles[$document['document_status']] ?? ["bg" => "bg-gray-100", "text" => "text-gray-700"];
        ?>
        <span class="inline-block <?php echo $style['bg'] . ' ' . $style['text']; ?> text-xs font-semibold px-2 py-1 rounded-full mb-2">
            <?php echo htmlspecialchars($document['document_status']); ?>
        </span>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>