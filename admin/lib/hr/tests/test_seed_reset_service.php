<?php

require_once dirname(__DIR__, 2) . '/admin_schema.php';
require_once dirname(__DIR__, 2) . '/system_seed_runner.php';

function hrTestSeedResetAllowedTables(): array {
    return [
        'hr_assessment_tests',
        'hr_test_dimensions',
        'hr_test_questions',
        'hr_test_options',
        'hr_test_scoring_rules',
        'hr_test_assignments',
        'hr_test_attempts',
        'hr_test_responses',
        'hr_test_results',
        'hr_test_retake_requests',
        'hr_test_audit_logs',
        'hr_tests',
        'test_questions',
        'assessment_questions',
        'employee_assessment_questions',
        'hr_assessment_questions',
        'test_options',
        'test_results',
        'employee_test_results',
    ];
}

function hrTestSeedResetDependencyOrder(): array {
    return [
        'hr_test_results',
        'hr_test_responses',
        'hr_test_attempts',
        'hr_test_assignments',
        'hr_test_retake_requests',
        'hr_test_scoring_rules',
        'hr_test_options',
        'hr_test_questions',
        'hr_test_dimensions',
        'hr_assessment_tests',
        'hr_test_audit_logs',
        'employee_test_results',
        'test_results',
        'test_options',
        'test_questions',
        'assessment_questions',
        'employee_assessment_questions',
        'hr_assessment_questions',
        'hr_tests',
    ];
}

function hrTestSeedResetQuoteIdentifier(string $identifier): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('شناسه جدول آزمون معتبر نیست.');
    }
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function hrDetectExistingTestTables(PDO $db): array {
    $allowed = hrTestSeedResetAllowedTables();
    if (!$allowed) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $db->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)");
    $stmt->execute($allowed);
    $found = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $tables = [];
    foreach ($allowed as $table) {
        if (!isset($found[$table])) {
            continue;
        }
        $count = (int)$db->query('SELECT COUNT(*) FROM ' . hrTestSeedResetQuoteIdentifier($table))->fetchColumn();
        $tables[$table] = ['table' => $table, 'rows' => $count];
    }
    return $tables;
}

function hrArchiveTestTable(PDO $db, string $tableName, string $suffix): array {
    if (!in_array($tableName, hrTestSeedResetAllowedTables(), true)) {
        throw new InvalidArgumentException('جدول خارج از محدوده مجاز Reset آزمون است.');
    }
    $existing = hrDetectExistingTestTables($db);
    if (!isset($existing[$tableName])) {
        return ['table' => $tableName, 'archived' => false, 'rows' => 0, 'backup_table' => null, 'reason' => 'missing'];
    }
    $rows = (int)$existing[$tableName]['rows'];
    if ($rows <= 0) {
        return ['table' => $tableName, 'archived' => false, 'rows' => 0, 'backup_table' => null, 'reason' => 'empty'];
    }
    $backupTable = 'backup_' . $tableName . '_' . $suffix;
    $db->exec('CREATE TABLE ' . hrTestSeedResetQuoteIdentifier($backupTable) . ' AS SELECT * FROM ' . hrTestSeedResetQuoteIdentifier($tableName));
    return ['table' => $tableName, 'archived' => true, 'rows' => $rows, 'backup_table' => $backupTable];
}

function hrArchiveOldTestData(PDO $db): array {
    $suffix = date('Ymd_His');
    $result = ['suffix' => $suffix, 'tables' => [], 'rows_archived' => 0];
    foreach (hrTestSeedResetDependencyOrder() as $table) {
        if (!in_array($table, hrTestSeedResetAllowedTables(), true)) {
            continue;
        }
        $archive = hrArchiveTestTable($db, $table, $suffix);
        $result['tables'][$table] = $archive;
        if (!empty($archive['archived'])) {
            $result['rows_archived'] += (int)$archive['rows'];
        }
    }
    return $result;
}

function hrDeleteOldTestData(PDO $db): array {
    $existing = hrDetectExistingTestTables($db);
    $result = ['tables' => [], 'rows_deleted' => 0];
    $db->beginTransaction();
    try {
        foreach (hrTestSeedResetDependencyOrder() as $table) {
            if (!isset($existing[$table])) {
                continue;
            }
            $deleted = (int)$db->exec('DELETE FROM ' . hrTestSeedResetQuoteIdentifier($table));
            $result['tables'][$table] = $deleted;
            $result['rows_deleted'] += $deleted;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    return $result;
}

function hrDisableLegacyTestSeed(PDO $db): array {
    ensureSeedRegistrySchema($db);
    $updated = 0;
    $stmt = $db->prepare("UPDATE seed_registry SET status='skipped', error_message=?, updated_at=NOW() WHERE seed_file=? OR seed_key=?");
    $stmt->execute([
        'Legacy restaurant HR tests seed is disabled. Use key_restaurant_organizational_tests.',
        'database/seeds/seed_restaurant_hr_tests.php',
        'seed_restaurant_hr_tests',
    ]);
    $updated += $stmt->rowCount();

    if (function_exists('hrEnsureCoreSchema')) {
        hrEnsureCoreSchema($db);
    }
    if (systemSeedTableExists($db, 'hr_module_settings')) {
        $setting = json_encode(['disabled' => true, 'canonical_seed' => 'key_restaurant_organizational_tests'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $settingsStmt = $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json), updated_by=VALUES(updated_by), updated_at=NOW()');
        $settingsStmt->execute(['hr_tests', 'legacy_hr_tests_seed_disabled', $setting, null]);
        $updated += $settingsStmt->rowCount();
    }
    return ['legacy_seed_disabled' => true, 'rows_updated' => $updated];
}

function hrRunNewRestaurantTestSeed(PDO $db): array {
    registerDefaultSeeds($db);
    return runSeed($db, 'key_restaurant_organizational_tests');
}

function hrVerifyNewTestSeed(PDO $db): array {
    $requiredCodes = [
        'KEY_ORG_BEHAVIOR',
        'KEY_RESTAURANT_OPERATIONS',
        'KEY_SALES_CUSTOMER_INTERACTION',
        'KEY_MARKETING_CONTENT',
        'KEY_KPI_REPORTING_LITERACY',
    ];
    $summary = ['ok' => false, 'tests' => 0, 'questions' => 0, 'options' => 0, 'missing_test_codes' => $requiredCodes];
    foreach (['hr_assessment_tests', 'hr_test_questions', 'hr_test_options'] as $table) {
        if (!systemSeedTableExists($db, $table)) {
            return $summary;
        }
    }
    $placeholders = implode(',', array_fill(0, count($requiredCodes), '?'));
    $stmt = $db->prepare("SELECT test_code FROM hr_assessment_tests WHERE test_code IN ($placeholders)");
    $stmt->execute($requiredCodes);
    $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $summary['missing_test_codes'] = array_values(array_diff($requiredCodes, $found));
    $summary['tests'] = count($found);
    $summary['questions'] = (int)$db->query('SELECT COUNT(*) FROM hr_test_questions q JOIN hr_assessment_tests t ON t.id=q.test_id WHERE t.test_code LIKE "KEY_%"')->fetchColumn();
    $summary['options'] = (int)$db->query('SELECT COUNT(*) FROM hr_test_options o JOIN hr_test_questions q ON q.id=o.question_id JOIN hr_assessment_tests t ON t.id=q.test_id WHERE t.test_code LIKE "KEY_%"')->fetchColumn();
    $summary['ok'] = !$summary['missing_test_codes'] && $summary['questions'] > 0 && $summary['options'] > 0;
    return $summary;
}

function hrVerifyOldQuestionsRemoved(PDO $db): array {
    $legacyCodes = ['DISC','MBTI_ORG','ORG_EQ','JOB_SATISFACTION','ORG_COMMITMENT','BURNOUT_RISK','ROLE_FIT','TEAM_READINESS','CUSTOMER_READINESS','RUSH_STRESS','MENU_KNOWLEDGE','SERVICE_STANDARDS','HYGIENE_SAFETY','UPSELL'];
    $result = ['ok' => true, 'old_tests_remaining' => 0, 'old_questions_remaining' => 0, 'old_seed_registered' => false];
    if (systemSeedTableExists($db, 'hr_assessment_tests')) {
        $placeholders = implode(',', array_fill(0, count($legacyCodes), '?'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM hr_assessment_tests WHERE test_code IN ($placeholders)");
        $stmt->execute($legacyCodes);
        $result['old_tests_remaining'] = (int)$stmt->fetchColumn();
    }
    if (systemSeedTableExists($db, 'hr_test_questions')) {
        $prefixWhere = implode(' OR ', array_fill(0, count($legacyCodes), 'code LIKE ?'));
        $stmt = $db->prepare('SELECT COUNT(*) FROM hr_test_questions WHERE ' . $prefixWhere);
        $stmt->execute(array_map(static fn(string $code): string => $code . '\_%', $legacyCodes));
        $result['old_questions_remaining'] = (int)$stmt->fetchColumn();
    }
    if (systemSeedTableExists($db, 'seed_registry')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM seed_registry WHERE (seed_file=? OR seed_key=?) AND status<>'skipped'");
        $stmt->execute(['database/seeds/seed_restaurant_hr_tests.php', 'seed_restaurant_hr_tests']);
        $result['old_seed_registered'] = (int)$stmt->fetchColumn() > 0;
    }
    $result['ok'] = $result['old_tests_remaining'] === 0 && $result['old_questions_remaining'] === 0 && !$result['old_seed_registered'];
    return $result;
}

function hrResetRestaurantOrganizationalTests(PDO $db, int $actorUserId, array $options = []): array {
    $confirmation = trim((string)($options['confirmation'] ?? ''));
    if ($confirmation !== 'RESET_HR_TESTS') {
        throw new RuntimeException('برای Reset بانک آزمون باید عبارت RESET_HR_TESTS دقیق وارد شود.');
    }
    if ($actorUserId <= 0 || !systemSeedTableExists($db, 'admins')) {
        throw new RuntimeException('کاربر مجاز برای Reset آزمون شناسایی نشد.');
    }
    $actorStmt = $db->prepare('SELECT id,role FROM admins WHERE id=? LIMIT 1');
    $actorStmt->execute([$actorUserId]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$actor || (string)($actor['role'] ?? '') !== 'super_admin') {
        throw new RuntimeException('فقط super_admin می‌تواند Reset بانک آزمون را اجرا کند.');
    }

    $detected = hrDetectExistingTestTables($db);
    $archive = hrArchiveOldTestData($db);
    $delete = hrDeleteOldTestData($db);
    $legacy = hrDisableLegacyTestSeed($db);
    $seed = hrRunNewRestaurantTestSeed($db);
    $newVerification = hrVerifyNewTestSeed($db);
    $oldVerification = hrVerifyOldQuestionsRemoved($db);
    $result = [
        'detected_tables' => $detected,
        'archive' => $archive,
        'delete' => $delete,
        'legacy' => $legacy,
        'seed' => $seed,
        'new_seed_verification' => $newVerification,
        'old_seed_verification' => $oldVerification,
    ];
    systemSetupLog($db, 'reset_hr_test_seed_only', $actorUserId, ($newVerification['ok'] && $oldVerification['ok']) ? 'completed' : 'failed', 'Reset ایمن بانک آزمون سازمانی رستوران KEY', $result, ($newVerification['ok'] && $oldVerification['ok']) ? null : 'Verification failed after test seed reset.');
    return $result;
}
