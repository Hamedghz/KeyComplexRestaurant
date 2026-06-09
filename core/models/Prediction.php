<?php
require_once __DIR__ . '/Model.php';

class Prediction extends Model {
    protected $table = 'predictions';

    private function hasColumn(string $column, string $table = 'predictions'): bool {
        try {
            $safeTable = str_replace('`', '``', $table);
            $stmt = $this->db->prepare('SHOW COLUMNS FROM `' . $safeTable . '` LIKE :column_name');
            $stmt->execute(['column_name' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Prediction column lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    private function existingColumns(string $table = 'predictions'): array {
        try {
            $safeTable = str_replace('`', '``', $table);
            $stmt = $this->db->query('SHOW COLUMNS FROM `' . $safeTable . '`');
            return array_map(static fn($row) => $row['Field'], $stmt->fetchAll());
        } catch (Throwable $e) {
            error_log('Prediction columns lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    public function createWithCrmMatch($data) {
        $mobile = normalizeMobile($data['mobile'] ?? '');
        if (!preg_match('/^0?9\d{9}$/', $mobile)) {
            throw new RuntimeException('شماره موبایل معتبر نیست.');
        }
        $scoreOne = (int)($data['predicted_team_one_score'] ?? $data['predicted_score_team_a'] ?? -1);
        $scoreTwo = (int)($data['predicted_team_two_score'] ?? $data['predicted_score_team_b'] ?? -1);
        if ($scoreOne < 0 || $scoreTwo < 0) {
            throw new RuntimeException('امتیازها باید عدد غیرمنفی باشند.');
        }

        $data['mobile'] = $mobile;
        $stmt = $this->db->prepare('SELECT * FROM crm_customers WHERE mobile = :mobile LIMIT 1');
        $stmt->execute(['mobile' => $mobile]);
        $customer = $stmt->fetch();

        $matchStmt = $this->db->prepare('SELECT * FROM matches WHERE id = :id LIMIT 1');
        $matchStmt->execute(['id' => $data['match_id']]);
        $match = $matchStmt->fetch();
        if (!$match) {
            throw new RuntimeException('مسابقه یافت نشد.');
        }
        $now = date('Y-m-d H:i:s');
        $predictionStart = $match['prediction_start_at'] ?? $match['prediction_open_at'] ?? null;
        $predictionEnd = $match['prediction_end_at'] ?? $match['prediction_close_at'] ?? null;
        if (empty($match['is_active']) || empty($match['active_for_prediction']) || (string)($match['status'] ?? 'active') !== 'active' || $predictionStart > $now || $predictionEnd < $now) {
            throw new RuntimeException('مهلت ثبت پیش‌بینی برای این مسابقه فعال نیست.');
        }

        $dupSql = $this->hasColumn('customer_mobile')
            ? 'SELECT id FROM predictions WHERE match_id = :match_id AND (mobile = :mobile OR customer_mobile = :mobile) LIMIT 1'
            : 'SELECT id FROM predictions WHERE match_id = :match_id AND mobile = :mobile LIMIT 1';
        $dupStmt = $this->db->prepare($dupSql);
        $dupStmt->execute(['match_id' => (int)$data['match_id'], 'mobile' => $mobile]);
        if ($dupStmt->fetch()) {
            throw new RuntimeException('برای این شماره موبایل قبلاً پیش‌بینی این مسابقه ثبت شده است.');
        }

        $data['attended_match_time'] = 0;
        if ($customer) {
            $visitStmt = $this->db->prepare('SELECT COUNT(*) AS total FROM orders WHERE (customer_phone = :mobile OR user_id = :user_id) AND created_at BETWEEN :start_at AND :end_at');
            $start = $match['match_start_at'] ?? ($match['match_date'] . ' ' . ($match['broadcast_time'] ?: $match['kickoff_time']));
            $end = date('Y-m-d H:i:s', strtotime($start . ' +3 hours'));
            $visitStmt->execute(['mobile' => $mobile, 'user_id' => $customer['user_id'] ?? 0, 'start_at' => $start, 'end_at' => $end]);
            $data['attended_match_time'] = ((int)($visitStmt->fetch()['total'] ?? 0)) > 0 ? 1 : 0;
        }
        if ($customer && $data['attended_match_time'] && $this->hasColumn('attended_match_event', 'crm_customers')) {
            $this->db->prepare('UPDATE crm_customers SET attended_match_event = 1 WHERE id = :id')->execute(['id' => $customer['id']]);
        }

        $teamOne = $match['team_one_name'] ?? $match['team_a'] ?? '';
        $teamTwo = $match['team_two_name'] ?? $match['team_b'] ?? '';
        $insert = [
            'match_id' => (int)$data['match_id'],
            'customer_id' => $customer['id'] ?? null,
            'customer_name' => trim((string)($data['customer_name'] ?? '')),
            'customer_last_name' => trim((string)($data['customer_last_name'] ?? '')),
            'customer_mobile' => $mobile,
            'mobile' => $mobile,
            'team_one_name' => $teamOne,
            'team_two_name' => $teamTwo,
            'predicted_team_one_score' => $scoreOne,
            'predicted_team_two_score' => $scoreTwo,
            'predicted_score_team_a' => $scoreOne,
            'predicted_score_team_b' => $scoreTwo,
            'wants_reservation' => !empty($data['wants_reservation']) ? 1 : 0,
            'reserve_table_interest' => !empty($data['wants_reservation']) ? 1 : 0,
            'crm_matched' => $customer ? 1 : 0,
            'crm_match' => $customer ? 1 : 0,
            'customer_exists' => $customer ? 1 : 0,
            'attended_match_time' => $data['attended_match_time'],
            'attended_match' => $data['attended_match_time'],
            'source' => $data['source'] ?? 'prediction.php',
            'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => substr((string)($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
        ];
        if ($insert['customer_name'] === '') {
            throw new RuntimeException('نام الزامی است.');
        }

        $columns = array_flip($this->existingColumns());
        return $this->create(array_intersect_key($insert, $columns));
    }
}
