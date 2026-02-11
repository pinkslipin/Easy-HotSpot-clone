<?php
require_once 'config.php';
require_once 'security_helper.php';
secure_session_start();
require_user();
csrf_require();

$i = 0;

if (true) {	

	$guest_list=$_POST['removal_list'];
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