-- Additive setup/migration/seed registries. No seed execution or data reset occurs here.
CREATE TABLE IF NOT EXISTS `seed_registry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seed_key` varchar(190) NOT NULL,
  `seed_file` varchar(255) NOT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `batch` int NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `rows_inserted` int NOT NULL DEFAULT 0,
  `rows_updated` int NOT NULL DEFAULT 0,
  `rows_skipped` int NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_seed_registry_key` (`seed_key`),
  KEY `idx_seed_registry_status` (`status`),
  KEY `idx_seed_registry_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `setup_run_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_type` varchar(80) NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `summary` text DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setup_run_type` (`run_type`, `created_at`),
  KEY `idx_setup_run_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

