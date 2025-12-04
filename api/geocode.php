<?php
require_once '../connect.php';
header('Content-Type: application/json');

function geocodeAddress($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "AlumTrak-System/1.0");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function reverseGeocode($lat, $lon) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $lat . "&lon=" . $lon;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "AlumTrak-System/1.0");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Handle geocoding request (address to coordinates)
if ($_GET['action'] == 'geocode' && isset($_GET['address'])) {
    $results = geocodeAddress($_GET['address']);
    echo json_encode($results);
}

// Handle reverse geocoding request (coordinates to address)
if ($_GET['action'] == 'reverse' && isset($_GET['lat']) && isset($_GET['lon'])) {
    $result = reverseGeocode($_GET['lat'], $_GET['lon']);
    echo json_encode($result);
}
?>