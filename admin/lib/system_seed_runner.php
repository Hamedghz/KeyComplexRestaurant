<?php

function systemSeedTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function systemSeedColumnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureSeedRegistrySchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `seed_registry` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `setup_run_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function systemSeedDefinitions(): array {
    return [
        'key_business_coaching_standards' => ['database/seeds/seed_key_business_coaching_standards.php', 'seedKeyBusinessCoachingStandards'],
        'key_restaurant_duties_checklists' => ['database/seeds/seed_key_restaurant_duties_checklists.php', 'seedKeyRestaurantDutiesChecklists'],
        'key_restaurant_kpis' => ['database/seeds/seed_key_restaurant_kpis.php', 'seedKeyRestaurantKpis'],
        'key_restaurant_organizational_tests' => ['database/seeds/seed_key_restaurant_organizational_tests.php', 'seedKeyRestaurantOrganizationalTests'],
        'key_planner_defaults' => ['database/seeds/seed_key_planner_defaults.php', 'seedKeyPlannerDefaults'],
        'key_okr_tmo_samples' => ['database/seeds/seed_key_okr_tmo_samples.php', 'seedKeyOkrTmoSamples'],
        'key_hr_permissions' => ['database/seeds/seed_key_hr_permissions.php', 'seedKeyHrPermissions'],
        'key_hr_navigation' => ['database/seeds/seed_key_hr_navigation.php', 'seedKeyHrNavigation'],
        'hr_performance_suite' => ['database/seeds/seed_hr_performance_suite.php', 'seedHrPerformanceSuite'],
    ];
}

function calculateSeedChecksum(string $seedFile): string {
    $path = ROOT_PATH . '/' . ltrim(str_replace('\\', '/', $seedFile), '/');
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('فایل Seed قابل خواندن نیست.');
    }
    return hash_file('sha256', $path) ?: '';
}

function registerSeed(PDO $db, string $seedKey, string $seedFile): void {
    ensureSeedRegistrySchema($db);
    $checksum = calculateSeedChecksum($seedFile);
    $stmt = $db->prepare("INSERT INTO seed_registry (seed_key,seed_file,checksum,status,created_at,updated_at)
        VALUES (?,?,?,'pending',NOW(),NOW())
        ON DUPLICATE KEY UPDATE seed_file=VALUES(seed_file), status=IF(checksum<>VALUES(checksum),'pending',status), checksum=VALUES(checksum), updated_at=NOW()");
    $stmt->execute([$seedKey, $seedFile, $checksum]);
}

function registerDefaultSeeds(PDO $db): void {
    foreach (systemSeedDefinitions() as $seedKey => $definition) {
        registerSeed($db, $seedKey, $definition[0]);
    }
}

function listRegisteredSeeds(PDO $db): array {
    ensureSeedRegistrySchema($db);
    registerDefaultSeeds($db);
    $stmt = $db->query('SELECT * FROM seed_registry ORDER BY id ASC');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function systemSeedNormalizeResult(array $result): array {
    $inserted = (int)($result['inserted'] ?? $result['groups'] ?? $result['tests'] ?? 0);
    $updated = (int)($result['updated'] ?? 0);
    $skipped = (int)($result['skipped'] ?? 0);
    foreach (['duties', 'checklists'] as $key) {
        if (isset($result[$key]) && is_array($result[$key])) {
            $inserted += (int)($result[$key]['inserted'] ?? 0);
            $updated += (int)($result[$key]['updated'] ?? 0);
            $skipped += (int)($result[$key]['skipped'] ?? 0);
        }
    }
    return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'details' => $result];
}

function updateSeedStatus(PDO $db, string $seedKey, string $status, array $counts = [], ?string $error = null): void {
    $stmt = $db->prepare('UPDATE seed_registry SET status=?,rows_inserted=?,rows_updated=?,rows_skipped=?,error_message=?,executed_at=IF(?="completed",NOW(),executed_at),updated_at=NOW() WHERE seed_key=?');
    $stmt->execute([$status, (int)($counts['inserted'] ?? 0), (int)($counts['updated'] ?? 0), (int)($counts['skipped'] ?? 0), $error, $status, $seedKey]);
}

function systemSetupLog(PDO $db, string $type, int $actorId, string $status, string $summary, array $details = [], ?string $error = null): void {
    ensureSeedRegistrySchema($db);
    $stmt = $db->prepare('INSERT INTO setup_run_logs (run_type,actor_user_id,status,summary,details_json,error_message) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$type, $actorId ?: null, $status, $summary, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $error]);
}

function runSeed(PDO $db, string $seedKey, array $options = []): array {
    $definitions = systemSeedDefinitions();
    if (!isset($definitions[$seedKey])) {
        throw new InvalidArgumentException('Seed ثبت‌شده معتبر نیست.');
    }
    registerSeed($db, $seedKey, $definitions[$seedKey][0]);
    updateSeedStatus($db, $seedKey, 'running');
    [$seedFile, $function] = $definitions[$seedKey];
    try {
        require_once ROOT_PATH . '/' . $seedFile;
        if (!function_exists($function)) {
            throw new RuntimeException('تابع اجرای Seed پیدا نشد.');
        }
        $result = $seedKey === 'hr_performance_suite'
            ? $function((int)($options['actor_id'] ?? 0))
            : $function($db, (int)($options['actor_id'] ?? 0));
        $normalized = systemSeedNormalizeResult(is_array($result) ? $result : []);
        updateSeedStatus($db, $seedKey, 'completed', $normalized);
        return $normalized;
    } catch (Throwable $e) {
        updateSeedStatus($db, $seedKey, 'failed', [], mb_substr($e->getMessage(), 0, 2000));
        throw $e;
    }
}

function runPendingSeeds(PDO $db, array $options = []): array {
    $results = ['ran' => [], 'errors' => []];
    foreach (listRegisteredSeeds($db) as $seed) {
        if (in_array((string)($seed['status'] ?? ''), ['completed', 'skipped'], true)) {
            continue;
        }
        try {
            $results['ran'][$seed['seed_key']] = runSeed($db, $seed['seed_key'], $options);
        } catch (Throwable $e) {
            $results['errors'][$seed['seed_key']] = 'اجرای Seed ناموفق بود.';
            safeAdminLog('Seed failed for ' . $seed['seed_key'] . ': ' . $e->getMessage());
            break;
        }
    }
    return $results;
}
