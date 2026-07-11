<?php

$root = dirname(__DIR__);
require_once $root . '/database/seeds/seed_key_kpis.php';

$definitions = keyKpiDefinitions();
if (count($definitions) < 50) {
    throw new RuntimeException('Expected at least 50 KEY KPI definitions after coaching metrics.');
}

$codes = [];
$byCalculationType = [];
foreach ($definitions as $kpi) {
    [$kpiCode, $title, $department, $roleCode, $unitLabel, $targetValue, $weight, $direction, $calculationType] = $kpi;
    if ($kpiCode === '' || $title === '' || $department === '' || $unitLabel === '') {
        throw new RuntimeException('KPI definition has required empty fields.');
    }
    if (!in_array($direction, ['positive', 'negative'], true)) {
        throw new RuntimeException('Invalid KPI direction: ' . $direction);
    }
    if (!in_array($calculationType, ['manual_score', 'simple_percent', 'bsf_component'], true)) {
        throw new RuntimeException('Invalid KPI calculation type: ' . $calculationType);
    }
    $codes[] = $kpiCode;
    $byCalculationType[$calculationType] = ($byCalculationType[$calculationType] ?? 0) + 1;
}
if (count($codes) !== count(array_unique($codes))) {
    throw new RuntimeException('Duplicate kpi_code found.');
}
if (($byCalculationType['bsf_component'] ?? 0) !== 5) {
    throw new RuntimeException('Expected 5 BSF component KPIs.');
}
foreach (['script_compliance_score', 'listening_score', 'fab_usage_score', 'objection_handling_score'] as $requiredCode) {
    if (!in_array($requiredCode, $codes, true)) {
        throw new RuntimeException('Missing business coaching KPI: ' . $requiredCode);
    }
}

$seed = file_get_contents($root . '/database/seeds/seed_key_kpis.php') ?: '';
foreach (['hr_kpi_definitions', 'kpi_code', 'seedKeyKpis', 'keyKpiDefinitions'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('KPI seed missing required token: ' . $needle);
    }
}
if (preg_match('/INSERT\s+INTO\s+`?(hr_kpi_assignments|hr_kpi_entries|employee_score_history|admins|users)/i', $seed)) {
    throw new RuntimeException('KPI seed must not create assignments, entries, scores, users, or admins.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_14_key_kpis_seed.sql') ?: '';
foreach (['CREATE TABLE IF NOT EXISTS `hr_kpi_definitions`', '`kpi_code`', '`direction`', '`calculation_type`', '`weight`'] as $needle) {
    if (strpos($migration, $needle) === false) {
        throw new RuntimeException('KPI migration missing required token: ' . $needle);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('KPI migration must not contain destructive SQL.');
}

$doc = file_get_contents($root . '/docs/hr-imports/kpi-source-map.md') ?: '';
foreach (['50 KPI definitions', 'BSF', 'Marketing and sales', 'Sales coaching standards', 'Internal manager'] as $needle) {
    if (strpos($doc, $needle) === false) {
        throw new RuntimeException('KPI source map missing ' . $needle);
    }
}

echo "key_kpis_seed_contract_test: OK\n";
