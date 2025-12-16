<?php
include("connect.php");

if (!function_exists('isSubmissionPeriodOpen')) {
    require_once 'api/utils/deadline.php';
}

$status = isSubmissionPeriodOpen($conn);
$info = getAllDeadlineInfo($conn);

echo "<h2>Submission Status Check</h2>";
echo "<p>Submission is currently: " . ($status ? "OPEN" : "CLOSED") . "</p>";
echo "<p>Manual Override: " . ($info['has_manual_override'] ? "YES" : "NO") . "</p>";
if ($info['open_date']) {
    echo "<p>Open Date: " . $info['open_date'] . "</p>";
}
if ($info['close_date']) {
    echo "<p>Close Date: " . $info['close_date'] . "</p>";
}
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

$conn->close();
?>