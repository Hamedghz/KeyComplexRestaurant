<?php
/**
 * Admin Dashboard
 */

session_start();

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/models/Order.php';
require_once __DIR__ . '/../core/models/MenuItem.php';

$auth = new Auth();

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentAdmin = $auth->getCurrentAdmin();
$orderModel = new Order();
$menuModel = new MenuItem();

// Get statistics
$todayOrders = $orderModel->getTodayOrders();
$todayStats = $orderModel->getStatistics(date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'));
$recentOrders = $orderModel->getRecent(5);

// Calculate today's revenue
$todayRevenue = 0;
foreach ($todayOrders as $order) {
    if ($order['payment_status'] === 'paid') {
        $todayRevenue += $order['total'];
    }
}

$pageTitle = 'داشبورد';
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-grid">
    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <h3><?php echo count($todayOrders); ?></h3>
                <p>سفارشات امروز</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3><?php echo number_format($todayRevenue, 0); ?> تومان</h3>
                <p>درآمد امروز</p>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <h3><?php echo $orderModel->count(['order_status' => 'pending']); ?></h3>
                <p>سفارشات در انتظار</p>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🍽️</div>
            <div class="stat-content">
                <h3><?php echo $menuModel->count(['is_available' => 1]); ?></h3>
                <p>آیتم‌های فعال منو</p>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h2>آخرین سفارشات</h2>
            <a href="orders.php" class="btn btn-sm">مشاهده همه</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentOrders)): ?>
                <p class="text-muted">هیچ سفارشی ثبت نشده است</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>شماره سفارش</th>
                                <th>مشتری</th>
                                <th>مبلغ</th>
                                <th>وضعیت</th>
                                <th>زمان</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo number_format($order['total'], 0); ?> تومان</td>
                                    <td>
                                        <span class="badge badge-<?php echo getStatusClass($order['order_status']); ?>">
                                            <?php echo getStatusLabel($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">جزئیات</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h2>دسترسی سریع</h2>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="menu-items.php?action=add" class="quick-action-btn">
                    <span class="icon">➕</span>
                    <span>افزودن آیتم منو</span>
                </a>
                <a href="orders.php?status=pending" class="quick-action-btn">
                    <span class="icon">📋</span>
                    <span>سفارشات در انتظار</span>
                </a>
                <a href="feedback.php" class="quick-action-btn">
                    <span class="icon">⭐</span>
                    <span>نظرات مشتریان</span>
                </a>
                <a href="settings.php" class="quick-action-btn">
                    <span class="icon">⚙️</span>
                    <span>تنظیمات</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';

// Helper functions
function getStatusClass($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'preparing' => 'primary',
        'ready' => 'success',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'در انتظار',
        'confirmed' => 'تایید شده',
        'preparing' => 'در حال آماده‌سازی',
        'ready' => 'آماده تحویل',
        'delivered' => 'تحویل داده شده',
        'cancelled' => 'لغو شده'
    ];
    return $labels[$status] ?? $status;
}
?>
