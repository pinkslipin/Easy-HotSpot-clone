<?php
header('Content-Type: application/json');
use PEAR2\Net\RouterOS;
require_once 'PEAR2/Autoload.php';
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();
$util = new RouterOS\Util($client = new RouterOS\Client("$host", "$user", "$pass"));

$profile_name=strtolower($_POST['profile_name']);

	if (!empty($profile_name)) {
		
		$printRequest = new RouterOS\Request('/ip hotspot user profile print');
		$printRequest->setArgument('.proplist', '.id,name');
		$printRequest->setQuery(RouterOS\Query::where('name', $profile_name)); 

		$idList = '';
		foreach ($client->sendSync($printRequest)->getAllOfType(RouterOS\Response::TYPE_DATA) as $item) {
			$idList .= ',' . $item->getProperty('.id');
		}
		$idList = substr($idList, 1);
		//$idList now contains a comma separated list of all IDs.

		$removeRequest = new RouterOS\Request('/ip hotspot user profile remove');
		$removeRequest->setArgument('numbers', $idList);
		$client->sendSync($removeRequest); 
		echo 2; //Success
		}
	else
		{
		echo 1; //Profile name Empty
	}
?>