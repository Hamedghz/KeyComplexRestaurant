<?php

require_once __DIR__ . '/okr.php';

function okrLayoutStart(string $title, string $role = 'manager'): array {
    [$db, $admin, $pageTitle] = okrStart($title, $role);
    return [$db, $admin, $pageTitle];
}

function okrSelectUsers(array $users, string $name, $selected = null): void {
    echo '<select class="form-control" name="' . h($name) . '"><option value="">انتخاب کاربر</option>';
    foreach ($users as $user) {
        $label = trim((string)($user['full_name'] ?: $user['username']) . ' / ' . (string)($user['department'] ?? ''));
        echo '<option value="' . h($user['id']) . '"' . ((int)$selected === (int)$user['id'] ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    echo '</select>';
}

function okrObjectiveOptions(PDO $db): array {
    return okrFetchAll($db, 'SELECT * FROM okr_objectives WHERE status!="archived" ORDER BY target_month DESC,id DESC');
}

function okrKrOptions(PDO $db, int $objectiveId = 0): array {
    $sql = 'SELECT kr.*, o.title AS objective_title FROM okr_key_results kr JOIN okr_objectives o ON o.id=kr.objective_id WHERE kr.status!="archived"';
    $params = [];
    if ($objectiveId > 0) { $sql .= ' AND kr.objective_id=?'; $params[] = $objectiveId; }
    return okrFetchAll($db, $sql . ' ORDER BY o.target_month DESC, kr.id DESC', $params);
}

function okrRenderObjectivesPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('اهداف ماهانه OKR', 'manager');
    $message = ''; $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'save_objective') {
                okrSaveObjective($db, $admin, $_POST);
                redirectTo('okr-objectives.php?saved=1');
            }
            if ($action === 'seed_examples') {
                requireValidCsrf();
                $count = okrSeedExamples($db, $admin);
                redirectTo('okr-objectives.php?seeded=' . urlencode((string)$count));
            }
        }
    } catch (Throwable $e) {
        safeAdminLog('OKR objective page failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ثبت هدف انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    }
    if (isset($_GET['saved'])) $message = 'هدف ذخیره شد.';
    if (isset($_GET['seeded'])) $message = 'نمونه‌های OKR آماده شد.';
    $users = okrUsers($db);
    $periods = function_exists('hrFetchPeriods') ? hrFetchPeriods($db, false) : [];
    $objectives = okrObjectiveOptions($db);
    include dirname(__DIR__) . '/../includes/header.php';
    okrAlert($message); okrAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>هدف ماهانه جدید</h2><form method="post"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="seed_examples"><button class="btn btn-sm btn-success">نمونه‌های Business Coaching</button></form></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_objective"><input class="form-control" name="title" placeholder="عنوان هدف" required><input class="form-control" name="target_month" value="<?php echo h(date('Y-m')); ?>" placeholder="YYYY-MM"><select class="form-control" name="period_id"><option value="">دوره</option><?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>"><?php echo h($period['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="scope_type"><option value="company">کل شرکت / رستوران</option><option value="department">دپارتمان</option><option value="team">تیم</option></select><input class="form-control" name="scope_id" placeholder="شناسه محدوده"><div class="form-group"><label>مالک</label><?php okrSelectUsers($users, 'owner_user_id', $admin['id']); ?></div><div class="form-group"><label>TMO</label><?php okrSelectUsers($users, 'tmo_user_id', $admin['id']); ?></div><select class="form-control" name="status"><option value="draft">پیش‌نویس</option><option value="active">فعال</option><option value="reviewed">بازبینی‌شده</option><option value="closed">بسته</option></select><input class="form-control" type="number" min="0" max="100" step="0.01" name="manual_progress_percent" placeholder="پیشرفت دستی"><textarea class="form-control" name="description" placeholder="توضیح" style="grid-column:1/-1"></textarea><button class="btn btn-primary">ذخیره هدف</button></form></div></div>
    <div class="card"><div class="card-header"><h2>اهداف فعال</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>هدف</th><th>ماه</th><th>محدوده</th><th>مالک</th><th>TMO</th><th>پیشرفت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($objectives as $o): ?><tr><td><?php echo h($o['title']); ?></td><td><?php echo h($o['target_month']); ?></td><td><?php echo h($o['scope_type'] . ' ' . (string)$o['scope_id']); ?></td><td><?php echo h((string)$o['owner_user_id']); ?></td><td><?php echo h((string)$o['tmo_user_id']); ?></td><td><?php echo h(number_format((float)$o['final_progress_percent'], 1)); ?>%</td><td><?php echo h($o['status']); ?></td><td><a class="btn btn-sm" href="okr-key-results.php?objective_id=<?php echo h($o['id']); ?>">KR</a> <a class="btn btn-sm" href="okr-actions.php?objective_id=<?php echo h($o['id']); ?>">اقدام</a> <a class="btn btn-sm" href="tmo-review.php?objective_id=<?php echo h($o['id']); ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function okrRenderKeyResultsPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('Key Results', 'manager');
    $message = ''; $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_kr') {
            okrSaveKr($db, $admin, $_POST);
            redirectTo('okr-key-results.php?saved=1&objective_id=' . urlencode((string)$_POST['objective_id']));
        }
    } catch (Throwable $e) {
        safeAdminLog('OKR KR page failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ثبت KR انجام نشد.';
    }
    if (isset($_GET['saved'])) $message = 'KR ذخیره شد.';
    $objectiveId = (int)($_GET['objective_id'] ?? 0);
    $objectives = okrObjectiveOptions($db);
    $krs = okrKrOptions($db, $objectiveId);
    include dirname(__DIR__) . '/../includes/header.php';
    okrAlert($message); okrAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>KR جدید</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_kr"><select class="form-control" name="objective_id" required><?php foreach ($objectives as $o): ?><option value="<?php echo h($o['id']); ?>" <?php echo $objectiveId===(int)$o['id']?'selected':''; ?>><?php echo h($o['title']); ?></option><?php endforeach; ?></select><input class="form-control" name="title" placeholder="عنوان KR" required><select class="form-control" name="kr_type"><option value="numeric">عددی</option><option value="descriptive">توصیفی</option></select><input class="form-control" type="number" step="0.01" name="target_value" placeholder="هدف"><input class="form-control" type="number" step="0.01" name="current_value" placeholder="مقدار فعلی"><input class="form-control" name="unit_label" placeholder="واحد"><input class="form-control" type="number" step="0.01" name="weight" value="1" placeholder="وزن"><input class="form-control" type="number" step="0.01" name="manual_progress_percent" placeholder="پیشرفت دستی"><textarea class="form-control" name="description" placeholder="توضیح" style="grid-column:1/-1"></textarea><button class="btn btn-primary">ذخیره KR</button></form></div></div>
    <div class="card"><div class="card-header"><h2>KRها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>هدف</th><th>KR</th><th>نوع</th><th>مقدار</th><th>وزن</th><th>پیشرفت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($krs as $kr): ?><tr><td><?php echo h($kr['objective_title']); ?></td><td><?php echo h($kr['title']); ?></td><td><?php echo h($kr['kr_type']); ?></td><td><?php echo h((string)$kr['current_value'] . ' / ' . (string)$kr['target_value'] . ' ' . (string)$kr['unit_label']); ?></td><td><?php echo h((string)$kr['weight']); ?></td><td><?php echo h(number_format((float)$kr['final_progress_percent'], 1)); ?>%</td><td><a class="btn btn-sm" href="okr-actions.php?objective_id=<?php echo h($kr['objective_id']); ?>&kr_id=<?php echo h($kr['id']); ?>">اقدام</a></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function okrRenderActionsPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('OKR Actions', 'manager');
    $message = ''; $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'save_action') {
                okrSaveAction($db, $admin, $_POST);
                redirectTo('okr-actions.php?saved=1&objective_id=' . urlencode((string)$_POST['objective_id']));
            }
            if ($action === 'create_task') {
                requireValidCsrf();
                okrCreatePlannerTaskForAction($db, $admin, (int)$_POST['action_id']);
                redirectTo('okr-actions.php?task_created=1');
            }
        }
    } catch (Throwable $e) {
        safeAdminLog('OKR action page failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ثبت اقدام انجام نشد.';
    }
    if (isset($_GET['saved'])) $message = 'اقدام ذخیره شد.';
    if (isset($_GET['task_created'])) $message = 'تسک پلنر برای اقدام ساخته شد.';
    $objectiveId = (int)($_GET['objective_id'] ?? 0); $krId = (int)($_GET['kr_id'] ?? 0);
    $objectives = okrObjectiveOptions($db); $krs = okrKrOptions($db, $objectiveId); $users = okrUsers($db);
    $actions = okrFetchAll($db, 'SELECT a.*, o.title AS objective_title, kr.title AS kr_title FROM okr_actions a JOIN okr_objectives o ON o.id=a.objective_id LEFT JOIN okr_key_results kr ON kr.id=a.kr_id ORDER BY a.due_date IS NULL,a.due_date ASC,a.id DESC LIMIT 300');
    include dirname(__DIR__) . '/../includes/header.php';
    okrAlert($message); okrAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>اقدام جدید</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_action"><select class="form-control" name="objective_id" required><?php foreach ($objectives as $o): ?><option value="<?php echo h($o['id']); ?>" <?php echo $objectiveId===(int)$o['id']?'selected':''; ?>><?php echo h($o['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="kr_id"><option value="">بدون KR</option><?php foreach ($krs as $kr): ?><option value="<?php echo h($kr['id']); ?>" <?php echo $krId===(int)$kr['id']?'selected':''; ?>><?php echo h($kr['title']); ?></option><?php endforeach; ?></select><input class="form-control" name="title" placeholder="عنوان اقدام" required><div class="form-group"><label>مالک</label><?php okrSelectUsers($users, 'owner_user_id', $admin['id']); ?></div><input class="form-control" name="department" placeholder="دپارتمان"><input class="form-control" name="due_date" placeholder="موعد YYYY-MM-DD"><select class="form-control" name="priority"><option value="normal">عادی</option><option value="high">بالا</option><option value="urgent">فوری</option><option value="low">کم</option></select><select class="form-control" name="status"><option value="pending">در انتظار</option><option value="in_progress">در حال انجام</option><option value="done">انجام شد</option><option value="cancelled">لغو</option></select><input class="form-control" type="number" step="0.01" name="manual_progress_percent" placeholder="پیشرفت دستی"><label><input type="checkbox" name="create_planner_task" value="1"> ساخت تسک پلنر</label><textarea class="form-control" name="description" placeholder="توضیح" style="grid-column:1/-1"></textarea><button class="btn btn-primary">ذخیره اقدام</button></form></div></div>
    <div class="card"><div class="card-header"><h2>اقدام‌ها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>هدف</th><th>KR</th><th>اقدام</th><th>مالک</th><th>موعد</th><th>پلنر</th><th>پیشرفت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($actions as $a): ?><tr><td><?php echo h($a['objective_title']); ?></td><td><?php echo h($a['kr_title'] ?: '-'); ?></td><td><?php echo h($a['title']); ?></td><td><?php echo h((string)$a['owner_user_id']); ?></td><td><?php echo h($a['due_date'] ?: '-'); ?></td><td><?php echo h((string)($a['planner_task_id'] ?: '-')); ?></td><td><?php echo h(number_format((float)$a['final_progress_percent'], 1)); ?>%</td><td><?php if (!$a['planner_task_id']): ?><form method="post"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="create_task"><input type="hidden" name="action_id" value="<?php echo h($a['id']); ?>"><button class="btn btn-sm btn-success">تبدیل به تسک</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function okrRenderProgressPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('پیشرفت OKR', 'manager');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? 'recalculate');
        if ($action === 'link_kpi') {
            okrLinkKpi($db, $admin, $_POST);
            redirectTo('okr-progress.php?kpi_linked=1');
        }
        requireValidCsrf();
        foreach (okrObjectiveOptions($db) as $o) okrRecalculateObjective($db, (int)$o['id'], (int)$admin['id'], 'system');
        redirectTo('okr-progress.php?recalculated=1');
    }
    okrAlert(isset($_GET['recalculated']) ? 'پیشرفت‌ها محاسبه شد.' : '');
    okrAlert(isset($_GET['kpi_linked']) ? 'اتصال KPI ثبت شد.' : '');
    $objectives = okrObjectiveOptions($db);
    $krs = okrKrOptions($db);
    $kpis = okrFetchAll($db, 'SELECT id,title,code,kpi_key,kpi_code FROM hr_kpi_definitions WHERE status!="archived" ORDER BY title LIMIT 300');
    $assignments = okrFetchAll($db, 'SELECT a.id, k.title FROM hr_kpi_assignments a JOIN hr_kpi_definitions k ON k.id=a.kpi_id WHERE a.status="active" ORDER BY a.id DESC LIMIT 300');
    $logs = okrFetchAll($db, 'SELECT * FROM okr_progress_logs ORDER BY id DESC LIMIT 100');
    include dirname(__DIR__) . '/../includes/header.php';
    ?>
    <div class="card"><div class="card-header"><h2>اتصال KPI</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="link_kpi"><select class="form-control" name="objective_id"><option value="">هدف</option><?php foreach ($objectives as $o): ?><option value="<?php echo h($o['id']); ?>"><?php echo h($o['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="kr_id"><option value="">KR</option><?php foreach ($krs as $kr): ?><option value="<?php echo h($kr['id']); ?>"><?php echo h($kr['objective_title'] . ' / ' . $kr['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="kpi_definition_id"><option value="">KPI تعریف‌شده</option><?php foreach ($kpis as $kpi): ?><option value="<?php echo h($kpi['id']); ?>"><?php echo h($kpi['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="kpi_assignment_id"><option value="">KPI تخصیص</option><?php foreach ($assignments as $assignment): ?><option value="<?php echo h($assignment['id']); ?>"><?php echo h($assignment['title'] . ' #' . $assignment['id']); ?></option><?php endforeach; ?></select><input class="form-control" type="number" step="0.01" name="weight" value="1"><button class="btn btn-success">ثبت اتصال</button></form></div></div>
    <div class="card"><div class="card-header"><h2>محاسبه پیشرفت</h2><form method="post"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><button class="btn btn-sm btn-primary">محاسبه مجدد</button></form></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>هدف</th><th>محاسبه‌شده</th><th>دستی</th><th>نهایی</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($objectives as $o): ?><tr><td><?php echo h($o['title']); ?></td><td><?php echo h(number_format((float)$o['calculated_progress_percent'],1)); ?>%</td><td><?php echo h((string)($o['manual_progress_percent'] ?? '-')); ?></td><td><?php echo h(number_format((float)$o['final_progress_percent'],1)); ?>%</td><td><?php echo h($o['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="card"><div class="card-header"><h2>لاگ پیشرفت</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>موجودیت</th><th>منبع</th><th>قدیم</th><th>جدید</th><th>یادداشت</th><th>زمان</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?php echo h($log['entity_type'] . '#' . $log['entity_id']); ?></td><td><?php echo h($log['source']); ?></td><td><?php echo h((string)$log['old_progress_percent']); ?></td><td><?php echo h((string)$log['new_progress_percent']); ?></td><td><?php echo h((string)$log['note']); ?></td><td><?php echo h($log['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function okrRenderTmoReviewPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('مرور مدیریتی TMO', 'manager');
    $message = ''; $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            okrSaveTmoReview($db, $admin, $_POST);
            redirectTo('tmo-review.php?saved=1');
        }
    } catch (Throwable $e) {
        safeAdminLog('TMO review failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ثبت مرور انجام نشد.';
    }
    if (isset($_GET['saved'])) $message = 'مرور TMO ثبت شد.';
    $objectiveId = (int)($_GET['objective_id'] ?? 0);
    $objectives = okrObjectiveOptions($db); $users = okrUsers($db);
    $reviews = okrFetchAll($db, 'SELECT r.*, o.title AS objective_title FROM tmo_reviews r JOIN okr_objectives o ON o.id=r.objective_id ORDER BY r.id DESC LIMIT 100');
    include dirname(__DIR__) . '/../includes/header.php';
    okrAlert($message); okrAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>ثبت مرور TMO</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><select class="form-control" name="objective_id" required><?php foreach ($objectives as $o): ?><option value="<?php echo h($o['id']); ?>" <?php echo $objectiveId===(int)$o['id']?'selected':''; ?>><?php echo h($o['title']); ?></option><?php endforeach; ?></select><div class="form-group"><label>TMO</label><?php okrSelectUsers($users, 'tmo_user_id', $admin['id']); ?></div><input class="form-control" name="review_date" value="<?php echo h(date('Y-m-d')); ?>"><input class="form-control" type="number" step="0.01" name="final_score" placeholder="امتیاز نهایی"><select class="form-control" name="status"><option value="draft">پیش‌نویس</option><option value="submitted">ارسال شده</option><option value="approved">تایید شده</option><option value="closed">بسته</option></select><textarea class="form-control" name="result_summary" placeholder="خلاصه نتیجه" style="grid-column:1/-1"></textarea><textarea class="form-control" name="blockers" placeholder="موانع" style="grid-column:1/-1"></textarea><textarea class="form-control" name="decisions" placeholder="تصمیم‌ها" style="grid-column:1/-1"></textarea><textarea class="form-control" name="next_actions" placeholder="اقدام‌های بعدی" style="grid-column:1/-1"></textarea><button class="btn btn-primary">ثبت مرور</button></form></div></div>
    <div class="card"><div class="card-header"><h2>مرورهای ثبت شده</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>هدف</th><th>TMO</th><th>تاریخ</th><th>امتیاز</th><th>وضعیت</th><th>خلاصه</th></tr></thead><tbody><?php foreach ($reviews as $r): ?><tr><td><?php echo h($r['objective_title']); ?></td><td><?php echo h((string)$r['tmo_user_id']); ?></td><td><?php echo h($r['review_date']); ?></td><td><?php echo h((string)$r['final_score']); ?></td><td><?php echo h($r['status']); ?></td><td><?php echo h((string)$r['result_summary']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function okrRenderTmoDashboardPage(): void {
    [$db, $admin, $pageTitle] = okrLayoutStart('داشبورد TMO', 'manager');
    $adminId = (int)$admin['id'];
    $active = okrFetchAll($db, 'SELECT * FROM okr_objectives WHERE status IN ("draft","active","reviewed") AND (tmo_user_id=? OR ? IN (SELECT id FROM admins WHERE id=? AND role IN ("admin","super_admin"))) ORDER BY target_month DESC,id DESC', [$adminId, $adminId, $adminId]);
    $delayed = okrFetchAll($db, 'SELECT * FROM okr_actions WHERE status NOT IN ("done","cancelled") AND due_date IS NOT NULL AND due_date < CURDATE() ORDER BY due_date ASC LIMIT 50');
    $lowKrs = okrFetchAll($db, 'SELECT kr.*, o.title AS objective_title FROM okr_key_results kr JOIN okr_objectives o ON o.id=kr.objective_id WHERE kr.final_progress_percent < 70 AND kr.status="active" ORDER BY kr.final_progress_percent ASC LIMIT 50');
    $overdueTasks = okrFetchAll($db, 'SELECT * FROM planner_tasks WHERE source_module="okr" AND status NOT IN ("done","cancelled") AND task_date < CURDATE() ORDER BY task_date ASC LIMIT 50');
    $kpiRisks = okrFetchAll($db, 'SELECT l.*, AVG(s.score_percent) AS score_percent FROM okr_kpi_links l LEFT JOIN hr_kpi_scores s ON s.kpi_id=l.kpi_definition_id OR s.assignment_id=l.kpi_assignment_id WHERE l.status="active" GROUP BY l.id HAVING score_percent < 70 OR score_percent IS NULL LIMIT 50');
    $pending = okrFetchAll($db, 'SELECT o.* FROM okr_objectives o LEFT JOIN tmo_reviews r ON r.objective_id=o.id AND r.status IN ("submitted","approved","closed") WHERE o.status IN ("active","reviewed") AND r.id IS NULL ORDER BY o.id DESC LIMIT 50');
    include dirname(__DIR__) . '/../includes/header.php';
    ?>
    <div class="stats-grid"><div class="stat-card stat-info"><div class="stat-content"><h3><?php echo count($active); ?></h3><p>اهداف فعال</p></div></div><div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo count($delayed); ?></h3><p>اقدام‌های تاخیردار</p></div></div><div class="stat-card stat-danger"><div class="stat-content"><h3><?php echo count($lowKrs); ?></h3><p>KR کم‌پیشرفت</p></div></div><div class="stat-card stat-success"><div class="stat-content"><h3><?php echo count($pending); ?></h3><p>Review در انتظار</p></div></div></div>
    <div class="card"><div class="card-header"><h2>ریسک‌های TMO</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>نوع</th><th>عنوان / شناسه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($delayed as $a): ?><tr><td>Action</td><td><?php echo h($a['title']); ?></td><td><?php echo h($a['due_date']); ?></td><td><a class="btn btn-sm" href="okr-actions.php">مشاهده</a></td></tr><?php endforeach; ?><?php foreach ($lowKrs as $kr): ?><tr><td>KR</td><td><?php echo h($kr['objective_title'] . ' / ' . $kr['title']); ?></td><td><?php echo h(number_format((float)$kr['final_progress_percent'],1)); ?>%</td><td><a class="btn btn-sm" href="okr-key-results.php?objective_id=<?php echo h($kr['objective_id']); ?>">مشاهده</a></td></tr><?php endforeach; ?><?php foreach ($overdueTasks as $task): ?><tr><td>Planner</td><td><?php echo h($task['title']); ?></td><td><?php echo h($task['task_date']); ?></td><td><a class="btn btn-sm" href="planner.php">پلنر</a></td></tr><?php endforeach; ?><?php foreach ($kpiRisks as $risk): ?><tr><td>KPI</td><td><?php echo h('Link #' . $risk['id']); ?></td><td><?php echo h($risk['score_percent'] === null ? 'بدون امتیاز' : number_format((float)$risk['score_percent'],1) . '%'); ?></td><td><a class="btn btn-sm" href="okr-progress.php">پیشرفت</a></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}
