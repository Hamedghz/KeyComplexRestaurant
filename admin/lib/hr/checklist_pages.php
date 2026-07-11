<?php

require_once __DIR__ . '/checklists.php';

if (!function_exists('hrChecklistRenderLayoutStart')) {
    function hrChecklistRenderLayoutStart(string $title, string $role = 'employee'): array {
        $currentAdmin = adminGuard($role);
        $db = adminDb();
        hrChecklistEnsureSchema($db);
        $pageTitle = $title;
        include __DIR__ . '/../../includes/header.php';
        return [$db, $currentAdmin];
    }
}

if (!function_exists('hrChecklistAlert')) {
    function hrChecklistAlert(string $message): void {
        if ($message !== '') {
            echo '<div class="alert alert-info">' . h($message) . '</div>';
        }
    }
}

if (!function_exists('hrChecklistRenderDutiesPage')) {
    function hrChecklistRenderDutiesPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('شرح وظایف نقش‌ها', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrChecklistHandleDutiesPost($db, $admin, $_POST) : '';
        $duties = hrChecklistFetchAll($db, 'SELECT * FROM hr_role_duties WHERE status != "archived" ORDER BY role_code ASC, sort_order ASC, id DESC LIMIT 300');
        hrChecklistAlert($message);
        ?>
        <div class="card">
            <div class="card-header"><h2>ثبت / ویرایش شرح وظیفه</h2></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="save_duty">
                    <div class="form-grid">
                        <div class="form-group"><label>نقش</label><input class="form-control" name="role_key" placeholder="internal_manager" required></div>
                        <div class="form-group"><label>دپارتمان</label><input class="form-control" name="department" placeholder="operations"></div>
                        <div class="form-group"><label>عنوان</label><input class="form-control" name="title" required></div>
                        <div class="form-group"><label>نوع مسئولیت</label><select class="form-control" name="responsibility_type"><option value="daily">روزانه</option><option value="shift">شیفت</option><option value="weekly">هفتگی</option><option value="monthly">ماهانه</option><option value="general">عمومی</option><option value="as_needed">در صورت نیاز</option></select></div>
                        <div class="form-group"><label>استاندارد</label><input class="form-control" name="standard_group" placeholder="sop_5s"></div>
                        <div class="form-group"><label>اولویت</label><select class="form-control" name="priority"><option value="normal">عادی</option><option value="low">کم</option><option value="high">بالا</option><option value="critical">بحرانی</option></select></div>
                        <div class="form-group"><label>وضعیت</label><select class="form-control" name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option><option value="archived">آرشیو</option></select></div>
                        <div class="form-group"><label>ترتیب</label><input class="form-control" type="number" name="sort_order" value="0"></div>
                    </div>
                    <div class="form-group"><label>توضیح</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                    <button class="btn btn-primary" type="submit">ذخیره</button>
                </form>
            </div>
        </div>
        <div class="card"><div class="card-header"><h2>شرح وظایف ثبت‌شده</h2></div><div class="card-body table-responsive">
            <table class="table"><thead><tr><th>نقش</th><th>دپارتمان</th><th>عنوان</th><th>نوع</th><th>اولویت</th><th>وضعیت</th></tr></thead><tbody>
                <?php foreach ($duties as $duty): ?><tr><td><?php echo h((string)($duty['role_key'] ?: $duty['role_code'])); ?></td><td><?php echo h((string)($duty['department'] ?? '')); ?></td><td><?php echo h((string)$duty['title']); ?></td><td><?php echo h((string)$duty['responsibility_type']); ?></td><td><?php echo h((string)$duty['priority']); ?></td><td><?php echo h((string)$duty['status']); ?></td></tr><?php endforeach; ?>
                <?php if (!$duties): ?><tr><td colspan="6" class="text-muted">رکوردی یافت نشد.</td></tr><?php endif; ?>
            </tbody></table>
        </div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrChecklistRenderTemplatesPage')) {
    function hrChecklistRenderTemplatesPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('قالب چک‌لیست‌ها', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrChecklistHandleTemplatePost($db, $admin, $_POST) : '';
        $templates = hrChecklistFetchTemplates($db);
        $duties = hrChecklistFetchAll($db, 'SELECT id, role_code, title FROM hr_role_duties WHERE status="active" ORDER BY role_code,title LIMIT 300');
        hrChecklistAlert($message);
        ?>
        <div class="card">
            <div class="card-header"><h2>قالب جدید</h2></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="save_template">
                    <div class="form-grid">
                        <div class="form-group"><label>کد</label><input class="form-control" name="code" required></div>
                        <div class="form-group"><label>عنوان</label><input class="form-control" name="title" required></div>
                        <div class="form-group"><label>دپارتمان</label><input class="form-control" name="department"></div>
                        <div class="form-group"><label>نقش</label><input class="form-control" name="role_key"></div>
                        <div class="form-group"><label>دوره</label><select class="form-control" name="period_type"><option value="daily">روزانه</option><option value="shift">شیفت</option><option value="weekly">هفتگی</option><option value="monthly">ماهانه</option><option value="custom">دلخواه</option></select></div>
                        <div class="form-group"><label>شیفت</label><input class="form-control" name="shift_code"></div>
                        <div class="form-group"><label><input type="checkbox" name="requires_manager_approval" value="1"> تایید مدیر</label></div>
                        <div class="form-group"><label><input type="checkbox" name="requires_inspector_approval" value="1"> تایید بازرس</label></div>
                    </div>
                    <div class="form-group"><label>توضیح</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <button class="btn btn-primary" type="submit">ذخیره قالب</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>افزودن آیتم</h2></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="save_item">
                    <div class="form-grid">
                        <div class="form-group"><label>قالب</label><select class="form-control" name="template_id"><?php foreach ($templates as $template): ?><option value="<?php echo (int)$template['id']; ?>"><?php echo h((string)$template['title']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>کد آیتم</label><input class="form-control" name="item_code"></div>
                        <div class="form-group"><label>عنوان</label><input class="form-control" name="title" required></div>
                        <div class="form-group"><label>فاز</label><input class="form-control" name="phase" value="during_shift"></div>
                        <div class="form-group"><label>شرح وظیفه لینک‌شده</label><select class="form-control" name="linked_duty_id"><option value="">-</option><?php foreach ($duties as $duty): ?><option value="<?php echo (int)$duty['id']; ?>"><?php echo h($duty['role_code'] . ' - ' . $duty['title']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>امتیاز کیفی سقف</label><input class="form-control" type="number" name="max_quality_score" value="5"></div>
                        <div class="form-group"><label><input type="checkbox" name="is_required" value="1" checked> الزامی</label></div>
                        <div class="form-group"><label><input type="checkbox" name="has_quality_score" value="1"> امتیاز کیفی</label></div>
                        <div class="form-group"><label><input type="checkbox" name="has_note" value="1"> یادداشت</label></div>
                        <div class="form-group"><label><input type="checkbox" name="can_create_planner_task" value="1"> ایجاد تسک اصلاحی</label></div>
                    </div>
                    <div class="form-group"><label>توضیح</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <button class="btn btn-primary" type="submit">ذخیره آیتم</button>
                </form>
            </div>
        </div>
        <div class="card"><div class="card-header"><h2>قالب‌ها</h2></div><div class="card-body table-responsive">
            <table class="table"><thead><tr><th>کد</th><th>عنوان</th><th>نقش</th><th>دوره</th><th>آیتم‌ها</th></tr></thead><tbody>
                <?php foreach ($templates as $template): $items = hrChecklistFetchItems($db, (int)$template['id']); ?><tr><td><?php echo h((string)($template['code'] ?: $template['template_code'] ?: $template['template_key'])); ?></td><td><?php echo h((string)$template['title']); ?></td><td><?php echo h((string)($template['role_key'] ?: $template['role_code'])); ?></td><td><?php echo h((string)$template['period_type']); ?></td><td><?php echo count($items); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrChecklistRenderAssignmentsPage')) {
    function hrChecklistRenderAssignmentsPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('تخصیص چک‌لیست', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrChecklistHandleAssignmentPost($db, $admin, $_POST) : '';
        $templates = hrChecklistFetchTemplates($db);
        $assignments = hrChecklistFetchAll($db, 'SELECT a.*, t.title AS template_title FROM hr_checklist_assignments a JOIN hr_checklist_templates t ON t.id=a.template_id ORDER BY a.id DESC LIMIT 200');
        hrChecklistAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>تخصیص جدید</h2></div><div class="card-body">
            <form method="post">
                <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="assign">
                <div class="form-grid">
                    <div class="form-group"><label>قالب</label><select class="form-control" name="template_id"><?php foreach ($templates as $template): ?><option value="<?php echo (int)$template['id']; ?>"><?php echo h((string)$template['title']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>نوع تخصیص</label><select class="form-control" name="assigned_scope_type"><option value="role">نقش</option><option value="department">دپارتمان</option><option value="employee">کارمند</option><option value="all">همه</option></select></div>
                    <div class="form-group"><label>شناسه محدوده</label><input class="form-control" name="assigned_scope_id" placeholder="cashier / hall"></div>
                    <div class="form-group"><label>شناسه کارمند</label><input class="form-control" type="number" name="assigned_employee_id"></div>
                    <div class="form-group"><label>شروع</label><input class="form-control" name="starts_at" value="<?php echo h(date('Y-m-d 00:00:00')); ?>"></div>
                    <div class="form-group"><label>پایان</label><input class="form-control" name="ends_at"></div>
                </div>
                <button class="btn btn-primary" type="submit">تخصیص</button>
            </form>
        </div></div>
        <div class="card"><div class="card-header"><h2>تخصیص‌ها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>قالب</th><th>نوع</th><th>محدوده</th><th>کارمند</th><th>شروع</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($assignments as $row): ?><tr><td><?php echo h((string)$row['template_title']); ?></td><td><?php echo h((string)$row['assigned_scope_type']); ?></td><td><?php echo h((string)($row['assigned_scope_id'] ?? '')); ?></td><td><?php echo h((string)($row['assigned_employee_id'] ?? '')); ?></td><td><?php echo h((string)($row['starts_at'] ?? $row['due_date'] ?? '')); ?></td><td><?php echo h((string)$row['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrChecklistVisibleAssignmentWhere')) {
    function hrChecklistVisibleAssignmentWhere(array $admin): array {
        if (hrCan($admin, 'hr_checklists_manage', ['manager','admin','super_admin']) || in_array((string)($admin['role'] ?? ''), ['admin','super_admin'], true)) {
            return ['1=1', []];
        }
        $adminId = (int)($admin['id'] ?? 0);
        $role = (string)($admin['role'] ?? 'employee');
        $department = (string)($admin['department'] ?? '');
        return ['(a.assigned_employee_id=? OR a.employee_id=? OR a.assigned_scope_type="all" OR (a.assigned_scope_type="role" AND a.assigned_scope_id=?) OR (a.assigned_scope_type="department" AND a.assigned_scope_id=?))', [$adminId, $adminId, $role, $department]];
    }
}

if (!function_exists('hrChecklistRenderSubmissionsPage')) {
    function hrChecklistRenderSubmissionsPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('ثبت انجام چک‌لیست', 'employee');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrChecklistHandleSubmissionPost($db, $admin, $_POST) : '';
        [$where, $params] = hrChecklistVisibleAssignmentWhere($admin);
        $assignments = hrChecklistFetchAll($db, 'SELECT a.*, t.title AS template_title FROM hr_checklist_assignments a JOIN hr_checklist_templates t ON t.id=a.template_id WHERE ' . $where . ' ORDER BY a.id DESC LIMIT 100', $params);
        $selectedId = (int)($_GET['assignment_id'] ?? ($assignments[0]['id'] ?? 0));
        $selected = null;
        foreach ($assignments as $assignment) if ((int)$assignment['id'] === $selectedId) $selected = $assignment;
        $items = $selected ? hrChecklistFetchItems($db, (int)$selected['template_id']) : [];
        hrChecklistAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>انتخاب تخصیص</h2></div><div class="card-body"><div class="quick-actions"><?php foreach ($assignments as $assignment): ?><a class="quick-action-btn" href="hr-checklist-submissions.php?assignment_id=<?php echo (int)$assignment['id']; ?>"><span class="icon">▣</span><strong><?php echo h((string)$assignment['template_title']); ?></strong></a><?php endforeach; ?><?php if (!$assignments): ?><p class="text-muted">تخصیصی برای شما وجود ندارد.</p><?php endif; ?></div></div></div>
        <?php if ($selected): ?>
        <div class="card"><div class="card-header"><h2><?php echo h((string)$selected['template_title']); ?></h2></div><div class="card-body">
            <form method="post">
                <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="submit_checklist">
                <input type="hidden" name="assignment_id" value="<?php echo (int)$selected['id']; ?>">
                <input type="hidden" name="submission_status" value="submitted">
                <div class="form-grid"><div class="form-group"><label>تاریخ</label><input class="form-control" type="date" name="checklist_date" value="<?php echo h(date('Y-m-d')); ?>"></div><div class="form-group"><label>شیفت</label><input class="form-control" name="shift_code"></div></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>آیتم</th><th>انجام شد</th><th>امتیاز</th><th>یادداشت</th><th>ایراد / تسک اصلاحی</th></tr></thead><tbody><?php foreach ($items as $item): $itemId=(int)$item['id']; ?><tr><td><?php echo h((string)$item['title']); ?></td><td><input type="checkbox" name="done[<?php echo $itemId; ?>]" value="1"></td><td><?php if (!empty($item['has_quality_score'])): ?><input class="form-control" type="number" name="quality[<?php echo $itemId; ?>]" min="0" max="<?php echo h((string)($item['max_quality_score'] ?: 5)); ?>"><?php endif; ?></td><td><?php if (!empty($item['has_note'])): ?><input class="form-control" name="note[<?php echo $itemId; ?>]"><?php endif; ?></td><td><input type="checkbox" name="issue[<?php echo $itemId; ?>]" value="1"> <?php echo !empty($item['can_create_planner_task']) ? 'تسک اصلاحی' : ''; ?></td></tr><?php endforeach; ?></tbody></table></div>
                <button class="btn btn-success" type="submit">ثبت چک‌لیست</button>
            </form>
        </div></div>
        <?php endif; include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrChecklistRenderApprovalsPage')) {
    function hrChecklistRenderApprovalsPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('تایید چک‌لیست‌ها', 'manager');
        $message = $_SERVER['REQUEST_METHOD'] === 'POST' ? hrChecklistHandleApprovalPost($db, $admin, $_POST) : '';
        $subs = hrChecklistFetchAll($db, 'SELECT s.*, t.title AS template_title, a.assigned_scope_type, a.assigned_scope_id FROM hr_checklist_submissions s JOIN hr_checklist_templates t ON t.id=s.template_id JOIN hr_checklist_assignments a ON a.id=s.assignment_id ORDER BY s.id DESC LIMIT 200');
        hrChecklistAlert($message);
        ?>
        <div class="card"><div class="card-header"><h2>در انتظار تایید / بازبینی</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>قالب</th><th>کارمند</th><th>تاریخ</th><th>تکمیل</th><th>امتیاز</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($subs as $sub): ?><tr><td><?php echo h((string)$sub['template_title']); ?></td><td><?php echo (int)$sub['employee_id']; ?></td><td><?php echo h((string)$sub['checklist_date']); ?></td><td><?php echo h((string)$sub['completion_percent']); ?>%</td><td><?php echo h((string)$sub['total_quality_score']); ?></td><td><?php echo h((string)$sub['status']); ?></td><td><form method="post" class="admin-filter"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="submission_id" value="<?php echo (int)$sub['id']; ?>"><select class="form-control" name="approval_type"><option value="manager">مدیر</option><option value="inspector">بازرس</option></select><select class="form-control" name="approval_status"><option value="approved">تایید</option><option value="rejected">رد</option></select><input class="form-control" name="note" placeholder="یادداشت"><button class="btn btn-sm btn-primary">ثبت</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}

if (!function_exists('hrChecklistRenderReportPage')) {
    function hrChecklistRenderReportPage(): void {
        [$db, $admin] = hrChecklistRenderLayoutStart('گزارش چک‌لیست‌ها', 'manager');
        $summary = [
            'submissions' => (int)($db->query('SELECT COUNT(*) FROM hr_checklist_submissions')->fetchColumn() ?: 0),
            'pending' => (int)($db->query('SELECT COUNT(*) FROM hr_checklist_submissions WHERE status="submitted"')->fetchColumn() ?: 0),
            'rejected' => (int)($db->query('SELECT COUNT(*) FROM hr_checklist_submissions WHERE status="rejected"')->fetchColumn() ?: 0),
            'issues' => (int)($db->query('SELECT COUNT(*) FROM hr_checklist_submission_items WHERE issue_flag=1')->fetchColumn() ?: 0),
        ];
        $rows = hrChecklistFetchAll($db, 'SELECT t.title, t.role_key, t.role_code, t.department, COUNT(s.id) AS submissions, AVG(s.completion_percent) AS completion_rate, AVG(s.total_quality_score) AS quality_score FROM hr_checklist_templates t LEFT JOIN hr_checklist_submissions s ON s.template_id=t.id GROUP BY t.id ORDER BY t.department,t.title');
        ?>
        <div class="stats-row"><div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo $summary['submissions']; ?></h3><p>ثبت‌ها</p></div></div><div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo $summary['pending']; ?></h3><p>نیازمند تایید</p></div></div><div class="stat-card stat-danger"><div class="stat-content"><h3><?php echo $summary['rejected']; ?></h3><p>رد شده</p></div></div><div class="stat-card stat-info"><div class="stat-content"><h3><?php echo $summary['issues']; ?></h3><p>ایرادات</p></div></div></div>
        <div class="card"><div class="card-header"><h2>گزارش تکمیل و کیفیت</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>قالب</th><th>نقش</th><th>دپارتمان</th><th>تعداد ثبت</th><th>میانگین تکمیل</th><th>میانگین کیفیت</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo h((string)$row['title']); ?></td><td><?php echo h((string)($row['role_key'] ?: $row['role_code'])); ?></td><td><?php echo h((string)$row['department']); ?></td><td><?php echo (int)$row['submissions']; ?></td><td><?php echo h(number_format((float)$row['completion_rate'], 2)); ?>%</td><td><?php echo h(number_format((float)$row['quality_score'], 2)); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php include __DIR__ . '/../../includes/footer.php';
    }
}
