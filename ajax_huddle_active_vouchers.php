<?php
/**
 * AJAX: Get active hotspot vouchers linked to huddle room bookings
 * Returns vouchers issued for checked-in huddle room bookings
 * 
 * Response JSON:
 * {
 *   success: bool,
 *   vouchers: [
 *     {
 *       booking_id: int,
 *       booking_ref: string,
 *       username: string,
 *       address: string,
 *       session_uptime: string,
 *       voucher_left: string,
 *       expired: bool
 *     }
 *   ]
 * }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_auth();
// Note: CSRF check not needed for GET requests (read-only operation)
require_once 'dbconfig.php';
require_once 'config.php';

function parseRosTime($t) {
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

function fmtSecs($total) {
    if ($total <= 0) return 'Expired';
    $d = intdiv($total, 86400); $total %= 86400;
    $h = intdiv($total, 3600);  $total %= 3600;
    $m = intdiv($total, 60);    $s = $total % 60;
    $r = '';
    if ($d) $r .= $d.'d ';
    if ($h) $r .= $h.'h ';
    if ($m) $r .= $m.'m';
    if ($s && !$m && !$h && !$d) $r .= $s.'s';
    return trim($r);
}

try {
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        echo json_encode(['success' => true, 'vouchers' => []]);
        exit;
    }

    require_once 'routeros_api.php';
    $routerConn = createRouterConnection($host, $user, $pass);
    
    if (!$routerConn['success']) {
        error_log("Router connection failed in ajax_huddle_active_vouchers");
        echo json_encode(['success' => true, 'vouchers' => [], 'message' => 'Router offline']);
        exit;
    }

    $util = $routerConn['util'];
    
    // Step 1: Get all active hotspot users with their session info
    $util->setMenu('/ip/hotspot/active');
    $activeUsers = [];
    
    foreach ($util->getAll() as $item) {
        $username = $item->getProperty('user');
        $address = $item->getProperty('address');
        $sessionUp = $item->getProperty('uptime');
        
        $activeUsers[$username] = [
            'address' => $address,
            'sessionUp' => $sessionUp,
            'sessionSecs' => parseRosTime($sessionUp)
        ];
    }
    
    // Step 2: Get user limits for computing remaining time
    $util->setMenu('/ip/hotspot/user');
    $userLimits = [];
    
    foreach ($util->getAll() as $u) {
        $username = $u->getProperty('name');
        $userLimits[$username] = [
            'limit' => $u->getProperty('limit-uptime'),
            'accu' => $u->getProperty('uptime')
        ];
    }
    
    // Step 3: Get all logged-in vouchers from database and match with active users
    $stmt = $DB_con->prepare("
        SELECT hb.id as booking_id, hb.booking_ref, hv.user_name
        FROM huddle_room_bookings hb
        INNER JOIN hotspot_vouchers hv ON hb.id = hv.booking_id
        WHERE hb.status = 'checked_in'
        AND hv.status = 'Active'
        AND hv.user_name IS NOT NULL
        ORDER BY hb.booking_date DESC, hb.start_time DESC
    ");
    $stmt->execute();
    $bookingVouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Step 4: Combine data - only include vouchers that are currently online
    $result = [];
    
    foreach ($bookingVouchers as $bv) {
        $username = $bv['user_name'];
        
        // Only include if user is currently active
        if (!isset($activeUsers[$username])) {
            continue;
        }
        
        $activeData = $activeUsers[$username];
        $limitData = $userLimits[$username] ?? null;
        
        // Calculate remaining time
        if ($limitData && !empty($limitData['limit'])) {
            $limitSecs = parseRosTime($limitData['limit']);
            $accuSecs = parseRosTime($limitData['accu']);
            $sessionSecs = $activeData['sessionSecs'];
            $remaining = $limitSecs - $accuSecs - $sessionSecs;
            $voucherLeft = fmtSecs($remaining);
            $expired = ($remaining <= 0);
        } else {
            $voucherLeft = 'Unlimited';
            $expired = false;
        }
        
        $result[] = [
            'booking_id' => (int)$bv['booking_id'],
            'booking_ref' => $bv['booking_ref'],
            'username' => $username,
            'address' => $activeData['address'],
            'session_uptime' => $activeData['sessionUp'],
            'voucher_left' => $voucherLeft,
            'expired' => $expired
        ];
    }
    
    echo json_encode(['success' => true, 'vouchers' => $result]);

} catch (Exception $e) {
    error_log("Error in ajax_huddle_active_vouchers: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
