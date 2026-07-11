<?php

require_once __DIR__ . '/kpis.php';

if (!function_exists('hrKpiRenderStart')) {
    function hrKpiRenderStart(string $title, string $role = 'manager'): array {
        $currentAdmin = adminGuard($role);
        $db = adminDb();
        hrKpiEnsureSchema($db);
        $pageTitle = $title;
        include __DIR__ . '/../../includes/header.php';
        return [$db, $currentAdmin];
    }
}

if (!function_exists('hrKpiAlert')) {
    function hrKpiAlert(string $message): void {
        if ($message !== '') echo '<div class="alert alert-info">' . h($message) . '</div>';
    }
}

if (!function_exists('hrKpiReportFilterSql')) {
    function hrKpiReportFilterSql(array $input): array {
        $where = [];
        $params = [];
        $department = trim((string)($input['department'] ?? ''));
        $role = trim((string)($input['role'] ?? ''));
        $employeeId = (int)($input['employee_id'] ?? 0);
        $periodId = (int)($input['period_id'] ?? 0);
        if ($department !== '') {
            $where[] = 'k.department = ?';
            $params[] = $department;
        }
        if ($role !== '') {
            $where[] = '(k.role_code = ? OR k.role_key = ?)';
            $params[] = $role;
            $params[] = $role;
        }
        if ($employeeId > 0) {
            $where[] = 's.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($periodId > 0) {
            $where[] = 's.period_id = ?';
            $params[] = $periodId;
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }
}

if (!function_exists('hrKpiRenderDefinitionsPage')) {
    function hrKpiRenderDefinitionsPage(): void {
        [$db, $admin] = hrKpiRenderStart('تعریف KPI', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrKpiHandleDefinitionPost($db, $admin, $_POST) : '';
        $rows = hrKpiFetchAll($db, 'SELECT * FROM hr_kpi_definitions WHERE status!="archived" ORDER BY department, role_code, id DESC LIMIT 300');
        hrKpiAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>KPI جدید</h2></div><div class="card-body">
            <form method="post">
                <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="save_kpi">
                <div class="form-grid">
                    <div class="form-group"><label>کد</label><input class="form-control" name="code" required></div>
                    <div class="form-group"><label>عنوان</label><input class="form-control" name="title" required></div>
                    <div class="form-group"><label>دپارتمان</label><input class="form-control" name="department"></div>
                    <div class="form-group"><label>نقش</label><input class="form-control" name="role_key"></div>
                    <div class="form-group"><label>گروه استاندارد</label><input class="form-control" name="standard_group" placeholder="bsf / sales_script"></div>
                    <div class="form-group"><label>واحد</label><input class="form-control" name="unit_label"></div>
                    <div class="form-group"><label>هدف</label><input class="form-control" type="number" step="0.01" name="target_value"></div>
                    <div class="form-group"><label>وزن</label><input class="form-control" type="number" step="0.01" name="weight" value="10"></div>
                    <div class="form-group"><label>جهت</label><select class="form-control" name="direction"><option value="positive">مثبت</option><option value="negative">منفی</option></select></div>
                    <div class="form-group"><label>محاسبه</label><select class="form-control" name="calculation_type"><option value="simple_percent">درصد ساده</option><option value="manual_score">امتیاز دستی</option><option value="bsf_component">جزء BSF</option></select></div>
                    <div class="form-group"><label>سبز</label><input class="form-control" type="number" step="0.01" name="rag_green_threshold" value="90"></div>
                    <div class="form-group"><label>زرد</label><input class="form-control" type="number" step="0.01" name="rag_yellow_threshold" value="70"></div>
                </div>
                <div class="form-group"><label>توضیح</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                <button class="btn btn-primary">ذخیره KPI</button>
            </form>
        </div></div>
        <div class="card"><div class="card-header"><h2>KPIها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>کد</th><th>عنوان</th><th>دپارتمان</th><th>نقش</th><th>هدف</th><th>وزن</th><th>جهت</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo h((string)($row['code'] ?: $row['kpi_code'] ?: $row['kpi_key'])); ?></td><td><?php echo h((string)$row['title']); ?></td><td><?php echo h((string)($row['department'] ?? '')); ?></td><td><?php echo h((string)($row['role_key'] ?: $row['role_code'])); ?></td><td><?php echo h((string)($row['target_value'] ?? '')); ?></td><td><?php echo h((string)$row['weight']); ?></td><td><?php echo h((string)$row['direction']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrKpiRenderAssignmentsPage')) {
    function hrKpiRenderAssignmentsPage(): void {
        [$db, $admin] = hrKpiRenderStart('تخصیص KPI', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrKpiHandleAssignmentPost($db, $admin, $_POST) : '';
        $kpis = hrKpiFetchAll($db, 'SELECT id,title,department,role_code FROM hr_kpi_definitions WHERE status="active" ORDER BY department,title');
        $rows = hrKpiFetchAll($db, 'SELECT a.*, k.title AS kpi_title FROM hr_kpi_assignments a JOIN hr_kpi_definitions k ON k.id=a.kpi_id ORDER BY a.id DESC LIMIT 200');
        hrKpiAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>تخصیص جدید</h2></div><div class="card-body"><form method="post">
            <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="assign_kpi">
            <div class="form-grid"><div class="form-group"><label>KPI</label><select class="form-control" name="kpi_id"><?php foreach ($kpis as $kpi): ?><option value="<?php echo (int)$kpi['id']; ?>"><?php echo h((string)$kpi['title']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>نوع تخصیص</label><select class="form-control" name="assigned_scope_type"><option value="role">نقش</option><option value="department">دپارتمان</option><option value="employee">کارمند</option><option value="all">همه</option></select></div><div class="form-group"><label>شناسه محدوده</label><input class="form-control" name="assigned_scope_id"></div><div class="form-group"><label>شناسه کارمند</label><input class="form-control" type="number" name="employee_id"></div><div class="form-group"><label>ماه</label><input class="form-control" name="period_month" value="<?php echo h(date('Y-m')); ?>"></div><div class="form-group"><label>دوره</label><input class="form-control" type="number" name="period_id"></div></div>
            <button class="btn btn-primary">تخصیص</button>
        </form></div></div>
        <div class="card"><div class="card-header"><h2>تخصیص‌ها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>KPI</th><th>نوع</th><th>محدوده</th><th>کارمند</th><th>دوره</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo h((string)$row['kpi_title']); ?></td><td><?php echo h((string)$row['assigned_scope_type']); ?></td><td><?php echo h((string)($row['assigned_scope_id'] ?? $row['role_code'] ?? '')); ?></td><td><?php echo h((string)($row['employee_id'] ?? '')); ?></td><td><?php echo h((string)($row['period_id'] ?? $row['period_month'] ?? '')); ?></td><td><?php echo h((string)$row['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrKpiVisibleAssignmentWhere')) {
    function hrKpiVisibleAssignmentWhere(array $admin): array {
        if (hrCan($admin, 'hr_kpi_manage', ['manager','admin','super_admin']) || in_array((string)($admin['role'] ?? ''), ['admin','super_admin'], true)) return ['1=1', []];
        $id = (int)($admin['id'] ?? 0);
        $role = (string)($admin['role'] ?? 'employee');
        $department = (string)($admin['department'] ?? '');
        return ['(a.employee_id=? OR a.assigned_scope_type="all" OR (a.assigned_scope_type="role" AND a.assigned_scope_id=?) OR (a.assigned_scope_type="department" AND a.assigned_scope_id=?))', [$id, $role, $department]];
    }
}

if (!function_exists('hrKpiRenderEntriesPage')) {
    function hrKpiRenderEntriesPage(): void {
        [$db, $admin] = hrKpiRenderStart('ورود مقدار KPI', 'employee');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrKpiHandleEntryPost($db, $admin, $_POST) : '';
        [$where, $params] = hrKpiVisibleAssignmentWhere($admin);
        $assignments = hrKpiFetchAll($db, 'SELECT a.*, k.title AS kpi_title, k.unit_label, k.calculation_type FROM hr_kpi_assignments a JOIN hr_kpi_definitions k ON k.id=a.kpi_id WHERE ' . $where . ' ORDER BY a.id DESC LIMIT 200', $params);
        hrKpiAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>ثبت مقدار دستی</h2></div><div class="card-body"><form method="post">
            <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_entry">
            <div class="form-grid"><div class="form-group"><label>تخصیص</label><select class="form-control" name="assignment_id"><?php foreach ($assignments as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo h((string)$a['kpi_title']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>کارمند</label><input class="form-control" type="number" name="employee_id" value="<?php echo (int)($admin['id'] ?? 0); ?>"></div><div class="form-group"><label>مقدار واقعی</label><input class="form-control" type="number" step="0.01" name="actual_value" required></div><div class="form-group"><label>امتیاز دستی</label><input class="form-control" type="number" step="0.01" name="manual_score"></div></div><div class="form-group"><label>یادداشت</label><textarea class="form-control" name="note" rows="2"></textarea></div>
            <button class="btn btn-success">ثبت و محاسبه</button>
        </form></div></div>
        <div class="card"><div class="card-header"><h2>تخصیص‌های قابل ثبت</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>KPI</th><th>محدوده</th><th>واحد</th><th>نوع محاسبه</th></tr></thead><tbody><?php foreach ($assignments as $a): ?><tr><td><?php echo h((string)$a['kpi_title']); ?></td><td><?php echo h((string)($a['assigned_scope_id'] ?? '')); ?></td><td><?php echo h((string)($a['unit_label'] ?? '')); ?></td><td><?php echo h((string)$a['calculation_type']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrKpiRenderScoresPage')) {
    function hrKpiRenderScoresPage(): void {
        [$db, $admin] = hrKpiRenderStart('امتیازهای KPI', 'manager');
        $scores = hrKpiFetchAll($db, 'SELECT s.*, k.title, k.department, k.role_code FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id ORDER BY s.id DESC LIMIT 300');
        ?>
        <div class="card"><div class="card-header"><h2>نتایج محاسبه</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>KPI</th><th>کارمند</th><th>مقدار</th><th>هدف</th><th>درصد</th><th>وزنی</th><th>RAG</th><th>زمان</th></tr></thead><tbody><?php foreach ($scores as $s): ?><tr><td><?php echo h((string)$s['title']); ?></td><td><?php echo h((string)($s['employee_id'] ?? '')); ?></td><td><?php echo h((string)$s['actual_value']); ?></td><td><?php echo h((string)$s['target_value']); ?></td><td><?php echo h((string)$s['score_percent']); ?>%</td><td><?php echo h((string)$s['weighted_score']); ?></td><td><span class="badge badge-<?php echo $s['rag_status']==='green'?'success':($s['rag_status']==='yellow'?'warning':'danger'); ?>"><?php echo h((string)$s['rag_status']); ?></span></td><td><?php echo h((string)$s['calculated_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrKpiRenderReportPage')) {
    function hrKpiRenderReportPage(): void {
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            adminGuard('manager');
            $db = adminDb();
            hrKpiEnsureSchema($db);
            [$filterSql, $filterParams] = hrKpiReportFilterSql($_GET);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=kpi-report.csv');
            echo "department,role,kpi,score_percent,weighted_score,rag\n";
            foreach (hrKpiFetchAll($db, 'SELECT k.department,k.role_code,k.title,s.score_percent,s.weighted_score,s.rag_status FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id' . $filterSql . ' ORDER BY s.id DESC', $filterParams) as $row) {
                echo implode(',', array_map(static fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
            }
            exit;
        }
        [$db, $admin] = hrKpiRenderStart('گزارش KPI', 'manager');
        [$filterSql, $filterParams] = hrKpiReportFilterSql($_GET);
        $queryString = http_build_query(array_filter([
            'department' => trim((string)($_GET['department'] ?? '')),
            'role' => trim((string)($_GET['role'] ?? '')),
            'employee_id' => trim((string)($_GET['employee_id'] ?? '')),
            'period_id' => trim((string)($_GET['period_id'] ?? '')),
            'export' => 'csv',
        ], static fn($value) => $value !== ''));
        $summary = hrKpiFetchAll($db, 'SELECT k.department, k.role_code, COUNT(s.id) AS score_count, AVG(s.score_percent) AS score_percent, SUM(s.weighted_score) AS weighted_score FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id' . $filterSql . ' GROUP BY k.department,k.role_code ORDER BY k.department,k.role_code', $filterParams);
        $rag = hrKpiFetchAll($db, 'SELECT s.rag_status, COUNT(*) AS total FROM hr_kpi_scores s JOIN hr_kpi_definitions k ON k.id=s.kpi_id' . $filterSql . ' GROUP BY s.rag_status', $filterParams);
        $corrective = hrKpiFetchAll($db, 'SELECT c.*, k.title AS kpi_title FROM hr_kpi_corrective_actions c JOIN hr_kpi_scores s ON s.id=c.kpi_score_id JOIN hr_kpi_definitions k ON k.id=s.kpi_id' . $filterSql . ' ORDER BY c.id DESC LIMIT 100', $filterParams);
        ?>
        <div class="card"><div class="card-header"><h2>فیلتر گزارش</h2></div><div class="card-body"><form method="get" class="form-grid"><div class="form-group"><label>دپارتمان</label><input class="form-control" name="department" value="<?php echo h((string)($_GET['department'] ?? '')); ?>"></div><div class="form-group"><label>نقش</label><input class="form-control" name="role" value="<?php echo h((string)($_GET['role'] ?? '')); ?>"></div><div class="form-group"><label>شناسه کارمند</label><input class="form-control" type="number" name="employee_id" value="<?php echo h((string)($_GET['employee_id'] ?? '')); ?>"></div><div class="form-group"><label>شناسه دوره</label><input class="form-control" type="number" name="period_id" value="<?php echo h((string)($_GET['period_id'] ?? '')); ?>"></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn btn-secondary" href="hr-kpi-report.php">حذف فیلتر</a></div></form></div></div>
        <div class="card"><div class="card-header"><h2>گزارش تجمیعی</h2><a class="btn btn-sm" href="hr-kpi-report.php?<?php echo h($queryString); ?>">CSV</a></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>دپارتمان</th><th>نقش</th><th>تعداد</th><th>میانگین درصد</th><th>امتیاز وزنی</th></tr></thead><tbody><?php foreach ($summary as $row): ?><tr><td><?php echo h((string)$row['department']); ?></td><td><?php echo h((string)$row['role_code']); ?></td><td><?php echo (int)$row['score_count']; ?></td><td><?php echo h(number_format((float)$row['score_percent'], 2)); ?>%</td><td><?php echo h(number_format((float)$row['weighted_score'], 2)); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <div class="card"><div class="card-header"><h2>RAG و اقدامات اصلاحی</h2></div><div class="card-body"><p><?php foreach ($rag as $r): ?><span class="badge badge-info"><?php echo h($r['rag_status'] . ': ' . $r['total']); ?></span> <?php endforeach; ?></p><div class="table-responsive"><table class="table"><thead><tr><th>KPI</th><th>عنوان</th><th>مالک</th><th>موعد</th><th>تسک</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($corrective as $c): ?><tr><td><?php echo h((string)$c['kpi_title']); ?></td><td><?php echo h((string)$c['title']); ?></td><td><?php echo h((string)$c['owner_user_id']); ?></td><td><?php echo h((string)$c['due_date']); ?></td><td><?php echo h((string)($c['planner_task_id'] ?? '')); ?></td><td><?php echo h((string)$c['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}
