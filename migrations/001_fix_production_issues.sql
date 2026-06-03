-- Migration: Fix Production Blocking Issues
-- Created: 2025-06-03

-- 1. Make database fields nullable (except critical ones)
ALTER TABLE `crm_customers` 
    MODIFY `birth_date` DATE NULL,
    MODIFY `first_purchase_date` DATE NULL,
    MODIFY `total_orders` INT NULL DEFAULT 0,
    MODIFY `total_purchase_volume` DECIMAL(10,2) NULL DEFAULT 0.00,
    MODIFY `reminder_date` DATE NULL,
    MODIFY `acquisition_source` VARCHAR(100) NULL,
    MODIFY `notes` TEXT NULL,
    MODIFY `surveys_completed_count` INT NULL DEFAULT 0,
    MODIFY `last_visit_date` DATE NULL,
    MODIFY `tags` VARCHAR(255) NULL;

ALTER TABLE `matches`
    MODIFY `broadcast_time` TIME NULL,
    MODIFY `final_score_team_a` INT NULL,
    MODIFY `final_score_team_b` INT NULL,
    MODIFY `match_finished` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `predictions`
    MODIFY `crm_matched` TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY `customer_exists` TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY `attended_match_time` TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Create KEY Story settings table
CREATE TABLE IF NOT EXISTS `key_story_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NULL,
    `subtitle` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `image` VARCHAR(500) NULL,
    `gallery` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default KEY Story
INSERT INTO `key_story_settings` (`title`, `subtitle`, `description`, `active`) 
VALUES (
    'داستان KEY',
    'سفری در دل طعم و معنا',
    'KEY رستوران و کافه، جایی که هر لحظه، خاطره‌ای تازه می‌سازیم. از بهترین مواد اولیه تا خدمات بی‌نظیر، همه چیز اینجا برای شما است.',
    1
);

-- 3. Create Pool Leads table
CREATE TABLE IF NOT EXISTS `pool_leads` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `acquisition_source` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `status` ENUM('new', 'contacted', 'converted', 'rejected') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pool_mobile` (`mobile`),
    KEY `idx_pool_source` (`acquisition_source`),
    KEY `idx_pool_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Traffic Analytics tables
CREATE TABLE IF NOT EXISTS `traffic_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `country` VARCHAR(100) NULL,
    `city` VARCHAR(100) NULL,
    `isp` VARCHAR(255) NULL,
    `referrer` VARCHAR(500) NULL,
    `landing_page` VARCHAR(500) NULL,
    `user_agent` TEXT NULL,
    `browser` VARCHAR(100) NULL,
    `os` VARCHAR(100) NULL,
    `device` VARCHAR(50) NULL,
    `language` VARCHAR(10) NULL,
    `visit_duration` INT NULL,
    `pages_viewed` INT NOT NULL DEFAULT 1,
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_traffic_session` (`session_id`),
    KEY `idx_traffic_ip` (`ip_address`),
    KEY `idx_traffic_date` (`created_at`),
    KEY `idx_traffic_country` (`country`),
    KEY `idx_traffic_referrer` (`referrer`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_sources` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_name` VARCHAR(100) NOT NULL,
    `source_type` ENUM('direct', 'organic', 'social', 'referral', 'campaign') NOT NULL,
    `visits_count` INT NOT NULL DEFAULT 0,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_source_date` (`source_name`, `date`),
    KEY `idx_source_type` (`source_type`),
    KEY `idx_source_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_sessions` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_session` (`session_id`),
    KEY `idx_session_active` (`is_active`, `last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_locations` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `country` VARCHAR(100) NOT NULL,
    `city` VARCHAR(100) NULL,
    `visits_count` INT NOT NULL DEFAULT 0,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`),
    KEY `idx_location_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_statistics` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `total_visits` INT NOT NULL DEFAULT 0,
    `unique_visitors` INT NOT NULL DEFAULT 0,
    `total_page_views` INT NOT NULL DEFAULT 0,
    `bounce_rate` DECIMAL(5,2) NULL,
    `avg_duration` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add CRM attendance field
ALTER TABLE `crm_customers`
    ADD COLUMN `attended_match_event` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tags`;

-- 6. Create indexes for performance
CREATE INDEX `idx_matches_finished` ON `matches` (`match_finished`);
CREATE INDEX `idx_crm_attended` ON `crm_customers` (`attended_match_event`);
