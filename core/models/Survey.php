<?php
/**
 * Survey Model - Dynamic Form Engine
 */

require_once __DIR__ . '/Model.php';

class Survey extends Model {
    protected $table = 'dynamic_forms';

    private function hasColumn(string $column): bool {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM dynamic_forms LIKE :column_name');
            $stmt->execute(['column_name' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Survey column lookup failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active survey form
     */
    public function getActiveForm() {
        $sql = "
            SELECT * FROM dynamic_forms
            WHERE is_active = 1
        ";
        if ($this->hasColumn('start_date')) {
            $sql .= " AND (start_date IS NULL OR start_date <= NOW())";
        }
        if ($this->hasColumn('end_date')) {
            $sql .= " AND (end_date IS NULL OR end_date >= NOW())";
        }
        $sql .= " ORDER BY display_order ASC, created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $form = $stmt->fetch();
        
        if ($form && !empty($form['form_schema'])) {
            $form['form_schema'] = json_decode($form['form_schema'], true);
        }
        
        return $form;
    }
    
    /**
     * Get form by ID
     */
    public function getForm($id) {
        $form = $this->find($id);
        
        if ($form && !empty($form['form_schema'])) {
            $form['form_schema'] = json_decode($form['form_schema'], true);
        }
        
        return $form;
    }
    
    /**
     * Get all forms
     */
    public function getAllForms() {
        $stmt = $this->db->query("
            SELECT id, form_name, form_title_fa, form_title_en, is_active, 
                   created_at, 
                   (SELECT COUNT(*) FROM survey_responses WHERE form_id = dynamic_forms.id) as response_count
            FROM dynamic_forms 
            ORDER BY display_order ASC, created_at DESC
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create new form
     */
    public function createForm($data) {
        // Validate and encode schema
        if (isset($data['form_schema']) && is_array($data['form_schema'])) {
            $data['form_schema'] = json_encode($data['form_schema'], JSON_UNESCAPED_UNICODE);
        }
        
        return $this->create($data);
    }
    
    /**
     * Update form
     */
    public function updateForm($id, $data) {
        // Validate and encode schema
        if (isset($data['form_schema']) && is_array($data['form_schema'])) {
            $data['form_schema'] = json_encode($data['form_schema'], JSON_UNESCAPED_UNICODE);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Submit survey response
     */
    public function submitResponse($formId, $responseData, $metadata = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO survey_responses 
                (form_id, order_id, user_id, response_data, customer_name, customer_phone, customer_email, ip_address, user_agent)
                VALUES (:form_id, :order_id, :user_id, :response_data, :customer_name, :customer_phone, :customer_email, :ip_address, :user_agent)
            ");
            
            return $stmt->execute([
                'form_id' => $formId,
                'order_id' => $metadata['order_id'] ?? null,
                'user_id' => $metadata['user_id'] ?? null,
                'response_data' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                'customer_name' => $metadata['customer_name'] ?? null,
                'customer_phone' => $metadata['customer_phone'] ?? null,
                'customer_email' => $metadata['customer_email'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Survey Response Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get responses for a form
     */
    public function getResponses($formId, $limit = null) {
        $sql = "
            SELECT sr.*, df.form_title_fa, df.form_title_en
            FROM survey_responses sr
            LEFT JOIN dynamic_forms df ON sr.form_id = df.id
            WHERE sr.form_id = :form_id
            ORDER BY sr.submitted_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
        
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $responses = $stmt->fetchAll();
        
        // Decode JSON response data
        foreach ($responses as &$response) {
            if (!empty($response['response_data'])) {
                $response['response_data'] = json_decode($response['response_data'], true);
            }
        }
        
        return $responses;
    }
    
    /**
     * Get response statistics
     */
    public function getStatistics($formId) {
        $form = $this->getForm($formId);
        
        if (!$form) {
            return null;
        }
        
        $responses = $this->getResponses($formId);
        $totalResponses = count($responses);
        
        if ($totalResponses === 0) {
            return [
                'total_responses' => 0,
                'field_stats' => []
            ];
        }
        
        $fieldStats = [];
        
        foreach ($form['form_schema']['fields'] as $field) {
            $fieldId = $field['id'];
            $fieldStats[$fieldId] = [
                'label_fa' => $field['label_fa'],
                'type' => $field['type'],
                'values' => []
            ];
            
            // Collect all values for this field
            foreach ($responses as $response) {
                if (isset($response['response_data'][$fieldId])) {
                    $value = $response['response_data'][$fieldId];
                    
                    if ($field['type'] === 'stars') {
                        $fieldStats[$fieldId]['values'][] = (int)$value;
                    } elseif ($field['type'] === 'multiple_choice') {
                        if (!isset($fieldStats[$fieldId]['counts'][$value])) {
                            $fieldStats[$fieldId]['counts'][$value] = 0;
                        }
                        $fieldStats[$fieldId]['counts'][$value]++;
                    } else {
                        $fieldStats[$fieldId]['values'][] = $value;
                    }
                }
            }
            
            // Calculate statistics
            if ($field['type'] === 'stars' && !empty($fieldStats[$fieldId]['values'])) {
                $values = $fieldStats[$fieldId]['values'];
                $fieldStats[$fieldId]['average'] = round(array_sum($values) / count($values), 2);
                $fieldStats[$fieldId]['min'] = min($values);
                $fieldStats[$fieldId]['max'] = max($values);
                $fieldStats[$fieldId]['count'] = count($values);
            }
        }
        
        return [
            'total_responses' => $totalResponses,
            'field_stats' => $fieldStats
        ];
    }
    
    /**
     * Validate response data against form schema
     */
    public function validateResponse($formId, $responseData) {
        $form = $this->getForm($formId);
        
        if (!$form) {
            return ['valid' => false, 'errors' => ['فرم یافت نشد']];
        }
        
        $errors = [];
        $schema = $form['form_schema'];
        
        foreach ($schema['fields'] as $field) {
            $fieldId = $field['id'];
            $value = $responseData[$fieldId] ?? null;
            
            // Check required fields
            if ($field['required'] && empty($value)) {
                $errors[] = "فیلد {$field['label_fa']} الزامی است";
                continue;
            }
            
            // Validate by type
            if (!empty($value)) {
                switch ($field['type']) {
                    case 'stars':
                        if (!is_numeric($value) || $value < 1 || $value > ($field['max_stars'] ?? 5)) {
                            $errors[] = "مقدار {$field['label_fa']} نامعتبر است";
                        }
                        break;
                        
                    case 'multiple_choice':
                        $validOptions = array_column($field['options'], 'value');
                        if (!in_array($value, $validOptions)) {
                            $errors[] = "گزینه انتخابی برای {$field['label_fa']} نامعتبر است";
                        }
                        break;
                        
                    case 'textarea':
                    case 'text':
                        if (isset($field['max_length']) && strlen($value) > $field['max_length']) {
                            $errors[] = "{$field['label_fa']} بیش از حد مجاز طولانی است";
                        }
                        break;
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
