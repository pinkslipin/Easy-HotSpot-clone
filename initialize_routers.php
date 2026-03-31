<?php
/**
 * Multi-Router System Initialization
 * 
 * Run this ONCE to initialize the multi-router support.
 * Then delete this file for security.
 */

require_once 'dbconfig.php';
require_once 'database.php';
require_once 'routers_config.php';

echo "<h2>Initializing Multi-Router System...</h2>";
echo "<p>Setting up database tables and router status...</p>";

try {
    // Initialize router status entries for all configured routers
    $routers = getAllRouters();
    foreach ($routers as $router_id => $config) {
        $stmt = $DB_con->prepare("
            INSERT IGNORE INTO router_status (router_id, is_online, is_active, last_heartbeat) 
            VALUES (:router_id, 1, 0, NOW())
        ");
        $stmt->execute([':router_id' => $router_id]);
        echo "<p style='color: blue;'>✓ Initialized: " . htmlspecialchars($config['name']) . " (" . htmlspecialchars($router_id) . ") at " . htmlspecialchars($config['ip']) . "</p>";
    }
    
    // Set Converge as default active router
    $stmt = $DB_con->prepare("UPDATE router_status SET is_active = 1 WHERE router_id = 'converge'");
    $stmt->execute();
    echo "<p style='color: green;'><strong>✓ Converge router set as the active router</strong></p>";
    
    echo "<hr>";
    echo "<p style='color: green;'><strong>✓ Multi-Router system initialized successfully!</strong></p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Delete this file (initialize_routers.php) for security</li>";
    echo "<li>Go to <a href='index.php'>Dashboard</a> to verify the system is working</li>";
    echo "<li>Configure Globe router credentials if different from Converge</li>";
    echo "<li>Proceed with Phase 2: Health Monitoring</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check that database.php ran successfully and that your database connection is working.</p>";
}

?>
