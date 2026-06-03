-- Employee evaluation, CRM acquisition, social links, and Balad fallback support.
ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `department` varchar(100) DEFAULT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `permissions` JSON DEFAULT NULL AFTER `department`,
  MODIFY `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin';

ALTER TABLE `crm_customers`
  ADD COLUMN IF NOT EXISTS `acquisition_source` varchar(100) DEFAULT NULL AFTER `reminder_date`;
CREATE INDEX IF NOT EXISTS `idx_crm_acquisition_source` ON `crm_customers` (`acquisition_source`);

CREATE TABLE IF NOT EXISTS `acquisition_sources` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_acquisition_title` (`title`),
  KEY `idx_acquisition_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `acquisition_sources` (`title`, `sort_order`, `active`) VALUES
('Instagram',10,1),('Telegram',20,1),('Google',30,1),('Balad',40,1),('Friend Referral',50,1),('Walk-in',60,1),('Website',70,1),('Advertisement',80,1),('Other',90,1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

CREATE TABLE IF NOT EXISTS `social_links` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `url` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_social_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Instagram','📷','https://instagram.com/keyrestaurant',10,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Instagram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Telegram','✈️','https://t.me/keyrestaurant',20,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Telegram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'WhatsApp','💬','https://wa.me/989121234567',30,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='WhatsApp');

CREATE TABLE IF NOT EXISTS `employee_evaluations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `category_group` varchar(50) NOT NULL DEFAULT 'common',
  `scores` JSON NOT NULL,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_eval_once` (`evaluator_id`, `employee_id`, `period_month`),
  KEY `idx_eval_employee_month` (`employee_id`, `period_month`),
  CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_monthly_inputs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_monthly_inputs` (`employee_id`, `period_month`),
  CONSTRAINT `fk_monthly_inputs_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_inputs_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_score_history` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_score_employee_month` (`employee_id`, `period_month`),
  KEY `idx_score_month_final` (`period_month`, `final_score`),
  CONSTRAINT `fk_score_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_rewards` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `reward_date` date NOT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rewards_employee_date` (`employee_id`, `reward_date`),
  CONSTRAINT `fk_rewards_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rewards_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_warnings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `warning_date` date NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_warnings_employee_date` (`employee_id`, `warning_date`),
  CONSTRAINT `fk_warnings_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warnings_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('balad_map_url','https://balad.ir/location?latitude=35.6892&longitude=51.3890','url','contact',1)
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`), `setting_type`=VALUES(`setting_type`), `category`=VALUES(`category`), `is_public`=VALUES(`is_public`);
