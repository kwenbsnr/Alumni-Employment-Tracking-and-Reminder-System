<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// Debug mode
$debug = isset($_GET['debug']);
if ($debug) {
    error_log("=== GEOCODE API CALLED ===");
    error_log("Action: " . ($_GET['action'] ?? 'none'));
    error_log("Lat: " . ($_GET['lat'] ?? 'none'));
    error_log("Lon: " . ($_GET['lon'] ?? 'none'));
}

function nominatimReverseGeocode($lat, $lon, $zoom = 18) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=$zoom&addressdetails=1";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Alumni-Tracking-System/1.0\r\n",
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === FALSE) {
        error_log("Nominatim request failed for lat=$lat, lon=$lon");
        return ['error' => 'Reverse geocoding service unavailable'];
    }
    
    $data = json_decode($response, true);
    error_log("Nominatim raw response: " . json_encode($data));
    return $data;
}

// Enhanced city extraction function
function extractCity($address) {
    if (!$address || !is_array($address)) {
        error_log("extractCity: No address data");
        return '';
    }
    
    error_log("extractCity input: " . json_encode($address));
    
    // Try different possible city fields in order of preference
    $cityFields = ['city', 'town', 'village', 'municipality', 'locality', 'county', 'suburb'];
    
    foreach ($cityFields as $field) {
        if (isset($address[$field]) && !empty(trim($address[$field]))) {
            $city = trim($address[$field]);
            error_log("extractCity found in '$field': '$city'");
            return $city;
        }
    }
    
    error_log("extractCity: No city found in any field");
    return '';
}

// Main logic
$action = $_GET['action'] ?? '';

try {
    if ($action === 'reverse') {
        $lat = $_GET['lat'] ?? '';
        $lon = $_GET['lon'] ?? '';
        
        if (empty($lat) || empty($lon)) {
            throw new Exception('Latitude and longitude parameters are required');
        }
        
        // Validate coordinates
        if (!is_numeric($lat) || !is_numeric($lon) || 
            $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new Exception('Invalid coordinates');
        }
        
        $result = nominatimReverseGeocode($lat, $lon);
        
        if (isset($result['error'])) {
            throw new Exception($result['error']);
        }
        
        if (!isset($result['address'])) {
            throw new Exception('No address data found for this location');
        }
        
        $address_data = $result['address'];
        $city = extractCity($address_data);
        $state = $address_data['state'] ?? $address_data['province'] ?? $address_data['region'] ?? '';
        $country = $address_data['country'] ?? '';
        
        // Create formatted address
        $formatted_parts = [];
        if ($city) $formatted_parts[] = $city;
        if ($state) $formatted_parts[] = $state;
        if ($country) $formatted_parts[] = $country;
        $formatted_address = implode(', ', $formatted_parts);
        
        $response = [
            'success' => true,
            'latitude' => (float)$lat,
            'longitude' => (float)$lon,
            'address' => [
                'city' => $city,
                'state_province' => $state,
                'country' => $country,
                'formatted_address' => $formatted_address,
                'full_display' => $result['display_name'] ?? '',
                'raw_address_data' => $address_data // For debugging
            ]
        ];
        
        error_log("Final response: " . json_encode($response));
        echo json_encode($response);
        
    } elseif ($action === 'geocode') {
        // Forward geocoding logic here
        $address = $_GET['address'] ?? '';
        if (empty($address)) {
            throw new Exception('Address parameter is required');
        }
        
        $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=5";
        
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Alumni-Tracking-System/1.0\r\n",
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            throw new Exception('Geocoding service unavailable');
        }
        
        $results = json_decode($response, true);
        echo json_encode(['success' => true, 'results' => $results]);
        
    } else {
        throw new Exception('Invalid action. Use "reverse" or "geocode"');
    }
} catch (Exception $e) {
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'action' => $action,
            'lat' => $_GET['lat'] ?? '',
            'lon' => $_GET['lon'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    error_log("Geocode API error: " . $e->getMessage());
    echo json_encode($error_response);
}