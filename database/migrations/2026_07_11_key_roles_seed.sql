-- KEY restaurant role definitions foundation. Additive only.

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
