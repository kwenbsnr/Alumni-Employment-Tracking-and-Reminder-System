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

// Fetch ACCURATE dashboard statistics
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
         WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) AS total_documents
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch graduation trends
$graduatesQuery = "
    SELECT u.batch_year, COUNT(*) as count 
    FROM users u
    INNER JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.batch_year IS NOT NULL AND u.batch_year != '' AND u.batch_year != '0000'
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

// Fetch recent activity
$recentActivityQuery = "
    SELECT ul.update_type, ul.updated_at, ul.update_details,
           u.name as admin_name, u2.name as alumni_name, u2.batch_year
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

    html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        height: 100vh;
        padding: 10px;
        box-sizing: border-box;
    }
    .main-content {
        display: flex;
        flex-direction: column;
        gap: 20px;
        overflow: hidden;
    }
    .recent-activity-sidebar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .recent-activity-sidebar .activity-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    @media (max-width: 1024px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-grid">
    <div class="main-content">
        <!-- Enhanced Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Alumni Card (All Graduates) -->
            <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #3b82f6;">
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Alumni</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['total_alumni']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['approved_profiles']; ?></p>
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
                    <?php if ($stats['total_alumni'] > 0): ?>
                        <div class="mt-2">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Completion Rate</span>
                                <span><?php echo round(($stats['approved_profiles'] / $stats['total_alumni']) * 100, 1); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-green-500 h-1.5 rounded-full" style="width: <?php echo min(100, ($stats['approved_profiles'] / $stats['total_alumni']) * 100); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Reviews Card -->
            <div class="stats-card bg-white rounded-xl shadow-sm" style="--card-color: #f59e0b;">
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Pending Reviews</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['pending_profiles']; ?></p>
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
                    <?php if ($stats['pending_profiles'] > 0): ?>
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
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['rejected_profiles']; ?></p>
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
                    <?php if ($stats['rejected_profiles'] > 0): ?>
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

        <!-- Analytics Section -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-3">
            <div class="mb-5 border-b pb-3">
                <h2 class="text-xl font-ex Learn more about alumni analytics-extrabold text-bold-gray-900 flex items-center">
                    <i class="fas fa-chart-bar mr-1 text-blue-600"></i> Alumni Analytics
                </h2>
                <p class="text-sm text-gray-500 mt-1">Visual data for career status and graduation trends.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-gray-200 p-3 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-bold text-gray-800 mb-2 border-b pb-2">Employment Status Distribution</h3>
                    <?php if (array_sum($careerData) > 0): ?>
                        <div class="h-80"><canvas id="employmentChart"></canvas></div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-80 text-gray-400">
                            <i class="fas fa-chart-pie text-5xl mb-3"></i>
                            <p class="text-sm">No employment data available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-gray-200 p-3 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-bold text-gray-800 mb-2 border-b pb-2">Graduates per Year</h3>
                    <?php if (!empty($gradYears)): ?>
                        <div class="h-80"><canvas id="graduationChart"></canvas></div>
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
            <?php if ($recentActivityResult->num_rows > 0): ?>
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
                                        <?php echo htmlspecialchars($activity['admin_name'] ?? 'Admin'); ?>
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
<?php if (array_sum($careerData) > 0): ?>
new Chart(document.getElementById('employmentChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($careerLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($careerData); ?>,
            backgroundColor: ['#4A90E2', '#7ED321', '#F5A623', '#D0021B', '#9B51E0'],
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
            legend: { position: 'right', labels: { usePointStyle: true, padding: 15 } },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const value = ctx.raw || 0;
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const percentage = ((value/total)*100).toFixed(1);
                        return `${ctx.label}: ${value} (${percentage}%)`;
                    }
                },
                backgroundColor: 'rgba(255,255,255,0.95)',
                borderColor: '#e5e7eb',
                borderWidth: 1,
                cornerRadius: 8
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($gradYears)): ?>
const gradCtx = document.getElementById('graduationChart').getContext('2d');
const gradient = gradCtx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(139, 92, 246, 0.3)');
gradient.addColorStop(1, 'rgba(139, 92, 246, 0.05)');
new Chart(gradCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($gradYears); ?>,
        datasets: [{
            label: 'Graduates per Year',
            data: <?php echo json_encode($gradCounts); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#8b5cf6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } },
            x: { grid: { color: 'rgba(0,0,0,0.05)' } }
        }
    }
});
<?php endif; ?>

// Toast notification
document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    if (params.has('success') && typeof showToast === 'function') {
        showToast(params.get('success'), 'success');
    } else if (params.has('error') && typeof showToast === 'function') {
        showToast(params.get('error'), 'error');
    }
});
</script>

<?php
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
    $name = !empty($activity['alumni_name']) ? htmlspecialchars($activity['alumni_name']) : "Alumni";
    $batch = !empty($activity['batch_year']) ? " - Batch " . $activity['batch_year'] : "";
    $details = !empty($activity['update_details']) ? htmlspecialchars($activity['update_details']) : ucfirst($activity['update_type']) . "d profile";
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