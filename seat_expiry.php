<?php
/**
 * Seat Expiry Cleanup
 * -------------------
 * Resets any 'reserved' seat whose 2-hour window has elapsed back to 'available'.
 * Also releases 'occupied' seats once voucher uptime is fully consumed.
 *
 * Usage (in PHP pages):
 *   require_once 'seat_expiry.php';
 *   $released = expireSeats($DB_con);   // returns int count of seats released
 *
 * Usage (standalone CLI / cron):
 *   php /path/to/seat_expiry.php
 *   Cron example (every 5 min): * /5 * * * * php /var/www/html/Easy-HotSpot-clone/seat_expiry.php
 */

/**
 * Release all reserved seats whose expires_at has passed.
 *
 * @param PDO $db  Active database connection
 * @return int     Number of seats released
 */
function expireSeats(PDO $db): int {
    try {
        $stmt = $db->prepare("
            UPDATE seats
            SET    status            = 'available',
                   reserved_by       = NULL,
                   voucher_username  = NULL,
                   reserved_at       = NULL,
                   expires_at        = NULL
            WHERE  status      = 'reserved'
              AND  expires_at  IS NOT NULL
              AND  expires_at  < NOW()
        ");
        $stmt->execute();
        return (int) $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[seat_expiry] expireSeats error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Parse RouterOS uptime string to seconds.
 */
function seatParseRosUptime(string $t): int {
    if ($t === '') return 0;
    $s = 0;
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) {
        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    }
    if (preg_match('/(\d+)w/', $t, $m)) $s += (int) $m[1] * 604800;
    if (preg_match('/(\d+)d/', $t, $m)) $s += (int) $m[1] * 86400;
    if (preg_match('/(\d+)h/', $t, $m)) $s += (int) $m[1] * 3600;
    if (preg_match('/(\d+)m/', $t, $m)) $s += (int) $m[1] * 60;
    if (preg_match('/(\d+)s/', $t, $m)) $s += (int) $m[1];
    return $s;
}

/**
 * Release occupied seats whose voucher time has been fully consumed.
 *
 * Criteria:
 *  - Seat must be occupied and mapped to an active voucher username.
 *  - For duration packages, if router uptime >= router limit-uptime, seat is released.
 *  - If hotspot user no longer exists on the router, seat is released as stale.
 *
 * @param PDO $db Active database connection
 * @param array $events Optional output list of expiry events for UI notifications
 * @return int Number of occupied seats released
 */
function expireConsumedOccupiedSeats(PDO $db, array &$events = []): int {
    if (defined('MOCK_MODE') && MOCK_MODE === true) {
        return 0;
    }

    if (!isset($GLOBALS['host'], $GLOBALS['user'], $GLOBALS['pass'])) {
        require_once __DIR__ . '/config.php';
    }
    require_once __DIR__ . '/routeros_api.php';

    $connection = createRouterConnection($GLOBALS['host'], $GLOBALS['user'], $GLOBALS['pass']);
    if (!$connection['success']) {
        return 0;
    }
    $util = $connection['util'];

    try {
        $stmt = $db->query("\n            SELECT s.id, s.voucher_username, COALESCE(v.limit_uptime, '') AS db_limit_uptime\n            FROM seats s\n            LEFT JOIN hotspot_vouchers v\n                   ON v.user_name = s.voucher_username\n                  AND v.status = 'Active'\n            WHERE s.status = 'occupied'\n              AND s.voucher_username IS NOT NULL\n              AND s.voucher_username <> ''\n        ");
        $occupied = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[seat_expiry] expireConsumedOccupiedSeats query error: ' . $e->getMessage());
        return 0;
    }

    if (!$occupied) {
        return 0;
    }

    $released = 0;
    foreach ($occupied as $seat) {
        $username = (string) $seat['voucher_username'];
        $releaseSeat = false;
        $reason = '';

        try {
            $util->setMenu('/ip/hotspot/user');
            $users = $util->find('name', $username);

            // If user no longer exists in router, free stale occupied seat.
            if (empty($users)) {
                $releaseSeat = true;
            } else {
                $u = $users[0];
                $limitStr = (string) ($u->getProperty('limit-uptime') ?? '');
                $usedStr  = (string) ($u->getProperty('uptime') ?? '');

                $limitSecs = seatParseRosUptime($limitStr);
                if ($limitSecs <= 0 && $seat['db_limit_uptime'] !== '') {
                    $limitSecs = seatParseRosUptime((string) $seat['db_limit_uptime']);
                }
                $usedSecs = seatParseRosUptime($usedStr);

                if ($limitSecs > 0 && $usedSecs >= $limitSecs) {
                    $releaseSeat = true;
                    $reason = 'time_exceeded';
                }
            }

            if ($releaseSeat) {
                // Ensure internet is cut immediately if any active session remains.
                $util->setMenu('/ip/hotspot/active');
                $activeSessions = $util->find('user', $username);
                foreach ($activeSessions as $session) {
                    try { $util->remove($session->getProperty('.id')); } catch (Exception $e) {}
                }

                $util->setMenu('/ip/hotspot/user');
                try { $util->removeUser($username); } catch (Exception $e) {}

                $updSeat = $db->prepare("\n                    UPDATE seats\n                    SET status = 'available',\n                        reserved_by = NULL,\n                        voucher_username = NULL,\n                        reserved_at = NULL,\n                        expires_at = NULL\n                    WHERE id = :id\n                ");
                $updSeat->execute([':id' => (int) $seat['id']]);

                $updVoucher = $db->prepare("\n                    UPDATE hotspot_vouchers\n                    SET status = 'Expired'\n                    WHERE user_name = :u\n                      AND status = 'Active'\n                ");
                $updVoucher->execute([':u' => $username]);

                if (($reason ?? '') === 'time_exceeded') {
                    $events[] = [
                        'event' => 'voucher_auto_logout_verified',
                        'username' => $username,
                        'seat_id' => (int) $seat['id'],
                        'message' => "Voucher time exceeded for {$username}. Auto-logout verified.",
                    ];

                    require_once __DIR__ . '/audit_log.php';
                    auditLog(
                        'voucher_auto_logout',
                        "Auto-logout verified: voucher time exceeded for hotspot user '{$username}' (seat ID " . (int) $seat['id'] . ').',
                        $username
                    );
                }

                $released++;
            }
        } catch (Exception $e) {
            error_log('[seat_expiry] expireConsumedOccupiedSeats user error (' . $username . '): ' . $e->getMessage());
        }
    }

    return $released;
}

// ── Standalone CLI / cron entry-point ────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    $dir = __DIR__;
    require_once $dir . '/dbconfig.php';
    $releasedReserved = expireSeats($DB_con);
    $releasedOccupied = expireConsumedOccupiedSeats($DB_con);
    echo '[' . date('Y-m-d H:i:s') . "] Expired {$releasedReserved} reserved seat(s); auto-released {$releasedOccupied} consumed occupied seat(s).\n";
}
