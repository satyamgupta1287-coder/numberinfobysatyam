<?php
/**
 * Configuration Settings for Number Info API & Key Manager
 * Developer: Satyam Gupta
 */

// Timezone setting for accurate expiry and logging
date_default_timezone_set('Asia/Kolkata');

// Master Admin Secret Key for managing keys via admin.php or Admin REST API
// Can also be set in Vercel Environment Variables as ADMIN_SECRET_KEY
define('ADMIN_SECRET_KEY', getenv('ADMIN_SECRET_KEY') ?: 'satyamop');

// Upstash Redis Cloud Database (100% Automatic Key Persistence for Vercel)
define('UPSTASH_REDIS_REST_URL', getenv('UPSTASH_REDIS_REST_URL') ?: 'https://handy-oarfish-116940.upstash.io');
define('UPSTASH_REDIS_REST_TOKEN', getenv('UPSTASH_REDIS_REST_TOKEN') ?: 'gQAAAAAAAcjMAAIgcDIzNTE4NDcyYWJkNTI0ZmJjOTBlZjE3YTlmMTI4ZTYxNw');

// Path to local data directory and keys database (used as local fallback & cache)
if (getenv('VERCEL') || !@is_writable(__DIR__)) {
    $tempDir = sys_get_temp_dir() . '/numberinfo_data';
    define('DATA_DIR', $tempDir);
} else {
    define('DATA_DIR', __DIR__ . '/data');
}

define('KEYS_FILE', DATA_DIR . '/keys.json');

// Developer & Branding Info
define('API_DEVELOPER', 'Satyam Gupta');
define('API_CREDIT', 'Satyam Gupta');

// Default initial key configuration (always active and unlimited)
define('DEFAULT_LEGACY_KEY', 'satyamm');

// Static / Permanent API Keys (optional fallback)
$STATIC_API_KEYS = [];
$envKeys = getenv('API_KEYS_JSON');
if (!empty($envKeys)) {
    $parsedEnv = json_decode($envKeys, true);
    if (is_array($parsedEnv)) {
        $STATIC_API_KEYS = array_merge($STATIC_API_KEYS, $parsedEnv);
    }
}

// Upstream API URL
define('UPSTREAM_API_URL', 'https://exploitsindia.site/osintcallerbot/number.php?exploits=');
