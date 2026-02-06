<?php
	require_once 'security_helper.php';
	secure_session_start();
	
	// SECURITY: Only administrators can change router settings
	if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] != 1) {
		echo '<script>cmodal("Access Denied!", "Only administrators can change settings.", "error", "index.php")</script>';
	} elseif (isset($_POST['btn_update'])) {
		// SECURITY: Validate CSRF token
		if (!csrf_validate()) {
			echo '<script>cmodal("Security Error!", "Invalid security token. Please refresh and try again.", "error", "index.php")</script>';
		} else {
			$newhost = trim($_POST['newhost']);
			$newuser = trim($_POST['newuser']);
			$newpass = $_POST['newpass'];
			
			// SECURITY: Validate IP address format (prevents code injection)
			if (!filter_var($newhost, FILTER_VALIDATE_IP)) {
				echo '<script>cmodal("Invalid Input!", "Please enter a valid IP address.", "error", "index.php")</script>';
			} elseif (write_router_config($newhost, $newuser, $newpass)) {
				require_once 'audit_log.php';
				auditLog('settings_change', 'Router settings updated by admin');
				echo '<script>cmodal("Success!", "Successfully saved the new settings!", "success", "index.php")</script>';
			} else {
				echo '<script>cmodal("Error!", "Failed to save settings.", "error", "index.php")</script>';
			}
		}
	}
?>
<div class="container">
	<header>
		<h1 style="text-align:center;">Easy Hotspot</h1>	
		<h2 style="text-align:center;">Simple HotSpot User Management Utility</h2>
		<h3 style="text-align:center;">By TEAM ZETOZONE</h3>
	</header>
	<div class="row">
		<div class="col-sm-6 col-sm-offset-3 well" style="box-shadow: 10px 10px 5px #888888;">
			<div class="panel panel-primary">
				<div class="panel panel-heading">
					<p><strong>Please update the below settings</strong></p>
				</div>
				<div class="panel-body">		
					<form class="form-horizontal" id="loginform" action="" method="POST">
						<?php echo csrf_field(); ?>
						<div class="form-group form-group-sm">
							<label class="col-sm-2 control-label" for="txt_hostname">Host IP</label>
							<div class="col-sm-8">
								<input type="text" id="txt_hostname" name="newhost" placeholder="IP address of host" value="<?php echo e($host); ?>" required class="form-control" autofocus>
							</div>
						</div>
						<div class="form-group form-group-sm">
							<label class="col-sm-2 control-label" for="txt_username">Username</label>
							<div class="col-sm-8">
								<input type="text" id="txt_username" name="newuser" placeholder="Registered Username" value="<?php echo e($user); ?>" required class="form-control" autofocus>
							</div>
						</div>						
						<div class="form-group form-group-sm">
							<label class="col-sm-2 control-label" for="newpass">Password</label>
							<div class="col-sm-8">
								<input type="password" id="newpass" name="newpass" placeholder="Password" value="<?php echo e($pass); ?>" required class="form-control">
							</div>
						</div>
						<div class="form-group form-group-sm">
							<div class="col-sm-2 col-sm-offset-4">
								<button id="btn_update" name="btn_update" type="submit" class="btn btn-primary">&nbsp;Submit</button>
							</div>
							<div class="col-sm-2">
								<button id="btn_cancel" name="btn_cancel" type="close" class="btn btn-success">&nbsp;Cancel</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>