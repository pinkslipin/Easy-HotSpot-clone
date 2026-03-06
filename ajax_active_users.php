<?php
/**
 * AJAX endpoint: returns current active hotspot users as JSON.
 * Called by the "Active Now" modal for live-refresh.
 */
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
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
    $connection = createRouterConnection($host, $user, $pass);
    if (!$connection['success']) {
        echo json_encode(['success' => false, 'message' => 'Router connection failed']);
        exit;
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

    // 2. Fetch active sessions – two-pass so that shared vouchers (same username
    //    on multiple devices) all display the same remaining time.
    //
    //    MikroTik's limit-uptime clock starts from the FIRST session of a user.
    //    If Device A has been connected 30 min and Device B just connected, the
    //    correct remaining time for BOTH is:
    //        limit - accumulated_uptime - max_active_session_uptime
    //    Using each device's own session uptime would make Device B appear to
    //    have more time left than Device A, which is misleading and wrong.
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

    // Pass 2 – calculate remaining time using the SHARED (oldest) session clock
    $rows = [];
    foreach ($rawSessions as $raw) {
        $activeUser = $raw['user'];
        $info = isset($userLimits[$activeUser]) ? $userLimits[$activeUser] : null;

        if ($info && !empty($info['limit'])) {
            $limitSecs   = parseRosTime2($info['limit']);
            $accuSecs    = parseRosTime2($info['accu']);
            // All devices on this account see time remaining from the oldest
            // active session (max uptime), not from their own connect time.
            $sharedSecs  = $maxSessionSecs[$activeUser];
            $remaining   = $limitSecs - $accuSecs - $sharedSecs;
            $voucherLeft = fmtSecs2($remaining);
            $expired     = ($remaining <= 0);
        } else {
            $voucherLeft = 'Unlimited';
            $expired     = false;
        }

        $rows[] = [
            'server'      => $raw['server'],
            'domain'      => $raw['domain'],
            'user'        => $activeUser,
            'address'     => $raw['address'],
            'sessionUp'   => $raw['sessionUp'],
            'voucherLeft' => $voucherLeft,
            'expired'     => $expired,
        ];
    }

    echo json_encode(['success' => true, 'users' => $rows]);

} catch (Exception $e) {
    error_log('ajax_active_users error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch active users']);
}
