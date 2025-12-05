<?php
header('Content-Type: text/plain');

$test_locations = [
    ['lat' => 14.5995, 'lon' => 120.9842, 'name' => 'Manila'],
    ['lat' => 40.7128, 'lon' => -74.0060, 'name' => 'New York'],
    ['lat' => 51.5074, 'lon' => -0.1278, 'name' => 'London']
];

foreach ($test_locations as $loc) {
    // Use correct relative path
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/Alumni-Employment-Tracking-and-Reminder-System/api/geocode.php?action=reverse&lat={$loc['lat']}&lon={$loc['lon']}";
    
    echo "Testing {$loc['name']}:\n";
    echo "URL: $url\n";
    
    $response = @file_get_contents($url);
    
    if ($response === FALSE) {
        echo "ERROR: Could not access geocode.php\n";
        echo "Check if file exists at: api/geocode.php\n";
        echo "Current directory: " . __DIR__ . "\n";
        echo "API file should be at: " . __DIR__ . "/api/geocode.php\n";
        
        // Check if file exists
        $api_file = __DIR__ . '/api/geocode.php';
        if (file_exists($api_file)) {
            echo "✓ File exists at: $api_file\n";
            echo "Try accessing directly: http://localhost/Alumni-Employment-Tracking-and-Reminder-System/api/geocode.php?action=reverse&lat=14.5995&lon=120.9842\n";
        } else {
            echo "✗ File NOT found at: $api_file\n";
        }
    } else {
        $data = json_decode($response, true);
        echo "Success! Response:\n";
        print_r($data);
        echo "\n";
        echo "City found: " . ($data['address']['city'] ?? 'NOT FOUND') . "\n";
        echo "State found: " . ($data['address']['state_province'] ?? 'NOT FOUND') . "\n";
        echo "Country found: " . ($data['address']['country'] ?? 'NOT FOUND') . "\n";
    }
    echo "---\n";
}

// Also test direct access
echo "\n=== Direct API Test ===\n";
$direct_url = "http://localhost/Alumni-Employment-Tracking-and-Reminder-System/api/geocode.php?action=reverse&lat=14.5995&lon=120.9842";
echo "Try this URL in browser: $direct_url\n";
?>