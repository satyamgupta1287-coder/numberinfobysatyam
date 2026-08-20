<?php
/**
 * Number Info API Endpoint with Multi-Key Validation, Quotas, and Expiration
 * Developer: Satyam Gupta
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/KeyManager.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Extract API Key (supports GET parameter, POST parameter, or HTTP Header)
$apikey = $_GET['apikey'] ?? $_POST['apikey'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

// Check for Bearer token in Authorization header if not found
if (empty($apikey) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $apikey = $matches[1];
    }
}

// 2. Validate API Key & Consume Quota
$validation = KeyManager::validateAndConsume($apikey);

if (!$validation['valid']) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error_code" => $validation['error_code'] ?? "AUTH_ERROR",
        "message" => $validation['message'] ?? "Authentication failed",
        "developer" => API_DEVELOPER
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Extract & Validate Phone Number
$number = $_GET['number'] ?? $_POST['number'] ?? '';

if (empty($number)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error_code" => "MISSING_NUMBER",
        "message" => "Please provide a valid phone number",
        "example" => "?apikey=" . htmlspecialchars($apikey) . "&number=9570187989",
        "key_info" => $validation['key_info'],
        "developer" => API_DEVELOPER
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$cleanNumber = preg_replace('/\D/', '', $number);

if (strlen($cleanNumber) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error_code" => "INVALID_NUMBER_FORMAT",
        "message" => "Phone number is too short or invalid",
        "developer" => API_DEVELOPER
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Request Upstream Service
$url = UPSTREAM_API_URL . urlencode($cleanNumber);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: */*'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        "success" => false,
        "error_code" => "UPSTREAM_CONNECT_FAILED",
        "message" => "Failed to connect to upstream server",
        "error" => $curlError,
        "developer" => API_DEVELOPER
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        "success" => false,
        "error_code" => "UPSTREAM_HTTP_ERROR",
        "message" => "Upstream server returned HTTP error",
        "http_code" => $httpCode,
        "developer" => API_DEVELOPER
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Response Processing & Filtering
$data = json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE) {
    $rawText = isset($data['result']) ? $data['result'] : $data;
    if (is_array($rawText)) {
        $rawText = implode("\n", $rawText);
    }
} else {
    $rawText = trim($response);
}

// Convert <br> tags to newlines
$cleanResponse = preg_replace('/<br\s*\/?>/i', "\n", $rawText);

// Strip extra HTML tags
$cleanResponse = strip_tags($cleanResponse);

// Split into lines
$linesArray = preg_split('/\r\n|\r|\n/', trim($cleanResponse));

// Filter unwanted lines (empty lines, dashes/underscores, BUY API, SUPPORT)
$filteredArray = array_filter($linesArray, function($line) {
    $line = trim($line);
    
    if (empty($line)) return false;
    if (preg_match('/^[-_]+$/', $line)) return false;
    if (stripos($line, 'BUY API') !== false) return false;
    if (stripos($line, 'SUPPORT') !== false) return false;
    
    return true;
});

$finalResult = array_values($filteredArray);

// 6. Return Structured Success Response
echo json_encode([
    "success" => true,
    "developer" => API_DEVELOPER,
    "credit" => API_CREDIT,
    "key_info" => $validation['key_info'],
    "result" => $finalResult
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
