<?php
/**
 * Authentication Class
 * Handles admin authentication and session management
 */

require_once __DIR__ . '/bootstrap.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureAuthTables();
    }

    private function ensureAuthTables(): void {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS `admin_sessions` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `admin_id` int(11) UNSIGNED NOT NULL,
                `session_token` varchar(64) NOT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `expires_at` datetime NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_admin_session_token` (`session_token`),
                KEY `idx_admin_sessions_admin` (`admin_id`),
                KEY `idx_admin_sessions_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->db->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `admin_id` int(11) UNSIGNED DEFAULT NULL,
                `action` varchar(120) NOT NULL,
                `entity_type` varchar(120) DEFAULT NULL,
                `entity_id` varchar(120) DEFAULT NULL,
                `description` text DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_activity_admin` (`admin_id`),
                KEY `idx_activity_action` (`action`),
                KEY `idx_activity_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('Auth table ensure failed: ' . $e->getMessage());
        }
    }

    private function adminSelectFields(bool $includePassword = false): string {
        $fields = ['id', 'username', 'email', 'full_name', 'role'];
        if ($includePassword) {
            $fields[] = 'password';
            $fields[] = 'is_active';
        }
        foreach (['department', 'permissions'] as $column) {
            try {
                $stmt = $this->db->prepare('SHOW COLUMNS FROM admins LIKE ?');
                $stmt->execute([$column]);
                $fields[] = $stmt->fetchColumn() ? $column : "NULL AS {$column}";
            } catch (Throwable $e) {
                $fields[] = "NULL AS {$column}";
            }
        }
        return implode(', ', $fields);
    }
    
    /**
     * Login admin user
     */
    public function login($username, $password) {
        try {
            $loginIdentifier = trim((string)$username);

            $stmt = $this->db->prepare("
                SELECT " . $this->adminSelectFields(true) . "
                FROM admins 
                WHERE (username = :username OR email = :email) AND is_active = 1
            ");
            $stmt->execute([
                'username' => $loginIdentifier,
                'email' => $loginIdentifier
            ]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Create session
                $sessionToken = $this->createSession($admin['id']);
                
                // Update last login
                $updateStmt = $this->db->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                $updateStmt->execute(['id' => $admin['id']]);
                
                // Set session data
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['session_token'] = $sessionToken;
                $_SESSION['last_activity'] = time();
                
                // Log activity
                $this->logActivity($admin['id'], 'login', null, null, 'تسجیل ورود موفق');
                
                return [
                    'success' => true,
                    'message' => 'ورود موفقیت‌آمیز بود',
                    'redirect' => ADMIN_URL . ($admin['role'] === 'employee' ? '/employee-dashboard.php' : '/dashboard.php')
                ];
            }
            
            return [
                'success' => false,
                'message' => 'نام کاربری یا رمز عبور اشتباه است'
            ];
            
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ورود به سیستم'
            ];
        }
    }
    
    /**
     * Create admin session
     */
    private function createSession($adminId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ADMIN_SESSION_DURATION);
        
        $stmt = $this->db->prepare("
            INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at)
            VALUES (:admin_id, :token, :ip, :user_agent, :expires_at)
        ");
        
        $stmt->execute([
            'admin_id' => $adminId,
            'token' => $token,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt
        ]);
        
        return $token;
    }
    
    /**
     * Check if admin is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > ADMIN_SESSION_DURATION)) {
            $this->logout();
            return false;
        }
        
        // Verify session token
        $stmt = $this->db->prepare("
            SELECT id FROM admin_sessions 
            WHERE admin_id = :admin_id 
            AND session_token = :token 
            AND expires_at > NOW()
        ");
        
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'],
            'token' => $_SESSION['session_token']
        ]);
        
        if ($stmt->fetch()) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        $this->logout();
        return false;
    }
    
    /**
     * Logout admin
     */
    public function logout() {
        if (isset($_SESSION['admin_id']) && isset($_SESSION['session_token'])) {
            // Delete session from database
            $stmt = $this->db->prepare("
                DELETE FROM admin_sessions 
                WHERE admin_id = :admin_id AND session_token = :token
            ");
            $stmt->execute([
                'admin_id' => $_SESSION['admin_id'],
                'token' => $_SESSION['session_token']
            ]);
            
            // Log activity
            $this->logActivity($_SESSION['admin_id'], 'logout', null, null, 'خروج از سیستم');
        }
        
        // Clear session
        session_unset();
        session_destroy();
    }
    
    /**
     * Get current admin data
     */
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT " . $this->adminSelectFields(false) . "
            FROM admins 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        
        return $stmt->fetch();
    }
    
    /**
     * Check if admin has permission
     */
    public function hasPermission($requiredRole = 'admin') {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $roles = ['employee' => 0, 'manager' => 1, 'admin' => 2, 'super_admin' => 3];
        $currentRole = $_SESSION['admin_role'] ?? 'employee';
        
        return ($roles[$currentRole] ?? -1) >= ($roles[$requiredRole] ?? 0);
    }
    
    /**
     * Log admin activity
     */
    public function logActivity($adminId, $action, $entityType = null, $entityId = null, $description = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description, ip_address, user_agent)
                VALUES (:admin_id, :action, :entity_type, :entity_id, :description, :ip, :user_agent)
            ");
            
            $stmt->execute([
                'admin_id' => $adminId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Activity Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        try {
            $stmt = $this->db->prepare("DELETE FROM admin_sessions WHERE expires_at < NOW()");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Clean Sessions Error: " . $e->getMessage());
        }
    }
}
