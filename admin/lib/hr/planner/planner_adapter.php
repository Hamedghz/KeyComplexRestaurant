<?php

require_once __DIR__ . '/planner_service.php';

if (!function_exists('plannerCreateLinkedTask')) {
    function plannerCreateLinkedTask(PDO $db, array $payload, array $actor = []): int {
        $actor = $actor ?: [
            'id' => (int)($payload['assigned_by'] ?? $payload['owner_user_id'] ?? 0),
            'role' => 'system',
            'department' => (string)($payload['department'] ?? ''),
        ];
        $taskPayload = plannerNormalizeTaskPayload($payload, $actor);
        $taskId = plannerCreateTask($db, $taskPayload);
        plannerInsertLog($db, $taskId, (int)($actor['id'] ?? 0), 'linked_create', null, $taskPayload['status'], (string)($payload['note'] ?? ''));
        return $taskId;
    }
}

if (!function_exists('plannerCreateChecklistTask')) {
    function plannerCreateChecklistTask(PDO $db, array $payload, array $actor = []): int {
        $payload['source_module'] = $payload['source_module'] ?? 'checklist';
        $payload['source_entity_type'] = $payload['source_entity_type'] ?? 'checklist_issue';
        return plannerCreateLinkedTask($db, $payload, $actor);
    }
}

if (!function_exists('plannerCreateKpiCorrectiveTask')) {
    function plannerCreateKpiCorrectiveTask(PDO $db, array $payload, array $actor = []): int {
        $payload['source_module'] = $payload['source_module'] ?? 'kpi';
        $payload['source_entity_type'] = $payload['source_entity_type'] ?? 'corrective_action';
        return plannerCreateLinkedTask($db, $payload, $actor);
    }
}

if (!function_exists('plannerCreateOkrActionTask')) {
    function plannerCreateOkrActionTask(PDO $db, array $payload, array $actor = []): int {
        $payload['source_module'] = $payload['source_module'] ?? 'okr';
        $payload['source_entity_type'] = $payload['source_entity_type'] ?? 'okr_action';
        return plannerCreateLinkedTask($db, $payload, $actor);
    }
}

if (!function_exists('plannerCreateBusinessCoachingTask')) {
    function plannerCreateBusinessCoachingTask(PDO $db, array $payload, array $actor = []): int {
        $payload['source_module'] = $payload['source_module'] ?? 'business_coaching';
        $payload['source_entity_type'] = $payload['source_entity_type'] ?? 'standard_follow_up';
        return plannerCreateLinkedTask($db, $payload, $actor);
    }
}
