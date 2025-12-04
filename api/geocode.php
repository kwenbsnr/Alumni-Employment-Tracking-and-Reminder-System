<?php
header('Content-Type: application/json');

function nominatimGeocode($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=5";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Alumni-Tracking-System/1.0\r\n"
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return json_decode($response, true);
}

function nominatimReverseGeocode($lat, $lon, $zoom = 18) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=$zoom";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Alumni-Tracking-System/1.0\r\n"
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return json_decode($response, true);
}

// Main logic
$action = $_GET['action'] ?? '';

try {
    if ($action === 'geocode') {
        $address = $_GET['address'] ?? '';
        if (empty($address)) {
            throw new Exception('Address parameter is required');
        }
        
        $results = nominatimGeocode($address);
        echo json_encode($results);
        
    } elseif ($action === 'reverse') {
        $lat = $_GET['lat'] ?? '';
        $lon = $_GET['lon'] ?? '';
        
        if (empty($lat) || empty($lon)) {
            throw new Exception('Latitude and longitude parameters are required');
        }
        
        $result = nominatimReverseGeocode($lat, $lon);
        echo json_encode($result);
        
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}