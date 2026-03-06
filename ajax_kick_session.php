<?php
/**
 * ajax_kick_session.php
 *
 * Kick a device off the network WITHOUT deleting the user account.
 *
 * What this does:
 *   1. Removes all active sessions for the user  → device loses internet immediately
 *      and is redirected to the captive portal.
 *   2. Removes any hotspot cookies tied to that user → MAC address binding is cleared
 *      so the device must re-authenticate (cannot auto-reconnect with cached credentials).
 *   3. Removes any hotspot host entries for that user → clears IP/MAC lease from the
 *      hotspot host table so the portal treats them as a fresh visitor.
 *   4. Does NOT delete the user from /ip/hotspot/user.
 *   5. Does NOT change the voucher status in the database.
 *
 * Result: The voucher is still valid. The correct person can open the portal and
 * log in with the same credentials.
 *
 * POST params:
 *   csrf_token — CSRF token
 *   username   — hotspot username whose session/MAC binding to clear
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
require_auth();
csrf_require();
require_once 'config.php';
require_once 'routeros_api.php';
require_once 'audit_log.php';

function ksFail(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$username = trim($_POST['username'] ?? '');
if ($username === '') ksFail('Username is required.');

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    auditLog('session_reset', "Mock: reset session for '$username'");
    echo json_encode(['success' => true, 'message' => "Session reset for $username (mock mode)."]);
    exit;
}

$conn = createRouterConnection($host, $user, $pass);
if (!$conn['success']) ksFail('Router connection failed: ' . $conn['error']);
$util = $conn['util'];

$kicked   = 0;
$cookies  = 0;
$hosts    = 0;

// ── 1. Kick active sessions ───────────────────────────────────────────────────
$util->setMenu('/ip/hotspot/active');
$sessions = $util->find('user', $username);
foreach ($sessions as $s) {
    try {
        $util->remove($s->getProperty('.id'));
        $kicked++;
    } catch (Exception $e) {}
}

// ── 2. Clear cookie (MAC) bindings ───────────────────────────────────────────
try {
    $util->setMenu('/ip/hotspot/cookie');
    $cookieList = $util->find('user', $username);
    foreach ($cookieList as $c) {
        try {
            $util->remove($c->getProperty('.id'));
            $cookies++;
        } catch (Exception $e) {}
    }
} catch (Exception $e) {
    // /ip/hotspot/cookie may not exist on all RouterOS versions — non-fatal
}

// ── 3. Clear host entries ────────────────────────────────────────────────────
try {
    $util->setMenu('/ip/hotspot/host');
    $hostList = $util->find('user', $username);
    foreach ($hostList as $h) {
        try {
            $util->remove($h->getProperty('.id'));
            $hosts++;
        } catch (Exception $e) {}
    }
} catch (Exception $e) {
    // Non-fatal
}

auditLog('session_reset',
    "Reset session for '$username': kicked $kicked session(s), cleared $cookies cookie(s), $hosts host(s). Voucher kept intact."
);

echo json_encode([
    'success'  => true,
    'message'  => "Session reset for '$username'. Device disconnected and redirected to portal. Voucher is still valid — the correct user can now log in.",
    'kicked'   => $kicked,
    'cookies'  => $cookies,
    'hosts'    => $hosts,
]);
