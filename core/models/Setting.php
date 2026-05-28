<?php
/**
 * Setting Model
 */

require_once __DIR__ . '/Model.php';

class Setting extends Model {
    protected $table = 'settings';
    private static $cache = [];
    
    /**
     * Get setting value by key
     */
    public function get($key, $default = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $stmt = $this->db->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = :key");
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $this->castValue($result['setting_value'], $result['setting_type']);
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value, $type = 'text') {
        $stmt = $this->db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type)
            VALUES (:key, :value, :type)
            ON DUPLICATE KEY UPDATE setting_value = :value, setting_type = :type
        ");
        
        $result = $stmt->execute([
            'key' => $key,
            'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
            'type' => $type
        ]);
        
        // Clear cache
        unset(self::$cache[$key]);
        
        return $result;
    }
    
    /**
     * Get all settings by category
     */
    public function getByCategory($category) {
        $stmt = $this->db->prepare("SELECT * FROM settings WHERE category = :category ORDER BY setting_key");
        $stmt->execute(['category' => $category]);
        $results = $stmt->fetchAll();
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
        }
        
        return $settings;
    }
    
    /**
     * Get all public settings (for frontend API)
     */
    public function getPublicSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM settings WHERE is_public = 1");
        $results = $stmt->fetchAll();
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
        }
        
        return $settings;
    }
    
    /**
     * Update multiple settings
     */
    public function updateMultiple($settings) {
        try {
            $this->beginTransaction();
            
            foreach ($settings as $key => $value) {
                // Get current setting type
                $stmt = $this->db->prepare("SELECT setting_type FROM settings WHERE setting_key = :key");
                $stmt->execute(['key' => $key]);
                $result = $stmt->fetch();
                
                if ($result) {
                    $type = $result['setting_type'];
                    $this->set($key, $value, $type);
                }
            }
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Cast value based on type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Get WebGL settings
     */
    public function getWebGLSettings() {
        return [
            'fog_intensity' => $this->get('webgl_fog_intensity', 0.5),
            'bloom_intensity' => $this->get('webgl_bloom_intensity', 0.8),
            'animation_speed' => $this->get('webgl_animation_speed', 1.0)
        ];
    }
    
    /**
     * Get theme colors
     */
    public function getThemeColors() {
        return [
            'primary' => $this->get('primary_color', '#004647'),
            'accent' => $this->get('accent_color', '#D4AF37')
        ];
    }
}
