<?php
/**
 * Router Connection Helper
 * 
 * This file provides a unified way to get a router connection,
 * automatically switching between mock mode and real MikroTik
 * based on the MOCK_MODE setting in config.php
 * 
 * UPDATED: Now uses modern evilfreelancer/routeros-api-php library
 * Compatible with RouterOS 6.43+ and 7.x
 * 
 * USAGE:
 *   require_once 'router_helper.php';
 *   $util = getRouterUtil();
 *   // Now use $util just like before!
 */

require_once __DIR__ . '/config.php';

function getRouterUtil() {
    global $host, $user, $pass;
    
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        // Development mode - use mock router
        require_once __DIR__ . '/mock_router.php';
        return new MockRouterUtil();
    } else {
        // Production mode - connect to real MikroTik using modern library
        require_once __DIR__ . '/routeros_api.php';
        $connection = createRouterConnection($host, $user, $pass);
        if (!$connection['success']) {
            throw new Exception("Router connection failed: " . $connection['error']);
        }
        return $connection['util'];
    }
}

function getRouterClient() {
    global $host, $user, $pass;
    
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        require_once __DIR__ . '/mock_router.php';
        return new MockClient();
    } else {
        // Production mode - connect to real MikroTik using modern library
        require_once __DIR__ . '/routeros_api.php';
        $connection = createRouterConnection($host, $user, $pass);
        if (!$connection['success']) {
            throw new Exception("Router connection failed: " . $connection['error']);
        }
        return $connection['client'];
    }
}

/**
 * Test router connection and return status
 */
function testRouterConnectionStatus() {
    global $host, $user, $pass;
    
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        return [
            'success' => true,
            'mode' => 'mock',
            'message' => 'Running in Mock Mode (development)'
        ];
    }
    
    require_once __DIR__ . '/routeros_api.php';
    $result = testRouterConnection($host, $user, $pass);
    $result['mode'] = 'live';
    return $result;
}

/**
 * Run health checks periodically (cache for 30 seconds)
 * Integrated into every page load for automatic monitoring
 */
function runHealthCheckIfNeeded() {
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        return; // Skip health checks in mock mode
    }
    
    try {
        $cache_file = __DIR__ . '/tmp/last_health_check.txt';
        $cache_valid = false;
        
        if (file_exists($cache_file)) {
            $last_check = file_get_contents($cache_file);
            $last_check_time = strtotime($last_check);
            $cache_valid = (time() - $last_check_time) < 30; // Cache for 30 seconds
        }
        
        if (!$cache_valid) {
            // Run health check asynchronously via background task
            require_once __DIR__ . '/health_check.php';
            require_once __DIR__ . '/migration_handler.php';
            
            $health_checker = new HealthChecker();
            $health_checker->checkAllRouters();
            
            $migration_handler = new MigrationHandler();
            $migration_handler->checkAndRecover();
            
            // Update cache timestamp
            if (!file_exists(dirname($cache_file))) {
                @mkdir(dirname($cache_file), 0777, true);
            }
            file_put_contents($cache_file, date('Y-m-d H:i:s'));
        }
    } catch (Exception $e) {
        error_log("Health check error in router_helper: " . $e->getMessage());
    }
}

// Run health checks on page load (non-blocking)
if (!defined('SKIP_HEALTH_CHECK')) {
    runHealthCheckIfNeeded();
}
