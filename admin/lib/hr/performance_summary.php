<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/checklists.php';
require_once __DIR__ . '/kpis.php';
require_once __DIR__ . '/planner/planner_service.php';
require_once __DIR__ . '/okr.php';
require_once __DIR__ . '/tests.php';

function hrPerformanceSummaryEnsure(PDO $db): void {
    hrEnsureCoreSchema($db);
    hrChecklistEnsureSchema($db);
    hrKpiEnsureSchema($db);
    plannerEnsureSchema($db);
    okrEnsureSchema($db);
    hrOrgTestsEnsureSchema($db);
    hrPerformanceSummaryEnsureDefaultWeights($db, 0);
}

function hrPerformanceSummaryEnsureDefaultWeights(PDO $db, int $actorId): array {
    $defaults = ['kpi' => 40, 'checklist' => 25, 'planner' => 20, 'tests' => 15];
    try {
        $stmt = $db->prepare('SELECT setting_value_json FROM hr_module_settings WHERE module_key=? AND setting_key=? LIMIT 1');
        $stmt->execute(['performance_summary', 'score_weights']);
        $raw = (string)($stmt->fetchColumn() ?: '');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $weights = [];
            foreach ($defaults as $key => $default) {
                $weights[$key] = max(0.0, (float)($decoded[$key] ?? $default));
            }
            return $weights;
        }
        $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json),updated_by=VALUES(updated_by),updated_at=NOW()')
            ->execute(['performance_summary', 'score_weights', json_encode($defaults, JSON_UNESCAPED_UNICODE), $actorId ?: null]);
    } catch (Throwable $e) {
        safeAdminLog('Performance summary weights failed: ' . $e->getMessage());
    }
    return $defaults;
}

function hrPerformanceSummarySaveWeights(PDO $db, array $admin, array $input): void {
    requireValidCsrf();
    $weights = [
        'kpi' => max(0.0, (float)($input['kpi_weight'] ?? 40)),
        'checklist' => max(0.0, (float)($input['checklist_weight'] ?? 25)),
        'planner' => max(0.0, (float)($input['planner_weight'] ?? 20)),
        'tests' => max(0.0, (float)($input['test_weight'] ?? 15)),
    ];
    if (array_sum($weights) <= 0) {
        throw new RuntimeException('جمع وزن‌ها باید بیشتر از صفر باشد.');
    }
    $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json),updated_by=VALUES(updated_by),updated_at=NOW()')
        ->execute(['performance_summary', 'score_weights', json_encode($weights, JSON_UNESCAPED_UNICODE), (int)$admin['id']]);
}

function hrPerformanceSummaryDateRange(array $period = [], string $month = ''): array {
    if (!empty($period['starts_at']) || !empty($period['start_date'])) {
        $start = substr((string)($period['starts_at'] ?? $period['start_date']), 0, 10);
        $end = substr((string)($period['ends_at'] ?? $period['end_date'] ?? ''), 0, 10);
        return [$start, $end ?: date('Y-m-t', strtotime($start ?: 'now'))];
    }
    $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
    return [$month . '-01', date('Y-m-t', strtotime($month . '-01'))];
}

function hrPerformanceSummaryAvg(PDO $db, string $sql, array $params): ?float {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (float)$value;
    } catch (Throwable $e) {
        safeAdminLog('Performance summary metric failed: ' . $e->getMessage());
        return null;
    }
}

function hrPerformanceSummaryCount(PDO $db, string $sql, array $params): int {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        safeAdminLog('Performance summary count failed: ' . $e->getMessage());
        return 0;
    }
}

function hrPerformanceSummaryBuild(PDO $db, array $employee, array $context, array $weights): array {
    $employeeId = (int)$employee['id'];
    [$start, $end] = $context['range'];
    $periodId = (int)($context['period_id'] ?? 0);
    $month = (string)$context['month'];

    $testScore = hrPerformanceSummaryAvg($db, 'SELECT AVG(overall_score) FROM hr_test_results WHERE employee_id=? AND status="final" AND deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $checklistCompletion = hrPerformanceSummaryAvg($db, 'SELECT AVG(completion_percent) FROM hr_checklist_submissions WHERE employee_id=? AND checklist_date BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $checklistQuality = hrPerformanceSummaryAvg($db, 'SELECT AVG(total_quality_score) FROM hr_checklist_submissions WHERE employee_id=? AND checklist_date BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $checklistScore = $checklistCompletion === null && $checklistQuality === null ? null : (($checklistCompletion ?? 0) * 0.6 + ($checklistQuality ?? $checklistCompletion ?? 0) * 0.4);
    $kpiScore = $periodId > 0
        ? hrPerformanceSummaryAvg($db, 'SELECT SUM(weighted_score) FROM hr_kpi_scores WHERE employee_id=? AND (period_id=? OR period_id IS NULL)', [$employeeId, $periodId])
        : hrPerformanceSummaryAvg($db, 'SELECT SUM(weighted_score) FROM hr_kpi_scores WHERE employee_id=? AND DATE(calculated_at) BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $kpiScore = $kpiScore === null ? null : min(100.0, $kpiScore);

    $taskCount = hrPerformanceSummaryCount($db, 'SELECT COUNT(*) FROM planner_tasks WHERE owner_user_id=? AND task_date BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $doneCount = hrPerformanceSummaryCount($db, 'SELECT COUNT(*) FROM planner_tasks WHERE owner_user_id=? AND task_date BETWEEN ? AND ? AND status="done"', [$employeeId, $start, $end]);
    $plannerProgress = hrPerformanceSummaryAvg($db, 'SELECT AVG(progress_percent) FROM planner_tasks WHERE owner_user_id=? AND task_date BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $plannerScore = $taskCount > 0 ? (($doneCount / max(1, $taskCount)) * 70 + ($plannerProgress ?? 0) * 0.3) : null;

    $okrParticipation = hrPerformanceSummaryAvg($db, 'SELECT AVG(final_progress_percent) FROM okr_actions WHERE owner_user_id=? AND (due_date IS NULL OR due_date BETWEEN ? AND ?)', [$employeeId, $start, $end]);
    $salesScore = hrPerformanceSummaryAvg($db, 'SELECT AVG(s.score_percent) FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id WHERE s.employee_id=? AND (k.standard_group IN ("sales_script","fab","objections","listening") OR k.kpi_key IN ("script_compliance_score","open_question_usage","listening_score","fab_usage_score","clear_cta_usage","objection_handling_score") OR k.kpi_code IN ("script_compliance_score","open_question_usage","listening_score","fab_usage_score","clear_cta_usage","objection_handling_score")) AND DATE(s.calculated_at) BETWEEN ? AND ?', [$employeeId, $start, $end]);
    $customerScore = hrPerformanceSummaryAvg($db, 'SELECT AVG(s.score_percent) FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id WHERE s.employee_id=? AND (k.standard_group IN ("customer_journey","after_sales_support","referral","customer_types") OR k.kpi_key IN ("customer_complaint_count","complaint_resolution_time","complaint_resolution_rate","repeat_purchase_after_complaint","customer_referral_count") OR k.kpi_code IN ("customer_complaint_count","complaint_resolution_time","complaint_resolution_rate","repeat_purchase_after_complaint","customer_referral_count")) AND DATE(s.calculated_at) BETWEEN ? AND ?', [$employeeId, $start, $end]);

    $components = [
        'kpi' => $kpiScore,
        'checklist' => $checklistScore,
        'planner' => $plannerScore,
        'tests' => $testScore,
    ];
    $weighted = 0.0;
    $weightSum = 0.0;
    foreach ($components as $key => $score) {
        if ($score === null) {
            continue;
        }
        $w = max(0.0, (float)($weights[$key] ?? 0));
        $weighted += max(0.0, min(100.0, (float)$score)) * $w;
        $weightSum += $w;
    }
    $finalScore = $weightSum > 0 ? round($weighted / $weightSum, 2) : 0.0;
    $previous = hrPerformanceSummaryAvg($db, 'SELECT final_score FROM employee_score_history WHERE employee_id=? AND period_month < ? ORDER BY period_month DESC LIMIT 1', [$employeeId, $month]);
    $trend = $previous === null ? 'new' : ($finalScore > $previous ? 'up' : ($finalScore < $previous ? 'down' : 'flat'));
    $overdue = hrPerformanceSummaryCount($db, 'SELECT COUNT(*) FROM planner_tasks WHERE owner_user_id=? AND status NOT IN ("done","cancelled") AND task_date < CURDATE()', [$employeeId]);
    $warnings = [];
    if ($finalScore < 70) $warnings[] = 'امتیاز نهایی پایین‌تر از ۷۰ است.';
    if (($kpiScore ?? 100) < 70) $warnings[] = 'KPI نیازمند اقدام اصلاحی است.';
    if (($checklistScore ?? 100) < 70) $warnings[] = 'چک‌لیست یا کیفیت اجرای وظایف پایین است.';
    if ($overdue > 0) $warnings[] = $overdue . ' تسک عقب‌افتاده دارد.';

    $notes = '';
    try {
        $stmt = $db->prepare('SELECT evaluation_notes FROM employee_performance WHERE admin_id=? AND period_month=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$employeeId, $month]);
        $notes = (string)($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        safeAdminLog('Performance summary manager notes lookup failed: ' . $e->getMessage());
    }

    return [
        'employee' => $employee,
        'test_score' => $testScore,
        'checklist_completion' => $checklistCompletion,
        'checklist_quality' => $checklistQuality,
        'checklist_score' => $checklistScore,
        'kpi_score' => $kpiScore,
        'planner_score' => $plannerScore,
        'planner_done' => $doneCount,
        'planner_total' => $taskCount,
        'okr_participation' => $okrParticipation,
        'sales_score' => $salesScore,
        'customer_score' => $customerScore,
        'final_score' => $finalScore,
        'trend' => $trend,
        'warnings' => $warnings,
        'manager_notes' => $notes,
    ];
}

function hrPerformanceSummaryFetchEmployees(PDO $db, array $admin, array $filters): array {
    $where = ['is_active=1'];
    $params = [];
    if ((string)($admin['role'] ?? '') === 'manager') {
        $where[] = 'department=?';
        $params[] = (string)($admin['department'] ?? '');
    }
    if (!empty($filters['department'])) {
        $where[] = 'department=?';
        $params[] = (string)$filters['department'];
    }
    if (!empty($filters['role'])) {
        $where[] = 'role=?';
        $params[] = (string)$filters['role'];
    }
    if (!empty($filters['employee_id'])) {
        $where[] = 'id=?';
        $params[] = (int)$filters['employee_id'];
    }
    $stmt = $db->prepare('SELECT id,username,full_name,role,department FROM admins WHERE ' . implode(' AND ', $where) . ' ORDER BY department, full_name, username LIMIT 500');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function hrPerformanceSummaryRenderPage(): void {
    $admin = adminGuard('manager');
    if (!adminPermissionAllows($admin, 'hr_performance_summary_view', ['manager','admin','super_admin']) && !adminPermissionAllows($admin, 'employee_performance', ['manager','admin','super_admin'])) {
        http_response_code(403);
        adminRenderSafeError('خلاصه نهایی عملکرد پرسنل', 'Performance summary permission denied');
        return;
    }
    $db = adminDb();
    hrPerformanceSummaryEnsure($db);
    $pageTitle = 'خلاصه نهایی عملکرد پرسنل';
    $message = '';
    $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['summary_action'] ?? '') === 'save_weights') {
            if (!adminPermissionAllows($admin, 'hr_performance_summary_manage', ['admin','super_admin']) && !adminPermissionAllows($admin, 'hr_platform_manage', ['admin','super_admin'])) {
                throw new RuntimeException('مجوز تغییر وزن‌های خلاصه عملکرد برای حساب شما فعال نیست.');
            }
            hrPerformanceSummarySaveWeights($db, $admin, $_POST);
            redirectTo('hr-performance-summary.php?weights_saved=1');
        }
    } catch (Throwable $e) {
        safeAdminLog('Performance summary settings save failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ذخیره تنظیمات انجام نشد.';
    }
    if (isset($_GET['weights_saved'])) $message = 'وزن‌های امتیاز نهایی ذخیره شد.';
    $weights = hrPerformanceSummaryEnsureDefaultWeights($db, (int)$admin['id']);
    $periods = okrFetchAll($db, 'SELECT * FROM hr_periods ORDER BY starts_at DESC,id DESC LIMIT 80');
    $periodId = (int)($_GET['period_id'] ?? 0);
    $period = [];
    foreach ($periods as $p) {
        if ((int)$p['id'] === $periodId) { $period = $p; break; }
    }
    $month = trim((string)($_GET['target_month'] ?? '')) ?: date('Y-m');
    $range = hrPerformanceSummaryDateRange($period, $month);
    $filters = [
        'department' => trim((string)($_GET['department'] ?? '')),
        'role' => trim((string)($_GET['role'] ?? '')),
        'employee_id' => (int)($_GET['employee_id'] ?? 0),
    ];
    $employees = hrPerformanceSummaryFetchEmployees($db, $admin, $filters);
    $summaries = array_map(static fn($employee) => hrPerformanceSummaryBuild($db, $employee, ['period_id' => $periodId, 'month' => $month, 'range' => $range], $weights), $employees);
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hr-performance-summary-' . $month . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['employee','role','department','test','checklist_completion','checklist_quality','kpi','planner','okr','sales','customer','final','trend','warnings']);
        foreach ($summaries as $s) {
            $e = $s['employee'];
            fputcsv($out, [hrEmployeeDisplayName($e), $e['role'], $e['department'], $s['test_score'], $s['checklist_completion'], $s['checklist_quality'], $s['kpi_score'], $s['planner_score'], $s['okr_participation'], $s['sales_score'], $s['customer_score'], $s['final_score'], $s['trend'], implode(' | ', $s['warnings'])]);
        }
        exit;
    }
    $departments = okrFetchAll($db, "SELECT DISTINCT department FROM admins WHERE is_active=1 AND department IS NOT NULL AND department<>'' ORDER BY department");
    include __DIR__ . '/../../includes/header.php';
    okrAlert($message);
    okrAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>فیلتر و خروجی</h2><a class="btn btn-sm" href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>">CSV</a></div><div class="card-body"><form method="get" class="admin-filter"><input class="form-control" name="target_month" value="<?php echo h($month); ?>" placeholder="YYYY-MM"><select class="form-control" name="period_id"><option value="">دوره جاری/ماه</option><?php foreach ($periods as $p): ?><option value="<?php echo h($p['id']); ?>" <?php echo $periodId===(int)$p['id']?'selected':''; ?>><?php echo h($p['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="department"><option value="">همه دپارتمان‌ها</option><?php foreach ($departments as $d): ?><option value="<?php echo h($d['department']); ?>" <?php echo $filters['department']===(string)$d['department']?'selected':''; ?>><?php echo h($d['department']); ?></option><?php endforeach; ?></select><input class="form-control" name="role" value="<?php echo h($filters['role']); ?>" placeholder="نقش"><input class="form-control" type="number" name="employee_id" value="<?php echo h((string)$filters['employee_id']); ?>" placeholder="شناسه پرسنل"><button class="btn btn-primary">نمایش</button></form></div></div>
    <?php if (adminPermissionAllows($admin, 'hr_performance_summary_manage', ['admin','super_admin']) || adminPermissionAllows($admin, 'hr_platform_manage', ['admin','super_admin'])): ?><div class="card"><div class="card-header"><h2>وزن امتیاز نهایی</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="summary_action" value="save_weights"><input class="form-control" type="number" step="0.01" name="kpi_weight" value="<?php echo h((string)$weights['kpi']); ?>" placeholder="KPI"><input class="form-control" type="number" step="0.01" name="checklist_weight" value="<?php echo h((string)$weights['checklist']); ?>" placeholder="Checklist"><input class="form-control" type="number" step="0.01" name="planner_weight" value="<?php echo h((string)$weights['planner']); ?>" placeholder="Planner"><input class="form-control" type="number" step="0.01" name="test_weight" value="<?php echo h((string)$weights['tests']); ?>" placeholder="Tests"><button class="btn btn-success">ذخیره وزن‌ها</button></form></div></div><?php endif; ?>
    <div class="card"><div class="card-header"><h2>خلاصه نهایی عملکرد</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>پرسنل</th><th>نقش/دپارتمان</th><th>آزمون</th><th>چک‌لیست</th><th>KPI</th><th>پلنر</th><th>OKR</th><th>فروش/مشتری</th><th>نهایی</th><th>روند</th><th>هشدار</th><th>یادداشت مدیر</th></tr></thead><tbody><?php foreach ($summaries as $s): $e=$s['employee']; ?><tr><td><?php echo h(hrEmployeeDisplayName($e)); ?><br><small><?php echo h((string)$e['id']); ?></small></td><td><?php echo h(($e['role'] ?: '-') . ' / ' . ($e['department'] ?: '-')); ?></td><td><?php echo h($s['test_score'] === null ? '-' : number_format($s['test_score'],1) . '%'); ?></td><td><?php echo h(($s['checklist_completion'] === null ? '-' : number_format($s['checklist_completion'],1) . '%') . ' / Q:' . ($s['checklist_quality'] === null ? '-' : number_format($s['checklist_quality'],1))); ?></td><td><?php echo h($s['kpi_score'] === null ? '-' : number_format($s['kpi_score'],1) . '%'); ?></td><td><?php echo h($s['planner_score'] === null ? '-' : number_format($s['planner_score'],1) . '%'); ?><br><small><?php echo h($s['planner_done'] . '/' . $s['planner_total']); ?></small></td><td><?php echo h($s['okr_participation'] === null ? '-' : number_format($s['okr_participation'],1) . '%'); ?></td><td><?php echo h('Sales: ' . ($s['sales_score'] === null ? '-' : number_format($s['sales_score'],1)) . ' / Customer: ' . ($s['customer_score'] === null ? '-' : number_format($s['customer_score'],1))); ?></td><td><strong><?php echo h(number_format($s['final_score'],1)); ?>%</strong></td><td><?php echo h($s['trend']); ?></td><td><?php echo h($s['warnings'] ? implode(' | ', $s['warnings']) : '-'); ?></td><td><?php echo h($s['manager_notes'] ?: '-'); ?></td></tr><?php endforeach; ?><?php if (!$summaries): ?><tr><td colspan="12" class="text-center text-muted">داده‌ای برای این فیلتر وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php include __DIR__ . '/../../includes/footer.php';
}
