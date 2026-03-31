<?php
/**
 * Load Balancer Utility
 * 
 * Handles random distribution of users across routers
 */

require_once __DIR__ . '/routers_config.php';
require_once __DIR__ . '/dbconfig.php';

class LoadBalancer {
    private $db;
    
    public function __construct($db_connection = null) {
        global $DB_con;
        $this->db = $db_connection ?: $DB_con;
    }
    
    /**
     * Get a random enabled router
     * Returns: router_id (e.g., 'converge' or 'globe')
     */
    public function getRandomRouter() {
        $routers = getAllRouters();
        if (empty($routers)) {
            return getPrimaryRouterId();
        }
        
        $router_ids = array_keys($routers);
        return $router_ids[array_rand($router_ids)];
    }
    
    /**
     * Get router with least load (fewest active users)
     * Returns: router_id
     */
    public function getLeastLoadedRouter() {
        try {
            $stmt = $this->db->prepare("
                SELECT router_id FROM (
                    SELECT assigned_router as router_id, COUNT(*) as count 
                    FROM hotspot_vouchers 
                    WHERE status = 'Active'
                    GROUP BY assigned_router
                ) as load_counts
                ORDER BY count ASC
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['router_id'];
            }
            
            // If no results, return a random router
            return $this->getRandomRouter();
        } catch (Exception $e) {
            error_log("Error getting least loaded router: " . $e->getMessage());
            return $this->getRandomRouter();
        }
    }
    
    /**
     * Assign user to a router
     * Returns: router_id
     */
    public function assignUserToRouter($strategy = 'random') {
        switch ($strategy) {
            case 'least-loaded':
                return $this->getLeastLoadedRouter();
            case 'random':
            default:
                return $this->getRandomRouter();
        }
    }
}

?>
