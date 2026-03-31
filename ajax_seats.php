<?php
/**
 * AJAX: Fetch all seats grouped by zone — called on page load and every 30 s.
 *
 * Returns JSON:
 * {
 *   success: true,
 *   seats:   { "Main Entrance": [...], "Inner Room": [...], ... },
 *   summary: { total, available, reserved, occupied },
 *   ts:      "HH:MM:SS"   ← last-refreshed timestamp
 * }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'seat_expiry.php';

// Always run expiry cleanup so stale reservations never show as reserved
expireSeats($DB_con);
$expiryEvents = [];
expireConsumedOccupiedSeats($DB_con, $expiryEvents);

try {
    // Pull seat data with latest voucher info for occupied/reserved seats.
    // We join on the most-recent Active (or any) voucher for the stored
    // voucher_username so we can show package + price on the card.
    $stmt = $DB_con->query("
        SELECT   s.id, s.seat_number, s.zone, s.zone_code, s.status,
                 s.reserved_by, s.voucher_username,
                 s.reserved_at, s.expires_at,
                 v.package_name, v.price    AS voucher_price,
                 v.limit_uptime             AS voucher_uptime,
                 v.package_id,
                 hu.username               AS staff_name
        FROM     seats s
        LEFT JOIN hotspot_vouchers v
                  ON v.user_name = s.voucher_username
                 AND v.status    = 'Active'
        LEFT JOIN hotspot_users hu
                  ON hu.user_id = s.reserved_by
        ORDER BY FIELD(s.zone_code, 'ME', 'IR', 'CR', 'PR'), s.seat_number
    ");
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by zone for the client renderer
    $grouped   = [];
    $available = 0;
    $reserved  = 0;
    $occupied  = 0;

    foreach ($seats as $seat) {
        // Format expiry countdown if reserved
        $seat['expires_in'] = null;
        if ($seat['status'] === 'reserved' && $seat['expires_at']) {
            $diff = strtotime($seat['expires_at']) - time();
            if ($diff > 0) {
                $h = intdiv($diff, 3600);
                $m = intdiv($diff % 3600, 60);
                $seat['expires_in'] = ($h > 0 ? "{$h}h " : '') . "{$m}m";
            } else {
                $seat['expires_in'] = 'Expiring…';
            }
        }

        // Humanised "seated since" for occupied seats
        $seat['occupied_since'] = null;
        if ($seat['status'] === 'occupied' && $seat['reserved_at']) {
            $diff = time() - strtotime($seat['reserved_at']);
            if ($diff < 3600) {
                $seat['occupied_since'] = intdiv($diff, 60) . 'm ago';
            } elseif ($diff < 86400) {
                $h = intdiv($diff, 3600);
                $m = intdiv($diff % 3600, 60);
                $seat['occupied_since'] = "{$h}h {$m}m ago";
            } else {
                $seat['occupied_since'] = date('M j, g:ia', strtotime($seat['reserved_at']));
            }
        }

        // Null-safe voucher fields (avoid JS undefined errors)
        $seat['package_name']   = $seat['package_name']   ?? null;
        $seat['voucher_price']  = $seat['voucher_price']  !== null ? (float)$seat['voucher_price'] : null;
        $seat['voucher_uptime'] = $seat['voucher_uptime'] ?? null;
        $seat['package_id']     = $seat['package_id']     ?? null;
        $seat['staff_name']     = $seat['staff_name']     ?? null;

        $grouped[$seat['zone']][] = $seat;

        match ($seat['status']) {
            'available' => $available++,
            'reserved'  => $reserved++,
            'occupied'  => $occupied++,
            default     => null,
        };
    }

    echo json_encode([
        'success' => true,
        'seats'   => $grouped,
        'summary' => [
            'total'     => count($seats),
            'available' => $available,
            'reserved'  => $reserved,
            'occupied'  => $occupied,
        ],
        'notifications' => $expiryEvents,
        'ts' => date('H:i:s'),
    ]);

} catch (PDOException $e) {
    error_log('[ajax_seats] error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch seats']);
}
