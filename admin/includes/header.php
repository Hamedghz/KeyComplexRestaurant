<?php
$adminCurrentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ''), PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? ''));

if (!function_exists('adminMenuCanAccessRole')) {
    function adminMenuCanAccessRole(?array $admin, string $requiredRole): bool {
        $roles = ['employee' => 0, 'manager' => 1, 'admin' => 2, 'super_admin' => 3];
        $currentRole = (string)($admin['role'] ?? ($_SESSION['admin_role'] ?? 'employee'));

        return ($roles[$currentRole] ?? -1) >= ($roles[$requiredRole] ?? 0);
    }
}

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adminMenuIsActive')) {
    function adminMenuIsActive(array $item, string $currentPage): bool {
        $activePages = $item['active'] ?? [$item['url'] ?? ''];

        return in_array($currentPage, $activePages, true);
    }
}

$adminMenuGroups = [
    [
        'title' => 'داشبورد',
        'items' => [
            ['url' => 'dashboard.php', 'icon' => '📊', 'title' => 'داشبورد', 'role' => 'employee'],
            ['url' => 'employee-dashboard.php', 'icon' => '🧑‍💼', 'title' => 'داشبورد کارمند', 'role' => 'employee'],
        ],
    ],
    [
        'title' => 'مشتریان و CRM',
        'items' => [
            ['url' => 'crm.php', 'icon' => '👤', 'title' => 'CRM', 'role' => 'manager'],
            ['url' => 'crm-reports.php', 'icon' => '📣', 'title' => 'گزارش منابع جذب', 'role' => 'manager'],
            ['url' => 'acquisition-sources.php', 'icon' => '🧲', 'title' => 'منابع جذب', 'role' => 'manager'],
            ['url' => 'orders.php', 'icon' => '📋', 'title' => 'سفارشات', 'role' => 'employee'],
        ],
    ],
    [
        'title' => 'مسابقات و پیش‌بینی',
        'items' => [
            ['url' => 'matches.php', 'icon' => '⚽', 'title' => 'مسابقات', 'role' => 'manager'],
            ['url' => 'predictions.php', 'icon' => '🏆', 'title' => 'پیش‌بینی', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'سایت و محتوا',
        'items' => [
            ['url' => 'banners.php', 'icon' => '🖼️', 'title' => 'بنر اصلی', 'role' => 'manager'],
            ['url' => 'social-links.php', 'icon' => '🔗', 'title' => 'شبکه‌های اجتماعی', 'role' => 'admin'],
            ['url' => 'key-story.php', 'icon' => '📖', 'title' => 'مدیریت داستان KEY', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'منو و محصولات',
        'items' => [
            ['url' => 'categories.php', 'icon' => '📁', 'title' => 'فیلترهای منو', 'role' => 'manager'],
            ['url' => 'menu-items.php', 'icon' => '🍽️', 'title' => 'آیتم‌های منو', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'نظرسنجی و ارزیابی',
        'items' => [
            ['url' => 'surveys.php', 'icon' => '📝', 'title' => 'نظرسنجی‌ها', 'role' => 'admin'],
            ['url' => 'survey-responses.php', 'icon' => '📨', 'title' => 'پاسخ‌های نظرسنجی', 'role' => 'manager'],
            ['url' => 'feedback.php', 'icon' => '⭐', 'title' => 'نظرات', 'role' => 'manager'],
            ['url' => 'evaluation-builder.php', 'icon' => '🧭', 'title' => 'Build Evaluation', 'role' => 'admin', 'active' => ['evaluation-builder.php', 'employee-evaluation-settings.php']],
            ['url' => 'employee-evaluations.php', 'icon' => '📝', 'title' => 'Evaluate', 'role' => 'employee'],
            ['url' => 'employee-tests.php', 'icon' => '🧠', 'title' => 'آزمون‌های من', 'role' => 'employee'],
            ['url' => 'employee-performance.php', 'icon' => '📈', 'title' => 'View', 'role' => 'manager'],
            ['url' => 'employee-assessments.php', 'icon' => '🧪', 'title' => 'Assessment Results', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'استخر و لیدها',
        'items' => [
            ['url' => 'pool-leads.php', 'icon' => '🏊', 'title' => 'لیدهای استخر', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'آنالیتیکس و گزارش‌ها',
        'items' => [
            ['url' => 'analytics.php', 'icon' => '📈', 'title' => 'Visitor Logs', 'role' => 'manager'],
            ['url' => 'analytics-traffic-sources.php', 'icon' => '🧭', 'title' => 'Traffic Sources', 'role' => 'manager'],
            ['url' => 'visitor-analytics.php', 'icon' => '🧩', 'title' => 'Visitor Path Analytics', 'role' => 'manager'],
            ['url' => 'analytics-live.php', 'icon' => '🟢', 'title' => 'Live Visitors', 'role' => 'manager'],
            ['url' => 'analytics-geographic.php', 'icon' => '🌍', 'title' => 'Geographic Analytics', 'role' => 'manager'],
            ['url' => 'analytics-device.php', 'icon' => '📱', 'title' => 'Device Analytics', 'role' => 'manager'],
            ['url' => 'analytics-export.php', 'icon' => '📤', 'title' => 'Export Center', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'فایل‌ها و مستندات',
        'items' => [
            ['url' => 'media.php', 'icon' => '🗂️', 'title' => 'رسانه‌ها', 'role' => 'manager'],
        ],
    ],
    [
        'title' => 'کاربران و دسترسی',
        'items' => [
            ['url' => 'users.php', 'icon' => '👥', 'title' => 'کاربران', 'role' => 'admin'],
        ],
    ],
    [
        'title' => 'تنظیمات و سیستم',
        'items' => [
            ['url' => 'settings.php', 'icon' => '⚙️', 'title' => 'تنظیمات', 'role' => 'admin'],
            ['url' => 'system-update.php', 'icon' => '⬆️', 'title' => 'بروزرسانی سیستم', 'role' => 'super_admin'],
        ],
    ],
];
?>
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
            padding: 14px 0 20px;
        }

        .menu-group {
            margin: 4px 0;
        }

        .menu-group summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 20px;
            color: rgba(255,255,255,0.82);
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            border-right: 3px solid transparent;
            transition: all 0.3s;
        }

        .menu-group summary::-webkit-details-marker {
            display: none;
        }

        .menu-group summary::before {
            content: '▾';
            font-size: 11px;
            transform: rotate(90deg);
            transition: transform 0.2s ease;
            opacity: 0.85;
        }

        .menu-group[open] summary::before {
            transform: rotate(0deg);
        }

        .menu-group summary:hover,
        .menu-group.active summary {
            background: rgba(255,255,255,0.08);
            color: white;
            border-right-color: rgba(212,175,55,0.75);
        }

        .menu-group-items {
            padding: 2px 0 6px;
        }
        
        .menu-item {
            display: block;
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-right: 3px solid transparent;
        }

        .menu-group .menu-item {
            padding: 10px 36px 10px 20px;
            font-size: 14px;
            color: rgba(255,255,255,0.92);
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
        
        <nav class="sidebar-menu" aria-label="Admin navigation">
            <?php foreach ($adminMenuGroups as $menuGroup): ?>
                <?php
                $visibleItems = array_values(array_filter($menuGroup['items'], function ($item) use ($currentAdmin) {
                    return adminMenuCanAccessRole($currentAdmin ?? null, $item['role'] ?? 'employee');
                }));
                $groupIsActive = false;
                foreach ($visibleItems as $visibleItem) {
                    if (adminMenuIsActive($visibleItem, $adminCurrentPage)) {
                        $groupIsActive = true;
                        break;
                    }
                }
                ?>
                <?php if ($visibleItems): ?>
                    <details class="menu-group <?php echo $groupIsActive ? 'active' : ''; ?>" <?php echo $groupIsActive ? 'open' : ''; ?>>
                        <summary><?php echo h($menuGroup['title']); ?></summary>
                        <div class="menu-group-items">
                            <?php foreach ($visibleItems as $menuItem): ?>
                                <?php $itemIsActive = adminMenuIsActive($menuItem, $adminCurrentPage); ?>
                                <a href="<?php echo h($menuItem['url']); ?>" class="menu-item <?php echo $itemIsActive ? 'active' : ''; ?>">
                                    <span><?php echo h($menuItem['icon']); ?></span> <?php echo h($menuItem['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
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
