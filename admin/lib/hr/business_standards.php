<?php

if (!function_exists('hrBusinessStandards')) {
    function hrBusinessStandards(string $group = '', bool $activeOnly = true): array {
        if (!adminTableExists('business_standards')) return [];
        $where = ['1=1'];
        $params = [];
        if ($group !== '') {
            $where[] = 'standard_group = ?';
            $params[] = hrModuleKey($group);
        }
        if ($activeOnly) {
            $where[] = 'status = "active"';
        }
        $stmt = adminDb()->prepare('SELECT * FROM business_standards WHERE ' . implode(' AND ', $where) . ' ORDER BY standard_group ASC, title ASC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('hrBusinessStandardItems')) {
    function hrBusinessStandardItems(int $standardId, bool $activeOnly = true): array {
        if (!adminTableExists('business_standard_items')) return [];
        $sql = 'SELECT * FROM business_standard_items WHERE standard_id = ?';
        if ($activeOnly) $sql .= ' AND status = "active"';
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = adminDb()->prepare($sql);
        $stmt->execute([$standardId]);
        return $stmt->fetchAll();
    }
}
