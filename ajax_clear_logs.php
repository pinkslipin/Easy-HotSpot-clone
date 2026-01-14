<?php
/**
 * AJAX handler to clear audit logs
 */
session_start();
require_once 'dbconfig.php';
require_once 'audit_log.php';

// Check user permissions - only admin can clear logs
if (!isset($_SESSION['username']) || $_SESSION['user_level'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Admin only']);
    exit;
}

try {
    // Log this action before clearing (will be the first entry after clear)
    $success = clearAllAuditLogs();
    
    if ($success) {
        // Add a new entry to record the clear
        auditLog('logs_cleared', 'All audit logs were cleared by administrator');
        echo json_encode(['success' => true, 'message' => 'Logs cleared successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear logs']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
