<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Dashboard";
$active_page = "dashboard";

// Fetch employment status distribution (ALL alumni with profiles)
$careerQuery = "SELECT employment_status, COUNT(*) as total 
                FROM alumni_profile 
                WHERE employment_status IS NOT NULL AND employment_status != ''
                GROUP BY employment_status";
$result = $conn->query($careerQuery);

$careerLabels = ['Employed', 'Self-Employed', 'Unemployed', 'Student', 'Employed & Student'];
$careerData = [0, 0, 0, 0, 0];

if ($result && $result->num_rows > 0) {
    $statusCounts = array_fill_keys($careerLabels, 0);
    while ($row = $result->fetch_assoc()) {
        if (in_array($row['employment_status'], $careerLabels)) {
            $statusCounts[$row['employment_status']] = $row['total'];
        }
    }
    $careerData = array_values($statusCounts);
}

// Fetch ACCURATE dashboard statistics - FIXED LOGIC
$statsQuery = "
    SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'alumni') AS total_alumni,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Approved') AS approved_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Pending') AS pending_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Rejected') AS rejected_profiles,
        (SELECT COUNT(*) FROM alumni_profile 
         WHERE submission_status = 'Approved' 
         AND employment_status IN ('Employed', 'Self-Employed', 'Employed & Student')) AS employed_count,
        (SELECT COUNT(DISTINCT u.batch_year) 
         FROM users u 
         WHERE u.role = 'alumni' 
         AND u.batch_year IS NOT NULL AND u.batch_year != '' AND u.batch_year != '0000') AS unique_graduation_years,
        (SELECT COUNT(*) FROM alumni_documents 
         WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) AS total_documents,
        (SELECT COUNT(DISTINCT ap.user_id) FROM alumni_profile ap WHERE ap.submission_status IS NOT NULL) AS alumni_with_profiles
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc() ?? [];

// Fetch graduation trends - include all alumni
$graduatesQuery = "
    SELECT u.batch_year, COUNT(*) as count 
    FROM users u
    WHERE u.role = 'alumni' 
    AND u.batch_year IS NOT NULL 
    AND u.batch_year != '' 
    AND u.batch_year != '0000'
    GROUP BY u.batch_year 
    ORDER BY u.batch_year
";
$graduatesResult = $conn->query($graduatesQuery);
$gradYears = [];
$gradCounts = [];
if ($graduatesResult && $graduatesResult->num_rows > 0) {
    while ($row = $graduatesResult->fetch_assoc()) {
        $gradYears[] = (string)$row['batch_year'];
        $gradCounts[] = $row['count'];
    }
}

// Calculate total alumni with profiles for employment chart - FIXED
$totalWithProfiles = $stats['alumni_with_profiles'] ?? 0;
$totalAlumni = $stats['total_alumni'] ?? 0;
$withoutProfiles = $totalAlumni - $totalWithProfiles;

// Fetch recent activity
$recentActivityQuery = "
    SELECT ul.update_type, ul.updated_at, ul.update_details,
           CONCAT(
                u.first_name, 
                IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
                ' ',
                u.last_name,
                IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
           ) as admin_name,
           CONCAT(
                u2.first_name, 
                IF(u2.middle_name IS NOT NULL AND u2.middle_name != '', CONCAT(' ', u2.middle_name), ''),
                ' ',
                u2.last_name,
                IF(u2.suffix IS NOT NULL AND u2.suffix != '', CONCAT(' ', u.suffix), '')
           ) as alumni_name,
           u2.batch_year
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN users u2 ON ul.updated_id = u2.user_id
    WHERE ul.update_type IN ('approve', 'reject', 'update')
    ORDER BY ul.updated_at DESC LIMIT 10
";
$recentActivityResult = $conn->query($recentActivityQuery);

ob_start();
?>
<style>
    .stats-card {
        transition: all 0.3s ease;
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
    }
    .stats-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--card-color), transparent);
    }
    .stats-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }
    .card-icon { transition: transform 0.3s ease; }
    .stats-card:hover .card-icon { transform: scale(1.15); }

    html, body { height: 100%; margin: 0; padding: 0; }
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        min-height: 100vh;
        padding: 10px;
        box-sizing: border-box;
    }
    .main-content {
        display: flex;
        flex-direction: column;
        gap: 20px;
        overflow-y: auto;
    }
    .recent-activity-sidebar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 600px;
    }
    .recent-activity-sidebar .activity-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    @media (max-width: 1024px) {
        .dashboard-grid { 
            grid-template-columns: 1fr; 
            grid-template-rows: auto auto;
        }
        .recent-activity-sidebar {
            height: auto;
            max-height: 400px;
        }
    }
</style>

<div class="dashboard-grid">
    <div class="main-content">
        <!-- Enhanced Stats Cards -->
        <div class="space-y-4">
            <!-- Total Alumni, Active Alumni & Employment Rate -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Total Alumni Card (All Graduates) -->
                <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #3b82f6;">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Alumni</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['total_alumni'] ?? 0; ?></p>
                                <p class="text-xs text-gray-500 mt-1">All graduated alumni in system</p>
                            </div>
                            <div class="p-3 rounded-xl bg-blue-50 card-icon">
                                <i class="fas fa-users text-xl text-blue-500"></i>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center text-xs text-blue-600">
                            <i class="fas fa-graduation-cap mr-1"></i>
                            <span>All graduated students</span>
                        </div>
                    </div>
                </div>

                <!-- Active Alumni Card (Completed Requirements) -->
                <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #10b981;">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Active Alumni</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['approved_profiles'] ?? 0; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Completed tracking requirements</p>
                            </div>
                            <div class="p-3 rounded-xl bg-green-50 card-icon">
                                <i class="fas fa-user-check text-xl text-green-500"></i>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center text-xs text-green-600">
                            <i class="fas fa-shield-check mr-1"></i>
                            <span>Fully verified & active</span>
                        </div>
                        <?php if (($stats['total_alumni'] ?? 0) > 0): ?>
                            <div class="mt-2">
                                <div class="flex justify-between text-xs text-gray-600 mb-1">
                                    <span>Completion Rate</span>
                                    <span><?php echo round((($stats['approved_profiles'] ?? 0) / ($stats['total_alumni'] ?? 1)) * 100, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-green-500 h-1.5 rounded-full" style="width: <?php echo min(100, (($stats['approved_profiles'] ?? 0) / ($stats['total_alumni'] ?? 1)) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employment Rate Card -->
                <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #8b5cf6;">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Employment Rate</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    <?php 
                                    $total_employed = $stats['employed_count'] ?? 0;
                                    $active_alumni = $stats['approved_profiles'] ?? 0;
                                    $employment_rate = $active_alumni > 0 ? round(($total_employed / $active_alumni) * 100, 1) : 0;
                                    echo $employment_rate; 
                                    ?>%
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Of active alumni</p>
                            </div>
                            <div class="p-3 rounded-xl bg-purple-50 card-icon">
                                <i class="fas fa-briefcase text-xl text-purple-500"></i>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center text-xs text-purple-600">
                            <i class="fas fa-chart-line mr-1"></i>
                            <span><?php echo $total_employed; ?> employed alumni</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Reviews & Rejected Profiles -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Pending Reviews Card -->
                <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #f59e0b;">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Pending Reviews</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['pending_profiles'] ?? 0; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Awaiting admin approval</p>
                            </div>
                            <div class="p-3 rounded-xl bg-yellow-50 card-icon">
                                <i class="fas fa-clock text-xl text-yellow-500"></i>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center text-xs text-yellow-600">
                            <i class="fas fa-hourglass-half mr-1"></i>
                            <span>Requires review</span>
                        </div>
                        <?php if (($stats['pending_profiles'] ?? 0) > 0): ?>
                            <div class="mt-2">
                                <div class="flex items-center text-xs text-yellow-700 bg-yellow-50 px-2 py-1 rounded border border-yellow-200">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    <span>Needs attention</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rejected Profiles Card -->
                <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #ef4444;">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Rejected Profiles</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['rejected_profiles'] ?? 0; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Need corrections & resubmission</p>
                            </div>
                            <div class="p-3 rounded-xl bg-red-50 card-icon">
                                <i class="fas fa-times-circle text-xl text-red-500"></i>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center text-xs text-red-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span>Requires updates</span>
                        </div>
                        <?php if (($stats['rejected_profiles'] ?? 0) > 0): ?>
                            <div class="mt-2">
                                <div class="flex items-center text-xs text-red-700 bg-red-50 px-2 py-1 rounded border border-red-200">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    <span>Awaiting resubmission</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Analytics Section -->
        <div class="stats-card bg-white rounded-xl shadow-sm border border-gray-100 mt-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="p-3 rounded-xl bg-blue-50">
                            <i class="fas fa-chart-bar text-xl text-blue-500"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Alumni Analytics</h2>
                            <p class="text-sm text-gray-500 mt-1">Visual data insights and trends</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i>
                        <span>Real-time data visualization</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Employment Status Distribution Card -->
                    <div class="stats-card bg-gray-50 rounded-xl border border-gray-200 p-4 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                                Employment Status Distribution
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-semibold bg-purple-100 text-purple-700 px-2 py-1 rounded-full">
                                    <?php echo $totalWithProfiles; ?> with profiles
                                </span>
                                <?php if ($withoutProfiles > 0): ?>
                                <span class="text-xs font-semibold bg-gray-100 text-gray-700 px-2 py-1 rounded-full">
                                    <?php echo $withoutProfiles; ?> no profile
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($totalAlumni > 0): ?>
                            <div class="h-72"><canvas id="employmentChart"></canvas></div>
                            <div class="mt-3 text-center">
                                <p class="text-xs text-gray-600">
                                    Showing data for <?php echo $totalWithProfiles; ?> alumni with profiles 
                                    (<?php echo round(($totalWithProfiles / $totalAlumni) * 100, 1); ?>% of total)
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-72 text-gray-400">
                                <i class="fas fa-chart-pie text-5xl mb-3"></i>
                                <p class="text-sm font-medium">No alumni data available</p>
                                <p class="text-xs mt-1">Employment data will appear here once alumni submit profiles</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Graduates per Year Card -->
                    <div class="stats-card bg-gray-50 rounded-xl border border-gray-200 p-4 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                                Graduation Trends
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                                    <?php echo count($gradYears); ?> batches
                                </span>
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                    <?php echo array_sum($gradCounts); ?> total
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($gradYears)): ?>
                            <div class="h-72"><canvas id="graduationChart"></canvas></div>
                            <div class="mt-3 flex justify-between text-xs text-gray-600">
                                <span>Peak: <?php echo max($gradCounts); ?> graduates</span>
                                <span>Average: <?php echo round(array_sum($gradCounts) / count($gradCounts), 1); ?>/year</span>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-72 text-gray-400">
                                <i class="fas fa-chart-line text-5xl mb-3"></i>
                                <p class="text-sm font-medium">No graduation data available</p>
                                <p class="text-xs mt-1">Graduation trends will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <div class="flex items-center space-x-4">
                            <span class="flex items-center">
                                <i class="fas fa-sync-alt mr-1"></i>
                                Auto-updates every 5 minutes
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-database mr-1"></i>
                                Based on verified alumni data
                            </span>
                        </div>
                        <span class="text-gray-400">
                            Last updated: <?php echo date('M j, Y g:i A'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Sidebar -->
    <div class="recent-activity-sidebar">
        <div class="p-5 border-b" style="background: linear-gradient(135deg, #e0f2fe 0%, #bfdbfe 50%, #93c5fd 100%); border-bottom: 1px solid #d1d5db;">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">Recent Activity</h3>
                    <p class="text-sm text-blue-700 mt-1">Latest 10 updates and changes</p>
                </div>
                <a href="activity_log.php" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors font-medium text-xs border border-blue-200">
                    View All <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
        <div class="activity-list space-y-3">
            <?php if ($recentActivityResult && $recentActivityResult->num_rows > 0): ?>
                <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                    <div class="p-3 bg-white rounded-lg border border-gray-100 hover:shadow-sm transition">
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
                                        <?php echo htmlspecialchars($activity['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>
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
<?php if ($totalAlumni > 0): ?>
// Enhanced Employment Status Distribution Chart
new Chart(document.getElementById('employmentChart'), {
    type: 'doughnut',
    data: {
        labels: [
            <?php 
            echo implode(', ', array_map(function($label) {
                return "'" . $label . "'";
            }, $careerLabels));
            ?>,
            'No Profile Submitted'
        ],
        datasets: [{
            data: [
                <?php echo implode(', ', $careerData); ?>,
                <?php echo $withoutProfiles; ?>
            ],
            backgroundColor: [
                '#4A90E2', // Employed - Blue
                '#7ED321', // Self-Employed - Green
                '#F5A623', // Unemployed - Orange
                '#D0021B', // Student - Red
                '#9B51E0', // Employed & Student - Purple
                '#95A5A6'  // No Profile - Gray
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 20,
            hoverBorderWidth: 4,
            hoverBackgroundColor: [
                '#357ABD', // Darker Blue
                '#6BC120', // Darker Green
                '#E6951F', // Darker Orange
                '#B8021A', // Darker Red
                '#8A46D4', // Darker Purple
                '#7F8C8D'  // Darker Gray
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { 
                position: 'right', 
                labels: { 
                    usePointStyle: true, 
                    padding: 20,
                    font: { 
                        size: 11, 
                        weight: '600',
                        family: "'Inter', 'Segoe UI', sans-serif"
                    },
                    color: '#374151'
                } 
            },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                borderColor: '#4B5563',
                borderWidth: 2,
                cornerRadius: 12,
                padding: 16,
                titleFont: { 
                    size: 14, 
                    weight: '700',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                bodyFont: { 
                    size: 13, 
                    weight: '600',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                footerFont: {
                    size: 11,
                    weight: '500',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                titleColor: '#F9FAFB',
                bodyColor: '#E5E7EB',
                footerColor: '#9CA3AF',
                boxPadding: 10,
                callbacks: {
                    title: function(tooltipItems) {
                        return tooltipItems[0].label;
                    },
                    label: function(context) {
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `
                            ${value} alumni • ${percentage}% of total
                        `.trim();
                    },
                    afterLabel: function(context) {
                        const label = context.label;
                        if (label === 'No Profile Submitted') {
                            return '📝 Profile not yet started';
                        } else {
                            return '✅ Profile submitted';
                        }
                    }
                },
                displayColors: true,
                usePointStyle: true,
                caretSize: 8,
                caretPadding: 12
            }
        },
        animation: {
            animateScale: true,
            animateRotate: true,
            duration: 2000,
            easing: 'easeOutQuart'
        },
        hover: {
            mode: 'nearest',
            intersect: true,
            animationDuration: 300
        }
    }
});
<?php endif; ?>

<?php if (!empty($gradYears)): ?>
// Enhanced Graduates per Year Chart with Clear Hover Text
const gradCtx = document.getElementById('graduationChart').getContext('2d');

// Create enhanced gradient
const gradient = gradCtx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(139, 92, 246, 0.4)');
gradient.addColorStop(0.7, 'rgba(139, 92, 246, 0.15)');
gradient.addColorStop(1, 'rgba(139, 92, 246, 0.05)');

// Hover gradient
const hoverGradient = gradCtx.createLinearGradient(0, 0, 0, 400);
hoverGradient.addColorStop(0, 'rgba(139, 92, 246, 0.6)');
hoverGradient.addColorStop(0.7, 'rgba(139, 92, 246, 0.25)');
hoverGradient.addColorStop(1, 'rgba(139, 92, 246, 0.1)');

// Calculate statistics
const gradData = <?php echo json_encode($gradCounts); ?>;
const totalGrads = gradData.reduce((a, b) => a + b, 0);
const maxGrads = Math.max(...gradData);
const avgGrads = Math.round(totalGrads / gradData.length);

new Chart(gradCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($gradYears); ?>,
        datasets: [{
            label: 'Graduates',
            data: gradData,
            borderColor: '#8b5cf6',
            backgroundColor: gradient,
            borderWidth: 4,
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#8b5cf6',
            pointBorderColor: '#fff',
            pointBorderWidth: 3,
            pointRadius: 6,
            pointHoverRadius: 10,
            pointHoverBackgroundColor: '#7c3aed',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 4,
            hoverBackgroundColor: hoverGradient
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
                enabled: true,
                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                borderColor: '#7c3aed',
                borderWidth: 3,
                cornerRadius: 14,
                padding: 18,
                titleFont: { 
                    size: 15, 
                    weight: '700',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                bodyFont: { 
                    size: 14, 
                    weight: '600',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                footerFont: {
                    size: 12,
                    weight: '500',
                    family: "'Inter', 'Segoe UI', sans-serif"
                },
                titleColor: '#F9FAFB',
                bodyColor: '#E5E7EB',
                footerColor: '#9CA3AF',
                boxPadding: 12,
                callbacks: {
                    title: function(tooltipItems) {
                        return `🎓 Batch ${tooltipItems[0].label}`;
                    },
                    label: function(context) {
                        const value = context.parsed.y;
                        const percentage = ((value / totalGrads) * 100).toFixed(1);
                        return `${value} graduates • ${percentage}% of total alumni`;
                    },
                    afterLabel: function(context) {
                        const year = context.label;
                        const index = context.dataIndex;
                        const prevYear = index > 0 ? gradData[index - 1] : null;
                        
                        if (prevYear !== null) {
                            const change = context.parsed.y - prevYear;
                            const changePercent = ((change / prevYear) * 100).toFixed(1);
                            if (change > 0) {
                                return `📈 +${change} from previous year (+${changePercent}%)`;
                            } else if (change < 0) {
                                return `📉 ${change} from previous year (${changePercent}%)`;
                            } else {
                                return `➡️ No change from previous year`;
                            }
                        }
                        return `⭐ First recorded batch`;
                    }
                },
                displayColors: false,
                caretSize: 10,
                caretPadding: 15
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { 
                    color: 'rgba(0,0,0,0.08)',
                    drawBorder: false,
                    lineWidth: 1
                }, 
                ticks: { 
                    stepSize: Math.ceil(maxGrads / 5),
                    font: { 
                        size: 12, 
                        weight: '600',
                        family: "'Inter', 'Segoe UI', sans-serif"
                    },
                    color: '#6B7280',
                    padding: 10
                },
                border: { display: false }
            },
            x: { 
                grid: { 
                    color: 'rgba(0,0,0,0.08)',
                    drawBorder: false,
                    lineWidth: 1
                },
                ticks: {
                    font: { 
                        size: 12, 
                        weight: '600',
                        family: "'Inter', 'Segoe UI', sans-serif"
                    },
                    color: '#6B7280',
                    maxRotation: 45,
                    padding: 12
                },
                border: { display: false }
            }
        },
        interaction: {
            intersect: false,
            mode: 'nearest'
        },
        animation: {
            duration: 2000,
            easing: 'easeOutQuart'
        },
        elements: {
            line: {
                tension: 0.3
            },
            point: {
                hoverBackgroundColor: '#7c3aed',
                hoverBorderColor: '#fff'
            }
        },
        hover: {
            mode: 'nearest',
            intersect: false,
            animationDuration: 300
        }
    }
});
<?php endif; ?>

// Enhanced hover effects for chart containers
document.addEventListener("DOMContentLoaded", () => {
    const chartContainers = document.querySelectorAll('.stats-card');
    
    chartContainers.forEach(container => {
        // Initial animation
        container.style.opacity = '0';
        container.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            container.style.transition = 'all 0.6s ease-out';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 100);
        
        // Enhanced hover effects
        container.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            this.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.25)';
        });
        
        container.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '';
        });
    });
    
    // Toast notification
    const params = new URLSearchParams(window.location.search);
    if (params.has('success')) {
        showToast(params.get('success'), 'success');
    } else if (params.has('error')) {
        showToast(params.get('error'), 'error');
    }
});
</script>

<?php
// Helper functions - KEEP THESE IN THIS FILE since they're used in the JS above
function getActivityIcon($type) {
    return $type === 'approve' ? 'check-circle' : ($type === 'reject' ? 'times-circle' : 'edit');
}

function getActivityColor($type) {
    return $type === 'approve' ? 'bg-green-100 text-green-500' :
           ($type === 'reject' ? 'bg-red-100 text-red-500' : 'bg-blue-100 text-blue-500');
}

function getActivityBadgeColor($type) {
    return $type === 'approve' ? 'bg-green-50 text-green-700 border border-green-200' :
           ($type === 'reject' ? 'bg-red-50 text-red-700 border border-red-200' :
           'bg-blue-50 text-blue-700 border border-blue-200');
}

function getEnhancedActivityText($activity) {
    $name = !empty($activity['alumni_name']) ? htmlspecialchars($activity['alumni_name'], ENT_QUOTES, 'UTF-8') : "Alumni";
    $batch = !empty($activity['batch_year']) ? " - Batch " . $activity['batch_year'] : "";
    $details = !empty($activity['update_details']) ? htmlspecialchars($activity['update_details'], ENT_QUOTES, 'UTF-8') : ucfirst($activity['update_type']) . "d profile";
    return $details . " for " . $name . $batch;
}

function time_elapsed_string($datetime) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y'=>'year','m'=>'month','w'=>'week','d'=>'day','h'=>'hour','i'=>'minute','s'=>'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        else unset($string[$k]);
    }
    return !empty($string) ? implode(', ', array_slice($string, 0, 1)) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>