<?php
require_once __DIR__ . '/admin_schema.php';
require_once ROOT_PATH . '/database/seeds/seed_restaurant_hr_tests.php';

function hrEvaluationInputTypes(): array {
    return [
        'numeric' => 'امتیاز عددی',
        'rating' => 'مقیاس رتبه بندی',
        'yes_no' => 'بله / خیر',
        'multiple_choice' => 'چند گزینه ای',
        'text' => 'یادداشت متنی',
        'kpi' => 'KPI / مقدار دستی',
        'automatic' => 'سیستمی / خودکار',
    ];
}

function hrEvaluationPeriodTypes(): array {
    return [
        'monthly' => 'ماهانه',
        'quarterly' => 'فصلی',
        'yearly' => 'سالانه',
        'probation' => 'دوره آزمایشی',
        'training_completion' => 'پایان آموزش',
        'hiring' => 'استخدام / پیش از استخدام',
        'custom' => 'سفارشی',
    ];
}

function hrEvaluationStatuses(): array {
    return [
        'draft' => 'پیش نویس',
        'active' => 'فعال',
        'closed' => 'بسته',
        'archived' => 'آرشیو',
    ];
}

function hrEvaluationFormTypes(): array {
    return [
        'employee_performance' => 'عملکرد کارمند',
        'peer' => 'ارزیابی همکار',
        'manager' => 'ارزیابی مدیر',
        'self' => 'خودارزیابی',
        'organizational_assessment' => 'سنجش سازمانی',
        'job_test' => 'آزمون شغلی',
        'external_catalog' => 'کاتالوگ آزمون خارجی',
    ];
}

function hrVisibilityLevels(): array {
    return [
        'private' => 'خصوصی HR',
        'manager' => 'مدیران',
        'employee' => 'قابل مشاهده برای کارمند',
    ];
}

function hrAssessmentCategories(): array {
    return [
        'behavioral' => 'رفتاری و سازمانی',
        'skills' => 'مهارتی رستوران',
        'knowledge' => 'دانش شغلی و ایمنی',
        'organizational' => 'نگرش سازمانی',
        'personality' => 'شخصیت و رفتار',
        'commitment' => 'تعهد سازمانی',
        'satisfaction' => 'رضایت شغلی',
        'intelligence' => 'توانایی شناختی',
        'stress' => 'استرس و فرسودگی',
        'strengths' => 'نقاط قوت',
        'vocational' => 'علایق شغلی',
        'spatial' => 'تجسم فضایی',
        'other' => 'سایر',
    ];
}

function hrAssessmentScoringMethods(): array {
    return [
        'manual' => 'ورود دستی نتیجه',
        'calculated' => 'محاسبه داخلی',
        'external' => 'نتیجه از منبع خارجی',
    ];
}

function hrTestAnswerTypes(): array {
    return [
        'scale_5' => 'مقیاس ۱ تا ۵',
        'likert_5' => 'لیکرت پنج گزینه‌ای',
        'scale_7' => 'مقیاس ۱ تا ۷',
        'yes_no' => 'بله / خیر',
        'true_false' => 'درست / غلط',
        'single_choice' => 'تک گزینه‌ای',
        'multiple_choice' => 'چند گزینه ای',
        'multi_choice' => 'چند انتخابی',
        'scenario' => 'سناریویی',
        'numeric' => 'عددی',
        'text' => 'متنی',
    ];
}

function hrSetting(PDO $db, string $key, $default = null) {
    if (!adminTableExists($db, 'settings')) {
        return $default;
    }
    try {
        $stmt = $db->prepare('SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }
        if (($row['setting_type'] ?? '') === 'boolean') {
            return in_array((string)$row['setting_value'], ['1', 'true', 'yes', 'on'], true);
        }
        if (($row['setting_type'] ?? '') === 'json') {
            $decoded = json_decode((string)$row['setting_value'], true);
            return is_array($decoded) ? $decoded : $default;
        }
        return $row['setting_value'];
    } catch (Throwable $e) {
        safeAdminLog('HR setting lookup failed: ' . $e->getMessage());
        return $default;
    }
}

function hrJsonDecode($value): array {
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function hrJsonEncode(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function hrClampScore($value, float $min = 0, float $max = 100): float {
    return max($min, min($max, (float)$value));
}

function hrAssessmentDisclaimer(): string {
    return 'این نتیجه برای تحلیل سازمانی، آموزشی و مدیریتی استفاده می‌شود و جایگزین ارزیابی تخصصی، پزشکی یا روان‌شناسی نیست.';
}

function hrTestAudit(PDO $db, string $action, string $entityType, ?int $entityId, array $context = [], ?int $actorId = null): void {
    try {
        if (!adminTableExists($db, 'hr_test_audit_logs')) {
            return;
        }
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $description = trim((string)($context['description'] ?? ''));
        unset($context['description']);
        $stmt = $db->prepare('INSERT INTO hr_test_audit_logs (actor_id,action,entity_type,entity_id,description,context_json,ip_hash) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $actorId ?: (int)($_SESSION['admin_id'] ?? 0) ?: null,
            substr($action, 0, 80),
            substr($entityType, 0, 80),
            $entityId ?: null,
            $description !== '' ? substr($description, 0, 500) : null,
            $context ? hrJsonEncode($context) : null,
            $ip !== '' ? hash('sha256', $ip . '|' . (string)hrSetting($db, 'app_key', 'key-restaurant')) : null,
        ]);
    } catch (Throwable $e) {
        safeAdminLog('HR test audit write failed: ' . $e->getMessage());
    }
}

function hrEnsureProfessionalTestSchema(PDO $db): void {
    $migration = ROOT_PATH . '/database/migrations/2026_06_30_professional_hr_tests.sql';
    if (is_readable($migration)) {
        $sql = (string)file_get_contents($migration);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?: $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $db->exec($statement);
            } catch (Throwable $e) {
                safeAdminLog('Professional HR schema statement failed: ' . $e->getMessage());
            }
        }
    }

    ensureTableColumns('hr_assessment_tests', [
        'category_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `category`',
        'analysis_type' => "varchar(60) NOT NULL DEFAULT 'positive' AFTER `scoring_method_type`",
        'show_disclaimer' => 'tinyint(1) NOT NULL DEFAULT 1 AFTER `allow_retake`',
        'created_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `show_disclaimer`',
        'updated_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `created_by`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_dimensions', [
        'status' => "varchar(30) NOT NULL DEFAULT 'active' AFTER `negative_label`",
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
        'created_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `updated_at`',
        'updated_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `created_by`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_by`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_questions', [
        'question_type' => "varchar(40) DEFAULT NULL AFTER `answer_type`",
        'score_direction' => "varchar(20) DEFAULT NULL AFTER `scoring_direction`",
        'is_reverse_scored' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `score_direction`',
        'is_required' => 'tinyint(1) NOT NULL DEFAULT 1 AFTER `is_reverse_scored`',
        'is_critical' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `is_required`',
        'role_visibility' => 'varchar(500) DEFAULT NULL AFTER `is_critical`',
        'status' => "varchar(30) NOT NULL DEFAULT 'active' AFTER `is_active`",
        'created_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `updated_at`',
        'updated_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `created_by`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_by`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_assignments', [
        'target_type' => "varchar(40) NOT NULL DEFAULT 'employee' AFTER `test_id`",
        'target_id' => 'varchar(120) DEFAULT NULL AFTER `target_type`',
        'branch_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `role`',
        'shift_code' => 'varchar(100) DEFAULT NULL AFTER `branch_id`',
        'manager_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `shift_code`',
        'max_attempts' => 'int(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `allow_retake`',
        'show_result_to_employee' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `max_attempts`',
        'description' => 'text DEFAULT NULL AFTER `show_result_to_employee`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_responses', [
        'attempt_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `assignment_id`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
}

function hrPerformanceSourceWeights(PDO $db): array {
    $defaults = ['manager' => 35, 'peer' => 25, 'attendance' => 20, 'department_kpi' => 20];
    $configured = hrSetting($db, 'hr_performance_source_weights', $defaults);
    if (!is_array($configured)) {
        return $defaults;
    }
    $weights = [];
    foreach ($defaults as $key => $default) {
        $weights[$key] = max(0.0, (float)($configured[$key] ?? $default));
    }
    $sum = array_sum($weights);
    return $sum > 0 ? $weights : $defaults;
}

function hrCanOverrideClosedPeriod(array $admin): bool {
    return adminPermissionAllows($admin, 'employee_closed_period_override', ['super_admin']);
}

function hrCanEditPeriod(array $period, array $admin): bool {
    $status = (string)($period['status'] ?? 'draft');
    return in_array($status, ['draft', 'active'], true) || hrCanOverrideClosedPeriod($admin);
}

function hrPeriodScoreKey(array $period): string {
    $periodKey = (string)($period['period_key'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
        return $periodKey;
    }
    $id = max(0, (int)($period['id'] ?? 0));
    if ($id > 0) {
        return 'P' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }
    $timestamp = strtotime((string)($period['start_date'] ?? 'now')) ?: time();
    return date('Y-m', $timestamp);
}

function hrEnsureEvaluationSchema(PDO $db): void {
    $run = function (string $sql, string $label) use ($db): void {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            safeAdminLog($label . ' failed: ' . $e->getMessage());
        }
    };

    $run("CREATE TABLE IF NOT EXISTS `hr_evaluation_categories` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `code` varchar(80) NOT NULL,
        `title` varchar(160) NOT NULL,
        `form_type` varchar(60) NOT NULL DEFAULT 'employee_performance',
        `allow_self_evaluation` tinyint(1) NOT NULL DEFAULT 0,
        `prevent_duplicate_responses` tinyint(1) NOT NULL DEFAULT 1,
        `manual_result_entry` tinyint(1) NOT NULL DEFAULT 0,
        `external_link` varchar(500) DEFAULT NULL,
        `age_guidance` varchar(120) DEFAULT NULL,
        `question_count` int(11) DEFAULT NULL,
        `intended_use` varchar(180) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `applicable_role` varchar(60) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_eval_category_code` (`code`),
        KEY `idx_hr_eval_category_active_order` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_evaluation_categories');

    ensureTableColumns('hr_evaluation_categories', [
        'form_type' => "varchar(60) NOT NULL DEFAULT 'employee_performance' AFTER `title`",
        'allow_self_evaluation' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `form_type`',
        'prevent_duplicate_responses' => 'tinyint(1) NOT NULL DEFAULT 1 AFTER `allow_self_evaluation`',
        'manual_result_entry' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `prevent_duplicate_responses`',
        'external_link' => 'varchar(500) DEFAULT NULL AFTER `manual_result_entry`',
        'age_guidance' => 'varchar(120) DEFAULT NULL AFTER `external_link`',
        'question_count' => 'int(11) DEFAULT NULL AFTER `age_guidance`',
        'intended_use' => 'varchar(180) DEFAULT NULL AFTER `question_count`',
    ]);

    $run("CREATE TABLE IF NOT EXISTS `hr_evaluation_criteria` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `category_id` int(11) UNSIGNED NOT NULL,
        `code` varchar(80) NOT NULL,
        `title` varchar(180) NOT NULL,
        `description` text DEFAULT NULL,
        `input_type` varchar(40) NOT NULL DEFAULT 'numeric',
        `options_json` longtext DEFAULT NULL,
        `weight` decimal(7,2) NOT NULL DEFAULT 0.00,
        `max_score` decimal(7,2) NOT NULL DEFAULT 100.00,
        `include_in_score` tinyint(1) NOT NULL DEFAULT 1,
        `visibility` varchar(40) NOT NULL DEFAULT 'manager',
        `applicable_role` varchar(60) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_eval_criterion_code` (`category_id`, `code`),
        KEY `idx_hr_eval_criterion_category` (`category_id`, `is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_evaluation_criteria');

    $run("CREATE TABLE IF NOT EXISTS `hr_evaluation_periods` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` varchar(180) NOT NULL,
        `period_type` varchar(60) NOT NULL DEFAULT 'monthly',
        `period_key` varchar(60) DEFAULT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `status` varchar(40) NOT NULL DEFAULT 'draft',
        `visibility` varchar(40) NOT NULL DEFAULT 'manager',
        `description` text DEFAULT NULL,
        `created_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_hr_eval_period_status` (`status`, `period_type`),
        KEY `idx_hr_eval_period_key` (`period_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_evaluation_periods');

    $run("CREATE TABLE IF NOT EXISTS `hr_assessment_tests` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` varchar(180) NOT NULL,
        `test_code` varchar(80) NOT NULL,
        `category` varchar(80) NOT NULL DEFAULT 'other',
        `age_guidance` varchar(120) DEFAULT NULL,
        `question_count` int(11) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `external_link` varchar(500) DEFAULT NULL,
        `source_url` varchar(500) DEFAULT NULL,
        `source_license` varchar(160) DEFAULT NULL,
        `scoring_method_type` varchar(40) NOT NULL DEFAULT 'manual',
        `import_metadata` longtext DEFAULT NULL,
        `is_paid` tinyint(1) NOT NULL DEFAULT 0,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `intended_use` varchar(180) DEFAULT NULL,
        `time_limit_minutes` int(11) DEFAULT NULL,
        `assigned_role` varchar(60) DEFAULT NULL,
        `assigned_department` varchar(100) DEFAULT NULL,
        `allow_retake` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_assessment_test_code` (`test_code`),
        KEY `idx_hr_assessment_active_order` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_assessment_tests');

    ensureTableColumns('hr_assessment_tests', [
        'source_url' => 'varchar(500) DEFAULT NULL AFTER `external_link`',
        'source_license' => 'varchar(160) DEFAULT NULL AFTER `source_url`',
        'scoring_method_type' => "varchar(40) NOT NULL DEFAULT 'manual' AFTER `source_license`",
        'import_metadata' => 'longtext DEFAULT NULL AFTER `scoring_method_type`',
        'time_limit_minutes' => 'int(11) DEFAULT NULL AFTER `intended_use`',
        'assigned_role' => 'varchar(60) DEFAULT NULL AFTER `time_limit_minutes`',
        'assigned_department' => 'varchar(100) DEFAULT NULL AFTER `assigned_role`',
        'allow_retake' => 'tinyint(1) NOT NULL DEFAULT 0 AFTER `assigned_department`',
    ]);

    $run("CREATE TABLE IF NOT EXISTS `hr_test_dimensions` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `test_id` int(11) UNSIGNED NOT NULL,
        `code` varchar(80) NOT NULL,
        `title` varchar(180) NOT NULL,
        `description` text DEFAULT NULL,
        `positive_label` varchar(120) DEFAULT NULL,
        `negative_label` varchar(120) DEFAULT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_test_dimension` (`test_id`, `code`),
        KEY `idx_hr_test_dimension_test` (`test_id`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_test_dimensions');

    $run("CREATE TABLE IF NOT EXISTS `hr_test_questions` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `test_id` int(11) UNSIGNED NOT NULL,
        `dimension_id` int(11) UNSIGNED DEFAULT NULL,
        `code` varchar(80) NOT NULL,
        `question_text` text NOT NULL,
        `answer_type` varchar(40) NOT NULL DEFAULT 'scale_5',
        `options_json` longtext DEFAULT NULL,
        `weight` decimal(7,2) NOT NULL DEFAULT 1.00,
        `scoring_direction` varchar(20) NOT NULL DEFAULT 'positive',
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_test_question` (`test_id`, `code`),
        KEY `idx_hr_test_question_test` (`test_id`, `is_active`, `sort_order`),
        KEY `idx_hr_test_question_dimension` (`dimension_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_test_questions');

    $run("CREATE TABLE IF NOT EXISTS `hr_test_assignments` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `test_id` int(11) UNSIGNED NOT NULL,
        `employee_id` int(11) UNSIGNED DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `role` varchar(60) DEFAULT NULL,
        `period_id` int(11) UNSIGNED DEFAULT NULL,
        `due_date` date DEFAULT NULL,
        `status` varchar(40) NOT NULL DEFAULT 'active',
        `allow_retake` tinyint(1) NOT NULL DEFAULT 0,
        `assigned_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_hr_test_assignment_test` (`test_id`, `status`),
        KEY `idx_hr_test_assignment_employee` (`employee_id`, `status`),
        KEY `idx_hr_test_assignment_scope` (`department`, `role`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_test_assignments');

    $run("CREATE TABLE IF NOT EXISTS `hr_test_responses` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `assignment_id` int(11) UNSIGNED DEFAULT NULL,
        `test_id` int(11) UNSIGNED NOT NULL,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `period_id` int(11) UNSIGNED DEFAULT NULL,
        `status` varchar(40) NOT NULL DEFAULT 'in_progress',
        `answers_json` longtext DEFAULT NULL,
        `dimension_scores_json` longtext DEFAULT NULL,
        `profile_output` varchar(180) DEFAULT NULL,
        `normalized_score` decimal(6,2) DEFAULT NULL,
        `started_at` datetime DEFAULT NULL,
        `submitted_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_hr_test_response_employee` (`employee_id`, `status`),
        KEY `idx_hr_test_response_test` (`test_id`, `submitted_at`),
        KEY `idx_hr_test_response_assignment` (`assignment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_test_responses');

    $run("CREATE TABLE IF NOT EXISTS `hr_assessment_results` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `test_id` int(11) UNSIGNED NOT NULL,
        `completion_date` date NOT NULL,
        `result_summary` text DEFAULT NULL,
        `score_value` varchar(120) DEFAULT NULL,
        `result_type` varchar(120) DEFAULT NULL,
        `attachment_path` varchar(500) DEFAULT NULL,
        `hr_notes` text DEFAULT NULL,
        `visibility` varchar(40) NOT NULL DEFAULT 'private',
        `recorded_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_hr_assessment_result_employee` (`employee_id`, `completion_date`),
        KEY `idx_hr_assessment_result_test` (`test_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure hr_assessment_results');

    ensureTableColumns('employee_evaluations', [
        'period_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `period_month`',
        'category_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `category_group`',
        'answers' => 'longtext DEFAULT NULL AFTER `scores`',
        'category_score' => 'decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `manager_score`',
        'source_type' => "varchar(40) NOT NULL DEFAULT 'peer' AFTER `category_score`",
    ]);
    ensureTableColumns('employee_score_history', [
        'period_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `period_month`',
        'category_breakdown' => 'longtext DEFAULT NULL AFTER `final_score`',
        'source_breakdown' => 'longtext DEFAULT NULL AFTER `category_breakdown`',
    ]);
    ensureTableColumns('employee_performance', [
        'period_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `period_month`',
        'score_breakdown' => 'longtext DEFAULT NULL AFTER `score`',
    ]);

    adminEnsureIndexes('employee_evaluations', [
        'idx_eval_period_category' => 'INDEX `idx_eval_period_category` (`period_id`, `category_id`)',
        'idx_eval_category_group' => 'INDEX `idx_eval_category_group` (`category_group`)',
    ]);
    if (adminIndexExists($db, 'employee_evaluations', 'uniq_eval_once') && !adminIndexExists($db, 'employee_evaluations', 'uniq_eval_once_form')) {
        safeAdminLog('Legacy employee evaluation unique index retained; destructive index replacement is disabled.');
    } else {
        adminEnsureIndexes('employee_evaluations', [
            'uniq_eval_once_form' => 'UNIQUE KEY `uniq_eval_once_form` (`evaluator_id`, `employee_id`, `period_id`, `category_id`, `period_month`)',
        ]);
    }
    adminEnsureIndexes('employee_score_history', [
        'idx_score_period_final' => 'INDEX `idx_score_period_final` (`period_id`, `final_score`)',
    ]);
    adminEnsureIndexes('employee_performance', [
        'idx_employee_performance_period_score' => 'INDEX `idx_employee_performance_period_score` (`period_id`, `score`)',
    ]);

    hrEnsureProfessionalTestSchema($db);
    hrSeedDefaultEvaluationData($db);
    hrSeedDefaultAssessmentTests($db);
}

function hrSeedDefaultEvaluationData(PDO $db): void {
    try {
        $count = (int)$db->query('SELECT COUNT(*) FROM hr_evaluation_categories')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $defaults = [
            'common' => [
                'title' => 'شایستگی های عمومی',
                'criteria' => [
                    ['discipline', 'انضباط', 'numeric', 20],
                    ['teamwork', 'کار تیمی', 'numeric', 20],
                    ['responsibility', 'مسئولیت پذیری', 'numeric', 20],
                    ['communication', 'ارتباطات', 'numeric', 15],
                    ['honesty', 'صداقت', 'numeric', 15],
                    ['key_culture', 'فرهنگ KEY', 'numeric', 10],
                ],
            ],
            'restaurant' => [
                'title' => 'عملیات رستوران',
                'criteria' => [
                    ['service_speed', 'سرعت سرویس', 'numeric', 35],
                    ['hygiene', 'بهداشت', 'numeric', 35],
                    ['order_accuracy', 'دقت سفارش', 'numeric', 30],
                ],
            ],
            'technology' => [
                'title' => 'فناوری',
                'criteria' => [
                    ['uptime', 'پایداری سرویس', 'kpi', 25],
                    ['maintenance', 'نگهداری', 'numeric', 25],
                    ['documentation', 'مستندسازی', 'numeric', 25],
                    ['security', 'امنیت', 'numeric', 25],
                ],
            ],
            'marketing' => [
                'title' => 'مارکتینگ',
                'criteria' => [
                    ['content_calendar', 'تقویم محتوا', 'numeric', 20],
                    ['reporting', 'گزارش دهی', 'numeric', 20],
                    ['content_quality', 'کیفیت محتوا', 'numeric', 25],
                    ['engagement', 'تعامل', 'numeric', 20],
                    ['transparency', 'شفافیت', 'numeric', 15],
                ],
            ],
        ];
        $sort = 10;
        foreach ($defaults as $code => $category) {
            $stmt = $db->prepare('INSERT INTO hr_evaluation_categories (code,title,is_active,sort_order) VALUES (?,?,1,?)');
            $stmt->execute([$code, $category['title'], $sort]);
            $categoryId = (int)$db->lastInsertId();
            $criterionSort = 10;
            foreach ($category['criteria'] as $criterion) {
                $db->prepare('INSERT INTO hr_evaluation_criteria (category_id,code,title,input_type,weight,max_score,include_in_score,is_active,sort_order) VALUES (?,?,?,?,?,100,1,1,?)')
                    ->execute([$categoryId, $criterion[0], $criterion[1], $criterion[2], $criterion[3], $criterionSort]);
                $criterionSort += 10;
            }
            $sort += 10;
        }

        $db->prepare('INSERT INTO settings (setting_key,setting_value,setting_type,category,is_public) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE setting_key=setting_key')
            ->execute(['hr_allow_self_evaluation', '0', 'boolean', 'hr']);
        $db->prepare('INSERT INTO settings (setting_key,setting_value,setting_type,category,is_public) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE setting_key=setting_key')
            ->execute(['hr_performance_source_weights', hrJsonEncode(['manager' => 35, 'peer' => 25, 'attendance' => 20, 'department_kpi' => 20]), 'json', 'hr']);
    } catch (Throwable $e) {
        safeAdminLog('HR default evaluation seed failed: ' . $e->getMessage());
    }
}

function hrNormalizeCriterionOptions(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return hrJsonEncode($decoded);
    }
    $options = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $label = $parts[0] ?? '';
        if ($label === '') {
            continue;
        }
        $options[] = [
            'label' => $label,
            'score' => isset($parts[1]) ? hrClampScore($parts[1]) : 0,
        ];
    }
    return $options ? hrJsonEncode($options) : '';
}

function hrCriterionOptions(array $criterion): array {
    $options = hrJsonDecode($criterion['options_json'] ?? '');
    if (!$options) {
        return [];
    }
    $normalized = [];
    foreach ($options as $key => $value) {
        if (is_array($value)) {
            $label = (string)($value['label'] ?? $value['title'] ?? $key);
            $score = hrClampScore($value['score'] ?? $value['value'] ?? 0);
        } else {
            $label = is_string($key) ? $key : (string)$value;
            $score = is_numeric($value) ? hrClampScore($value) : 0;
        }
        $normalized[] = ['label' => $label, 'score' => $score];
    }
    return $normalized;
}

function hrFetchCategories(PDO $db, bool $activeOnly = false): array {
    $sql = 'SELECT * FROM hr_evaluation_categories';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, title ASC';
    return $db->query($sql)->fetchAll();
}

function hrFetchCriteria(PDO $db, ?int $categoryId = null, bool $activeOnly = false): array {
    $where = [];
    $params = [];
    if ($categoryId) {
        $where[] = 'c.category_id = ?';
        $params[] = $categoryId;
    }
    if ($activeOnly) {
        $where[] = 'c.is_active = 1';
    }
    $sql = 'SELECT c.*, cat.title AS category_title, cat.code AS category_code FROM hr_evaluation_criteria c JOIN hr_evaluation_categories cat ON cat.id = c.category_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY cat.sort_order ASC, c.sort_order ASC, c.title ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function hrFetchPeriods(PDO $db, bool $activeOnly = false): array {
    $sql = 'SELECT * FROM hr_evaluation_periods';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY COALESCE(start_date, created_at) DESC, id DESC';
    return $db->query($sql)->fetchAll();
}

function hrFindPeriod(PDO $db, int $periodId): ?array {
    $stmt = $db->prepare('SELECT * FROM hr_evaluation_periods WHERE id = ? LIMIT 1');
    $stmt->execute([$periodId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function hrFindCategory(PDO $db, int $categoryId): ?array {
    $stmt = $db->prepare('SELECT * FROM hr_evaluation_categories WHERE id = ? LIMIT 1');
    $stmt->execute([$categoryId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function hrEmployeeDisplayName(array $employee): string {
    return trim((string)($employee['full_name'] ?? '')) !== '' ? (string)$employee['full_name'] : (string)($employee['username'] ?? '');
}

function hrEligibleEmployees(PDO $db, array $currentAdmin, ?array $category = null): array {
    $where = ["is_active = 1", "role IN ('employee','manager','admin')"];
    $params = [];
    $formType = (string)($category['form_type'] ?? '');
    $selfAllowed = $formType === 'self' || (int)($category['allow_self_evaluation'] ?? 0) === 1;
    if ($formType === 'self') {
        $where[] = 'id = ?';
        $params[] = (int)$currentAdmin['id'];
    }
    if (($category['applicable_role'] ?? '') !== '') {
        $where[] = 'role = ?';
        $params[] = $category['applicable_role'];
    }
    if (($category['department'] ?? '') !== '') {
        $where[] = 'department = ?';
        $params[] = $category['department'];
    }
    if (($currentAdmin['role'] ?? '') === 'employee' && !$selfAllowed) {
        $where[] = 'id <> ?';
        $params[] = (int)$currentAdmin['id'];
    }
    $stmt = $db->prepare('SELECT id, username, full_name, role, department FROM admins WHERE ' . implode(' AND ', $where) . ' ORDER BY department, full_name, username');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function hrCriterionAppliesToEmployee(array $criterion, array $employee): bool {
    if (($criterion['applicable_role'] ?? '') !== '' && (string)$criterion['applicable_role'] !== (string)($employee['role'] ?? '')) {
        return false;
    }
    if (($criterion['department'] ?? '') !== '' && (string)$criterion['department'] !== (string)($employee['department'] ?? '')) {
        return false;
    }
    return true;
}

function hrNormalizeAnswer(array $criterion, $raw): array {
    $type = (string)($criterion['input_type'] ?? 'numeric');
    $maxScore = max(1, (float)($criterion['max_score'] ?? 100));
    if ($type === 'yes_no') {
        $value = in_array((string)$raw, ['1', 'yes', 'بله'], true) ? 'yes' : 'no';
        return ['value' => $value, 'score' => $value === 'yes' ? 100.0 : 0.0, 'scored' => true];
    }
    if ($type === 'multiple_choice') {
        $options = hrCriterionOptions($criterion);
        $index = (int)$raw;
        $selected = $options[$index] ?? null;
        return ['value' => $selected['label'] ?? '', 'score' => (float)($selected['score'] ?? 0), 'scored' => true];
    }
    if ($type === 'text') {
        return ['value' => trim((string)$raw), 'score' => 0.0, 'scored' => false];
    }
    $value = hrClampScore($raw, 0, $maxScore);
    $score = round(($value / $maxScore) * 100, 4);
    return ['value' => $value, 'score' => $score, 'scored' => true];
}

function hrCalculateCategoryScore(array $criteria, array $answers, array $employee = []): array {
    $weightedTotal = 0.0;
    $weightSum = 0.0;
    $breakdown = [];
    foreach ($criteria as $criterion) {
        if (!hrCriterionAppliesToEmployee($criterion, $employee)) {
            continue;
        }
        $criterionId = (int)$criterion['id'];
        $normalized = hrNormalizeAnswer($criterion, $answers[$criterionId] ?? null);
        $weight = (float)($criterion['weight'] ?? 0);
        $include = (int)($criterion['include_in_score'] ?? 0) === 1 && $normalized['scored'];
        if ($include && $weight > 0) {
            $weightedTotal += ((float)$normalized['score']) * $weight;
            $weightSum += $weight;
        }
        $breakdown[$criterionId] = [
            'code' => (string)$criterion['code'],
            'title' => (string)$criterion['title'],
            'input_type' => (string)$criterion['input_type'],
            'weight' => $weight,
            'include_in_score' => $include,
            'value' => $normalized['value'],
            'score' => round((float)$normalized['score'], 2),
        ];
    }
    $score = $weightSum > 0 ? round($weightedTotal / $weightSum, 2) : 0.0;
    return ['score' => $score, 'weight_sum' => round($weightSum, 2), 'breakdown' => $breakdown];
}

function hrSaveEvaluation(PDO $db, array $currentAdmin, int $periodId, int $employeeId, int $categoryId, array $answers, string $notes): array {
    $period = hrFindPeriod($db, $periodId);
    $category = hrFindCategory($db, $categoryId);
    if (!$period || !$category || (int)$category['is_active'] !== 1) {
        throw new RuntimeException('دوره یا فرم ارزیابی معتبر نیست.');
    }
    if (!hrCanEditPeriod($period, $currentAdmin)) {
        throw new RuntimeException('این دوره بسته یا آرشیو شده است و امکان ویرایش عادی ندارد.');
    }
    $formAllowsSelf = (int)($category['allow_self_evaluation'] ?? 0) === 1 || (string)($category['form_type'] ?? '') === 'self';
    if ((int)$currentAdmin['id'] === $employeeId && !$formAllowsSelf && !hrSetting($db, 'hr_allow_self_evaluation', false)) {
        throw new RuntimeException('خودارزیابی در تنظیمات فعلی مجاز نیست.');
    }
    $stmt = $db->prepare("SELECT id, username, full_name, role, department FROM admins WHERE id = ? AND is_active = 1 AND role IN ('employee','manager','admin') LIMIT 1");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    if (!$employee) {
        throw new RuntimeException('کارمند انتخاب شده معتبر نیست.');
    }
    $criteria = hrFetchCriteria($db, $categoryId, true);
    $criteria = array_values(array_filter($criteria, static fn($criterion) => hrCriterionAppliesToEmployee($criterion, $employee)));
    if (!$criteria) {
        throw new RuntimeException('برای این فرم معیار فعال و قابل ثبت وجود ندارد.');
    }
    $calculated = hrCalculateCategoryScore($criteria, $answers, $employee);
    $formType = (string)($category['form_type'] ?? 'employee_performance');
    if ($formType === 'self' || (int)$currentAdmin['id'] === $employeeId) {
        $sourceType = 'self';
    } elseif (in_array($formType, ['organizational_assessment', 'job_test', 'external_catalog'], true)) {
        $sourceType = 'assessment';
    } else {
        $sourceType = in_array((string)$currentAdmin['role'], ['manager', 'admin', 'super_admin'], true) ? 'manager' : 'peer';
    }
    $peerScore = $sourceType === 'peer' ? $calculated['score'] : 0.0;
    $managerScore = in_array($sourceType, ['manager', 'self', 'assessment'], true) ? $calculated['score'] : 0.0;
    $periodMonth = hrPeriodScoreKey($period);

    $duplicate = $db->prepare('SELECT id FROM employee_evaluations WHERE evaluator_id = ? AND employee_id = ? AND period_month = ? AND (period_id = ? OR period_id IS NULL) AND (category_id = ? OR category_group = ?) LIMIT 1');
    $duplicate->execute([(int)$currentAdmin['id'], $employeeId, $periodMonth, $periodId, $categoryId, (string)$category['code']]);
    $existingId = (int)($duplicate->fetchColumn() ?: 0);
    if ($existingId && (int)($category['prevent_duplicate_responses'] ?? 1) === 1 && !hrCanEditPeriod($period, $currentAdmin)) {
        throw new RuntimeException('برای این فرم پاسخ تکراری مجاز نیست.');
    }

    $payload = hrJsonEncode($calculated['breakdown']);
    $params = [
        'evaluator_id' => (int)$currentAdmin['id'],
        'employee_id' => $employeeId,
        'period_month' => $periodMonth,
        'period_id' => $periodId,
        'category_group' => (string)$category['code'],
        'category_id' => $categoryId,
        'scores' => $payload,
        'answers' => $payload,
        'peer_score' => $peerScore,
        'manager_score' => $managerScore,
        'category_score' => $calculated['score'],
        'source_type' => $sourceType,
        'notes' => $notes !== '' ? $notes : null,
    ];

    if ($existingId) {
        $updateParams = [
            'id' => $existingId,
            'period_id' => $periodId,
            'category_group' => (string)$category['code'],
            'category_id' => $categoryId,
            'scores' => $payload,
            'answers' => $payload,
            'peer_score' => $peerScore,
            'manager_score' => $managerScore,
            'category_score' => $calculated['score'],
            'source_type' => $sourceType,
            'notes' => $notes !== '' ? $notes : null,
        ];
        $db->prepare('UPDATE employee_evaluations SET period_id=:period_id, category_group=:category_group, category_id=:category_id, scores=:scores, answers=:answers, peer_score=:peer_score, manager_score=:manager_score, category_score=:category_score, source_type=:source_type, notes=:notes, updated_at=NOW() WHERE id=:id')
            ->execute($updateParams);
    } else {
        $db->prepare('INSERT INTO employee_evaluations (evaluator_id, employee_id, period_month, period_id, category_group, category_id, scores, answers, peer_score, manager_score, category_score, source_type, notes, is_private) VALUES (:evaluator_id, :employee_id, :period_month, :period_id, :category_group, :category_id, :scores, :answers, :peer_score, :manager_score, :category_score, :source_type, :notes, 1)')
            ->execute($params);
    }

    hrRecalculateEmployeeScore($db, $employeeId, $periodId);
    return $calculated;
}

function hrRecalculateEmployeeScore(PDO $db, int $employeeId, int $periodId): array {
    $period = hrFindPeriod($db, $periodId);
    if (!$period) {
        throw new RuntimeException('دوره ارزیابی یافت نشد.');
    }
    $periodMonth = hrPeriodScoreKey($period);

    $stmt = $db->prepare('SELECT ev.category_id, ev.category_group, ev.source_type, ev.peer_score, ev.manager_score, ev.category_score, cat.title AS category_title FROM employee_evaluations ev LEFT JOIN hr_evaluation_categories cat ON cat.id = ev.category_id WHERE ev.employee_id = ? AND (ev.period_id = ? OR ev.period_month = ?)');
    $stmt->execute([$employeeId, $periodId, $periodMonth]);
    $evaluations = $stmt->fetchAll();

    $categoryBuckets = [];
    $sourceBuckets = ['manager' => [], 'peer' => []];
    foreach ($evaluations as $row) {
        $categoryKey = (string)($row['category_id'] ?: $row['category_group']);
        $categoryBuckets[$categoryKey]['title'] = (string)($row['category_title'] ?: $row['category_group']);
        $categoryBuckets[$categoryKey]['scores'][] = (float)($row['category_score'] ?: max((float)$row['peer_score'], (float)$row['manager_score']));
        $source = (string)($row['source_type'] ?? ((float)$row['manager_score'] > 0 ? 'manager' : 'peer'));
        $sourceBuckets[$source][] = (float)($row['category_score'] ?: max((float)$row['peer_score'], (float)$row['manager_score']));
    }

    $categoryBreakdown = [];
    foreach ($categoryBuckets as $key => $bucket) {
        $categoryBreakdown[$key] = [
            'title' => $bucket['title'],
            'score' => round(array_sum($bucket['scores']) / max(1, count($bucket['scores'])), 2),
        ];
    }
    $managerScore = $sourceBuckets['manager'] ? round(array_sum($sourceBuckets['manager']) / count($sourceBuckets['manager']), 2) : 0.0;
    $peerScore = $sourceBuckets['peer'] ? round(array_sum($sourceBuckets['peer']) / count($sourceBuckets['peer']), 2) : 0.0;

    $inputStmt = $db->prepare('SELECT manager_score, attendance_score, department_kpi_score, notes FROM employee_monthly_inputs WHERE employee_id = ? AND period_month = ? LIMIT 1');
    $inputStmt->execute([$employeeId, $periodMonth]);
    $input = $inputStmt->fetch() ?: ['manager_score' => 0, 'attendance_score' => 0, 'department_kpi_score' => 0, 'notes' => null];
    $managerScore = max($managerScore, (float)$input['manager_score']);
    $attendanceScore = (float)$input['attendance_score'];
    $kpiScore = (float)$input['department_kpi_score'];
    $weights = hrPerformanceSourceWeights($db);
    $weightTotal = max(1.0, array_sum($weights));
    $finalScore = round(
        (($managerScore * $weights['manager']) + ($peerScore * $weights['peer']) + ($attendanceScore * $weights['attendance']) + ($kpiScore * $weights['department_kpi'])) / $weightTotal,
        2
    );
    $sourceBreakdown = [
        'manager' => $managerScore,
        'peer' => $peerScore,
        'attendance' => $attendanceScore,
        'department_kpi' => $kpiScore,
        'weights' => $weights,
    ];

    $db->prepare('INSERT INTO employee_score_history (employee_id, period_month, period_id, manager_score, peer_score, attendance_score, department_kpi_score, final_score, category_breakdown, source_breakdown) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE period_id=VALUES(period_id), manager_score=VALUES(manager_score), peer_score=VALUES(peer_score), attendance_score=VALUES(attendance_score), department_kpi_score=VALUES(department_kpi_score), final_score=VALUES(final_score), category_breakdown=VALUES(category_breakdown), source_breakdown=VALUES(source_breakdown), calculated_at=NOW()')
        ->execute([$employeeId, $periodMonth, $periodId, $managerScore, $peerScore, $attendanceScore, $kpiScore, $finalScore, hrJsonEncode($categoryBreakdown), hrJsonEncode($sourceBreakdown)]);

    $db->prepare('INSERT INTO employee_performance (admin_id, period_month, period_id, score, score_breakdown, evaluation_notes, evaluated_by) VALUES (?, ?, ?, ?, ?, ?, NULL) ON DUPLICATE KEY UPDATE period_id=VALUES(period_id), score=VALUES(score), score_breakdown=VALUES(score_breakdown), updated_at=NOW()')
        ->execute([$employeeId, $periodMonth, $periodId, $finalScore, hrJsonEncode(['categories' => $categoryBreakdown, 'sources' => $sourceBreakdown]), $input['notes'] ?? null]);

    return [
        'employee_id' => $employeeId,
        'period_id' => $periodId,
        'period_month' => $periodMonth,
        'manager_score' => $managerScore,
        'peer_score' => $peerScore,
        'attendance_score' => $attendanceScore,
        'department_kpi_score' => $kpiScore,
        'final_score' => $finalScore,
        'category_breakdown' => $categoryBreakdown,
        'source_breakdown' => $sourceBreakdown,
    ];
}

function hrRecalculatePeriod(PDO $db, int $periodId): int {
    $stmt = $db->query("SELECT id FROM admins WHERE is_active = 1 AND role IN ('employee','manager','admin')");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $employeeId) {
        try {
            hrRecalculateEmployeeScore($db, (int)$employeeId, $periodId);
            $count++;
        } catch (Throwable $e) {
            safeAdminLog('HR period recalculation failed for employee ' . $employeeId . ': ' . $e->getMessage());
        }
    }
    return $count;
}

function hrRecalculateFormScores(PDO $db, int $categoryId, ?int $periodId = null): int {
    $where = ['category_id = ?'];
    $params = [$categoryId];
    if ($periodId !== null) {
        $where[] = 'period_id = ?';
        $params[] = $periodId;
    }
    $stmt = $db->prepare('SELECT DISTINCT employee_id, period_id FROM employee_evaluations WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        $targetPeriodId = (int)($row['period_id'] ?: $periodId);
        if ($targetPeriodId <= 0) {
            continue;
        }
        try {
            hrRecalculateEmployeeScore($db, (int)$row['employee_id'], $targetPeriodId);
            $count++;
        } catch (Throwable $e) {
            safeAdminLog('HR form recalculation failed for category ' . $categoryId . ': ' . $e->getMessage());
        }
    }
    return $count;
}

function hrDefaultAssessmentTests(): array {
    return [
        ['MBTI', 'MBTI', 'personality', 'بزرگسال', null, 'شناخت ترجیحات شخصیتی برای خودشناسی و تیم سازی'],
        ['DISC', 'DISC', 'personality', 'بزرگسال', null, 'شناخت سبک رفتاری و ارتباطی'],
        ['Gardner / MII', 'GARDNER_MII', 'strengths', 'بزرگسال', null, 'شناخت استعدادها و هوش های چندگانه'],
        ['Allen & Meyer Organizational Commitment', 'ALLEN_MEYER', 'commitment', 'بزرگسال', null, 'بررسی تعهد سازمانی به صورت غیرتنبیهی'],
        ['Minnesota Job Satisfaction', 'MINNESOTA_JS', 'satisfaction', 'بزرگسال', null, 'بررسی رضایت شغلی برای برنامه توسعه'],
        ['EQ', 'EQ', 'personality', 'بزرگسال', null, 'هوش هیجانی و مهارت های ارتباطی'],
        ['Raven IQ', 'RAVEN_IQ', 'intelligence', 'بزرگسال', null, 'ارزیابی شناختی با منبع معتبر خارجی'],
        ['Burnout', 'BURNOUT', 'stress', 'بزرگسال', null, 'پایش فرسودگی شغلی برای حمایت و مداخله سازمانی'],
        ['Holland vocational interest', 'HOLLAND', 'vocational', 'بزرگسال', null, 'شناخت علاقه های شغلی'],
        ['Spatial visualization', 'SPATIAL_VIS', 'spatial', 'بزرگسال', null, 'ارزیابی تجسم فضایی'],
    ];
}

function hrFetchAssessmentTests(PDO $db, bool $activeOnly = false): array {
    $sql = 'SELECT * FROM hr_assessment_tests WHERE deleted_at IS NULL';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, title ASC';
    return $db->query($sql)->fetchAll();
}

function hrFetchTestDimensions(PDO $db, int $testId): array {
    $stmt = $db->prepare('SELECT * FROM hr_test_dimensions WHERE test_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, title ASC');
    $stmt->execute([$testId]);
    return $stmt->fetchAll();
}

function hrFetchTestQuestions(PDO $db, int $testId, bool $activeOnly = false): array {
    $sql = 'SELECT q.*, d.code AS dimension_code, d.title AS dimension_title FROM hr_test_questions q LEFT JOIN hr_test_dimensions d ON d.id = q.dimension_id WHERE q.test_id = ? AND q.deleted_at IS NULL';
    if ($activeOnly) {
        $sql .= ' AND q.is_active = 1';
    }
    $sql .= ' ORDER BY q.sort_order ASC, q.id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute([$testId]);
    return $stmt->fetchAll();
}

function hrTestQuestionOptions(array $question): array {
    $options = hrJsonDecode($question['options_json'] ?? '');
    if ($options) {
        return hrCriterionOptions(['options_json' => hrJsonEncode($options)]);
    }
    $type = (string)($question['answer_type'] ?? 'scale_5');
    if ($type === 'scale_7') {
        return array_map(static fn($score) => ['label' => (string)$score, 'score' => (float)$score], range(1, 7));
    }
    if (in_array($type, ['scale_5', 'likert_5'], true)) {
        return array_map(static fn($score) => ['label' => (string)$score, 'score' => (float)$score], range(1, 5));
    }
    if (in_array($type, ['yes_no', 'true_false'], true)) {
        return [['label' => 'بله', 'score' => 1], ['label' => 'خیر', 'score' => 0]];
    }
    return [];
}

function hrNormalizeTestAnswer(array $question, $raw): array {
    $type = (string)($question['answer_type'] ?? 'scale_5');
    if ($type === 'text') {
        return ['value' => trim((string)$raw), 'score' => 0.0, 'scored' => false];
    }
    if ($type === 'multi_choice') {
        $options = hrTestQuestionOptions($question);
        $selectedIndexes = is_array($raw) ? $raw : [];
        $score = 0.0; $labels = [];
        foreach ($selectedIndexes as $index) {
            if (isset($options[(int)$index])) { $score += (float)$options[(int)$index]['score']; $labels[] = (string)$options[(int)$index]['label']; }
        }
        return ['value' => $labels, 'score' => $score, 'scored' => true];
    }
    if (in_array($type, ['multiple_choice', 'single_choice', 'scenario', 'scale_5', 'likert_5', 'scale_7'], true)) {
        $options = hrTestQuestionOptions($question);
        $index = (int)$raw;
        $selected = $options[$index] ?? null;
        return ['value' => $selected['label'] ?? '', 'score' => (float)($selected['score'] ?? 0), 'scored' => true];
    }
    if (in_array($type, ['yes_no', 'true_false'], true)) {
        $value = in_array((string)$raw, ['1', 'yes', 'بله', 'true', 'درست'], true) ? (string)$raw : 'no';
        $yes = in_array($value, ['1', 'yes', 'بله', 'true', 'درست'], true);
        return ['value' => $yes ? 'yes' : 'no', 'score' => $yes ? 1.0 : 0.0, 'scored' => true];
    }
    $max = $type === 'scale_7' ? 7.0 : ($type === 'numeric' ? 100.0 : 5.0);
    $value = hrClampScore($raw, 1, $max);
    return ['value' => $value, 'score' => $value, 'scored' => true];
}

function hrQuestionVisibleToRole(array $question, string $role): bool {
    $raw = trim((string)($question['role_visibility'] ?? ''));
    if ($raw === '') return true;
    $roles = array_filter(array_map('trim', explode(',', $raw)));
    return in_array($role, $roles, true);
}

function hrPositiveResultLevel(float $score): string {
    if ($score < 40) return 'ضعیف';
    if ($score < 60) return 'نیازمند بهبود';
    if ($score < 75) return 'قابل قبول';
    if ($score < 90) return 'خوب';
    return 'عالی';
}

function hrRiskResultLevel(float $score): string {
    if ($score < 40) return 'ریسک پایین';
    if ($score < 65) return 'نیازمند پایش';
    if ($score < 80) return 'ریسک متوسط';
    return 'ریسک بالا';
}

function hrTestResultLevel(string $testCode, string $analysisType, float $score): string {
    if ($testCode === 'MENU_KNOWLEDGE') {
        if ($score < 60) return 'نیازمند آموزش فوری';
        if ($score < 75) return 'قابل قبول';
        if ($score < 90) return 'خوب';
        return 'مسلط';
    }
    if ($testCode === 'ORG_EQ') {
        if ($score < 50) return 'نیازمند آموزش جدی';
        if ($score < 70) return 'قابل قبول با نیاز به بهبود';
        if ($score < 85) return 'خوب';
        return 'عالی';
    }
    return $analysisType === 'risk' ? hrRiskResultLevel($score) : hrPositiveResultLevel($score);
}

function hrCalculateTestScore(PDO $db, int $testId, array $answers): array {
    $testStmt = $db->prepare('SELECT * FROM hr_assessment_tests WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $testStmt->execute([$testId]);
    $test = $testStmt->fetch();
    if (!$test) {
        throw new RuntimeException('آزمون انتخاب‌شده معتبر نیست.');
    }
    $questions = hrFetchTestQuestions($db, $testId, true);
    $dimensions = [];
    $warnings = [];
    foreach ($questions as $question) {
        $questionId = (int)$question['id'];
        if (!array_key_exists($questionId, $answers) || $answers[$questionId] === '' || $answers[$questionId] === null) continue;
        $normalized = hrNormalizeTestAnswer($question, $answers[$questionId] ?? null);
        if (!$normalized['scored']) continue;
        $dimensionKey = (string)($question['dimension_code'] ?: 'general');
        $dimensionTitle = (string)($question['dimension_title'] ?: 'عمومی');
        $options = hrTestQuestionOptions($question);
        $scores = array_column($options, 'score');
        $min = $scores ? (float)min($scores) : 0.0;
        $max = $scores ? (float)max($scores) : (((string)$question['answer_type'] === 'scale_7') ? 7.0 : 5.0);
        $reverse = (int)($question['is_reverse_scored'] ?? 0) === 1
            || in_array((string)($question['score_direction'] ?? $question['scoring_direction'] ?? 'positive'), ['negative', 'reverse'], true);
        $score = $reverse ? $max + $min - (float)$normalized['score'] : (float)$normalized['score'];
        $weight = max(0.0, (float)($question['weight'] ?? 1));
        $normalizedScore = $max > $min ? (($score - $min) / ($max - $min)) * 100 : $score;
        $dimensions[$dimensionKey]['title'] = $dimensionTitle;
        $dimensions[$dimensionKey]['weighted_total'] = ($dimensions[$dimensionKey]['weighted_total'] ?? 0) + ($normalizedScore * $weight);
        $dimensions[$dimensionKey]['weight_sum'] = ($dimensions[$dimensionKey]['weight_sum'] ?? 0) + $weight;
        if ((int)($question['is_critical'] ?? 0) === 1 && $normalizedScore < 50) {
            $warnings[] = 'نیاز به بازآموزی فوری در سؤال بحرانی: ' . (string)$question['question_text'];
        }
    }
    $breakdown = [];
    foreach ($dimensions as $dimensionCode => $dimension) {
        $breakdown[$dimensionCode] = ['title' => $dimension['title'], 'score' => round($dimension['weighted_total'] / max(1, $dimension['weight_sum']), 2)];
    }
    $final = $breakdown ? round(array_sum(array_column($breakdown, 'score')) / count($breakdown), 2) : 0.0;
    uasort($breakdown, static fn($a, $b) => ((float)$b['score']) <=> ((float)$a['score']));
    $code = strtoupper((string)($test['test_code'] ?? ''));
    $analysisType = (string)($test['analysis_type'] ?? 'positive');
    $profile = $breakdown ? (string)array_key_first($breakdown) : 'manual';
    if ($code === 'DISC' && count($breakdown) > 1) {
        $keys = array_keys($breakdown);
        $profile = abs((float)$breakdown[$keys[0]]['score'] - (float)$breakdown[$keys[1]]['score']) < 10 ? $keys[0] . $keys[1] : $keys[0];
    } elseif ($code === 'MBTI_ORG') {
        $profile = '';
        foreach ([['E','I'], ['S','N'], ['T','F'], ['J','P']] as [$left, $right]) {
            $profile .= (float)($breakdown[$left]['score'] ?? 0) >= (float)($breakdown[$right]['score'] ?? 0) ? $left : $right;
        }
    }
    if ($code === 'ORG_COMMITMENT' && (float)($breakdown['affective']['score'] ?? 100) < 60 && (float)($breakdown['continuance']['score'] ?? 0) >= 75) {
        $warnings[] = 'الگوی ماندگاری غیرانگیزشی محتمل است؛ این هشدار نیازمند گفت‌وگوی حمایتی HR است و قضاوت قطعی درباره فرد نیست.';
    }
    if ($code === 'JOB_SATISFACTION') {
        foreach ($breakdown as $dimension) if ((float)$dimension['score'] < 60) $warnings[] = 'ناحیه ریسک رضایت شغلی: ' . (string)$dimension['title'];
    }
    $strengths = [];
    $improvements = [];
    foreach ($breakdown as $dimensionCode => $dimension) {
        $label = (string)($dimension['title'] ?? $dimensionCode);
        $score = (float)$dimension['score'];
        if (($analysisType === 'risk' && $score < 40) || ($analysisType !== 'risk' && $score >= 75)) $strengths[] = $label;
        if (($analysisType === 'risk' && $score >= 65) || ($analysisType !== 'risk' && $score < 60)) $improvements[] = $label;
    }
    $recommendations = [];
    if (adminTableExists($db, 'hr_test_recommendations')) {
        $stmt = $db->prepare("SELECT dimension_code,recommendation_text,min_score,max_score FROM hr_test_recommendations WHERE test_id=? AND status='active' AND deleted_at IS NULL ORDER BY sort_order,id");
        $stmt->execute([$testId]);
        foreach ($stmt->fetchAll() as $row) {
            $dimensionCode = (string)($row['dimension_code'] ?? '');
            $score = $dimensionCode !== '' ? (float)($breakdown[$dimensionCode]['score'] ?? $final) : $final;
            if (($row['min_score'] === null || $score >= (float)$row['min_score']) && ($row['max_score'] === null || $score <= (float)$row['max_score'])) {
                $recommendations[] = (string)$row['recommendation_text'];
            }
        }
    }
    if (!$recommendations && $improvements) $recommendations[] = 'برای بهبود «' . implode('، ', array_slice($improvements, 0, 3)) . '» برنامه آموزشی و بازخورد عملی تنظیم شود.';
    return [
        'dimension_scores' => $breakdown,
        'normalized_score' => $final,
        'profile_output' => $profile,
        'result_level' => hrTestResultLevel($code, $analysisType, $final),
        'strengths' => $strengths,
        'improvements' => $improvements,
        'recommendations' => array_values(array_unique($recommendations)),
        'warnings' => $warnings,
        'disclaimer' => hrAssessmentDisclaimer(),
    ];
}

function hrAssignmentAppliesToEmployee(array $assignment, array $employee): bool {
    if (!empty($assignment['employee_id']) && (int)$assignment['employee_id'] !== (int)$employee['id']) {
        return false;
    }
    if (!empty($assignment['department']) && (string)$assignment['department'] !== (string)($employee['department'] ?? '')) {
        return false;
    }
    if (!empty($assignment['role']) && (string)$assignment['role'] !== (string)($employee['role'] ?? '')) {
        return false;
    }
    return true;
}

function hrFetchAssignedTests(PDO $db, array $employee): array {
    $stmt = $db->prepare("
        SELECT a.*, t.title, t.test_code, t.category, t.time_limit_minutes, t.allow_retake AS test_allow_retake,
            r.id AS response_id, r.status AS response_status, r.normalized_score, r.profile_output, r.submitted_at
        FROM hr_test_assignments a
        JOIN hr_assessment_tests t ON t.id = a.test_id
        LEFT JOIN hr_test_responses r ON r.id = (
            SELECT r2.id FROM hr_test_responses r2
            WHERE r2.assignment_id = a.id AND r2.employee_id = ? AND r2.deleted_at IS NULL
            ORDER BY r2.id DESC LIMIT 1
        )
        WHERE a.status = 'active' AND a.deleted_at IS NULL
          AND t.is_active = 1
          AND (a.employee_id = ? OR a.employee_id IS NULL)
          AND (a.department IS NULL OR a.department = '' OR a.department = ?)
          AND (a.role IS NULL OR a.role = '' OR a.role = ?)
        ORDER BY COALESCE(a.due_date, '2999-12-31') ASC, a.id DESC
    ");
    $stmt->execute([(int)$employee['id'], (int)$employee['id'], (string)($employee['department'] ?? ''), (string)($employee['role'] ?? '')]);
    return $stmt->fetchAll();
}

function hrSaveTestResponse(PDO $db, array $employee, int $assignmentId, array $answers, bool $submit): array {
    $stmt = $db->prepare('SELECT a.*, t.allow_retake AS test_allow_retake FROM hr_test_assignments a JOIN hr_assessment_tests t ON t.id = a.test_id WHERE a.id = ? AND a.status = "active" AND a.deleted_at IS NULL AND t.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();
    if (!$assignment || !hrAssignmentAppliesToEmployee($assignment, $employee)) {
        throw new RuntimeException('آزمون انتخاب شده برای شما فعال نیست.');
    }
    if (!empty($assignment['due_date']) && (string)$assignment['due_date'] < date('Y-m-d')) {
        throw new RuntimeException('مهلت انجام این آزمون به پایان رسیده است.');
    }
    $questions = hrFetchTestQuestions($db, (int)$assignment['test_id'], true);
    if ($submit) {
        foreach ($questions as $question) {
            $questionId = (int)$question['id'];
            if (!hrQuestionVisibleToRole($question, (string)($employee['role'] ?? 'employee'))) continue;
            if ((int)($question['is_required'] ?? 1) === 1 && (!array_key_exists($questionId, $answers) || $answers[$questionId] === '' || $answers[$questionId] === null)) {
                throw new RuntimeException('پاسخ همه سؤال‌های الزامی باید ثبت شود.');
            }
        }
    }
    $existing = $db->prepare('SELECT * FROM hr_test_responses WHERE assignment_id = ? AND employee_id = ? ORDER BY id DESC LIMIT 1');
    $existing->execute([$assignmentId, (int)$employee['id']]);
    $response = $existing->fetch();
    if ($response && (string)$response['status'] === 'submitted' && !(int)$assignment['allow_retake'] && !(int)$assignment['test_allow_retake']) {
        throw new RuntimeException('این آزمون قبلا ثبت نهایی شده است.');
    }
    $completedAttempts = $db->prepare("SELECT COUNT(*) FROM hr_test_attempts WHERE assignment_id=? AND employee_id=? AND status='submitted' AND deleted_at IS NULL");
    $completedAttempts->execute([$assignmentId, (int)$employee['id']]);
    $completedCount = (int)$completedAttempts->fetchColumn();
    $maxAttempts = max(1, (int)($assignment['max_attempts'] ?? 1));
    if ($submit && $completedCount >= $maxAttempts) {
        throw new RuntimeException('تعداد دفعات مجاز انجام آزمون به پایان رسیده است.');
    }
    $score = $submit ? hrCalculateTestScore($db, (int)$assignment['test_id'], $answers) : ['dimension_scores' => [], 'normalized_score' => null, 'profile_output' => null];
    $db->beginTransaction();
    try {
        $attemptStmt = $db->prepare("SELECT * FROM hr_test_attempts WHERE assignment_id=? AND employee_id=? AND status='in_progress' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $attemptStmt->execute([$assignmentId, (int)$employee['id']]);
        $attempt = $attemptStmt->fetch();
        if (!$attempt) {
            $db->prepare('INSERT INTO hr_test_attempts (assignment_id,test_id,employee_id,attempt_no,status,started_at,submitted_at) VALUES (?,?,?,?,?,NOW(),' . ($submit ? 'NOW()' : 'NULL') . ')')
                ->execute([$assignmentId, (int)$assignment['test_id'], (int)$employee['id'], $completedCount + 1, $submit ? 'submitted' : 'in_progress']);
            $attemptId = (int)$db->lastInsertId();
        } else {
            $attemptId = (int)$attempt['id'];
            if ($submit) $db->prepare("UPDATE hr_test_attempts SET status='submitted',submitted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$attemptId]);
        }
    $payload = [
        'assignment_id' => $assignmentId,
        'attempt_id' => $attemptId,
        'test_id' => (int)$assignment['test_id'],
        'employee_id' => (int)$employee['id'],
        'period_id' => $assignment['period_id'] ?: null,
        'status' => $submit ? 'submitted' : 'in_progress',
        'answers_json' => hrJsonEncode($answers),
        'dimension_scores_json' => $submit ? hrJsonEncode($score['dimension_scores']) : null,
        'profile_output' => $score['profile_output'],
        'normalized_score' => $score['normalized_score'],
    ];
    if ($response && (string)$response['status'] !== 'submitted') {
        $payload['id'] = (int)$response['id'];
        $db->prepare('UPDATE hr_test_responses SET attempt_id=:attempt_id,status=:status,answers_json=:answers_json,dimension_scores_json=:dimension_scores_json,profile_output=:profile_output,normalized_score=:normalized_score,submitted_at=' . ($submit ? 'NOW()' : 'submitted_at') . ',updated_at=NOW() WHERE id=:id')
            ->execute(array_intersect_key($payload, array_flip(['id','attempt_id','status','answers_json','dimension_scores_json','profile_output','normalized_score'])));
    } else {
        $db->prepare('INSERT INTO hr_test_responses (assignment_id,attempt_id,test_id,employee_id,period_id,status,answers_json,dimension_scores_json,profile_output,normalized_score,started_at,submitted_at) VALUES (:assignment_id,:attempt_id,:test_id,:employee_id,:period_id,:status,:answers_json,:dimension_scores_json,:profile_output,:normalized_score,NOW(),' . ($submit ? 'NOW()' : 'NULL') . ')')
            ->execute(array_intersect_key($payload, array_flip(['assignment_id','attempt_id','test_id','employee_id','period_id','status','answers_json','dimension_scores_json','profile_output','normalized_score'])));
    }
    if ($submit) {
        $db->prepare('INSERT INTO hr_assessment_results (employee_id,test_id,completion_date,result_summary,score_value,result_type,visibility,recorded_by) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([(int)$employee['id'], (int)$assignment['test_id'], date('Y-m-d'), hrJsonEncode($score['dimension_scores']), (string)$score['normalized_score'], (string)$score['profile_output'], 'manager', (int)$employee['id']]);
        $db->prepare('INSERT INTO hr_test_results (attempt_id,assignment_id,test_id,employee_id,overall_score,result_level,profile_code,dimension_scores_json,strengths_json,improvements_json,recommendations_json,warnings_json,analysis_disclaimer,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"final") ON DUPLICATE KEY UPDATE overall_score=VALUES(overall_score),result_level=VALUES(result_level),profile_code=VALUES(profile_code),dimension_scores_json=VALUES(dimension_scores_json),strengths_json=VALUES(strengths_json),improvements_json=VALUES(improvements_json),recommendations_json=VALUES(recommendations_json),warnings_json=VALUES(warnings_json),analysis_disclaimer=VALUES(analysis_disclaimer),updated_at=NOW()')
            ->execute([$attemptId,$assignmentId,(int)$assignment['test_id'],(int)$employee['id'],$score['normalized_score'],$score['result_level'],$score['profile_output'],hrJsonEncode($score['dimension_scores']),hrJsonEncode($score['strengths']),hrJsonEncode($score['improvements']),hrJsonEncode($score['recommendations']),hrJsonEncode($score['warnings']),$score['disclaimer']]);
    }
        hrTestAudit($db, $submit ? 'submit' : 'save_progress', 'test_attempt', $attemptId, ['assignment_id' => $assignmentId, 'test_id' => (int)$assignment['test_id']], (int)$employee['id']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return $score;
}

function hrSeedDefaultAssessmentTests(PDO $db): void {
    try {
        $count = (int)$db->query('SELECT COUNT(*) FROM hr_assessment_tests')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $sort = 10;
        foreach (hrDefaultAssessmentTests() as $test) {
            $db->prepare('INSERT INTO hr_assessment_tests (title,test_code,category,age_guidance,question_count,description,intended_use,is_active,sort_order) VALUES (?,?,?,?,?,?,?,1,?)')
                ->execute([$test[0], $test[1], $test[2], $test[3], $test[4], $test[5], 'استخدام، تیم سازی، توسعه فردی و بینش HR', $sort]);
            $sort += 10;
        }
    } catch (Throwable $e) {
        safeAdminLog('HR default assessment seed failed: ' . $e->getMessage());
    }
}

function hrNormalizeAssessmentCode(string $code, string $fallbackTitle = ''): string {
    $source = $code !== '' ? $code : $fallbackTitle;
    $normalized = strtoupper(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $source) ?: '');
    return trim($normalized, '_') ?: 'ASSESSMENT_' . date('YmdHis');
}

function hrAssessmentFormCode(string $testCode): string {
    return 'assessment_' . strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $testCode));
}

function hrLicenseLooksSafe(string $license): bool {
    $license = strtolower(trim($license));
    if ($license === '') {
        return false;
    }
    foreach (['mit', 'apache', 'bsd', 'cc0', 'public domain', 'public-domain', 'unlicense', 'odc-by', 'cc-by', 'cc by'] as $allowed) {
        if (strpos($license, $allowed) !== false) {
            return true;
        }
    }
    return false;
}

function hrFetchPublicJson(string $url): array {
    $parts = parse_url($url);
    if (!in_array($parts['scheme'] ?? '', ['https'], true)) {
        throw new RuntimeException('فقط آدرس HTTPS برای وارد کردن کاتالوگ مجاز است.');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    $allowedHosts = ['raw.githubusercontent.com', 'github.com', 'gist.githubusercontent.com'];
    if (!in_array($host, $allowedHosts, true)) {
        throw new RuntimeException('برای کاهش ریسک فقط GitHub raw/gist به عنوان منبع واردات پذیرفته می شود.');
    }
    if ($host === 'github.com') {
        $path = (string)($parts['path'] ?? '');
        if (strpos($path, '/blob/') !== false) {
            $url = 'https://raw.githubusercontent.com' . str_replace('/blob/', '/', $path);
        }
    }
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: KEY-HR-Catalog-Importer\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException('دریافت فایل JSON از منبع عمومی انجام نشد.');
    }
    if (strlen($raw) > 1024 * 1024) {
        throw new RuntimeException('فایل وارداتی بیش از حد بزرگ است.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('فایل وارداتی JSON معتبر نیست.');
    }
    return $decoded;
}

function hrNormalizeImportedAssessmentDefinition(array $payload, string $sourceUrl = ''): array {
    $root = isset($payload['test']) && is_array($payload['test']) ? $payload['test'] : $payload;
    $license = (string)($root['license'] ?? $payload['license'] ?? '');
    if (!hrLicenseLooksSafe($license)) {
        throw new RuntimeException('مجوز منبع وارداتی امن یا سازگار تشخیص داده نشد.');
    }

    $title = trim((string)($root['title'] ?? $root['name'] ?? ''));
    $code = hrNormalizeAssessmentCode((string)($root['code'] ?? $root['test_code'] ?? ''), $title);
    if ($title === '') {
        $title = $code;
    }
    $scoringMethod = (string)($root['scoring_method_type'] ?? $root['scoring_method'] ?? 'manual');
    $scoringMethods = hrAssessmentScoringMethods();
    if (!isset($scoringMethods[$scoringMethod])) {
        $scoringMethod = 'manual';
    }

    $dimensions = [];
    foreach (['dimensions', 'scales', 'categories', 'domains'] as $key) {
        if (!empty($root[$key]) && is_array($root[$key])) {
            $dimensions = $root[$key];
            break;
        }
    }
    if (!$dimensions && !empty($root['questions']) && is_array($root['questions'])) {
        foreach ($root['questions'] as $question) {
            $dimension = is_array($question) ? (string)($question['dimension'] ?? $question['scale'] ?? $question['category'] ?? 'imported') : 'imported';
            $dimensions[$dimension] = ['code' => $dimension, 'title' => $dimension];
        }
    }

    $criteria = [];
    $sort = 10;
    foreach ($dimensions as $key => $dimension) {
        $dimension = is_array($dimension) ? $dimension : ['title' => (string)$dimension];
        $criterionCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($dimension['code'] ?? $key ?? ('dimension_' . $sort)));
        $criterionCode = trim((string)$criterionCode, '_') ?: 'dimension_' . $sort;
        $criteria[] = [
            'code' => strtolower($criterionCode),
            'title' => trim((string)($dimension['title'] ?? $dimension['label'] ?? $dimension['name'] ?? $criterionCode)),
            'description' => trim((string)($dimension['description'] ?? '')),
            'input_type' => 'rating',
            'options_json' => hrJsonEncode([
                ['label' => 'خیلی کم', 'score' => 20],
                ['label' => 'کم', 'score' => 40],
                ['label' => 'متوسط', 'score' => 60],
                ['label' => 'زیاد', 'score' => 80],
                ['label' => 'خیلی زیاد', 'score' => 100],
            ]),
            'weight' => isset($dimension['weight']) ? hrClampScore($dimension['weight'], 0, 1000) : 1,
            'sort_order' => $sort,
        ];
        $sort += 10;
    }

    if (!$criteria) {
        $criteria[] = [
            'code' => 'manual_result',
            'title' => 'نتیجه ساختاری وارداتی',
            'description' => 'پرسش های دارای متن کپی رایت وارد نشده اند. نتیجه این آزمون به صورت دستی یا از منبع خارجی ثبت می شود.',
            'input_type' => 'text',
            'options_json' => null,
            'weight' => 0,
            'sort_order' => 10,
        ];
    }

    return [
        'title' => $title,
        'test_code' => $code,
        'category' => (string)($root['category'] ?? 'other'),
        'age_guidance' => (string)($root['age_range'] ?? $root['age_guidance'] ?? ''),
        'question_count' => isset($root['question_count']) ? max(0, (int)$root['question_count']) : null,
        'description' => trim((string)($root['description'] ?? '')),
        'external_link' => trim((string)($root['external_link'] ?? $root['reference'] ?? '')),
        'source_url' => $sourceUrl,
        'source_license' => $license,
        'scoring_method_type' => $scoringMethod,
        'intended_use' => trim((string)($root['intended_use'] ?? 'HR insight')),
        'criteria' => $criteria,
        'import_metadata' => [
            'imported_at' => date('c'),
            'source_url' => $sourceUrl,
            'license' => $license,
            'copyright_guard' => 'Question wording is not imported; only structure, dimensions, options, weights, and metadata are stored.',
        ],
    ];
}

function hrImportAssessmentDefinition(PDO $db, array $definition): int {
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO hr_assessment_tests (title,test_code,category,age_guidance,question_count,description,external_link,source_url,source_license,scoring_method_type,import_metadata,intended_use,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),age_guidance=VALUES(age_guidance),question_count=VALUES(question_count),description=VALUES(description),external_link=VALUES(external_link),source_url=VALUES(source_url),source_license=VALUES(source_license),scoring_method_type=VALUES(scoring_method_type),import_metadata=VALUES(import_metadata),intended_use=VALUES(intended_use),is_active=1')
            ->execute([
                $definition['title'],
                $definition['test_code'],
                $definition['category'],
                $definition['age_guidance'] ?: null,
                $definition['question_count'],
                $definition['description'] ?: null,
                $definition['external_link'] ?: null,
                $definition['source_url'] ?: null,
                $definition['source_license'] ?: null,
                $definition['scoring_method_type'],
                hrJsonEncode($definition['import_metadata'] ?? []),
                $definition['intended_use'] ?: null,
                500,
            ]);

        $formCode = hrAssessmentFormCode((string)$definition['test_code']);
        $db->prepare('INSERT INTO hr_evaluation_categories (code,title,form_type,manual_result_entry,external_link,age_guidance,question_count,intended_use,description,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),form_type=VALUES(form_type),manual_result_entry=VALUES(manual_result_entry),external_link=VALUES(external_link),age_guidance=VALUES(age_guidance),question_count=VALUES(question_count),intended_use=VALUES(intended_use),description=VALUES(description),is_active=VALUES(is_active)')
            ->execute([
                $formCode,
                $definition['title'],
                'external_catalog',
                $definition['scoring_method_type'] === 'manual' ? 1 : 0,
                $definition['external_link'] ?: $definition['source_url'] ?: null,
                $definition['age_guidance'] ?: null,
                $definition['question_count'],
                $definition['intended_use'] ?: null,
                $definition['description'] ?: null,
                1,
                500,
            ]);
        $stmt = $db->prepare('SELECT id FROM hr_evaluation_categories WHERE code = ? LIMIT 1');
        $stmt->execute([$formCode]);
        $categoryId = (int)$stmt->fetchColumn();

        foreach ($definition['criteria'] as $criterion) {
            $db->prepare('INSERT INTO hr_evaluation_criteria (category_id,code,title,description,input_type,options_json,weight,max_score,include_in_score,visibility,is_active,sort_order) VALUES (?,?,?,?,?,?,?,100,1,"manager",1,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),input_type=VALUES(input_type),options_json=VALUES(options_json),weight=VALUES(weight),is_active=1,sort_order=VALUES(sort_order)')
                ->execute([
                    $categoryId,
                    $criterion['code'],
                    $criterion['title'] ?: $criterion['code'],
                    $criterion['description'] ?: null,
                    $criterion['input_type'],
                    $criterion['options_json'] ?: null,
                    $criterion['weight'],
                    $criterion['sort_order'],
                ]);

            $db->prepare('INSERT INTO hr_test_dimensions (test_id,code,title,description,sort_order) SELECT id,?,?,?,? FROM hr_assessment_tests WHERE test_code = ? ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),sort_order=VALUES(sort_order)')
                ->execute([
                    $criterion['code'],
                    $criterion['title'] ?: $criterion['code'],
                    $criterion['description'] ?: null,
                    $criterion['sort_order'],
                    $definition['test_code'],
                ]);
            $dimensionStmt = $db->prepare('SELECT d.id FROM hr_test_dimensions d JOIN hr_assessment_tests t ON t.id = d.test_id WHERE t.test_code = ? AND d.code = ? LIMIT 1');
            $dimensionStmt->execute([$definition['test_code'], $criterion['code']]);
            $dimensionId = (int)$dimensionStmt->fetchColumn();
            $testStmt = $db->prepare('SELECT id FROM hr_assessment_tests WHERE test_code = ? LIMIT 1');
            $testStmt->execute([$definition['test_code']]);
            $testId = (int)$testStmt->fetchColumn();
            if ($testId > 0) {
                $db->prepare('INSERT INTO hr_test_questions (test_id,dimension_id,code,question_text,answer_type,options_json,weight,scoring_direction,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE dimension_id=VALUES(dimension_id),question_text=VALUES(question_text),answer_type=VALUES(answer_type),options_json=VALUES(options_json),weight=VALUES(weight),sort_order=VALUES(sort_order)')
                    ->execute([
                        $testId,
                        $dimensionId ?: null,
                        'template_' . $criterion['code'],
                        'ارزیابی ساختاری بعد: ' . ($criterion['title'] ?: $criterion['code']),
                        'scale_5',
                        $criterion['options_json'] ?: null,
                        $criterion['weight'],
                        'positive',
                        $criterion['sort_order'],
                    ]);
            }
        }
        $db->commit();
        return $categoryId;
    } catch (Throwable $e) {
        $db->rollBack();
        safeAdminLog('HR assessment import failed: ' . $e->getMessage());
        throw $e;
    }
}

function hrImportAssessmentCatalogFromUrl(PDO $db, string $url): int {
    $payload = hrFetchPublicJson($url);
    $definition = hrNormalizeImportedAssessmentDefinition($payload, $url);
    return hrImportAssessmentDefinition($db, $definition);
}

function hrUploadAssessmentAttachment(string $field, string $current = ''): string {
    if (empty($_FILES[$field]['name'])) {
        return $current;
    }
    if (!isset($_FILES[$field]['error']) || (int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('آپلود فایل پیوست انجام نشد.');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int)($_FILES[$field]['size'] ?? 0) <= 0 || (int)$_FILES[$field]['size'] > $maxBytes) {
        throw new RuntimeException('حجم فایل پیوست باید حداکثر ۵ مگابایت باشد.');
    }

    $original = (string)$_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('فرمت فایل پیوست مجاز نیست.');
    }

    $tmpPath = (string)$_FILES[$field]['tmp_name'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mime !== '' && $mime !== $allowed[$ext]) {
        throw new RuntimeException('نوع فایل پیوست با پسوند آن سازگار نیست.');
    }

    $dir = ROOT_PATH . '/uploads/hr-assessments';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('مسیر ذخیره پیوست قابل ایجاد نیست.');
    }

    $name = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmpPath, $target)) {
        throw new RuntimeException('ذخیره فایل پیوست انجام نشد.');
    }

    if ($current !== '' && strpos($current, 'uploads/hr-assessments/') === 0) {
        $old = ROOT_PATH . '/' . $current;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    return 'uploads/hr-assessments/' . $name;
}

function hrSyncAssessmentCatalogToForms(PDO $db): int {
    if (!adminTableExists($db, 'hr_assessment_tests') || !adminTableExists($db, 'hr_evaluation_categories')) {
        return 0;
    }
    $tests = $db->query('SELECT * FROM hr_assessment_tests ORDER BY sort_order ASC, title ASC')->fetchAll();
    $count = 0;
    foreach ($tests as $test) {
        $code = hrAssessmentFormCode((string)$test['test_code']);
        $exists = $db->prepare('SELECT id FROM hr_evaluation_categories WHERE code = ? LIMIT 1');
        $exists->execute([$code]);
        if ($exists->fetchColumn()) {
            continue;
        }
        try {
            $db->prepare("INSERT INTO hr_evaluation_categories (code,title,form_type,manual_result_entry,external_link,age_guidance,question_count,intended_use,description,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $code,
                    (string)$test['title'],
                    'external_catalog',
                    1,
                    $test['external_link'] ?: null,
                    $test['age_guidance'] ?: null,
                    $test['question_count'] === null ? null : (int)$test['question_count'],
                    $test['intended_use'] ?: null,
                    $test['description'] ?: null,
                    (int)$test['is_active'],
                    (int)$test['sort_order'],
                ]);
            $count++;
        } catch (Throwable $e) {
            safeAdminLog('Assessment catalog sync failed for ' . $code . ': ' . $e->getMessage());
        }
    }
    return $count;
}
