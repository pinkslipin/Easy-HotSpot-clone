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

$profile_name=strtolower($_POST['profile_name']);

	if (!empty($profile_name)) {
		
		// Use modern library to find and remove the profile
		$util->setMenu('/ip/hotspot/user/profile');
		$items = $util->find('name', $profile_name);
		foreach ($items as $item) {
			$id = $item->getProperty('.id');
			if ($id) {
				$util->remove($id);
			}
		}
		echo 2; //Success
		}
	else
		{
		echo 1; //Profile name Empty
	}
?>