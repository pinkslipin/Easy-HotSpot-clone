<?php
/**
 * AJAX handler to clear audit logs
 */
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';

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
    error_log('Clear logs error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to clear logs']);
}
?>
