-- Admin/CRM/Survey/Prediction/Menu content migration for MySQL/phpMyAdmin.
-- Run from phpMyAdmin Import or mysql CLI after database/schema.sql and database/survey_schema.sql.

ALTER TABLE `menu_categories`
  ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`,
  ADD INDEX `idx_menu_categories_active_order` (`is_active`, `sort_order`);

ALTER TABLE `menu_items`
  ADD COLUMN `gallery_images` JSON DEFAULT NULL AFTER `image`,
  ADD INDEX `idx_menu_items_category_active_order` (`category_id`, `is_available`, `sort_order`);

CREATE TABLE IF NOT EXISTS `crm_customers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `first_purchase_date` date DEFAULT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `total_purchase_volume` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reminder_date` date DEFAULT NULL,
  `acquisition_source` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `surveys_completed_count` int(11) NOT NULL DEFAULT 0,
  `last_visit_date` date DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crm_mobile` (`mobile`),
  KEY `idx_crm_mobile` (`mobile`),
  KEY `idx_crm_birth_date` (`birth_date`),
  KEY `idx_crm_reminder_date` (`reminder_date`),
  KEY `idx_crm_last_visit_date` (`last_visit_date`),
  KEY `idx_crm_created_at` (`created_at`),
  CONSTRAINT `fk_crm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_timelines` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crm_timeline_customer_date` (`customer_id`, `event_date`),
  CONSTRAINT `fk_crm_timeline_customer` FOREIGN KEY (`customer_id`) REFERENCES `crm_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_banners` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `mobile_image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hero_active_order` (`active_status`, `display_order`),
  KEY `idx_hero_start_end` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matches` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_a` varchar(120) NOT NULL,
  `team_b` varchar(120) NOT NULL,
  `match_date` date NOT NULL,
  `kickoff_time` time NOT NULL,
  `broadcast_time` time DEFAULT NULL,
  `prediction_open_at` datetime NOT NULL,
  `prediction_close_at` datetime NOT NULL,
  `status` enum('scheduled','live','finished','cancelled') NOT NULL DEFAULT 'scheduled',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_for_prediction` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_matches_date` (`match_date`),
  KEY `idx_matches_team_a` (`team_a`),
  KEY `idx_matches_team_b` (`team_b`),
  KEY `idx_matches_prediction_window` (`prediction_open_at`, `prediction_close_at`),
  KEY `idx_matches_active_status` (`is_active`, `active_for_prediction`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `predictions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `match_id` int(11) UNSIGNED NOT NULL,
  `predicted_score_team_a` tinyint UNSIGNED NOT NULL,
  `predicted_score_team_b` tinyint UNSIGNED NOT NULL,
  `crm_matched` tinyint(1) NOT NULL DEFAULT 0,
  `customer_exists` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match_time` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prediction_mobile_match` (`mobile`, `match_id`),
  KEY `idx_predictions_mobile` (`mobile`),
  KEY `idx_predictions_match` (`match_id`),
  KEY `idx_predictions_created_at` (`created_at`),
  CONSTRAINT `fk_predictions_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `survey_responses`
  ADD INDEX `idx_survey_customer_phone` (`customer_phone`);
