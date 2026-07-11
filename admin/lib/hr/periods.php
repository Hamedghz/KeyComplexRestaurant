<?php

if (!function_exists('hrCurrentPeriod')) {
    function hrCurrentPeriod(string $periodType = 'monthly'): ?array {
        try {
            if (!adminTableExists('hr_periods')) return null;
            $stmt = adminDb()->prepare('SELECT * FROM hr_periods WHERE period_type = ? AND status = "active" AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY starts_at DESC, id DESC LIMIT 1');
            $stmt->execute([$periodType]);
            $period = $stmt->fetch();
            return $period ?: null;
        } catch (Throwable $e) {
            safeAdminLog('HR current period lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('hrCurrentMonthKey')) {
    function hrCurrentMonthKey(): string {
        $period = hrCurrentPeriod('monthly');
        if (!empty($period['starts_at'])) {
            return substr((string)$period['starts_at'], 0, 7);
        }
        return date('Y-m');
    }
}
