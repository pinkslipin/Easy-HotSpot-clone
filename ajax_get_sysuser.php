<?php
require_once 'security_helper.php';
header('Content-Type: application/json');
require_admin();
csrf_require();

$user_id = sanitize_int($_POST['user_id']);
if ($user_id > 0) {
	include('dbconfig.php');
	
	// SECURITY: Never return password hash to client
	$stmt = $DB_con->prepare("SELECT user_id, username, firstname, lastname, user_level, status, date_added FROM hotspot_users WHERE user_id = :user_id");
	$stmt->execute(array(':user_id' => $user_id));
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$row['password'] = '********'; // Masked
		echo json_encode($row);
	} else {
		echo json_encode(['error' => 'User not found']);
	}
} else {
	echo json_encode(['error' => 'Invalid user ID']);
}
?>