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

		// Helper: parse RouterOS time string (e.g. "5h30m", "1d") to seconds.
		// Direct string comparison is WRONG: "30m" > "1h" as strings because '3' > '1'.
		$parseRos = function($t) {
			if (empty($t)) return 0;
			$s = 0;
			if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) return (int)$m[1]*3600+(int)$m[2]*60+(int)$m[3];
			if (preg_match('/(\d+)w/', $t, $m)) $s += (int)$m[1]*604800;
			if (preg_match('/(\d+)d/', $t, $m)) $s += (int)$m[1]*86400;
			if (preg_match('/(\d+)h/', $t, $m)) $s += (int)$m[1]*3600;
			if (preg_match('/(\d+)m/', $t, $m)) $s += (int)$m[1]*60;
			if (preg_match('/(\d+)s/', $t, $m)) $s += (int)$m[1];
			return $s;
		};

		$items        = $util->getAll('.id,limit-uptime,uptime,name');
		$removeCount  = 0;
		$expiredNames = [];
		foreach ($items as $item) {
			$limitUptime = $item->getProperty('limit-uptime');
			$uptime      = $item->getProperty('uptime');
			if (!empty($limitUptime) && $parseRos($limitUptime) > 0) {
				if ($parseRos($uptime) >= $parseRos($limitUptime)) {
					$id = $item->getProperty('.id');
					if ($id) {
						$expiredNames[] = $item->getProperty('name');
						$util->remove($id);
						$removeCount++;
					}
				}
			}
		}

		// Kick active sessions for all removed users so devices lose internet access immediately
		if (!empty($expiredNames)) {
			$util->setMenu('/ip/hotspot/active');
			foreach ($expiredNames as $expiredUser) {
				try {
					$activeSessions = $util->find('user', $expiredUser);
					foreach ($activeSessions as $session) {
						$util->remove($session->getProperty('.id'));
					}
				} catch (Exception $e) {
					// Continue even if a session removal fails
				}
			}

			// Update DB status to 'Expired' so dashboard counts are accurate
			require_once 'dbconfig.php';
			try {
				$placeholders = implode(',', array_fill(0, count($expiredNames), '?'));
				$stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Expired' WHERE user_name IN ($placeholders)");
				$stmt->execute(array_values($expiredNames));
			} catch (Exception $e) {
				// Non-fatal: router removal already succeeded
			}
		}

		echo $removeCount;
	}
}
//End Removing All Validity Expired Guest User Accounts
?>
