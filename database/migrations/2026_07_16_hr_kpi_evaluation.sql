-- Phase 6 KPI evaluation with BSF and business coaching metrics. Additive only.

CREATE TABLE IF NOT EXISTS `hr_kpi_definitions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `code` varchar(120) DEFAULT NULL,
  `kpi_key` varchar(120) DEFAULT NULL,
  `kpi_code` varchar(120) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role_key` varchar(100) DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `standard_group` varchar(120) DEFAULT NULL,
  `unit_label` varchar(80) DEFAULT NULL,
  `target_value` decimal(14,4) DEFAULT NULL,
  `min_value` decimal(14,4) DEFAULT NULL,
  `max_value` decimal(14,4) DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `direction` enum('positive','negative') NOT NULL DEFAULT 'positive',
  `calculation_type` varchar(80) NOT NULL DEFAULT 'simple_percent',
  `rag_green_threshold` decimal(8,2) DEFAULT 90.00,
  `rag_yellow_threshold` decimal(8,2) DEFAULT 70.00,
  `max_score_percent` decimal(8,2) NOT NULL DEFAULT 100.00,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_kpi_definition_code_phase6` (`code`),
  KEY `idx_hr_kpi_definition_scope_phase6` (`department`, `role_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
  `assigned_scope_id` varchar(120) DEFAULT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_assignment_scope_phase6` (`assigned_scope_type`, `assigned_scope_id`, `period_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_entries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `manual_score` decimal(8,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `entered_by` int(11) UNSIGNED DEFAULT NULL,
  `entered_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_entry_period_phase6` (`period_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_scores` (
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
  KEY `idx_hr_kpi_score_assignment_phase6` (`assignment_id`, `period_id`),
  KEY `idx_hr_kpi_score_rag_phase6` (`rag_status`, `calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_corrective_actions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_score_id` int(11) UNSIGNED NOT NULL,
  `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_corrective_score_phase6` (`kpi_score_id`, `status`),
  KEY `idx_hr_kpi_corrective_task_phase6` (`planner_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
