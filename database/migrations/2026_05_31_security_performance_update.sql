-- Security/RBAC/update metadata migration.
-- Run after 2026_05_31_admin_crm_prediction_content.sql.

ALTER TABLE `admins`
  MODIFY `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin';

CREATE TABLE IF NOT EXISTS `system_versions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `git_commit` varchar(64) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_versions_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
