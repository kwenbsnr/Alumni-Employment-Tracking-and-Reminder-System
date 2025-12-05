<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

function nominatimGeocode($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=5";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Alumni-Tracking-System/1.0\r\n",
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === FALSE) {
        return ['error' => 'Geocoding service unavailable'];
    }
    return json_decode($response, true);
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
        return ['error' => 'Reverse geocoding service unavailable'];
    }
    return json_decode($response, true);
}

// Enhanced city extraction function
function extractCity($address) {
    if (!$address || !is_array($address)) return '';
    
    // Try different possible city fields in order of preference
    $cityFields = ['city', 'town', 'village', 'municipality', 'locality', 'county', 'suburb'];
    
    foreach ($cityFields as $field) {
        if (isset($address[$field]) && !empty(trim($address[$field]))) {
            return trim($address[$field]);
        }
    }
    
    return '';
}

// Extract state/province
function extractState($address) {
    if (!$address || !is_array($address)) return '';
    
    $stateFields = ['state', 'province', 'region', 'state_district'];
    
    foreach ($stateFields as $field) {
        if (isset($address[$field]) && !empty(trim($address[$field]))) {
            return trim($address[$field]);
        }
    }
    
    return '';
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
        echo json_encode(['success' => true, 'results' => $results]);
        
    } elseif ($action === 'reverse') {
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
        
        // Enhanced response with better extraction
        $address_data = $result['address'];
        $city = extractCity($address_data);
        $state = extractState($address_data);
        $country = $address_data['country'] ?? '';
        
        // Create formatted address
        $formatted_parts = [];
        if ($city) $formatted_parts[] = $city;
        if ($state) $formatted_parts[] = $state;
        if ($country) $formatted_parts[] = $country;
        $formatted_address = implode(', ', $formatted_parts);
        
        // If city still empty, try to extract from display_name
        if (empty($city) && isset($result['display_name'])) {
            $parts = explode(',', $result['display_name']);
            if (count($parts) > 0) {
                $possible_city = trim($parts[0]);
                if (!empty($possible_city) && !is_numeric($possible_city[0])) {
                    $city = $possible_city;
                    $formatted_parts[0] = $city; // Update formatted parts
                    $formatted_address = implode(', ', $formatted_parts);
                }
            }
        }
        
        $response = [
            'success' => true,
            'latitude' => (float)$lat,
            'longitude' => (float)$lon,
            'address' => [
                'city' => $city,
                'state_province' => $state,
                'country' => $country,
                'formatted_address' => $formatted_address,
                'full_display' => $result['display_name'] ?? ''
            ]
        ];
        
        echo json_encode($response);
        
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}