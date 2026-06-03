<?php
/**
 * Admin Panel - Login Page
 */

session_start();

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . (($_SESSION['admin_role'] ?? '') === 'employee' ? 'employee-dashboard.php' : 'dashboard.php'));
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyRequestCsrf()) {
        $error = 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($error === '' && (empty($username) || empty($password))) {
        $error = 'لطفاً نام کاربری و رمز عبور را وارد کنید';
    } elseif ($error === '') {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            header('Location: ' . ($result['redirect'] ?? 'dashboard.php'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت - KEY</title>
    <style>
        @font-face { font-family: Vazirmatn; src: url('../assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); font-display: swap; }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: linear-gradient(135deg, #004647 0%, #002829 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #004647;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #D4AF37;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            direction: rtl;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #004647;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #004647 0%, #006668 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 70, 71, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>KEY</h1>
            <p>پنل مدیریت رستوران و کافه</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="username">نام کاربری یا ایمیل</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">ورود به پنل</button>
        </form>
        
        <div class="footer-text">
            نام کاربری پیش‌فرض: <strong>admin</strong><br>
            رمز عبور پیش‌فرض: <strong>admin123</strong>
        </div>
    </div>
</body>
</html>
