<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();

if (defined('MOCK_MODE') && MOCK_MODE === true) {
	require_once 'mock_router.php';
	$util = new MockRouterUtil();
	$client = new MockClient();
} else {
	require_once 'routeros_api.php';
	$connection = createRouterConnection($host, $user, $pass);
	if (!$connection['success']) { echo json_encode(['error' => 'Connection failed']); exit; }
	$util = $connection['util'];
	$client = $connection['client'];
}
if (true) {
	$profile_name=$_POST['profile_name'];
	
	// Use modern library to find the profile
	$util->setMenu('/ip/hotspot/user/profile');
	$items = $util->find('name', $profile_name);

	foreach ($items as $item) {

	$tname =  $item->getProperty("name");
	$taddress_pool =  $item->getProperty("address-pool");
	$tshared_users =  $item->getProperty("shared-users");
	$trate_limit =  $item->getProperty("rate-limit");
	$tsession_timeout =  $item->getProperty("session-timeout");
	$ton_login =  $item->getProperty("on-login");
	$tmac_cookie_timeout =  $item->getProperty("mac-cookie-timeout");
	$tkeepalive_timeout =  $item->getProperty("keepalive-timeout");
	
	$exploded = explode(",",$ton_login);
	
	$ton_expiry = $exploded[1];
	$tprice = $exploded[2];	
	$tvalidity = $exploded[3];
	$tgrace_period = $exploded[4];
	$tlock_user = $exploded[6];
	
	if($ton_expiry == "rem"){ $tton_expiry = "Remove"; }
		elseif ($ton_expiry == "ntf"){ $tton_expiry = "Notice"; }
		elseif ($ton_expiry == "remc") { $tton_expiry = "Remove & Record"; }
		elseif ($ton_expiry == "ntfc") { $tton_expiry = "Notice & Record"; }
		else $tton_expiry = "0";
		

	$arr = array('name' => $tname, 'address_pool' => $taddress_pool, 'rate_limit' => $trate_limit, 'session_timeout' => $tsession_timeout,
		'shared_users' => $tshared_users, 'mac_cookie_timeout' => $tmac_cookie_timeout,
		'keepalive_timeout' => $tkeepalive_timeout, 'on_expiry' => $ton_expiry, 'price' => $tprice, 'validity' => $tvalidity,
		'grace_period' => $tgrace_period, 'lock_user' => $tlock_user );

	echo json_encode($arr); 
	}
}	
?>