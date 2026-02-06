<?php 
require_once 'security_helper.php';
require_admin();
csrf_require();

if (!empty($_POST['username'])) { 
	include('dbconfig.php');
	// SECURITY: Generate random initial password (bcrypt), not hardcoded 'password'
	$chars = 'abcdefghjkmnpqrstuvwxyz23456789';
	$initialPass = '';
	for ($p = 0; $p < 8; $p++) { $initialPass .= $chars[random_int(0, strlen($chars) - 1)]; }
	$password = secure_password_hash($initialPass);
	$username = sanitize_username($_POST['username']);
	$firstname=$_POST['firstname'];
	$lastname=$_POST['lastname'];
	$user_level=$_POST['user_level'];
	$status=$_POST['status'];

	// SECURITY: Validate user_level and status against allowed values
	if (!in_array((int)$user_level, [1, 2, 3], true)) { echo 0; exit; }
	if (!in_array($status, ['Active', 'Disabled'], true)) { echo 0; exit; }
	
	
	$stmt = $DB_con->prepare("SELECT * FROM hotspot_users WHERE username =:username");
	$stmt->execute(array(':username' => $username));
	$count = $stmt->rowCount();
	
	if ($count != 0) {
		echo 1;
	}
 else
	{
		$stmt = $DB_con->prepare("insert into hotspot_users (username, password, firstname, lastname, date_added, user_level, status)
			values(:username, :password, :firstname, :lastname, CURDATE(), :user_level, :status)");
		$stmt->execute(array(':username' => $username, ':password' => $password, ':firstname' => $firstname,
			':lastname' => $lastname, ':user_level' => $user_level, ':status' => $status));
		echo 2;
	}
}
else {
	echo 0;
}	
// End Adding a new System User Details, Returned from modal_add_user.php
?>