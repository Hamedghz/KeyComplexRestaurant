-- Planner / daily task module. Additive only.

CREATE TABLE IF NOT EXISTS `planner_tasks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_task_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_task_comments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_planner_comments_task` (`task_id`, `created_at`),
  KEY `idx_planner_comments_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('hr_performance_goals',65,'hr_planner_mine','planner.php','📅','employee',40,'["planner.php","planner-today.php","planner-assigned.php","planner-report.php","hr-planner-mine.php","hr-planner-today.php","hr-planner-tomorrow.php","hr-planner-overdue.php","hr-planner-referred.php","hr-planner-reports.php"]')
ON DUPLICATE KEY UPDATE group_key=VALUES(group_key), group_order=VALUES(group_order), url=VALUES(url), icon=VALUES(icon), min_role=VALUES(min_role), sort_order=VALUES(sort_order), active_pages=VALUES(active_pages), is_active=1;
