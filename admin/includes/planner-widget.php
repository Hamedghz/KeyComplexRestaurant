<?php

require_once __DIR__ . '/../lib/hr/planner/planner_service.php';
require_once __DIR__ . '/../lib/hr/planner/planner_report_service.php';

$plannerWidgetMessage = '';
$plannerWidgetTasks = [];
$plannerWidgetSummary = ['today' => 0, 'overdue' => 0, 'tomorrow' => 0];

try {
    $plannerDb = adminDb();
    plannerEnsureSchema($plannerDb);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['planner_action'])) {
        $plannerWidgetMessage = plannerHandleActionPost($plannerDb, $currentAdmin ?? [], $_POST);
    }
    $plannerWidgetTasks = plannerQueryTasks($plannerDb, ['date_mode' => 'today'], $currentAdmin ?? []);
    $plannerWidgetSummary = plannerReportSummary($plannerDb, $currentAdmin ?? []);
} catch (Throwable $e) {
    safeAdminLog('Planner widget failed: ' . $e->getMessage());
}
?>
<div class="card">
    <div class="card-header">
        <h2>پلنر امروز</h2>
        <a href="planner.php" class="btn btn-sm">جزئیات</a>
    </div>
    <div class="card-body">
        <?php if ($plannerWidgetMessage !== ''): ?>
            <div class="alert alert-info"><?php echo h($plannerWidgetMessage); ?></div>
        <?php endif; ?>
        <form method="post" class="form-inline" style="margin-bottom:12px;">
            <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="planner_action" value="quick_add">
            <div class="form-group" style="flex:1;min-width:220px;">
                <input class="form-control" type="text" name="title" placeholder="افزودن سریع تسک امروز" required>
            </div>
            <button class="btn btn-primary" type="submit">ثبت</button>
        </form>
        <div class="quick-actions" style="margin-bottom:12px;">
            <a class="quick-action-btn" href="planner-today.php"><span class="icon">📅</span><span>امروز: <?php echo (int)($plannerWidgetSummary['today'] ?? 0); ?></span></a>
            <a class="quick-action-btn" href="planner.php?date_mode=overdue"><span class="icon">⏳</span><span>عقب‌افتاده: <?php echo (int)($plannerWidgetSummary['overdue'] ?? 0); ?></span></a>
            <a class="quick-action-btn" href="planner.php?date_mode=tomorrow"><span class="icon">➡️</span><span>فردا: <?php echo (int)($plannerWidgetSummary['tomorrow'] ?? 0); ?></span></a>
        </div>
        <?php if (!$plannerWidgetTasks): ?>
            <p class="text-muted">برای امروز تسکی ثبت نشده است.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                    <?php foreach (array_slice($plannerWidgetTasks, 0, 5) as $plannerTask): ?>
                        <tr>
                            <td><?php echo h((string)$plannerTask['title']); ?></td>
                            <td><?php echo h((string)$plannerTask['priority']); ?></td>
                            <td>
                                <?php if (plannerCanManageTask($currentAdmin ?? [], $plannerTask) && (string)$plannerTask['status'] !== 'done'): ?>
                                    <form method="post" style="display:inline-block;margin:0 2px;">
                                        <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                        <input type="hidden" name="planner_action" value="mark_done">
                                        <input type="hidden" name="task_id" value="<?php echo (int)$plannerTask['id']; ?>">
                                        <button class="btn btn-sm btn-success" type="submit">انجام شد</button>
                                    </form>
                                    <form method="post" style="display:inline-block;margin:0 2px;">
                                        <input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                        <input type="hidden" name="planner_action" value="transfer_tomorrow">
                                        <input type="hidden" name="task_id" value="<?php echo (int)$plannerTask['id']; ?>">
                                        <button class="btn btn-sm btn-warning" type="submit">فردا</button>
                                    </form>
                                <?php endif; ?>
                                <a class="btn btn-sm" href="planner.php?task_date=<?php echo urlencode((string)$plannerTask['task_date']); ?>">جزئیات</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
