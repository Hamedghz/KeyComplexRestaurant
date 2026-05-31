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
}
