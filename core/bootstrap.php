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
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMPORT_EXTENSIONS', ['csv', 'xlsx']);
define('ALLOWED_MODEL_TYPES', ['model/gltf-binary', 'application/octet-stream']);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200);
define('ITEMS_PER_PAGE', 20);
define('SITE_NAME', 'KEY Restaurant & Coffeehouse');
define('SITE_NAME_FA', 'KEY رستوران و کافه');
define('ADMIN_SESSION_DURATION', 7200);
define('APP_TIMEZONE', 'Asia/Tehran');

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0755, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

// PHP 8 string helpers are used by shared admin utilities. Production hosts may
// still run PHP 7.4, so provide small compatible polyfills before any request
// code calls them.
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        $length = strlen($needle);
        return substr($haystack, -$length) === $needle;
    }
}

// Session INI values must be configured before any session is opened. Some
// entry points (notably installer/update flows on shared hosting) may include
// bootstrap after session_start(), so avoid noisy warnings that can break admin
// responses while keeping the intended settings for normal requests.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', parse_url(BASE_URL, PHP_URL_SCHEME) === 'https' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
}

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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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



if (!function_exists('sendSecurityHeaders')) {
    function sendSecurityHeaders() {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    }
}

if (!function_exists('assetUrl')) {
    function assetUrl($path) {
        $path = '/' . ltrim((string)$path, '/');
        $fullPath = PUBLIC_PATH . $path;
        $version = is_file($fullPath) ? (string)filemtime($fullPath) : date('Ymd');
        return BASE_URL . $path . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('localFontPreloadLinks')) {
    function localFontPreloadLinks() {
        return '<link rel="preload" href="' . htmlspecialchars(assetUrl('assets/fonts/Vazirmatn-Regular.woff2'), ENT_QUOTES, 'UTF-8') . '" as="font" type="font/woff2" crossorigin>' . "\n"
            . '<link rel="preload" href="' . htmlspecialchars(assetUrl('assets/fonts/Vazirmatn-Bold.woff2'), ENT_QUOTES, 'UTF-8') . '" as="font" type="font/woff2" crossorigin>';
    }
}

if (!function_exists('renderSeoMeta')) {
    function renderSeoMeta($title, $description, $image = '', $canonical = '', $keywords = '') {
        $canonical = $canonical !== '' ? $canonical : BASE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $image = $image !== '' ? $image : assetUrl('assets/images/home-preview.svg');
        $title = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars((string)$description, ENT_QUOTES, 'UTF-8');
        $canonical = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
        $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
        $keywordsTag = $keywords !== '' ? '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">' . "\n" : '';

        return '<title>' . $title . '</title>' . "\n"
            . '<meta name="description" content="' . $description . '">' . "\n"
            . $keywordsTag
            . '<link rel="canonical" href="' . $canonical . '">' . "\n"
            . '<meta property="og:locale" content="fa_IR">' . "\n"
            . '<meta property="og:type" content="website">' . "\n"
            . '<meta property="og:title" content="' . $title . '">' . "\n"
            . '<meta property="og:description" content="' . $description . '">' . "\n"
            . '<meta property="og:image" content="' . $image . '">' . "\n"
            . '<meta name="twitter:card" content="summary_large_image">' . "\n"
            . '<meta name="twitter:title" content="' . $title . '">' . "\n"
            . '<meta name="twitter:description" content="' . $description . '">' . "\n"
            . '<meta name="twitter:image" content="' . $image . '">' . "\n"
            . '<link rel="icon" href="' . htmlspecialchars(assetUrl('assets/images/home-preview.svg'), ENT_QUOTES, 'UTF-8') . '" type="image/svg+xml">';
    }
}

if (!function_exists('verifyRequestCsrf')) {
    function verifyRequestCsrf() {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return verifyCSRFToken((string)$token);
    }
}

if (!function_exists('requireValidCsrf')) {
    function requireValidCsrf() {
        if (!verifyRequestCsrf()) {
            http_response_code(419);
            throw new RuntimeException('درخواست نامعتبر است. لطفاً صفحه را تازه‌سازی کنید.');
        }
    }
}

if (!function_exists('validateUploadedFile')) {
    function validateUploadedFile($file, $allowedExtensions, $allowedMimeTypes, $maxSize = null) {
        if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'فایل معتبر ارسال نشده است.'];
        }

        $maxSize = $maxSize ?? MAX_FILE_SIZE;
        if ((int)$file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'حجم فایل بیش از حد مجاز است.'];
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['valid' => false, 'message' => 'پسوند فایل مجاز نیست.'];
        }

        $mime = '';
        if (is_file($file['tmp_name'])) {
            $mime = mime_content_type($file['tmp_name']) ?: '';
        }

        if ($allowedMimeTypes && !in_array($mime, $allowedMimeTypes, true)) {
            return ['valid' => false, 'message' => 'نوع فایل مجاز نیست.'];
        }

        return ['valid' => true, 'extension' => $extension, 'mime' => $mime];
    }
}

if (!function_exists('optimizeUploadedImage')) {
    function optimizeUploadedImage($tmpPath, $destinationDirectory, $baseName) {
        if (!is_dir($destinationDirectory)) {
            mkdir($destinationDirectory, 0755, true);
        }

        $info = getimagesize($tmpPath);
        if (!$info) {
            throw new RuntimeException('تصویر معتبر نیست.');
        }

        $mime = $info['mime'] ?? '';
        $target = $destinationDirectory . '/' . $baseName . '.webp';

        if (function_exists('imagewebp')) {
            $source = null;
            if ($mime === 'image/jpeg') {
                $source = imagecreatefromjpeg($tmpPath);
            } elseif ($mime === 'image/png') {
                $source = imagecreatefrompng($tmpPath);
                if ($source) {
                    imagepalettetotruecolor($source);
                    imagealphablending($source, true);
                    imagesavealpha($source, true);
                }
            } elseif ($mime === 'image/webp') {
                $source = imagecreatefromwebp($tmpPath);
            }

            if ($source) {
                imagewebp($source, $target, 82);
                imagedestroy($source);
                return basename($target);
            }
        }

        $extension = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $fallback = $destinationDirectory . '/' . $baseName . '.' . $extension;
        if (!move_uploaded_file($tmpPath, $fallback)) {
            throw new RuntimeException('ذخیره فایل ناموفق بود.');
        }
        return basename($fallback);
    }
}

if (PHP_SAPI !== 'cli') {
    sendSecurityHeaders();
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

if (!function_exists('appTimeZone')) {
    function appTimeZone(): DateTimeZone {
        static $timezone = null;
        if ($timezone === null) {
            $timezone = new DateTimeZone(APP_TIMEZONE);
        }
        return $timezone;
    }
}

if (!function_exists('appNow')) {
    function appNow(): DateTimeImmutable {
        return new DateTimeImmutable('now', appTimeZone());
    }
}

if (!function_exists('appMysqlDateTime')) {
    function appMysqlDateTime(?DateTimeImmutable $dateTime = null): string {
        return ($dateTime ?: appNow())->setTimezone(appTimeZone())->format('Y-m-d H:i:s');
    }
}

if (!function_exists('parseStoredDateTime')) {
    function parseStoredDateTime($value): ?DateTimeImmutable {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $dateTime = DateTimeImmutable::createFromFormat('!' . $format, $value, appTimeZone());
            $errors = DateTimeImmutable::getLastErrors();
            if ($dateTime instanceof DateTimeImmutable && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))) {
                return $dateTime;
            }
        }

        try {
            return new DateTimeImmutable($value, appTimeZone());
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('formatJalaliDateTime')) {
    function formatJalaliDateTime($datetime, $includeTime = true) {
        if (empty($datetime)) return '';
        $dateTime = parseStoredDateTime($datetime);
        if (!$dateTime) return '';
        $dateTime = $dateTime->setTimezone(appTimeZone());
        [$jy, $jm, $jd] = gregorianToJalaliParts((int)$dateTime->format('Y'), (int)$dateTime->format('n'), (int)$dateTime->format('j'));
        $date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        return $includeTime ? $date . ' ' . $dateTime->format('H:i') : $date;
    }
}

if (!function_exists('normalizePersianDateDigits')) {
    function normalizePersianDateDigits($value) {
        return strtr((string)$value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    }
}

if (!function_exists('parsePersianTime')) {
    function parsePersianTime($value) {
        $value = trim(normalizePersianDateDigits($value));
        if ($value === '') return null;
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) return $value;
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $second = isset($matches[3]) ? (int)$matches[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) return $value;
        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }
}

if (!function_exists('parsePersianDate')) {
    function parsePersianDate($value, $withTime = false) {
        $value = trim(normalizePersianDateDigits($value));
        if ($value === '') return null;

        if (!preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})(?:\s+(\d{1,2}:\d{2}(?::\d{2})?))?$/', $value, $matches)) {
            return $value;
        }

        $y = (int)$matches[1];
        $m = (int)$matches[2];
        $d = (int)$matches[3];

        if ($y >= 1300 && $y <= 1599) {
            if ($m < 1 || $m > 12 || $d < 1 || $d > ($m <= 6 ? 31 : 30)) {
                return $value;
            }
            [$gy, $gm, $gd] = jalaliToGregorianParts($y, $m, $d);
            [$checkY, $checkM, $checkD] = gregorianToJalaliParts($gy, $gm, $gd);
            if ($checkY !== $y || $checkM !== $m || $checkD !== $d) {
                return $value;
            }
        } else {
            [$gy, $gm, $gd] = [$y, $m, $d];
        }

        if (!checkdate($gm, $gd, $gy)) {
            return $value;
        }

        $time = parsePersianTime($matches[4] ?? '00:00:00');
        if ($time === null || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $value;
        }

        $dateTime = DateTimeImmutable::createFromFormat('!Y-n-j H:i:s', sprintf('%04d-%d-%d %s', $gy, $gm, $gd, $time), appTimeZone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dateTime || ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) {
            return $value;
        }

        return $withTime ? $dateTime->format('Y-m-d H:i:s') : $dateTime->format('Y-m-d');
    }
}

if (!function_exists('isMatchOpenForPrediction')) {
    function isMatchOpenForPrediction(array $match, ?DateTimeImmutable $now = null): bool {
        $now = $now ? $now->setTimezone(appTimeZone()) : appNow();
        $predictionStart = parseStoredDateTime($match['prediction_start_at'] ?? $match['prediction_open_at'] ?? null);
        $predictionEnd = parseStoredDateTime($match['prediction_end_at'] ?? $match['prediction_close_at'] ?? null);

        return (int)($match['is_active'] ?? 0) === 1
            && (int)($match['active_for_prediction'] ?? 0) === 1
            && $predictionStart instanceof DateTimeImmutable
            && $predictionEnd instanceof DateTimeImmutable
            && $predictionStart <= $now
            && $predictionEnd >= $now;
    }
}

if (!function_exists('matchPredictionAvailabilityError')) {
    function matchPredictionAvailabilityError(array $match, ?DateTimeImmutable $now = null): ?string {
        $now = $now ? $now->setTimezone(appTimeZone()) : appNow();
        if ((int)($match['is_active'] ?? 0) !== 1 || (int)($match['active_for_prediction'] ?? 0) !== 1) {
            return 'این مسابقه برای پیش‌بینی فعال نیست.';
        }

        $predictionStart = parseStoredDateTime($match['prediction_start_at'] ?? $match['prediction_open_at'] ?? null);
        $predictionEnd = parseStoredDateTime($match['prediction_end_at'] ?? $match['prediction_close_at'] ?? null);
        if (!$predictionStart || !$predictionEnd) {
            return 'بازه زمانی پیش‌بینی برای این مسابقه معتبر نیست.';
        }
        if ($predictionStart > $now) {
            return 'مهلت ثبت پیش‌بینی برای این مسابقه هنوز آغاز نشده است.';
        }
        if ($predictionEnd < $now) {
            return 'مهلت ثبت پیش‌بینی برای این مسابقه به پایان رسیده است.';
        }

        return null;
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
            $this->connection->exec("SET time_zone = '" . appNow()->format('P') . "'");
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
