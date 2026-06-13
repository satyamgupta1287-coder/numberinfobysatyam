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
        "example" => "?apikey=satyamgupta&number=8651369226",
        "developer" => "https://t.me/satyamgupta9999",
        "credit" => "https://t.me/osintsatyam",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = preg_replace('/\D/', '', $number);

/* New API */
$url = "https://all-leak-check-api.vercel.app/api/search?query=91" . urlencode($number);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch data",
        "developer" => "https://t.me/satyamosint",
        "credit" => "https://t.me/satyamgupta9999",
        "private" => "https://t.me/satyamosint"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode($response, true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid response from server",
        "developer" => "https://t.me/osintbysatyam",
        "credit" => "satyamgupta",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Hide developer/website/channel fields from API response */
$hideFields = [
    'developer', 'Developer', 'DEVELOPER',
    'website', 'Website', 'WEBSITE',
    'channel', 'Channel', 'CHANNEL'
];

foreach ($hideFields as $field) {
    unset($data[$field]);
}

/* Fix swapped Full name and Father Name in string values */
if (isset($data['result']) && is_array($data['result'])) {
    if (isset($data['result']['data']) && is_array($data['result']['data'])) {
        foreach ($data['result']['data'] as &$item) {
            foreach ($item as $key => &$value) {
                if (is_string($value)) {
                    // Swap "Full name: X" with "Father Name: Y"
                    if (strpos($value, 'Full name:') !== false && strpos($value, 'Father Name:') !== false) {
                        // Extract values
                        preg_match('/Full name:\s*([^,]+)/', $value, $fullNameMatch);
                        preg_match('/Father Name:\s*([^,]+)/', $value, $fatherNameMatch);
                        
                        if (!empty($fullNameMatch[1]) && !empty($fatherNameMatch[1])) {
                            $fullNameValue = trim($fullNameMatch[1]);
                            $fatherNameValue = trim($fatherNameMatch[1]);
                            
                            // Swap them
                            $value = str_replace('Full name: ' . $fullNameValue, 'Full name: ' . $fatherNameValue, $value);
                            $value = str_replace('Father Name: ' . $fatherNameValue, 'Father Name: ' . $fullNameValue, $value);
                        }
                    }
                }
            }
            unset($value);
        }
        unset($item);
    }
}

echo json_encode([
    "success" => true,
    "developer" => "Satyam Gupta",
    "credit" => "https://t.me/osintbysatyam",
    "private" => "https://t.me/+14rDlunTEzwwZGY1",
    "result" => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
