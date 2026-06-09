<?php
require_once __DIR__ . '/Model.php';

class MatchModel extends Model {
    protected $table = 'matches';

    private function hasColumn(string $column): bool {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM matches LIKE :column_name');
            $stmt->execute(['column_name' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Match column lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    public function activeForPrediction() {
        $hasAliases = $this->hasColumn('team_one_name') && $this->hasColumn('prediction_start_at') && $this->hasColumn('match_start_at');
        if (!$hasAliases) {
            $stmt = $this->db->prepare("
                SELECT *, team_a AS display_team_one, team_b AS display_team_two, prediction_open_at AS display_prediction_start_at, prediction_close_at AS display_prediction_end_at, CONCAT(match_date, ' ', kickoff_time) AS display_match_start_at, reward_points AS display_points_reward
                FROM matches
                WHERE is_active = 1
                  AND active_for_prediction = 1
                  AND status = 'active'
                  AND prediction_open_at <= NOW()
                  AND prediction_close_at >= NOW()
                ORDER BY match_date ASC, kickoff_time ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare("
            SELECT *,
                   COALESCE(team_one_name, team_a) AS display_team_one,
                   COALESCE(team_two_name, team_b) AS display_team_two,
                   COALESCE(prediction_start_at, prediction_open_at) AS display_prediction_start_at,
                   COALESCE(prediction_end_at, prediction_close_at) AS display_prediction_end_at,
                   COALESCE(match_start_at, CONCAT(match_date, ' ', kickoff_time)) AS display_match_start_at,
                   COALESCE(points_reward, reward_points, 0) AS display_points_reward
            FROM matches
            WHERE COALESCE(is_active, 1) = 1
              AND COALESCE(active_for_prediction, 1) = 1
              AND COALESCE(status, 'active') = 'active'
              AND COALESCE(prediction_start_at, prediction_open_at) <= NOW()
              AND COALESCE(prediction_end_at, prediction_close_at) >= NOW()
            ORDER BY COALESCE(match_start_at, CONCAT(match_date, ' ', kickoff_time)) ASC, id ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
