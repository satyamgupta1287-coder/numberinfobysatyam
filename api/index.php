<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('API_KEY', 'satyam');

$apikey = $_GET['apikey'] ?? '';

if ($apikey !== API_KEY) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid API Key",
        "developer" => "https://t.me/satyamgupta9999",
        "credit" => "https://t.me/satyamgupta9999",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = $_GET['number'] ?? '';

if (empty($number)) {
    echo json_encode([
        "success" => false,
        "message" => "Please provide number",
        "example" => "?apikey=satyam&number=9570187989",
        "developer" => "https://t.me/satyamgupta9999",
        "credit" => "https://t.me/osintsatyam",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = preg_replace('/\D/', '', $number);

/* 
 * URL ko http se https me change kiya gaya hai 
 */
$url = "https://num-info-advance-shadow-hex.site.je/?api_key=fuckyou&mobile=" . urlencode($number);

$ch = curl_init();

// Bot protection bypass karne ke liye strong User-Agent add kiya hai
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch data (HTTP $httpCode)",
        "developer" => "https://t.me/satyamosint",
        "credit" => "https://t.me/satyamgupta9999",
        "private" => "https://t.me/satyamosint"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode($response, true);

// Agar JSON decode fail hota hai, toh ab ye batayega ki server ne asal me kya bheja tha
if (!$data || !isset($data['data'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid response from server",
        "debug_response" => substr(strip_tags($response), 0, 200), // Ye response print karega debug ke liye
        "developer" => "https://t.me/osintbysatyam",
        "credit" => "satyamgupta",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/* 
 * DATA CLEANUP & SWAPPING LOGIC
 */
$cleanData = $data['data'];

// 1. Father name aur Full name ko swap karna
if (isset($cleanData['personal_info'])) {
    $wrongFullName = $cleanData['personal_info']['full_name'] ?? '';
    $wrongFatherName = $cleanData['personal_info']['father_name'] ?? '';

    $cleanData['personal_info']['full_name'] = $wrongFatherName;
    $cleanData['personal_info']['father_name'] = $wrongFullName;
    
    if(isset($cleanData['personal_info']['Developer'])){
        unset($cleanData['personal_info']['Developer']);
    }
}

// 2. Baki jagah se faltu tags hatana
if (isset($cleanData['contact_info']['Developer'])) {
    unset($cleanData['contact_info']['Developer']);
}
if (isset($cleanData['other_info']['Developer'])) {
    unset($cleanData['other_info']['Developer']);
}
if (isset($cleanData['Developer'])) {
    unset($cleanData['Developer']);
}

echo json_encode([
    "success" => true,
    "developer" => "Satyam Gupta",
    "credit" => "https://t.me/osintbysatyam",
    "private" => "https://t.me/+14rDlunTEzwwZGY1",
    "result" => $cleanData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
