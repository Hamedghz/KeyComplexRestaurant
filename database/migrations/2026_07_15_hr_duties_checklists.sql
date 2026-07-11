-- Phase 5 role duties, checklists, SOP and 5S. Additive only.

CREATE TABLE IF NOT EXISTS `hr_role_duties` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key` varchar(100) DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `responsibility_type` enum('daily','shift','weekly','monthly','general','as_needed') NOT NULL DEFAULT 'general',
  `standard_group` varchar(120) DEFAULT NULL,
  `priority` enum('low','normal','medium','high','critical') NOT NULL DEFAULT 'normal',
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_role_duty_status` (`status`, `role_key`),
  KEY `idx_hr_role_duty_priority` (`priority`, `responsibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_templates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `code` varchar(120) DEFAULT NULL,
  `template_key` varchar(120) DEFAULT NULL,
  `template_code` varchar(120) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role_key` varchar(100) DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `period_type` enum('daily','shift','weekly','monthly','custom') NOT NULL DEFAULT 'daily',
  `shift_code` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `standard_group` varchar(120) DEFAULT NULL,
  `requires_manager_approval` tinyint(1) NOT NULL DEFAULT 0,
  `requires_inspector_approval` tinyint(1) NOT NULL DEFAULT 0,
  `items_json` longtext DEFAULT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_template_code` (`code`),
  KEY `idx_hr_checklist_template_status` (`status`, `role_key`),
  KEY `idx_hr_checklist_template_period` (`period_type`, `department`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `has_quality_score` tinyint(1) NOT NULL DEFAULT 0,
  `max_quality_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `has_note` tinyint(1) NOT NULL DEFAULT 0,
  `linked_duty_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_standard_item_id` int(11) UNSIGNED DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_items_template` (`template_id`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_key` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_category_key` (`category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
  `assigned_scope_id` varchar(120) DEFAULT NULL,
  `assigned_employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'assigned',
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_assignment_scope` (`assigned_scope_type`, `assigned_scope_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_submissions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `template_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `checklist_date` date NOT NULL,
  `shift_code` varchar(60) DEFAULT NULL,
  `completion_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_quality_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','submitted','manager_approved','inspector_approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_submission_assignment` (`assignment_id`, `status`),
  KEY `idx_hr_checklist_submission_employee` (`employee_id`, `checklist_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_submission_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_approvals` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) UNSIGNED NOT NULL,
  `approver_id` int(11) UNSIGNED NOT NULL,
  `approval_type` enum('manager','inspector') NOT NULL,
  `status` enum('approved','rejected') NOT NULL,
  `note` text DEFAULT NULL,
  `approved_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_approval_submission` (`submission_id`, `approval_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
