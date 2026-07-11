-- HR / Evaluation / KPI / Planner / OKR architecture slice.
-- Additive only: no destructive statements and no unrelated table changes.

CREATE TABLE IF NOT EXISTS `hr_role_duties` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `duty_code` varchar(140) DEFAULT NULL,
  `role_code` varchar(100) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `responsibility_type` enum('daily','shift','weekly','monthly','as_needed') NOT NULL DEFAULT 'daily',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_role_duty_code` (`duty_code`),
  UNIQUE KEY `uniq_hr_role_duty` (`role_code`, `title`),
  KEY `idx_hr_role_duty_status` (`status`, `role_code`),
  KEY `idx_hr_role_duty_priority` (`priority`, `responsibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_templates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key` varchar(120) NOT NULL,
  `template_code` varchar(120) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `period_type` enum('daily','shift','weekly','monthly','custom') NOT NULL DEFAULT 'daily',
  `requires_manager_approval` tinyint(1) NOT NULL DEFAULT 0,
  `requires_inspector_approval` tinyint(1) NOT NULL DEFAULT 0,
  `items_json` longtext DEFAULT NULL,
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_template_key` (`template_key`),
  UNIQUE KEY `uniq_hr_checklist_template_code` (`template_code`),
  KEY `idx_hr_checklist_template_status` (`status`, `role_code`),
  KEY `idx_hr_checklist_template_period` (`period_type`, `department`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `template_code` varchar(120) NOT NULL,
  `item_code` varchar(140) NOT NULL,
  `title` varchar(190) NOT NULL,
  `phase` varchar(60) NOT NULL DEFAULT 'during_shift',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `has_quality_score` tinyint(1) NOT NULL DEFAULT 0,
  `max_quality_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `has_note` tinyint(1) NOT NULL DEFAULT 0,
  `can_create_planner_task` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_item_code` (`template_code`, `item_code`),
  KEY `idx_hr_checklist_items_template` (`template_id`, `status`, `sort_order`),
  KEY `idx_hr_checklist_items_phase` (`phase`, `is_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'assigned',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_assignment_owner` (`employee_id`, `status`, `due_date`),
  KEY `idx_hr_checklist_assignment_template` (`template_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_submissions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `answers_json` longtext DEFAULT NULL,
  `manager_id` int(11) UNSIGNED DEFAULT NULL,
  `approval_status` varchar(30) NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_submission_assignment` (`assignment_id`, `approval_status`),
  KEY `idx_hr_checklist_submission_employee` (`employee_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_definitions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_key` varchar(120) NOT NULL,
  `kpi_code` varchar(120) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'performance',
  `formula_key` varchar(120) DEFAULT NULL,
  `unit` varchar(60) DEFAULT NULL,
  `unit_label` varchar(80) DEFAULT NULL,
  `target_value` decimal(14,4) DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `direction` enum('positive','negative') NOT NULL DEFAULT 'positive',
  `calculation_type` varchar(80) NOT NULL DEFAULT 'simple_percent',
  `rag_green_threshold` decimal(8,2) DEFAULT NULL,
  `rag_yellow_threshold` decimal(8,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_kpi_definition_key` (`kpi_key`),
  UNIQUE KEY `uniq_hr_kpi_definition_code` (`kpi_code`),
  KEY `idx_hr_kpi_definition_status` (`status`, `category`),
  KEY `idx_hr_kpi_definition_scope` (`department`, `role_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `period_month` char(7) NOT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_kpi_assignment` (`kpi_id`, `employee_id`, `role_code`, `period_month`),
  KEY `idx_hr_kpi_assignment_owner` (`employee_id`, `period_month`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_entries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `score_value` decimal(6,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `entered_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_entry_assignment` (`assignment_id`, `entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_planner_tasks` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(11) UNSIGNED DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `source_type` varchar(80) DEFAULT NULL,
  `source_id` int(11) UNSIGNED DEFAULT NULL,
  `customer_stage` varchar(80) DEFAULT NULL,
  `channel` varchar(80) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `priority` varchar(30) NOT NULL DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_planner_owner` (`owner_id`, `status`, `due_date`),
  KEY `idx_hr_planner_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_monthly_objectives` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_key` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `period_month` char(7) NOT NULL,
  `tmo_user_id` int(11) UNSIGNED DEFAULT NULL,
  `owner_id` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_monthly_objective` (`objective_key`, `period_month`),
  KEY `idx_hr_monthly_objective_tmo` (`tmo_user_id`, `period_month`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_key_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `target_value` decimal(14,4) DEFAULT NULL,
  `current_value` decimal(14,4) DEFAULT NULL,
  `kpi_id` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_key_result_objective` (`objective_id`, `status`),
  KEY `idx_hr_key_result_kpi` (`kpi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_okr_actions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_result_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `owner_id` int(11) UNSIGNED DEFAULT NULL,
  `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_okr_action_kr` (`key_result_id`, `status`),
  KEY `idx_hr_okr_action_task` (`planner_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_tmo_reviews` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `tmo_reviewer_id` int(11) UNSIGNED DEFAULT NULL,
  `review_status` varchar(30) NOT NULL DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_tmo_review_objective` (`objective_id`, `review_status`),
  KEY `idx_hr_tmo_review_reviewer` (`tmo_reviewer_id`, `reviewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('hr_performance_goals',60,'hr_dashboard','hr-dashboard.php','📌','employee',5,'["hr-dashboard.php"]'),
('hr_performance_goals',60,'hr_tests_bank','hr-tests-bank.php','🧠','admin',10,'["hr-tests-bank.php","hr-test-questions.php","hr-test-assignments.php","hr-my-tests.php","hr-test-results.php","hr-test-personnel-report.php","employee-tests.php","employee-assessments.php","hr-test-report.php","evaluation-builder.php"]'),
('hr_performance_goals',60,'hr_role_duties','hr-role-duties.php','✅','manager',20,'["hr-role-duties.php","hr-checklist-templates.php","hr-checklist-assignments.php","hr-checklist-submissions.php","hr-checklist-approvals.php","hr-checklist-progress.php"]'),
('hr_performance_goals',60,'hr_kpi_definitions','hr-kpi-definitions.php','📈','manager',30,'["hr-kpi-definitions.php","hr-kpi-assignments.php","hr-kpi-entries.php","hr-kpi-scores.php","hr-kpi-reports.php","employee-performance.php"]'),
('hr_performance_goals',60,'hr_planner_mine','hr-planner-mine.php','📅','employee',40,'["hr-planner-mine.php","hr-planner-today.php","hr-planner-tomorrow.php","hr-planner-overdue.php","hr-planner-referred.php","hr-planner-reports.php"]'),
('hr_performance_goals',60,'hr_okr_objectives','hr-okr-objectives.php','🎯','manager',50,'["hr-okr-objectives.php","hr-okr-key-results.php","hr-okr-actions.php","hr-okr-task-links.php","hr-okr-progress.php","hr-tmo-reviews.php"]')
ON DUPLICATE KEY UPDATE group_key=VALUES(group_key), group_order=VALUES(group_order), url=VALUES(url), icon=VALUES(icon), min_role=VALUES(min_role), sort_order=VALUES(sort_order), active_pages=VALUES(active_pages), is_active=1;

UPDATE `admin_navigation_items`
SET `is_active` = 0
WHERE `item_key` IN ('evaluation_builder','employee_evaluations','employee_tests','employee_performance','employee_assessments','hr_test_report');
