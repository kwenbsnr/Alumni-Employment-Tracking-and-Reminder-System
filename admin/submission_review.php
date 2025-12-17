<?php
// submission_review.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

$page_title = "Submission Review";
$active_page = "submission_review";

// Get all submissions with filters
$where_clause = "1=1";
$params = [];
$types = "";

// Filter by notification type
if (isset($_GET['type']) && in_array($_GET['type'], ['new_submission', 'resubmission', 'update'])) {
    $where_clause .= " AND n.notification_type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}

// Filter by employment status
if (isset($_GET['employment_status']) && !empty($_GET['employment_status'])) {
    $where_clause .= " AND n.employment_status = ?";
    $params[] = $_GET['employment_status'];
    $types .= "s";
}

// Filter by read status
if (isset($_GET['read_status']) && $_GET['read_status'] === 'unread') {
    $where_clause .= " AND n.is_read = FALSE";
} elseif (isset($_GET['read_status']) && $_GET['read_status'] === 'read') {
    $where_clause .= " AND n.is_read = TRUE";
}

// Filter by date range
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where_clause .= " AND DATE(n.submission_time) >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where_clause .= " AND DATE(n.submission_time) <= ?";
    $params[] = $_GET['date_to'];
    $types .= "s";
}

// Filter by batch year
if (isset($_GET['batch_year']) && is_numeric($_GET['batch_year'])) {
    $where_clause .= " AND n.batch_year = ?";
    $params[] = $_GET['batch_year'];
    $types .= "s";
}

// Get submissions count for pagination
$count_sql = "SELECT COUNT(*) as total FROM admin_notifications n WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_count / $per_page);

// Get submissions
$sql = "
    SELECT 
        n.notification_id,
        n.user_id,
        n.notification_type,
        n.alumni_name,
        n.employment_status,
        n.batch_year,
        n.submission_time,
        n.is_read,
        u.email,
        u.contact_number,
        ei.company_name,
        ei.business_type,
        ed.school_name,
        ed.degree_pursued
    FROM admin_notifications n
    LEFT JOIN users u ON n.user_id = u.user_id
    LEFT JOIN employment_info ei ON n.user_id = ei.user_id
    LEFT JOIN education_info ed ON n.user_id = ed.user_id
    WHERE $where_clause
    ORDER BY n.submission_time DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();

// Helper function for status colors
function getNotificationTypeColor($type) {
    switch($type) {
        case 'new_submission': return 'bg-green-100 text-green-800 border-green-200';
        case 'resubmission': return 'bg-orange-100 text-orange-800 border-orange-200';
        case 'update': return 'bg-purple-100 text-purple-800 border-purple-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getNotificationTypeIcon($type) {
    switch($type) {
        case 'new_submission': return 'fas fa-user-plus';
        case 'resubmission': return 'fas fa-redo';
        case 'update': return 'fas fa-sync-alt';
        default: return 'fas fa-bell';
    }
}

function getNotificationTypeLabel($type) {
    switch($type) {
        case 'new_submission': return 'New Submission';
        case 'resubmission': return 'Resubmission';
        case 'update': return 'Update';
        default: return 'Notification';
    }
}

function getTimeAgo($timestamp) {
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Submission Review</h2>
                <p class="text-gray-600 mt-1">Review recent alumni employment submissions</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-500">
                    Total: <span class="font-bold"><?php echo $total_count; ?></span> submissions
                </span>
                <?php if ($total_count > 0): ?>
                    <button id="markAllReadBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        Mark All as Read
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Submission Type</label>
                <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All Types</option>
                    <option value="new_submission" <?php echo ($_GET['type'] ?? '') === 'new_submission' ? 'selected' : ''; ?>>New Submissions</option>
                    <option value="resubmission" <?php echo ($_GET['type'] ?? '') === 'resubmission' ? 'selected' : ''; ?>>Resubmissions</option>
                    <option value="update" <?php echo ($_GET['type'] ?? '') === 'update' ? 'selected' : ''; ?>>Updates</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select name="employment_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All Statuses</option>
                    <option value="Employed" <?php echo ($_GET['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                    <option value="Self-Employed" <?php echo ($_GET['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                    <option value="Student" <?php echo ($_GET['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                    <option value="Employed & Student" <?php echo ($_GET['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                    <option value="Unemployed" <?php echo ($_GET['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Read Status</label>
                <select name="read_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All</option>
                    <option value="unread" <?php echo ($_GET['read_status'] ?? '') === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                    <option value="read" <?php echo ($_GET['read_status'] ?? '') === 'read' ? 'selected' : ''; ?>>Read Only</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Batch Year</label>
                <input type="number" name="batch_year" value="<?php echo $_GET['batch_year'] ?? ''; ?>"
                       min="2000" max="<?php echo date('Y'); ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                       placeholder="e.g., 2023">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            
            <div class="md:col-span-4 flex justify-end space-x-3 mt-2">
                <a href="submission_review.php" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Clear Filters
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Submissions Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumni</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                                <p class="text-sm">No submissions found</p>
                                <p class="text-xs text-gray-400 mt-1">Try adjusting your filters</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): ?>
                            <?php 
                            $type_color = getNotificationTypeColor($sub['notification_type']);
                            $type_icon = getNotificationTypeIcon($sub['notification_type']);
                            $type_label = getNotificationTypeLabel($sub['notification_type']);
                            $time_ago = getTimeAgo($sub['submission_time']);
                            ?>
                           <tr class="hover:bg-gray-50 <?php echo $sub['is_read'] ? '' : 'bg-blue-50'; ?> transition-colors"
    data-notification-id="<?php echo $sub['notification_id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($sub['alumni_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Batch <?php echo htmlspecialchars($sub['batch_year']); ?>
                                            </div>
                                            <?php if (!empty($sub['email'])): ?>
                                                <div class="text-xs text-gray-400 truncate max-w-xs">
                                                    <?php echo htmlspecialchars($sub['email']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="<?php echo $type_icon; ?> mr-2 text-gray-500"></i>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type_color; ?>">
                                            <?php echo $type_label; ?>
                                        </span>
                                        <?php if (!$sub['is_read']): ?>
                                            <span class="ml-2 inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php
                                        $status_color = '';
                                        $status_icon = '';
                                        switch($sub['employment_status']) {
                                            case 'Employed':
                                                $status_color = 'bg-green-100 text-green-800 border-green-200';
                                                $status_icon = 'fas fa-building text-green-600';
                                                break;
                                            case 'Self-Employed':
                                                $status_color = 'bg-blue-100 text-blue-800 border-blue-200';
                                                $status_icon = 'fas fa-briefcase text-blue-600';
                                                break;
                                            case 'Student':
                                                $status_color = 'bg-purple-100 text-purple-800 border-purple-200';
                                                $status_icon = 'fas fa-graduation-cap text-purple-600';
                                                break;
                                            case 'Employed & Student':
                                                $status_color = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                $status_icon = 'fas fa-user-graduate text-yellow-600';
                                                break;
                                            case 'Unemployed':
                                                $status_color = 'bg-red-100 text-red-800 border-red-200';
                                                $status_icon = 'fas fa-user-slash text-red-600';
                                                break;
                                            default:
                                                $status_color = 'bg-gray-100 text-gray-800 border-gray-200';
                                                $status_icon = 'fas fa-user-clock text-gray-600';
                                        }
                                        ?>
                                        <i class="<?php echo $status_icon; ?> mr-2"></i>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($sub['employment_status']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php if (!empty($sub['company_name'])): ?>
                                            <div class="mb-1 flex items-center">
                                                <i class="fas fa-building text-gray-400 mr-2 text-xs"></i>
                                                <span><?php echo htmlspecialchars($sub['company_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($sub['business_type'])): ?>
                                            <div class="mb-1 flex items-center">
                                                <i class="fas fa-store text-gray-400 mr-2 text-xs"></i>
                                                <span><?php echo htmlspecialchars($sub['business_type']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($sub['school_name'])): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-school text-gray-400 mr-2 text-xs"></i>
                                                <span><?php echo htmlspecialchars($sub['school_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="font-medium"><?php echo $time_ago; ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?php echo date('M j, Y g:i A', strtotime($sub['submission_time'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="alumni_management.php?view=<?php echo $sub['user_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 px-3 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 transition duration-200">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                       <button class="mark-read-btn text-green-600 hover:text-green-900 px-3 py-1 rounded-lg bg-green-50 hover:bg-green-100 transition duration-200"
        data-id="<?php echo $sub['notification_id']; ?>">
    <i class="fas fa-check mr-1"></i> Mark Read
</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <?php if ($page > 1): ?>
        <a href="...">Previous</a>
    <?php endif; ?>

    <?php 
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    for ($p = $start_page; $p <= $end_page; $p++): // Start colon
    ?>
        <a href="..." class="<?php echo $p == $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
        </a>
    <?php endfor; ?> <?php if ($page < $total_pages): ?>
        <a href="...">Next</a>
    <?php endif; ?>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Submission review page loaded');
    
    // Get the base URL for API calls
    const baseUrl = window.location.origin;
    const currentPath = window.location.pathname;
    const isAdminPage = currentPath.includes('admin/');
    
    // Build correct API path
    let apiBasePath = '../api/notification/';
    if (!isAdminPage) {
        apiBasePath = 'api/notification/';
    }
    
    console.log('API Base Path:', apiBasePath);
    
    // Function to show alert
    function showAlert(message, type = 'error') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 
            'bg-red-100 text-red-800 border border-red-300'
        }`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                <span>${message}</span>
                <button class="ml-4 text-gray-500 hover:text-gray-700" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(alertDiv);
        
        if (type === 'success') {
            setTimeout(() => alertDiv.remove(), 3000);
        }
    }
    
    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async function() {
            if (!confirm('Mark all notifications as read?')) return;
            
            console.log('Mark all as read clicked');
            
            try {
                const response = await fetch(apiBasePath + 'mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include' // Important: sends session cookies
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Response data:', result);
                
                if (result.success) {
                    showAlert(`Successfully marked ${result.marked_read || 0} notifications as read`, 'success');
                    // Reload after a short delay
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('Failed to mark all as read: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to mark all as read: ' + error.message);
            }
        });
    }
    
    // Individual mark as read buttons
    document.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.stopPropagation(); // Prevent row click event
            
            const notificationId = this.getAttribute('data-id');
            console.log('Mark as read clicked for ID:', notificationId);
            
            if (!notificationId) {
                showAlert('No notification ID found!');
                return;
            }
            
            const row = this.closest('tr');
            
            // Show loading state
            const originalHTML = this.innerHTML;
            const originalClasses = this.className;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing';
            this.disabled = true;
            this.classList.remove('hover:bg-green-100', 'hover:text-green-900');
            
            try {
                const response = await fetch(apiBasePath + 'mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include', // Important for session
                    body: JSON.stringify({ notification_id: notificationId })
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Response data:', result);
                
                if (result.success) {
                    // Update the row appearance
                    row.classList.remove('bg-blue-50');
                    
                    // Remove blue dot if exists
                    const blueDot = row.querySelector('.bg-blue-500');
                    if (blueDot) blueDot.remove();
                    
                    // Update button
                    this.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Read';
                    this.className = originalClasses
                        .replace('bg-green-50', 'bg-gray-100')
                        .replace('text-green-600', 'text-gray-500')
                        .replace('hover:bg-green-100', '')
                        .replace('hover:text-green-900', '');
                    this.disabled = true;
                    
                    // Update unread count in header if exists
                    const unreadCountEl = document.querySelector('span.font-bold');
                    if (unreadCountEl && unreadCountEl.textContent) {
                        const currentCount = parseInt(unreadCountEl.textContent);
                        if (!isNaN(currentCount) && currentCount > 0) {
                            unreadCountEl.textContent = currentCount - 1;
                        }
                    }
                    
                    showAlert('Notification marked as read', 'success');
                    
                } else {
                    showAlert('Failed to mark as read: ' + (result.error || 'Unknown error'));
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                    this.className = originalClasses;
                }
            } catch (error) {
                console.error('Error details:', error);
                showAlert('Failed to mark as read: ' + error.message);
                this.innerHTML = originalHTML;
                this.disabled = false;
                this.className = originalClasses;
            }
        });
    });
    
});
</script>
<?php
$page_content = ob_get_clean();
include("admin_format.php");
$conn->close();
?>