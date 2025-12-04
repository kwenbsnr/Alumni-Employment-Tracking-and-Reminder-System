<?php
// api/geocode.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Set a proper user agent for Nominatim
ini_set('user_agent', 'Alumni-Tracking-System/1.0 (contact@example.com)');

function makeRequest($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Alumni-Tracking-System/1.0',
                'Accept: application/json',
                'Accept-Language: en-US,en;q=0.9'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        $error = error_get_last();
        return ['error' => $error['message'] ?? 'Request failed'];
    }
    
    return json_decode($response, true);
}

function geocodeAddress($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . 
           urlencode($address) . "&addressdetails=1&limit=5";
    
    return makeRequest($url);
}

function reverseGeocode($lat, $lon, $zoom = 18) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . 
           $lat . "&lon=" . $lon . "&zoom=" . $zoom . "&addressdetails=1";
    
    return makeRequest($url);
}

try {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'geocode':
                if (!isset($_GET['address']) || empty(trim($_GET['address']))) {
                    echo json_encode(['error' => 'Address parameter is required']);
                    exit;
                }
                $results = geocodeAddress($_GET['address']);
                echo json_encode($results);
                break;
                
            case 'reverse':
                if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
                    echo json_encode(['error' => 'Latitude and longitude parameters are required']);
                    exit;
                }
                $lat = floatval($_GET['lat']);
                $lon = floatval($_GET['lon']);
                $zoom = isset($_GET['zoom']) ? intval($_GET['zoom']) : 18;
                $result = reverseGeocode($lat, $lon, $zoom);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } else {
        echo json_encode(['error' => 'No action specified']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>