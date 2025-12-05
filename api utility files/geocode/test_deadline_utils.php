<?php

require_once 'connect.php';
require_once 'api/utils/deadline.php';

echo "<h2>Testing Deadline Utility Functions</h2>";

// Test all functions
$allInfo = getAllDeadlineInfo($conn);

echo "<h3>Deadline Information:</h3>";
echo "Formatted Deadline: " . $allInfo['formatted_date'] . "<br>";
echo "Days Remaining: " . $allInfo['days_remaining'] . "<br>";
echo "Submission Open: " . ($allInfo['is_open'] ? "✅ YES" : "❌ NO") . "<br>";
echo "Urgency Level: " . $allInfo['urgency_level'] . "<br>";
echo "Manual Override: " . ($allInfo['has_manual_override'] ? "✅ ON" : "❌ OFF") . "<br>";

echo "<h3>Individual Function Tests:</h3>";
echo "Deadline Date: " . getDeadlineDate($conn) . "<br>";
echo "Formatted: " . getFormattedDeadline($conn) . "<br>";
echo "Days Until: " . calculateDaysUntilDeadline($conn) . "<br>";
echo "Is Approaching (7 days): " . (isDeadlineApproaching($conn) ? "YES" : "NO") . "<br>";
echo "Is Approaching (30 days): " . (isDeadlineApproaching($conn, 30) ? "YES" : "NO") . "<br>";

// Test alumni functions
$onTimeCount = getOnTimeSubmissionsCount($conn);
echo "<h3>Submission Statistics:</h3>";
echo "On-time Submissions: " . $onTimeCount . "<br>";

$pastDeadlineAlumni = getAlumniPastDeadline($conn);
echo "Alumni Past Deadline: " . count($pastDeadlineAlumni) . "<br>";

if (count($pastDeadlineAlumni) > 0) {
    echo "<h4>Sample Alumni Past Deadline:</h4>";
    foreach (array_slice($pastDeadlineAlumni, 0, 3) as $alumni) {
        echo "- " . $alumni['full_name'] . " (" . $alumni['batch_year'] . ")<br>";
    }
}

$conn->close();