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
