<?php
/**
 * Seat Walk-in / Check-in with Inline Voucher Issuance
 *
 * Atomically:
 *   1. Transitions the seat to 'occupied'
 *   2. Creates the hotspot user on the router
 *   3. Records the voucher in hotspot_vouchers
 *
 * POST params:
 *   csrf_token   — CSRF token
 *   action       — 'walkin'   (available → occupied)
 *                  'checkin'  (reserved  → occupied)
 *   seat_id      — integer seat primary key
 *   username     — hotspot username
 *   password     — hotspot password
 *   package_id   — package identifier from packages_config.php
 *   profile      — RouterOS hotspot user profile name
 *   limit_bytes  — data limit in GB (0 = no limit)
 *
 * Response: JSON { success, message, [username, password, package_name, price] }
 */

header('Content-Type: application/json');

require_once 'security_helper.php';
require_once 'packages_config.php';
require_once 'dbconfig.php';
require_once 'audit_log.php';

require_auth();
csrf_require();

// ── Helpers ───────────────────────────────────────────────────────────────────

function swJsonFail(string $msg): void
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────

$action      = trim($_POST['action']      ?? '');
$seat_id     = intval($_POST['seat_id']   ?? 0);
$username    = trim($_POST['username']    ?? '');
$password    = trim($_POST['password']    ?? '');
$package_id  = trim($_POST['package_id'] ?? '');
$profile     = trim($_POST['profile']    ?? 'default');
$limit_bytes = intval($_POST['limit_bytes'] ?? 0);

if (!in_array($action, ['walkin', 'checkin'], true)) swJsonFail('Invalid action.');
if ($seat_id <= 0)                                    swJsonFail('Invalid seat ID.');
if ($username === '')                                  swJsonFail('Username is required.');
if ($password === '')                                  swJsonFail('Password is required.');
if ($package_id === '')                                swJsonFail('Package is required.');
if ($profile === '')                                   $profile = 'default';

$package = getPackage($package_id);
if (!$package) swJsonFail('Unknown package: ' . htmlspecialchars($package_id));

// ── Router connection ─────────────────────────────────────────────────────────

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    require_once 'mock_router.php';
    $util = new MockRouterUtil();
} else {
    require_once 'config.php';
    require_once 'routeros_api.php';
    $connection = createRouterConnection($host, $user, $pass);
    if (!$connection['success']) {
        swJsonFail('Router connection failed: ' . $connection['error']);
    }
    $util = $connection['util'];
}

// ── DB transaction: lock seat, add router user, insert voucher ────────────────

try {
    $DB_con->beginTransaction();

    // Lock + verify the seat is in the expected state
    $requiredStatus = ($action === 'walkin') ? 'available' : 'reserved';
    $stmtSeat = $DB_con->prepare(
        'SELECT id, status FROM seats WHERE id = :id FOR UPDATE'
    );
    $stmtSeat->execute([':id' => $seat_id]);
    $seat = $stmtSeat->fetch(PDO::FETCH_ASSOC);

    if (!$seat) {
        $DB_con->rollBack();
        swJsonFail('Seat not found.');
    }
    if ($seat['status'] !== $requiredStatus) {
        $DB_con->rollBack();
        swJsonFail('Seat is no longer ' . $requiredStatus . '. Please refresh the map and try again.');
    }

    // ── Add user to router ─────────────────────────────────────────────────

    $limit_uptime = ($package['type'] === 'duration') ? $package['limit_uptime'] : '1d';

    // Remove stale/ghost account if it exists with no active session
    $util->setMenu('/ip/hotspot/active');
    $liveSession = $util->find('user', $username);
    if (!empty($liveSession)) {
        $DB_con->rollBack();
        swJsonFail('Username "' . htmlspecialchars($username) . '" is currently logged in on the network. Please choose a different username.');
    }
    $util->setMenu('/ip/hotspot/user');
    $staleEntry = $util->find('name', $username);
    if (!empty($staleEntry)) {
        try { $util->removeUser($username); } catch (Exception $e) {}
    }

    $util->setMenu('/ip/hotspot/user');
    $countBefore  = count($util);

    $userArgs = [
        'name'         => $username,
        'password'     => $password,
        'disabled'     => 'no',
        'limit-uptime' => $limit_uptime,
        'profile'      => $profile,
        'comment'      => 'PKG:' . $package_id . ';SEAT:' . $seat_id,
    ];
    if ($limit_bytes > 0) {
        $userArgs['limit-bytes-total'] = strval($limit_bytes * 1073741824);
    }
    $util->add($userArgs);

    if (count($util) <= $countBefore) {
        $DB_con->rollBack();
        swJsonFail('Username "' . htmlspecialchars($username) . '" already exists or could not be added.');
    }

    // ── Insert hotspot_vouchers record ─────────────────────────────────────

    $price           = $package['price'];
    $package_name    = getPackageDisplayName($package_id);
    $package_type    = $package['type'];
    $expires_on      = getExpiryDate();
    $db_limit_uptime = ($package['type'] === 'duration') ? $package['limit_uptime'] : '';

    // Generate sequential booking_id (same logic as ajax_adduser.php)
    $stmtBk = $DB_con->prepare(
        'SELECT booking_id FROM hotspot_vouchers ORDER BY booking_id DESC LIMIT 1'
    );
    $stmtBk->execute();
    $rowBk      = $stmtBk->fetch(PDO::FETCH_ASSOC);
    $booking_id = ($rowBk && isset($rowBk['booking_id'])) ? intval($rowBk['booking_id']) + 1 : 1;

    $uid      = $booking_id . '-1-' . date('dmY');
    $batch_id = strtoupper($package_id) . '-' . date('mdHi');

    $stmtV = $DB_con->prepare(
        'INSERT INTO hotspot_vouchers
            (created_on, created_by, creator, user_name, password,
             printed_times, printed_last, status, group_of, booking_id,
             limit_uptime, limit_bytes, profile, uid, batch_id,
             price, expires_on, package_id, package_name, package_type)
         VALUES
            (NOW(), :created_by, :creator, :user_name, :password,
             0, \'\', \'Active\', 1, :booking_id,
             :limit_uptime, :limit_bytes, :profile, :uid, :batch_id,
             :price, :expires_on, :package_id, :package_name, :package_type)'
    );
    $stmtV->execute([
        ':created_by'   => $_SESSION['username'],
        ':creator'      => $_SESSION['id'],
        ':user_name'    => $username,
        ':password'     => $password,
        ':booking_id'   => $booking_id,
        ':limit_uptime' => $db_limit_uptime,
        ':limit_bytes'  => $limit_bytes,
        ':profile'      => $profile,
        ':uid'          => $uid,
        ':batch_id'     => $batch_id,
        ':price'        => $price,
        ':expires_on'   => $expires_on,
        ':package_id'   => $package_id,
        ':package_name' => $package_name,
        ':package_type' => $package_type,
    ]);

    // ── Mark seat as occupied ──────────────────────────────────────────────

    $stmtUpd = $DB_con->prepare(
        'UPDATE seats
            SET status           = \'occupied\',
                reserved_by      = :who,
                voucher_username = :vuser,
                reserved_at      = NOW(),
                expires_at       = NULL
          WHERE id = :id'
    );
    $stmtUpd->execute([
        ':who'   => (int) $_SESSION['id'],
        ':vuser' => $username,
        ':id'    => $seat_id,
    ]);

    $DB_con->commit();

    // Audit log
    $actionLabel = ($action === 'walkin') ? 'Walk-in' : 'Check-in';
    auditLog(
        'seat_' . $action,
        "$actionLabel: seat #$seat_id → user $username ($package_name)"
    );

    echo json_encode([
        'success'      => true,
        'message'      => 'Seat occupied. Voucher issued for ' . $username . '.',
        'username'     => $username,
        'password'     => $password,
        'package_name' => $package_name,
        'price'        => formatPrice($price),
    ]);

} catch (Exception $e) {
    if ($DB_con->inTransaction()) {
        $DB_con->rollBack();
    }
    swJsonFail('Server error: ' . $e->getMessage());
}
