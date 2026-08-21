<?php
/**
 * KeyManager - High-performance API Key Manager with Cloud Database (Upstash Redis), Quotas, and Live Activity Logging
 * Developer: Satyam Gupta
 */

require_once __DIR__ . '/config.php';

class KeyManager {
    
    /**
     * Check if Upstash Redis Cloud is configured
     */
    private static function isCloudEnabled() {
        return defined('UPSTASH_REDIS_REST_URL') && !empty(UPSTASH_REDIS_REST_URL)
            && defined('UPSTASH_REDIS_REST_TOKEN') && !empty(UPSTASH_REDIS_REST_TOKEN);
    }

    /**
     * Fetch from Upstash Cloud Redis by Key
     */
    private static function fetchFromCloud($key = 'numberinfo_keys') {
        if (!self::isCloudEnabled()) return null;
        
        $url = rtrim(UPSTASH_REDIS_REST_URL, '/') . '/get/' . urlencode($key);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . UPSTASH_REDIS_REST_TOKEN,
                'Accept: application/json'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return null;
        $data = json_decode($response, true);
        if (isset($data['result']) && !empty($data['result'])) {
            $decoded = json_decode($data['result'], true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Save to Upstash Cloud Redis by Key
     */
    private static function sendToCloud($key, $data) {
        if (!self::isCloudEnabled()) return false;

        $url = rtrim(UPSTASH_REDIS_REST_URL, '/') . '/set/' . urlencode($key);
        $jsonPayload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . UPSTASH_REDIS_REST_TOKEN,
                'Content-Type: text/plain'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return ($response !== false);
    }

    /**
     * Ensure local storage cache folder exists
     */
    private static function initStorage() {
        if (!file_exists(DATA_DIR)) {
            @mkdir(DATA_DIR, 0777, true);
            @file_put_contents(DATA_DIR . '/.htaccess', "Deny from all\n");
            @file_put_contents(DATA_DIR . '/index.php', "<?php http_response_code(403); exit('Forbidden'); ?>");
        }
    }

    /**
     * Load all keys from Cloud (Upstash) or Local JSON with auto-initialization
     */
    public static function getAllKeys() {
        global $STATIC_API_KEYS;
        self::initStorage();

        $keys = null;

        // 1. Try fetching from Cloud first
        if (self::isCloudEnabled()) {
            $keys = self::fetchFromCloud('numberinfo_keys');
        }

        // 2. If Cloud was empty or disabled, check local storage
        if (!is_array($keys) && file_exists(KEYS_FILE)) {
            $fp = @fopen(KEYS_FILE, 'r');
            if ($fp) {
                flock($fp, LOCK_SH);
                $content = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $keys = $decoded;
                }
            }
        }

        // 3. If still empty, initialize default legacy key
        if (!is_array($keys)) {
            $keys = [
                DEFAULT_LEGACY_KEY => [
                    'key' => DEFAULT_LEGACY_KEY,
                    'owner' => 'Satyam (Master Default)',
                    'status' => 'active',          // active, suspended, expired
                    'request_limit' => -1,          // -1 means unlimited
                    'requests_used' => 0,
                    'expires_at' => null,           // null means lifetime / never expires
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_used_at' => null
                ]
            ];
            self::saveAllKeys($keys);
        }

        // Always ensure default legacy key exists
        if (!isset($keys[DEFAULT_LEGACY_KEY])) {
            $keys[DEFAULT_LEGACY_KEY] = [
                'key' => DEFAULT_LEGACY_KEY,
                'owner' => 'Satyam (Master Default)',
                'status' => 'active',
                'request_limit' => -1,
                'requests_used' => 0,
                'expires_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'last_used_at' => null
            ];
        }

        // Merge any static keys from config
        if (!empty($STATIC_API_KEYS) && is_array($STATIC_API_KEYS)) {
            foreach ($STATIC_API_KEYS as $k => $v) {
                if (!isset($keys[$k])) {
                    $keys[$k] = array_merge([
                        'key' => $k,
                        'owner' => 'Config Key',
                        'status' => 'active',
                        'request_limit' => -1,
                        'requests_used' => 0,
                        'expires_at' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'last_used_at' => null
                    ], $v);
                }
            }
        }

        return $keys;
    }

    /**
     * Save all keys to Cloud (Upstash) and Local JSON storage
     */
    private static function saveAllKeys(array $keys) {
        self::initStorage();
        
        // 1. Save to Cloud if enabled
        if (self::isCloudEnabled()) {
            self::sendToCloud('numberinfo_keys', $keys);
        }

        // 2. Save to local file cache
        $fp = @fopen(KEYS_FILE, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            return true;
        }

        return self::isCloudEnabled();
    }

    /**
     * Generate a secure, unique API key string
     */
    public static function generateKeyString($prefix = 'satyam_') {
        return $prefix . bin2hex(random_bytes(8));
    }

    /**
     * Helper to compute expiration datetime from custom inputs
     */
    public static function parseExpiry($type, $value) {
        if ($type === 'lifetime' || empty($type) || ($type !== 'datetime' && (!is_numeric($value) || $value <= 0))) {
            return null;
        }

        if ($type === 'minutes') {
            return date('Y-m-d H:i:s', strtotime("+{$value} minutes"));
        } elseif ($type === 'hours') {
            return date('Y-m-d H:i:s', strtotime("+{$value} hours"));
        } elseif ($type === 'days') {
            return date('Y-m-d H:i:s', strtotime("+{$value} days"));
        } elseif ($type === 'datetime') {
            $ts = strtotime($value);
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        }

        return null;
    }

    /**
     * Create a new API Key
     */
    public static function createKey($owner, $limit = -1, $expiresAt = null, $customKey = null) {
        $keys = self::getAllKeys();
        
        $keyString = !empty($customKey) ? trim($customKey) : self::generateKeyString();

        if (isset($keys[$keyString])) {
            return [
                'success' => false,
                'message' => 'An API key with this string already exists.'
            ];
        }

        $keyData = [
            'key' => $keyString,
            'owner' => trim($owner) ?: 'User',
            'status' => 'active',
            'request_limit' => (int)$limit,
            'requests_used' => 0,
            'expires_at' => !empty($expiresAt) ? $expiresAt : null,
            'created_at' => date('Y-m-d H:i:s'),
            'last_used_at' => null
        ];

        $keys[$keyString] = $keyData;
        self::saveAllKeys($keys);

        return [
            'success' => true,
            'message' => 'API Key created successfully.',
            'key' => $keyData
        ];
    }

    /**
     * Validate an API Key and increment its usage counter atomically
     */
    public static function validateAndConsume($keyString) {
        $keyString = trim($keyString);
        if (empty($keyString)) {
            return [
                'valid' => false,
                'error_code' => 'MISSING_API_KEY',
                'message' => 'API Key is required. Pass ?apikey=YOUR_KEY'
            ];
        }

        $keys = self::getAllKeys();

        if (!isset($keys[$keyString])) {
            return [
                'valid' => false,
                'error_code' => 'INVALID_API_KEY',
                'message' => 'Invalid API Key provided.'
            ];
        }

        $keyData = $keys[$keyString];

        // 1. Check if key is suspended
        if ($keyData['status'] === 'suspended') {
            return [
                'valid' => false,
                'error_code' => 'KEY_SUSPENDED',
                'message' => 'This API key has been suspended by the administrator.'
            ];
        }

        // 2. Check if key is expired
        if (!empty($keyData['expires_at'])) {
            $expiryTimestamp = strtotime($keyData['expires_at']);
            if (time() > $expiryTimestamp) {
                $keys[$keyString]['status'] = 'expired';
                self::saveAllKeys($keys);

                return [
                    'valid' => false,
                    'error_code' => 'KEY_EXPIRED',
                    'message' => 'This API key expired on ' . $keyData['expires_at'] . '. Please renew your plan.'
                ];
            }
        }

        // 3. Check if request limit is exceeded
        if ($keyData['request_limit'] > -1 && $keyData['requests_used'] >= $keyData['request_limit']) {
            return [
                'valid' => false,
                'error_code' => 'LIMIT_EXCEEDED',
                'message' => 'API request limit reached (' . $keyData['requests_used'] . '/' . $keyData['request_limit'] . '). Please contact admin for more quota.'
            ];
        }

        // 4. Increment usage counter and update last used time
        $keys[$keyString]['requests_used']++;
        $keys[$keyString]['last_used_at'] = date('Y-m-d H:i:s');
        self::saveAllKeys($keys);

        $updatedKey = $keys[$keyString];
        $remaining = ($updatedKey['request_limit'] === -1) ? 'Unlimited' : max(0, $updatedKey['request_limit'] - $updatedKey['requests_used']);

        return [
            'valid' => true,
            'key_info' => [
                'key' => $updatedKey['key'],
                'owner' => $updatedKey['owner'],
                'requests_used' => $updatedKey['requests_used'],
                'request_limit' => ($updatedKey['request_limit'] === -1) ? 'Unlimited' : $updatedKey['request_limit'],
                'requests_remaining' => $remaining,
                'expires_at' => $updatedKey['expires_at'] ?? 'Never (Lifetime)'
            ]
        ];
    }

    /**
     * Update an existing API key (Owner, Limit, Status, Expiry, Usage)
     */
    public static function updateKey($keyString, array $updates) {
        $keys = self::getAllKeys();
        if (!isset($keys[$keyString])) {
            return ['success' => false, 'message' => 'Key not found.'];
        }

        if (isset($updates['owner'])) {
            $keys[$keyString]['owner'] = trim($updates['owner']);
        }
        if (isset($updates['status']) && in_array($updates['status'], ['active', 'suspended', 'expired'])) {
            $keys[$keyString]['status'] = $updates['status'];
        }
        if (isset($updates['request_limit'])) {
            $keys[$keyString]['request_limit'] = (int)$updates['request_limit'];
        }
        if (array_key_exists('expires_at', $updates)) {
            $keys[$keyString]['expires_at'] = !empty($updates['expires_at']) ? $updates['expires_at'] : null;
        }
        if (isset($updates['requests_used'])) {
            $keys[$keyString]['requests_used'] = max(0, (int)$updates['requests_used']);
        }

        self::saveAllKeys($keys);
        return ['success' => true, 'message' => 'Key updated successfully.', 'key' => $keys[$keyString]];
    }

    /**
     * Reset usage counter for an API key
     */
    public static function resetUsage($keyString) {
        $keys = self::getAllKeys();
        if (!isset($keys[$keyString])) {
            return ['success' => false, 'message' => 'Key not found.'];
        }

        $keys[$keyString]['requests_used'] = 0;
        self::saveAllKeys($keys);
        return ['success' => true, 'message' => 'Usage count reset to 0.', 'key' => $keys[$keyString]];
    }

    /**
     * Delete an API key
     */
    public static function deleteKey($keyString) {
        $keys = self::getAllKeys();
        if (!isset($keys[$keyString])) {
            return ['success' => false, 'message' => 'Key not found.'];
        }

        unset($keys[$keyString]);
        self::saveAllKeys($keys);
        return ['success' => true, 'message' => 'Key deleted successfully.'];
    }

    /**
     * Toggle status (active <-> suspended)
     */
    public static function toggleStatus($keyString) {
        $keys = self::getAllKeys();
        if (!isset($keys[$keyString])) {
            return ['success' => false, 'message' => 'Key not found.'];
        }

        $newStatus = ($keys[$keyString]['status'] === 'active') ? 'suspended' : 'active';
        $keys[$keyString]['status'] = $newStatus;
        self::saveAllKeys($keys);

        return ['success' => true, 'message' => "Key status changed to {$newStatus}.", 'status' => $newStatus];
    }

    // ==========================================
    // 📊 LIVE SEARCH & NUMBER REQUEST LOGGING
    // ==========================================

    /**
     * Log an API request with the searched phone number, owner, IP, and timestamp
     */
    public static function logRequest($keyString, $owner, $searchedNumber, $status = 'success', $clientIp = '', $httpCode = 200) {
        self::initStorage();

        $logEntry = [
            'id' => uniqid('log_'),
            'time' => date('Y-m-d H:i:s'),
            'number' => $searchedNumber,
            'key' => $keyString,
            'owner' => $owner ?: 'Unknown',
            'ip' => $clientIp ?: 'Unknown',
            'status' => $status,
            'http_code' => $httpCode
        ];

        $logs = self::getLogs(MAX_LOGS_COUNT);
        array_unshift($logs, $logEntry); // Add newest on top

        // Trim logs to MAX_LOGS_COUNT
        if (count($logs) > MAX_LOGS_COUNT) {
            $logs = array_slice($logs, 0, MAX_LOGS_COUNT);
        }

        // Save to Cloud
        if (self::isCloudEnabled()) {
            self::sendToCloud('numberinfo_logs', $logs);
        }

        // Save to local cache
        $fp = @fopen(LOGS_FILE, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        return true;
    }

    /**
     * Get all search logs
     */
    public static function getLogs($limit = 100) {
        self::initStorage();
        $logs = null;

        if (self::isCloudEnabled()) {
            $logs = self::fetchFromCloud('numberinfo_logs');
        }

        if (!is_array($logs) && file_exists(LOGS_FILE)) {
            $fp = @fopen(LOGS_FILE, 'r');
            if ($fp) {
                flock($fp, LOCK_SH);
                $content = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $logs = $decoded;
                }
            }
        }

        if (!is_array($logs)) {
            $logs = [];
        }

        return array_slice($logs, 0, $limit);
    }

    /**
     * Clear all search logs
     */
    public static function clearLogs() {
        self::initStorage();
        if (self::isCloudEnabled()) {
            self::sendToCloud('numberinfo_logs', []);
        }

        $fp = @fopen(LOGS_FILE, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode([], JSON_PRETTY_PRINT));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        return ['success' => true, 'message' => 'Search activity logs cleared successfully.'];
    }
}
