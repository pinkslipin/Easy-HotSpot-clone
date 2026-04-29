<?php
/**
 * AJAX: Transfer a customer to a new seat with fresh credentials.
 *
 * POST params:
 *   csrf_token   — CSRF token
 *   from_seat_id — int  (currently occupied seat)
 *   to_seat_id   — int  (destination, must be available)
 *   new_username — string (new hotspot username for the destination seat)
 *   new_password — string (new hotspot password)
 *
 * What happens:
 *   1. Remaining time is computed from the router (limit-uptime − session-uptime).
 *      Falls back to the full DB limit_uptime if the user is not currently connected.
 *   2. A new hotspot user is created on the router with the remaining time.
 *   3. The old hotspot user's active sessions are kicked and the user is removed.
 *   4. The old voucher record is marked 'Used'; a new Active record is inserted.
 *   5. Seats table is updated atomically.
 *
 * The customer's wifi will briefly drop and they must reconnect with new credentials.
 *
 * NOTE: auditLog() is called AFTER commit() — DDL inside it would implicitly
 *       commit any open DB transaction.
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';
require_once 'packages_config.php';
require_once 'config.php';
require_once 'routeros_api.php';

function stFail(string $msg): void
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Parse RouterOS uptime string to seconds (same as other seat files)
function stParseUptime(string $t): int
{
    if (empty($t)) return 0;
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) {
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    $s = 0;
    if (preg_match('/(\d+)w/', $t, $m)) $s += (int)$m[1] * 604800;
    if (preg_match('/(\d+)d/', $t, $m)) $s += (int)$m[1] * 86400;
    if (preg_match('/(\d+)h/', $t, $m)) $s += (int)$m[1] * 3600;
    if (preg_match('/(\d+)m/', $t, $m)) $s += (int)$m[1] * 60;
    if (preg_match('/(\d+)s/', $t, $m)) $s += (int)$m[1];
    return $s;
}

// Convert seconds to RouterOS limit-uptime string (e.g. 3620 → "1h2s")
function stSecondsToRos(int $secs): string
{
    if ($secs <= 0) return '0s';
    $w = intdiv($secs, 604800); $secs %= 604800;
    $d = intdiv($secs, 86400);  $secs %= 86400;
    $h = intdiv($secs, 3600);   $secs %= 3600;
    $m = intdiv($secs, 60);     $secs %= 60;
    $out = '';
    if ($w) $out .= "{$w}w";
    if ($d) $out .= "{$d}d";
    if ($h) $out .= "{$h}h";
    if ($m) $out .= "{$m}m";
    if ($secs || !$out) $out .= "{$secs}s";
    return $out;
}

// ── Input ─────────────────────────────────────────────────────────────────────
$from_id     = isset($_POST['from_seat_id']) ? (int)$_POST['from_seat_id']     : 0;
$to_id       = isset($_POST['to_seat_id'])   ? (int)$_POST['to_seat_id']       : 0;
$new_username = trim($_POST['new_username']  ?? '');
$new_password = trim($_POST['new_password']  ?? '');

if ($from_id <= 0 || $to_id <= 0)   stFail('Invalid seat IDs.');
if ($from_id === $to_id)             stFail('Source and destination must be different.');
if ($new_username === '')            stFail('New username is required.');
if ($new_password === '')            stFail('New password is required.');

$staff_id = (int)$_SESSION['id'];

// ── Router connection (before DB transaction so we can validate + query) ──────
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    $router_ok  = true;
    $util       = null;
    $mock_mode  = true;
} else {
    $conn = createRouterConnection($host, $user, $pass);
    if (!$conn['success']) stFail('Router connection failed: ' . $conn['error']);
    $util      = $conn['util'];
    $mock_mode = false;
    $router_ok = true;

    // Check new username isn't already on the router.
    // If it exists but has NO active session it is a stale/ghost account —
    // silently remove it so the new user can be created without issues.
    $util->setMenu('/ip/hotspot/active');
    $activeSessions = $util->find('user', $new_username);
    if (!empty($activeSessions)) {
        stFail('Username "' . htmlspecialchars($new_username) . '" is currently logged in on the network. Please choose a different username.');
    }
    // No active session — remove any stale account entry before re-creating
    $util->setMenu('/ip/hotspot/user');
    $existing = $util->find('name', $new_username);
    if (!empty($existing)) {
        try { $util->removeUser($new_username); } catch (Exception $e) {}
    }
}

// ── DB transaction ────────────────────────────────────────────────────────────
try {
    $DB_con->beginTransaction();

    // Check new_username not already Active in DB
    $chk = $DB_con->prepare("SELECT id FROM hotspot_vouchers WHERE user_name = :u AND status = 'Active' LIMIT 1");
    $chk->execute([':u' => $new_username]);
    if ($chk->fetch()) {
        $DB_con->rollBack();
        stFail('Username "' . htmlspecialchars($new_username) . '" is already in use. Please choose a different username.');
    }

    // Lock both seat rows (lower id first to prevent deadlocks)
    $lo = min($from_id, $to_id);
    $hi = max($from_id, $to_id);
    $stmt = $DB_con->prepare("
        SELECT id, seat_number, zone, status, voucher_username, reserved_by
        FROM   seats
        WHERE  id IN (:lo, :hi)
        ORDER  BY id
        FOR UPDATE
    ");
    $stmt->execute([':lo' => $lo, ':hi' => $hi]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 2) { $DB_con->rollBack(); stFail('One or both seats not found.'); }

    $byId = [];
    foreach ($rows as $r) { $byId[$r['id']] = $r; }
    $from = $byId[$from_id];
    $to   = $byId[$to_id];

    if ($from['status'] !== 'occupied') {
        $DB_con->rollBack();
        stFail("Seat {$from['seat_number']} is no longer occupied.");
    }
    if ($to['status'] !== 'available') {
        $DB_con->rollBack();
        stFail("Seat {$to['seat_number']} is no longer available.");
    }
    if (empty($from['voucher_username'])) {
        $DB_con->rollBack();
        stFail("Seat {$from['seat_number']} has no voucher attached.");
    }

    $old_username = $from['voucher_username'];

    // ── Get old voucher details to copy to the new record ──────────────────
    $vStmt = $DB_con->prepare("
        SELECT limit_uptime, limit_bytes, profile, price,
               package_id, package_name, package_type
        FROM   hotspot_vouchers
        WHERE  user_name = :u AND status = 'Active'
        LIMIT  1
    ");
    $vStmt->execute([':u' => $old_username]);
    $oldVoucher = $vStmt->fetch(PDO::FETCH_ASSOC);

    // ── Compute remaining time ─────────────────────────────────────────────
    // Remaining = limit - max(cumulative, session). user.uptime refreshes lazily,
    // so taking the max picks whichever counter is more advanced and avoids both
    // under- and over-counting the time the customer has actually used.
    $limit_secs       = $oldVoucher ? stParseUptime($oldVoucher['limit_uptime'] ?? '') : 0;
    $cumulative_secs  = 0;
    $session_secs     = 0;

    if (!$mock_mode && $util && $limit_secs > 0) {
        $util->setMenu('/ip/hotspot/user');
        $userList = $util->find('name', $old_username);
        if (!empty($userList)) {
            $cumulative_secs = stParseUptime($userList[0]->getProperty('uptime') ?? '');
        }

        $util->setMenu('/ip/hotspot/active');
        $sessions = $util->find('user', $old_username);
        if (!empty($sessions)) {
            foreach ($sessions as $s) {
                $up = stParseUptime($s->getProperty('uptime') ?? '');
                if ($up > $session_secs) $session_secs = $up;
            }
        }
    }

    $used_secs        = max($cumulative_secs, $session_secs);
    $remaining_secs   = ($limit_secs > 0) ? max(60, $limit_secs - $used_secs) : 0;
    $new_limit_uptime = ($remaining_secs > 0) ? stSecondsToRos($remaining_secs) : ($oldVoucher['limit_uptime'] ?? '1h');

    // ── Create new router user with remaining time ─────────────────────────
    if (!$mock_mode && $util) {
        $util->setMenu('/ip/hotspot/user');
        $countBefore = count($util);
        $util->add([
            'name'         => $new_username,
            'password'     => $new_password,
            'disabled'     => 'no',
            'limit-uptime' => $new_limit_uptime,
            'profile'      => $oldVoucher['profile'] ?? 'default',
            'comment'      => 'TRANSFER:' . $from['seat_number'] . '->' . $to['seat_number'],
        ]);
        if (count($util) <= $countBefore) {
            $DB_con->rollBack();
            stFail('Router rejected the new user account. Please try a different username.');
        }
    }

    // ── Update seats ────────────────────────────────────────────────────────
    $DB_con->prepare("
        UPDATE seats SET status='available', reserved_by=NULL,
               voucher_username=NULL, reserved_at=NULL, expires_at=NULL
        WHERE id=:id
    ")->execute([':id' => $from_id]);

    $DB_con->prepare("
        UPDATE seats SET status='occupied', reserved_by=:who,
               voucher_username=:vuser, reserved_at=NOW(), expires_at=NULL
        WHERE id=:id
    ")->execute([':who' => $staff_id, ':vuser' => $new_username, ':id' => $to_id]);

    // ── Retire old voucher ──────────────────────────────────────────────────
    $DB_con->prepare("
        UPDATE hotspot_vouchers SET status='Used'
        WHERE  user_name=:u AND status='Active'
    ")->execute([':u' => $old_username]);

    // ── Insert new voucher record ───────────────────────────────────────────
    $bkStmt = $DB_con->query('SELECT booking_id FROM hotspot_vouchers ORDER BY booking_id DESC LIMIT 1');
    $bkRow  = $bkStmt->fetch(PDO::FETCH_ASSOC);
    $booking_id = ($bkRow && isset($bkRow['booking_id'])) ? intval($bkRow['booking_id']) + 1 : 1;
    $uid        = $booking_id . '-1-' . date('dmY');

    $insStmt = $DB_con->prepare("
        INSERT INTO hotspot_vouchers
            (created_on, created_by, creator, user_name, password,
             printed_times, printed_last, status, group_of, booking_id,
             limit_uptime, limit_bytes, profile, uid,
             price, expires_on, package_id, package_name, package_type)
        VALUES
            (NOW(), :created_by, :creator, :uname, :pass,
             0, '', 'Active', 1, :bid,
             :limit_uptime, :limit_bytes, :profile, :uid,
             :price, :expires_on, :pkg_id, :pkg_name, :pkg_type)
    ");
    $insStmt->execute([
        ':created_by'   => $_SESSION['username'],
        ':creator'      => $staff_id,
        ':uname'        => $new_username,
        ':pass'         => $new_password,
        ':bid'          => $booking_id,
        ':limit_uptime' => $new_limit_uptime,
        ':limit_bytes'  => $oldVoucher['limit_bytes']  ?? '0',
        ':profile'      => $oldVoucher['profile']      ?? 'default',
        ':uid'          => $uid,
        ':price'        => $oldVoucher['price']        ?? 0,
        ':expires_on'   => getExpiryDate(),
        ':pkg_id'       => $oldVoucher['package_id']   ?? '',
        ':pkg_name'     => $oldVoucher['package_name'] ?? '',
        ':pkg_type'     => $oldVoucher['package_type'] ?? 'duration',
    ]);

    $DB_con->commit();

    // ── Post-commit: kick old sessions + remove old router user ───────────
    if (!$mock_mode && $util) {
        // Kick active sessions (device loses internet immediately)
        $util->setMenu('/ip/hotspot/active');
        $activeSessions = $util->find('user', $old_username);
        foreach ($activeSessions as $s) {
            try { $util->remove($s->getProperty('.id')); } catch (Exception $e) {}
        }
        // Remove the old hotspot user account
        $util->setMenu('/ip/hotspot/user');
        try { $util->removeUser($old_username); } catch (Exception $e) {}
    }

    // ── Post-commit: audit log ─────────────────────────────────────────────
    auditLog(
        'seat_transfer',
        "Transferred from {$from['seat_number']} ({$old_username}) "
        . "to {$to['seat_number']} ({$new_username}), "
        . "remaining: {$new_limit_uptime}, by staff #{$staff_id}"
    );

    echo json_encode([
        'success'          => true,
        'message'          => "Customer moved {$from['seat_number']} → {$to['seat_number']}.",
        'new_username'     => $new_username,
        'new_password'     => $new_password,
        'remaining_time'   => $new_limit_uptime,
    ]);

} catch (PDOException $e) {
    if ($DB_con->inTransaction()) $DB_con->rollBack();
    error_log('[ajax_seat_transfer] PDOException: ' . $e->getMessage());
    stFail('Database error. Please try again.');
}
