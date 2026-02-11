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
} else {
	require_once 'routeros_api.php';
	$connection = createRouterConnection($host, $user, $pass);
	if (!$connection['success']) { echo 0; exit; }
	$util = $connection['util'];
	$client = $connection['client'];
}
if (true) {

	$profile_name=strtolower($_POST['profile_name']);
	$session_timeout=$_POST['session_timeout'];
	$shared_users=$_POST['shared_users'];
	$mac_cookie_timeout=$_POST['mac_cookie_timeout'];
	$keepalive_timeout=$_POST['keepalive_timeout'];
	$rx_rate_limit=$_POST['rx_rate_limit'];
	$tx_rate_limit=$_POST['tx_rate_limit'];

	$validity = $_POST['validity'];
	$grace_period = $_POST['grace_period'];
	$on_expiry = $_POST['on_expiry'];
	$price = $_POST['price'];
	$lock_user = $_POST['lock_user'];
	
	// SECURITY: Validate inputs to prevent RouterOS script injection
	$price = preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $price) ? $price : '0';
	$validity = preg_match('/^[0-9]+[smhdw]?( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/', $validity) ? $validity : '1d';
	$grace_period = preg_match('/^[0-9]+[smhdw]?( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/', $grace_period) ? $grace_period : '1d';
	$on_expiry = in_array($on_expiry, ['rem', 'ntf', 'remc', 'ntfc', '0'], true) ? $on_expiry : '0';
	$lock_user = in_array($lock_user, ['Enable', 'Disable'], true) ? $lock_user : 'Disable';
	$shared_users = intval($shared_users);

	$rate_limit = $rx_rate_limit.'/'.$tx_rate_limit;
	if (empty($rx_rate_limit))  $rx_rate_limit = "256k";
	if (empty($tx_rate_limit))  $tx_rate_limit = "128k";
	if (empty($shared_users))  $shared_users = 1;
	if ($price == "") {$price = "0";}
	if($lock_user === 'Enable'){$mac_bind = ';[:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';} else {$mac_bind = "";}

	$login_script = "";

	switch ($on_expiry) {
		case "rem":
			$login_script = ':put (",rem,'.$price.','.$validity.','.$grace_period.',,'.$lock_user.',");{:local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$validity.');[/system scheduler add disabled=no interval=$uptime name=$user on-event="[/ip hotspot active remove [find where user=$user]];[/ip hotspot user set limit-uptime=1s [find where name=$user]];[/sys sch re [find where name=$user]];[/sys script run [find where name=$user]];[/sys script re [find where name=$user]]" start-date=$date start-time=$time];[/system script add name=$user source=":local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$grace_period.');[/system scheduler add disabled=no interval=\$uptime name=$user on-event= \"[/ip hotspot user remove [find where name=$user]];[/ip hotspot active remove [find where user=$user]];[/sys sch re [find where name=$user]]\"]"]';
			break;
		case "ntf":
			$login_script = ':put (",ntf,'.$price.','.$validity.',,,'.$lock_user.',"); {:local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$validity.');[/system scheduler add disabled=no interval=$uptime name=$user on-event= "[/ip hotspot user set limit-uptime=1s [find where name=$user]];[/ip hotspot active remove [find where user=$user]];[/sys sch re [find where name=$user]]" start-date=$date start-time=$time]';
			break;
		case "remc":
			$login_script = ':put (",remc,'.$price.','.$validity.','.$grace_period.',,'.$lock_user.',"); {:local price ('.$price.');:local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$validity.');[/system scheduler add disabled=no interval=$uptime name=$user on-event="[/ip hotspot active remove [find where user=$user]];[/ip hotspot user set limit-uptime=1s [find where name=$user]];[/sys sch re [find where name=$user]];[/sys script run [find where name=$user]];[/sys script re [find where name=$user]]" start-date=$date start-time=$time];[/system script add name=$user source=":local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$grace_period.');[/system scheduler add disabled=no interval=\$uptime name=$user on-event= \"[/ip hotspot user remove [find where name=$user]];[/ip hotspot active remove [find where user=$user]];[/sys sch re [find where name=$user]]\"]"];:local bln [:pick $date 0 3]; :local thn [:pick $date 7 11];[:local mac $"mac-address"; /system script add name="$date-|-$time-|-$user-|-$price-|-$address-|-$mac-|-'.$validity.'" owner="$bln$thn" source=$date comment=Zetozone]';
			break;
		case "ntfc":
			$login_script = ':put (",ntfc,'.$price.','.$validity.',,,'.$lock_user.',"); {:local price ('.$price.');:local date [/system clock get date ];:local time [/system clock get time ];:local uptime ('.$validity.');[/system scheduler add disabled=no interval=$uptime name=$user on-event= "[/ip hotspot user set limit-uptime=1s [find where name=$user]];[/ip hotspot active remove [find where user=$user]];[/sys sch re [find where name=$user]]" start-date=$date start-time=$time];:local bln [:pick $date 0 3]; :local thn [:pick $date 7 11];[:local mac $"mac-address"; /system script add name="$date-|-$time-|-$user-|-$price-|-$address-|-$mac-|-'.$validity.'" owner="$bln$thn" source=$date comment=Zetozone]';
			break;
		case "0":
			if ($price != "" ){
				$login_script = ':put (",,'.$price.',,,noexp,'.$lock_user.',")';
			}	
			break;
	}
	$login_script .= $mac_bind;
	
	if (!empty($profile_name)) {
		
		// Use modern library to find and update the profile
		$util->setMenu('/ip/hotspot/user/profile');
		$items = $util->find('name', $profile_name);
		
		if (!empty($items)) {
			$id = $items[0]->getProperty('.id');
			
			// Build the set query using modern library
			$query = new \RouterOS\Query('/ip/hotspot/user/profile/set');
			$query->equal('.id', $id);
			$query->equal('rate-limit', $rate_limit);
			$query->equal('shared-users', (string)$shared_users);
			$query->equal('status-autorefresh', '1m');
			$query->equal('transparent-proxy', 'yes');
			$query->equal('on-login', $login_script);
			
			$client->query($query)->read();
		}
		echo 2; //Success
	}
	else
		{
		echo 1; //Profile name Improper
	}
}
else
	{
		echo 0; //Not Authorised
}
?>