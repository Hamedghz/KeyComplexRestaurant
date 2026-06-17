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
        $now = appMysqlDateTime();
        $hasTeamAliases = $this->hasColumn('team_one_name') && $this->hasColumn('team_two_name');
        $hasMatchStart = $this->hasColumn('match_start_at');
        $hasPointsReward = $this->hasColumn('points_reward');
        $hasPredictionWindow = $this->hasColumn('prediction_start_at') && $this->hasColumn('prediction_end_at');

        $teamOneSelect = $hasTeamAliases ? 'COALESCE(team_one_name, team_a)' : 'team_a';
        $teamTwoSelect = $hasTeamAliases ? 'COALESCE(team_two_name, team_b)' : 'team_b';
        $matchStartSelect = $hasMatchStart ? "COALESCE(match_start_at, CONCAT(match_date, ' ', kickoff_time))" : "CONCAT(match_date, ' ', kickoff_time)";
        $pointsSelect = $hasPointsReward ? 'COALESCE(points_reward, reward_points, 0)' : 'reward_points';
        $predictionStartColumn = $hasPredictionWindow ? 'prediction_start_at' : 'prediction_open_at';
        $predictionEndColumn = $hasPredictionWindow ? 'prediction_end_at' : 'prediction_close_at';

        if (!$hasPredictionWindow) {
            $stmt = $this->db->prepare("
                SELECT *,
                       {$teamOneSelect} AS display_team_one,
                       {$teamTwoSelect} AS display_team_two,
                       {$predictionStartColumn} AS display_prediction_start_at,
                       {$predictionEndColumn} AS display_prediction_end_at,
                       {$matchStartSelect} AS display_match_start_at,
                       {$pointsSelect} AS display_points_reward
                FROM matches
                WHERE is_active = 1
                  AND active_for_prediction = 1
                  AND {$predictionStartColumn} <= :now_start
                  AND {$predictionEndColumn} >= :now_end
                ORDER BY match_date ASC, kickoff_time ASC
            ");
            $stmt->execute(['now_start' => $now, 'now_end' => $now]);
            return array_values(array_filter($stmt->fetchAll(), static function (array $match): bool {
                return isMatchOpenForPrediction($match);
            }));
        }

        $stmt = $this->db->prepare("
            SELECT *,
                   {$teamOneSelect} AS display_team_one,
                   {$teamTwoSelect} AS display_team_two,
                   prediction_start_at AS display_prediction_start_at,
                   prediction_end_at AS display_prediction_end_at,
                   {$matchStartSelect} AS display_match_start_at,
                   {$pointsSelect} AS display_points_reward
            FROM matches
            WHERE is_active = 1
              AND active_for_prediction = 1
              AND prediction_start_at <= :now_start
              AND prediction_end_at >= :now_end
            ORDER BY {$matchStartSelect} ASC, id ASC
        ");
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        return array_values(array_filter($stmt->fetchAll(), static function (array $match): bool {
            return isMatchOpenForPrediction($match);
        }));
    }
}
