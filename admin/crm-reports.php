<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'گزارش منابع جذب CRM';
$summary = [];
$conversion = [];
try {
    $summary = $db->query("SELECT COALESCE(NULLIF(acquisition_source, ''), 'Unknown') AS source, COUNT(*) AS customer_count, COALESCE(SUM(total_purchase_volume),0) AS sales_total, COALESCE(SUM(total_orders),0) AS order_total, COALESCE(AVG(NULLIF(total_purchase_volume,0)),0) AS avg_purchase FROM crm_customers GROUP BY source ORDER BY customer_count DESC, sales_total DESC")->fetchAll();
    $conversion = $db->query("SELECT COALESCE(NULLIF(acquisition_source, ''), 'Unknown') AS source, COUNT(*) AS leads, SUM(CASE WHEN total_orders > 0 OR total_purchase_volume > 0 THEN 1 ELSE 0 END) AS converted, ROUND((SUM(CASE WHEN total_orders > 0 OR total_purchase_volume > 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS conversion_rate FROM crm_customers GROUP BY source ORDER BY conversion_rate DESC, converted DESC")->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($error)): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row">
<?php foreach (array_slice($summary, 0, 4) as $row): ?>
    <div class="stat-card stat-info"><div class="stat-icon">📣</div><div class="stat-content"><h3><?php echo h($row['customer_count']); ?></h3><p><?php echo h($row['source']); ?> / <?php echo h(formatPrice($row['sales_total'])); ?></p></div></div>
<?php endforeach; ?>
</div>
<div class="card"><div class="card-header"><h2>تعداد مشتری و فروش بر اساس منبع جذب</h2><a class="btn btn-primary" href="acquisition-sources.php">مدیریت منابع</a></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>منبع</th><th>تعداد مشتری</th><th>تعداد خرید</th><th>فروش کل</th><th>میانگین خرید</th></tr></thead><tbody><?php foreach($summary as $row): ?><tr><td><?php echo h($row['source']); ?></td><td><?php echo h($row['customer_count']); ?></td><td><?php echo h($row['order_total']); ?></td><td><?php echo h(formatPrice($row['sales_total'])); ?></td><td><?php echo h(formatPrice($row['avg_purchase'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="card"><div class="card-header"><h2>آمار تبدیل</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>منبع</th><th>مشتریان/Lead</th><th>تبدیل‌شده</th><th>درصد تبدیل</th></tr></thead><tbody><?php foreach($conversion as $row): ?><tr><td><?php echo h($row['source']); ?></td><td><?php echo h($row['leads']); ?></td><td><?php echo h($row['converted']); ?></td><td><?php echo h($row['conversion_rate']); ?>%</td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
