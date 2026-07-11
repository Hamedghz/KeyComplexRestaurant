<?php

require_once __DIR__ . '/planner_repository.php';

if (!function_exists('plannerReportSummary')) {
    function plannerReportSummary(PDO $db, array $admin, array $filters = []): array {
        $where = [];
        $params = [];
        plannerApplyVisibilityWhere($where, $params, $admin);
        $base = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $summary = [
            'today' => 0,
            'tomorrow' => 0,
            'overdue' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'done' => 0,
            'linked' => 0,
        ];
        try {
            $stmt = $db->prepare('SELECT status, COUNT(*) AS total FROM planner_tasks' . $base . ' GROUP BY status');
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = (string)$row['status'];
                if (array_key_exists($key, $summary)) {
                    $summary[$key] = (int)$row['total'];
                }
            }

            $dateChecks = [
                'today' => ['task_date = ?', date('Y-m-d')],
                'tomorrow' => ['task_date = ?', date('Y-m-d', strtotime('+1 day'))],
                'overdue' => ["task_date < ? AND status NOT IN ('done','cancelled')", date('Y-m-d')],
            ];
            foreach ($dateChecks as $key => $check) {
                $localWhere = $where;
                $localParams = $params;
                $localWhere[] = $check[0];
                $localParams[] = $check[1];
                $stmt = $db->prepare('SELECT COUNT(*) FROM planner_tasks WHERE ' . implode(' AND ', $localWhere));
                $stmt->execute($localParams);
                $summary[$key] = (int)$stmt->fetchColumn();
            }

            $linkedWhere = $where;
            $linkedParams = $params;
            $linkedWhere[] = '(source_module IS NOT NULL OR linked_objective_id IS NOT NULL OR linked_kpi_score_id IS NOT NULL OR linked_checklist_item_id IS NOT NULL OR linked_customer_id IS NOT NULL OR linked_followup_id IS NOT NULL)';
            $stmt = $db->prepare('SELECT COUNT(*) FROM planner_tasks WHERE ' . implode(' AND ', $linkedWhere));
            $stmt->execute($linkedParams);
            $summary['linked'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            safeAdminLog('Planner report summary failed: ' . $e->getMessage());
        }
        return $summary;
    }
}
