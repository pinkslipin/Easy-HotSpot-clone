<?php
//Start Removing All Validity Expired Guest User Accounts

// Allow invocation from Windows Task Scheduler / CLI without an HTTP session
$isCli = (php_sapi_name() === 'cli');
if ($isCli) {
    chdir(__DIR__); // ensure relative require_once paths resolve correctly from CLI
}

require_once 'config.php';
require_once 'security_helper.php';

if (!$isCli) {
    secure_session_start();
    require_user();
    csrf_require();
}

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
		require_once 'dbconfig.php';
		require_once 'packages_config.php';

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
			try {
				$placeholders = implode(',', array_fill(0, count($expiredNames), '?'));
				$stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Expired' WHERE user_name IN ($placeholders)");
				$stmt->execute(array_values($expiredNames));
			} catch (Exception $e) {
				// Non-fatal: router removal already succeeded
			}
		}

		// ── Window package enforcement ────────────────────────────────────────
		// Kick any active sessions whose time-window has ended.
		// This backstops the RouterOS profile on-login script (which only fires
		// at login) and catches users who were connected when the window closed.
		$windowKickCount = 0;
		try {
			$nowSecs = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');
			$wStmt = $DB_con->prepare(
				"SELECT user_name, package_id FROM hotspot_vouchers
				  WHERE status = 'Active' AND package_type = 'window'"
			);
			$wStmt->execute();
			$windowVouchers = $wStmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($windowVouchers as $v) {
				$pkg = getPackage($v['package_id']);
				if (!$pkg || $pkg['type'] !== 'window') continue;

				list($sh, $sm) = explode(':', $pkg['window_start']);
				list($eh, $em) = explode(':', $pkg['window_end']);
				$wStart = (int)$sh * 3600 + (int)$sm * 60;
				$wEnd   = (int)$eh * 3600 + (int)$em * 60;

				$inWindow = ($pkg['spans_midnight'] ?? false)
					? ($nowSecs >= $wStart || $nowSecs < $wEnd)   // e.g. 18:00–06:00
					: ($nowSecs >= $wStart && $nowSecs < $wEnd);  // e.g. 06:00–18:00

				if (!$inWindow) {
					$util->setMenu('/ip/hotspot/active');
					try {
						$sessions = $util->find('user', $v['user_name']);
						foreach ($sessions as $sess) {
							$util->remove($sess->getProperty('.id'));
							$windowKickCount++;
						}
					} catch (Exception $e) {
						// Continue if a single kick fails
					}
				}
			}
		} catch (Exception $e) {
			// Non-fatal: duration cleanup already succeeded
		}

		echo $removeCount + $windowKickCount;
	}
}
//End Removing All Validity Expired Guest User Accounts
?>
