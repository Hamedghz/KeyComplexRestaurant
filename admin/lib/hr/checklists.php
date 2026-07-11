<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner/planner_adapter.php';
require_once dirname(__DIR__, 3) . '/database/seeds/seed_key_duties.php';
require_once dirname(__DIR__, 3) . '/database/seeds/seed_key_checklists.php';

if (!function_exists('hrChecklistColumnExists')) {
    function hrChecklistColumnExists(PDO $db, string $table, string $column): bool {
        $stmt = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('hrChecklistEnsureSchema')) {
    function hrChecklistEnsureSchema(?PDO $db = null): void {
        $db = $db ?: adminDb();
        keyDutiesEnsureSchema($db);
        keyChecklistsEnsureSchema($db);

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_categories` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_key` varchar(120) NOT NULL,
            `title` varchar(190) NOT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hr_checklist_category_key` (`category_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $dutyColumns = [
            'role_key' => "`role_key` varchar(100) DEFAULT NULL AFTER `duty_code`",
            'department' => "`department` varchar(100) DEFAULT NULL AFTER `role_key`",
            'standard_group' => "`standard_group` varchar(120) DEFAULT NULL AFTER `responsibility_type`",
            'sort_order' => "`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `status`",
            'updated_by' => "`updated_by` int(11) UNSIGNED DEFAULT NULL AFTER `created_by`",
        ];
        foreach ($dutyColumns as $column => $definition) {
            if (!hrChecklistColumnExists($db, 'hr_role_duties', $column)) {
                $db->exec('ALTER TABLE `hr_role_duties` ADD COLUMN ' . $definition);
            }
        }
        try { $db->exec("ALTER TABLE `hr_role_duties` MODIFY `responsibility_type` enum('daily','shift','weekly','monthly','general','as_needed') NOT NULL DEFAULT 'daily'"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE `hr_role_duties` MODIFY `priority` enum('low','normal','medium','high','critical') NOT NULL DEFAULT 'normal'"); } catch (Throwable $e) {}

        $templateColumns = [
            'code' => "`code` varchar(120) DEFAULT NULL AFTER `template_code`",
            'role_key' => "`role_key` varchar(100) DEFAULT NULL AFTER `department`",
            'shift_code' => "`shift_code` varchar(60) DEFAULT NULL AFTER `period_type`",
            'description' => "`description` text DEFAULT NULL AFTER `shift_code`",
            'standard_group' => "`standard_group` varchar(120) DEFAULT NULL AFTER `description`",
            'sort_order' => "`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `status`",
            'created_by' => "`created_by` int(11) UNSIGNED DEFAULT NULL AFTER `sort_order`",
            'updated_by' => "`updated_by` int(11) UNSIGNED DEFAULT NULL AFTER `created_by`",
        ];
        foreach ($templateColumns as $column => $definition) {
            if (!hrChecklistColumnExists($db, 'hr_checklist_templates', $column)) {
                $db->exec('ALTER TABLE `hr_checklist_templates` ADD COLUMN ' . $definition);
            }
        }
        try { $db->exec("ALTER TABLE `hr_checklist_templates` MODIFY `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active'"); } catch (Throwable $e) {}
        try { $db->exec('CREATE UNIQUE INDEX `uniq_hr_checklist_template_code_alias` ON `hr_checklist_templates` (`code`)'); } catch (Throwable $e) {}

        $itemColumns = [
            'description' => "`description` text DEFAULT NULL AFTER `title`",
            'linked_duty_id' => "`linked_duty_id` int(11) UNSIGNED DEFAULT NULL AFTER `has_note`",
            'linked_standard_item_id' => "`linked_standard_item_id` int(11) UNSIGNED DEFAULT NULL AFTER `linked_duty_id`",
        ];
        foreach ($itemColumns as $column => $definition) {
            if (!hrChecklistColumnExists($db, 'hr_checklist_items', $column)) {
                $db->exec('ALTER TABLE `hr_checklist_items` ADD COLUMN ' . $definition);
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_assignments` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `template_id` int(11) UNSIGNED NOT NULL,
            `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
            `assigned_scope_id` varchar(120) DEFAULT NULL,
            `assigned_employee_id` int(11) UNSIGNED DEFAULT NULL,
            `period_id` int(11) UNSIGNED DEFAULT NULL,
            `starts_at` datetime DEFAULT NULL,
            `ends_at` datetime DEFAULT NULL,
            `role_code` varchar(100) DEFAULT NULL,
            `employee_id` int(11) UNSIGNED DEFAULT NULL,
            `assigned_by` int(11) UNSIGNED DEFAULT NULL,
            `due_date` date DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'assigned',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_hr_checklist_assignment_scope` (`assigned_scope_type`, `assigned_scope_id`, `status`),
            KEY `idx_hr_checklist_assignment_owner` (`assigned_employee_id`, `status`, `starts_at`),
            KEY `idx_hr_checklist_assignment_template` (`template_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'assigned_scope_type' => "`assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role' AFTER `template_id`",
            'assigned_scope_id' => "`assigned_scope_id` varchar(120) DEFAULT NULL AFTER `assigned_scope_type`",
            'assigned_employee_id' => "`assigned_employee_id` int(11) UNSIGNED DEFAULT NULL AFTER `assigned_scope_id`",
            'period_id' => "`period_id` int(11) UNSIGNED DEFAULT NULL AFTER `assigned_employee_id`",
            'starts_at' => "`starts_at` datetime DEFAULT NULL AFTER `period_id`",
            'ends_at' => "`ends_at` datetime DEFAULT NULL AFTER `starts_at`",
        ] as $column => $definition) {
            if (!hrChecklistColumnExists($db, 'hr_checklist_assignments', $column)) {
                $db->exec('ALTER TABLE `hr_checklist_assignments` ADD COLUMN ' . $definition);
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_submissions` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `assignment_id` int(11) UNSIGNED NOT NULL,
            `template_id` int(11) UNSIGNED NOT NULL,
            `employee_id` int(11) UNSIGNED NOT NULL,
            `checklist_date` date NOT NULL,
            `shift_code` varchar(60) DEFAULT NULL,
            `completion_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
            `total_quality_score` decimal(8,2) NOT NULL DEFAULT 0.00,
            `answers_json` longtext DEFAULT NULL,
            `manager_id` int(11) UNSIGNED DEFAULT NULL,
            `approval_status` varchar(30) NOT NULL DEFAULT 'pending',
            `approval_notes` text DEFAULT NULL,
            `status` enum('draft','submitted','manager_approved','inspector_approved','rejected') NOT NULL DEFAULT 'draft',
            `submitted_at` datetime DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_hr_checklist_submission_assignment` (`assignment_id`, `status`),
            KEY `idx_hr_checklist_submission_employee` (`employee_id`, `checklist_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'template_id' => "`template_id` int(11) UNSIGNED DEFAULT NULL AFTER `assignment_id`",
            'checklist_date' => "`checklist_date` date DEFAULT NULL AFTER `employee_id`",
            'shift_code' => "`shift_code` varchar(60) DEFAULT NULL AFTER `checklist_date`",
            'completion_percent' => "`completion_percent` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `shift_code`",
            'total_quality_score' => "`total_quality_score` decimal(8,2) NOT NULL DEFAULT 0.00 AFTER `completion_percent`",
            'status' => "`status` enum('draft','submitted','manager_approved','inspector_approved','rejected') NOT NULL DEFAULT 'draft' AFTER `approval_notes`",
        ] as $column => $definition) {
            if (!hrChecklistColumnExists($db, 'hr_checklist_submissions', $column)) {
                $db->exec('ALTER TABLE `hr_checklist_submissions` ADD COLUMN ' . $definition);
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_submission_items` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `submission_id` int(11) UNSIGNED NOT NULL,
            `checklist_item_id` int(11) UNSIGNED NOT NULL,
            `is_done` tinyint(1) NOT NULL DEFAULT 0,
            `quality_score` decimal(6,2) DEFAULT NULL,
            `note` text DEFAULT NULL,
            `issue_flag` tinyint(1) NOT NULL DEFAULT 0,
            `corrective_task_id` int(11) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hr_submission_item` (`submission_id`, `checklist_item_id`),
            KEY `idx_hr_submission_item_issue` (`issue_flag`, `corrective_task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_approvals` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `submission_id` int(11) UNSIGNED NOT NULL,
            `approver_id` int(11) UNSIGNED NOT NULL,
            `approval_type` enum('manager','inspector') NOT NULL,
            `status` enum('approved','rejected') NOT NULL,
            `note` text DEFAULT NULL,
            `approved_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_hr_checklist_approval_submission` (`submission_id`, `approval_type`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('hrChecklistFetchAll')) {
    function hrChecklistFetchAll(PDO $db, string $sql, array $params = []): array {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            safeAdminLog('HR checklist query failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('hrChecklistFetchTemplates')) {
    function hrChecklistFetchTemplates(PDO $db): array {
        return hrChecklistFetchAll($db, 'SELECT * FROM hr_checklist_templates WHERE status != "archived" ORDER BY sort_order ASC, id DESC');
    }
}

if (!function_exists('hrChecklistFetchItems')) {
    function hrChecklistFetchItems(PDO $db, int $templateId): array {
        return hrChecklistFetchAll($db, 'SELECT * FROM hr_checklist_items WHERE template_id = ? AND status = "active" ORDER BY sort_order ASC, id ASC', [$templateId]);
    }
}

if (!function_exists('hrChecklistCurrentAdminId')) {
    function hrChecklistCurrentAdminId(array $admin): int {
        return max(0, (int)($admin['id'] ?? 0));
    }
}

if (!function_exists('hrChecklistHandleDutiesPost')) {
    function hrChecklistHandleDutiesPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'save_duty') return '';
        $title = trim((string)($post['title'] ?? ''));
        if ($title === '') return 'عنوان شرح وظیفه الزامی است.';
        $id = (int)($post['id'] ?? 0);
        $role = trim((string)($post['role_key'] ?? ''));
        $department = trim((string)($post['department'] ?? ''));
        $data = [
            $role, $role, $department, $title, trim((string)($post['description'] ?? '')),
            (string)($post['responsibility_type'] ?? 'general'), trim((string)($post['standard_group'] ?? '')) ?: null,
            (string)($post['priority'] ?? 'normal'), (string)($post['status'] ?? 'active'),
            (int)($post['sort_order'] ?? 0), hrChecklistCurrentAdminId($admin), hrChecklistCurrentAdminId($admin),
        ];
        if ($id > 0) {
            $stmt = $db->prepare('UPDATE hr_role_duties SET role_key=?, role_code=?, department=?, title=?, description=?, responsibility_type=?, standard_group=?, priority=?, status=?, sort_order=?, updated_by=? WHERE id=?');
            $stmt->execute([$role, $role, $department, $title, $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], hrChecklistCurrentAdminId($admin), $id]);
            return 'شرح وظیفه به‌روزرسانی شد.';
        }
        $dutyCode = 'manual_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $role . '_' . substr(sha1($title), 0, 10));
        $stmt = $db->prepare('INSERT INTO hr_role_duties (duty_code, role_key, role_code, department, title, description, responsibility_type, standard_group, priority, status, sort_order, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$dutyCode, ...$data]);
        return 'شرح وظیفه ثبت شد.';
    }
}

if (!function_exists('hrChecklistHandleTemplatePost')) {
    function hrChecklistHandleTemplatePost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        $action = (string)($post['action'] ?? '');
        if ($action === 'save_template') {
            $title = trim((string)($post['title'] ?? ''));
            $code = trim((string)($post['code'] ?? ''));
            if ($title === '' || $code === '') return 'عنوان و کد قالب الزامی است.';
            $id = (int)($post['id'] ?? 0);
            $values = [
                $code, $code, $code, $title, trim((string)($post['department'] ?? '')),
                trim((string)($post['role_key'] ?? '')) ?: null, trim((string)($post['role_key'] ?? '')) ?: null,
                (string)($post['period_type'] ?? 'daily'), trim((string)($post['shift_code'] ?? '')) ?: null,
                trim((string)($post['description'] ?? '')) ?: null, trim((string)($post['standard_group'] ?? '')) ?: null,
                !empty($post['requires_manager_approval']) ? 1 : 0,
                !empty($post['requires_inspector_approval']) ? 1 : 0,
                (string)($post['status'] ?? 'active'), (int)($post['sort_order'] ?? 0),
                hrChecklistCurrentAdminId($admin), hrChecklistCurrentAdminId($admin),
            ];
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE hr_checklist_templates SET template_key=?, template_code=?, code=?, title=?, department=?, role_code=?, role_key=?, period_type=?, shift_code=?, description=?, standard_group=?, requires_manager_approval=?, requires_inspector_approval=?, status=?, sort_order=?, updated_by=? WHERE id=?');
                $stmt->execute(array_merge(array_slice($values, 0, 15), [hrChecklistCurrentAdminId($admin), $id]));
                return 'قالب چک‌لیست به‌روزرسانی شد.';
            }
            $stmt = $db->prepare('INSERT INTO hr_checklist_templates (template_key, template_code, code, title, department, role_code, role_key, period_type, shift_code, description, standard_group, requires_manager_approval, requires_inspector_approval, status, sort_order, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute($values);
            return 'قالب چک‌لیست ثبت شد.';
        }
        if ($action === 'save_item') {
            $templateId = (int)($post['template_id'] ?? 0);
            $title = trim((string)($post['title'] ?? ''));
            if ($templateId <= 0 || $title === '') return 'قالب و عنوان آیتم الزامی است.';
            $template = hrChecklistFetchAll($db, 'SELECT * FROM hr_checklist_templates WHERE id=?', [$templateId])[0] ?? null;
            if (!$template) return 'قالب یافت نشد.';
            $code = trim((string)($post['item_code'] ?? '')) ?: ('manual_' . substr(sha1($title), 0, 10));
            $stmt = $db->prepare('INSERT INTO hr_checklist_items (template_id, template_code, item_code, title, description, phase, is_required, has_quality_score, max_quality_score, has_note, linked_duty_id, linked_standard_item_id, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"active") ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),phase=VALUES(phase),is_required=VALUES(is_required),has_quality_score=VALUES(has_quality_score),max_quality_score=VALUES(max_quality_score),has_note=VALUES(has_note),linked_duty_id=VALUES(linked_duty_id),linked_standard_item_id=VALUES(linked_standard_item_id),sort_order=VALUES(sort_order),status="active"');
            $stmt->execute([
                $templateId, (string)($template['template_code'] ?: $template['template_key']), $code, $title,
                trim((string)($post['description'] ?? '')) ?: null, (string)($post['phase'] ?? 'during_shift'),
                !empty($post['is_required']) ? 1 : 0, !empty($post['has_quality_score']) ? 1 : 0,
                ($post['max_quality_score'] ?? '') !== '' ? (int)$post['max_quality_score'] : null,
                !empty($post['has_note']) ? 1 : 0,
                ($post['linked_duty_id'] ?? '') !== '' ? (int)$post['linked_duty_id'] : null,
                ($post['linked_standard_item_id'] ?? '') !== '' ? (int)$post['linked_standard_item_id'] : null,
                (int)($post['sort_order'] ?? 0),
            ]);
            return 'آیتم چک‌لیست ثبت شد.';
        }
        return '';
    }
}

if (!function_exists('hrChecklistHandleAssignmentPost')) {
    function hrChecklistHandleAssignmentPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'assign') return '';
        $templateId = (int)($post['template_id'] ?? 0);
        if ($templateId <= 0) return 'قالب چک‌لیست الزامی است.';
        $scopeType = (string)($post['assigned_scope_type'] ?? 'role');
        $scopeId = trim((string)($post['assigned_scope_id'] ?? '')) ?: null;
        $employeeId = ($post['assigned_employee_id'] ?? '') !== '' ? (int)$post['assigned_employee_id'] : null;
        $startsAt = trim((string)($post['starts_at'] ?? '')) ?: date('Y-m-d 00:00:00');
        $endsAt = trim((string)($post['ends_at'] ?? '')) ?: null;
        $stmt = $db->prepare('INSERT INTO hr_checklist_assignments (template_id, assigned_scope_type, assigned_scope_id, assigned_employee_id, period_id, starts_at, ends_at, role_code, employee_id, assigned_by, due_date, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,"assigned")');
        $stmt->execute([$templateId, $scopeType, $scopeId, $employeeId, ($post['period_id'] ?? '') !== '' ? (int)$post['period_id'] : null, $startsAt, $endsAt, $scopeType === 'role' ? $scopeId : null, $employeeId, hrChecklistCurrentAdminId($admin), substr($startsAt, 0, 10)]);
        return 'چک‌لیست تخصیص داده شد.';
    }
}

if (!function_exists('hrChecklistHandleSubmissionPost')) {
    function hrChecklistHandleSubmissionPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'submit_checklist') return '';
        $assignmentId = (int)($post['assignment_id'] ?? 0);
        $assignment = hrChecklistFetchAll($db, 'SELECT a.*, t.title AS template_title FROM hr_checklist_assignments a JOIN hr_checklist_templates t ON t.id=a.template_id WHERE a.id=?', [$assignmentId])[0] ?? null;
        if (!$assignment) return 'تخصیص یافت نشد.';
        $items = hrChecklistFetchItems($db, (int)$assignment['template_id']);
        if (!$items) return 'آیتمی برای این قالب وجود ندارد.';
        $doneCount = 0;
        $qualityTotal = 0;
        $qualityCount = 0;
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            if (!empty($post['done'][$itemId])) $doneCount++;
            if (($post['quality'][$itemId] ?? '') !== '') {
                $qualityTotal += (float)$post['quality'][$itemId];
                $qualityCount++;
            }
        }
        $completion = $items ? round(($doneCount / count($items)) * 100, 2) : 0;
        $qualityScore = $qualityCount ? round($qualityTotal / $qualityCount, 2) : 0;
        $status = (string)($post['submission_status'] ?? 'submitted');
        $submittedAt = $status === 'submitted' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare('INSERT INTO hr_checklist_submissions (assignment_id, template_id, employee_id, checklist_date, shift_code, completion_percent, total_quality_score, status, submitted_at, answers_json, approval_status) VALUES (?,?,?,?,?,?,?,?,?,?,"pending")');
        $stmt->execute([$assignmentId, (int)$assignment['template_id'], hrChecklistCurrentAdminId($admin), (string)($post['checklist_date'] ?? date('Y-m-d')), trim((string)($post['shift_code'] ?? '')) ?: null, $completion, $qualityScore, $status, $submittedAt, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $submissionId = (int)$db->lastInsertId();

        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $issue = !empty($post['issue'][$itemId]) ? 1 : 0;
            $taskId = null;
            if ($issue && !empty($item['can_create_planner_task'])) {
                try {
                    $taskId = plannerCreateLinkedTask($db, [
                        'title' => 'اقدام اصلاحی: ' . $item['title'],
                        'description' => (string)($post['note'][$itemId] ?? ''),
                        'owner_user_id' => hrChecklistCurrentAdminId($admin),
                        'assigned_by' => hrChecklistCurrentAdminId($admin),
                        'department' => (string)($assignment['assigned_scope_type'] === 'department' ? $assignment['assigned_scope_id'] : ''),
                        'task_date' => date('Y-m-d', strtotime('+1 day')),
                        'priority' => 'high',
                        'source_module' => 'checklist',
                        'source_entity_type' => 'checklist_issue',
                        'source_entity_id' => $submissionId,
                        'linked_checklist_item_id' => $itemId,
                    ], $admin);
                } catch (Throwable $e) {
                    safeAdminLog('Checklist corrective planner task failed: ' . $e->getMessage());
                }
            }
            $stmtItem = $db->prepare('INSERT INTO hr_checklist_submission_items (submission_id, checklist_item_id, is_done, quality_score, note, issue_flag, corrective_task_id) VALUES (?,?,?,?,?,?,?)');
            $stmtItem->execute([$submissionId, $itemId, !empty($post['done'][$itemId]) ? 1 : 0, ($post['quality'][$itemId] ?? '') !== '' ? (float)$post['quality'][$itemId] : null, trim((string)($post['note'][$itemId] ?? '')) ?: null, $issue, $taskId]);
        }
        return 'چک‌لیست ثبت شد.';
    }
}

if (!function_exists('hrChecklistHandleApprovalPost')) {
    function hrChecklistHandleApprovalPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'approve') return '';
        $submissionId = (int)($post['submission_id'] ?? 0);
        $approvalType = (string)($post['approval_type'] ?? 'manager');
        $status = (string)($post['approval_status'] ?? 'approved');
        $stmt = $db->prepare('INSERT INTO hr_checklist_approvals (submission_id, approver_id, approval_type, status, note, approved_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->execute([$submissionId, hrChecklistCurrentAdminId($admin), $approvalType, $status, trim((string)($post['note'] ?? '')) ?: null]);
        $newStatus = $status === 'rejected' ? 'rejected' : ($approvalType === 'inspector' ? 'inspector_approved' : 'manager_approved');
        $db->prepare('UPDATE hr_checklist_submissions SET status=?, approval_status=?, approved_at=NOW() WHERE id=?')->execute([$newStatus, $status, $submissionId]);
        return 'وضعیت تایید ثبت شد.';
    }
}
