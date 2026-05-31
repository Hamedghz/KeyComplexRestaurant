<?php
/**
 * Application bootstrap for single-root shared-hosting deployment.
 *
 * All runtime configuration, including database credentials, is loaded from
 * /config.php in the server root. The installer creates that file and the lock file.
 */

$configFile = dirname(__DIR__) . '/config.php';
$installFile = dirname(__DIR__) . '/install.php';

if (!file_exists($configFile)) {
    $isCli = PHP_SAPI === 'cli';
    $currentScript = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : '';
    $installerScript = realpath($installFile);

    if (!$isCli && $installerScript && $currentScript !== $installerScript) {
        header('Location: install.php');
        exit;
    }

    throw new RuntimeException('Application is not installed. Run install.php first.');
}

$appConfig = require $configFile;

if (!is_array($appConfig) || empty($appConfig['db']) || !is_array($appConfig['db'])) {
    throw new RuntimeException('Invalid config.php. Run install.php again after removing installed.lock.');
}

$app = $appConfig['app'] ?? [];
$db = $appConfig['db'];


if (!function_exists('detectBaseUrl')) {
    function detectBaseUrl() {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}


$environment = $app['environment'] ?? 'production';
define('APP_ENV', $environment);
define('APP_DEBUG', (bool)($app['debug'] ?? ($environment !== 'production')));

define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH);
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('STORAGE_PATH', PUBLIC_PATH . '/storage');

define('BASE_URL', rtrim(!empty($app['base_url']) ? $app['base_url'] : detectBaseUrl(), '/'));
define('ADMIN_URL', BASE_URL . '/admin');
define('API_URL', BASE_URL . '/api');

define('DB_HOST', $db['host'] ?? '');
define('DB_NAME', $db['name'] ?? '');
define('DB_USER', $db['user'] ?? '');
define('DB_PASS', $db['pass'] ?? '');
define('DB_CHARSET', $db['charset'] ?? 'utf8mb4');

define('MAX_FILE_SIZE', (int)($app['max_file_size'] ?? (5 * 1024 * 1024)));
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_MODEL_TYPES', ['model/gltf-binary', 'application/octet-stream']);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200);
define('ITEMS_PER_PAGE', 20);
define('SITE_NAME', 'KEY Restaurant & Coffeehouse');
define('SITE_NAME_FA', 'KEY رستوران و کافه');
define('ADMIN_SESSION_DURATION', 7200);

date_default_timezone_set($app['timezone'] ?? 'Asia/Tehran');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_secure', parse_url(BASE_URL, PHP_URL_SCHEME) === 'https' ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }

        return $_SESSION[CSRF_TOKEN_NAME];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], (string)$token);
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if ($data === null) {
            return null;
        }

        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }

        return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return number_format((float)$price, 0, '.', ',') . ' تومان';
    }
}


if (!function_exists('normalizeMobile')) {
    function normalizeMobile($mobile) {
        $mobile = preg_replace('/[^0-9+]/', '', (string)$mobile);
        if (str_starts_with($mobile, '+98')) {
            $mobile = '0' . substr($mobile, 3);
        } elseif (str_starts_with($mobile, '98') && strlen($mobile) === 12) {
            $mobile = '0' . substr($mobile, 2);
        }
        return $mobile;
    }
}

if (!function_exists('gregorianToJalaliParts')) {
    function gregorianToJalaliParts($gy, $gm, $gd) {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('jalaliToGregorianParts')) {
    function jalaliToGregorianParts($jy, $jm, $jd) {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4) + $jd + (($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186));
        $gy = 400 * (int)($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * (int)(--$days / 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0,31,(($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
        for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }
        return [$gy, $gm, $gd];
    }
}

if (!function_exists('formatJalaliDateTime')) {
    function formatJalaliDateTime($datetime, $includeTime = true) {
        if (empty($datetime)) return '';
        $timestamp = strtotime((string)$datetime);
        if (!$timestamp) return '';
        [$jy, $jm, $jd] = gregorianToJalaliParts((int)date('Y', $timestamp), (int)date('n', $timestamp), (int)date('j', $timestamp));
        $date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        return $includeTime ? $date . ' ' . date('H:i', $timestamp) : $date;
    }
}

if (!function_exists('parsePersianDate')) {
    function parsePersianDate($value, $withTime = false) {
        $value = trim((string)$value);
        if ($value === '') return null;
        $value = strtr($value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $parts = preg_split('/\s+/', $value);
        $datePart = str_replace('-', '/', $parts[0]);
        $date = explode('/', $datePart);
        if (count($date) !== 3) return $value;
        [$y, $m, $d] = array_map('intval', $date);
        if ($y < 1700) {
            [$gy, $gm, $gd] = jalaliToGregorianParts($y, $m, $d);
        } else {
            [$gy, $gm, $gd] = [$y, $m, $d];
        }
        $time = $parts[1] ?? '00:00:00';
        if (strlen($time) === 5) $time .= ':00';
        return sprintf('%04d-%02d-%02d%s', $gy, $gm, $gd, $withTime ? ' ' . $time : '');
    }
}

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            jsonResponse([
                'success' => false,
                'message' => 'خطا در اتصال به پایگاه داده',
            ], 500);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}
