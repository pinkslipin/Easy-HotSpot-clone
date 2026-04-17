<?php
/**
 * Extend hotspot user uptime
 * Works for both seat-based and voucher-based systems
 *
 * POST params:
 *   username       — hotspot username
 *   extend_minutes — duration to extend (30, 60, 120, 180, 300, 360)
 *   csrf_token     — CSRF token
 */
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();

// Parse RouterOS uptime string to seconds
function parseUptime($t) {
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

// Convert seconds to RouterOS format
function secondsToRos($total) {
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

function extFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Parse input
$username = trim($_POST['username'] ?? '');
$extend_mins = intval($_POST['extend_minutes'] ?? 0);

if (empty($username) || $extend_mins <= 0) {
    extFail('Invalid parameters.');
}

// Whitelist allowed values
$allowed = [30, 60, 120, 180, 300, 360];
if (!in_array($extend_mins, $allowed, true)) {
    extFail('Invalid extension duration.');
}

// Mock mode
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    require_once 'audit_log.php';
    auditLog('extend_user', "Extended user $username by {$extend_mins} min (mock)");
    echo json_encode(['success' => true, 'message' => "Extended $username by {$extend_mins} minutes (mock)"]);
    exit;
}

// Connect to router
require_once 'routeros_api.php';
$conn = createRouterConnection($host, $user, $pass);
if (!$conn['success']) {
    extFail('Router connection failed: ' . $conn['error']);
}

$util = $conn['util'];
$client = $conn['client'];

try {
    // Find user in /ip/hotspot/user
    $util->setMenu('/ip/hotspot/user');
    $users = $util->find('name', $username);
    
    if (empty($users)) {
        extFail("User '$username' not found on router.");
    }
    
    $userId = $users[0]->getProperty('.id');
    $currentLimit = $users[0]->getProperty('limit-uptime') ?? '';
    
    // Calculate new limit
    $currentSecs = parseUptime($currentLimit);
    $extensionSecs = $extend_mins * 60;
    $newSecs = $currentSecs + $extensionSecs;
    $newLimit = secondsToRos($newSecs);
    
    // Update router
    $query = new \RouterOS\Query('/ip/hotspot/user/set');
    $query->equal('.id', $userId);
    $query->equal('limit-uptime', $newLimit);
    $client->query($query);
    
    // Log
    require_once 'audit_log.php';
    auditLog('extend_user', "Extended user '$username' by {$extend_mins} min → {$newLimit}");
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => "Extended $username by {$extend_mins} minutes. New limit: $newLimit"
    ]);
    
} catch (Exception $e) {
    error_log('[ajax_extend_user] Error: ' . $e->getMessage());
    extFail('Failed to extend time: ' . $e->getMessage());
}
?>
