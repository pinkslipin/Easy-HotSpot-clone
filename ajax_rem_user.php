<?php
require_once 'config.php';
if ( !isset($_SESSION) ) session_start();

$guest_name = trim($_GET['username']);

// Log single user deletion
require_once 'audit_log.php';
auditLog('user_delete', "Deleted single user: $guest_name");

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    // Mock mode - remove from local JSON storage
    require_once 'mock_router.php';
    $mockUtil = new MockRouterUtil();
    $mockUtil->removeUser($guest_name);
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