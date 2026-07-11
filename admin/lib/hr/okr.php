<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/planner/planner_adapter.php';
require_once __DIR__ . '/kpis.php';

function okrEnsureSchema(?PDO $db = null): void {
    $db = $db ?: adminDb();
    hrEnsureCoreSchema($db);
    plannerEnsureSchema($db);
    hrKpiEnsureSchema($db);
    $migration = dirname(__DIR__, 3) . '/database/migrations/2026_07_18_okr_tmo_management.sql';
    if (is_readable($migration)) {
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($migration)) ?: '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $db->exec($statement);
            } catch (Throwable $e) {
                safeAdminLog('OKR/TMO schema statement failed: ' . $e->getMessage());
            }
        }
    }
}

function okrStart(string $title, string $role = 'manager'): array {
    $admin = adminGuard($role);
    $db = adminDb();
    okrEnsureSchema($db);
    $pageTitle = $title;
    return [$db, $admin, $pageTitle];
}

function okrAlert(string $message, string $type = 'info'): void {
    if ($message !== '') {
        echo '<div class="alert alert-' . h($type) . '">' . h($message) . '</div>';
    }
}

function okrClamp($value): float {
    return max(0.0, min(100.0, (float)$value));
}

function okrFinalProgress(?float $manual, float $calculated): float {
    return $manual === null ? okrClamp($calculated) : max(okrClamp($manual), okrClamp($calculated));
}

function okrFetchAll(PDO $db, string $sql, array $params = []): array {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        safeAdminLog('OKR query failed: ' . $e->getMessage());
        return [];
    }
}

function okrUsers(PDO $db): array {
    return okrFetchAll($db, 'SELECT id, username, full_name, role, department FROM admins WHERE is_active=1 ORDER BY department, full_name, username');
}

function okrSaveObjective(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('عنوان هدف الزامی است.');
    }
    $scopeType = in_array((string)($input['scope_type'] ?? 'company'), ['company','department','team'], true) ? (string)$input['scope_type'] : 'company';
    $targetMonth = trim((string)($input['target_month'] ?? ''));
    if ($targetMonth !== '' && !preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        $targetMonth = date('Y-m');
    }
    $manual = ($input['manual_progress_percent'] ?? '') === '' ? null : okrClamp($input['manual_progress_percent']);
    $data = [
        'title' => $title,
        'description' => trim((string)($input['description'] ?? '')) ?: null,
        'period_id' => ($input['period_id'] ?? '') === '' ? null : (int)$input['period_id'],
        'target_month' => $targetMonth ?: date('Y-m'),
        'scope_type' => $scopeType,
        'scope_id' => trim((string)($input['scope_id'] ?? '')) ?: null,
        'owner_user_id' => (int)($input['owner_user_id'] ?? $admin['id']),
        'tmo_user_id' => (int)($input['tmo_user_id'] ?? 0) ?: null,
        'status' => in_array((string)($input['status'] ?? 'draft'), ['draft','active','reviewed','closed','archived'], true) ? (string)$input['status'] : 'draft',
        'manual_progress_percent' => $manual,
        'updated_by' => (int)$admin['id'],
    ];
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $data['id'] = $id;
        $db->prepare('UPDATE okr_objectives SET title=:title,description=:description,period_id=:period_id,target_month=:target_month,scope_type=:scope_type,scope_id=:scope_id,owner_user_id=:owner_user_id,tmo_user_id=:tmo_user_id,status=:status,manual_progress_percent=:manual_progress_percent,updated_by=:updated_by WHERE id=:id')->execute($data);
    } else {
        $data['created_by'] = (int)$admin['id'];
        $db->prepare('INSERT INTO okr_objectives (title,description,period_id,target_month,scope_type,scope_id,owner_user_id,tmo_user_id,status,manual_progress_percent,created_by,updated_by) VALUES (:title,:description,:period_id,:target_month,:scope_type,:scope_id,:owner_user_id,:tmo_user_id,:status,:manual_progress_percent,:created_by,:updated_by)')->execute($data);
        $id = (int)$db->lastInsertId();
    }
    okrRecalculateObjective($db, $id, (int)$admin['id'], 'manual');
    return $id;
}

function okrSaveKr(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $objectiveId = (int)($input['objective_id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    if ($objectiveId <= 0 || $title === '') {
        throw new RuntimeException('هدف و عنوان KR الزامی است.');
    }
    $manual = ($input['manual_progress_percent'] ?? '') === '' ? null : okrClamp($input['manual_progress_percent']);
    $krType = in_array((string)($input['kr_type'] ?? 'numeric'), ['numeric','descriptive'], true) ? (string)$input['kr_type'] : 'numeric';
    $data = [
        'objective_id' => $objectiveId,
        'title' => $title,
        'description' => trim((string)($input['description'] ?? '')) ?: null,
        'kr_type' => $krType,
        'target_value' => ($input['target_value'] ?? '') === '' ? null : (float)$input['target_value'],
        'current_value' => ($input['current_value'] ?? '') === '' ? null : (float)$input['current_value'],
        'unit_label' => trim((string)($input['unit_label'] ?? '')) ?: null,
        'weight' => max(0.01, (float)($input['weight'] ?? 1)),
        'manual_progress_percent' => $manual,
        'status' => trim((string)($input['status'] ?? 'active')) ?: 'active',
        'updated_by' => (int)$admin['id'],
    ];
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $data['id'] = $id;
        $db->prepare('UPDATE okr_key_results SET objective_id=:objective_id,title=:title,description=:description,kr_type=:kr_type,target_value=:target_value,current_value=:current_value,unit_label=:unit_label,weight=:weight,manual_progress_percent=:manual_progress_percent,status=:status,updated_by=:updated_by WHERE id=:id')->execute($data);
    } else {
        $data['created_by'] = (int)$admin['id'];
        $db->prepare('INSERT INTO okr_key_results (objective_id,title,description,kr_type,target_value,current_value,unit_label,weight,manual_progress_percent,status,created_by,updated_by) VALUES (:objective_id,:title,:description,:kr_type,:target_value,:current_value,:unit_label,:weight,:manual_progress_percent,:status,:created_by,:updated_by)')->execute($data);
        $id = (int)$db->lastInsertId();
    }
    okrRecalculateKr($db, $id, (int)$admin['id'], 'manual');
    return $id;
}

function okrSaveAction(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $objectiveId = (int)($input['objective_id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    if ($objectiveId <= 0 || $title === '') {
        throw new RuntimeException('هدف و عنوان اقدام الزامی است.');
    }
    $manual = ($input['manual_progress_percent'] ?? '') === '' ? null : okrClamp($input['manual_progress_percent']);
    $status = in_array((string)($input['status'] ?? 'pending'), ['pending','in_progress','done','cancelled','overdue'], true) ? (string)$input['status'] : 'pending';
    $data = [
        'objective_id' => $objectiveId,
        'kr_id' => ($input['kr_id'] ?? '') === '' ? null : (int)$input['kr_id'],
        'title' => $title,
        'description' => trim((string)($input['description'] ?? '')) ?: null,
        'owner_user_id' => (int)($input['owner_user_id'] ?? $admin['id']),
        'department' => trim((string)($input['department'] ?? '')) ?: null,
        'due_date' => parsePersianDate($input['due_date'] ?? '', false) ?: null,
        'priority' => in_array((string)($input['priority'] ?? 'normal'), ['low','normal','high','urgent'], true) ? (string)$input['priority'] : 'normal',
        'status' => $status,
        'manual_progress_percent' => $manual,
        'updated_by' => (int)$admin['id'],
    ];
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $data['id'] = $id;
        $db->prepare('UPDATE okr_actions SET objective_id=:objective_id,kr_id=:kr_id,title=:title,description=:description,owner_user_id=:owner_user_id,department=:department,due_date=:due_date,priority=:priority,status=:status,manual_progress_percent=:manual_progress_percent,updated_by=:updated_by WHERE id=:id')->execute($data);
    } else {
        $data['created_by'] = (int)$admin['id'];
        $db->prepare('INSERT INTO okr_actions (objective_id,kr_id,title,description,owner_user_id,department,due_date,priority,status,manual_progress_percent,created_by,updated_by) VALUES (:objective_id,:kr_id,:title,:description,:owner_user_id,:department,:due_date,:priority,:status,:manual_progress_percent,:created_by,:updated_by)')->execute($data);
        $id = (int)$db->lastInsertId();
    }
    if (!empty($input['create_planner_task'])) {
        okrCreatePlannerTaskForAction($db, $admin, $id);
    }
    okrRecalculateAction($db, $id, (int)$admin['id'], 'manual');
    return $id;
}

function okrCreatePlannerTaskForAction(PDO $db, array $admin, int $actionId): ?int {
    $stmt = $db->prepare('SELECT * FROM okr_actions WHERE id=? LIMIT 1');
    $stmt->execute([$actionId]);
    $action = $stmt->fetch();
    if (!$action) return null;
    if (!empty($action['planner_task_id'])) return (int)$action['planner_task_id'];
    $taskId = plannerCreateOkrActionTask($db, [
        'title' => $action['title'],
        'description' => $action['description'],
        'owner_user_id' => (int)($action['owner_user_id'] ?: $admin['id']),
        'assigned_by' => (int)$admin['id'],
        'department' => (string)($action['department'] ?? $admin['department'] ?? ''),
        'task_date' => $action['due_date'] ?: date('Y-m-d'),
        'priority' => $action['priority'] ?: 'normal',
        'status' => $action['status'] === 'done' ? 'done' : 'pending',
        'progress_percent' => (int)($action['final_progress_percent'] ?? 0),
        'source_entity_id' => $actionId,
        'linked_objective_id' => (int)$action['objective_id'],
        'linked_kr_id' => (int)($action['kr_id'] ?? 0),
        'linked_action_id' => $actionId,
    ], $admin);
    $db->prepare('UPDATE okr_actions SET planner_task_id=?, updated_by=? WHERE id=?')->execute([$taskId, (int)$admin['id'], $actionId]);
    return $taskId;
}

function okrLinkKpi(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $objectiveId = ($input['objective_id'] ?? '') === '' ? null : (int)$input['objective_id'];
    $krId = ($input['kr_id'] ?? '') === '' ? null : (int)$input['kr_id'];
    if (!$objectiveId && !$krId) throw new RuntimeException('هدف یا KR برای اتصال KPI الزامی است.');
    $db->prepare('INSERT INTO okr_kpi_links (objective_id,kr_id,kpi_definition_id,kpi_assignment_id,weight,status) VALUES (?,?,?,?,?,"active")')
        ->execute([$objectiveId, $krId, ($input['kpi_definition_id'] ?? '') === '' ? null : (int)$input['kpi_definition_id'], ($input['kpi_assignment_id'] ?? '') === '' ? null : (int)$input['kpi_assignment_id'], max(0.01, (float)($input['weight'] ?? 1))]);
    $id = (int)$db->lastInsertId();
    if ($krId) okrRecalculateKr($db, $krId, (int)$admin['id'], 'kpi');
    if ($objectiveId) okrRecalculateObjective($db, $objectiveId, (int)$admin['id'], 'kpi');
    return $id;
}

function okrSaveTmoReview(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $objectiveId = (int)($input['objective_id'] ?? 0);
    if ($objectiveId <= 0) throw new RuntimeException('هدف برای ثبت مرور TMO الزامی است.');
    $reviewDate = parsePersianDate($input['review_date'] ?? '', false) ?: date('Y-m-d');
    $status = in_array((string)($input['status'] ?? 'draft'), ['draft','submitted','approved','closed'], true) ? (string)$input['status'] : 'draft';
    $db->prepare('INSERT INTO tmo_reviews (objective_id,tmo_user_id,review_date,result_summary,blockers,decisions,next_actions,final_score,status) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$objectiveId, (int)($input['tmo_user_id'] ?? $admin['id']), $reviewDate, trim((string)($input['result_summary'] ?? '')) ?: null, trim((string)($input['blockers'] ?? '')) ?: null, trim((string)($input['decisions'] ?? '')) ?: null, trim((string)($input['next_actions'] ?? '')) ?: null, ($input['final_score'] ?? '') === '' ? null : okrClamp($input['final_score']), $status]);
    $id = (int)$db->lastInsertId();
    $db->prepare('UPDATE okr_objectives SET status="reviewed", updated_by=? WHERE id=? AND status!="closed"')->execute([(int)$admin['id'], $objectiveId]);
    return $id;
}

function okrProgressLog(PDO $db, string $type, int $id, string $source, ?float $old, float $new, int $actorId, string $note = ''): void {
    try {
        $db->prepare('INSERT INTO okr_progress_logs (entity_type,entity_id,source,old_progress_percent,new_progress_percent,note,created_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$type, $id, $source, $old, okrClamp($new), $note ?: null, $actorId ?: null]);
    } catch (Throwable $e) {
        safeAdminLog('OKR progress log failed: ' . $e->getMessage());
    }
}

function okrRecalculateAction(PDO $db, int $actionId, int $actorId = 0, string $source = 'system'): float {
    $stmt = $db->prepare('SELECT a.*, p.progress_percent AS planner_progress, p.status AS planner_status FROM okr_actions a LEFT JOIN planner_tasks p ON p.id=a.planner_task_id WHERE a.id=? LIMIT 1');
    $stmt->execute([$actionId]);
    $action = $stmt->fetch();
    if (!$action) return 0.0;
    $calculated = $action['planner_task_id'] ? (float)($action['planner_progress'] ?? 0) : ((string)$action['status'] === 'done' ? 100.0 : 0.0);
    $manual = $action['manual_progress_percent'] === null ? null : (float)$action['manual_progress_percent'];
    $final = okrFinalProgress($manual, $calculated);
    $old = (float)($action['final_progress_percent'] ?? 0);
    $db->prepare('UPDATE okr_actions SET calculated_progress_percent=?, final_progress_percent=?, status=IF(?=100 AND status NOT IN ("cancelled"),"done",status) WHERE id=?')->execute([$calculated, $final, $final, $actionId]);
    okrProgressLog($db, 'action', $actionId, $source, $old, $final, $actorId);
    if (!empty($action['kr_id'])) okrRecalculateKr($db, (int)$action['kr_id'], $actorId, $source);
    okrRecalculateObjective($db, (int)$action['objective_id'], $actorId, $source);
    return $final;
}

function okrLinkedKpiProgress(PDO $db, ?int $objectiveId, ?int $krId): array {
    $where = ['l.status="active"'];
    $params = [];
    if ($krId) { $where[] = 'l.kr_id=?'; $params[] = $krId; }
    elseif ($objectiveId) { $where[] = 'l.objective_id=?'; $params[] = $objectiveId; }
    $rows = okrFetchAll($db, 'SELECT l.weight, AVG(s.score_percent) AS score_percent FROM okr_kpi_links l LEFT JOIN hr_kpi_scores s ON (s.kpi_id=l.kpi_definition_id OR s.assignment_id=l.kpi_assignment_id) WHERE ' . implode(' AND ', $where) . ' GROUP BY l.id,l.weight', $params);
    $sum = 0.0; $weight = 0.0;
    foreach ($rows as $row) {
        if ($row['score_percent'] === null) continue;
        $w = max(0.01, (float)$row['weight']);
        $sum += okrClamp($row['score_percent']) * $w;
        $weight += $w;
    }
    return [$weight > 0 ? $sum / $weight : null, $weight];
}

function okrRecalculateKr(PDO $db, int $krId, int $actorId = 0, string $source = 'system'): float {
    $stmt = $db->prepare('SELECT * FROM okr_key_results WHERE id=? LIMIT 1');
    $stmt->execute([$krId]);
    $kr = $stmt->fetch();
    if (!$kr) return 0.0;
    $actionRows = okrFetchAll($db, 'SELECT final_progress_percent FROM okr_actions WHERE kr_id=? AND status!="cancelled"', [$krId]);
    $parts = [];
    foreach ($actionRows as $row) $parts[] = (float)$row['final_progress_percent'];
    [$kpiProgress] = okrLinkedKpiProgress($db, null, $krId);
    if ($kpiProgress !== null) $parts[] = (float)$kpiProgress;
    if (($kr['kr_type'] ?? '') === 'numeric' && (float)($kr['target_value'] ?? 0) > 0 && $kr['current_value'] !== null) {
        $parts[] = okrClamp(((float)$kr['current_value'] / (float)$kr['target_value']) * 100);
    }
    $calculated = $parts ? array_sum($parts) / count($parts) : 0.0;
    $manual = $kr['manual_progress_percent'] === null ? null : (float)$kr['manual_progress_percent'];
    $final = okrFinalProgress($manual, $calculated);
    $old = (float)($kr['final_progress_percent'] ?? 0);
    $db->prepare('UPDATE okr_key_results SET calculated_progress_percent=?, final_progress_percent=? WHERE id=?')->execute([$calculated, $final, $krId]);
    okrProgressLog($db, 'kr', $krId, $source, $old, $final, $actorId);
    okrRecalculateObjective($db, (int)$kr['objective_id'], $actorId, $source);
    return $final;
}

function okrRecalculateObjective(PDO $db, int $objectiveId, int $actorId = 0, string $source = 'system'): float {
    $stmt = $db->prepare('SELECT * FROM okr_objectives WHERE id=? LIMIT 1');
    $stmt->execute([$objectiveId]);
    $objective = $stmt->fetch();
    if (!$objective) return 0.0;
    $krs = okrFetchAll($db, 'SELECT final_progress_percent, weight FROM okr_key_results WHERE objective_id=? AND status!="archived"', [$objectiveId]);
    $sum = 0.0; $weight = 0.0;
    foreach ($krs as $kr) {
        $w = max(0.01, (float)$kr['weight']);
        $sum += (float)$kr['final_progress_percent'] * $w;
        $weight += $w;
    }
    [$kpiProgress, $kpiWeight] = okrLinkedKpiProgress($db, $objectiveId, null);
    if ($kpiProgress !== null) {
        $sum += (float)$kpiProgress * max(0.01, $kpiWeight);
        $weight += max(0.01, $kpiWeight);
    }
    $calculated = $weight > 0 ? $sum / $weight : 0.0;
    $manual = $objective['manual_progress_percent'] === null ? null : (float)$objective['manual_progress_percent'];
    $final = okrFinalProgress($manual, $calculated);
    $old = (float)($objective['final_progress_percent'] ?? 0);
    $db->prepare('UPDATE okr_objectives SET calculated_progress_percent=?, final_progress_percent=? WHERE id=?')->execute([$calculated, $final, $objectiveId]);
    okrProgressLog($db, 'objective', $objectiveId, $source, $old, $final, $actorId);
    return $final;
}

function okrSeedExamples(PDO $db, array $admin): int {
    $examples = [
        ['Improve sales conversion', ['increase lead count', 'improve conversion rate', 'reduce missed follow-ups', 'improve script compliance']],
        ['Improve customer experience', ['reduce complaint resolution time', 'increase satisfaction after resolution', 'increase repeat purchase', 'improve journey stage tracking']],
        ['Improve operational discipline', ['increase checklist completion', 'improve SOP execution score', 'reduce repeated issues', 'improve 5S audit score']],
        ['Improve marketing and content', ['increase Instagram leads', 'improve engagement rate', 'improve campaign ROAS', 'increase customer referrals']],
    ];
    $created = 0;
    foreach ($examples as $index => $example) {
        $title = $example[0];
        $exists = $db->prepare('SELECT id FROM okr_objectives WHERE title=? AND target_month=? LIMIT 1');
        $exists->execute([$title, date('Y-m')]);
        $objectiveId = (int)$exists->fetchColumn();
        if (!$objectiveId) {
            $objectiveId = okrSaveObjective($db, $admin, [
                CSRF_TOKEN_NAME => $_POST[CSRF_TOKEN_NAME] ?? generateCSRFToken(),
                'title' => $title,
                'description' => 'Business coaching OKR example',
                'target_month' => date('Y-m'),
                'scope_type' => 'company',
                'owner_user_id' => (int)$admin['id'],
                'tmo_user_id' => (int)$admin['id'],
                'status' => 'draft',
                'manual_progress_percent' => '',
            ]);
            $created++;
        }
        foreach ($example[1] as $krIndex => $krTitle) {
            $krExists = $db->prepare('SELECT id FROM okr_key_results WHERE objective_id=? AND title=? LIMIT 1');
            $krExists->execute([$objectiveId, $krTitle]);
            if (!$krExists->fetchColumn()) {
                okrSaveKr($db, $admin, [
                    CSRF_TOKEN_NAME => $_POST[CSRF_TOKEN_NAME] ?? generateCSRFToken(),
                    'objective_id' => $objectiveId,
                    'title' => $krTitle,
                    'kr_type' => 'numeric',
                    'target_value' => 100,
                    'current_value' => 0,
                    'weight' => 1,
                    'status' => 'active',
                ]);
            }
        }
    }
    return $created;
}
