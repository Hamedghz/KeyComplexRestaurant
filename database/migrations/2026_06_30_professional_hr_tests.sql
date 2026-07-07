-- Professional HR tests extension (additive only; no DROP/TRUNCATE/data deletion).
-- hr_assessment_tests is the existing canonical test catalog and intentionally
-- remains in use instead of creating a parallel hr_tests table.

CREATE TABLE IF NOT EXISTS `hr_test_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_category_slug` (`slug`),
  KEY `idx_hr_test_category_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_options` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `score_value` decimal(8,2) NOT NULL DEFAULT 0.00,
  `dimension_code` varchar(80) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_option_slug` (`question_id`, `slug`),
  KEY `idx_hr_test_option_question` (`question_id`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_scoring_rules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `rule_type` varchar(60) NOT NULL DEFAULT 'positive',
  `rule_config_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_scoring_rule` (`test_id`, `slug`),
  KEY `idx_hr_test_scoring_test` (`test_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_periods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_period_slug` (`slug`),
  KEY `idx_hr_test_period_status` (`status`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_attempts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `attempt_no` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'in_progress',
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_attempt_no` (`assignment_id`, `employee_id`, `attempt_no`),
  KEY `idx_hr_test_attempt_employee` (`employee_id`, `status`),
  KEY `idx_hr_test_attempt_test` (`test_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) UNSIGNED DEFAULT NULL,
  `assignment_id` int(11) UNSIGNED DEFAULT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `overall_score` decimal(6,2) DEFAULT NULL,
  `result_level` varchar(120) DEFAULT NULL,
  `profile_code` varchar(120) DEFAULT NULL,
  `dimension_scores_json` longtext DEFAULT NULL,
  `strengths_json` longtext DEFAULT NULL,
  `improvements_json` longtext DEFAULT NULL,
  `recommendations_json` longtext DEFAULT NULL,
  `warnings_json` longtext DEFAULT NULL,
  `analysis_disclaimer` varchar(500) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'final',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_result_attempt` (`attempt_id`),
  KEY `idx_hr_test_result_employee` (`employee_id`, `created_at`),
  KEY `idx_hr_test_result_test` (`test_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_role_profiles` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `role_code` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `dimension_targets_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_role_profile` (`test_id`, `role_code`),
  KEY `idx_hr_test_role_profile_status` (`status`, `role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_recommendations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `dimension_code` varchar(80) DEFAULT NULL,
  `min_score` decimal(6,2) DEFAULT NULL,
  `max_score` decimal(6,2) DEFAULT NULL,
  `recommendation_text` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_recommendation` (`test_id`, `slug`),
  KEY `idx_hr_test_recommendation_lookup` (`test_id`, `dimension_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `context_json` longtext DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_test_audit_entity` (`entity_type`, `entity_id`, `created_at`),
  KEY `idx_hr_test_audit_actor` (`actor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('surveys_evaluation',60,'hr_test_report','hr-test-report.php','📊','manager',90,NULL);
