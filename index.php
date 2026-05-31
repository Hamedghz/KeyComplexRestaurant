<?php
/**
 * KEY Restaurant & Coffeehouse
 * Frontend Homepage
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/models/Setting.php';
require_once __DIR__ . '/core/models/MenuItem.php';

$settingModel = new Setting();
$menuModel = new MenuItem();

// Homepage helpers
function homeEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function homeSafeHtml($value) {
    return strip_tags((string)$value, '<p><br><strong><b><em><i><ul><ol><li><span><a>');
}

function normalizeAssetPath($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return '/' . ltrim($path, '/');
}

function normalizeTelLink($phone) {
    return preg_replace('/[^0-9+]/', '', (string)$phone);
}

function getOpeningWindow($value) {
    if (is_array($value)) {
        $isClosed = !empty($value['closed']) || (isset($value['is_open']) && !$value['is_open']);

        if ($isClosed) {
            return null;
        }

        $open = $value['open'] ?? $value['from'] ?? null;
        $close = $value['close'] ?? $value['to'] ?? null;

        if ($open && $close) {
            return [$open, $close];
        }

        return null;
    }

    $value = trim((string)$value);

    if ($value === '' || in_array(strtolower($value), ['closed', 'off'], true)) {
        return null;
    }

    $parts = preg_split('/\s*-\s*/', $value);

    if (count($parts) !== 2) {
        return null;
    }

    return [$parts[0], $parts[1]];
}

function timeToMinutes($time) {
    $time = trim((string)$time);

    if ($time === '24:00') {
        return 1440;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
        return null;
    }

    $hours = (int)$matches[1];
    $minutes = (int)$matches[2];

    if ($hours > 23 || $minutes > 59) {
        return null;
    }

    return ($hours * 60) + $minutes;
}

function isOpenNow($openingHours, $dayKey, DateTimeInterface $now) {
    if (!is_array($openingHours) || empty($openingHours[$dayKey])) {
        return false;
    }

    $window = getOpeningWindow($openingHours[$dayKey]);

    if (!$window) {
        return false;
    }

    [$open, $close] = $window;
    $openMinutes = timeToMinutes($open);
    $closeMinutes = timeToMinutes($close);

    if ($openMinutes === null || $closeMinutes === null) {
        return false;
    }

    $currentMinutes = ((int)$now->format('G') * 60) + (int)$now->format('i');

    if ($closeMinutes <= $openMinutes) {
        return $currentMinutes >= $openMinutes || $currentMinutes < $closeMinutes;
    }

    return $currentMinutes >= $openMinutes && $currentMinutes < $closeMinutes;
}

function formatOpeningHours($value) {
    $window = getOpeningWindow($value);

    if (!$window) {
        return 'تعطیل';
    }

    return $window[0] . ' تا ' . $window[1];
}

function getSettingLinks($value, $fallback) {
    return is_array($value) && !empty($value) ? $value : $fallback;
}

$newsletterMessage = '';
$newsletterStatus = '';
$newsletterInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'newsletter') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $newsletterInput = trim((string)($_POST['subscriber_contact'] ?? ''));
    $submittedToken = (string)($_POST[CSRF_TOKEN_NAME] ?? '');

    if (!verifyCSRFToken($submittedToken)) {
        $newsletterStatus = 'error';
        $newsletterMessage = 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
    } elseif ($newsletterInput === '') {
        $newsletterStatus = 'error';
        $newsletterMessage = 'لطفاً شماره تماس یا ایمیل خود را وارد کنید.';
    } else {
        $isEmail = filter_var($newsletterInput, FILTER_VALIDATE_EMAIL);
        $phoneCandidate = preg_replace('/[\s\-()]/', '', $newsletterInput);
        $isPhone = (bool)preg_match('/^\+?[0-9]{8,15}$/', $phoneCandidate);

        if (!$isEmail && !$isPhone) {
            $newsletterStatus = 'error';
            $newsletterMessage = 'فرمت شماره تماس یا ایمیل معتبر نیست.';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    INSERT INTO newsletter_subscribers (phone, email, is_active)
                    VALUES (:phone, :email, 1)
                    ON DUPLICATE KEY UPDATE is_active = 1
                ");
                $stmt->execute([
                    'phone' => $isPhone ? $phoneCandidate : null,
                    'email' => $isEmail ? $newsletterInput : null,
                ]);

                $newsletterStatus = 'success';
                $newsletterMessage = 'عضویت شما با موفقیت ثبت شد.';
                $newsletterInput = '';
            } catch (PDOException $e) {
                error_log('Newsletter subscription error: ' . $e->getMessage());
                $newsletterStatus = 'error';
                $newsletterMessage = 'ثبت عضویت در حال حاضر امکان‌پذیر نیست. لطفاً بعداً تلاش کنید.';
            }
        }
    }
}

// Get settings
$siteName = $settingModel->get('site_name_fa', 'KEY رستوران و کافه');
$siteNameEn = $settingModel->get('site_name_en', 'KEY Restaurant & Coffeehouse');
$heroTitle = $settingModel->get('hero_title_fa', 'KEY رستوران و کافه');
$heroSubtitle = $settingModel->get('hero_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی');
$ctaText = $settingModel->get('hero_cta_text_fa', 'سفارش آنلاین');
$primaryColor = $settingModel->get('primary_color', '#004647');
$accentColor = $settingModel->get('accent_color', '#D4AF37');
$menuTitle = $settingModel->get('featured_menu_title_fa', 'منوی ویژه');
$aboutTitle = $settingModel->get('about_title_fa', 'درباره مجموعه');
$aboutContent = $settingModel->get('about_content_fa', 'روایت طعم‌های اصیل، قهوه‌های منتخب و میزبانی گرم در فضایی لوکس و آرام.');
$aboutImage = normalizeAssetPath($settingModel->get('about_image', ''));
$addressFa = $settingModel->get('address_fa', 'آدرس مجموعه');
$addressEn = $settingModel->get('address_en', 'Restaurant address');
$phoneNumber = $settingModel->get('phone_number', '+98 21 1234 5678');
$email = $settingModel->get('email', 'info@keyrestaurant.com');
$locationLat = $settingModel->get('location_lat', '35.6892');
$locationLng = $settingModel->get('location_lng', '51.3890');
$instagramUrl = $settingModel->get('instagram_url', 'https://instagram.com/keyrestaurant');
$telegramUrl = $settingModel->get('telegram_url', 'https://t.me/keyrestaurant');
$whatsappNumber = $settingModel->get('whatsapp_number', '+989121234567');
$locationTitle = $settingModel->get('location_title_fa', 'موقعیت و تماس');
$hoursTitle = $settingModel->get('opening_hours_title_fa', 'ساعت کاری');
$newsletterTitle = $settingModel->get('newsletter_title_fa', 'باشگاه مشتریان');
$newsletterText = $settingModel->get('newsletter_text_fa', 'برای دریافت خبرهای تازه، پیشنهادهای ویژه و رویدادهای مجموعه، شماره تماس یا ایمیل خود را ثبت کنید.');
$footerQuickLinksTitle = $settingModel->get('footer_quick_links_title_fa', 'دسترسی سریع');
$footerContactTitle = $settingModel->get('footer_contact_title_fa', 'اطلاعات تماس');
$footerCopyright = $settingModel->get('footer_copyright_fa', 'تمامی حقوق محفوظ است.');
$quickLinks = getSettingLinks($settingModel->get('footer_quick_links', []), [
    ['label' => 'منو', 'url' => '#menu'],
    ['label' => 'درباره ما', 'url' => '#about'],
    ['label' => 'موقعیت', 'url' => '#location'],
    ['label' => 'باشگاه مشتریان', 'url' => '#newsletter'],
]);
$openingHours = $settingModel->get('opening_hours', [
    'saturday' => '09:00-23:00',
    'sunday' => '09:00-23:00',
    'monday' => '09:00-23:00',
    'tuesday' => '09:00-23:00',
    'wednesday' => '09:00-23:00',
    'thursday' => '09:00-23:00',
    'friday' => '10:00-24:00',
]);

if (!is_array($openingHours)) {
    $openingHours = [];
}

$weekDays = [
    'saturday' => 'شنبه',
    'sunday' => 'یکشنبه',
    'monday' => 'دوشنبه',
    'tuesday' => 'سه‌شنبه',
    'wednesday' => 'چهارشنبه',
    'thursday' => 'پنجشنبه',
    'friday' => 'جمعه',
];
$dayMap = [
    'Sat' => 'saturday',
    'Sun' => 'sunday',
    'Mon' => 'monday',
    'Tue' => 'tuesday',
    'Wed' => 'wednesday',
    'Thu' => 'thursday',
    'Fri' => 'friday',
];
$now = new DateTimeImmutable('now');
$currentDayKey = $dayMap[$now->format('D')] ?? 'saturday';
$isCurrentlyOpen = isOpenNow($openingHours, $currentDayKey, $now);
$baladMapUrl = 'https://balad.ir/location?latitude=' . rawurlencode((string)$locationLat) . '&longitude=' . rawurlencode((string)$locationLng);
$telLink = normalizeTelLink($phoneNumber);
$whatsappLink = $whatsappNumber !== '' ? 'https://wa.me/' . ltrim(normalizeTelLink($whatsappNumber), '+') : '';
$newsletterToken = generateCSRFToken();

// Get WebGL settings
$webglSettings = $settingModel->getWebGLSettings();

// Get dynamic hero banners and filterable menu categories/items
$db = Database::getInstance()->getConnection();
try {
    $heroStmt = $db->prepare("SELECT * FROM hero_banners WHERE active_status = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY display_order ASC, id DESC");
    $heroStmt->execute();
    $heroBanners = $heroStmt->fetchAll();
} catch (Throwable $e) {
    $heroBanners = [];
}
try {
    $categoryStmt = $db->prepare("SELECT * FROM menu_categories WHERE COALESCE(is_active, 1) = 1 ORDER BY sort_order ASC, name_fa ASC");
    $categoryStmt->execute();
    $menuCategories = $categoryStmt->fetchAll();
    $menuItemsByCategory = [];
    foreach ($menuCategories as $category) {
        $menuItemsByCategory[$category['id']] = $menuModel->getByCategory($category['id']);
    }
} catch (Throwable $e) {
    $menuCategories = [];
    $menuItemsByCategory = [];
}
// Backward-compatible fallback for older databases without the new content tables.
$featuredItems = $menuModel->getFeatured(6);
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo renderSeoMeta($siteName, $heroSubtitle, assetUrl('assets/images/home-preview.svg'), BASE_URL . '/', 'رستوران, کافه, KEY, منو, سفارش غذا'); ?>
    <?php echo localFontPreloadLinks(); ?>
    <link rel="preload" href="<?php echo homeEscape(assetUrl('assets/images/home-preview.svg')); ?>" as="image" type="image/svg+xml">
    <style>
        @font-face { font-family: Vazirmatn; src: url('assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: Vazirmatn; src: url('assets/fonts/Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-display: swap; }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: <?php echo $primaryColor; ?>;
            --accent: <?php echo $accentColor; ?>;
            --white: #FFFFFF;
            --black: #0A0A0A;
        }
        
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: var(--black);
            color: var(--white);
            direction: rtl;
            overflow-x: hidden;
        }
        
        /* WebGL Hero Section */
        #hero-section {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        
        #webgl-canvas,
        .hero-static-fallback {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .hero-static-fallback {
            background: radial-gradient(circle at 50% 35%, rgba(212,175,55,0.22), transparent 32%), linear-gradient(135deg, #004647, #061819 72%);
            opacity: 0;
            transform: scale(1.03);
            transition: opacity .4s ease, transform 2s ease;
        }

        .no-webgl .hero-static-fallback,
        .reduced-webgl .hero-static-fallback {
            opacity: 1;
            transform: scale(1);
        }

        .no-webgl #webgl-canvas { display: none; }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(0,70,71,0.3) 0%, rgba(0,0,0,0.7) 100%);
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        
        .logo-container {
            margin-bottom: 40px;
            animation: fadeInDown 1s ease-out;
        }
        
        .lotus-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        
        .lotus-petal {
            fill: var(--accent);
            opacity: 0;
            animation: petalBloom 0.6s ease-out forwards;
        }
        
        .lotus-petal:nth-child(1) { animation-delay: 0.1s; }
        .lotus-petal:nth-child(2) { animation-delay: 0.2s; }
        .lotus-petal:nth-child(3) { animation-delay: 0.3s; }
        .lotus-petal:nth-child(4) { animation-delay: 0.4s; }
        .lotus-petal:nth-child(5) { animation-delay: 0.5s; }
        .lotus-petal:nth-child(6) { animation-delay: 0.6s; }
        .lotus-petal:nth-child(7) { animation-delay: 0.7s; }
        .lotus-petal:nth-child(8) { animation-delay: 0.8s; }
        .lotus-petal:nth-child(9) { animation-delay: 0.9s; }
        
        @keyframes petalBloom {
            from {
                opacity: 0;
                transform: scale(0) rotate(-45deg);
            }
            to {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero-title {
            font-size: clamp(32px, 8vw, 72px);
            font-weight: 700;
            color: var(--white);
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: fadeInUp 1s ease-out 0.5s both;
        }
        
        .hero-subtitle {
            font-size: clamp(16px, 3vw, 24px);
            color: var(--accent);
            margin-bottom: 40px;
            animation: fadeInUp 1s ease-out 0.7s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .glass-button {
            display: inline-block;
            padding: 18px 50px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(212, 175, 55, 0.5);
            border-radius: 50px;
            color: var(--white);
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out 0.9s both;
        }
        
        .glass-button:hover {
            background: rgba(212, 175, 55, 0.2);
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(212, 175, 55, 0.3);
        }
        
        .scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
            z-index: 2;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }
        
        /* Social Links */
        .social-links {
            position: fixed;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s;
        }
        
        .social-link:hover {
            background: var(--accent);
            transform: scale(1.1);
        }
        
        /* Menu Section */
        .menu-section {
            padding: 100px 20px;
            background: linear-gradient(180deg, var(--black) 0%, var(--primary) 100%);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 48px;
            color: var(--accent);
            margin-bottom: 60px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .menu-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(212, 175, 55, 0.2);
        }
        
        .menu-card-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .menu-card-content {
            padding: 25px;
        }
        
        .menu-card-title {
            font-size: 24px;
            color: var(--white);
            margin-bottom: 10px;
        }
        
        .menu-card-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .menu-card-price {
            font-size: 20px;
            color: var(--accent);
            font-weight: 700;
        }
        
        /* Shared premium sections */
        .premium-section {
            padding: 100px 20px;
            position: relative;
            overflow: hidden;
        }

        .premium-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 10%, rgba(212, 175, 55, 0.14), transparent 28%),
                        radial-gradient(circle at 80% 85%, rgba(0, 70, 71, 0.35), transparent 30%);
            pointer-events: none;
        }

        .premium-section .container {
            position: relative;
            z-index: 1;
        }

        .section-eyebrow {
            color: rgba(212, 175, 55, 0.75);
            font-size: 14px;
            letter-spacing: 4px;
            text-align: center;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
        }

        .about-section {
            background: linear-gradient(180deg, var(--primary) 0%, var(--black) 100%);
        }

        .about-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.85fr);
            gap: 34px;
            align-items: center;
        }

        .about-copy {
            padding: 42px;
            line-height: 2;
            font-size: 18px;
            color: rgba(255, 255, 255, 0.82);
        }

        .about-copy p:not(:last-child),
        .about-copy ul:not(:last-child),
        .about-copy ol:not(:last-child) {
            margin-bottom: 18px;
        }

        .about-copy a {
            color: var(--accent);
        }

        .about-image-wrap {
            min-height: 430px;
            overflow: hidden;
            position: relative;
        }

        .about-image-wrap img {
            width: 100%;
            height: 100%;
            min-height: 430px;
            object-fit: cover;
            display: block;
            filter: saturate(0.9) contrast(1.08);
        }

        .about-image-placeholder {
            height: 430px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(212, 175, 55, 0.45);
            font-size: 96px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.12), rgba(0, 70, 71, 0.35));
        }

        .location-section {
            background: linear-gradient(180deg, var(--black) 0%, rgba(0, 70, 71, 0.72) 100%);
        }

        .location-grid {
            display: grid;
            grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.2fr);
            gap: 28px;
        }

        .contact-card,
        .newsletter-card {
            padding: 34px;
        }

        .contact-list {
            list-style: none;
            display: grid;
            gap: 20px;
            margin-bottom: 28px;
        }

        .contact-label {
            display: block;
            color: var(--accent);
            font-size: 14px;
            margin-bottom: 6px;
        }

        .contact-value,
        .contact-value-en {
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.8;
        }

        .contact-value-en {
            direction: ltr;
            text-align: right;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.62);
        }

        .section-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 24px;
            border-radius: 999px;
            color: var(--white);
            text-decoration: none;
            border: 1px solid rgba(212, 175, 55, 0.38);
            background: rgba(212, 175, 55, 0.12);
            transition: all 0.3s;
        }

        .secondary-button:hover {
            background: rgba(212, 175, 55, 0.22);
            transform: translateY(-2px);
        }

        .map-frame {
            min-height: 390px;
            overflow: hidden;
        }

        .map-frame iframe {
            width: 100%;
            height: 100%;
            min-height: 390px;
            border: 0;
            filter: grayscale(0.15) contrast(1.04);
        }

        .hours-section {
            background: var(--black);
        }

        .hours-status {
            width: fit-content;
            margin: -30px auto 38px;
            padding: 12px 22px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--white);
            background: rgba(255, 255, 255, 0.07);
        }

        .hours-status.is-open {
            border-color: rgba(88, 214, 141, 0.45);
            color: #8ef2b3;
        }

        .hours-status.is-closed {
            border-color: rgba(255, 118, 117, 0.45);
            color: #ffb1ad;
        }

        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .hours-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 20px;
            color: rgba(255, 255, 255, 0.72);
        }

        .hours-row.current-day {
            border-color: rgba(212, 175, 55, 0.5);
            color: var(--white);
            box-shadow: 0 18px 38px rgba(212, 175, 55, 0.12);
        }

        .hours-day {
            font-weight: 700;
        }

        .hours-time {
            color: var(--accent);
            direction: ltr;
        }

        .newsletter-section {
            background: linear-gradient(180deg, rgba(0, 70, 71, 0.72) 0%, var(--black) 100%);
        }

        .newsletter-card {
            max-width: 760px;
            margin: 0 auto;
            text-align: center;
        }

        .newsletter-text {
            color: rgba(255, 255, 255, 0.72);
            line-height: 1.9;
            margin-bottom: 28px;
        }

        .newsletter-form {
            display: flex;
            gap: 12px;
            padding: 8px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.26);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .newsletter-form input {
            flex: 1;
            min-width: 0;
            border: 0;
            background: transparent;
            color: var(--white);
            padding: 0 18px;
            font-size: 16px;
            outline: none;
            direction: ltr;
            text-align: right;
        }

        .newsletter-form input::placeholder {
            color: rgba(255, 255, 255, 0.45);
        }

        .newsletter-message {
            margin-bottom: 18px;
            padding: 12px 18px;
            border-radius: 18px;
            color: var(--white);
            background: rgba(255, 255, 255, 0.08);
        }

        .newsletter-message.success {
            color: #8ef2b3;
            border: 1px solid rgba(88, 214, 141, 0.35);
        }

        .newsletter-message.error {
            color: #ffb1ad;
            border: 1px solid rgba(255, 118, 117, 0.35);
        }

        /* Footer */
        .footer {
            background: var(--black);
            padding: 70px 20px 32px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 1fr;
            gap: 34px;
            text-align: right;
            margin-bottom: 36px;
        }

        .footer-title {
            color: var(--accent);
            margin-bottom: 18px;
            font-size: 20px;
        }

        .footer-text,
        .footer-link,
        .footer-contact li {
            color: rgba(255, 255, 255, 0.62);
            line-height: 1.9;
        }

        .footer-link {
            display: block;
            text-decoration: none;
            margin-bottom: 10px;
            transition: color 0.3s;
        }

        .footer-link:hover {
            color: var(--accent);
        }

        .footer-contact {
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .footer-social {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .footer-social a {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--white);
            text-decoration: none;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-bottom {
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .about-layout,
            .location-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .about-image-wrap,
            .about-image-wrap img,
            .about-image-placeholder,
            .map-frame,
            .map-frame iframe {
                min-height: 320px;
            }
        }


        .hero-banner-slide { display: none; animation: fadeIn 0.7s ease; }
        .hero-banner-slide.active { display: block; }
        .hero-banner-art { max-width: min(520px, 82vw); max-height: 34vh; object-fit: cover; border-radius: 28px; margin-bottom: 20px; box-shadow: 0 24px 70px rgba(0,0,0,.35); }
        .hero-banner-description { max-width: 680px; margin: 1rem auto; color: rgba(255,255,255,0.88); line-height: 1.9; }
        .hero-banner-dots { display:flex; gap:8px; justify-content:center; margin-top:18px; }
        .hero-banner-dots button { width:10px; height:10px; border-radius:50%; border:0; background:rgba(255,255,255,.45); cursor:pointer; }
        .hero-banner-dots button.active { background: var(--accent); }
        .menu-category-tabs { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-bottom:28px; }
        .menu-category-tab { border:1px solid rgba(212,175,55,.5); background:rgba(255,255,255,.08); color:#fff; padding:10px 18px; border-radius:999px; cursor:pointer; }
        .menu-category-tab.active { background:var(--accent); color:#112; }
        .menu-category-tab.hidden-category { display:none; }
        .menu-category-tab.hidden-category.is-visible { display:inline-flex; }
        .menu-category-panel { display:none; }
        .menu-category-panel.active { display:block; }
        .show-more-categories { display:block; margin:0 auto 24px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .social-links {
                left: 15px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }

            .premium-section,
            .menu-section {
                padding: 72px 16px;
            }

            .section-title {
                font-size: 36px;
                margin-bottom: 44px;
            }

            .about-copy,
            .contact-card,
            .newsletter-card {
                padding: 26px;
            }

            .newsletter-form {
                border-radius: 24px;
                flex-direction: column;
            }

            .newsletter-form input {
                min-height: 52px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section with WebGL -->
    <section id="hero-section">
        <canvas id="webgl-canvas" aria-hidden="true"></canvas>
        <div class="hero-static-fallback" aria-hidden="true"></div>
        <div class="hero-overlay"></div>
        
        <div class="hero-content">
            <div class="logo-container">
                <!-- 9-Petal Lotus Logo -->
                <svg class="lotus-logo" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <g transform="translate(100, 100)">
                        <!-- Center circle -->
                        <circle cx="0" cy="0" r="15" fill="var(--accent)" opacity="0.8"/>
                        
                        <!-- 9 petals arranged in circle -->
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(0)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(40)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(80)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(120)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(160)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(200)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(240)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(280)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(320)"/>
                    </g>
                </svg>
            </div>
            
            <?php if (!empty($heroBanners)): ?>
                <div class="hero-banner-slider" data-hero-slider>
                    <?php foreach ($heroBanners as $index => $banner): ?>
                        <div class="hero-banner-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-hero-slide>
                            <?php if (!empty($banner['image'])): ?>
                                <picture>
                                    <?php if (!empty($banner['mobile_image'])): ?><source media="(max-width: 640px)" srcset="/uploads/banners/<?php echo homeEscape($banner['mobile_image']); ?>"><?php endif; ?>
                                    <source srcset="/uploads/banners/<?php echo homeEscape($banner['image']); ?>" type="image/webp">
                                    <img class="hero-banner-art" src="/uploads/banners/<?php echo homeEscape($banner['image']); ?>" alt="<?php echo homeEscape($banner['title']); ?>" <?php echo $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async">
                                </picture>
                            <?php endif; ?>
                            <h1 class="hero-title"><?php echo homeEscape($banner['title']); ?></h1>
                            <?php if (!empty($banner['subtitle'])): ?><p class="hero-subtitle"><?php echo homeEscape($banner['subtitle']); ?></p><?php endif; ?>
                            <?php if (!empty($banner['description'])): ?><p class="hero-banner-description"><?php echo homeEscape($banner['description']); ?></p><?php endif; ?>
                            <?php if (!empty($banner['button_text'])): ?><a href="<?php echo homeEscape($banner['button_link'] ?: '#menu'); ?>" class="glass-button"><?php echo homeEscape($banner['button_text']); ?></a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="hero-banner-dots" aria-label="انتخاب بنر">
                        <?php foreach ($heroBanners as $index => $banner): ?><button type="button" class="<?php echo $index === 0 ? 'active' : ''; ?>" data-hero-dot="<?php echo $index; ?>"></button><?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>
                <p class="hero-subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p>
                <a href="#menu" class="glass-button"><?php echo htmlspecialchars($ctaText); ?></a>
            <?php endif; ?>
        </div>
        
        <div class="scroll-indicator">
            <svg width="30" height="50" viewBox="0 0 30 50">
                <rect x="5" y="5" width="20" height="40" rx="10" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <circle cx="15" cy="15" r="3" fill="var(--accent)">
                    <animate attributeName="cy" from="15" to="35" dur="1.5s" repeatCount="indefinite"/>
                </circle>
            </svg>
        </div>
    </section>
    
    <!-- Social Links -->
    <div class="social-links">
        <?php if ($instagramUrl !== '#'): ?>
            <a href="<?php echo homeEscape($instagramUrl); ?>" class="social-link" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
        <?php endif; ?>
        <?php if ($telegramUrl !== '#'): ?>
            <a href="<?php echo homeEscape($telegramUrl); ?>" class="social-link" target="_blank" rel="noopener" aria-label="Telegram">✈️</a>
        <?php endif; ?>
        <?php if ($telLink !== ''): ?>
            <a href="tel:<?php echo homeEscape($telLink); ?>" class="social-link" aria-label="Call">📞</a>
        <?php endif; ?>
    </div>
    
    <!-- Menu Section -->
    <section id="menu" class="menu-section">
        <div class="container">
            <h2 class="section-title"><?php echo homeEscape($menuTitle); ?></h2>
            
            <?php if (!empty($menuCategories)): ?>
                <div class="menu-category-tabs" data-menu-tabs>
                    <?php foreach ($menuCategories as $index => $category): ?>
                        <button type="button" class="menu-category-tab <?php echo $index === 0 ? 'active' : ''; ?> <?php echo $index >= 3 ? 'hidden-category' : ''; ?>" data-category-target="cat-<?php echo (int)$category['id']; ?>">
                            <?php echo homeEscape(($category['icon'] ? $category['icon'] . ' ' : '') . $category['name_fa']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if (count($menuCategories) > 3): ?><button type="button" class="glass-button show-more-categories" data-show-more-categories>Show More</button><?php endif; ?>
                <?php foreach ($menuCategories as $index => $category): ?>
                    <div class="menu-category-panel <?php echo $index === 0 ? 'active' : ''; ?>" id="cat-<?php echo (int)$category['id']; ?>">
                        <div class="menu-grid">
                            <?php foreach (($menuItemsByCategory[$category['id']] ?? []) as $item): ?>
                                <div class="menu-card">
                                    <picture>
                                        <source srcset="/uploads/menu/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.webp'); ?>" type="image/webp">
                                        <img src="/uploads/menu/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name_fa']); ?>" class="menu-card-image" loading="lazy" decoding="async">
                                    </picture>
                                    <div class="menu-card-content">
                                        <h3 class="menu-card-title"><?php echo htmlspecialchars($item['name_fa']); ?></h3>
                                        <p class="menu-card-description"><?php echo htmlspecialchars($item['description_fa']); ?></p>
                                        <div class="menu-card-price"><?php echo number_format((float)($item['discount_price'] ?: $item['price']), 0); ?> تومان</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="menu-grid">
                    <?php foreach ($featuredItems as $item): ?>
                        <div class="menu-card">
                            <picture><source srcset="/uploads/menu/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.webp'); ?>" type="image/webp"><img src="/uploads/menu/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name_fa']); ?>" class="menu-card-image" loading="lazy" decoding="async"></picture>
                            <div class="menu-card-content"><h3 class="menu-card-title"><?php echo htmlspecialchars($item['name_fa']); ?></h3><p class="menu-card-description"><?php echo htmlspecialchars($item['description_fa']); ?></p><div class="menu-card-price"><?php echo number_format($item['price'], 0); ?> تومان</div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About / Story Section -->
    <section id="about" class="premium-section about-section">
        <div class="container">
            <div class="section-eyebrow">KEY STORY</div>
            <h2 class="section-title"><?php echo homeEscape($aboutTitle); ?></h2>
            <div class="about-layout">
                <div class="about-copy glass-panel">
                    <?php echo homeSafeHtml($aboutContent); ?>
                </div>
                <div class="about-image-wrap glass-panel">
                    <?php if ($aboutImage !== ''): ?>
                        <img src="<?php echo homeEscape($aboutImage); ?>" alt="<?php echo homeEscape($aboutTitle); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="about-image-placeholder" aria-hidden="true">✦</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Location + Map Section -->
    <section id="location" class="premium-section location-section">
        <div class="container">
            <div class="section-eyebrow">VISIT US</div>
            <h2 class="section-title"><?php echo homeEscape($locationTitle); ?></h2>
            <div class="location-grid">
                <div class="contact-card glass-panel">
                    <ul class="contact-list">
                        <li>
                            <span class="contact-label">آدرس</span>
                            <div class="contact-value"><?php echo homeEscape($addressFa); ?></div>
                            <div class="contact-value-en"><?php echo homeEscape($addressEn); ?></div>
                        </li>
                        <li>
                            <span class="contact-label">تلفن</span>
                            <div class="contact-value"><?php echo homeEscape($phoneNumber); ?></div>
                        </li>
                    </ul>
                    <div class="section-actions">
                        <?php if ($telLink !== ''): ?>
                            <a class="secondary-button" href="tel:<?php echo homeEscape($telLink); ?>">تماس فوری</a>
                        <?php endif; ?>
                        <a class="secondary-button" href="<?php echo homeEscape($baladMapUrl); ?>" target="_blank" rel="noopener">باز کردن در بلد</a>
                    </div>
                </div>
                <div class="map-frame glass-panel">
                    <iframe title="Balad map" src="<?php echo homeEscape($baladMapUrl); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Opening Hours Section -->
    <section id="hours" class="premium-section hours-section">
        <div class="container">
            <div class="section-eyebrow">OPENING HOURS</div>
            <h2 class="section-title"><?php echo homeEscape($hoursTitle); ?></h2>
            <div class="hours-status <?php echo $isCurrentlyOpen ? 'is-open' : 'is-closed'; ?>">
                <?php echo $isCurrentlyOpen ? 'هم‌اکنون باز است' : 'هم‌اکنون بسته است'; ?>
            </div>
            <div class="hours-grid">
                <?php foreach ($weekDays as $dayKey => $dayLabel): ?>
                    <div class="hours-row glass-panel <?php echo $dayKey === $currentDayKey ? 'current-day' : ''; ?>">
                        <span class="hours-day"><?php echo homeEscape($dayLabel); ?></span>
                        <span class="hours-time"><?php echo homeEscape(formatOpeningHours($openingHours[$dayKey] ?? null)); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Newsletter / Customer Club Section -->
    <section id="newsletter" class="premium-section newsletter-section">
        <div class="container">
            <div class="newsletter-card glass-panel">
                <div class="section-eyebrow">CUSTOMER CLUB</div>
                <h2 class="section-title"><?php echo homeEscape($newsletterTitle); ?></h2>
                <p class="newsletter-text"><?php echo homeEscape($newsletterText); ?></p>
                <?php if ($newsletterMessage !== ''): ?>
                    <div class="newsletter-message <?php echo homeEscape($newsletterStatus); ?>">
                        <?php echo homeEscape($newsletterMessage); ?>
                    </div>
                <?php endif; ?>
                <form class="newsletter-form" method="post" action="#newsletter" novalidate>
                    <input type="hidden" name="form_name" value="newsletter">
                    <input type="hidden" name="<?php echo homeEscape(CSRF_TOKEN_NAME); ?>" value="<?php echo homeEscape($newsletterToken); ?>">
                    <input type="text" name="subscriber_contact" value="<?php echo homeEscape($newsletterInput); ?>" placeholder="شماره تماس یا ایمیل" inputmode="email" aria-label="شماره تماس یا ایمیل" required>
                    <button type="submit" class="secondary-button">عضویت</button>
                </form>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3 class="footer-title"><?php echo homeEscape($siteName); ?></h3>
                    <p class="footer-text"><?php echo homeEscape($heroSubtitle); ?></p>
                    <div class="footer-social">
                        <?php if ($instagramUrl !== '#'): ?>
                            <a href="<?php echo homeEscape($instagramUrl); ?>" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
                        <?php endif; ?>
                        <?php if ($telegramUrl !== '#'): ?>
                            <a href="<?php echo homeEscape($telegramUrl); ?>" target="_blank" rel="noopener" aria-label="Telegram">✈️</a>
                        <?php endif; ?>
                        <?php if ($whatsappLink !== ''): ?>
                            <a href="<?php echo homeEscape($whatsappLink); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">💬</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h3 class="footer-title"><?php echo homeEscape($footerQuickLinksTitle); ?></h3>
                    <?php foreach ($quickLinks as $link): ?>
                        <?php
                            $linkLabel = is_array($link) ? ($link['label'] ?? '') : '';
                            $linkUrl = is_array($link) ? ($link['url'] ?? '#') : '#';
                        ?>
                        <?php if ($linkLabel !== ''): ?>
                            <a class="footer-link" href="<?php echo homeEscape($linkUrl); ?>"><?php echo homeEscape($linkLabel); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h3 class="footer-title"><?php echo homeEscape($footerContactTitle); ?></h3>
                    <ul class="footer-contact">
                        <li><?php echo homeEscape($phoneNumber); ?></li>
                        <li><?php echo homeEscape($email); ?></li>
                        <li><?php echo homeEscape($addressFa); ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo date('Y'); ?> <?php echo homeEscape($siteNameEn); ?> — <?php echo homeEscape($footerCopyright); ?>
            </div>
        </div>
    </footer>
    
    <!-- WebGL Script -->
    <script>
        // Progressive WebGL Hero Scene: full, reduced, static fallback.
        const canvas = document.getElementById('webgl-canvas');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const lowMemory = navigator.deviceMemory && navigator.deviceMemory <= 2;
        const gl = canvas && !prefersReducedMotion ? (canvas.getContext('webgl', { powerPreference: lowMemory ? 'low-power' : 'high-performance' }) || canvas.getContext('experimental-webgl')) : null;

        if (!gl) {
            document.documentElement.classList.add('no-webgl');
        } else {
            if (lowMemory) document.documentElement.classList.add('reduced-webgl');
            const frameStep = lowMemory ? 0.004 : 0.01;
            let animationId = 0;
            function resizeCanvas() {
                const ratio = lowMemory ? 1 : Math.min(window.devicePixelRatio || 1, 2);
                canvas.width = Math.floor(window.innerWidth * ratio);
                canvas.height = Math.floor(window.innerHeight * ratio);
                gl.viewport(0, 0, canvas.width, canvas.height);
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas, { passive: true });
            let time = 0;
            function render() {
                time += frameStep;
                gl.clearColor(Math.max(0, Math.sin(time * 0.5) * 0.05), 0.27 + Math.sin(time * 0.3) * 0.08, 0.28 + Math.sin(time * 0.4) * 0.08, 1);
                gl.clear(gl.COLOR_BUFFER_BIT);
                animationId = requestAnimationFrame(render);
            }
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) cancelAnimationFrame(animationId);
                else render();
            });
            render();
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const slides = Array.from(document.querySelectorAll('[data-hero-slide]'));
            const dots = Array.from(document.querySelectorAll('[data-hero-dot]'));
            let current = 0;
            function showSlide(index) { if (!slides.length) return; slides[current].classList.remove('active'); dots[current]?.classList.remove('active'); current = index % slides.length; slides[current].classList.add('active'); dots[current]?.classList.add('active'); }
            dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));
            if (slides.length > 1) setInterval(() => showSlide((current + 1) % slides.length), 6000);

            const tabs = Array.from(document.querySelectorAll('[data-category-target]'));
            const panels = Array.from(document.querySelectorAll('.menu-category-panel'));
            tabs.forEach(tab => tab.addEventListener('click', () => { tabs.forEach(t => t.classList.remove('active')); panels.forEach(p => p.classList.remove('active')); tab.classList.add('active'); document.getElementById(tab.dataset.categoryTarget)?.classList.add('active'); }));
            document.querySelector('[data-show-more-categories]')?.addEventListener('click', function () { document.querySelectorAll('.hidden-category').forEach(el => el.classList.add('is-visible')); this.remove(); });
        });
    </script>
</body>
</html>
