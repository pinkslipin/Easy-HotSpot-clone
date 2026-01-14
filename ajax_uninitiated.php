<?php
//Start Removing All Un-initiated Guest User Accounts
require_once 'config.php';
if ( !isset($_SESSION) ) session_start();

if ($_SESSION['user_level'] <= 2) {
	
	if (defined('MOCK_MODE') && MOCK_MODE === true) {
		// Mock mode - remove users with 0s uptime
		require_once 'mock_router.php';
		$mockUtil = new MockRouterUtil();
		$mockUtil->setMenu('/ip hotspot user');
		
		$removed = 0;
		foreach ($mockUtil->getAll() as $item) {
			if ($item->getProperty('uptime') == '0s' || $item->getProperty('uptime') == 0) {
				if ($mockUtil->removeUser($item->getProperty('name'))) {
					$removed++;
				}
			}
		}
		echo $removed;
	} else {
		// Real router mode - using modern library (RouterOS 6.43+/7.x compatible)
		require_once 'routeros_api.php';
		$connection = createRouterConnection($host, $user, $pass);
		
		if ($connection['success']) {
			$util = $connection['util'];
			$util->setMenu('/ip/hotspot/user');
			
			$removed = 0;
			$items = $util->getAll('.id,name,uptime');
			
			foreach ($items as $item) {
				$uptime = $item->getProperty('uptime');
				if ($uptime === '0s' || $uptime === 0 || $uptime === null || $uptime === '') {
					$id = $item->getProperty('.id');
					if ($id && $util->remove($id)) {
						$removed++;
					}
				}
			}
			echo $removed;
		} else {
			echo 0;
		}
	}
}
//End Removing All Un-initiated Guest User Accounts
?>
