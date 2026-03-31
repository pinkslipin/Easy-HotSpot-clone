<?php
/**
 * Migration Handler
 * 
 * Handles recovery when a failed router comes back online.
 * Processes pending operations that were queued while the router was offline.
 */

require_once __DIR__ . '/router_manager.php';
require_once __DIR__ . '/health_check.php';
require_once __DIR__ . '/dbconfig.php';

class MigrationHandler {
    private $db;
    private $router_manager;
    private $health_checker;
    
    public function __construct($db_connection = null) {
        global $DB_con;
        $this->db = $db_connection ?: $DB_con;
        $this->router_manager = new RouterManager($this->db);
        $this->health_checker = new HealthChecker($this->db);
    }
    
    /**
     * Check all routers and handle recovery scenarios
     */
    public function checkAndRecover() {
        $results = $this->health_checker->checkAllRouters();
        
        foreach ($results as $router_id => $result) {
            if ($result['status'] === 'online') {
                // Router came back online, process pending operations
                $this->processPendingOperations($router_id);
            }
        }
    }
    
    /**
     * Process all pending operations for a router
     */
    public function processPendingOperations($router_id) {
        try {
            // Get all pending operations for this router
            $stmt = $this->db->prepare("
                SELECT * FROM pending_operations 
                WHERE assigned_router = :router_id AND completed_at IS NULL
                ORDER BY created_at ASC
            ");
            $stmt->execute([':router_id' => $router_id]);
            $pending_ops = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($pending_ops)) {
                return;
            }
            
            require_once __DIR__ . '/routeros_api.php';
            $config = getRouterConfig($router_id);
            $connection = createRouterConnection($config['ip'], $config['user'], $config['pass'], $config['port']);
            
            if (!$connection['success']) {
                error_log("Cannot connect to router $router_id to process pending ops");
                return;
            }
            
            $successful = 0;
            $failed = 0;
            
            foreach ($pending_ops as $op) {
                try {
                    $this->executePendingOperation($connection['util'], $op);
                    $this->markOperationComplete($op['id']);
                    $successful++;
                } catch (Exception $e) {
                    $this->recordOperationError($op['id'], $e->getMessage());
                    $failed++;
                }
            }
            
            // Log recovery event
            if ($successful > 0 || $failed > 0) {
                require_once __DIR__ . '/audit_log.php';
                auditLog('router_recovery', 
                    "Router $router_id recovered. Processed $successful pending operations "
                    . "($failed failed). System is back to normal.");
            }
            
        } catch (Exception $e) {
            error_log("Error processing pending operations: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a single pending operation
     */
    private function executePendingOperation($router_util, $operation) {
        $op_type = $operation['operation_type'];
        $user_name = $operation['user_name'];
        $params = json_decode($operation['operation_params'], true) ?? [];
        
        $router_util->setMenu('/ip/hotspot/user');
        
        switch ($op_type) {
            case 'create':
                $router_util->add($params);
                break;
                
            case 'delete':
                $users = $router_util->find('name', $user_name);
                if (!empty($users)) {
                    $user_id = $users[0]->getProperty('.id');
                    $router_util->remove($user_id);
                }
                break;
                
            case 'update':
                $users = $router_util->find('name', $user_name);
                if (!empty($users)) {
                    $user_id = $users[0]->getProperty('.id');
                    $router_util->update($user_id, $params);
                }
                break;
                
            default:
                throw new Exception("Unknown operation type: $op_type");
        }
    }
    
    /**
     * Mark an operation as completed
     */
    private function markOperationComplete($operation_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE pending_operations 
                SET completed_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $operation_id]);
        } catch (Exception $e) {
            error_log("Error marking operation complete: " . $e->getMessage());
        }
    }
    
    /**
     * Record an error for a pending operation
     */
    private function recordOperationError($operation_id, $error_message) {
        try {
            $stmt = $this->db->prepare("
                UPDATE pending_operations 
                SET retry_count = retry_count + 1, 
                    last_retry = NOW(),
                    error_message = :error_message
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $operation_id,
                ':error_message' => substr($error_message, 0, 500)
            ]);
        } catch (Exception $e) {
            error_log("Error recording operation error: " . $e->getMessage());
        }
    }
    
    /**
     * Queue an operation for when the router comes back online
     */
    public function queueOperation($operation_type, $user_name, $router_id, $params = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO pending_operations 
                (operation_type, user_name, assigned_router, operation_params)
                VALUES (:operation_type, :user_name, :router_id, :params)
            ");
            $stmt->execute([
                ':operation_type' => $operation_type,
                ':user_name' => $user_name,
                ':router_id' => $router_id,
                ':params' => json_encode($params)
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error queuing operation: " . $e->getMessage());
            return false;
        }
    }
}

?>
