<?php
session_start();

// Log logout before destroying session
if (isset($_SESSION['username'])) {
    require_once 'audit_log.php';
    auditLog('logout', 'User logged out');
}

session_unset(); 
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
header('location:index.php');
exit;
?>