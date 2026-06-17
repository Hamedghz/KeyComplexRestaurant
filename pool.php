<?php
/**
 * Pool Leads Collection Page
 * KEY Restaurant Swimming Pool Customer Lead Form
 */

require_once __DIR__ . '/core/bootstrap.php';

$pageTitle = 'فرم استخر ';
$message = '';
$status = '';
$poolOptions = [
    'استخر هامون',
    'استخر دهکده المپیک',
    'استخر خانه شنا',
];
$customerTypes = [
    'ادارات',
    'تفریحی',
    'آموزشی',
    'آب درمانی',
];
$formData = ['full_name' => '', 'mobile' => '', 'pool_name' => '', 'customer_type' => '', 'acquisition_source' => ''];

// Get acquisition sources
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT title FROM acquisition_sources WHERE active = 1 ORDER BY sort_order ASC, title ASC");
    $sources = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($sources)) {
        $sources = ['Instagram', 'Telegram', 'Google', 'Balad', 'دوستان', 'وب‌سایت', 'تبلیغات', 'سایر'];
    }
} catch (Throwable $e) {
    $sources = ['Instagram', 'Telegram', 'Google', 'Balad', 'دوستان', 'وب‌سایت', 'تبلیغات', 'سایر'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $formData['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $formData['mobile'] = trim((string)($_POST['mobile'] ?? ''));
    $formData['pool_name'] = trim((string)($_POST['pool_name'] ?? ''));
    $formData['customer_type'] = trim((string)($_POST['customer_type'] ?? ''));
    $formData['acquisition_source'] = trim((string)($_POST['acquisition_source'] ?? ''));

    $submittedToken = (string)($_POST[CSRF_TOKEN_NAME] ?? '');

    if (!verifyCSRFToken($submittedToken)) {
        $status = 'error';
        $message = 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
    } elseif ($formData['full_name'] === '') {
        $status = 'error';
        $message = 'لطفاً نام کامل خود را وارد کنید.';
    } elseif ($formData['mobile'] === '') {
        $status = 'error';
        $message = 'لطفاً شماره موبایل خود را وارد کنید.';
    } elseif (!preg_match('/^09\d{9}$/', $formData['mobile'])) {
        $status = 'error';
        $message = 'شماره موبایل باید با 09 شروع شده و 11 رقم باشد.';
    } elseif (!in_array($formData['pool_name'], $poolOptions, true)) {
        $status = 'error';
        $message = 'لطفاً استخر موردنظر را انتخاب کنید.';
    } elseif (!in_array($formData['customer_type'], $customerTypes, true)) {
        $status = 'error';
        $message = 'لطفاً نوع مشتری را انتخاب کنید.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO pool_leads (full_name, mobile, pool_name, customer_type, acquisition_source, status)
                VALUES (:full_name, :mobile, :pool_name, :customer_type, :acquisition_source, 'new')
            ");
            $stmt->execute([
                'full_name' => $formData['full_name'],
                'mobile' => $formData['mobile'],
                'pool_name' => $formData['pool_name'],
                'customer_type' => $formData['customer_type'],
                'acquisition_source' => $formData['acquisition_source'] ?: null,
            ]);

            $status = 'success';
            $message = 'اطلاعات شما با موفقیت ثبت شد. به زودی با شما تماس خواهیم گرفت.';
            $formData = ['full_name' => '', 'mobile' => '', 'pool_name' => '', 'customer_type' => '', 'acquisition_source' => ''];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $status = 'error';
                $message = 'این شماره موبایل قبلاً ثبت شده است.';
            } else {
                error_log('Pool lead submission error: ' . $e->getMessage());
                $status = 'error';
                $message = 'خطایی رخ داده است. لطفاً بعداً تلاش کنید.';
            }
        }
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - KEY</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #004647;
            --accent: #D4AF37;
            --success: #28a745;
            --danger: #dc3545;
            --white: #ffffff;
            --black: #000000;
        }

        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #002829 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 36px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .logo p {
            color: var(--accent);
            font-size: 18px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .message.error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .radio-group {
            display: grid;
            gap: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s;
        }

        .radio-option input {
            width: auto;
        }

        .radio-option:has(input:checked) {
            border-color: var(--accent);
            background: rgba(212, 175, 55, 0.08);
        }

        .btn {
            width: 100%;
            padding: 18px;
            background: var(--accent);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #c49d2f;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>استخر </h1>
            <p>ثبت‌ و دریافت اطلاعات</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($status); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="<?php echo htmlspecialchars(CSRF_TOKEN_NAME); ?>" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label class="form-label" for="full_name">نام کامل *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                       value="<?php echo htmlspecialchars($formData['full_name']); ?>" 
                       placeholder="نام و نام خانوادگی" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="mobile">شماره موبایل *</label>
                <input type="tel" id="mobile" name="mobile" class="form-control" 
                       value="<?php echo htmlspecialchars($formData['mobile']); ?>" 
                       placeholder="09xxxxxxxxx" pattern="09[0-9]{9}" required>
            </div>

            <div class="form-group">
                <label class="form-label">انتخاب استخر *</label>
                <div class="radio-group">
                    <?php foreach ($poolOptions as $poolOption): ?>
                        <label class="radio-option">
                            <input type="radio" name="pool_name" value="<?php echo htmlspecialchars($poolOption); ?>" <?php echo $formData['pool_name'] === $poolOption ? 'checked' : ''; ?> required>
                            <span><?php echo htmlspecialchars($poolOption); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">نوع مشتری *</label>
                <div class="radio-group">
                    <?php foreach ($customerTypes as $customerType): ?>
                        <label class="radio-option">
                            <input type="radio" name="customer_type" value="<?php echo htmlspecialchars($customerType); ?>" <?php echo $formData['customer_type'] === $customerType ? 'checked' : ''; ?> required>
                            <span><?php echo htmlspecialchars($customerType); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="acquisition_source">از کجا ما را شناختید؟</label>
                <select id="acquisition_source" name="acquisition_source" class="form-control">
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?php echo htmlspecialchars($source); ?>" 
                                <?php echo $formData['acquisition_source'] === $source ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($source); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">ثبت‌</button>
        </form>

        <div class="back-link">
            <a href="/">← بازگشت به صفحه اصلی</a>
        </div>
    </div>
    <script src="/assets/js/analytics-tracker.js" defer></script>
</body>
</html>
