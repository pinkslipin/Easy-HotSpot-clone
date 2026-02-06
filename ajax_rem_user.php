<?php
require_once 'config.php';
require_once 'security_helper.php';

// SECURITY: Require authenticated user (was completely unprotected!)
require_auth();
csrf_require();

$guest_name = trim($_POST['username']);
if (empty($guest_name)) { echo 'Error: No username provided'; exit; }

// Log single user deletion
require_once 'audit_log.php';
auditLog('user_delete', "Deleted single user: $guest_name");

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    // Mock mode - remove from database (mock users are synced from DB)
    require_once 'dbconfig.php';
    try {
        $stmt = $DB_con->prepare("DELETE FROM hotspot_vouchers WHERE user_name = :user_name");
        $stmt->execute([':user_name' => $guest_name]);
    } catch (Exception $e) {
        // Silently fail
    }
} else {
    // Real router mode - using modern library (RouterOS 6.43+/7.x compatible)
    require_once 'routeros_api.php';
    $connection = createRouterConnection($host, $user, $pass);
    if ($connection['success']) {
        $util = $connection['util'];
        $util->setMenu('/ip/hotspot/user');
        $util->removeUser($guest_name);
    }
}
?>