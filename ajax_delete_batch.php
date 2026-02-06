<?php
/**
 * AJAX handler to delete an entire batch of vouchers
 */
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'config.php';

// Check user permissions - require level 1 or 2
if ($_SESSION['user_level'] > 2) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$batch_id = isset($_POST['batch_id']) ? $_POST['batch_id'] : '';

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'No batch ID provided']);
    exit;
}

try {
    // Get vouchers in this batch for router removal
    $stmt = $DB_con->prepare("SELECT user_name FROM hotspot_vouchers WHERE batch_id = :batch_id");
    $stmt->execute([':batch_id' => $batch_id]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deleted_count = count($vouchers);
    
    // Remove from router if not in mock mode
    if (!defined('MOCK_MODE') || MOCK_MODE !== true) {
        // Use modern RouterOS API library (RouterOS 6.43+/7.x compatible)
        require_once 'routeros_api.php';
        $connection = createRouterConnection($host, $user, $pass);
        
        if ($connection['success']) {
            $util = $connection['util'];
            $util->setMenu('/ip/hotspot/user');
            
            foreach ($vouchers as $voucher) {
                try {
                    $util->removeUser($voucher['user_name']);
                } catch (Exception $e) {
                    // Continue even if one fails
                }
            }
        }
    } else {
        // Mock mode - remove from JSON
        require_once 'mock_router.php';
        $mockRouter = new MockRouterUtil();
        $mockRouter->setMenu('/ip hotspot user');
        
        foreach ($vouchers as $voucher) {
            $mockRouter->removeUser($voucher['user_name']);
        }
    }
    
    // Delete from database
    $stmt = $DB_con->prepare("DELETE FROM hotspot_vouchers WHERE batch_id = :batch_id");
    $stmt->execute([':batch_id' => $batch_id]);
    
    // Log this action
    logAuditAction('batch_delete', "Deleted batch: $batch_id ($deleted_count vouchers)");
    
    echo json_encode([
        'success' => true, 
        'message' => "Batch '$batch_id' deleted successfully ($deleted_count vouchers removed)"
    ]);
    
} catch (Exception $e) {
    error_log('Batch delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete batch']);
}

/**
 * Log audit action (placeholder - will be implemented in audit_log.php)
 */
function logAuditAction($action, $details) {
    global $DB_con;
    
    try {
        // Check if audit_log table exists
        $stmt = $DB_con->query("SHOW TABLES LIKE 'audit_log'");
        if ($stmt->rowCount() > 0) {
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $stmt = $DB_con->prepare("INSERT INTO audit_log (action, details, username, ip_address, created_at) VALUES (:action, :details, :username, :ip, NOW())");
            $stmt->execute([
                ':action' => $action,
                ':details' => $details,
                ':username' => $username,
                ':ip' => $ip
            ]);
        }
    } catch (Exception $e) {
        // Silently fail if audit logging doesn't work
    }
}
?>
