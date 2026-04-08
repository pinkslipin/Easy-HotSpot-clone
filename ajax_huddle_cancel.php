<?php
/**
 * AJAX: Cancel a Huddle Room booking.
 * 
 * POST params:
 *   booking_id: int
 *   cancellation_reason: string (optional)
 *   csrf_token: string
 * 
 * Returns JSON:
 *   { success: bool, message: string }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';

function hcanFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$reason = trim($_POST['cancellation_reason'] ?? 'User cancelled');
$staff_id = (int)$_SESSION['id'];

if ($booking_id <= 0) hcanFail('Invalid booking ID.');

// ── Fetch booking ──────────────────────────────────────────────
$bookStmt = $DB_con->prepare("SELECT * FROM huddle_room_bookings WHERE id = ?");
$bookStmt->execute([$booking_id]);
$booking = $bookStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) hcanFail('Booking not found.');

// Can't cancel completed or already cancelled bookings
if (in_array($booking['status'], ['completed', 'cancelled'])) {
    hcanFail('Cannot cancel a ' . $booking['status'] . ' booking.');
}

// ── Cancel booking ─────────────────────────────────────────────
try {
    $updateStmt = $DB_con->prepare("
        UPDATE huddle_room_bookings
        SET status = 'cancelled',
            cancelled_by = ?,
            cancellation_reason = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$staff_id, $reason, $booking_id]);
    
    auditLog('huddle_room_cancel', "Booking {$booking['booking_ref']} cancelled by staff. Reason: $reason");
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully.'
    ]);
    
} catch (Exception $e) {
    hcanFail('Database error: ' . $e->getMessage());
}
?>
