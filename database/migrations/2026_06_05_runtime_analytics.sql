-- KEY Restaurant & Coffeehouse runtime/admin schema repair migration
-- Valid incremental migration for existing installs; all CREATE statements are non-destructive.
-- Column/index reconciliation for already-existing tables is handled by SchemaSynchronizer against database/schema.sql.

-- schema_migrations
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`),
  KEY `idx_schema_migrations_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin',
  `department` varchar(100) DEFAULT NULL,
  `permissions` JSON DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `membership_level` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `loyalty_points` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `last_order_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `membership_level` (`membership_level`),
  KEY `is_active` (`is_active`),
  KEY `loyalty_points` (`loyalty_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `order_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `order_type` enum('dine_in','takeaway','delivery') DEFAULT 'takeaway',
  `table_number` varchar(10) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','online') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` enum('pending','confirmed','preparing','ready','delivered','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL COMMENT 'in minutes',
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `order_status` (`order_status`),
  KEY `payment_status` (`payment_status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- menu_categories
CREATE TABLE IF NOT EXISTS `menu_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_fa` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description_fa` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_menu_categories_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- menu_items
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(11) UNSIGNED NOT NULL,
  `name_fa` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description_fa` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `gallery_images` JSON DEFAULT NULL,
  `ingredients_fa` text DEFAULT NULL,
  `ingredients_en` text DEFAULT NULL,
  `calories` int(11) DEFAULT NULL,
  `preparation_time` int(11) DEFAULT NULL COMMENT 'in minutes',
  `is_available` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_vegetarian` tinyint(1) DEFAULT 0,
  `is_spicy` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `orders_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `is_available` (`is_available`),
  KEY `is_featured` (`is_featured`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_menu_items_category_active_order` (`category_id`, `is_available`, `sort_order`),
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dynamic_forms
CREATE TABLE IF NOT EXISTS `dynamic_forms` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_name` varchar(100) NOT NULL,
  `form_title_fa` varchar(200) NOT NULL,
  `form_title_en` varchar(200) DEFAULT NULL,
  `form_description_fa` text DEFAULT NULL,
  `form_description_en` text DEFAULT NULL,
  `form_schema` JSON NOT NULL COMMENT 'JSON schema of form fields',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `display_order` (`display_order`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_forms_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- survey_responses
CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `response_data` JSON NOT NULL COMMENT 'JSON response data',
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `submitted_at` (`submitted_at`),
  KEY `idx_survey_customer_phone` (`customer_phone`),
  CONSTRAINT `fk_responses_form` FOREIGN KEY (`form_id`) REFERENCES `dynamic_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_responses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- crm_customers
CREATE TABLE IF NOT EXISTS `crm_customers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `first_purchase_date` date DEFAULT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `total_purchase_volume` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_date` date DEFAULT NULL,
  `acquisition_source` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `surveys_completed_count` int(11) NOT NULL DEFAULT 0,
  `last_visit_date` date DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `attended_match_event` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crm_mobile` (`mobile`),
  KEY `idx_crm_mobile` (`mobile`),
  KEY `idx_crm_birth_date` (`birth_date`),
  KEY `idx_crm_reminder_date` (`reminder_date`),
  KEY `idx_crm_acquisition_source` (`acquisition_source`),
  KEY `idx_crm_last_visit_date` (`last_visit_date`),
  KEY `idx_crm_created_at` (`created_at`),
  KEY `idx_crm_attended` (`attended_match_event`),
  CONSTRAINT `fk_crm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- crm_timelines
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

-- hero_banners
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

-- matches
CREATE TABLE IF NOT EXISTS `matches` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_a` varchar(120) NOT NULL,
  `team_b` varchar(120) NOT NULL,
  `match_date` date NOT NULL,
  `kickoff_time` time NOT NULL,
  `broadcast_time` time DEFAULT NULL,
  `final_score_team_a` int(11) DEFAULT NULL,
  `final_score_team_b` int(11) DEFAULT NULL,
  `match_finished` tinyint(1) NOT NULL DEFAULT 0,
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
  KEY `idx_matches_active_status` (`is_active`, `active_for_prediction`, `status`),
  KEY `idx_matches_finished` (`match_finished`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- predictions
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
  `is_correct_prediction` tinyint(1) NOT NULL DEFAULT 0,
  `crm_match` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prediction_mobile_match` (`mobile`, `match_id`),
  KEY `idx_predictions_mobile` (`mobile`),
  KEY `idx_predictions_match` (`match_id`),
  KEY `idx_predictions_created_at` (`created_at`),
  CONSTRAINT `fk_predictions_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- employee_evaluations
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

-- employee_monthly_inputs
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

-- employee_score_history
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

-- employee_rewards
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

-- employee_warnings
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

-- employee_performance
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

-- analytics_visitors
CREATE TABLE IF NOT EXISTS `analytics_visitors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_uuid` varchar(64) NOT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analytics_visitor_uuid` (`visitor_uuid`),
  KEY `idx_analytics_visitors_uuid` (`visitor_uuid`),
  KEY `idx_analytics_visitors_device` (`device_type`),
  KEY `idx_analytics_visitors_browser` (`browser`),
  KEY `idx_analytics_visitors_os` (`os`),
  KEY `idx_analytics_visitors_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- analytics_sessions
CREATE TABLE IF NOT EXISTS `analytics_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_uuid` varchar(64) NOT NULL,
  `visitor_uuid` varchar(64) NOT NULL,
  `started_at` datetime NOT NULL,
  `last_activity_at` datetime NOT NULL,
  `landing_page` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `medium` varchar(100) DEFAULT NULL,
  `campaign` varchar(150) DEFAULT NULL,
  `utm_source` varchar(100) DEFAULT NULL,
  `utm_medium` varchar(100) DEFAULT NULL,
  `utm_campaign` varchar(150) DEFAULT NULL,
  `utm_term` varchar(150) DEFAULT NULL,
  `utm_content` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analytics_session_uuid` (`session_uuid`),
  KEY `idx_analytics_sessions_uuid` (`session_uuid`),
  KEY `idx_analytics_sessions_visitor` (`visitor_uuid`),
  KEY `idx_analytics_sessions_started` (`started_at`),
  KEY `idx_analytics_sessions_activity` (`last_activity_at`),
  KEY `idx_analytics_sessions_source` (`source`),
  KEY `idx_analytics_sessions_medium` (`medium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- analytics_pageviews
CREATE TABLE IF NOT EXISTS `analytics_pageviews` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_uuid` varchar(64) NOT NULL,
  `session_uuid` varchar(64) NOT NULL,
  `page_url` varchar(1000) DEFAULT NULL,
  `page_path` varchar(500) DEFAULT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `screen_width` int(11) DEFAULT NULL,
  `screen_height` int(11) DEFAULT NULL,
  `browser_language` varchar(50) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `viewed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_analytics_pageviews_visitor` (`visitor_uuid`),
  KEY `idx_analytics_pageviews_session` (`session_uuid`),
  KEY `idx_analytics_pageviews_viewed` (`viewed_at`),
  KEY `idx_analytics_pageviews_path` (`page_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
