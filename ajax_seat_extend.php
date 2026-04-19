<?php
/**
 * Extend hotspot user uptime from the Seat Map.
 * Requires auth (any staff) — not admin-only — since staff handle walk-ins.
 *
 * POST params:
 *   csrf_token      — CSRF token
 *   seat_id         — int  (used to look up voucher_username and verify seat)
 *   extend_minutes  — int  (must be in allowed whitelist)
 */
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
require_once 'dbconfig.php';
require_once 'audit_log.php';

require_auth();
csrf_require();

function seJsonFail(string $msg): void
{
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Parse RouterOS uptime string to seconds ────────────────────────────────
function seParseUptime(string $t): int
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

function seSecondsToRos(int $total): string
{
    if ($total <= 0) return '0s';
    $d = intdiv($total, 86400); $total %= 86400;
    $h = intdiv($total, 3600);  $total %= 3600;
    $m = intdiv($total, 60);    $s = $total % 60;
    $r = '';
    if ($d) $r .= $d . 'd';
    if ($h) $r .= $h . 'h';
    if ($m) $r .= $m . 'm';
    if ($s || !$r) $r .= $s . 's';
    return $r;
}

// ── Input validation ───────────────────────────────────────────────────────
$seat_id      = intval($_POST['seat_id']       ?? 0);
$extend_mins  = intval($_POST['extend_minutes'] ?? 0);

if ($seat_id <= 0)    seJsonFail('Invalid seat ID.');
if ($extend_mins <= 0) seJsonFail('Invalid extension amount.');

// Whitelist — same as ajax_extend_user.php
$allowed = [30, 60, 120, 180, 300, 360];
if (!in_array($extend_mins, $allowed, true)) {
    seJsonFail('Invalid extension duration.');
}

// ── Look up the voucher username for this seat ─────────────────────────────
$stmt = $DB_con->prepare("SELECT voucher_username, seat_number FROM seats WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $seat_id]);
$seat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seat)                         seJsonFail('Seat not found.');
if (empty($seat['voucher_username'])) seJsonFail('No voucher is assigned to this seat.');

$username   = $seat['voucher_username'];
$seat_label = $seat['seat_number'];

// ── Mock mode ──────────────────────────────────────────────────────────────
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    auditLog('seat_extend', "Extended seat {$seat_label} / user {$username} by {$extend_mins} min (mock)");
    echo json_encode([
        'success'   => true,
        'message'   => "Extended $username by {$extend_mins} minutes (mock).",
        'new_limit' => seSecondsToRos($extend_mins * 60),
        'username'  => $username,
    ]);
    exit;
}

// ── Live router ────────────────────────────────────────────────────────────
require_once 'routeros_api.php';
$connection = createRouterConnection($host, $user, $pass);
if (!$connection['success']) {
    seJsonFail('Router connection failed: ' . $connection['error']);
}
$util   = $connection['util'];
$client = $connection['client'];

// Find user
$util->setMenu('/ip/hotspot/user');
$users = $util->find('name', $username);
if (empty($users)) {
    seJsonFail("User '$username' not found on router.");
}

$userId        = $users[0]->getProperty('.id');
$currentLimit  = $users[0]->getProperty('limit-uptime') ?? '';
$sessionUptime = $users[0]->getProperty('session-uptime') ?? '';

// Calculate remaining time and add extension
// remaining = max(0, limit - session_already_used)
// new_limit = session_already_used + remaining + extension
$limitSecs = seParseUptime($currentLimit);
$sessionSecs = seParseUptime($sessionUptime);
$extensionSecs = $extend_mins * 60;
$remainingSecs = max(0, $limitSecs - $sessionSecs);
$newSecs  = $sessionSecs + $remainingSecs + $extensionSecs;
$newLimit = seSecondsToRos($newSecs);

// Push to router
$query = new \RouterOS\Query('/ip/hotspot/user/set');
$query->equal('.id', $userId);
$query->equal('limit-uptime', $newLimit);
$client->query($query);

auditLog('seat_extend', "Extended seat {$seat_label} / user {$username} by {$extend_mins} min → new limit: {$newLimit}");

echo json_encode([
    'success'   => true,
    'message'   => "Extended $username by {$extend_mins} min. New limit: $newLimit",
    'new_limit' => $newLimit,
    'username'  => $username,
]);
