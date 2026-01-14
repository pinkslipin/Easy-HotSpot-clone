<?php
require_once 'config.php';
if ( !isset($_SESSION) ) session_start();

$i = 0;

if ($_SESSION['user_level'] <= 3) {	

	$guest_list=$_GET['removal_list'];
	if (count($guest_list) != 0) {
		
		if (defined('MOCK_MODE') && MOCK_MODE === true) {
			// Mock mode - remove from local JSON storage
			require_once 'mock_router.php';
			$mockUtil = new MockRouterUtil();
			
			foreach ($guest_list as $guest) {
				if ($mockUtil->removeUser($guest)) {
					$i++;
				}
			}
		} else {
			// Real router mode
			require_once 'PEAR2/Autoload.php';
			$client = new \PEAR2\Net\RouterOS\Client("$host", "$user", "$pass");
			$util = new \PEAR2\Net\RouterOS\Util($client);
			
			$printRequest = new \PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print');
			$printRequest->setArgument('.proplist', '.id,name');
			$removeRequest = new \PEAR2\Net\RouterOS\Request('/ip/hotspot/user/remove');
			foreach ($guest_list as $guest) {
				$i++;
				$printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('name', $guest));
				$id = $client->sendSync($printRequest)->getProperty('.id');
				$removeRequest->setArgument('numbers', $id);
				$client->sendSync($removeRequest);
			}
		}
		echo $i;
	}
	else
	{
		echo -1;
	}
}	
else
	{
	echo 0; 
}