<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('API_KEY', 'satyamm');

$apikey = $_GET['apikey'] ?? '';

if ($apikey !== API_KEY) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid API Key",
        "developer" => "Satyam Gupta"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = $_GET['number'] ?? '';

if (empty($number)) {
    echo json_encode([
        "success" => false,
        "message" => "Please provide number",
        "example" => "?apikey=satyamm&number=9570187989"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = preg_replace('/\D/', '', $number);

/*
 * Working upstream URL
 * NOTE: yahan extra &key=... nahi lagaya gaya hai
 */
$url = "https://exploitsindia.site/osintcallerbot/number.php?exploits=" . urlencode($number);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
    CURLOPT_HTTPHEADER => [
        'Accept: */*'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);

curl_close($ch);

if ($response === false) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to connect to upstream server",
        "error" => $curlError
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        "success" => false,
        "message" => "Upstream server returned HTTP error",
        "http_code" => $httpCode
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/*
 * Pehle check karo response JSON hai ya nahi.
 */
$data = json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE) {

    // Agar upstream JSON hai
    $finalResult = isset($data['result'])
        ? $data['result']
        : $data;

} else {

    /*
     * Agar upstream plain-text/mock response hai,
     * to usko raw string ke roop mein return karo.
     */
    $finalResult = trim($response);
}

echo json_encode([
    "success" => true,
    "developer" => "Satyam Gupta",
    "credit" => "Satyam Gupta",
    "result" => $finalResult
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
