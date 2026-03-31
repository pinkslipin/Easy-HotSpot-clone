<?php
/**
 * Router Manager Utility Class
 * 
 * Manages router connections, status checks, and state.
 */

require_once __DIR__ . '/routers_config.php';
require_once __DIR__ . '/routeros_api.php';
require_once __DIR__ . '/dbconfig.php';

class RouterManager {
    private $db;
    
    public function __construct($db_connection = null) {
        global $DB_con;
        $this->db = $db_connection ?: $DB_con;
    }
    
    /**
     * Get list of all routers
     */
    public function getAllRouters() {
        return getAllRouters();
    }
    
    /**
     * Get router configuration
     */
    public function getRouterConfig($router_id) {
        return getRouterConfig($router_id);
    }
    
    /**
     * Test connection to a specific router
     * Returns: ['success' => bool, 'message' => string, 'response_time' => int (ms)]
     */
    public function testRouterConnection($router_id) {
        $config = getRouterConfig($router_id);
        if (!$config) {
            return [
                'success' => false,
                'message' => "Router '$router_id' not found",
                'response_time' => null
            ];
        }
        
        $start = microtime(true);
        try {
            $result = testRouterConnection($config['ip'], $config['user'], $config['pass'], $config['port']);
            $time_ms = round((microtime(true) - $start) * 1000);
            
            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'identity' => $result['identity'] ?? null,
                'response_time' => $time_ms
            ];
        } catch (Exception $e) {
            $time_ms = round((microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_time' => $time_ms
            ];
        }
    }
    
    /**
     * Get current active router from database
     * Returns: router_id or null if none set
     */
    public function getActiveRouter() {
        try {
            $stmt = $this->db->prepare("
                SELECT router_id FROM router_status 
                WHERE is_active = 1 
                ORDER BY last_updated DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['router_id'] : null;
        } catch (Exception $e) {
            error_log("Error getting active router: " . $e->getMessage());
            // Fallback to primary router
            return getPrimaryRouterId();
        }
    }
    
    /**
     * Set active router in database
     */
    public function setActiveRouter($router_id) {
        try {
            // Deactivate all routers first
            $stmt = $this->db->prepare("UPDATE router_status SET is_active = 0");
            $stmt->execute();
            
            // Activate the selected router
            $stmt = $this->db->prepare("
                INSERT INTO router_status (router_id, is_active, last_updated) 
                VALUES (:router_id, 1, NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, last_updated = NOW()
            ");
            $stmt->execute([':router_id' => $router_id]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error setting active router: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get router status (online/offline)
     */
    public function getRouterStatus($router_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM router_status 
                WHERE router_id = :router_id
            ");
            $stmt->execute([':router_id' => $router_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Update router health status
     */
    public function updateRouterHealth($router_id, $is_online) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO router_status (router_id, is_online, last_heartbeat) 
                VALUES (:router_id, :is_online, NOW())
                ON DUPLICATE KEY UPDATE is_online = :is_online, last_heartbeat = NOW()
            ");
            $stmt->execute([
                ':router_id' => $router_id,
                ':is_online' => $is_online ? 1 : 0
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error updating router health: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users assigned to a router
     */
    public function getUsersForRouter($router_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_name, password, limit_uptime, limit_bytes, profile, status 
                FROM hotspot_vouchers 
                WHERE assigned_router = :router_id AND status = 'Active'
            ");
            $stmt->execute([':router_id' => $router_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching users: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count users per router
     */
    public function getUserCount($router_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM hotspot_vouchers 
                WHERE assigned_router = :router_id AND status = 'Active'
            ");
            $stmt->execute([':router_id' => $router_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get user distribution across all routers
     */
    public function getUserDistribution() {
        try {
            $stmt = $this->db->prepare("
                SELECT assigned_router, COUNT(*) as count 
                FROM hotspot_vouchers 
                WHERE status = 'Active'
                GROUP BY assigned_router
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

?>
