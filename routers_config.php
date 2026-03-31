<?php
/**
 * Multi-Router Configuration
 * 
 * Define multiple MikroTik routers for load balancing and failover.
 * Each router has a unique identifier and credentials.
 * 
 * CONFIGURATION:
 * - 'converge' = Primary router (Converge ISP)
 * - 'globe'    = Secondary router (Globe Router)
 * 
 * Both routers must have identical hotspot profiles for seamless user migration.
 */

$routers = [
    'main' => [
        'name'      => 'Main MikroTik',
        'ip'        => '192.168.88.1',
        'user'      => 'hotspot-api',
        'pass'      => 'Pinkslippy1@',
        'port'      => 8728,
        'priority'  => 1,  
        'enabled'   => true
    ]
];

/**
 * Get router configuration by identifier
 */
function getRouterConfig($router_id) {
    global $routers;
    if (isset($routers[$router_id])) {
        return $routers[$router_id];
    }
    return null;
}

/**
 * Get all enabled routers
 */
function getAllRouters() {
    global $routers;
    $enabled = [];
    foreach ($routers as $id => $config) {
        if ($config['enabled']) {
            $enabled[$id] = $config;
        }
    }
    return $enabled;
}

/**
 * Get router by priority (returns array sorted by priority)
 */
function getRoutersByPriority() {
    $routers_list = getAllRouters();
    uasort($routers_list, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    return $routers_list;
}

/**
 * Get primary (highest priority) router ID
 */
function getPrimaryRouterId() {
    $routers = getRoutersByPriority();
    if (!empty($routers)) {
        return key($routers);
    }
    return null;
}

?>
