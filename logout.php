<?php
session_start();

// Log logout before destroying session
if (isset($_SESSION['username'])) {
    require_once 'audit_log.php';
    auditLog('logout', 'User logged out');
}

session_unset(); 
session_destroy();
header('location:index.php');
?>