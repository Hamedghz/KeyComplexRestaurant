-- Missing admin functionality, lotus settings, Balad map URL, and performance schema.
ALTER TABLE `admins`
  MODIFY `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin';

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `department` varchar(100) DEFAULT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `permissions` JSON DEFAULT NULL AFTER `department`;

CREATE TABLE IF NOT EXISTS `employee_performance` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `reward` varchar(255) DEFAULT NULL,
  `penalty` varchar(255) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `evaluated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee_period` (`admin_id`, `period_month`),
  KEY `idx_employee_performance_month_score` (`period_month`, `score`),
  CONSTRAINT `fk_employee_performance_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_performance_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('site_description_fa', 'تجربه‌ای لوکس از غذا و نوشیدنی', 'text', 'general', 1),
('logo_image', '', 'url', 'general', 1),
('seo_title_fa', 'KEY رستوران و کافه', 'text', 'seo', 1),
('seo_description_fa', 'رستوران و کافه KEY', 'text', 'seo', 1),
('default_language', 'fa', 'text', 'general', 1),
('jalali_calendar_enabled', '1', 'boolean', 'general', 0),
('balad_map_url', 'https://balad.ir/location?latitude=35.6892&longitude=51.3890', 'url', 'contact', 1),
('lotus_logo_image', '', 'url', 'lotus', 1),
('lotus_title_fa', 'KEY رستوران و کافه', 'text', 'lotus', 1),
('lotus_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی', 'text', 'lotus', 1),
('lotus_description_fa', '', 'text', 'lotus', 1),
('lotus_cta_text_fa', '', 'text', 'lotus', 1),
('lotus_cta_link', '#menu', 'url', 'lotus', 1),
('lotus_active', '1', 'boolean', 'lotus', 1)
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
