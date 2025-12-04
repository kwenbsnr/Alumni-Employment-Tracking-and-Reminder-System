<?php
require_once 'connect.php';
require_once 'api/notification/notif_service.php';

echo "<h2>Testing Semiannual Update Notifications</h2>";

// Get some approved alumni for testing
$query = "
    SELECT u.user_id, u.email, ap.last_profile_update, 
           TIMESTAMPDIFF(MONTH, ap.last_profile_update, NOW()) as months_since_update
    FROM users u 
    INNER JOIN alumni_profile ap ON u.user_id = ap.user_id 
    WHERE u.role = 'alumni'
    AND ap.submission_status = 'Approved'
    LIMIT 5
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>User ID</th>
                <th>Email</th>
                <th>Last Update</th>
                <th>Months Since</th>
                <th>Needs Update?</th>
                <th>Test</th>
            </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $needs_update = ($row['months_since_update'] >= 6) ? 'YES' : 'NO';
        
        echo "<tr>
                <td>{$row['user_id']}</td>
                <td>{$row['email']}</td>
                <td>{$row['last_profile_update']}</td>
                <td>{$row['months_since_update']}</td>
                <td>{$needs_update}</td>
                <td>";
        
        if ($needs_update == 'YES') {
            $test_result = send_profile_update_reminder($conn, $row['user_id']);
            echo $test_result['success'] ? '✅ Sent' : '❌ Failed: ' . ($test_result['error'] ?? 'Unknown');
        } else {
            echo 'Not needed (< 6 months)';
        }
        
        echo "</td></tr>";
    }
    
    echo "</table>";
} else {
    echo "No approved alumni found.";
}

// Test batch function
echo "<h3>Batch Test</h3>";
$batch_result = send_semiannual_updates_to_all($conn);
echo "Would send to: " . $batch_result['total_sent'] . " alumni<br>";
echo "Check the error log for details.";

$conn->close();
?>