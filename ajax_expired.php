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
		// Real router mode - using modern library (RouterOS 6.43+/7.x compatible)
		require_once 'routeros_api.php';
		$connection = createRouterConnection($host, $user, $pass);
		if (!$connection['success']) {
			echo "0";
			exit;
		}
		$util = $connection['util'];
		$util->setMenu('/ip/hotspot/user');
		
		$items = $util->getAll('.id,limit-uptime,uptime,name');
		$removeCount = 0;
		foreach ($items as $item) {
			if (!empty($item->getProperty('limit-uptime'))) {
				if (!($item->getProperty('uptime') < $item->getProperty('limit-uptime'))) {
					$id = $item->getProperty('.id');
					if ($id) {
						$util->remove($id);
						$removeCount++;
					}
				}
			}
		}
		echo $removeCount;
	}
}
//End Removing All Validity Expired Guest User Accounts
?>
