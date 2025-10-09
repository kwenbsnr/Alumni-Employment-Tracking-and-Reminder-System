<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Edit Alumni Profile";

$alumni_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch alumni profile
$stmt = $conn->prepare("
    SELECT ap.*, u.email 
    FROM alumni_profile ap 
    LEFT JOIN users u ON ap.user_id = u.id
    WHERE ap.alumni_id = ?
");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
if (!$profile) {
    header("Location: alumni_management.php?error=invalid_alumni");
    exit;
}

// Fetch employment info
$ei_stmt = $conn->prepare("
    SELECT ei.job_title_id, jt.title AS job_title, ei.company_name, ei.salary_range,
           ei.school_name, ei.school_address, ei.degree_pursued
    FROM employment_info ei
    LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
    WHERE ei.alumni_id = ?
");
$ei_stmt->bind_param("i", $alumni_id);
$ei_stmt->execute();
$ei_result = $ei_stmt->get_result();
$employment = $ei_result->fetch_assoc() ?: [];

// Fetch documents
$doc_stmt = $conn->prepare("SELECT * FROM alumni_documents WHERE alumni_id = ?");
$doc_stmt->bind_param("i", $alumni_id);
$doc_stmt->execute();
$docs_result = $doc_stmt->get_result();
$docs = [];
while ($row = $docs_result->fetch_assoc()) {
    $docs[$row['document_type']] = $row;
}

ob_start();
?>

<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 p-4 rounded mb-4">Profile updated successfully!</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="bg-red-100 p-4 rounded mb-4">Error updating profile: <?php echo htmlspecialchars($_SESSION['error'] ?? 'Unknown'); ?></div>
<?php endif; ?>

<div class="bg-white p-6 rounded-xl shadow-lg">
    <h2 class="text-2xl font-bold mb-6">Edit Alumni Profile</h2>
    <form action="update_alumni.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="alumni_id" value="<?php echo htmlspecialchars($alumni_id); ?>">
        
        <!-- Personal Details -->
        <div>
            <h3 class="text-lg font-semibold mb-4">Personal Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
            </div>
        </div>

        <!-- Employment Status -->
        <div>
            <h3 class="text-lg font-semibold mb-4">Employment Status</h3>
            <select id="employment_status" name="employment_status" onchange="toggleFields()" class="w-full border rounded-lg p-2">
                <option value="" <?php echo !isset($profile['employment_status']) ? 'selected' : ''; ?>>Select Status</option>
                <option value="Employed" <?php echo ($profile['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                <option value="Self-Employed" <?php echo ($profile['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                <option value="Unemployed" <?php echo ($profile['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                <option value="Student" <?php echo ($profile['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                <option value="Employed & Student" <?php echo ($profile['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
            </select>
        </div>

        <!-- Employment Details -->
        <div id="employment_details" class="grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo !in_array($profile['employment_status'] ?? '', ['Employed', 'Employed & Student']) ? 'hidden' : ''; ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">Job Title</label>
                <input type="text" name="job_title" value="<?php echo htmlspecialchars($employment['job_title'] ?? ''); ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($employment['company_name'] ?? ''); ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Salary Range</label>
                <select name="salary_range" class="w-full border rounded-lg p-2">
                    <option value="" <?php echo !isset($employment['salary_range']) ? 'selected' : ''; ?>>Select Range</option>
                    <option value="<5000" <?php echo ($employment['salary_range'] ?? '') === '<5000' ? 'selected' : ''; ?>>Less than 5,000</option>
                    <option value="5000-10000" <?php echo ($employment['salary_range'] ?? '') === '5000-10000' ? 'selected' : ''; ?>>5,000 - 10,000</option>
                    <option value="10000-20000" <?php echo ($employment['salary_range'] ?? '') === '10000-20000' ? 'selected' : ''; ?>>10,000 - 20,000</option>
                    <option value="20000-50000" <?php echo ($employment['salary_range'] ?? '') === '20000-50000' ? 'selected' : ''; ?>>20,000 - 50,000</option>
                    <option value=">50000" <?php echo ($employment['salary_range'] ?? '') === '>50000' ? 'selected' : ''; ?>>More than 50,000</option>
                </select>
            </div>
        </div>

        <!-- Academic Details -->
        <div id="academic_details" class="grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo !in_array($profile['employment_status'] ?? '', ['Student', 'Employed & Student']) ? 'hidden' : ''; ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">School Name</label>
                <input type="text" name="school_name" value="<?php echo htmlspecialchars($employment['school_name'] ?? ''); ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">School Address</label>
                <input type="text" name="school_address" value="<?php echo htmlspecialchars($employment['school_address'] ?? ''); ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Degree Pursued</label>
                <input type="text" name="degree_pursued" value="<?php echo htmlspecialchars($employment['degree_pursued'] ?? ''); ?>" class="w-full border rounded-lg p-2">
            </div>
        </div>

        <!-- Location Details -->
        <div>
            <h3 class="text-lg font-semibold mb-4">Location Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Year Graduated</label>
                    <select name="year_graduated" required class="w-full border rounded-lg p-2">
                        <option value="" <?php echo !isset($profile['year_graduated']) ? 'selected' : ''; ?>>Select Year</option>
                        <?php for ($year = 2000; $year <= 2025; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($profile['year_graduated'] ?? '') == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Province</label>
                    <select id="province" name="province" onchange="updateCities()" class="w-full border rounded-lg p-2">
                        <option value="" <?php echo !isset($profile['province']) ? 'selected' : ''; ?>>Select Province</option>
                        <option value="Metro Manila" <?php echo ($profile['province'] ?? '') === 'Metro Manila' ? 'selected' : ''; ?>>Metro Manila</option>
                        <option value="Cebu" <?php echo ($profile['province'] ?? '') === 'Cebu' ? 'selected' : ''; ?>>Cebu</option>
                        <option value="Davao" <?php echo ($profile['province'] ?? '') === 'Davao' ? 'selected' : ''; ?>>Davao</option>
                        <!-- Add more provinces as needed -->
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">City/Municipality</label>
                    <select id="city" name="city" class="w-full border rounded-lg p-2">
                        <option value="" <?php echo !isset($profile['city']) ? 'selected' : ''; ?>>Select City</option>
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Barangay</label>
                    <input type="text" name="barangay" value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Zip Code</label>
                    <input type="text" name="zip_code" value="<?php echo htmlspecialchars($profile['zip_code'] ?? ''); ?>" required class="w-full border rounded-lg p-2">
                </div>
            </div>
        </div>

        <!-- File Uploads -->
        <div id="coe" class="<?php echo !in_array($profile['employment_status'] ?? '', ['Employed', 'Employed & Student']) ? 'hidden' : ''; ?>">
            <label class="block text-sm font-medium text-gray-700">Certificate of Employment</label>
            <input type="file" name="coe_file" accept=".pdf" class="w-full border rounded-lg p-2">
            <?php if (isset($docs['COE'])): ?>
                <p>Uploaded: <a href="../Uploads/<?php echo htmlspecialchars($docs['COE']['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($docs['COE']['file_path']); ?></a> (<?php echo htmlspecialchars($docs['COE']['document_status']); ?><?php echo $docs['COE']['rejection_reason'] ? ', Reason: ' . htmlspecialchars($docs['COE']['rejection_reason']) : ''; ?>)</p>
            <?php endif; ?>
        </div>
        <div id="business_cert" class="<?php echo ($profile['employment_status'] ?? '') !== 'Self-Employed' ? 'hidden' : ''; ?>">
            <label class="block text-sm font-medium text-gray-700">Business Certificate</label>
            <input type="file" name="business_file" accept=".pdf" class="w-full border rounded-lg p-2">
            <?php if (isset($docs['B_CERT'])): ?>
                <p>Uploaded: <a href="../Uploads/<?php echo htmlspecialchars($docs['B_CERT']['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($docs['B_CERT']['file_path']); ?></a> (<?php echo htmlspecialchars($docs['B_CERT']['document_status']); ?><?php echo $docs['B_CERT']['rejection_reason'] ? ', Reason: ' . htmlspecialchars($docs['B_CERT']['rejection_reason']) : ''; ?>)</p>
            <?php endif; ?>
        </div>
        <div id="cor" class="<?php echo !in_array($profile['employment_status'] ?? '', ['Student', 'Employed & Student']) ? 'hidden' : ''; ?>">
            <label class="block text-sm font-medium text-gray-700">Certificate of Registration</label>
            <input type="file" name="cor_file" accept=".pdf" class="w-full border rounded-lg p-2">
            <?php if (isset($docs['COR'])): ?>
                <p>Uploaded: <a href="../Uploads/<?php echo htmlspecialchars($docs['COR']['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($docs['COR']['file_path']); ?></a> (<?php echo htmlspecialchars($docs['COR']['document_status']); ?><?php echo $docs['COR']['rejection_reason'] ? ', Reason: ' . htmlspecialchars($docs['COR']['rejection_reason']) : ''; ?>)</p>
            <?php endif; ?>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="alumni_management.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save Changes</button>
        </div>
    </form>
</div>

<script>
function toggleFields() {
    const status = document.getElementById("employment_status")?.value;
    document.getElementById("employment_details")?.classList.toggle("hidden", !["Employed", "Employed & Student"].includes(status));
    document.getElementById("academic_details")?.classList.toggle("hidden", !["Student", "Employed & Student"].includes(status));
    document.getElementById("coe")?.classList.toggle("hidden", !["Employed", "Employed & Student"].includes(status));
    document.getElementById("business_cert")?.classList.toggle("hidden", status !== "Self-Employed");
    document.getElementById("cor")?.classList.toggle("hidden", !["Student", "Employed & Student"].includes(status));
}

const cityMap = {
    "Metro Manila": ["Manila", "Quezon City", "Makati"],
    "Cebu": ["Cebu City", "Mandaue", "Lapu-Lapu"],
    "Davao": ["Davao City", "Tagum", "Digos"]
    // Add more mappings as needed
};

function updateCities() {
    const province = document.getElementById("province")?.value;
    const citySelect = document.getElementById("city");
    if (!citySelect) return;
    citySelect.innerHTML = '<option value="">Select City</option>';
    if (province && cityMap[province]) {
        cityMap[province].forEach(city => {
            const option = document.createElement("option");
            option.value = city;
            option.textContent = city;
            if (city === "<?php echo addslashes($profile['city'] ?? ''); ?>") option.selected = true;
            citySelect.appendChild(option);
        });
    }
}

document.addEventListener("DOMContentLoaded", () => {
    toggleFields();
    updateCities();
});
</script>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>