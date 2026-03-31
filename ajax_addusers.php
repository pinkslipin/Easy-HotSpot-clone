<?php
// Suppress error details in production
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

/**
 * Batch Voucher Creation
 * 
 * Creates multiple hotspot user vouchers on the router and in the database.
 * Now supports package-based pricing including time-window passes.
 */

require_once 'config.php';
require_once 'packages_config.php';
require_once 'security_helper.php';

// SECURITY: Require authenticated user
require_auth();
csrf_require();

// Load multi-router support
require_once 'load_balancer.php';

// Initialize router connection (mock or real)
$load_balancer = null; // Will be initialized for load balancing

if (defined('MOCK_MODE') && MOCK_MODE === true) {
	require_once 'mock_router.php';
	$util = new MockRouterUtil();
	$client = new MockClient();
} else {
	// Use modern RouterOS API library (works with RouterOS 6.43+ and 7.x)
	require_once 'routeros_api.php';
	
	// Phase 3: Load-Balanced User Assignment
	$load_balancer = new LoadBalancer($DB_con);
	
	// For batch creation, we'll assign each user randomly
	// We'll initialize connection on-demand per user to support different routers
}

if (isset($_POST['no_of_users'])) $no_of_users = $_POST['no_of_users'];
if (isset($_POST['pass_length'])) $passLength = $_POST['pass_length'];
if (isset($_POST['user_prefix'])) $user_prefix = $_POST['user_prefix'];

// Package-based voucher creation
$package_id = null;
if (isset($_POST['package_id'])) {
	$package_id = $_POST['package_id'];
}
// Backward compatibility: also accept limit_uptime if package_id is not provided
if (!$package_id && isset($_POST['limit_uptime'])) {
	$legacy_uptime = $_POST['limit_uptime'];
	// Try to find matching package
	foreach (getAllPackages() as $pkg) {
		if ($pkg['type'] === 'duration' && $pkg['limit_uptime'] === $legacy_uptime) {
			$package_id = $pkg['id'];
			break;
		}
	}
	// If no match, use default
	if (!$package_id) {
		$package_id = 'ind_1h';
	}
}

if (isset($_POST['limit_bytes'])) $limit_bytes = $_POST['limit_bytes'];
if (isset($_POST['profile'])) $profile = $_POST['profile'];
if (isset($_POST['same_pass'])) $same_pass = $_POST['same_pass'];
if (isset($_POST['pass_type'])) $pass_type = $_POST['pass_type'];

if (session_status() === PHP_SESSION_NONE) session_start();

// Get package information
$package = getPackage($package_id);
if (!$package) {
	echo 0; // Error - invalid package
	exit;
}

$price = $package['price'];
$package_name = getPackageDisplayName($package_id);
$package_type = $package['type'];

// Determine limit_uptime for router
if ($package['type'] === 'duration') {
	$limit_uptime = $package['limit_uptime'];
} else {
	// Window packages don't use traditional limit-uptime
	$limit_uptime = '1d'; // Will be managed by profile script
}

switch ($pass_type) {
	case "s":
		$passAlphabet = "abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz";
		$user_prefix = strtolower($user_prefix);
		break;
	case "c":
		$passAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$user_prefix = strtoupper($user_prefix);
		break;
	case "n":
		$passAlphabet = "123456789123456789123456789123456789123456789123456789";
		break;
	case "sc":
		$passAlphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		break;
	case "sn":
		$passAlphabet = "abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz123456789123456789123456789";
		$user_prefix = strtolower($user_prefix);
		break;
	case "cn":
		$passAlphabet = "123456789123456789123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789123456789123456789";
		$user_prefix = strtoupper($user_prefix);
		break;
	case "scn":
		$passAlphabet = "abcdefghijklmnopqrstuvwxyz123456789123456789123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789";
		break;
}

$passAlphabetLimit = strlen($passAlphabet)-1;
	
if($_SESSION['user_level'] >= 1 and $_SESSION['user_level'] <= 3) {
	include('dbconfig.php');
	
	$stmt = $DB_con->prepare("SELECT booking_id from hotspot_vouchers ORDER BY booking_id DESC LIMIT 1");
	$stmt->execute(array());
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$booking_id = ($row && isset($row['booking_id'])) ? $row['booking_id'] : 0;
	$booking_id++;
	
	// Generate batch ID based on package ID and timestamp (not uptime)
	$batch_id = strtoupper($package_id) . '-' . date('mdHi');
	
	// Get expiry date from config
	$expires_on = getExpiryDate();
	
	// Store limit_uptime from package (empty for window packages)
	$db_limit_uptime = ($package['type'] === 'duration') ? $package['limit_uptime'] : '';

	// Phase 3: For batch creation, we need a per-user approach with load balancing
	$k = 1;
	for($i=0; $i < $no_of_users; $i++){
		$pass = '';
		$uid = '';
		//Password generation
		for ($j = 0; $j < $passLength; ++$j) {
			$pass .= $passAlphabet[random_int(0, $passAlphabetLimit)];
		}
		$pass = str_shuffle($pass);
		//Username generation
		for ($j = 0; $j < $passLength; ++$j) {
			$uid .= $passAlphabet[random_int(0, $passAlphabetLimit)];
		}
		//Adding prefix to username
		$user_name = $user_prefix.$uid;
		
		//username & password same or different
		if ($same_pass == 2) {	$pass_word = $pass; } else { $pass_word = $user_name; }
		
		// PHASE 3: Load-Balanced User Assignment
		// Each user is randomly assigned to a router
		$assigned_router = 'converge'; // Default for mock mode
		if (defined('MOCK_MODE') && MOCK_MODE !== true) {
			$assigned_router = $load_balancer->getRandomRouter();
			$router_config = getRouterConfig($assigned_router);
			$connection = createRouterConnection($router_config['ip'], $router_config['user'], $router_config['pass'], $router_config['port']);
			if (!$connection['success']) {
				error_log("Failed to connect to router $assigned_router for batch user creation");
				continue; // Skip this user and move to next
			}
			$util = $connection['util'];
			$client = $connection['client'];
		}
		
		$util->setMenu('/ip hotspot user');
		$existingUsers = $util->getAll();
		$iv = count($existingUsers);
		
		if (intval($limit_bytes) != 0) {
			$limit_bytes_total = (intval($limit_bytes) * 1024 * 1024 * 1024 );
			$util->add(
				array(
					'name' => "$user_name",
					'password' => "$pass_word",
					'disabled' => "no",
					'limit-uptime' => "$limit_uptime",
					'limit-bytes-total' => "$limit_bytes_total",
					'profile' => "$profile",
					'comment' => "PKG:$package_id",
				)
			);
		}
		else
			{
			$util->add(
				array(
					'name' => "$user_name",
					'password' => "$pass_word",
					'disabled' => "no",
					'limit-uptime' => "$limit_uptime",
					'profile' => "$profile",
					'comment' => "PKG:$package_id",
				)
			);
			$limit_bytes = 0; // For Adding it to Local database
		}	

		$updatedUsers = $util->getAll();
		if ($iv != count($updatedUsers)) {
			$voucher_uid = $booking_id.'-'.$k.'-'.$no_of_users.date('dmY');
			
			// Insert with assigned_router
			$stmt = $DB_con->prepare("INSERT INTO hotspot_vouchers (created_on, created_by, creator, user_name, password, printed_times,
				printed_last, status, group_of, booking_id, limit_uptime, limit_bytes, profile, uid, batch_id, price, expires_on,
				package_id, package_name, package_type, assigned_router)
				VALUES(NOW(), :created_by, :creator, :user_name, :password, :printed_times, :printed_last, :status, :group_of, 
				:booking_id, :limit_uptime, :limit_bytes, :profile, :uid, :batch_id, :price, :expires_on,
				:package_id, :package_name, :package_type, :assigned_router)");
			
			$stmt->execute(array(
				':created_by' => $_SESSION['username'], 
				':creator' => $_SESSION['id'], 
				':user_name' => $user_name, 
				':password' => $pass_word,
				':printed_times' => 0, 
				':printed_last' => '', 
				':status' => 'Active', 
				':group_of' => $no_of_users,
				':booking_id' => $booking_id, 
				':limit_uptime' => $db_limit_uptime, 
				':limit_bytes' => $limit_bytes,
				':profile' => $profile, 
				':uid' => $voucher_uid, 
				':batch_id' => $batch_id, 
				':price' => $price, 
				':expires_on' => $expires_on,
				':package_id' => $package_id,
				':package_name' => $package_name,
				':package_type' => $package_type,
				':assigned_router' => $assigned_router
			));			
			$k++;	
		} 	
	}
	
	// Log the voucher creation
	$created = $k - 1;
	if ($created > 0) {
		require_once 'audit_log.php';
		auditLog('voucher_create', "Created batch $batch_id with $created vouchers ($package_name) with load balancing");
	}
	
	echo $created; //Successful
}
else
	{
	echo 0; // Not an Authorised User
}
?>