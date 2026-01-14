<?php
/**
 * Voucher Expiry Check
 * 
 * Include this file to automatically check and mark expired vouchers.
 * Call checkExpiredVouchers() to update voucher statuses.
 */

require_once 'dbconfig.php';

/**
 * Check and mark expired vouchers as 'Expired'
 * Returns the number of vouchers that were marked as expired
 */
function checkExpiredVouchers() {
    global $DB_con;
    
    try {
        // Mark vouchers as expired where expires_on has passed and status is still Active
        $stmt = $DB_con->prepare("UPDATE hotspot_vouchers 
            SET status = 'Expired' 
            WHERE status = 'Active' 
            AND expires_on IS NOT NULL 
            AND expires_on < NOW()");
        $stmt->execute();
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get count of expired vouchers
 */
function getExpiredVoucherCount() {
    global $DB_con;
    
    try {
        $stmt = $DB_con->prepare("SELECT COUNT(*) as count FROM hotspot_vouchers WHERE status = 'Expired'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get count of vouchers expiring soon (within X days)
 */
function getExpiringSoonCount($days = 7) {
    global $DB_con;
    
    try {
        $stmt = $DB_con->prepare("SELECT COUNT(*) as count FROM hotspot_vouchers 
            WHERE status = 'Active' 
            AND expires_on IS NOT NULL 
            AND expires_on BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)");
        $stmt->execute(array(':days' => $days));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get voucher statistics
 */
function getVoucherStats() {
    global $DB_con;
    
    // First, check for expired vouchers
    checkExpiredVouchers();
    
    try {
        $stats = array(
            'active' => 0,
            'expired' => 0,
            'used' => 0,
            'expiring_soon' => 0,
            'total_revenue' => 0
        );
        
        // Count by status
        $stmt = $DB_con->prepare("SELECT status, COUNT(*) as count, SUM(price) as revenue 
            FROM hotspot_vouchers 
            GROUP BY status");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = strtolower($row['status']);
            if ($status === 'active') {
                $stats['active'] = $row['count'];
            } elseif ($status === 'expired') {
                $stats['expired'] = $row['count'];
            } elseif ($status === 'over' || $status === 'used') {
                $stats['used'] = $row['count'];
            }
            $stats['total_revenue'] += $row['revenue'];
        }
        
        // Count expiring soon
        $stats['expiring_soon'] = getExpiringSoonCount(7);
        
        return $stats;
    } catch (Exception $e) {
        return array('active' => 0, 'expired' => 0, 'used' => 0, 'expiring_soon' => 0, 'total_revenue' => 0);
    }
}
?>
