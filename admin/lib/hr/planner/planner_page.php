<?php

require_once __DIR__ . '/planner_service.php';
require_once __DIR__ . '/planner_report_service.php';

if (!function_exists('plannerRenderTaskRows')) {
    function plannerRenderTaskRows(array $tasks, array $admin): void {
        $statuses = plannerStatusOptions();
        $priorities = plannerPriorityOptions();
        if (!$tasks) {
            echo '<p class="text-muted">تسکی برای نمایش وجود ندارد.</p>';
            return;
        }
        echo '<div class="table-responsive"><table class="table"><thead><tr><th>عنوان</th><th>تاریخ</th><th>اولویت</th><th>وضعیت</th><th>پیشرفت</th><th>لینک</th><th>عملیات</th></tr></thead><tbody>';
        foreach ($tasks as $task) {
            $canManage = plannerCanManageTask($admin, $task);
            $linked = trim((string)($task['source_module'] ?? '')) !== '' ? (string)$task['source_module'] : '-';
            echo '<tr>';
            echo '<td><strong>' . h((string)$task['title']) . '</strong><br><small class="text-muted">' . h((string)($task['description'] ?? '')) . '</small></td>';
            echo '<td>' . h((string)$task['task_date']) . '</td>';
            echo '<td>' . h($priorities[(string)$task['priority']] ?? (string)$task['priority']) . '</td>';
            echo '<td><span class="badge badge-info">' . h($statuses[(string)$task['status']] ?? (string)$task['status']) . '</span></td>';
            echo '<td>' . (int)$task['progress_percent'] . '%</td>';
            echo '<td>' . h($linked) . '</td>';
            echo '<td>';
            if ($canManage && (string)$task['status'] !== 'done') {
                echo '<form method="post" style="display:inline-block;margin:0 2px"><input type="hidden" name="' . h(CSRF_TOKEN_NAME) . '" value="' . h(generateCSRFToken()) . '"><input type="hidden" name="planner_action" value="mark_done"><input type="hidden" name="task_id" value="' . (int)$task['id'] . '"><button class="btn btn-sm btn-success" type="submit">انجام شد</button></form>';
                echo '<form method="post" style="display:inline-block;margin:0 2px"><input type="hidden" name="' . h(CSRF_TOKEN_NAME) . '" value="' . h(generateCSRFToken()) . '"><input type="hidden" name="planner_action" value="transfer_tomorrow"><input type="hidden" name="task_id" value="' . (int)$task['id'] . '"><button class="btn btn-sm btn-warning" type="submit">انتقال به فردا</button></form>';
            }
            echo '<a class="btn btn-sm" href="planner.php?task_date=' . urlencode((string)$task['task_date']) . '">جزئیات</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('plannerRenderPage')) {
    function plannerRenderPage(string $mode = 'all'): void {
        $currentAdmin = adminGuard('employee');
        if (!hrCan($currentAdmin, 'planner_access', ['employee','manager','admin','super_admin'])) {
            adminRenderSafeError('پلنر کاری', 'Planner access denied.');
            return;
        }
        $db = adminDb();
        plannerEnsureSchema($db);
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = plannerHandleActionPost($db, $currentAdmin, $_POST);
        }
        $filters = [
            'date_mode' => $mode,
            'task_date' => trim((string)($_GET['task_date'] ?? '')),
            'status' => trim((string)($_GET['status'] ?? '')),
        ];
        if ($mode === 'assigned') {
            $filters['assigned_by'] = (int)($currentAdmin['id'] ?? 0);
            $filters['date_mode'] = '';
        }
        $tasks = plannerQueryTasks($db, $filters, $currentAdmin);
        $summary = plannerReportSummary($db, $currentAdmin);
        $pageTitle = $mode === 'report' ? 'گزارش پلنر' : 'پلنر کاری';
        include __DIR__ . '/../../../includes/header.php';
        ?>
        <?php if ($message !== ''): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
        <div class="stats-row">
            <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo (int)$summary['today']; ?></h3><p>امروز</p></div></div>
            <div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo (int)$summary['overdue']; ?></h3><p>عقب‌افتاده</p></div></div>
            <div class="stat-card stat-info"><div class="stat-content"><h3><?php echo (int)$summary['tomorrow']; ?></h3><p>فردا</p></div></div>
            <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo (int)$summary['done']; ?></h3><p>انجام شده</p></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h2>افزودن سریع</h2></div>
            <div class="card-body">
                <form method="post" class="form-inline">
                    <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="planner_action" value="quick_add">
                    <div class="form-group"><input class="form-control" type="text" name="title" placeholder="عنوان تسک امروز" required></div>
                    <button class="btn btn-primary" type="submit">ثبت سریع</button>
                </form>
            </div>
        </div>
        <?php if ($mode !== 'report'): ?>
            <div class="card">
                <div class="card-header"><h2>تسک جدید</h2></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                        <input type="hidden" name="planner_action" value="create_task">
                        <div class="form-grid">
                            <div class="form-group"><label>عنوان</label><input class="form-control" type="text" name="title" required></div>
                            <div class="form-group"><label>شناسه مسئول</label><input class="form-control" type="number" name="owner_user_id" value="<?php echo (int)($currentAdmin['id'] ?? 0); ?>"></div>
                            <div class="form-group"><label>تاریخ</label><input class="form-control" type="date" name="task_date" value="<?php echo h(date('Y-m-d')); ?>"></div>
                            <div class="form-group"><label>اولویت</label><select class="form-control" name="priority"><?php foreach (plannerPriorityOptions() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>وضعیت</label><select class="form-control" name="status"><?php foreach (plannerStatusOptions() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>درصد پیشرفت</label><input class="form-control" type="number" name="progress_percent" min="0" max="100" value="0"></div>
                            <div class="form-group"><label>شیفت</label><input class="form-control" type="text" name="shift_code"></div>
                            <div class="form-group"><label>تکرار</label><input class="form-control" type="text" name="recurrence_rule" placeholder="weekly / monthly"></div>
                        </div>
                        <div class="form-group"><label>توضیح</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                        <div class="form-group"><label><input type="checkbox" name="is_recurring" value="1"> تسک تکرارشونده</label></div>
                        <button class="btn btn-primary" type="submit">ثبت تسک</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <h2><?php echo $mode === 'report' ? 'خلاصه و تسک‌های قابل مشاهده' : 'لیست تسک‌ها'; ?></h2>
                <div>
                    <a class="btn btn-sm" href="planner-today.php">امروز</a>
                    <a class="btn btn-sm" href="planner.php?date_mode=yesterday">دیروز</a>
                    <a class="btn btn-sm" href="planner.php?date_mode=tomorrow">فردا</a>
                    <a class="btn btn-sm" href="planner.php?date_mode=overdue">عقب‌افتاده</a>
                </div>
            </div>
            <div class="card-body"><?php plannerRenderTaskRows($tasks, $currentAdmin); ?></div>
        </div>
        <?php
        include __DIR__ . '/../../../includes/footer.php';
    }
}
