<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';
$currentAdmin = adminGuard('manager');
$pageTitle = 'گزارش مدیریتی آزمون‌ها';
try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'HR test report bootstrap failed: ' . $e->getMessage());
    return;
}
if (!adminPermissionAllows($currentAdmin, 'employee_test_reports', ['manager','admin','super_admin'])) {
    http_response_code(403);
    adminRenderSafeError($pageTitle, 'HR test report permission denied');
    return;
}

$testId = max(0, (int)($_GET['test_id'] ?? 0));
$department = trim((string)($_GET['department'] ?? ''));
$role = trim((string)($_GET['role'] ?? ''));
$from = parsePersianDate($_GET['from'] ?? '', false) ?: '';
$to = parsePersianDate($_GET['to'] ?? '', false) ?: '';
$where = ["r.status='final'", 'r.deleted_at IS NULL'];
$params = [];
$isRestrictedManager = (string)($currentAdmin['role'] ?? '') === 'manager';
if ($isRestrictedManager) {
    $where[] = 'a.department = ?';
    $params[] = (string)($currentAdmin['department'] ?? '');
} elseif ($department !== '') {
    $where[] = 'a.department = ?'; $params[] = $department;
}
if ($testId) { $where[]='r.test_id=?'; $params[]=$testId; }
if ($role !== '') { $where[]='a.role=?'; $params[]=$role; }
if ($from !== '') { $where[]='DATE(r.created_at)>=?'; $params[]=$from; }
if ($to !== '') { $where[]='DATE(r.created_at)<=?'; $params[]=$to; }
$sql = 'SELECT r.*,t.title AS test_title,t.test_code,a.full_name,a.username,a.department,a.role,ta.due_date,ta.show_result_to_employee FROM hr_test_results r JOIN hr_assessment_tests t ON t.id=r.test_id JOIN admins a ON a.id=r.employee_id LEFT JOIN hr_test_assignments ta ON ta.id=r.assignment_id WHERE '.implode(' AND ',$where).' ORDER BY r.created_at DESC,r.id DESC';
$stmt=$db->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
$tests=hrFetchAssessmentTests($db,true);
$departments=$db->query("SELECT DISTINCT department FROM admins WHERE is_active=1 AND department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$summary=[];
foreach ($rows as $row) {
    $key=(string)$row['test_title'].'|'.(string)($row['department'] ?: '-');
    if (!isset($summary[$key])) $summary[$key]=['title'=>$row['test_title'],'department'=>$row['department'] ?: '-','sum'=>0.0,'count'=>0,'completed'=>0];
    $summary[$key]['sum']+=(float)$row['overall_score']; $summary[$key]['count']++; $summary[$key]['completed']++;
}
foreach ($summary as &$item) $item['average']=$item['count'] ? round($item['sum']/$item['count'],2) : 0; unset($item);
$format=(string)($_GET['format'] ?? 'html');
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hr-test-report-'.date('Ymd-His').'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','wb');
    fputcsv($out,['پرسنل','واحد','نقش','آزمون','امتیاز','سطح','پروفایل','تاریخ']);
    foreach ($rows as $row) fputcsv($out,[$row['full_name'] ?: $row['username'],$row['department'],$row['role'],$row['test_title'],$row['overall_score'],$row['result_level'],$row['profile_code'],$row['created_at']]);
    fclose($out); exit;
}
if ($format === 'svg') {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hr-test-report-'.date('Ymd-His').'.svg"');
    $items=array_slice(array_values($summary),0,12); $height=max(180,80+count($items)*42);
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="'.$height.'" viewBox="0 0 1000 '.$height.'"><rect width="100%" height="100%" fill="#fff"/><style>text{font-family:Tahoma,Arial;fill:#202124}.label{font-size:15px}.title{font-size:22px;font-weight:bold}</style><text x="950" y="38" text-anchor="end" class="title">گزارش آزمون‌های پرسنل رستوران کی</text>';
    $y=75; foreach ($items as $item) { $width=max(0,min(700,(float)$item['average']*7)); echo '<text x="960" y="'.($y+17).'" text-anchor="end" class="label">'.h($item['title'].' / '.$item['department'].' / '.$item['average'].'٪').'</text><rect x="50" y="'.$y.'" width="'.$width.'" height="24" rx="6" fill="#0d6efd"/>'; $y+=42; }
    echo '<text x="950" y="'.($height-20).'" text-anchor="end" class="label">'.h(hrAssessmentDisclaimer()).'</text></svg>'; exit;
}
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header"><h2>فیلتر و خروجی</h2><div><a class="btn btn-sm btn-success" href="?<?php echo h(http_build_query(array_merge($_GET,['format'=>'csv']))); ?>">Excel / CSV</a> <a class="btn btn-sm" href="?<?php echo h(http_build_query(array_merge($_GET,['format'=>'svg']))); ?>">تصویر SVG</a> <button class="btn btn-sm btn-primary" onclick="window.print()">چاپ / PDF</button></div></div>
  <div class="card-body"><form method="get" class="admin-filter"><select class="form-control" name="test_id"><option value="">همه آزمون‌ها</option><?php foreach($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo $testId===(int)$test['id']?'selected':''; ?>><?php echo h($test['title']); ?></option><?php endforeach; ?></select><?php if(!$isRestrictedManager): ?><select class="form-control" name="department"><option value="">همه واحدها</option><?php foreach($departments as $item): ?><option <?php echo $department===$item?'selected':''; ?>><?php echo h($item); ?></option><?php endforeach; ?></select><?php endif; ?><input class="form-control" name="role" value="<?php echo h($role); ?>" placeholder="نقش"><input class="form-control" name="from" value="<?php echo h($from); ?>" placeholder="از تاریخ"><input class="form-control" name="to" value="<?php echo h($to); ?>" placeholder="تا تاریخ"><button class="btn btn-primary">اعمال فیلتر</button></form></div>
</div>
<div class="stats-grid"><div class="stat-card stat-info"><div class="stat-content"><h3><?php echo h(count($rows)); ?></h3><p>نتیجه تکمیل‌شده</p></div></div><div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h($rows ? number_format(array_sum(array_map(static fn($r)=>(float)$r['overall_score'],$rows))/count($rows),1) : '0'); ?>٪</h3><p>میانگین کل</p></div></div></div>
<div class="card"><div class="card-header"><h2>میانگین آزمون و واحد</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>آزمون</th><th>واحد</th><th>تعداد</th><th>میانگین</th></tr></thead><tbody><?php foreach($summary as $item): ?><tr><td><?php echo h($item['title']); ?></td><td><?php echo h($item['department']); ?></td><td><?php echo h($item['count']); ?></td><td><?php echo h(number_format($item['average'],1)); ?>٪</td></tr><?php endforeach; ?><?php if(!$summary): ?><tr><td colspan="4" class="text-center text-muted">داده‌ای برای فیلتر انتخاب‌شده وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card"><div class="card-header"><h2>نتایج فردی</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>پرسنل</th><th>واحد / نقش</th><th>آزمون</th><th>امتیاز</th><th>سطح</th><th>پروفایل</th><th>تاریخ</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h(($row['department'] ?: '-').' / '.$row['role']); ?></td><td><?php echo h($row['test_title']); ?></td><td><?php echo h($row['overall_score']); ?>٪</td><td><?php echo h($row['result_level']); ?></td><td><?php echo h($row['profile_code']); ?></td><td><?php echo h($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table><p class="text-muted"><?php echo h(hrAssessmentDisclaimer()); ?></p></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
