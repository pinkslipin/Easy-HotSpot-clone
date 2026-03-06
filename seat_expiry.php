<?php
/**
 * Seat Expiry Cleanup
 * -------------------
 * Resets any 'reserved' seat whose 2-hour window has elapsed back to 'available'.
 *
 * Usage (in PHP pages):
 *   require_once 'seat_expiry.php';
 *   $released = expireSeats($DB_con);   // returns int count of seats released
 *
 * Usage (standalone CLI / cron):
 *   php /path/to/seat_expiry.php
 *   Cron example (every 5 min): * /5 * * * * php /var/www/html/Easy-HotSpot-clone/seat_expiry.php
 */

/**
 * Release all reserved seats whose expires_at has passed.
 *
 * @param PDO $db  Active database connection
 * @return int     Number of seats released
 */
function expireSeats(PDO $db): int {
    try {
        $stmt = $db->prepare("
            UPDATE seats
            SET    status            = 'available',
                   reserved_by       = NULL,
                   voucher_username  = NULL,
                   reserved_at       = NULL,
                   expires_at        = NULL
            WHERE  status      = 'reserved'
              AND  expires_at  IS NOT NULL
              AND  expires_at  < NOW()
        ");
        $stmt->execute();
        return (int) $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[seat_expiry] expireSeats error: ' . $e->getMessage());
        return 0;
    }
}

// ── Standalone CLI / cron entry-point ────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    $dir = __DIR__;
    require_once $dir . '/dbconfig.php';
    $released = expireSeats($DB_con);
    echo '[' . date('Y-m-d H:i:s') . "] Expired {$released} reserved seat(s).\n";
}
