<?php
// Strictly no whitespace before this opening tag
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_NAME', 'ozeki');

// Image Configuration
define('MAX_IMAGE_SIZE_MB', 5);
define('MAX_IMAGE_WIDTH', 1200);
define('MAX_IMAGE_HEIGHT', 1200);
define('IMAGE_QUALITY', 85);

// API Configuration
define('API_RATE_LIMIT', 5);
define('API_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://192.168.1.129'
]);

// Security Configuration
define('ENABLE_HTTPS', false);
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/error.log');

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Calculate bytes from MB (for image size checking)
function getMaxImageBytes() {
    return MAX_IMAGE_SIZE_MB * 1024 * 1024;
}

// Helper function to check if HTTPS is enabled
function isHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           ($_SERVER['SERVER_PORT'] == 443) ||
           (defined('ENABLE_HTTPS') && ENABLE_HTTPS);
}

// Error reporting configuration
if (defined('LOG_ERRORS') && LOG_ERRORS) {
    ini_set('display_errors', 0);  // Ensure errors aren't displayed
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE);
}