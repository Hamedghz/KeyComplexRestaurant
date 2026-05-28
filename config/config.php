<?php
/**
 * Application Configuration
 * KEY Restaurant & Coffeehouse
 */

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Tehran');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Application paths
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public_html');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// URL configuration
define('BASE_URL', 'http://localhost/KeyComplexRestaurant/public_html');
define('ADMIN_URL', BASE_URL . '/admin');
define('API_URL', BASE_URL . '/api');

// Upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_MODEL_TYPES', ['model/gltf-binary', 'application/octet-stream']);

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200); // 2 hours

// Pagination
define('ITEMS_PER_PAGE', 20);

// Application settings
define('SITE_NAME', 'KEY Restaurant & Coffeehouse');
define('SITE_NAME_FA', 'KEY رستوران و کافه');

// Admin session duration (in seconds)
define('ADMIN_SESSION_DURATION', 7200); // 2 hours

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Helper function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Helper function for JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper function to sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Helper function to format price
function formatPrice($price) {
    return number_format($price, 0, '.', ',') . ' تومان';
}

// Helper function to format date in Persian
function formatPersianDate($date) {
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return jdate('Y/m/d H:i', $timestamp);
}

// Load Jalali date helper if available
if (file_exists(ROOT_PATH . '/core/helpers/jdate.php')) {
    require_once ROOT_PATH . '/core/helpers/jdate.php';
}
