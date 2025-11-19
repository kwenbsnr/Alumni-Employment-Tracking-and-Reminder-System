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
    /* Tighter card padding/content */
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
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

/* Chart container improvements - removed .analytics-section for tighter layout */
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #f1f1f1;
}

.activity-item {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.activity-item:hover {
    border-left-color: var(--activity-color);
    background: #f8fafc;
}

/* New layout styles - Tighter grid for max space */
.dashboard-grid {
    display: grid;
    /* Adjusted sidebar width slightly for more main content space */
    grid-template-columns: 1fr 360px; 
    gap: 20px; /* Reduced gap */
    align-items: start;
    margin-top: -8px; /* Pull content up slightly */
}

.main-content {
    display: flex;
    flex-direction: column;
    gap: 20px; /* Reduced gap */
}

.recent-activity-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    height: fit-content;
    position: sticky;
    top: 20px; /* Adjusted sticky top */
}

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .recent-activity-sidebar {
        position: static;
    }
}
</style>

<div class="dashboard-grid">
    <div class="main-content">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4"> <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #3b82f6;">
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

        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm"> <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900">Alumni Analytics</h2>
                <p class="text-sm text-gray-600">Key alumni metrics at a glance</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5"> <div class="rounded-lg border border-gray-200 p-4"> <h3 class="text-base font-semibold text-gray-800 mb-3">Employment Status Distribution</h3>
                    <?php if (array_sum($careerData) > 0): ?>
                        <div class="h-64">
                            <canvas id="employmentChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                            <i class="fas fa-chart-pie text-4xl mb-2"></i>
                            <p class="text-sm">No employment data yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-lg border border-gray-200 p-4"> <h3 class="text-base font-semibold text-gray-800 mb-3">Graduates by Year Trend</h3>
                    <?php if (!empty($gradYears) && array_sum($gradCounts) > 0): ?>
                        <div class="h-64">
                            <canvas id="graduationChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                            <i class="fas fa-chart-line text-4xl mb-2"></i>
                            <p class="text-sm">No graduation data yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="recent-activity-sidebar">
        <div class="p-5 border-b border-gray-200"> <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                    <p class="text-sm text-gray-600 mt-1">Latest 10 updates and changes</p>
                </div>
                <a href="activity_log.php" class="inline-flex items-center px-3 py-1.5 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors font-medium text-xs">
                    View All
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
        <div class="p-4 space-y-3 max-h-[calc(100vh-140px)] overflow-y-auto"> <?php if ($recentActivityResult->num_rows > 0): ?>
                <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                    <div class="activity-item p-3 bg-white rounded-lg border border-gray-100 hover:shadow-sm transition-all" 
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Employment Distribution Chart with improved colors
<?php if (array_sum($careerData) > 0): ?>
const employmentCtx = document.getElementById('employmentChart').getContext('2d');
new Chart(employmentCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($careerLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($careerData); ?>,
            backgroundColor: [
                '#4A90E2', // Soft Blue
                '#7ED321', // Soft Green  
                '#F5A623', // Soft Orange
                '#D0021B', // Soft Red
                '#9B51E0', // Soft Purple
                '#06b6d4', // Cyan
                '#84cc16'  // Lime
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverBorderWidth: 4,
            hoverBorderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                position: 'bottom',
                labels: {
                    padding: 20, /* Reduced padding */
                    usePointStyle: true,
                    font: {
                        size: 12,
                        family: "'Inter', sans-serif"
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#1f2937',
                bodyColor: '#4b5563',
                borderColor: '#e5e7eb',
                borderWidth: 1,
                cornerRadius: 8,
                usePointStyle: true,
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        cutout: '0%'
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
    
    // Get the affected user's name
    if (!empty($activity['first_name']) && !empty($activity['last_name'])) {
        $name = htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']);
    } else {
        $name = "an Alumni";
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
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = [
        'y' => 'year',
        'm' => 'month',
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

    if (!$full) $string = array_slice($string, 0, 1); 
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>