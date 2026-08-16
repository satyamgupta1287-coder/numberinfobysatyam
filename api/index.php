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
 * Response processing & Filtering
 */
$data = json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE) {
    $rawText = isset($data['result']) ? $data['result'] : $data;
} else {
    $rawText = trim($response);
}

// 1. Agar response me <br> tags hain to unhe newline me badal do
$cleanResponse = preg_replace('/<br\s*\/?>/i', "\n", $rawText);

// 2. Extra HTML tags hata do
$cleanResponse = strip_tags($cleanResponse);

// 3. Newline ke hisaab se array me split kar lo
$linesArray = preg_split('/\r\n|\r|\n/', trim($cleanResponse));

// 4. Unwanted lines ko filter karo (Khali line, underscore, BUY API, SUPPORT)
$filteredArray = array_filter($linesArray, function($line) {
    $line = trim($line);
    
    // Khali space wali lines hatao
    if (empty($line)) return false;
    
    // Sirf dashes (-) ya underscores (_) wali lines hatao
    if (preg_match('/^[-_]+$/', $line)) return false;
    
    // 'BUY API' aur 'SUPPORT' wale keywords filter karo
    if (stripos($line, 'BUY API') !== false) return false;
    if (stripos($line, 'SUPPORT') !== false) return false;
    
    return true;
});

// 5. Array ki indexing theek kar lo
$finalResult = array_values($filteredArray);

echo json_encode([
    "success" => true,
    "developer" => "Satyam Gupta",
    "credit" => "Satyam Gupta",
    "result" => $finalResult
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
