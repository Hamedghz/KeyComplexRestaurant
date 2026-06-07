<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'پنل مدیریت'; ?> - KEY Admin</title>
    <?php echo localFontPreloadLinks(); ?>
    <style>
        @font-face { font-family: Vazirmatn; src: url('../assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); font-display: swap; }
        @font-face { font-family: Vazirmatn; src: url('../assets/fonts/Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-display: swap; }
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
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: #f5f6fa;
            direction: rtl;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary) 0%, #002829 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h1 {
            font-size: 28px;
            color: var(--accent);
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            display: block;
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-right: 3px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            border-right-color: var(--accent);
        }
        
        .menu-item span {
            margin-left: 10px;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            font-weight: bold;
        }
        
        .logout-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: rgba(220, 53, 69, 0.2);
            color: white;
            border: 1px solid rgba(220, 53, 69, 0.5);
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(220, 53, 69, 0.4);
        }
        
        /* Sticky Admin Header */
        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 900;
            margin: -30px -30px 30px;
            padding: 14px 30px;
            min-height: 68px;
            background: rgba(255,255,255,0.96);
            border-bottom: 1px solid #e8ecef;
            box-shadow: 0 2px 14px rgba(0,0,0,0.06);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .admin-topbar-brand, .admin-topbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-topbar-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--primary);
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 24px;
        }
        .admin-topbar-title strong, .admin-topbar-user strong { display:block; color: var(--primary); }
        .admin-topbar-title small, .admin-topbar-user small { color:#666; }
        .admin-profile-menu {
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 7px 12px;
            color: var(--primary);
            text-decoration: none;
            background:#fff;
        }
        .admin-logout-inline {
            border-radius: 999px;
            padding: 8px 14px;
            background: rgba(220,53,69,.1);
            color: var(--danger);
            text-decoration: none;
            border: 1px solid rgba(220,53,69,.25);
        }

        /* Main Content */
        .main-content {
            margin-right: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 14px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            color: var(--primary);
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-left: 20px;
        }
        
        .stat-content h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 14px;
        }
        
        .stat-primary { border-right: 4px solid var(--primary); }
        .stat-success { border-right: 4px solid var(--success); }
        .stat-warning { border-right: 4px solid var(--warning); }
        .stat-info { border-right: 4px solid var(--info); }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--primary);
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #003536;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: #333;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-primary { background: #cce5ff; color: #004085; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: var(--light);
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .quick-action-btn .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Utilities */
        .text-muted {
            color: #6c757d;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-3 { margin-top: 1rem; }
        .mb-3 { margin-bottom: 1rem; }
        

        .admin-filter { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:20px; }
        .import-box { margin: 15px 0; padding: 15px; background:#fff8e1; border-radius:8px; }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; }
        .alert-info { background:#d1ecf1; color:#0c5460; }
        .btn-sm { padding: 6px 10px; font-size: 12px; }
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(100%);
            }
            
            .main-content {
                margin-right: 0;
            }
            .admin-topbar { margin: -30px -30px 20px; padding: 12px 16px; flex-direction: column; align-items: stretch; }
            .admin-topbar-user { flex-wrap: wrap; }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h1>KEY</h1>
            <p>پنل مدیریت</p>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <span>📊</span> داشبورد
            </a>
            <a href="crm.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'crm.php' ? 'active' : ''; ?>">
                <span>👤</span> CRM
            </a>
            <a href="crm-reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'crm-reports.php' ? 'active' : ''; ?>">
                <span>📣</span> گزارش منابع جذب
            </a>
            <a href="acquisition-sources.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'acquisition-sources.php' ? 'active' : ''; ?>">
                <span>🧲</span> منابع جذب
            </a>
            <a href="matches.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>">
                <span>⚽</span> مسابقات
            </a>
            <a href="predictions.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'predictions.php' ? 'active' : ''; ?>">
                <span>🏆</span> پیش‌بینی
            </a>
            <a href="banners.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'banners.php' ? 'active' : ''; ?>">
                <span>🖼️</span> بنر اصلی
            </a>
            <a href="categories.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <span>📁</span> فیلترهای منو
            </a>
            <a href="menu-items.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'menu-items.php' ? 'active' : ''; ?>">
                <span>🍽️</span> آیتم‌های منو
            </a>
            <a href="surveys.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'surveys.php' ? 'active' : ''; ?>">
                <span>📝</span> نظرسنجی‌ها
            </a>
            <a href="survey-responses.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'survey-responses.php' ? 'active' : ''; ?>">
                <span>📨</span> پاسخ‌های نظرسنجی
            </a>
            <a href="orders.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <span>📋</span> سفارشات
            </a>
            <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <span>👥</span> کاربران
            </a>
            <a href="feedback.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>">
                <span>⭐</span> نظرات
            </a>
            <a href="media.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'media.php' ? 'active' : ''; ?>">
                <span>🗂️</span> رسانه‌ها
            </a>
            <a href="employee-dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'employee-dashboard.php' ? 'active' : ''; ?>">
                <span>🧑‍💼</span> داشبورد کارمند
            </a>
            <a href="employee-evaluations.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'employee-evaluations.php' ? 'active' : ''; ?>">
                <span>📝</span> ارزیابی همکاران
            </a>
            <a href="employee-performance.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'employee-performance.php' ? 'active' : ''; ?>">
                <span>📈</span> عملکرد کارکنان
            </a>
            <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <span>⚙️</span> تنظیمات
            </a>
            <a href="social-links.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'social-links.php' ? 'active' : ''; ?>">
                <span>🔗</span> شبکه‌های اجتماعی
            </a>
            <a href="key-story.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'key-story.php' ? 'active' : ''; ?>">
                <span>📖</span> مدیریت داستان KEY
            </a>
            <a href="pool-leads.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pool-leads.php' ? 'active' : ''; ?>">
                <span>🏊</span> لیدهای استخر
            </a>
            <a href="analytics.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                <span>📈</span> Visitor Logs
            </a>
            <a href="analytics-traffic-sources.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics-traffic-sources.php' ? 'active' : ''; ?>">
                <span>🧭</span> Traffic Sources
            </a>
            <a href="visitor-analytics.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'visitor-analytics.php' ? 'active' : ''; ?>">
                <span>🧩</span> Visitor Path Analytics
            </a>
            <a href="analytics-live.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics-live.php' ? 'active' : ''; ?>">
                <span>🟢</span> Live Visitors
            </a>
            <a href="analytics-geographic.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics-geographic.php' ? 'active' : ''; ?>">
                <span>🌍</span> Geographic Analytics
            </a>
            <a href="analytics-device.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics-device.php' ? 'active' : ''; ?>">
                <span>📱</span> Device Analytics
            </a>
            <a href="analytics-export.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics-export.php' ? 'active' : ''; ?>">
                <span>📤</span> Export Center
            </a>
            <a href="system-update.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'system-update.php' ? 'active' : ''; ?>">
                <span>⬆️</span> بروزرسانی سیستم
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <header class="admin-topbar" role="banner">
            <div class="admin-topbar-brand">
                <div class="admin-topbar-logo" aria-label="KEY logo">K</div>
                <div class="admin-topbar-title">
                    <strong>KEY Administrator</strong>
                    <small><?php echo htmlspecialchars($pageTitle ?? 'Admin Panel', ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>
            <div class="admin-topbar-user">
                <div>
                    <strong><?php echo htmlspecialchars($currentAdmin['username'] ?? $currentAdmin['full_name'] ?? 'admin', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small><?php echo htmlspecialchars($currentAdmin['role'] ?? 'super_admin', ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <a class="admin-profile-menu" href="users.php?action=edit&id=<?php echo urlencode((string)($currentAdmin['id'] ?? '')); ?>">Profile menu</a>
                <a class="admin-logout-inline" href="logout.php">Logout</a>
            </div>
        </header>
        <div class="page-header">
            <h1><?php echo $pageTitle ?? 'پنل مدیریت'; ?></h1>
            <div class="breadcrumb">
                KEY Restaurant & Coffeehouse
            </div>
        </div>
