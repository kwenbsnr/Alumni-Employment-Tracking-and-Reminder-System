<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Dashboard";
$active_page = "dashboard";

// Debug: Check what's in the alumni_profile table (kept for original context)
$debugQuery = "SELECT submission_status, employment_status, year_graduated, COUNT(*) as count 
               FROM alumni_profile 
               GROUP BY submission_status, employment_status, year_graduated 
               ORDER BY submission_status, employment_status, year_graduated";
$debugResult = $conn->query($debugQuery);

// Fetch employment status distribution (ALL alumni, not just approved)
$careerQuery = "SELECT employment_status, COUNT(*) as total 
                 FROM alumni_profile 
                 WHERE employment_status IS NOT NULL 
                 AND employment_status != ''
                 GROUP BY employment_status";
$result = $conn->query($careerQuery);

if (!$result) {
    // If query fails, show error and set empty arrays
    error_log("Employment status query failed: " . $conn->error);
    $careerLabels = ['Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed & Student'];
    $careerData = [0, 0, 0, 0, 0];
} else {
    $careerLabels = [];
    $careerData = [];
    
    // Initialize all possible employment statuses with zero counts
    $allStatuses = ['Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed & Student'];
    $statusCounts = array_fill_keys($allStatuses, 0);
    
    // Update counts with actual data
    while ($row = $result->fetch_assoc()) {
        $status = $row['employment_status'] ?: 'Unknown';
        $statusCounts[$status] = $row['total'];
    }
    
    // Convert to arrays for Chart.js
    foreach ($allStatuses as $status) {
        $careerLabels[] = $status;
        $careerData[] = $statusCounts[$status];
    }
}

// Fetch ACCURATE dashboard statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'alumni') as total_alumni,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Approved') as approved_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Pending') as pending_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Rejected') as rejected_profiles,
        (SELECT COUNT(*) FROM employment_info WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) as employed_count,
        (SELECT COUNT(DISTINCT year_graduated) FROM alumni_profile WHERE year_graduated IS NOT NULL) as unique_graduation_years,
        (SELECT COUNT(*) FROM alumni_documents WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) as total_documents
";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch graduation trends (ALL alumni with graduation years)
$graduatesQuery = "
    SELECT year_graduated, COUNT(*) as count 
    FROM alumni_profile 
    WHERE year_graduated IS NOT NULL 
    AND year_graduated != ''
    AND year_graduated != '0000'
    AND year_graduated != 0
    GROUP BY year_graduated 
    ORDER BY year_graduated
";
$graduatesResult = $conn->query($graduatesQuery);
$gradYears = [];
$gradCounts = [];

if ($graduatesResult && $graduatesResult->num_rows > 0) {
    while ($row = $graduatesResult->fetch_assoc()) {
        $gradYears[] = (string)$row['year_graduated']; // Convert to string for JSON
        $gradCounts[] = $row['count'];
    }
} else {
    // If no data, set default empty arrays
    $gradYears = [];
    $gradCounts = [];
}

// Fetch enhanced recent activity from update_log with more details
$recentActivityQuery = "
    SELECT 
        ul.log_id,
        ul.updated_by,
        ul.updated_id,
        ul.update_type,
        ul.updated_at,
        u.name as admin_name,
        ap.first_name,
        ap.last_name
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN alumni_profile ap ON ul.updated_id = ap.user_id
    ORDER BY ul.updated_at DESC
    LIMIT 10
";
$recentActivityResult = $conn->query($recentActivityQuery);

ob_start();
?>
<style>
/* Custom CSS for enhanced dashboard design */
.stats-card {
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--card-color), transparent);
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.card-hover:hover .card-icon {
    transform: scale(1.15);
}

.card-icon {
    transition: transform 0.3s ease;
}
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden; /* Prevents page scroll */
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 360px; /* Main content + sidebar */
    gap: 20px;
    height: 100vh; /* Full viewport height */
    padding: 10px;
    box-sizing: border-box;
}
.main-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
    height: 100%; /* Fill the grid height */
    overflow: hidden;
}

/* Recent Activity sidebar */
.recent-activity-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    height: 650px; /* Match analytics-section height */
    position: sticky;
    top: 2px;
}

/* Inner scrollable area */
.recent-activity-sidebar .p-4.space-y-3 {
    max-height: 100%;
    overflow-y: auto;
}


/* Optional: Fine-tune for very small screens */
@media (max-width: 1024px) {
    .recent-activity-sidebar {
        position: static;
        max-height: none;
    }
    .recent-activity-sidebar .p-4.space-y-3 {
        max-height: 400px; /* Re-establish a scrollable height for smaller screens */
    }
    .dashboard-grid {
        grid-template-columns: 1fr; /* Stack main content and sidebar */
    }
}

</style>

<div class="dashboard-grid">
<div class="main-content">
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #3b82f6;">
            <div class="p-4"> <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Alumni</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['total_alumni']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">All registered alumni users</p>
                </div>
                <div class="p-3 rounded-xl bg-blue-50 card-icon">
                    <i class="fas fa-users text-xl text-blue-500"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-blue-600"> <i class="fas fa-database mr-1"></i>
                <span>Complete database</span>
            </div>
        </div>
    </div>
    
    <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #10b981;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Approved Profiles</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['approved_profiles']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Verified alumni profiles</p>
                </div>
                <div class="p-3 rounded-xl bg-green-50 card-icon">
                    <i class="fas fa-check-circle text-xl text-green-500"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-green-600">
                <i class="fas fa-shield-check mr-1"></i>
                <span>Verified & approved</span>
            </div>
        </div>
    </div>
    
    <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #f59e0b;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Pending Reviews</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['pending_profiles']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Awaiting approval</p>
                </div>
                <div class="p-3 rounded-xl bg-yellow-50 card-icon">
                    <i class="fas fa-clock text-xl text-yellow-500"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-yellow-600">
                <i class="fas fa-hourglass-half mr-1"></i>
                <span>Requires attention</span>
            </div>
        </div>
    </div>
</div>

    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-3"> <div class="mb-5 border-b pb-3"> <h2 class="text-xl font-extrabold text-gray-900 flex items-center">
                <i class="fas fa-chart-bar mr-1 text-blue-600"></i>
                Alumni Analytics
            </h2>
            <p class="text-sm text-gray-500 mt-1">Visual data for career status and graduation trends.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"> <div class="rounded-xl border border-gray-200 p-3 shadow-sm transition duration-300 hover:shadow-md"> 
            <h3 class="text-lg font-bold text-gray-800 mb-2 border-b pb-2">Employment Status Distribution</h3>
                <?php if (array_sum($careerData) > 0): ?>
                    <div class="h-80"> <canvas id="employmentChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-80 text-gray-400">
                        <i class="fas fa-chart-pie text-5xl mb-3"></i>
                        <p class="text-sm">No employment data available</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-gray-200 p-3 shadow-sm transition duration-300 hover:shadow-md"> 
                <h3 class="text-lg font-bold text-gray-800 mb-2 border-b pb-2">Graduates per Year</h3>
                <?php if (!empty($gradYears) && array_sum($gradCounts) > 0): ?>
                    <div class="h-80"> <canvas id="graduationChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-80 text-gray-400">
                        <i class="fas fa-chart-line text-5xl mb-3"></i>
                        <p class="text-sm">No graduation data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
    <div class="recent-activity-sidebar">
<div class="p-5 border-b" 
     style="background: linear-gradient(135deg, #e0f2fe 0%, #bfdbfe 50%, #93c5fd 100%);
            border-bottom: 1px solid #d1d5db;">

    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-blue-900">Recent Activity</h3>
            <p class="text-sm text-blue-700 mt-1">Latest 10 updates and changes</p>
        </div>
        <a href="activity_log.php" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors font-medium text-xs border border-blue-200">
            View All
            <i class="fas fa-arrow-right ml-1 text-xs"></i>
        </a>
    </div>
</div>
        <div class="p-4 space-y-3 overflow-y-auto"> <?php if ($recentActivityResult->num_rows > 0): ?>
                <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                   <div class="activity-item p-3 bg-white rounded-lg border border-gray-100 hover:shadow-sm transition-all <?php echo $activity['update_type']; ?>" 
      style="--activity-color: <?php echo getActivityHexColor($activity['update_type']); ?>">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="p-2 rounded-lg <?php echo getActivityColor($activity['update_type']); ?>">
                                    <i class="fas fa-<?php echo getActivityIcon($activity['update_type']); ?> text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo getEnhancedActivityText($activity); ?>
                                </p>
                                <div class="flex items-center mt-1 space-x-3 text-xs text-gray-500">
                                    <span class="flex items-center truncate">
                                        <i class="fas fa-user-shield mr-1"></i>
                                        <?php echo htmlspecialchars($activity['admin_name']); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo time_elapsed_string($activity['updated_at']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo getActivityBadgeColor($activity['update_type']); ?>">
                                <i class="fas fa-<?php echo getActivityIcon($activity['update_type']); ?> mr-1 text-xs"></i>
                                <?php echo ucfirst($activity['update_type']); ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-3">
                        <i class="fas fa-inbox text-lg text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-400">No recent activity</p>
                    <p class="text-xs text-gray-400 mt-1">System updates will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
// Employment Distribution Chart with improved colors
<?php if (array_sum($careerData) > 0): ?>
const employmentCtx = document.getElementById('employmentChart').getContext('2d');
new Chart(employmentCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($careerLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($careerData); ?>,
            backgroundColor: [
                '#4A90E2', '#7ED321', '#F5A623', '#D0021B', '#9B51E0'
            ],
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 10
        }]
    },
options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: {
            position: 'right',  // <<====== MOVE LEGEND TO RIGHT SIDE
            labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 13, family: "'Inter', sans-serif" }
            }
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    const label = context.label || '';
                    const value = context.raw || 0;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((value / total) * 100).toFixed(1);
                    return `${label}: ${value} (${percentage}%)`;
                }
            },
            backgroundColor: 'rgba(255,255,255,0.95)',
            titleColor: '#1f2937',
            bodyColor: '#4b5563',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            cornerRadius: 8
        }
    }
}

});
<?php endif; ?>

// Graduation Trends Chart with enhanced styling
<?php if (!empty($gradYears) && array_sum($gradCounts) > 0): ?>
const graduationCtx = document.getElementById('graduationChart').getContext('2d');

// Create gradient for the area under the line
const gradient = graduationCtx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(139, 92, 246, 0.3)');
gradient.addColorStop(1, 'rgba(139, 92, 246, 0.05)');

new Chart(graduationCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($gradYears); ?>,
        datasets: [{
            label: 'Graduates per Year',
            data: <?php echo json_encode($gradCounts); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: gradient,
            borderWidth: 3, /* Reduced line thickness */
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#8b5cf6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2, /* Reduced point border */
            pointRadius: 5, /* Reduced point radius */
            pointHoverRadius: 7, /* Reduced hover radius */
            pointHoverBackgroundColor: '#8b5cf6',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#1f2937',
                bodyColor: '#4b5563',
                borderColor: '#e5e7eb',
                borderWidth: 1,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    stepSize: 1,
                    font: {
                        family: "'Inter', sans-serif"
                    }
                }
            },
            x: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: {
                        family: "'Inter', sans-serif"
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});
<?php endif; ?>

// Add hover effects for cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stats-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Toast notification handling
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        // Assuming showToast is a defined function in admin_format.php or another included file
        if (typeof showToast === 'function') {
            showToast(urlParams.get('success'), 'success');
        }
    } else if (urlParams.has('error')) {
        if (typeof showToast === 'function') {
            showToast(urlParams.get('error'), 'error');
        }
    }
});
</script>
<?php
// Enhanced Helper functions with new color function
function getActivityIcon($update_type) {
    switch ($update_type) {
        case 'approve': return 'check-circle';
        case 'reject': return 'times-circle';
        case 'update': return 'edit';
        default: return 'sync';
    }
}

function getActivityColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-500';
        case 'reject': return 'bg-red-100 text-red-500';
        case 'update': return 'bg-blue-100 text-blue-500';
        default: return 'bg-purple-100 text-purple-500';
    }
}

function getActivityHexColor($update_type) {
    switch ($update_type) {
        case 'approve': return '#10b981';
        case 'reject': return '#ef4444';
        case 'update': return '#3b82f6';
        default: return '#8b5cf6';
    }
}

function getActivityBadgeColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-50 text-green-700 border border-green-200';
        case 'reject': return 'bg-red-50 text-red-700 border border-red-200';
        case 'update': return 'bg-blue-50 text-blue-700 border border-blue-200';
        default: return 'bg-gray-50 text-gray-700 border border-gray-200';
    }
}

function getEnhancedActivityText($activity) {
    $name = '';
    
    // Get the affected user's name - improved logic
    if (!empty($activity['first_name']) || !empty($activity['last_name'])) {
        $name = trim(htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')));
    }
    
    // If we still don't have a name, try to get it from the users table
    if (empty($name)) {
        global $conn;
        $user_id = $activity['updated_id'];
        $userQuery = "SELECT name FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            $name = htmlspecialchars($userData['name']);
        } else {
            $name = "Alumni (ID: {$user_id})";
        }
        $stmt->close();
    }
    
    $actions = [
        'approve' => 'Approved',
        'reject' => 'Rejected', 
        'update' => 'Updated'
    ];
    
    $action = $actions[$activity['update_type']] ?? 'Modified';
    
    return "{$action} {$name}'s profile";
}
function time_elapsed_string($datetime, $full = false) {
    // Ensure we work with DateTime in the correct timezone
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    
    // If datetime came from MySQL without timezone, adjust it
    if ($ago->getTimezone()->getName() !== 'Asia/Manila') {
        $ago->setTimezone(new DateTimeZone('Asia/Manila'));
    }

    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>