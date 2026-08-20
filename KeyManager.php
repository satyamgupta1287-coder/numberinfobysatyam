<?php
/**
 * KeyManager - High-performance API Key Manager with Quota and Expiration Support
 * Developer: Satyam Gupta
 */

require_once __DIR__ . '/config.php';

class KeyManager {
    
    /**
     * Ensure the data directory and keys file exist with default initialization
     */
    private static function initStorage() {
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
            // Protect data folder from web access
            file_put_contents(DATA_DIR . '/.htaccess', "Deny from all\n");
            file_put_contents(DATA_DIR . '/index.php', "<?php http_response_code(403); exit('Forbidden'); ?>");
        }

        if (!file_exists(KEYS_FILE)) {
            $defaultKeys = [
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
            file_put_contents(KEYS_FILE, json_encode($defaultKeys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Load all keys from JSON storage with shared read lock
     */
    public static function getAllKeys() {
        self::initStorage();
        
        $fp = fopen(KEYS_FILE, 'r');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $keys = json_decode($content, true);
        return is_array($keys) ? $keys : [];
    }

    /**
     * Save all keys to JSON storage with exclusive write lock
     */
    private static function saveAllKeys(array $keys) {
        self::initStorage();
        
        $fp = fopen(KEYS_FILE, 'c+');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * Generate a secure, unique API key string
     */
    public static function generateKeyString($prefix = 'satyam_') {
        return $prefix . bin2hex(random_bytes(8));
    }

    /**
     * Create a new API Key
     * 
     * @param string $owner Name or ID of key owner
     * @param int $limit Total request limit (-1 for unlimited)
     * @param int|null $validityDays Number of days key is valid (null for lifetime)
     * @param string|null $customKey Optional custom key name
     * @return array Created key details
     */
    public static function createKey($owner, $limit = -1, $validityDays = null, $customKey = null) {
        $keys = self::getAllKeys();
        
        $keyString = !empty($customKey) ? trim($customKey) : self::generateKeyString();

        if (isset($keys[$keyString])) {
            return [
                'success' => false,
                'message' => 'An API key with this string already exists.'
            ];
        }

        $expiresAt = null;
        if (!empty($validityDays) && is_numeric($validityDays) && $validityDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
        }

        $keyData = [
            'key' => $keyString,
            'owner' => trim($owner) ?: 'User',
            'status' => 'active',
            'request_limit' => (int)$limit,
            'requests_used' => 0,
            'expires_at' => $expiresAt,
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
     * 
     * @param string $keyString
     * @return array [ 'valid' => bool, 'message' => string, 'key_info' => array|null, 'error_code' => string|null ]
     */
    public static function validateAndConsume($keyString) {
        if (empty($keyString)) {
            return [
                'valid' => false,
                'error_code' => 'MISSING_API_KEY',
                'message' => 'API Key is required. Pass ?apikey=YOUR_KEY'
            ];
        }

        self::initStorage();

        $fp = fopen(KEYS_FILE, 'c+');
        if (!$fp) {
            return [
                'valid' => false,
                'error_code' => 'STORAGE_ERROR',
                'message' => 'Unable to access key storage.'
            ];
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return [
                'valid' => false,
                'error_code' => 'LOCK_ERROR',
                'message' => 'Key database is currently busy. Please retry.'
            ];
        }

        $content = stream_get_contents($fp);
        $keys = json_decode($content, true);
        if (!is_array($keys) || !isset($keys[$keyString])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return [
                'valid' => false,
                'error_code' => 'INVALID_API_KEY',
                'message' => 'Invalid API Key provided.'
            ];
        }

        $keyData = $keys[$keyString];

        // 1. Check if key is suspended
        if ($keyData['status'] === 'suspended') {
            flock($fp, LOCK_UN);
            fclose($fp);
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
                // Auto mark status as expired
                $keys[$keyString]['status'] = 'expired';
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);

                return [
                    'valid' => false,
                    'error_code' => 'KEY_EXPIRED',
                    'message' => 'This API key expired on ' . $keyData['expires_at'] . '. Please renew your plan.'
                ];
            }
        }

        // 3. Check if request limit is exceeded
        if ($keyData['request_limit'] > -1 && $keyData['requests_used'] >= $keyData['request_limit']) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return [
                'valid' => false,
                'error_code' => 'LIMIT_EXCEEDED',
                'message' => 'API request limit reached (' . $keyData['requests_used'] . '/' . $keyData['request_limit'] . '). Please contact admin for more quota.'
            ];
        }

        // 4. Increment usage counter and update last used time
        $keys[$keyString]['requests_used']++;
        $keys[$keyString]['last_used_at'] = date('Y-m-d H:i:s');

        // Save updated data
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $updatedKey = $keys[$keyString];
        $remaining = ($updatedKey['request_limit'] === -1) ? 'Unlimited' : max(0, $updatedKey['request_limit'] - $updatedKey['requests_used']);

        return [
            'valid' => true,
            'key_info' => [
                'owner' => $updatedKey['owner'],
                'requests_used' => $updatedKey['requests_used'],
                'request_limit' => ($updatedKey['request_limit'] === -1) ? 'Unlimited' : $updatedKey['request_limit'],
                'requests_remaining' => $remaining,
                'expires_at' => $updatedKey['expires_at'] ?? 'Never (Lifetime)'
            ]
        ];
    }

    /**
     * Update an existing API key
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
        if (isset($updates['expires_at'])) {
            $keys[$keyString]['expires_at'] = $updates['expires_at'];
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
}
