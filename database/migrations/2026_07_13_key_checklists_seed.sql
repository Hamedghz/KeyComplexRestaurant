-- KEY restaurant checklist template and item seed foundation. Additive only.

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
