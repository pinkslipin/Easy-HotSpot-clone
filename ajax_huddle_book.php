<?php
/**
 * AJAX: Create a new Huddle Room booking.
 * 
 * POST params:
 *   visitor_name: string
 *   visitor_email: string
 *   visitor_phone: string
 *   company_name: string (optional)
 *   booking_date: YYYY-MM-DD
 *   start_time: HH:MM
 *   duration_hours: number (1-24 hours)
 *   occupancy: 5 | 8 | 10+ (base occupancy levels)
 *   csrf_token: string
 * 
 * Returns JSON:
 *   { success: bool, booking_ref: string, total_price: float, message: string }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
// Allow public bookings without authentication, but still require CSRF
secure_session_start();
csrf_require();
require_once 'dbconfig.php';
require_once 'audit_log.php';
require_once 'email_helper.php';

function hjFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
//test variables
// ── Input validation ───────────────────────────────────────────
$visitor_name = trim($_POST['visitor_name'] ?? '');
$visitor_email = trim($_POST['visitor_email'] ?? '');
$visitor_phone = trim($_POST['visitor_phone'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$booking_date = trim($_POST['booking_date'] ?? '');
$start_time = trim($_POST['start_time'] ?? '');
$duration_hours = floatval($_POST['duration_hours'] ?? 0);
$occupancy = intval($_POST['occupancy'] ?? 0);

if (!$visitor_name) hjFail('Visitor name is required.');
if (!$visitor_email) hjFail('Email is required.');
if (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) hjFail('Invalid email format.');
if (!$booking_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) hjFail('Invalid booking date.');
if (!$start_time || !preg_match('/^\d{2}:\d{2}$/', $start_time)) hjFail('Invalid start time.');
if ($duration_hours < 1 || $duration_hours > 24) hjFail('Duration must be between 1 and 24 hours.');
if (!in_array($occupancy, [5, 8, 10])) hjFail('Invalid occupancy.');

// ── Check if booking date is in the future or today ────────────
$bookDate = new DateTime($booking_date);
$today = new DateTime();
$today->setTime(0, 0, 0);
if ($bookDate < $today) {
    hjFail('Cannot book dates in the past.');
}

// ── Calculate end time ─────────────────────────────────────────
$startDT = DateTime::createFromFormat('Y-m-d H:i', "$booking_date $start_time");
$endDT = clone $startDT;
$endDT->modify("+{$duration_hours} hours");
$end_time = $endDT->format('H:i:s');
$booking_date_fmt = $bookDate->format('Y-m-d');

// ── Check for conflicts (overlapping bookings) ──────────────────
$conflictStmt = $DB_con->prepare("
    SELECT COUNT(*) as count FROM huddle_room_bookings
    WHERE booking_date = ?
      AND status IN ('confirmed', 'checked_in')
      AND (
        (TIME(start_time) < ? AND TIME(end_time) > ?)
        OR (TIME(start_time) >= ? AND TIME(start_time) < ?)
      )
");
$conflictStmt->execute([
    $booking_date_fmt,
    $end_time,
    $start_time,
    $start_time,
    $end_time
]);
$conflict_count = $conflictStmt->fetch(PDO::FETCH_ASSOC)['count'];
if ($conflict_count > 0) {
    hjFail('Time slot is already booked. Please select another time.');
}

// ── Fetch pricing for the occupancy level ────────────────────
$priceStmt = $DB_con->prepare("SELECT hourly_rate FROM huddle_room_pricing WHERE occupancy_level = ? AND active = 1");
$priceStmt->execute([$occupancy]);
$pricing = $priceStmt->fetch(PDO::FETCH_ASSOC);
if (!$pricing) {
    hjFail("Invalid occupancy level: $occupancy");
}

$hourly_rate = (float)$pricing['hourly_rate'];
$base_price = $hourly_rate * $duration_hours;
$extra_price = 0;
$total_price = $base_price + $extra_price;

// ── Generate booking reference ─────────────────────────────────
$booking_ref = 'HUB-' . date('dmY') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

// ── Insert booking into database ───────────────────────────────
try {
    $DB_con->beginTransaction();
    
    $insertStmt = $DB_con->prepare("
        INSERT INTO huddle_room_bookings
            (booking_ref, booking_date, start_time, end_time, duration_hours,
             visitor_name, visitor_email, visitor_phone, company_name,
             booked_occupancy, base_price, extra_price, total_price, status, created_at)
        VALUES
            (?, ?, ?, ?, ?,
             ?, ?, ?, ?,
             ?, ?, ?, ?, 'confirmed', NOW())
    ");
    
    $insertStmt->execute([
        $booking_ref, $booking_date_fmt, $start_time . ':00', $end_time,
        $duration_hours, $visitor_name, $visitor_email, $visitor_phone,
        $company_name, $occupancy, $base_price, $extra_price, $total_price
    ]);
    
    $DB_con->commit();
    
    // Log the event
    auditLog('huddle_room_booking', "New booking: $booking_ref for $visitor_name on $booking_date at $start_time ({$occupancy} pax, ₱" . number_format($total_price, 2) . ')');
    
    // Send confirmation email
    $booking_details = [
        'booking_ref' => $booking_ref,
        'visitor_name' => $visitor_name,
        'booking_date' => $booking_date_fmt,
        'start_time' => $start_time . ':00',
        'end_time' => $end_time,
        'duration_hours' => $duration_hours,
        'booked_occupancy' => $occupancy,
        'total_price' => $total_price
    ];
    
    $email_sent = sendBookingConfirmationEmail($visitor_email, $booking_details);
    
    // Return success (email failure is non-critical and should not fail the booking)
    echo json_encode([
        'success' => true,
        'booking_ref' => $booking_ref,
        'total_price' => $total_price,
        'email_sent' => $email_sent,
        'message' => "Booking confirmed! Ref: $booking_ref" . ($email_sent ? " — Confirmation email sent to $visitor_email" : " — Email notification pending")
    ]);
    
} catch (Exception $e) {
    $DB_con->rollBack();
    hjFail('Database error: ' . $e->getMessage());
}
?>
