<?php 
require_once 'security_helper.php';
header('Content-Type: application/json');
require_admin();
csrf_require();

include('dbconfig.php');
$user_id = sanitize_int($_POST['user_id']);
if ($user_id > 0) {
	
	// Generate a random 8-character temporary password
	$chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
	$temp_password = '';
	for ($i = 0; $i < 8; $i++) {
		$temp_password .= $chars[random_int(0, strlen($chars) - 1)];
	}
	
	$hashed = secure_password_hash($temp_password);
	
	$stmt = $DB_con->prepare("SELECT username FROM hotspot_users WHERE user_id = :user_id");
	$stmt->execute(array(':user_id' => $user_id));
	$count = $stmt->rowCount();
	
	if ($count == 0) {
		echo json_encode(['status' => 1, 'message' => 'User not found']);
	} else {
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt = $DB_con->prepare("UPDATE hotspot_users SET password = :password WHERE user_id = :user_id");
		$stmt->execute(array(':password' => $hashed, ':user_id' => $user_id));
		
		// Log the password reset
		require_once 'audit_log.php';
		auditLog('password_reset', 'Password reset for user: ' . $row['username']);
		
		echo json_encode([
			'status' => 2,
			'message' => 'Password reset successfully',
			'username' => $row['username'],
			'temp_password' => $temp_password
		]);
	}
} else {
	echo json_encode(['status' => 1, 'message' => 'Invalid user ID']);
}
?>