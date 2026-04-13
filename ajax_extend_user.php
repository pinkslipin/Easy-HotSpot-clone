<?php
// CRITICAL: Set error handling FIRST before anything else
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display to user
ini_set('log_errors', 1);       // Log to error_log

header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_unit_head();  // Allow admins (1) and unit heads (2) to extend time
csrf_require();

// Parse RouterOS uptime string to seconds
// Handles formats: "5h30m10s", "1d5h", "00:30:00"
function parseUptimeToSeconds($time) {
    if (empty($time)) return 0;
    $seconds = 0;
    // HH:MM:SS format
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $time, $m)) {
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    if (preg_match('/(\d+)w/', $time, $m)) $seconds += (int)$m[1] * 604800;
    if (preg_match('/(\d+)d/', $time, $m)) $seconds += (int)$m[1] * 86400;
    if (preg_match('/(\d+)h/', $time, $m)) $seconds += (int)$m[1] * 3600;
    if (preg_match('/(\d+)m/', $time, $m)) $seconds += (int)$m[1] * 60;
    if (preg_match('/(\d+)s/', $time, $m)) $seconds += (int)$m[1];
    return $seconds;
}

// Convert seconds back to RouterOS time format e.g. "1h30m"
function secondsToRouterOS($total) {
    $total = (int)$total;
    if ($total <= 0) return '0s';
    $d = intdiv($total, 86400); $total %= 86400;
    $h = intdiv($total, 3600);  $total %= 3600;
    $m = intdiv($total, 60);    $s = $total % 60;
    $r = '';
    if ($d) $r .= $d . 'd';
    if ($h) $r .= $h . 'h';
    if ($m) $r .= $m . 'm';
    if ($s || !$r) $r .= $s . 's';
    return $r;
}

$username     = isset($_POST['username'])       ? trim($_POST['username'])       : '';
$extend_mins  = isset($_POST['extend_minutes']) ? (int)$_POST['extend_minutes'] : 0;

if (empty($username) || $extend_mins <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Whitelist allowed extension values (minutes) to prevent abuse
$allowed = [30, 60, 120, 180, 300, 360];
if (!in_array($extend_mins, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid extension duration.']);
    exit;
}

if (defined('MOCK_MODE') && MOCK_MODE === true) {
    echo json_encode(['success' => true, 'message' => "Extended $username by {$extend_mins} minutes (mock)."]);
    exit;
}

require_once 'routeros_api.php';
require_once 'routers_config.php';

// PHASE 4: Look up which router the user is assigned to
require_once 'dbconfig.php';
try {
	$stmt = $DB_con->prepare("SELECT assigned_router, user_name, package_id FROM hotspot_vouchers WHERE user_name = :user_name AND status = 'Active' LIMIT 1");
	$stmt->execute([':user_name' => $username]);
	$voucher = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$voucher) {
		echo json_encode(['success' => false, 'message' => "Voucher for user '$username' not found or expired."]);
		exit;
	}
	
	$assigned_router = $voucher['assigned_router'] ?: 'converge';
} catch (Exception $e) {
	error_log('[ajax_extend_user] DB query error: ' . $e->getMessage());
	echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
	exit;
}

// PHASE 5: Try router connection, but have a database fallback
$routerSuccess = false;
$errorMsg = '';

try {
	$router_config = getRouterConfig($assigned_router);
	$connection = createRouterConnection($router_config['ip'], $router_config['user'], $router_config['pass'], $router_config['port']);

	if ($connection['success']) {
		$util   = $connection['util'];
		$client = $connection['client'];
		$routerSuccess = true;
	} else {
		$errorMsg = $connection['error'];
	}
} catch (Exception $e) {
	$errorMsg = $e->getMessage();
}

// ── Step 1: If router is available, update directly ──────────────────────
if ($routerSuccess) {
	try {
		$util->setMenu('/ip/hotspot/user');
		$users = $util->find('name', $username);

		if (empty($users)) {
			echo json_encode(['success' => false, 'message' => "User '$username' not found on router."]);
			exit;
		}

		$userId       = $users[0]->getProperty('.id');
		$currentLimit = $users[0]->getProperty('limit-uptime');

		// Calculate new limit-uptime
		$currentSecs   = parseUptimeToSeconds($currentLimit);
		$extensionSecs = $extend_mins * 60;
		$newSecs       = $currentSecs + $extensionSecs;
		$newLimit      = secondsToRouterOS($newSecs);

		// Update on router
		$query = new \RouterOS\Query('/ip/hotspot/user/set');
		$query->equal('.id', $userId);
		$query->equal('limit-uptime', $newLimit);
		$client->query($query);

		// Log success
		require_once 'audit_log.php';
		auditLog('extend_user', "Extended user '$username' by {$extend_mins} min on $assigned_router (direct). New limit: $newLimit");

		echo json_encode([
			'success'   => true,
			'message'   => "Extended $username by {$extend_mins} minutes. New limit: $newLimit",
			'new_limit' => $newLimit,
		]);
		exit;
	} catch (Exception $e) {
		error_log('[ajax_extend_user] Router operation failed: ' . $e->getMessage());
		// Fall through to database method below
	}
}

// ── FALLBACK: Router unavailable - store extension in database ──────────────
// This way the time increase is recorded and can sync when router recovers
try {
	error_log("[ajax_extend_user] Router unavailable ($errorMsg). Using database fallback for $username.");
	
	// Add extension minutes to the user's limit_uptime in the voucher record
	// We'll store it as a JSON note to track pending extensions
	$stmt = $DB_con->prepare("
		UPDATE hotspot_vouchers 
		SET limit_uptime = TIME_FORMAT(
				TIME_ADD(
					COALESCE(STR_TO_DATE(limit_uptime, '%i:%s'), SEC_TO_TIME(0)),
					SEC_TO_TIME(:extension_secs)
				),
				'%H:%i:%s'
			),
			notes = CONCAT(COALESCE(notes, ''), '\n[Extended +', :mins, 'min by ', :user, ' - ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'), ' - pending router sync]')
		WHERE user_name = :user_name AND status = 'Active'
	");
	
	$stmt->execute([
		':extension_secs' => $extend_mins * 60,
		':mins' => $extend_mins,
		':user' => $_SESSION['username'] ?? 'unknown',
		':user_name' => $username
	]);
	
	require_once 'audit_log.php';
	auditLog('extend_user_db', "Extended user '$username' by {$extend_mins} min (database fallback - router unavailable)");
	
	echo json_encode([
		'success'   => true,
		'message'   => "Time extension recorded (router will sync when available). Extended $username by {$extend_mins} minutes.",
		'pending'   => true
	]);
	exit;
} catch (Exception $e) {
	error_log('[ajax_extend_user] Database fallback failed: ' . $e->getMessage());
	echo json_encode(['success' => false, 'message' => 'Could not extend time at this moment. Please try again later.']);
	exit;
}
?>
