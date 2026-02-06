<?php
/**
 * AJAX handler to clear router system logs
 */
require_once 'security_helper.php';
secure_session_start();
require_admin();
require_once 'config.php';

try {
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        // Mock mode - just return success
        echo json_encode(['success' => true, 'message' => 'Mock mode: Logs would be cleared']);
        exit;
    }
    
    // Real router mode - clear router logs
    require_once 'routeros_api.php';
    
    $connection = createRouterConnection($host, $user, $pass);
    if (!$connection['success']) {
        echo json_encode(['success' => false, 'message' => 'Router connection failed: ' . $connection['error']]);
        exit;
    }
    
    $client = $connection['client'];
    
    // Execute the reset command to clear logs
    $query = new \RouterOS\Query('/log/print');
    $logs = $client->query($query)->read();
    
    // Remove all log entries by their IDs
    $cleared = 0;
    foreach ($logs as $log) {
        if (isset($log['.id'])) {
            $removeQuery = (new \RouterOS\Query('/log/remove'))
                ->equal('.id', $log['.id']);
            try {
                $client->query($removeQuery)->read();
                $cleared++;
            } catch (Exception $e) {
                // Some log entries may not be removable, continue
            }
        }
    }
    
    // Alternative: Use the system reset command for logs (if above doesn't work)
    // RouterOS doesn't have a direct /log/reset, logs are in memory
    // The best approach is to use: /system logging action set memory memory-lines=1
    // Then set it back, but this is complex
    
    if ($cleared > 0) {
        // Log this action to our audit log
        require_once 'audit_log.php';
        auditLog('router_logs_cleared', "Cleared $cleared router log entries");
        
        echo json_encode(['success' => true, 'message' => "Cleared $cleared log entries"]);
    } else {
        // Try alternative method - RouterOS logs are stored in memory
        // and can be cleared by adjusting the logging action
        echo json_encode([
            'success' => false, 
            'message' => 'Router logs cannot be deleted directly. They are stored in router memory and will naturally cycle out. Consider setting a smaller log buffer on the router.'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Router log clear error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to clear router logs']);
}
?>
