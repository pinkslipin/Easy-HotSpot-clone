<?php
/**
 * Seat Detail — live session info for an occupied or reserved seat.
 *
 * GET params:
 *   csrf_token — CSRF token
 *   seat_id    — integer seat primary key
 *
 * Response JSON:
 * {
 *   success: true,
 *   seat_number, zone, status,
 *   voucher_username, package_name, voucher_price, voucher_uptime,
 *   staff_name, occupied_since,
 *   // live (from router — null if user not currently connected):
 *   session_uptime, time_left_secs, time_left_fmt, ip_address, mac_address,
 *   bytes_in_fmt, bytes_out_fmt,
 *   is_online
 * }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
require_auth();
csrf_require();
require_once 'dbconfig.php';

function sdFail(string $msg): void
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Parse RouterOS uptime string to seconds ────────────────────────────────
function sdParseUptime(string $t): int
{
    if (empty($t)) return 0;
    $s = 0;
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) {
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    if (preg_match('/(\d+)w/', $t, $m)) $s += (int)$m[1] * 604800;
    if (preg_match('/(\d+)d/', $t, $m)) $s += (int)$m[1] * 86400;
    if (preg_match('/(\d+)h/', $t, $m)) $s += (int)$m[1] * 3600;
    if (preg_match('/(\d+)m/', $t, $m)) $s += (int)$m[1] * 60;
    if (preg_match('/(\d+)s/', $t, $m)) $s += (int)$m[1];
    return $s;
}

function sdFmtSecs(int $secs): string
{
    if ($secs <= 0) return '0s';
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    $out = '';
    if ($h) $out .= "{$h}h ";
    if ($m || $h) $out .= "{$m}m ";
    $out .= "{$s}s";
    return trim($out);
}

function sdFmtBytes(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 0)       . ' KB';
    return $bytes . ' B';
}

/**
 * Format a finish timestamp as "3:00 p.m." given seconds remaining from now.
 */
function sdFmtFinish(int $secsLeft): string
{
    $ts  = time() + $secsLeft;
    $fmt = date('g:i a', $ts);                           // e.g. "3:00 pm"
    return str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $fmt);
}

// ── Input ──────────────────────────────────────────────────────────────────
$seat_id = intval($_GET['seat_id'] ?? 0);
if ($seat_id <= 0) sdFail('Invalid seat ID.');

// ── Fetch seat + voucher from DB ───────────────────────────────────────────
$stmt = $DB_con->prepare("
    SELECT s.id, s.seat_number, s.zone, s.status,
           s.voucher_username, s.reserved_at, s.expires_at,
           v.package_name, v.price AS voucher_price,
           v.limit_uptime AS voucher_uptime, v.package_id,
           hu.username AS staff_name
    FROM   seats s
    LEFT JOIN hotspot_vouchers v
           ON v.user_name = s.voucher_username AND v.status = 'Active'
    LEFT JOIN hotspot_users hu
           ON hu.user_id = s.reserved_by
    WHERE  s.id = :id
    LIMIT  1
");
$stmt->execute([':id' => $seat_id]);
$seat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seat) sdFail('Seat not found.');

// Build base response
$resp = [
    'success'          => true,
    'seat_number'      => $seat['seat_number'],
    'zone'             => $seat['zone'],
    'status'           => $seat['status'],
    'voucher_username' => $seat['voucher_username'],
    'package_name'     => $seat['package_name'],
    'voucher_price'    => $seat['voucher_price'] !== null ? (float)$seat['voucher_price'] : null,
    'voucher_uptime'   => $seat['voucher_uptime'],
    'package_id'       => $seat['package_id'],
    'staff_name'       => $seat['staff_name'],
    'occupied_since'   => null,
    'expires_at_fmt'   => null,
    // live fields (filled below)
    'is_online'        => false,
    'session_uptime'   => null,
    'time_left_secs'   => null,
    'time_left_fmt'    => null,
    'ip_address'       => null,
    'mac_address'      => null,
    'bytes_in_fmt'     => null,
    'bytes_out_fmt'    => null,
    'finish_time_fmt'  => null,
];

if ($seat['reserved_at']) {
    $diff = time() - strtotime($seat['reserved_at']);
    if ($diff < 3600) {
        $resp['occupied_since'] = intdiv($diff, 60) . 'm ago';
    } elseif ($diff < 86400) {
        $h = intdiv($diff, 3600);
        $m = intdiv($diff % 3600, 60);
        $resp['occupied_since'] = "{$h}h {$m}m ago";
    } else {
        $resp['occupied_since'] = date('M j, g:ia', strtotime($seat['reserved_at']));
    }
}
if ($seat['expires_at']) {
    $resp['expires_at_fmt'] = date('g:ia', strtotime($seat['expires_at']));
}

// ── Live router query (only if we have a voucher username) ─────────────────
if (!empty($seat['voucher_username'])) {
    require_once 'config.php';

    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        // Mock data for development
        $resp['is_online']      = true;
        $resp['session_uptime'] = '0h 23m 14s';
        $resp['ip_address']     = '192.168.88.100';
        $resp['mac_address']    = 'AA:BB:CC:DD:EE:FF';
        $resp['bytes_in_fmt']   = '45.2 MB';
        $resp['bytes_out_fmt']  = '3.7 MB';
        // Compute time left from voucher uptime
        if (!empty($seat['voucher_uptime'])) {
            $limitSecs   = sdParseUptime($seat['voucher_uptime']);
            $sessionSecs = sdParseUptime('0h23m14s');
            $leftSecs    = max(0, $limitSecs - $sessionSecs);
            $resp['time_left_secs']  = $leftSecs;
            $resp['time_left_fmt']   = sdFmtSecs($leftSecs);
            $resp['finish_time_fmt'] = sdFmtFinish($leftSecs);
        }
    } else {
        require_once 'routeros_api.php';
        $connection = createRouterConnection($host, $user, $pass);
        if ($connection['success']) {
            $util   = $connection['util'];
            $client = $connection['client'];

            // ── Get user's limit-uptime from hotspot user list ─────────────
            $util->setMenu('/ip/hotspot/user');
            $rosUsers = $util->find('name', $seat['voucher_username']);
            $limitSecs = 0;
            if (!empty($rosUsers)) {
                $limitStr  = $rosUsers[0]->getProperty('limit-uptime') ?? '';
                $limitSecs = sdParseUptime($limitStr);
                // Override with DB value if router value is absent
                if ($limitSecs === 0 && !empty($seat['voucher_uptime'])) {
                    $limitSecs = sdParseUptime($seat['voucher_uptime']);
                }
            } elseif (!empty($seat['voucher_uptime'])) {
                $limitSecs = sdParseUptime($seat['voucher_uptime']);
            }

            // ── Get active session ────────────────────────────────────────
            $util->setMenu('/ip/hotspot/active');
            $activeSessions = $util->find('user', $seat['voucher_username']);

            if (!empty($activeSessions)) {
                // Pick the session with the most uptime (handles multi-device)
                $maxUptime = 0;
                $bestSession = null;
                foreach ($activeSessions as $session) {
                    $uptimeSecs = sdParseUptime($session->getProperty('uptime') ?? '');
                    if ($uptimeSecs > $maxUptime) {
                        $maxUptime   = $uptimeSecs;
                        $bestSession = $session;
                    }
                }
                if ($bestSession) {
                    $sessionSecs = $maxUptime;
                    $leftSecs    = ($limitSecs > 0) ? max(0, $limitSecs - $sessionSecs) : null;

                    $resp['is_online']      = true;
                    $resp['session_uptime'] = sdFmtSecs($sessionSecs);
                    $resp['time_left_secs']  = $leftSecs;
                    $resp['time_left_fmt']   = ($leftSecs !== null) ? sdFmtSecs($leftSecs) : null;
                    $resp['finish_time_fmt'] = ($leftSecs !== null) ? sdFmtFinish($leftSecs) : null;
                    $resp['ip_address']     = $bestSession->getProperty('address')   ?? null;
                    $resp['mac_address']    = $bestSession->getProperty('mac-address') ?? null;
                    $bytesIn  = intval($bestSession->getProperty('bytes-in')  ?? 0);
                    $bytesOut = intval($bestSession->getProperty('bytes-out') ?? 0);
                    $resp['bytes_in_fmt']  = sdFmtBytes($bytesIn);
                    $resp['bytes_out_fmt'] = sdFmtBytes($bytesOut);
                }
            } else {
                // User has a voucher but isn't actively connected right now
                $resp['is_online']     = false;
                $resp['time_left_fmt'] = ($limitSecs > 0) ? sdFmtSecs($limitSecs) . ' (not connected)' : null;
            }
        }
    }
}

echo json_encode($resp);
