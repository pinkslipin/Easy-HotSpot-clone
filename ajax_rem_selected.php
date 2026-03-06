<?php
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_user();
csrf_require();

$i = 0;

if (true) {	

	$guest_list = isset($_POST['removal_list']) && is_array($_POST['removal_list'])
		? $_POST['removal_list']
		: [];
	if (count($guest_list) != 0) {
		
		if (defined('MOCK_MODE') && MOCK_MODE === true) {
			// Mock mode - remove from database (mock users are synced from DB)
			require_once 'dbconfig.php';
			
			foreach ($guest_list as $guest) {
				try {
					$stmt = $DB_con->prepare("DELETE FROM hotspot_vouchers WHERE user_name = :user_name");
					$stmt->execute([':user_name' => $guest]);
					if ($stmt->rowCount() > 0) {
						$i++;
					}
				} catch (Exception $e) {
					// Continue on error
				}
			}
			
			// Log the removal
			require_once 'audit_log.php';
			auditLog('user_delete', "Removed $i selected users");
		} else {
			// Real router mode - using modern library (RouterOS 6.43+/7.x compatible)
			require_once 'routeros_api.php';
			$connection = createRouterConnection($host, $user, $pass);
			if (!$connection['success']) {
				echo -1;
				exit;
			}
			$util = $connection['util'];
			$util->setMenu('/ip/hotspot/user');
			foreach ($guest_list as $guest) {
				if ($util->removeUser($guest)) {
					$i++;
				}
			}

			// Kick active sessions so devices lose internet access immediately
			$util->setMenu('/ip/hotspot/active');
			foreach ($guest_list as $guest) {
				$activeSessions = $util->find('user', $guest);
				foreach ($activeSessions as $session) {
					$util->remove($session->getProperty('.id'));
				}
			}

			// Update DB status for all removed users so dashboard stats are accurate
			require_once 'dbconfig.php';
			if (!empty($guest_list)) {
				try {
					$placeholders = implode(',', array_fill(0, count($guest_list), '?'));
					$stmt = $DB_con->prepare("UPDATE hotspot_vouchers SET status = 'Used' WHERE user_name IN ($placeholders)");
					$stmt->execute(array_values($guest_list));
				} catch (Exception $e) {
					// Non-fatal: router removal already succeeded
				}
			}

			// Audit log for real-router removals
			require_once 'audit_log.php';
			auditLog('user_delete', "Bulk removed $i users: " . implode(', ', array_slice($guest_list, 0, 20)));
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