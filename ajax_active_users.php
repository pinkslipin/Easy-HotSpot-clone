<?php
/**
 * AJAX endpoint: returns current active hotspot users from ALL routers as JSON.
 * Called by the "Active Now" modal for live-refresh.
 * Combines active sessions from both Converge and Globe routers.
 */
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
require_once 'load_balancer.php';
require_auth(); // calls secure_session_start() internally
csrf_require();

// ── Helpers ────────────────────────────────────────────────────────────────
function parseRosTime2($t) {
    if (empty($t)) return 0;
    $s = 0;
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) return $m[1]*3600+$m[2]*60+$m[3];
    if (preg_match('/(\d+)w/', $t, $m)) $s += $m[1]*604800;
    if (preg_match('/(\d+)d/', $t, $m)) $s += $m[1]*86400;
    if (preg_match('/(\d+)h/', $t, $m)) $s += $m[1]*3600;
    if (preg_match('/(\d+)m/', $t, $m)) $s += $m[1]*60;
    if (preg_match('/(\d+)s/', $t, $m)) $s += $m[1];
    return $s;
}
function fmtSecs2($total) {
    if ($total <= 0) return 'Expired';
    $d = intdiv($total, 86400); $total %= 86400;
    $h = intdiv($total, 3600);  $total %= 3600;
    $m = intdiv($total, 60);    $s = $total % 60;
    $r = '';
    if ($d) $r .= $d.'d';
    if ($h) $r .= $h.'h';
    if ($m) $r .= $m.'m';
    if ($s || !$r) $r .= $s.'s';
    return $r;
}

try {
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        // Mock mode – return empty for now
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }

    require_once 'routeros_api.php';
    require_once 'router_manager.php';
    
    $lb = new LoadBalancer();
    $rm = new RouterManager();
    $allRows = [];
    
    // Get all configured routers
    $routers = $rm->getAllRouters();

    // Query each router for active sessions
    foreach ($routers as $routerId => $routerConfig) {
        if (!$routerConfig) continue;

        try {
            $connection = createRouterConnection(
                $routerConfig['ip'],
                $routerConfig['user'],
                $routerConfig['pass']
            );

            if (!$connection['success']) {
                error_log("Failed to connect to router: $routerId");
                continue;
            }

            $util = $connection['util'];

            // 1. Build lookup: username -> [limit-uptime, accumulated-uptime] from /ip hotspot user
            $util->setMenu('/ip/hotspot/user');
            $userLimits = [];
            foreach ($util->getAll() as $u) {
                $uname = $u->getProperty('name');
                $userLimits[$uname] = [
                    'limit' => $u->getProperty('limit-uptime'),
                    'accu'  => $u->getProperty('uptime'),
                ];
            }

            // 2. Fetch active sessions
            $util->setMenu('/ip/hotspot/active');
            $rawSessions  = [];
            $maxSessionSecs = [];   // username => max session uptime in seconds

            // Pass 1 – collect raw data and find max session uptime per username
            foreach ($util->getAll() as $item) {
                $activeUser  = $item->getProperty('user');
                $sessionUp   = $item->getProperty('uptime');
                $sessionSecs = parseRosTime2($sessionUp);

                $rawSessions[] = [
                    'server'     => $item->getProperty('server'),
                    'domain'     => $item->getProperty('domain'),
                    'user'       => $activeUser,
                    'address'    => $item->getProperty('address'),
                    'sessionUp'  => $sessionUp,
                    'sessionSecs'=> $sessionSecs,
                ];

                if (!isset($maxSessionSecs[$activeUser]) || $sessionSecs > $maxSessionSecs[$activeUser]) {
                    $maxSessionSecs[$activeUser] = $sessionSecs;
                }
            }

            // Pass 2 – calculate remaining time and include router name
            foreach ($rawSessions as $raw) {
                $activeUser = $raw['user'];
                $info = isset($userLimits[$activeUser]) ? $userLimits[$activeUser] : null;

                if ($info && !empty($info['limit'])) {
                    $limitSecs   = parseRosTime2($info['limit']);
                    $accuSecs    = parseRosTime2($info['accu']);
                    $sharedSecs  = $maxSessionSecs[$activeUser];
                    $remaining   = $limitSecs - $accuSecs - $sharedSecs;
                    $voucherLeft = fmtSecs2($remaining);
                    $expired     = ($remaining <= 0);
                } else {
                    $voucherLeft = 'Unlimited';
                    $expired     = false;
                }

                $allRows[] = [
                    'router'      => $routerConfig['name'],
                    'router_ip'   => $routerConfig['ip'],
                    'server'      => $raw['server'],
                    'domain'      => $raw['domain'],
                    'user'        => $activeUser,
                    'address'     => $raw['address'],
                    'sessionUp'   => $raw['sessionUp'],
                    'voucherLeft' => $voucherLeft,
                    'expired'     => $expired,
                ];
            }
        } catch (Exception $e) {
            error_log("Error querying router $routerId: " . $e->getMessage());
            continue;
        }
    }

    // Sort by username for cleaner display
    usort($allRows, function($a, $b) {
        return strcmp($a['user'], $b['user']);
    });

    echo json_encode(['success' => true, 'users' => $allRows]);

} catch (Exception $e) {
    error_log('ajax_active_users error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch active users']);
}
