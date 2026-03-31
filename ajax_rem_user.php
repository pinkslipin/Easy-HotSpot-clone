<?php
require_once 'config.php';
require_once 'security_helper.php';
require_once 'routers_config.php';

// SECURITY: Require authenticated user (was completely unprotected!)
require_auth();
csrf_require();

$guest_name = trim($_POST['username']);
if (empty($guest_name)) { echo 'Error: No username provided'; exit; }

// PHASE 4: Look up which router this user is assigned to
require_once 'dbconfig.php';
try {
	$stmt = $DB_con->prepare("SELECT assigned_router FROM hotspot_vouchers WHERE user_name = :user_name LIMIT 1");
	$stmt->execute([':user_name' => $guest_name]);
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	$assigned_router = $result ? $result['assigned_router'] : 'converge';
} catch (Exception $e) {
	$assigned_router = 'converge'; // Default fallback
}

// Log single user deletion
require_once 'audit_log.php';
auditLog('user_delete', "Deleted single user: $guest_name (router: $assigned_router)");

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    // Mock mode - remove from database (mock users are synced from DB)
    try {
        $stmt = $DB_con->prepare("DELETE FROM hotspot_vouchers WHERE user_name = :user_name");
        $stmt->execute([':user_name' => $guest_name]);
    } catch (Exception $e) {
        // Silently fail
    }
} else {
    // Real router mode - using modern library (RouterOS 6.43+/7.x compatible)
    require_once 'routeros_api.php';
    require_once 'migration_handler.php';
    
    $router_config = getRouterConfig($assigned_router);
    $connection = createRouterConnection($router_config['ip'], $router_config['user'], $router_config['pass'], $router_config['port']);
    
    if ($connection['success']) {
        $util = $connection['util'];
        $util->setMenu('/ip/hotspot/user');
        $util->removeUser($guest_name);

        // Kick any active sessions belonging to this user so the device
        // loses internet access immediately (MikroTik keeps the session
        // alive even after the user record is deleted).
        $util->setMenu('/ip/hotspot/active');
        $activeSessions = $util->find('user', $guest_name);
        foreach ($activeSessions as $session) {
            $util->remove($session->getProperty('.id'));
        }

        // Update DB status so the dashboard and voucher print list reflect the removal.
        try {
            $stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Used' WHERE user_name = :user_name");
            $stmt->execute([':user_name' => $guest_name]);
        } catch (Exception $e) {
            // Non-fatal: router removal already succeeded
        }
    } else {
        // Router is offline - queue the deletion for when it comes back online
        $migration_handler = new MigrationHandler();
        $migration_handler->queueOperation('delete', $guest_name, $assigned_router);
        auditLog('user_delete_queued', "Queued deletion for $guest_name (router $assigned_router offline)");
        
        // Still mark as used in DB so we don't try to delete again
        try {
            $stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Used' WHERE user_name = :user_name");
            $stmt->execute([':user_name' => $guest_name]);
        } catch (Exception $e) {
            // Non-fatal
        }
    }
    }
}
?>