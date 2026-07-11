-- KEY restaurant role duties seed foundation. Additive only.

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
