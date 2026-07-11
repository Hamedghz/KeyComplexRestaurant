<?php

require_once __DIR__ . '/planner_repository.php';

if (!function_exists('plannerPriorityOptions')) {
    function plannerPriorityOptions(): array {
        return ['low' => 'کم', 'normal' => 'عادی', 'high' => 'بالا', 'urgent' => 'فوری'];
    }
}

if (!function_exists('plannerStatusOptions')) {
    function plannerStatusOptions(): array {
        return [
            'pending' => 'در انتظار',
            'in_progress' => 'در حال انجام',
            'done' => 'انجام شد',
            'cancelled' => 'لغو شد',
            'postponed' => 'منتقل شد',
            'overdue' => 'عقب‌افتاده',
        ];
    }
}

if (!function_exists('plannerCanManageTask')) {
    function plannerCanManageTask(array $admin, array $task): bool {
        $role = (string)($admin['role'] ?? 'employee');
        $adminId = (int)($admin['id'] ?? 0);
        if ($role === 'super_admin' || $role === 'admin' || hrCan($admin, 'planner_report', ['admin', 'super_admin'])) {
            return true;
        }
        if ((int)($task['owner_user_id'] ?? 0) === $adminId && hrCan($admin, 'planner_manage_own', ['employee','manager','admin','super_admin'])) {
            return true;
        }
        if ((int)($task['assigned_by'] ?? 0) === $adminId && hrCan($admin, 'planner_assign', ['manager','admin','super_admin'])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('plannerNormalizeTaskPayload')) {
    function plannerNormalizeTaskPayload(array $data, array $admin): array {
        $ownerId = max(1, (int)($data['owner_user_id'] ?? $admin['id'] ?? 0));
        $progress = max(0, min(100, (int)($data['progress_percent'] ?? 0)));
        $priority = array_key_exists((string)($data['priority'] ?? ''), plannerPriorityOptions()) ? (string)$data['priority'] : 'normal';
        $status = array_key_exists((string)($data['status'] ?? ''), plannerStatusOptions()) ? (string)$data['status'] : 'pending';
        $taskDate = trim((string)($data['task_date'] ?? '')) ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $taskDate)) {
            $taskDate = date('Y-m-d');
        }
        $payload = [
            'title' => trim((string)($data['title'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'owner_user_id' => $ownerId,
            'assigned_by' => isset($data['assigned_by']) ? (int)$data['assigned_by'] : (int)($admin['id'] ?? 0),
            'department' => trim((string)($data['department'] ?? $admin['department'] ?? '')) ?: null,
            'role_key' => trim((string)($data['role_key'] ?? '')) ?: null,
            'task_date' => $taskDate,
            'due_at' => trim((string)($data['due_at'] ?? '')) ?: null,
            'period_id' => ($data['period_id'] ?? '') !== '' ? (int)$data['period_id'] : null,
            'shift_code' => trim((string)($data['shift_code'] ?? '')) ?: null,
            'priority' => $priority,
            'status' => $status,
            'progress_percent' => $status === 'done' ? 100 : $progress,
            'source_module' => trim((string)($data['source_module'] ?? '')) ?: null,
            'source_entity_type' => trim((string)($data['source_entity_type'] ?? '')) ?: null,
            'source_entity_id' => ($data['source_entity_id'] ?? '') !== '' ? (int)$data['source_entity_id'] : null,
            'linked_objective_id' => ($data['linked_objective_id'] ?? '') !== '' ? (int)$data['linked_objective_id'] : null,
            'linked_kr_id' => ($data['linked_kr_id'] ?? '') !== '' ? (int)$data['linked_kr_id'] : null,
            'linked_action_id' => ($data['linked_action_id'] ?? '') !== '' ? (int)$data['linked_action_id'] : null,
            'linked_kpi_score_id' => ($data['linked_kpi_score_id'] ?? '') !== '' ? (int)$data['linked_kpi_score_id'] : null,
            'linked_checklist_item_id' => ($data['linked_checklist_item_id'] ?? '') !== '' ? (int)$data['linked_checklist_item_id'] : null,
            'linked_customer_id' => ($data['linked_customer_id'] ?? '') !== '' ? (int)$data['linked_customer_id'] : null,
            'linked_followup_id' => ($data['linked_followup_id'] ?? '') !== '' ? (int)$data['linked_followup_id'] : null,
            'is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            'recurrence_rule' => trim((string)($data['recurrence_rule'] ?? '')) ?: null,
        ];
        if ($payload['title'] === '') {
            throw new InvalidArgumentException('عنوان تسک الزامی است.');
        }
        return $payload;
    }
}

if (!function_exists('plannerSaveTaskFromPost')) {
    function plannerSaveTaskFromPost(PDO $db, array $admin, array $post): int {
        $payload = plannerNormalizeTaskPayload($post, $admin);
        $taskId = plannerCreateTask($db, $payload);
        plannerInsertLog($db, $taskId, (int)($admin['id'] ?? 0), 'created', null, $payload['status'], trim((string)($post['note'] ?? '')));
        return $taskId;
    }
}

if (!function_exists('plannerQuickAdd')) {
    function plannerQuickAdd(PDO $db, array $admin, string $title): int {
        $payload = plannerNormalizeTaskPayload([
            'title' => $title,
            'owner_user_id' => (int)($admin['id'] ?? 0),
            'assigned_by' => (int)($admin['id'] ?? 0),
            'department' => (string)($admin['department'] ?? ''),
            'task_date' => date('Y-m-d'),
            'priority' => 'normal',
            'status' => 'pending',
            'source_module' => 'planner_quick_add',
        ], $admin);
        $taskId = plannerCreateTask($db, $payload);
        plannerInsertLog($db, $taskId, (int)($admin['id'] ?? 0), 'quick_add', null, 'pending', '');
        return $taskId;
    }
}

if (!function_exists('plannerMarkDone')) {
    function plannerMarkDone(PDO $db, array $admin, int $taskId, string $note = ''): bool {
        $task = plannerFetchTask($db, $taskId);
        if (!$task || !plannerCanManageTask($admin, $task)) {
            return false;
        }
        $oldStatus = (string)$task['status'];
        plannerUpdateTask($db, $taskId, ['status' => 'done', 'progress_percent' => 100, 'completed_at' => date('Y-m-d H:i:s')]);
        plannerInsertLog($db, $taskId, (int)($admin['id'] ?? 0), 'done', $oldStatus, 'done', $note);
        return true;
    }
}

if (!function_exists('plannerTransferToTomorrow')) {
    function plannerTransferToTomorrow(PDO $db, array $admin, int $taskId, string $note = ''): bool {
        $task = plannerFetchTask($db, $taskId);
        if (!$task || !plannerCanManageTask($admin, $task)) {
            return false;
        }
        $oldStatus = (string)$task['status'];
        plannerUpdateTask($db, $taskId, ['task_date' => date('Y-m-d', strtotime('+1 day')), 'status' => 'postponed']);
        plannerInsertLog($db, $taskId, (int)($admin['id'] ?? 0), 'transfer_tomorrow', $oldStatus, 'postponed', $note);
        return true;
    }
}

if (!function_exists('plannerHandleActionPost')) {
    function plannerHandleActionPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) {
            return 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
        }
        $action = (string)($post['planner_action'] ?? '');
        try {
            if ($action === 'quick_add') {
                plannerQuickAdd($db, $admin, (string)($post['title'] ?? ''));
                return 'تسک سریع ثبت شد.';
            }
            if ($action === 'create_task') {
                plannerSaveTaskFromPost($db, $admin, $post);
                return 'تسک ثبت شد.';
            }
            if ($action === 'mark_done') {
                return plannerMarkDone($db, $admin, (int)($post['task_id'] ?? 0), (string)($post['note'] ?? '')) ? 'تسک انجام شد.' : 'امکان تغییر این تسک وجود ندارد.';
            }
            if ($action === 'transfer_tomorrow') {
                return plannerTransferToTomorrow($db, $admin, (int)($post['task_id'] ?? 0), (string)($post['note'] ?? '')) ? 'تسک به فردا منتقل شد.' : 'امکان انتقال این تسک وجود ندارد.';
            }
            if ($action === 'comment') {
                $task = plannerFetchTask($db, (int)($post['task_id'] ?? 0));
                if ($task && plannerCanManageTask($admin, $task) && trim((string)($post['comment'] ?? '')) !== '') {
                    plannerAddComment($db, (int)$task['id'], (int)($admin['id'] ?? 0), (string)$post['comment']);
                    plannerInsertLog($db, (int)$task['id'], (int)($admin['id'] ?? 0), 'commented', (string)$task['status'], (string)$task['status'], '');
                    return 'یادداشت ثبت شد.';
                }
                return 'امکان ثبت یادداشت وجود ندارد.';
            }
        } catch (Throwable $e) {
            safeAdminLog('Planner action failed: ' . $e->getMessage());
            return 'خطا در ثبت عملیات پلنر. جزئیات در لاگ ثبت شد.';
        }
        return '';
    }
}
