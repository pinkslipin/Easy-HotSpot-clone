<?php
/**
 * AJAX: Delete selected vouchers by user_name.
 * Removes them from DB (dashboard stats update automatically)
 * and cleans up the router (kicks active sessions + removes user accounts).
 *
 * POST params:
 *   csrf_token  — CSRF token
 *   usernames[] — array of user_name values to delete
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'config.php';
require_once 'audit_log.php';

// ── Input validation ──────────────────────────────────────────────────────────
$raw = isset($_POST['usernames']) ? $_POST['usernames'] : [];
if (!is_array($raw) || empty($raw)) {
    echo json_encode(['success' => false, 'message' => 'No vouchers selected.']);
    exit;
}

// Sanitise: keep only non-empty strings, max 200 at a time
$usernames = array_values(array_filter(array_map('strval', $raw)));
if (count($usernames) > 200) {
    echo json_encode(['success' => false, 'message' => 'Too many at once (max 200).']);
    exit;
}

// ── Router cleanup (best-effort, non-fatal) ───────────────────────────────────
$routerOk = false;
if (!defined('MOCK_MODE') || MOCK_MODE !== true) {
    require_once 'routeros_api.php';
    $conn = createRouterConnection($host, $user, $pass);
    if ($conn['success']) {
        $util = $conn['util'];
        // Kick any active sessions first
        $util->setMenu('/ip/hotspot/active');
        foreach ($usernames as $uname) {
            try {
                $sessions = $util->find('user', $uname);
                foreach ($sessions as $s) {
                    $util->remove($s->getProperty('.id'));
                }
            } catch (Exception $e) { /* non-fatal */ }
        }
        // Remove user accounts
        $util->setMenu('/ip/hotspot/user');
        foreach ($usernames as $uname) {
            try { $util->removeUser($uname); } catch (Exception $e) { /* non-fatal */ }
        }
        $routerOk = true;
    }
}

// ── DB deletion ───────────────────────────────────────────────────────────────
try {
    // Build parameterised IN clause
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    $stmt = $DB_con->prepare(
        "DELETE FROM hotspot_vouchers WHERE user_name IN ($placeholders)"
    );
    $stmt->execute($usernames);
    $deleted = $stmt->rowCount();

    auditLog(
        'voucher_delete',
        "Deleted $deleted voucher(s): " . implode(', ', array_slice($usernames, 0, 20))
        . (count($usernames) > 20 ? ' …and more' : '')
        . '. Router cleaned: ' . ($routerOk ? 'yes' : 'no/offline')
    );

    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'message' => "$deleted voucher(s) deleted successfully.",
    ]);
} catch (PDOException $e) {
    error_log('[ajax_delete_vouchers] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
