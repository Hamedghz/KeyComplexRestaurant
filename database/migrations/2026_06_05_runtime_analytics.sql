
-- ============================================
-- RUNTIME ANALYTICS TABLES
-- ============================================
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`),
  KEY `idx_schema_migrations_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
