<?php
//Start Removing All Validity Expired Guest User Accounts
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_user();
csrf_require();

if (true) {	
	
	if (defined('MOCK_MODE') && MOCK_MODE === true) {
		// Mock mode - In mock mode, expired users would be handled differently
		// For now, just return success (no expired users to remove in mock)
		require_once 'mock_router.php';
		$mockUtil = new MockRouterUtil();
		// Mock doesn't track actual uptime usage, so nothing to expire
		echo "0"; // No expired users removed in mock mode
	} else {
		// Real router mode
		require_once 'PEAR2/Autoload.php';
		$client = new \PEAR2\Net\RouterOS\Client("$host", "$user", "$pass");
		$util = new \PEAR2\Net\RouterOS\Util($client);
		
		$printRequest = new \PEAR2\Net\RouterOS\Request('/ip hotspot user print');
		$printRequest->setArgument('.proplist', '.id,limit-uptime,uptime,name');
		$printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('.id', '*0', \PEAR2\Net\RouterOS\Query::OP_EQ)->not()); 

		$idList = '';
		foreach ($client->sendSync($printRequest)->getAllOfType(\PEAR2\Net\RouterOS\Response::TYPE_DATA) as $item) {
			if (!empty($item->getProperty('limit-uptime'))) {
				if (!($item->getProperty('uptime') < $item->getProperty('limit-uptime'))) {
					$idList .= ',' . $item->getProperty('.id');
				}
			}	
		}
		$idList = substr($idList, 1);

		$removeRequest = new \PEAR2\Net\RouterOS\Request('/ip hotspot user remove');
		$removeRequest->setArgument('numbers', $idList);
		$client->sendSync($removeRequest);
	}
}
//End Removing All Validity Expired Guest User Accounts
?>
