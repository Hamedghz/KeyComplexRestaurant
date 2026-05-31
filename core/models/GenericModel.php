<?php
require_once __DIR__ . '/Model.php';

class GenericModel extends Model {
    public function __construct($table) {
        $this->table = $table;
        parent::__construct();
    }

    public function getConnection() {
        return $this->db;
    }
}
