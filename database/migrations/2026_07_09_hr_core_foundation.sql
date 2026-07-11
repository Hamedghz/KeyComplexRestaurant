-- HR core foundation. Additive only; no unrelated module changes.

CREATE TABLE IF NOT EXISTS `hr_roles` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_code` varchar(100) NOT NULL,
  `title_fa` varchar(190) NOT NULL,
  `title_en` varchar(190) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `parent_role_code` varchar(100) DEFAULT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_managerial` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `source_label` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_role_code` (`role_code`),
  KEY `idx_hr_roles_parent` (`parent_role_code`),
  KEY `idx_hr_roles_department` (`department`, `status`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_periods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `period_type` enum('daily','shift','weekly','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `jalali_label` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_periods_type_status` (`period_type`, `status`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_dynamic_fields` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `field_key` varchar(120) NOT NULL,
  `label` varchar(190) NOT NULL,
  `field_type` enum('text','textarea','number','select','multi_select','checkbox','date','json') NOT NULL DEFAULT 'text',
  `options_json` longtext DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` longtext DEFAULT NULL,
  `weight` decimal(8,2) DEFAULT NULL,
  `visible_to` enum('employee','manager','hr','tmo','admin','all') NOT NULL DEFAULT 'all',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_dynamic_field` (`module_key`, `entity_type`, `entity_id`, `field_key`),
  KEY `idx_hr_dynamic_fields_lookup` (`module_key`, `entity_type`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_dynamic_field_values` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_id` int(11) UNSIGNED NOT NULL,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED NOT NULL,
  `subject_user_id` int(11) UNSIGNED DEFAULT NULL,
  `value_json` longtext DEFAULT NULL,
  `submitted_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_dynamic_values_entity` (`module_key`, `entity_type`, `entity_id`),
  KEY `idx_hr_dynamic_values_subject` (`subject_user_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `actor_user_id` int(11) UNSIGNED DEFAULT NULL,
  `old_value_json` longtext DEFAULT NULL,
  `new_value_json` longtext DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_audit_entity` (`module_key`, `entity_type`, `entity_id`, `created_at`),
  KEY `idx_hr_audit_actor` (`actor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_module_settings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `setting_key` varchar(120) NOT NULL,
  `setting_value_json` longtext DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_module_setting` (`module_key`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_standards` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `standard_key` varchar(120) NOT NULL,
  `standard_group` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `source_label` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_standard_key` (`standard_key`),
  KEY `idx_business_standards_group_status` (`standard_group`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_standard_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `standard_id` int(11) UNSIGNED NOT NULL,
  `item_key` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_standard_item` (`standard_id`, `item_key`),
  KEY `idx_business_standard_items_status` (`standard_id`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('hr_performance_goals',65,'hr_dashboard','hr-dashboard.php','📌','employee',5,'["hr-dashboard.php"]'),
('hr_performance_goals',65,'hr_tests_bank','hr-tests-bank.php','🧠','admin',10,'["hr-tests-bank.php","hr-test-questions.php","hr-test-assignments.php","hr-my-tests.php","hr-test-results.php","hr-test-personnel-report.php","employee-tests.php","employee-assessments.php","hr-test-report.php","evaluation-builder.php"]'),
('hr_performance_goals',65,'hr_role_duties','hr-role-duties.php','✅','manager',20,'["hr-role-duties.php","hr-checklist-templates.php","hr-checklist-assignments.php","hr-checklist-submissions.php","hr-checklist-approvals.php","hr-checklist-progress.php"]'),
('hr_performance_goals',65,'hr_kpi_definitions','hr-kpi-definitions.php','📈','manager',30,'["hr-kpi-definitions.php","hr-kpi-assignments.php","hr-kpi-entries.php","hr-kpi-scores.php","hr-kpi-reports.php","employee-performance.php"]'),
('hr_performance_goals',65,'hr_planner_mine','planner.php','📅','employee',40,'["planner.php","planner-today.php","planner-assigned.php","planner-report.php","hr-planner-mine.php","hr-planner-today.php","hr-planner-tomorrow.php","hr-planner-overdue.php","hr-planner-referred.php","hr-planner-reports.php"]'),
('hr_performance_goals',65,'hr_okr_objectives','hr-okr-objectives.php','🎯','manager',50,'["hr-okr-objectives.php","hr-okr-key-results.php","hr-okr-actions.php","hr-okr-task-links.php","hr-okr-progress.php","hr-tmo-reviews.php"]')
ON DUPLICATE KEY UPDATE group_key=VALUES(group_key), group_order=VALUES(group_order), url=VALUES(url), icon=VALUES(icon), min_role=VALUES(min_role), sort_order=VALUES(sort_order), active_pages=VALUES(active_pages), is_active=1;

UPDATE `admin_navigation_items`
SET `is_active` = 0
WHERE `item_key` IN ('evaluation_builder','employee_evaluations','employee_tests','employee_performance','employee_assessments','hr_test_report');
