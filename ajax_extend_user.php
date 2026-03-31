<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();

// Parse RouterOS uptime string to seconds
// Handles formats: "5h30m10s", "1d5h", "00:30:00"
function parseUptimeToSeconds($time) {
    if (empty($time)) return 0;
    $seconds = 0;
    // HH:MM:SS format
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $time, $m)) {
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    if (preg_match('/(\d+)w/', $time, $m)) $seconds += (int)$m[1] * 604800;
    if (preg_match('/(\d+)d/', $time, $m)) $seconds += (int)$m[1] * 86400;
    if (preg_match('/(\d+)h/', $time, $m)) $seconds += (int)$m[1] * 3600;
    if (preg_match('/(\d+)m/', $time, $m)) $seconds += (int)$m[1] * 60;
    if (preg_match('/(\d+)s/', $time, $m)) $seconds += (int)$m[1];
    return $seconds;
}

// Convert seconds back to RouterOS time format e.g. "1h30m"
function secondsToRouterOS($total) {
    $total = (int)$total;
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

$username     = isset($_POST['username'])       ? trim($_POST['username'])       : '';
$extend_mins  = isset($_POST['extend_minutes']) ? (int)$_POST['extend_minutes'] : 0;

if (empty($username) || $extend_mins <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Whitelist allowed extension values (minutes) to prevent abuse
$allowed = [30, 60, 120, 180, 300, 360];
if (!in_array($extend_mins, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid extension duration.']);
    exit;
}

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    echo json_encode(['success' => true, 'message' => "Extended $username by {$extend_mins} minutes (mock)."]);
    exit;
}

require_once 'routeros_api.php';
require_once 'routers_config.php';

// PHASE 4: Look up which router the user is assigned to
require_once 'dbconfig.php';
try {
	$stmt = $DB_con->prepare("SELECT assigned_router FROM hotspot_vouchers WHERE user_name = :user_name LIMIT 1");
	$stmt->execute([':user_name' => $username]);
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	$assigned_router = $result ? $result['assigned_router'] : 'converge';
} catch (Exception $e) {
	$assigned_router = 'converge'; // Default fallback
}

// Connect to the assigned router
$router_config = getRouterConfig($assigned_router);
$connection = createRouterConnection($router_config['ip'], $router_config['user'], $router_config['pass'], $router_config['port']);

if (!$connection['success']) {
    echo json_encode(['success' => false, 'message' => 'Router connection failed.']);
    exit;
}
$util   = $connection['util'];
$client = $connection['client'];

// ── Step 1: Find the user in /ip hotspot user ──────────────────────────────
$util->setMenu('/ip/hotspot/user');
$users = $util->find('name', $username);

if (empty($users)) {
    echo json_encode(['success' => false, 'message' => "User '$username' not found on assigned router ($assigned_router)."]);
    exit;
}

$userId       = $users[0]->getProperty('.id');
$currentLimit = $users[0]->getProperty('limit-uptime'); // e.g. "5h" or ""

// ── Step 2: Calculate new limit-uptime ────────────────────────────────────
$currentSecs   = parseUptimeToSeconds($currentLimit);
$extensionSecs = $extend_mins * 60;
$newSecs       = $currentSecs + $extensionSecs;
$newLimit      = secondsToRouterOS($newSecs);

// ── Step 3: Push update to router ─────────────────────────────────────────
// Note: ModernRouterClient::query() already calls ->read() internally, so we
// do NOT chain ->read() again here.
$query = new \RouterOS\Query('/ip/hotspot/user/set');
$query->equal('.id', $userId);
$query->equal('limit-uptime', $newLimit);
$client->query($query);

// ── Step 4: Log the action ────────────────────────────────────────────────
require_once 'audit_log.php';
auditLog('extend_user', "Extended user '$username' by {$extend_mins} min on $assigned_router. New limit-uptime: $newLimit");

echo json_encode([
    'success'   => true,
    'message'   => "Extended $username by {$extend_mins} minutes. New limit: $newLimit",
    'new_limit' => $newLimit,
]);
