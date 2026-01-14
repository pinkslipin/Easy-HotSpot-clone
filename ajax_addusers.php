<?php  error_reporting(E_ALL);
ini_set('display_errors', 1);

//require_once 'settings.php';
require_once 'config.php';

// Initialize router connection (mock or real)
if (defined('MOCK_MODE') && MOCK_MODE === true) {
	require_once 'mock_router.php';
	$util = new MockRouterUtil();
	$client = new MockClient();
} else {
	// Use modern RouterOS API library (works with RouterOS 6.43+ and 7.x)
	require_once 'routeros_api.php';
	$connection = createRouterConnection($host, $user, $pass);
	if (!$connection['success']) {
		die("Router connection failed: " . $connection['error']);
	}
	$util = $connection['util'];
	$client = $connection['client'];
}

if (isset($_GET['no_of_users'])) $no_of_users = $_GET['no_of_users'];
if (isset($_GET['pass_length'])) $passLength = $_GET['pass_length'];
if (isset($_GET['user_prefix'])) $user_prefix = $_GET['user_prefix'];
if (isset($_GET['limit_uptime'])) $limit_uptime = $_GET['limit_uptime'];
if (isset($_GET['limit_bytes'])) $limit_bytes = $_GET['limit_bytes'];
if (isset($_GET['profile'])) $profile = $_GET['profile'];
if (isset($_GET['same_pass'])) $same_pass = $_GET['same_pass'];
if (isset($_GET['pass_type'])) $pass_type = $_GET['pass_type'];

if ( !isset($_SESSION) ) session_start();

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
	require_once 'pricing_config.php';
	
	$stmt = $DB_con->prepare("SELECT booking_id from hotspot_vouchers ORDER BY booking_id DESC LIMIT 1");
	$stmt->execute(array());
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$booking_id = ($row && isset($row['booking_id'])) ? $row['booking_id'] : 0;
	$booking_id++;
	
	// Generate batch ID based on uptime limit and timestamp
	$batch_id = strtoupper($limit_uptime) . '-' . date('mdHi');
	
	// Get price and expiry date from config
	$price = getVoucherPrice($limit_uptime);
	$expires_on = getExpiryDate();
	
	// NOTE: Removed the UPDATE that marked all previous vouchers as 'Over'
	// Now each batch stays 'Active' until manually changed

	$stmt = $DB_con->prepare("insert into hotspot_vouchers (created_on, created_by, creator, user_name, password, printed_times,
		printed_last, status, group_of, booking_id, limit_uptime, limit_bytes, profile, uid, batch_id, price, expires_on)
		values(NOW(), :created_by, :creator,  :user_name, :password, :printed_times, :printed_last, :status, :group_of, 
		:booking_id, :limit_uptime, :limit_bytes, :profile, :uid, :batch_id, :price, :expires_on)");
		
	$k = 1;
	for($i=0; $i < $no_of_users; $i++){
		//$passAlphabet = 'abcdefghikmnpqrstuvxyz23456789';
		//$passAlphabetLimit = strlen($passAlphabet)-1;
		$pass = '';
		$uid = '';
		//Password generation
		for ($j = 0; $j < $passLength; ++$j) {
			$pass .= $passAlphabet[mt_rand(0, $passAlphabetLimit)];
		}
		$pass = str_shuffle($pass);
		//Username generation
		for ($j = 0; $j < $passLength; ++$j) {
			$uid .= $passAlphabet[mt_rand(0, $passAlphabetLimit)];
		}
		//Adding prefix to username
		$user_name = $user_prefix.$uid;
		
		//username & password same or different
		if ($same_pass == 2) {	$pass_word = $pass; } else { $pass_word = $user_name; }
		
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
					'comment' => "Zetozone",
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
					'comment' => "Zetozone",
				)
			);
			$limit_bytes = 0; // For Adding it to Local database
		}	

		$updatedUsers = $util->getAll();
		if ($iv != count($updatedUsers)) {
			$uid = $booking_id.'-'.$k.'-'.$no_of_users.date('dmY');
			//$creator = $_SESSION['username'].'['.$_SESSION['id'].']';
			$stmt->execute(array(':created_by' => $_SESSION['username'], ':creator' => $_SESSION['id'], ':user_name' => $user_name, ':password' => $pass_word,
				':printed_times' => 0, ':printed_last' => '', ':status' => 'Active', ':group_of' => $no_of_users,
				':booking_id' => $booking_id, ':limit_uptime' => $limit_uptime, ':limit_bytes' => $limit_bytes,
				':profile' => $profile, ':uid' => $uid, ':batch_id' => $batch_id, ':price' => $price, ':expires_on' => $expires_on));			
			$k++;	
		} 	
	}
	
	// Log the voucher creation
	$created = $k - 1;
	if ($created > 0) {
		require_once 'audit_log.php';
		auditLog('voucher_create', "Created batch $batch_id with $created vouchers ($limit_uptime)");
	}
	
	echo $created; //Successful
}
else
	{
	echo 0; // Not an Authorised User
}
?>