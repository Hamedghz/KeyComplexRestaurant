<?php
/**
 * KEY Restaurant & Coffeehouse - Professional Installer
 * One-Click Installation Wizard
 */

session_start();

// Security: Disable after first successful install
if (file_exists(__DIR__ . '/config/installed.lock')) {
    die('
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Installed</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; background: #004647; color: white; }
            .message { background: rgba(255,255,255,0.1); padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; }
            .warning { color: #D4AF37; font-size: 48px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="message">
            <div class="warning">⚠️</div>
            <h1>سیستم قبلاً نصب شده است</h1>
            <p>برای نصب مجدد، فایل <code>config/installed.lock</code> را حذف کنید.</p>
            <p><a href="public_html/admin" style="color: #D4AF37;">ورود به پنل مدیریت</a></p>
        </div>
    </body>
    </html>
    ');
}

// Installation steps
$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Step 2: Database Configuration
            $_SESSION['db_config'] = [
                'host' => $_POST['db_host'] ?? 'localhost',
                'name' => $_POST['db_name'] ?? 'key_restaurant',
                'user' => $_POST['db_user'] ?? '',
                'pass' => $_POST['db_pass'] ?? '',
                'prefix' => $_POST['db_prefix'] ?? ''
            ];
            
            // Test connection
            try {
                $dsn = "mysql:host={$_SESSION['db_config']['host']};charset=utf8mb4";
                $pdo = new PDO($dsn, $_SESSION['db_config']['user'], $_SESSION['db_config']['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check MySQL version
                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                if (version_compare($version, '5.7.0', '<')) {
                    $errors[] = "MySQL version must be 5.7 or higher. Current: $version";
                } else {
                    $success[] = "Database connection successful! MySQL $version";
                    header('Location: install.php?step=3');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = "Database connection failed: " . $e->getMessage();
            }
            break;
            
        case 3:
            // Step 3: Admin Account
            $_SESSION['admin_config'] = [
                'username' => $_POST['admin_user'] ?? 'admin',
                'email' => $_POST['admin_email'] ?? 'admin@keyrestaurant.com',
                'password' => $_POST['admin_pass'] ?? '',
                'full_name' => $_POST['admin_name'] ?? 'مدیر سیستم'
            ];
            
            if (strlen($_SESSION['admin_config']['password']) < 8) {
                $errors[] = "رمز عبور باید حداقل 8 کاراکتر باشد";
            } else {
                header('Location: install.php?step=4');
                exit;
            }
            break;
            
        case 4:
            // Step 4: Execute Installation
            $result = executeInstallation();
            if ($result['success']) {
                header('Location: install.php?step=5');
                exit;
            } else {
                $errors = $result['errors'];
            }
            break;
    }
}

/**
 * Execute the complete installation
 */
function executeInstallation() {
    $errors = [];
    
    try {
        // Connect to database
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$config['name']}`");
        
        // Execute schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        
        // Remove database creation from schema (already done)
        $schema = preg_replace('/CREATE DATABASE.*?;/s', '', $schema);
        $schema = preg_replace('/USE .*?;/s', '', $schema);
        
        // Execute SQL statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Create admin user
        $admin = $_SESSION['admin_config'];
        $hashedPassword = password_hash($admin['password'], PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO admins (username, email, password, full_name, role) 
            VALUES (:username, :email, :password, :full_name, 'super_admin')
            ON DUPLICATE KEY UPDATE password = :password
        ");
        
        $stmt->execute([
            'username' => $admin['username'],
            'email' => $admin['email'],
            'password' => $hashedPassword,
            'full_name' => $admin['full_name']
        ]);
        
        // Create config.php
        $configContent = generateConfigFile($config);
        
        if (!is_dir(__DIR__ . '/config')) {
            mkdir(__DIR__ . '/config', 0755, true);
        }
        
        file_put_contents(__DIR__ . '/config/database.php', $configContent);
        
        // Create lock file
        file_put_contents(__DIR__ . '/config/installed.lock', date('Y-m-d H:i:s'));
        
        // Create necessary directories
        $dirs = [
            'public_html/uploads/menu',
            'public_html/uploads/logo',
            'public_html/uploads/hero',
            'storage/logs',
            'storage/cache'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir(__DIR__ . '/' . $dir)) {
                mkdir(__DIR__ . '/' . $dir, 0755, true);
            }
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'errors' => [$e->getMessage()]];
    }
}

/**
 * Generate config file content
 */
function generateConfigFile($config) {
    return <<<PHP
<?php
/**
 * Database Configuration
 * Generated by installer on {date('Y-m-d H:i:s')}
 */

define('DB_HOST', '{$config['host']}');
define('DB_NAME', '{$config['name']}');
define('DB_USER', '{$config['user']}');
define('DB_PASS', '{$config['pass']}');
define('DB_CHARSET', 'utf8mb4');

// PDO connection class
class Database {
    private static \$instance = null;
    private \$connection;
    
    private function __construct() {
        try {
            \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            \$this->connection = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log("Database Connection Error: " . \$e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'خطا در اتصال به پایگاه داده'
            ]));
        }
    }
    
    public static function getInstance() {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }
    
    public function getConnection() {
        return \$this->connection;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
PHP;
}

/**
 * Check system requirements
 */
function checkRequirements() {
    $checks = [];
    
    // PHP Version
    $checks['php_version'] = [
        'name' => 'PHP Version',
        'required' => '8.0.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '8.0.0', '>=')
    ];
    
    // PDO MySQL
    $checks['pdo_mysql'] = [
        'name' => 'PDO MySQL Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo_mysql')
    ];
    
    // JSON
    $checks['json'] = [
        'name' => 'JSON Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json')
    ];
    
    // mbstring
    $checks['mbstring'] = [
        'name' => 'Mbstring Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('mbstring')
    ];
    
    // Config directory writable
    $checks['config_writable'] = [
        'name' => 'Config Directory Writable',
        'required' => 'Yes',
        'current' => is_writable(__DIR__) ? 'Yes' : 'No',
        'status' => is_writable(__DIR__)
    ];
    
    return $checks;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEY Restaurant - نصب سیستم</title>
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
            --warning: #ffc107;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #002829 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
        }
        
        .installer-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .installer-header {
            background: linear-gradient(135deg, var(--primary) 0%, #003536 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .installer-header h1 {
            font-size: 36px;
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .installer-header p {
            opacity: 0.9;
        }
        
        .progress-bar {
            display: flex;
            justify-content: space-between;
            padding: 30px 40px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .progress-step::before {
            content: '';
            position: absolute;
            top: 15px;
            right: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 0;
        }
        
        .progress-step:first-child::before {
            display: none;
        }
        
        .progress-step.active .step-number {
            background: var(--primary);
            color: white;
        }
        
        .progress-step.completed .step-number {
            background: var(--success);
            color: white;
        }
        
        .progress-step.completed::before {
            background: var(--success);
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .step-label {
            font-size: 12px;
            color: #6c757d;
        }
        
        .installer-content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 12px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #dee2e6;
        }
        
        .requirements-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-icon {
            font-size: 20px;
        }
        
        .status-pass { color: var(--success); }
        .status-fail { color: var(--danger); }
        
        .btn {
            display: inline-block;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #003536 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 70, 71, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-3 { margin-top: 1rem; }
        .mb-3 { margin-bottom: 1rem; }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>KEY</h1>
            <p>نصب سیستم مدیریت رستوران و کافه</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">بررسی سیستم</div>
            </div>
            <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">پایگاه داده</div>
            </div>
            <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">حساب مدیر</div>
            </div>
            <div class="progress-step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-label">نصب</div>
            </div>
            <div class="progress-step <?php echo $step >= 5 ? 'active' : ''; ?>">
                <div class="step-number">5</div>
                <div class="step-label">اتمام</div>
            </div>
        </div>
        
        <div class="installer-content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>خطا:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php foreach ($success as $msg): ?>
                        <div><?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <!-- Step 1: System Requirements -->
                <h2 class="mb-3">بررسی پیش‌نیازهای سیستم</h2>
                
                <?php $checks = checkRequirements(); ?>
                <?php $allPassed = !in_array(false, array_column($checks, 'status')); ?>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>مورد</th>
                            <th>مورد نیاز</th>
                            <th>وضعیت فعلی</th>
                            <th>نتیجه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check): ?>
                            <tr>
                                <td><?php echo $check['name']; ?></td>
                                <td><?php echo $check['required']; ?></td>
                                <td><?php echo $check['current']; ?></td>
                                <td class="status-icon <?php echo $check['status'] ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $check['status'] ? '✓' : '✗'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($allPassed): ?>
                    <div class="alert alert-success">
                        تمام پیش‌نیازها برآورده شده است. می‌توانید ادامه دهید.
                    </div>
                    <a href="install.php?step=2" class="btn btn-primary btn-block">ادامه نصب</a>
                <?php else: ?>
                    <div class="alert alert-danger">
                        برخی پیش‌نیازها برآورده نشده است. لطفاً آنها را رفع کنید.
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Database Configuration -->
                <h2 class="mb-3">تنظیمات پایگاه داده</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label>آدرس سرور (Host)</label>
                        <input type="text" name="db_host" value="localhost" required>
                        <small>معمولاً localhost است</small>
                    </div>
                    
                    <div class="form-group">
                        <label>نام پایگاه داده</label>
                        <input type="text" name="db_name" value="key_restaurant" required>
                        <small>نام دیتابیس که قبلاً ساخته‌اید</small>
                    </div>
                    
                    <div class="form-group">
                        <label>نام کاربری دیتابیس</label>
                        <input type="text" name="db_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label>رمز عبور دیتابیس</label>
                        <input type="password" name="db_pass">
                        <small>در صورت نداشتن رمز، خالی بگذارید</small>
                    </div>
                    
                    <div class="form-group">
                        <label>پیشوند جداول (اختیاری)</label>
                        <input type="text" name="db_prefix" placeholder="key_">
                        <small>برای جلوگیری از تداخل با جداول دیگر</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">تست اتصال و ادامه</button>
                </form>
                
            <?php elseif ($step == 3): ?>
                <!-- Step 3: Admin Account -->
                <h2 class="mb-3">ایجاد حساب مدیر</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label>نام کاربری</label>
                        <input type="text" name="admin_user" value="admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ایمیل</label>
                        <input type="email" name="admin_email" value="admin@keyrestaurant.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>نام کامل</label>
                        <input type="text" name="admin_name" value="مدیر سیستم" required>
                    </div>
                    
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="admin_pass" required minlength="8">
                        <small>حداقل 8 کاراکتر</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">ادامه</button>
                </form>
                
            <?php elseif ($step == 4): ?>
                <!-- Step 4: Installation -->
                <h2 class="mb-3 text-center">در حال نصب...</h2>
                
                <div class="text-center">
                    <div class="loading"></div>
                    <p class="mt-3">لطفاً صبر کنید. سیستم در حال نصب است...</p>
                </div>
                
                <form method="POST" id="installForm">
                    <input type="hidden" name="execute" value="1">
                </form>
                
                <script>
                    setTimeout(function() {
                        document.getElementById('installForm').submit();
                    }, 2000);
                </script>
                
            <?php elseif ($step == 5): ?>
                <!-- Step 5: Complete -->
                <div class="text-center">
                    <div class="success-icon">✓</div>
                    <h2 class="mb-3">نصب با موفقیت انجام شد!</h2>
                    
                    <div class="alert alert-success">
                        <p><strong>سیستم KEY با موفقیت نصب شد.</strong></p>
                        <p>اطلاعات ورود شما:</p>
                        <p>نام کاربری: <strong><?php echo htmlspecialchars($_SESSION['admin_config']['username'] ?? 'admin'); ?></strong></p>
                        <p>رمز عبور: همان رمزی که وارد کردید</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>مهم:</strong> لطفاً فایل <code>install.php</code> را از سرور حذف کنید.
                    </div>
                    
                    <a href="public_html/admin" class="btn btn-success btn-block">ورود به پنل مدیریت</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
