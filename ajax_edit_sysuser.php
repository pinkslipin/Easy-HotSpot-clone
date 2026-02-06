<?php 
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();

if ((!empty($_POST['user_id']))) { 
	include('dbconfig.php');

	$user_id=$_POST['user_id'];
	$username=strtolower($_POST['username']);
	$stmt = $DB_con->prepare("SELECT * FROM hotspot_users WHERE username = :username AND user_id != :user_id");
	$stmt->execute(array(':username' => $username, ':user_id' => $user_id));
	$count = $stmt->rowCount();

	if ($count != 0) {
		echo 1;
		}
	else
		{
		$firstname=$_POST['firstname'];
		$lastname=$_POST['lastname'];
		$user_level=$_POST['user_level'];
		$status=$_POST['status'];

		// SECURITY: Validate user_level and status against allowed values
		if (!in_array((int)$user_level, [1, 2, 3], true)) { echo 0; exit; }
		if (!in_array($status, ['Active', 'Disabled'], true)) { echo 0; exit; }

		$stmt = $DB_con->prepare("update hotspot_users set username=:username, firstname = :firstname , lastname = :lastname,
			user_level = :user_level, status = :status where user_id= :user_id");
		$stmt->execute(array(':username' => $username, ':firstname' => $firstname, ':lastname' => $lastname,
			':user_level' => $user_level, ':user_id' => $user_id, ':status' => $status));
		echo 2;
	}
}
else
	{
	echo 0;
}	
// End Adding a new System User Details, Returned from modal_add_user.php
?>