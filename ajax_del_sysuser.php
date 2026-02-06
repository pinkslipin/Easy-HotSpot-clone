<?php 
require_once 'security_helper.php';
secure_session_start();
require_admin();
csrf_require();
if ((!empty($_POST['user_id']))) { 
	include('dbconfig.php');
	$id=$_POST['user_id'];
	$myself = $_SESSION['id'];
	$stmt = $DB_con->prepare("delete from hotspot_users where user_id=:id and user_id != :myself");
	$stmt->execute(array(':id' => $id, ':myself' => $myself));
	echo 1;
	}
else
	{
		echo 0;
	}
// End Adding a new System User Details, Returned from modal_add_user.php
?>