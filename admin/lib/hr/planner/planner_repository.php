<?php

require_once __DIR__ . '/../bootstrap.php';

if (!function_exists('plannerSchemaSqlStatements')) {
    function plannerSchemaSqlStatements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS `planner_tasks` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` varchar(190) NOT NULL,
                `description` text DEFAULT NULL,
                `owner_user_id` int(11) UNSIGNED NOT NULL,
                `assigned_by` int(11) UNSIGNED DEFAULT NULL,
                `department` varchar(100) DEFAULT NULL,
                `role_key` varchar(80) DEFAULT NULL,
                `task_date` date NOT NULL,
                `due_at` datetime DEFAULT NULL,
                `period_id` int(11) UNSIGNED DEFAULT NULL,
                `shift_code` varchar(60) DEFAULT NULL,
                `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                `status` enum('pending','in_progress','done','cancelled','postponed','overdue') NOT NULL DEFAULT 'pending',
                `progress_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
                `source_module` varchar(100) DEFAULT NULL,
                `source_entity_type` varchar(100) DEFAULT NULL,
                `source_entity_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_objective_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_kr_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_action_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_kpi_score_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_checklist_item_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_customer_id` int(11) UNSIGNED DEFAULT NULL,
                `linked_followup_id` int(11) UNSIGNED DEFAULT NULL,
                `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
                `recurrence_rule` varchar(190) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `completed_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_planner_owner_date` (`owner_user_id`, `task_date`, `status`),
                KEY `idx_planner_assigned` (`assigned_by`, `task_date`),
                KEY `idx_planner_department` (`department`, `role_key`, `status`),
                KEY `idx_planner_due` (`status`, `due_at`),
                KEY `idx_planner_source` (`source_module`, `source_entity_type`, `source_entity_id`),
                KEY `idx_planner_links` (`linked_objective_id`, `linked_kr_id`, `linked_action_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `planner_task_logs` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` int(11) UNSIGNED NOT NULL,
                `user_id` int(11) UNSIGNED DEFAULT NULL,
                `action` varchar(80) NOT NULL,
                `old_status` varchar(40) DEFAULT NULL,
                `new_status` varchar(40) DEFAULT NULL,
                `note` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_planner_logs_task` (`task_id`, `created_at`),
                KEY `idx_planner_logs_user` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `planner_task_comments` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` int(11) UNSIGNED NOT NULL,
                `user_id` int(11) UNSIGNED DEFAULT NULL,
                `comment` text NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_planner_comments_task` (`task_id`, `created_at`),
                KEY `idx_planner_comments_user` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}

if (!function_exists('plannerEnsureSchema')) {
    function plannerEnsureSchema(?PDO $db = null): void {
        $db = $db ?: adminDb();
        foreach (plannerSchemaSqlStatements() as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable $e) {
                safeAdminLog('Planner schema ensure failed: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('plannerFetchTask')) {
    function plannerFetchTask(PDO $db, int $id): ?array {
        $stmt = $db->prepare('SELECT * FROM planner_tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($task) ? $task : null;
    }
}

if (!function_exists('plannerCreateTask')) {
    function plannerCreateTask(PDO $db, array $data): int {
        $columns = [
            'title','description','owner_user_id','assigned_by','department','role_key','task_date','due_at',
            'period_id','shift_code','priority','status','progress_percent','source_module','source_entity_type',
            'source_entity_id','linked_objective_id','linked_kr_id','linked_action_id','linked_kpi_score_id',
            'linked_checklist_item_id','linked_customer_id','linked_followup_id','is_recurring','recurrence_rule'
        ];
        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $fields[] = '`' . $column . '`';
                $placeholders[] = '?';
                $values[] = $data[$column];
            }
        }
        if (!$fields) {
            throw new InvalidArgumentException('Planner task data is empty.');
        }
        $stmt = $db->prepare('INSERT INTO planner_tasks (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')');
        $stmt->execute($values);
        return (int)$db->lastInsertId();
    }
}

if (!function_exists('plannerUpdateTask')) {
    function plannerUpdateTask(PDO $db, int $id, array $data): void {
        $allowed = [
            'title','description','owner_user_id','assigned_by','department','role_key','task_date','due_at',
            'period_id','shift_code','priority','status','progress_percent','source_module','source_entity_type',
            'source_entity_id','linked_objective_id','linked_kr_id','linked_action_id','linked_kpi_score_id',
            'linked_checklist_item_id','linked_customer_id','linked_followup_id','is_recurring','recurrence_rule',
            'completed_at'
        ];
        $sets = [];
        $values = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = '`' . $column . '` = ?';
                $values[] = $data[$column];
            }
        }
        if (!$sets) {
            return;
        }
        $values[] = $id;
        $stmt = $db->prepare('UPDATE planner_tasks SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
    }
}

if (!function_exists('plannerInsertLog')) {
    function plannerInsertLog(PDO $db, int $taskId, ?int $userId, string $action, ?string $oldStatus = null, ?string $newStatus = null, string $note = ''): void {
        $stmt = $db->prepare('INSERT INTO planner_task_logs (task_id, user_id, action, old_status, new_status, note) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$taskId, $userId, $action, $oldStatus, $newStatus, $note]);
    }
}

if (!function_exists('plannerAddComment')) {
    function plannerAddComment(PDO $db, int $taskId, ?int $userId, string $comment): void {
        $stmt = $db->prepare('INSERT INTO planner_task_comments (task_id, user_id, comment) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $userId, $comment]);
    }
}

if (!function_exists('plannerQueryTasks')) {
    function plannerQueryTasks(PDO $db, array $filters, array $admin): array {
        $where = [];
        $params = [];

        foreach (['owner_user_id','assigned_by','status','priority','department'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $where[] = '`' . $field . '` = ?';
                $params[] = $filters[$field];
            }
        }

        if (($filters['date_mode'] ?? '') === 'today') {
            $where[] = 'task_date = ?';
            $params[] = date('Y-m-d');
        } elseif (($filters['date_mode'] ?? '') === 'yesterday') {
            $where[] = 'task_date = ?';
            $params[] = date('Y-m-d', strtotime('-1 day'));
        } elseif (($filters['date_mode'] ?? '') === 'tomorrow') {
            $where[] = 'task_date = ?';
            $params[] = date('Y-m-d', strtotime('+1 day'));
        } elseif (($filters['date_mode'] ?? '') === 'overdue') {
            $where[] = "task_date < ? AND status NOT IN ('done','cancelled')";
            $params[] = date('Y-m-d');
        } elseif (($filters['task_date'] ?? '') !== '') {
            $where[] = 'task_date = ?';
            $params[] = $filters['task_date'];
        }

        plannerApplyVisibilityWhere($where, $params, $admin);

        $sql = 'SELECT * FROM planner_tasks';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY FIELD(priority, "urgent", "high", "normal", "low"), task_date ASC, COALESCE(due_at, CONCAT(task_date, " 23:59:59")) ASC, id DESC LIMIT 200';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('plannerApplyVisibilityWhere')) {
    function plannerApplyVisibilityWhere(array &$where, array &$params, array $admin): void {
        $role = (string)($admin['role'] ?? 'employee');
        $adminId = (int)($admin['id'] ?? 0);
        if ($role === 'super_admin' || $role === 'admin' || hrCan($admin, 'planner_report', ['admin', 'super_admin'])) {
            return;
        }
        if (hrCan($admin, 'tmo_access', []) || hrCan($admin, 'tmo_manage', [])) {
            $where[] = '(owner_user_id = ? OR assigned_by = ? OR linked_objective_id IS NOT NULL OR linked_kr_id IS NOT NULL OR linked_action_id IS NOT NULL)';
            array_push($params, $adminId, $adminId);
            return;
        }
        if (hrCan($admin, 'planner_view_team', ['manager'])) {
            $department = trim((string)($admin['department'] ?? ''));
            if ($department !== '') {
                $where[] = '(owner_user_id = ? OR assigned_by = ? OR department = ?)';
                array_push($params, $adminId, $adminId, $department);
                return;
            }
        }
        $where[] = '(owner_user_id = ? OR assigned_by = ?)';
        array_push($params, $adminId, $adminId);
    }
}
