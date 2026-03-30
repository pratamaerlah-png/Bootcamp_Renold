<?php
// save_location.php

require_once 'koneksi_db.php';

// --- Fungsi untuk mendapatkan IP Asli Pengunjung (diambil dari index.php) ---
function get_real_ip() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip_array = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim(reset($ip_array));
    }
    return $_SERVER['REMOTE_ADDR'];
}

// Hanya proses jika request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['latitude']) || !isset($input['longitude'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing coordinates.']);
    exit;
}

$lat = $input['latitude'];
$lon = $input['longitude'];

// --- Reverse Geocoding menggunakan OpenStreetMap (Nominatim) ---
$city = null;
$province = null;

$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lon}&zoom=10&addressdetails=1";

// Nominatim memerlukan User-Agent yang valid
$opts = [
    "http" => [
        "header" => "User-Agent: PratamaDigitect/1.0 (https://pratamadigitect.com)\r\n"
    ]
];
$context = stream_context_create($opts);
$geo_response = @file_get_contents($url, false, $context);

if ($geo_response) {
    $geo_data = json_decode($geo_response, true);
    if (isset($geo_data['address'])) {
        $city = $geo_data['address']['city'] ?? $geo_data['address']['town'] ?? $geo_data['address']['county'] ?? null;
        $province = $geo_data['address']['state'] ?? null;
    }
}

// Update database
$visitor_ip = get_real_ip();
$today_date = date('Y-m-d');

$stmt = $conn->prepare("UPDATE visitor_stats SET city = ?, province = ? WHERE ip_address = ? AND visit_date = ?");
$stmt->bind_param("ssss", $city, $province, $visitor_ip, $today_date);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'city' => $city, 'province' => $province]);
?>