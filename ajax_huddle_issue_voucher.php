<?php
/**
 * AJAX: Issue a voucher for a checked-in Huddle Room booking
 * 
 * POST params:
 *   booking_id: int
 *   voucher_username: string
 *   voucher_password: string
 *   package_id: string
 *   bandwidth_profile: string
 *   data_limit_gb: int (0 = unlimited)
 *   csrf_token: string
 * 
 * Returns JSON:
 *   { success: bool, voucher_user: string, message: string }
 */
header('Content-Type: application/json');
require_once 'security_helper.php';
secure_session_start();
require_auth();
csrf_require();
require_once 'dbconfig.php';
require_once 'packages_config.php';
require_once 'config.php';
require_once 'audit_log.php';

function hjvFail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$username = trim($_POST['voucher_username'] ?? '');
$password = trim($_POST['voucher_password'] ?? '');
$package_id = trim($_POST['package_id'] ?? '');
$profile = trim($_POST['bandwidth_profile'] ?? 'default');
$data_limit_gb = intval($_POST['data_limit_gb'] ?? 0);
$staff_id = (int)$_SESSION['id'];
$staff_username = $_SESSION['username'] ?? 'system';

if ($booking_id <= 0) hjvFail('Invalid booking ID.');
if ($username === '') hjvFail('Username is required.');
if ($password === '') hjvFail('Password is required.');
if (empty($package_id)) hjvFail('Please select a package.');

// ── Fetch booking ────────────────────────────────────────────────────────────
$bookingStmt = $DB_con->prepare("SELECT * FROM huddle_room_bookings WHERE id = ?");
$bookingStmt->execute([$booking_id]);
$booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) hjvFail('Booking not found.');
if (!in_array($booking['status'], ['confirmed', 'checked_in'], true)) {
    hjvFail('Booking must be confirmed before issuing voucher.');
}

// ── Fetch package info ───────────────────────────────────────────────────────
$pkg_info = getPackage($package_id);
if (!$pkg_info) hjvFail('Invalid package selected.');

$price = $pkg_info['price'];
$limit_uptime = getPackageLimitUptime($package_id);
$limit_bytes = $data_limit_gb > 0 ? ($data_limit_gb * 1024 * 1024 * 1024) : 0;

// ── Create router user on MikroTik ──────────────────────────────────────────
try {
    require_once 'routeros_api.php';
    
    $routerConn = createRouterConnection($host, $user, $pass);
    
    if (!$routerConn['success']) hjvFail('Failed to connect to router: ' . $routerConn['error']);
    
    $routerUtil = $routerConn['util'];

    // Prevent duplicate active usernames
    $routerUtil->setMenu('/ip/hotspot/active');
    $liveSession = $routerUtil->find('user', $username);
    if (!empty($liveSession)) {
        hjvFail('Username "' . htmlspecialchars($username) . '" is currently logged in. Use a different username.');
    }

    // Remove stale hotspot user entry if it exists but is not active
    $routerUtil->setMenu('/ip/hotspot/user');
    $staleEntry = $routerUtil->find('name', $username);
    if (!empty($staleEntry)) {
        try { $routerUtil->removeUser($username); } catch (Exception $e) {}
    }
    
    // Create hotspot user on router
    $routerUtil->setMenu('/ip hotspot user');
    $newUserParams = [
        'name'       => $username,
        'password'   => $password,
        'profile'    => $profile,
    ];
    
    // Apply package uptime for duration packages
    if ($limit_uptime !== '') {
        $newUserParams['limit-uptime'] = $limit_uptime;
    }

    // Add data limits if specified
    if ($limit_bytes > 0) {
        $newUserParams['limit-bytes-total'] = strval($limit_bytes);
    }
    
    $routerUtil->add($newUserParams);
    
} catch (Exception $e) {
    hjvFail('Router error: ' . $e->getMessage());
}

// ── Create voucher in database (schema-aligned with existing voucher flow) ─
try {
    $package_name = getPackageDisplayName($package_id);
    $package_type = $pkg_info['type'] ?? 'duration';
    $expires_on = getExpiryDate();

    // Use the actual huddle booking ID (critical: must link correctly to huddle_room_bookings)
    $voucher_booking_id = $booking_id;

    $uid = $voucher_booking_id . '-1-' . date('dmY');
    $batch_id = strtoupper($package_id) . '-HUD-' . date('mdHi');

    $voucherStmt = $DB_con->prepare("
        INSERT INTO hotspot_vouchers (
            created_on, created_by, creator, user_name, password,
            printed_times, printed_last, status, group_of, booking_id,
            limit_uptime, limit_bytes, profile, uid, batch_id,
            price, expires_on, package_id, package_name, package_type,
            assigned_router
        ) VALUES (
            NOW(), :created_by, :creator, :user_name, :password,
            0, '', 'Active', 1, :booking_id,
            :limit_uptime, :limit_bytes, :profile, :uid, :batch_id,
            :price, :expires_on, :package_id, :package_name, :package_type,
            :assigned_router
        )
    ");

    $voucherStmt->execute([
        ':created_by' => $staff_username,
        ':creator' => $staff_id,
        ':user_name' => $username,
        ':password' => $password,
        ':booking_id' => $voucher_booking_id,
        ':limit_uptime' => $limit_uptime,
        ':limit_bytes' => $data_limit_gb,
        ':profile' => $profile,
        ':uid' => $uid,
        ':batch_id' => $batch_id,
        ':price' => $price,
        ':expires_on' => $expires_on,
        ':package_id' => $package_id,
        ':package_name' => $package_name,
        ':package_type' => $package_type,
        ':assigned_router' => 'converge'
    ]);
    
} catch (PDOException $e) {
    hjvFail('Database error: ' . $e->getMessage());
}

// ── Audit log ───────────────────────────────────────────────────────────────
$log_msg = "Voucher issued for huddle booking {$booking['booking_ref']}: {$username} / Package: {$pkg_info['name']}";
auditLog('huddle_voucher_issued', $log_msg, $staff_username);

echo json_encode([
    'success' => true,
    'voucher_user' => $username,
    'message' => "✓ Voucher created: {$username}"
]);
