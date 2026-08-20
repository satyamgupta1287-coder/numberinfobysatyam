<?php
/**
 * Configuration Settings for Number Info API & Key Manager
 * Developer: Satyam Gupta
 */

// Timezone setting for accurate expiry and logging
date_default_timezone_set('Asia/Kolkata');

// Master Admin Secret Key for managing keys via admin.php or Admin REST API
// IMPORTANT: Change this to your own secure secret key before deploying to production!
define('ADMIN_SECRET_KEY', 'satyam_admin_2026');

// Path to data directory and keys database
define('DATA_DIR', __DIR__ . '/data');
define('KEYS_FILE', DATA_DIR . '/keys.json');

// Developer & Branding Info
define('API_DEVELOPER', 'Satyam Gupta');
define('API_CREDIT', 'Satyam Gupta');

// Default initial key configuration (created automatically if keys.json does not exist)
define('DEFAULT_LEGACY_KEY', 'satyamm');

// Upstream API URL
define('UPSTREAM_API_URL', 'https://exploitsindia.site/osintcallerbot/number.php?exploits=');
