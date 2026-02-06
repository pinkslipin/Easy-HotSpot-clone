<?php 
require_once 'security_helper.php';
require_user();
csrf_require();
include('dbconfig.php');

$np = isset($_POST['np']) ? $_POST['np'] : '';

if (!empty($np) && strlen($np) >= 4) {
	// SECURITY: Use bcrypt instead of SHA-1
	$hashed = secure_password_hash($np);
	$stmt = $DB_con->prepare("UPDATE hotspot_users SET password = :np WHERE user_id = :session_id");
	$stmt->execute(array(':np' => $hashed, ':session_id' => $_SESSION['id']));
	
	require_once 'audit_log.php';
	auditLog('password_change', 'User changed their own password');
	echo 1;
} else {
	echo 0;
}
?>