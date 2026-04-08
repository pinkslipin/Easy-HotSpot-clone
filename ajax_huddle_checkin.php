<?php
/**
 * AJAX: Check in a Huddle Room booking and finalize occupancy.
 * 
 * POST params:
 *   booking_id: int
 *   final_occupancy: int (can be different from booked_occupancy)
 *   csrf_token: string
 * 
 * Returns JSON:
 *   { success: bool, new_total_price: float, message: string }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';

function hcFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$final_occupancy = intval($_POST['final_occupancy'] ?? 0);
$staff_id = (int)$_SESSION['id'];

if ($booking_id <= 0) hcFail('Invalid booking ID.');
if ($final_occupancy <= 0) hcFail('Invalid occupancy.');

// ── Fetch booking ──────────────────────────────────────────────
$bookStmt = $DB_con->prepare("SELECT * FROM huddle_room_bookings WHERE id = ?");
$bookStmt->execute([$booking_id]);
$booking = $bookStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) hcFail('Booking not found.');
if ($booking['status'] !== 'confirmed') hcFail('Only confirmed bookings can be checked in.');

// ── Recalculate price based on final occupancy ──────────────────
// Get the base tier (always 5, 8, or 10)
$baseTiers = [5, 8, 10];
$base_occupancy = null;
foreach ($baseTiers as $tier) {
    if ($final_occupancy <= $tier) {
        $base_occupancy = $tier;
        break;
    }
}
if (!$base_occupancy) {
    $base_occupancy = 10; // Use highest tier if exceeds 10
}

// Get pricing for base occupancy
$priceStmt = $DB_con->prepare("SELECT hourly_rate FROM huddle_room_pricing WHERE occupancy_level = ? AND active = 1");
$priceStmt->execute([$base_occupancy]);
$pricingRow = $priceStmt->fetch(PDO::FETCH_ASSOC);

if (!$pricingRow) hcFail('Pricing not configured for occupancy: ' . $base_occupancy);

$hourly_rate = (float)$pricingRow['hourly_rate'];
$base_price = $hourly_rate * $booking['duration_hours'];

// Calculate extra price for occupants beyond the base tier
$extra_price = 0;
if ($final_occupancy > $base_occupancy) {
    $extra_pax = $final_occupancy - $base_occupancy;
    $extra_price = $extra_pax * 58; // 58 PHP per extra person
}

$new_total = $base_price + $extra_price;

// ── Update booking ─────────────────────────────────────────────
try {
    $updateStmt = $DB_con->prepare("
        UPDATE huddle_room_bookings
        SET final_occupancy = ?,
            base_price = ?,
            extra_price = ?,
            total_price = ?,
            status = 'checked_in',
            checked_in_by = ?,
            checked_in_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([
        $final_occupancy, $base_price, $extra_price, $new_total,
        $staff_id, $booking_id
    ]);
    
    auditLog('huddle_room_checkin', "Booking {$booking['booking_ref']}: {$booking['booked_occupancy']} → {$final_occupancy} pax. Price: ₱" . number_format($booking['total_price'], 2) . ' → ₱' . number_format($new_total, 2));
    
    echo json_encode([
        'success' => true,
        'new_total_price' => $new_total,
        'price_difference' => $new_total - $booking['total_price'],
        'message' => 'Check-in successful! Final occupancy: ' . $final_occupancy . ' pax'
    ]);
    
} catch (Exception $e) {
    hcFail('Database error: ' . $e->getMessage());
}
?>
