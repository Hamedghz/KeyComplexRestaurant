-- Phase 8 OKR / KR / TMO person management.
-- Additive only. TMO is selected from existing admins/users.

CREATE TABLE IF NOT EXISTS `okr_objectives` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `target_month` char(7) DEFAULT NULL,
  `scope_type` enum('company','department','team') NOT NULL DEFAULT 'company',
  `scope_id` varchar(120) DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `tmo_user_id` int(11) UNSIGNED DEFAULT NULL,
  `status` enum('draft','active','reviewed','closed','archived') NOT NULL DEFAULT 'draft',
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_objective_period` (`period_id`, `target_month`, `status`),
  KEY `idx_okr_objective_tmo` (`tmo_user_id`, `status`),
  KEY `idx_okr_objective_scope` (`scope_type`, `scope_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_key_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `kr_type` enum('numeric','descriptive') NOT NULL DEFAULT 'numeric',
  `target_value` decimal(14,4) DEFAULT NULL,
  `current_value` decimal(14,4) DEFAULT NULL,
  `unit_label` varchar(80) DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 1.00,
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_kr_objective` (`objective_id`, `status`),
  KEY `idx_okr_kr_progress` (`final_progress_percent`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_actions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `kr_id` int(11) UNSIGNED DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','done','cancelled','overdue') NOT NULL DEFAULT 'pending',
  `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_action_objective` (`objective_id`, `status`),
  KEY `idx_okr_action_kr` (`kr_id`, `status`),
  KEY `idx_okr_action_planner` (`planner_task_id`),
  KEY `idx_okr_action_owner_due` (`owner_user_id`, `due_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_kpi_links` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED DEFAULT NULL,
  `kr_id` int(11) UNSIGNED DEFAULT NULL,
  `kpi_definition_id` int(11) UNSIGNED DEFAULT NULL,
  `kpi_assignment_id` int(11) UNSIGNED DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 1.00,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_okr_kpi_link_objective` (`objective_id`, `status`),
  KEY `idx_okr_kpi_link_kr` (`kr_id`, `status`),
  KEY `idx_okr_kpi_link_kpi` (`kpi_definition_id`, `kpi_assignment_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_progress_logs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` enum('objective','kr','action') NOT NULL,
  `entity_id` int(11) UNSIGNED NOT NULL,
  `source` enum('manual','planner','kpi','system') NOT NULL DEFAULT 'manual',
  `old_progress_percent` decimal(6,2) DEFAULT NULL,
  `new_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_progress_entity` (`entity_type`, `entity_id`, `created_at`),
  KEY `idx_okr_progress_source` (`source`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tmo_reviews` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `tmo_user_id` int(11) UNSIGNED NOT NULL,
  `review_date` date NOT NULL,
  `result_summary` text DEFAULT NULL,
  `blockers` text DEFAULT NULL,
  `decisions` text DEFAULT NULL,
  `next_actions` text DEFAULT NULL,
  `final_score` decimal(6,2) DEFAULT NULL,
  `status` enum('draft','submitted','approved','closed') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tmo_review_objective` (`objective_id`, `status`),
  KEY `idx_tmo_review_user_date` (`tmo_user_id`, `review_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('hr_performance_goals',65,'hr_okr_objectives','okr-objectives.php','🎯','manager',50,'["okr-objectives.php","okr-key-results.php","okr-actions.php","okr-progress.php","tmo-review.php","tmo-dashboard.php","hr-okr-objectives.php","hr-okr-key-results.php","hr-okr-actions.php","hr-okr-progress.php","hr-tmo-reviews.php"]')
ON DUPLICATE KEY UPDATE group_key=VALUES(group_key), group_order=VALUES(group_order), url=VALUES(url), icon=VALUES(icon), min_role=VALUES(min_role), sort_order=VALUES(sort_order), active_pages=VALUES(active_pages), is_active=1;
