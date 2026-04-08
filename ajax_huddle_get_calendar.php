<?php
/**
 * AJAX: Get Huddle Room availability calendar for a given month.
 * 
 * GET params:
 *   year: int (e.g., 2026)
 *   month: int (1-12)
 * 
 * Returns JSON:
 *   {
 *     success: bool,
 *     days: [
 *       { date: "2026-04-08", available_slots: 3, booked: 1 },
 *       ...
 *     ],
 *     hourly_rates: { 5: 260, 8: 450, 10: 600 }
 *   }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_once 'dbconfig.php';

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

// Validate month/year
if ($month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

// Get the first and last day of the month
$first_day = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$last_day = date('Y-m-t', strtotime($first_day));

// ── Fetch pricing tiers ────────────────────────────────────────
$pricingStmt = $DB_con->query("SELECT occupancy_level, hourly_rate FROM huddle_room_pricing WHERE active = 1 ORDER BY occupancy_level");
$hourly_rates = [];
while ($row = $pricingStmt->fetch(PDO::FETCH_ASSOC)) {
    $hourly_rates[$row['occupancy_level']] = (float)$row['hourly_rate'];
}

// ── Calculate available time slots for each day ─────────────────
$days_data = [];
$current_date = strtotime($first_day);
$end_date = strtotime($last_day);

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $day_of_week = date('w', $current_date);
    
    // Check if room is open this day
    $availStmt = $DB_con->prepare("SELECT is_open, opens_at, closes_at FROM huddle_room_availability WHERE day_of_week = ?");
    $availStmt->execute([$day_of_week]);
    $avail = $availStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($avail && $avail['is_open']) {
        // Count booked slots for this day (each booking is 1 slot - could book multiple times same day?)
        $bookedStmt = $DB_con->prepare("
            SELECT COUNT(*) as count FROM huddle_room_bookings
            WHERE booking_date = ? AND status IN ('confirmed', 'checked_in', 'completed')
        ");
        $bookedStmt->execute([$date_str]);
        $booked_count = $bookedStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // For simplicity: allow max 2 bookings per day (morning + afternoon, or 2 full days)
        $available_slots = max(0, 2 - intval($booked_count));
        
        $days_data[] = [
            'date' => $date_str,
            'available_slots' => $available_slots,
            'booked' => intval($booked_count),
            'is_open' => true
        ];
    } else {
        $days_data[] = [
            'date' => $date_str,
            'available_slots' => 0,
            'booked' => 0,
            'is_open' => false
        ];
    }
    
    $current_date = strtotime('+1 day', $current_date);
}

echo json_encode([
    'success' => true,
    'year' => $year,
    'month' => $month,
    'days' => $days_data,
    'hourly_rates' => $hourly_rates
]);
?>
