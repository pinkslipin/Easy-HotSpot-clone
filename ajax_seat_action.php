<?php
/**
 * AJAX: Handle seat status transitions.
 *
 * POST params:
 *   action   : 'reserve' | 'occupy' | 'release'
 *   seat_id  : int
 *   csrf_token: string
 *
 * Returns JSON:
 *   { success: bool, message: string, new_status: string }
 *
 * Race condition prevention: row is locked with SELECT … FOR UPDATE inside a
 * transaction, so two simultaneous clicks on the same seat cannot both succeed.
 *
 * NOTE: auditLog() is intentionally called AFTER commit() because
 * createAuditLogTable() runs a DDL statement (CREATE TABLE IF NOT EXISTS)
 * which would implicitly commit any open MySQL transaction.
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';
require_once 'config.php';
require_once 'routeros_api.php';

// ── Input validation ─────────────────────────────────────────────────────────
$allowed_actions = ['reserve', 'occupy', 'release'];
$action  = isset($_POST['action'])  ? trim($_POST['action'])  : '';
$seat_id = isset($_POST['seat_id']) ? (int) $_POST['seat_id'] : 0;

if (!in_array($action, $allowed_actions, true) || $seat_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit;
}

$staff_id = (int) $_SESSION['id'];

// ── Transaction + row lock ────────────────────────────────────────────────────
try {
    $DB_con->beginTransaction();

    // Lock the row so concurrent requests queue up rather than double-booking
    $stmt = $DB_con->prepare("
        SELECT id, seat_number, zone, status, voucher_username
        FROM   seats
        WHERE  id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $seat_id]);
    $seat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seat) {
        $DB_con->rollBack();
        echo json_encode(['success' => false, 'message' => 'Seat not found.']);
        exit;
    }

    $current          = $seat['status'];
    $seat_label       = htmlspecialchars($seat['seat_number']);
    $voucher_username = $seat['voucher_username'];
    $new_status       = null;
    $msg              = '';
    $audit_action     = '';
    $audit_detail     = '';

    switch ($action) {

        // ── Reserve (available → reserved) ───────────────────────────────────
        case 'reserve':
            if ($current !== 'available') {
                respondConflict($DB_con, $seat_label, $current);
            }
            $stmt = $DB_con->prepare("
                UPDATE seats
                SET    status      = 'reserved',
                       reserved_by = :uid,
                       reserved_at = NOW(),
                       expires_at  = DATE_ADD(NOW(), INTERVAL 2 HOUR)
                WHERE  id = :id
            ");
            $stmt->execute([':uid' => $staff_id, ':id' => $seat_id]);
            $new_status   = 'reserved';
            $msg          = "Seat {$seat_label} reserved — expires in 2 hours.";
            $audit_action = 'seat_reserve';
            $audit_detail = "Reserved {$seat_label} ({$seat['zone']}) by staff #{$staff_id}";
            break;

        // ── Occupy (reserved → occupied) ─────────────────────────────────────
        case 'occupy':
            if ($current !== 'reserved') {
                respondConflict($DB_con, $seat_label, $current,
                    "Seat {$seat_label} must be Reserved before it can be marked Occupied.");
            }
            $stmt = $DB_con->prepare("
                UPDATE seats
                SET    status = 'occupied'
                WHERE  id = :id
            ");
            $stmt->execute([':id' => $seat_id]);
            $new_status   = 'occupied';
            $msg          = "Seat {$seat_label} marked as Occupied.";
            $audit_action = 'seat_occupy';
            $audit_detail = "Marked {$seat_label} ({$seat['zone']}) as occupied by staff #{$staff_id}";
            break;

        // ── Release (reserved|occupied → available) ───────────────────────────
        case 'release':
            if ($current === 'available') {
                $DB_con->rollBack();
                echo json_encode([
                    'success'    => false,
                    'message'    => "Seat {$seat_label} is already available.",
                    'new_status' => 'available',
                ]);
                exit;
            }
            $stmt = $DB_con->prepare("
                UPDATE seats
                SET    status            = 'available',
                       reserved_by       = NULL,
                       voucher_username  = NULL,
                       reserved_at       = NULL,
                       expires_at        = NULL
                WHERE  id = :id
            ");
            $stmt->execute([':id' => $seat_id]);

            // Mark the voucher as Used in the DB (while still in transaction)
            if ($voucher_username) {
                $stmt = $DB_con->prepare("
                    UPDATE hotspot_vouchers
                    SET    status = 'Used'
                    WHERE  user_name = :uname
                      AND  status = 'Active'
                ");
                $stmt->execute([':uname' => $voucher_username]);
            }

            $new_status   = 'available';
            $msg          = "Seat {$seat_label} released and now available.";
            $audit_action = 'seat_release';
            $audit_detail = "Released {$seat_label} ({$seat['zone']}) — was {$current}"
                          . ($voucher_username ? ", user: {$voucher_username}" : '')
                          . ", by staff #{$staff_id}";
            break;
    }

    // Commit BEFORE calling auditLog() — DDL inside auditLog would otherwise
    // implicitly commit the transaction and leave PDO with no active transaction.
    $DB_con->commit();

    // ── Post-commit: audit log ────────────────────────────────────────────────
    if ($audit_action) {
        auditLog($audit_action, $audit_detail);
    }

    // ── Post-commit: remove router user on release ────────────────────────────
    if ($action === 'release' && $voucher_username) {
        if (!(defined('MOCK_MODE') && MOCK_MODE === true)) {
            $conn = createRouterConnection($host, $user, $pass);
            if ($conn['success']) {
                $util = $conn['util'];

                // Kick any active sessions first so internet drops immediately
                $util->setMenu('/ip/hotspot/active');
                $activeSessions = $util->find('user', $voucher_username);
                foreach ($activeSessions as $session) {
                    try { $util->remove($session->getProperty('.id')); } catch (Exception $e) {}
                }

                // Remove the hotspot user account
                $util->setMenu('/ip/hotspot/user');
                try { $util->removeUser($voucher_username); } catch (Exception $e) {}
            }
        }
    }

    echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $new_status]);

} catch (PDOException $e) {
    if ($DB_con->inTransaction()) {
        $DB_con->rollBack();
    }
    error_log('[ajax_seat_action] PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}

// ── Helper ────────────────────────────────────────────────────────────────────
/**
 * Roll back, emit a conflict JSON response, and halt execution.
 * @param PDO    $db
 * @param string $label   Human-readable seat id (already escaped)
 * @param string $status  Current seat status
 * @param string $msg     Optional custom message
 */
function respondConflict(PDO $db, string $label, string $status, string $msg = ''): never {
    $db->rollBack();
    if (!$msg) {
        $msg = "Seat {$label} is currently {$status} — another staff may have just acted on it.";
    }
    echo json_encode(['success' => false, 'message' => $msg, 'new_status' => $status]);
    exit;
}
