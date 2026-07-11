<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner/planner_adapter.php';
require_once dirname(__DIR__, 3) . '/database/seeds/seed_key_kpis.php';

if (!function_exists('hrKpiColumnExists')) {
    function hrKpiColumnExists(PDO $db, string $table, string $column): bool {
        $stmt = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('hrKpiEnsureSchema')) {
    function hrKpiEnsureSchema(?PDO $db = null): void {
        $db = $db ?: adminDb();
        keyKpisEnsureSchema($db);
        $definitionColumns = [
            'code' => "`code` varchar(120) DEFAULT NULL AFTER `kpi_code`",
            'role_key' => "`role_key` varchar(100) DEFAULT NULL AFTER `role_code`",
            'standard_group' => "`standard_group` varchar(120) DEFAULT NULL AFTER `role_key`",
            'min_value' => "`min_value` decimal(14,4) DEFAULT NULL AFTER `target_value`",
            'max_value' => "`max_value` decimal(14,4) DEFAULT NULL AFTER `min_value`",
            'max_score_percent' => "`max_score_percent` decimal(8,2) NOT NULL DEFAULT 100.00 AFTER `rag_yellow_threshold`",
            'created_by' => "`created_by` int(11) UNSIGNED DEFAULT NULL AFTER `status`",
            'updated_by' => "`updated_by` int(11) UNSIGNED DEFAULT NULL AFTER `created_by`",
        ];
        foreach ($definitionColumns as $column => $definition) {
            if (!hrKpiColumnExists($db, 'hr_kpi_definitions', $column)) {
                $db->exec('ALTER TABLE `hr_kpi_definitions` ADD COLUMN ' . $definition);
            }
        }
        try { $db->exec('CREATE UNIQUE INDEX `uniq_hr_kpi_definition_code_alias` ON `hr_kpi_definitions` (`code`)'); } catch (Throwable $e) {}

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_kpi_assignments` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `kpi_id` int(11) UNSIGNED NOT NULL,
            `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
            `assigned_scope_id` varchar(120) DEFAULT NULL,
            `employee_id` int(11) UNSIGNED DEFAULT NULL,
            `period_id` int(11) UNSIGNED DEFAULT NULL,
            `period_month` char(7) DEFAULT NULL,
            `assigned_by` int(11) UNSIGNED DEFAULT NULL,
            `role_code` varchar(100) DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_hr_kpi_assignment_scope` (`assigned_scope_type`, `assigned_scope_id`, `period_id`, `status`),
            KEY `idx_hr_kpi_assignment_owner` (`employee_id`, `period_id`, `status`),
            KEY `idx_hr_kpi_assignment_kpi` (`kpi_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'assigned_scope_type' => "`assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role' AFTER `kpi_id`",
            'assigned_scope_id' => "`assigned_scope_id` varchar(120) DEFAULT NULL AFTER `assigned_scope_type`",
            'period_id' => "`period_id` int(11) UNSIGNED DEFAULT NULL AFTER `employee_id`",
        ] as $column => $definition) {
            if (!hrKpiColumnExists($db, 'hr_kpi_assignments', $column)) {
                $db->exec('ALTER TABLE `hr_kpi_assignments` ADD COLUMN ' . $definition);
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_kpi_entries` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `assignment_id` int(11) UNSIGNED NOT NULL,
            `kpi_id` int(11) UNSIGNED NOT NULL,
            `employee_id` int(11) UNSIGNED DEFAULT NULL,
            `period_id` int(11) UNSIGNED DEFAULT NULL,
            `entry_date` date DEFAULT NULL,
            `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
            `manual_score` decimal(8,2) DEFAULT NULL,
            `score_value` decimal(6,2) DEFAULT NULL,
            `note` text DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `entered_by` int(11) UNSIGNED DEFAULT NULL,
            `entered_at` datetime NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_hr_kpi_entry_assignment` (`assignment_id`, `entry_date`),
            KEY `idx_hr_kpi_entry_period` (`period_id`, `employee_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'kpi_id' => "`kpi_id` int(11) UNSIGNED DEFAULT NULL AFTER `assignment_id`",
            'employee_id' => "`employee_id` int(11) UNSIGNED DEFAULT NULL AFTER `kpi_id`",
            'period_id' => "`period_id` int(11) UNSIGNED DEFAULT NULL AFTER `employee_id`",
            'manual_score' => "`manual_score` decimal(8,2) DEFAULT NULL AFTER `actual_value`",
            'note' => "`note` text DEFAULT NULL AFTER `manual_score`",
            'entered_at' => "`entered_at` datetime DEFAULT NULL AFTER `entered_by`",
        ] as $column => $definition) {
            if (!hrKpiColumnExists($db, 'hr_kpi_entries', $column)) {
                $db->exec('ALTER TABLE `hr_kpi_entries` ADD COLUMN ' . $definition);
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_kpi_scores` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `assignment_id` int(11) UNSIGNED NOT NULL,
            `kpi_id` int(11) UNSIGNED NOT NULL,
            `employee_id` int(11) UNSIGNED DEFAULT NULL,
            `period_id` int(11) UNSIGNED DEFAULT NULL,
            `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
            `target_value` decimal(14,4) DEFAULT NULL,
            `score_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
            `weighted_score` decimal(8,2) NOT NULL DEFAULT 0.00,
            `rag_status` enum('green','yellow','red') NOT NULL DEFAULT 'red',
            `calculated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_hr_kpi_score_assignment` (`assignment_id`, `period_id`),
            KEY `idx_hr_kpi_score_rag` (`rag_status`, `calculated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_kpi_corrective_actions` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `kpi_score_id` int(11) UNSIGNED NOT NULL,
            `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
            `title` varchar(190) NOT NULL,
            `description` text DEFAULT NULL,
            `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
            `due_date` date DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'open',
            PRIMARY KEY (`id`),
            KEY `idx_hr_kpi_corrective_score` (`kpi_score_id`, `status`),
            KEY `idx_hr_kpi_corrective_task` (`planner_task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('hrKpiScorePercent')) {
    function hrKpiScorePercent(float $actual, ?float $target, string $direction, string $calculationType, ?float $manualScore, float $maxScorePercent = 100.0): float {
        if ($calculationType === 'manual_score' && $manualScore !== null) {
            return max(0.0, min($maxScorePercent, $manualScore));
        }
        if (!$target || $target == 0.0) {
            return 0.0;
        }
        if ($direction === 'negative') {
            if ($actual <= 0.0) return $maxScorePercent;
            $score = ($target / $actual) * 100;
        } else {
            $score = ($actual / $target) * 100;
        }
        return round(max(0.0, min($maxScorePercent, $score)), 2);
    }
}

if (!function_exists('hrKpiRagStatus')) {
    function hrKpiRagStatus(float $scorePercent, ?float $green = null, ?float $yellow = null): string {
        $green = $green ?? 90.0;
        $yellow = $yellow ?? 70.0;
        if ($scorePercent >= $green) return 'green';
        if ($scorePercent >= $yellow) return 'yellow';
        return 'red';
    }
}

if (!function_exists('hrKpiFetchAll')) {
    function hrKpiFetchAll(PDO $db, string $sql, array $params = []): array {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            safeAdminLog('HR KPI query failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('hrKpiCalculateFromEntry')) {
    function hrKpiCalculateFromEntry(PDO $db, array $entry, array $admin): ?int {
        $rows = hrKpiFetchAll($db, 'SELECT a.*, k.* FROM hr_kpi_assignments a JOIN hr_kpi_definitions k ON k.id=a.kpi_id WHERE a.id=? LIMIT 1', [(int)$entry['assignment_id']]);
        if (!$rows) return null;
        $def = $rows[0];
        $actual = (float)$entry['actual_value'];
        $target = $def['target_value'] !== null ? (float)$def['target_value'] : null;
        $manual = $entry['manual_score'] !== null ? (float)$entry['manual_score'] : null;
        $scorePercent = hrKpiScorePercent($actual, $target, (string)$def['direction'], (string)$def['calculation_type'], $manual, (float)($def['max_score_percent'] ?? 100));
        $weighted = round($scorePercent * ((float)$def['weight']) / 100, 2);
        $rag = hrKpiRagStatus($scorePercent, $def['rag_green_threshold'] !== null ? (float)$def['rag_green_threshold'] : null, $def['rag_yellow_threshold'] !== null ? (float)$def['rag_yellow_threshold'] : null);
        $stmt = $db->prepare('INSERT INTO hr_kpi_scores (assignment_id,kpi_id,employee_id,period_id,actual_value,target_value,score_percent,weighted_score,rag_status,calculated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([(int)$entry['assignment_id'], (int)$entry['kpi_id'], $entry['employee_id'] !== null ? (int)$entry['employee_id'] : null, $entry['period_id'] !== null ? (int)$entry['period_id'] : null, $actual, $target, $scorePercent, $weighted, $rag]);
        $scoreId = (int)$db->lastInsertId();
        if ($rag === 'red') {
            hrKpiCreateCorrectiveAction($db, $scoreId, $def, $entry, $admin);
        }
        return $scoreId;
    }
}

if (!function_exists('hrKpiCreateCorrectiveAction')) {
    function hrKpiCreateCorrectiveAction(PDO $db, int $scoreId, array $definition, array $entry, array $admin): void {
        $owner = (int)($entry['employee_id'] ?: ($admin['id'] ?? 0));
        $title = 'اقدام اصلاحی KPI: ' . (string)$definition['title'];
        $description = 'امتیاز KPI در محدوده قرمز قرار گرفت. مقدار واقعی: ' . (string)$entry['actual_value'];
        $taskId = null;
        try {
            $taskId = plannerCreateKpiCorrectiveTask($db, [
                'title' => $title,
                'description' => $description,
                'owner_user_id' => $owner,
                'assigned_by' => (int)($admin['id'] ?? 0),
                'department' => (string)($definition['department'] ?? ''),
                'task_date' => date('Y-m-d', strtotime('+1 day')),
                'priority' => 'high',
                'linked_kpi_score_id' => $scoreId,
            ], $admin);
        } catch (Throwable $e) {
            safeAdminLog('KPI corrective planner task failed: ' . $e->getMessage());
        }
        $stmt = $db->prepare('INSERT INTO hr_kpi_corrective_actions (kpi_score_id, planner_task_id, title, description, owner_user_id, due_date, status) VALUES (?,?,?,?,?,?, "open")');
        $stmt->execute([$scoreId, $taskId, $title, $description, $owner ?: null, date('Y-m-d', strtotime('+1 day'))]);
    }
}

if (!function_exists('hrKpiHandleDefinitionPost')) {
    function hrKpiHandleDefinitionPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'save_kpi') return '';
        $title = trim((string)($post['title'] ?? ''));
        $code = trim((string)($post['code'] ?? ''));
        if ($title === '' || $code === '') return 'عنوان و کد KPI الزامی است.';
        $stmt = $db->prepare('INSERT INTO hr_kpi_definitions (kpi_key,kpi_code,code,title,department,role_code,role_key,standard_group,category,unit,unit_label,target_value,min_value,max_value,weight,direction,calculation_type,rag_green_threshold,rag_yellow_threshold,max_score_percent,description,status,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),department=VALUES(department),role_code=VALUES(role_code),role_key=VALUES(role_key),standard_group=VALUES(standard_group),unit=VALUES(unit),unit_label=VALUES(unit_label),target_value=VALUES(target_value),min_value=VALUES(min_value),max_value=VALUES(max_value),weight=VALUES(weight),direction=VALUES(direction),calculation_type=VALUES(calculation_type),rag_green_threshold=VALUES(rag_green_threshold),rag_yellow_threshold=VALUES(rag_yellow_threshold),max_score_percent=VALUES(max_score_percent),description=VALUES(description),updated_by=VALUES(updated_by),status="active"');
        $stmt->execute([
            $code, $code, $code, $title, trim((string)($post['department'] ?? '')) ?: null,
            trim((string)($post['role_key'] ?? '')) ?: null, trim((string)($post['role_key'] ?? '')) ?: null,
            trim((string)($post['standard_group'] ?? '')) ?: null, trim((string)($post['department'] ?? 'performance')) ?: 'performance',
            trim((string)($post['unit_label'] ?? '')) ?: null, trim((string)($post['unit_label'] ?? '')) ?: null,
            ($post['target_value'] ?? '') !== '' ? (float)$post['target_value'] : null,
            ($post['min_value'] ?? '') !== '' ? (float)$post['min_value'] : null,
            ($post['max_value'] ?? '') !== '' ? (float)$post['max_value'] : null,
            (float)($post['weight'] ?? 0), (string)($post['direction'] ?? 'positive'),
            (string)($post['calculation_type'] ?? 'simple_percent'),
            ($post['rag_green_threshold'] ?? '') !== '' ? (float)$post['rag_green_threshold'] : 90,
            ($post['rag_yellow_threshold'] ?? '') !== '' ? (float)$post['rag_yellow_threshold'] : 70,
            ($post['max_score_percent'] ?? '') !== '' ? (float)$post['max_score_percent'] : 100,
            trim((string)($post['description'] ?? '')) ?: null,
            (int)($admin['id'] ?? 0), (int)($admin['id'] ?? 0),
        ]);
        return 'KPI ذخیره شد.';
    }
}

if (!function_exists('hrKpiHandleAssignmentPost')) {
    function hrKpiHandleAssignmentPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'assign_kpi') return '';
        $kpiId = (int)($post['kpi_id'] ?? 0);
        if ($kpiId <= 0) return 'KPI الزامی است.';
        $scope = (string)($post['assigned_scope_type'] ?? 'role');
        $scopeId = trim((string)($post['assigned_scope_id'] ?? '')) ?: null;
        $employee = ($post['employee_id'] ?? '') !== '' ? (int)$post['employee_id'] : null;
        $stmt = $db->prepare('INSERT INTO hr_kpi_assignments (kpi_id,assigned_scope_type,assigned_scope_id,employee_id,period_id,period_month,assigned_by,role_code,status) VALUES (?,?,?,?,?,?,?,?, "active")');
        $stmt->execute([$kpiId, $scope, $scopeId, $employee, ($post['period_id'] ?? '') !== '' ? (int)$post['period_id'] : null, trim((string)($post['period_month'] ?? date('Y-m'))) ?: date('Y-m'), (int)($admin['id'] ?? 0), $scope === 'role' ? $scopeId : null]);
        return 'KPI تخصیص داده شد.';
    }
}

if (!function_exists('hrKpiHandleEntryPost')) {
    function hrKpiHandleEntryPost(PDO $db, array $admin, array $post): string {
        if (!verifyRequestCsrf()) return 'درخواست نامعتبر است.';
        if (($post['action'] ?? '') !== 'save_entry') return '';
        $assignmentId = (int)($post['assignment_id'] ?? 0);
        $rows = hrKpiFetchAll($db, 'SELECT * FROM hr_kpi_assignments WHERE id=? LIMIT 1', [$assignmentId]);
        if (!$rows) return 'تخصیص یافت نشد.';
        $assignment = $rows[0];
        $employeeId = ($post['employee_id'] ?? '') !== '' ? (int)$post['employee_id'] : ((int)($assignment['employee_id'] ?? 0) ?: (int)($admin['id'] ?? 0));
        $stmt = $db->prepare('INSERT INTO hr_kpi_entries (assignment_id,kpi_id,employee_id,period_id,entry_date,actual_value,manual_score,score_value,note,notes,entered_by,entered_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$assignmentId, (int)$assignment['kpi_id'], $employeeId, $assignment['period_id'] !== null ? (int)$assignment['period_id'] : null, date('Y-m-d'), (float)($post['actual_value'] ?? 0), ($post['manual_score'] ?? '') !== '' ? (float)$post['manual_score'] : null, null, trim((string)($post['note'] ?? '')) ?: null, trim((string)($post['note'] ?? '')) ?: null, (int)($admin['id'] ?? 0)]);
        $entry = [
            'assignment_id' => $assignmentId,
            'kpi_id' => (int)$assignment['kpi_id'],
            'employee_id' => $employeeId,
            'period_id' => $assignment['period_id'],
            'actual_value' => (float)($post['actual_value'] ?? 0),
            'manual_score' => ($post['manual_score'] ?? '') !== '' ? (float)$post['manual_score'] : null,
        ];
        hrKpiCalculateFromEntry($db, $entry, $admin);
        return 'مقدار KPI ثبت و امتیاز محاسبه شد.';
    }
}
