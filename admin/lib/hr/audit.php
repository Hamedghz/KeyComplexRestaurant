<?php

if (!function_exists('hrAuditLog')) {
    function hrAuditLog(string $moduleKey, string $entityType, ?int $entityId, string $action, ?int $actorUserId = null, $oldValue = null, $newValue = null): void {
        try {
            if (!adminTableExists('hr_audit_logs')) {
                return;
            }
            $stmt = adminDb()->prepare('INSERT INTO hr_audit_logs (module_key,entity_type,entity_id,action,actor_user_id,old_value_json,new_value_json,ip_hash,user_agent_hash) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                hrModuleKey($moduleKey),
                hrModuleKey($entityType),
                $entityId ?: null,
                hrModuleKey($action),
                $actorUserId ?: null,
                $oldValue === null ? null : hrFoundationJsonEncode($oldValue),
                $newValue === null ? null : hrFoundationJsonEncode($newValue),
                hrHashRequestValue($_SERVER['REMOTE_ADDR'] ?? null),
                hrHashRequestValue($_SERVER['HTTP_USER_AGENT'] ?? null),
            ]);
        } catch (Throwable $e) {
            safeAdminLog('HR audit log failed: ' . $e->getMessage());
        }
    }
}
