<?php
require_once __DIR__ . '/Model.php';

class CrmCustomer extends Model {
    protected $table = 'crm_customers';

    public function findByMobile($mobile) {
        $stmt = $this->db->prepare('SELECT * FROM crm_customers WHERE mobile = :mobile LIMIT 1');
        $stmt->execute(['mobile' => normalizeMobile($mobile)]);
        return $stmt->fetch();
    }

    public function timeline($id) {
        $stmt = $this->db->prepare('SELECT * FROM crm_timelines WHERE customer_id = :id ORDER BY event_date DESC, id DESC');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function purchases($mobile) {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE customer_phone = :mobile OR customer_phone = :raw ORDER BY created_at DESC LIMIT 50');
        $normalized = normalizeMobile($mobile);
        $stmt->execute(['mobile' => $normalized, 'raw' => $mobile]);
        return $stmt->fetchAll();
    }

    public function predictions($mobile) {
        $stmt = $this->db->prepare('SELECT p.*, CONCAT(COALESCE(m.team_one_name, m.team_a), " - ", COALESCE(m.team_two_name, m.team_b)) AS match_title, COALESCE(m.match_start_at, CONCAT(m.match_date, " ", m.kickoff_time)) AS match_start_at FROM predictions p LEFT JOIN matches m ON p.match_id = m.id WHERE p.mobile = :mobile OR p.mobile = :raw OR p.customer_mobile = :mobile OR p.customer_mobile = :raw ORDER BY p.created_at DESC LIMIT 50');
        $normalized = normalizeMobile($mobile);
        $stmt->execute(['mobile' => $normalized, 'raw' => $mobile]);
        return $stmt->fetchAll();
    }

    public function predictionSummary($mobile) {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total_predictions, SUM(CASE WHEN COALESCE(is_winner, is_correct_prediction, 0) = 1 THEN 1 ELSE 0 END) AS winner_count, SUM(CASE WHEN COALESCE(wants_reservation, reserve_table_interest, 0) = 1 THEN 1 ELSE 0 END) AS reservation_interest_count, MAX(created_at) AS last_prediction_date FROM predictions WHERE mobile = :mobile OR mobile = :raw OR customer_mobile = :mobile OR customer_mobile = :raw');
        $normalized = normalizeMobile($mobile);
        $stmt->execute(['mobile' => $normalized, 'raw' => $mobile]);
        return $stmt->fetch() ?: ['total_predictions' => 0, 'winner_count' => 0, 'reservation_interest_count' => 0, 'last_prediction_date' => null];
    }

    public function surveyResponses($mobile) {
        $stmt = $this->db->prepare('SELECT sr.*, df.form_title_fa AS form_title FROM survey_responses sr LEFT JOIN dynamic_forms df ON sr.form_id = df.id WHERE sr.customer_phone = :mobile OR sr.customer_phone = :raw ORDER BY sr.submitted_at DESC LIMIT 50');
        $normalized = normalizeMobile($mobile);
        $stmt->execute(['mobile' => $normalized, 'raw' => $mobile]);
        return $stmt->fetchAll();
    }
}
