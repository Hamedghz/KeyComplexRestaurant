<?php
require_once __DIR__ . '/../core/models/CrmCustomer.php';
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard();
ensureAdminSchema();
$model = new CrmCustomer();
$customer = $model->find((int)($_GET['id'] ?? 0));
$pageTitle = 'پروفایل مشتری'; include __DIR__.'/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>پروفایل مشتری</h2></div><div class="card-body">
<?php if (!$customer): ?><p>مشتری یافت نشد.</p><?php else: ?>
<h3><?=h($customer['full_name'])?> - <?=h($customer['mobile'])?></h3>
<p>برچسب‌ها: <?=h($customer['tags'])?> | آخرین مراجعه: <?=h(formatJalaliDateTime($customer['last_visit_date'], false))?></p>
<?php $predictionSummary = $model->predictionSummary($customer['mobile']); ?>
<div class="stats-row">
    <div class="stat-card"><div class="stat-content"><h3><?=h($predictionSummary['total_predictions'] ?? 0)?></h3><p>کل پیش‌بینی‌ها</p></div></div>
    <div class="stat-card"><div class="stat-content"><h3><?=h($predictionSummary['winner_count'] ?? 0)?></h3><p>پیش‌بینی برنده</p></div></div>
    <div class="stat-card"><div class="stat-content"><h3><?=h($predictionSummary['reservation_interest_count'] ?? 0)?></h3><p>علاقه‌مند به رزرو</p></div></div>
    <div class="stat-card"><div class="stat-content"><h3><?=h(formatJalaliDateTime($predictionSummary['last_prediction_date'] ?? ''))?></h3><p>آخرین پیش‌بینی</p></div></div>
</div>
<h3>تایم‌لاین</h3><table class="table"><tr><th>نوع</th><th>عنوان</th><th>تاریخ</th></tr><?php foreach($model->timeline($customer['id']) as $t): ?><tr><td><?=h($t['event_type'])?></td><td><?=h($t['title'])?></td><td><?=h(formatJalaliDateTime($t['event_date']))?></td></tr><?php endforeach; ?></table>
<h3>تاریخچه خرید</h3><table class="table"><tr><th>سفارش</th><th>مبلغ</th><th>تاریخ</th></tr><?php foreach($model->purchases($customer['mobile']) as $o): ?><tr><td><?=h($o['order_number'])?></td><td><?=h(number_format((float)$o['total']))?></td><td><?=h(formatJalaliDateTime($o['created_at']))?></td></tr><?php endforeach; ?></table>
<h3>پیش‌بینی‌ها</h3><table class="table"><tr><th>مسابقه</th><th>نتیجه پیش‌بینی</th><th>رزرو</th><th>برنده</th><th>تاریخ</th></tr><?php foreach($model->predictions($customer['mobile']) as $p): ?><tr><td><?=h($p['match_title'] ?: $p['match_id'])?></td><td><?=h(($p['predicted_team_one_score'] ?? $p['predicted_score_team_a'] ?? '') . ' - ' . ($p['predicted_team_two_score'] ?? $p['predicted_score_team_b'] ?? ''))?></td><td><?=((int)($p['wants_reservation'] ?? $p['reserve_table_interest'] ?? 0) === 1) ? '✓' : '✗'?></td><td><?=((int)($p['is_winner'] ?? $p['is_correct_prediction'] ?? 0) === 1) ? '✓' : '✗'?></td><td><?=h(formatJalaliDateTime($p['created_at']))?></td></tr><?php endforeach; ?></table>
<h3>پاسخ‌های نظرسنجی</h3><table class="table"><tr><th>فرم</th><th>امتیاز رضایت</th><th>پیگیری CRM</th><th>تاریخ</th></tr><?php foreach($model->surveyResponses($customer['mobile']) as $s): ?><tr><td><?=h($s['form_title'] ?: $s['form_id'])?></td><td><?=h($s['satisfaction_score'] ?? '')?></td><td><?=((int)($s['crm_follow_up'] ?? 0) === 1) ? '✓' : '✗'?></td><td><?=h(formatJalaliDateTime($s['submitted_at']))?></td></tr><?php endforeach; ?></table>
<?php endif; ?></div></div><?php include __DIR__.'/includes/footer.php'; ?>
