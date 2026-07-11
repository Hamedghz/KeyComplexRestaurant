<?php

if (!function_exists('hrDynamicFields')) {
    function hrDynamicFields(string $moduleKey, string $entityType, ?int $entityId = null): array {
        if (!adminTableExists('hr_dynamic_fields')) return [];
        $where = ['module_key = ?', 'entity_type = ?', 'status = "active"', '(entity_id IS NULL OR entity_id = ?)'];
        $stmt = adminDb()->prepare('SELECT * FROM hr_dynamic_fields WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id ASC');
        $stmt->execute([hrModuleKey($moduleKey), hrModuleKey($entityType), $entityId ?: 0]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('hrSaveDynamicFieldValue')) {
    function hrSaveDynamicFieldValue(int $fieldId, string $moduleKey, string $entityType, int $entityId, $value, ?int $subjectUserId, ?int $submittedBy): void {
        if (!adminTableExists('hr_dynamic_field_values')) return;
        $stmt = adminDb()->prepare('INSERT INTO hr_dynamic_field_values (field_id,module_key,entity_type,entity_id,subject_user_id,value_json,submitted_by) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$fieldId, hrModuleKey($moduleKey), hrModuleKey($entityType), $entityId, $subjectUserId ?: null, hrFoundationJsonEncode($value), $submittedBy ?: null]);
    }
}
