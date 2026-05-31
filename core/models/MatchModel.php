<?php
require_once __DIR__ . '/Model.php';

class MatchModel extends Model {
    protected $table = 'matches';

    public function activeForPrediction() {
        $stmt = $this->db->query("SELECT * FROM matches WHERE is_active = 1 AND active_for_prediction = 1 AND prediction_open_at <= NOW() AND prediction_close_at >= NOW() ORDER BY match_date ASC, kickoff_time ASC");
        return $stmt->fetchAll();
    }
}
