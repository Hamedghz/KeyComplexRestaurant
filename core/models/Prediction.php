<?php
require_once __DIR__ . '/Model.php';

class Prediction extends Model {
    protected $table = 'predictions';

    public function createWithCrmMatch($data) {
        $mobile = normalizeMobile($data['mobile'] ?? '');
        $data['mobile'] = $mobile;
        $customer = null;
        $stmt = $this->db->prepare('SELECT * FROM crm_customers WHERE mobile = :mobile LIMIT 1');
        $stmt->execute(['mobile' => $mobile]);
        $customer = $stmt->fetch();
        $data['crm_matched'] = $customer ? 1 : 0;
        $data['customer_exists'] = $customer ? 1 : 0;

        $matchStmt = $this->db->prepare('SELECT * FROM matches WHERE id = :id LIMIT 1');
        $matchStmt->execute(['id' => $data['match_id']]);
        $match = $matchStmt->fetch();
        if (!$match) {
            throw new RuntimeException('مسابقه یافت نشد.');
        }
        $now = date('Y-m-d H:i:s');
        if (!$match['is_active'] || !$match['active_for_prediction'] || $match['prediction_open_at'] > $now || $match['prediction_close_at'] < $now) {
            throw new RuntimeException('مهلت ثبت پیش‌بینی برای این مسابقه فعال نیست.');
        }

        $data['attended_match_time'] = 0;
        if ($customer) {
            $visitStmt = $this->db->prepare('SELECT COUNT(*) AS total FROM orders WHERE (customer_phone = :mobile OR user_id = :user_id) AND created_at BETWEEN :start_at AND :end_at');
            $start = $match['match_date'] . ' ' . ($match['broadcast_time'] ?: $match['kickoff_time']);
            $end = date('Y-m-d H:i:s', strtotime($start . ' +3 hours'));
            $visitStmt->execute(['mobile' => $mobile, 'user_id' => $customer['user_id'] ?? 0, 'start_at' => $start, 'end_at' => $end]);
            $data['attended_match_time'] = ((int)($visitStmt->fetch()['total'] ?? 0)) > 0 ? 1 : 0;
        }

        return $this->create($data);
    }
}
