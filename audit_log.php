<?php
/**
 * Audit Log System
 * 
 * Provides logging functionality for tracking user actions
 */

require_once 'dbconfig.php';

/**
 * Create audit_log table if it doesn't exist
 */
function createAuditLogTable() {
    global $DB_con;
    
    $sql = "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        username VARCHAR(100),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_username (username),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    try {
        $DB_con->exec($sql);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log an action to the audit log
 */
function auditLog($action, $details = '', $username = null) {
    global $DB_con;
    
    // Ensure table exists
    createAuditLogTable();
    
    try {
        if ($username === null && isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'CLI';
        
        $stmt = $DB_con->prepare("INSERT INTO audit_log (action, details, username, ip_address, user_agent, created_at) 
            VALUES (:action, :details, :username, :ip, :ua, NOW())");
        $stmt->execute([
            ':action' => $action,
            ':details' => $details,
            ':username' => $username,
            ':ip' => $ip,
            ':ua' => $userAgent
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get audit log entries
 */
function getAuditLog($limit = 100, $action = null, $username = null) {
    global $DB_con;
    
    // Ensure table exists
    createAuditLogTable();
    
    try {
        $where = "1=1";
        $params = [];
        
        if ($action) {
            $where .= " AND action = :action";
            $params[':action'] = $action;
        }
        
        if ($username) {
            $where .= " AND username = :username";
            $params[':username'] = $username;
        }
        
        $sql = "SELECT * FROM audit_log WHERE $where ORDER BY created_at DESC LIMIT :limit";
        $stmt = $DB_con->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Clear old audit log entries
 */
function clearOldAuditLogs($days = 90) {
    global $DB_con;
    
    try {
        $stmt = $DB_con->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $stmt->execute([':days' => $days]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Clear all audit logs
 */
function clearAllAuditLogs() {
    global $DB_con;
    
    try {
        $stmt = $DB_con->exec("TRUNCATE TABLE audit_log");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get audit log statistics
 */
function getAuditStats() {
    global $DB_con;
    
    // Ensure table exists
    createAuditLogTable();
    
    try {
        $stats = [
            'total' => 0,
            'today' => 0,
            'by_action' => [],
            'by_user' => []
        ];
        
        // Total count
        $stmt = $DB_con->query("SELECT COUNT(*) as count FROM audit_log");
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Today's count
        $stmt = $DB_con->query("SELECT COUNT(*) as count FROM audit_log WHERE DATE(created_at) = CURDATE()");
        $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // By action
        $stmt = $DB_con->query("SELECT action, COUNT(*) as count FROM audit_log GROUP BY action ORDER BY count DESC LIMIT 10");
        $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // By user
        $stmt = $DB_con->query("SELECT username, COUNT(*) as count FROM audit_log GROUP BY username ORDER BY count DESC LIMIT 10");
        $stats['by_user'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    } catch (Exception $e) {
        return ['total' => 0, 'today' => 0, 'by_action' => [], 'by_user' => []];
    }
}

// Common actions
define('AUDIT_LOGIN', 'login');
define('AUDIT_LOGOUT', 'logout');
define('AUDIT_VOUCHER_CREATE', 'voucher_create');
define('AUDIT_VOUCHER_DELETE', 'voucher_delete');
define('AUDIT_BATCH_CREATE', 'batch_create');
define('AUDIT_BATCH_DELETE', 'batch_delete');
define('AUDIT_USER_CREATE', 'user_create');
define('AUDIT_USER_DELETE', 'user_delete');
define('AUDIT_USER_UPDATE', 'user_update');
define('AUDIT_SETTINGS_CHANGE', 'settings_change');
define('AUDIT_PORTAL_UPDATE', 'portal_update');
?>
