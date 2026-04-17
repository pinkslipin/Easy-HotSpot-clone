<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';

// SECURITY: Require authenticated user
require_auth();
csrf_require();

function remFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$guest_name = trim($_POST['username'] ?? '');
if (empty($guest_name)) { 
    remFail('No username provided');
}

// Mock mode
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    require_once 'dbconfig.php';
    try {
        $stmt = $DB_con->prepare("DELETE FROM hotspot_vouchers WHERE user_name = :user_name");
        $stmt->execute([':user_name' => $guest_name]);
    } catch (Exception $e) {
        // Silently fail
    }
    require_once 'audit_log.php';
    auditLog('user_delete', "Deleted single user: $guest_name (mock mode)");
    echo json_encode(['success' => true, 'message' => 'User removed successfully']);
    exit;
}

// Real router mode - connect directly
require_once 'routeros_api.php';
$conn = createRouterConnection($host, $user, $pass);
if (!$conn['success']) {
    remFail('Router connection failed: ' . $conn['error']);
}

$util = $conn['util'];
$client = $conn['client'];

try {
    // Remove user from /ip/hotspot/user
    $util->setMenu('/ip/hotspot/user');
    $util->removeUser($guest_name);
    
    // Kick any active sessions belonging to this user
    $util->setMenu('/ip/hotspot/active');
    $activeSessions = $util->find('user', $guest_name);
    foreach ($activeSessions as $session) {
        $util->remove($session->getProperty('.id'));
    }
    
    // Update DB status
    require_once 'dbconfig.php';
    try {
        $stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Used' WHERE user_name = :user_name");
        $stmt->execute([':user_name' => $guest_name]);
    } catch (Exception $e) {
        // Non-fatal
    }
    
    // Log
    require_once 'audit_log.php';
    auditLog('user_delete', "Deleted single user: $guest_name");
    
    // Return success
    echo json_encode(['success' => true, 'message' => 'User removed successfully']);
    
} catch (Exception $e) {
    error_log('[ajax_rem_user] Error: ' . $e->getMessage());
    remFail('Failed to remove user: ' . $e->getMessage());
}
?>