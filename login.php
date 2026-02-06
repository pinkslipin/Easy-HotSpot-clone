<?php 
// Start session FIRST before any HTML output
require_once 'security_helper.php';
secure_session_start();
// Suppress deprecation warnings for PHP 8.x compatibility
error_reporting(E_ALL & ~E_DEPRECATED);
?>
<?php include('header.php'); ?>
<div class="container">
	<header>
		<h1 style="text-align:center;">MINDSPACE</h1>	
		<h2 style="text-align:center;">System Management Utility</h2>
		<h3 style="text-align:center;">By pnkslp</h3>
	</header>
	<div class="row">
		<div class="col-sm-6 col-sm-offset-3 well" style="box-shadow: 10px 10px 5px #888888;">
			<div class="panel panel-primary">
				<div class="panel panel-heading">
					<p><strong>Login using Registered Credentials</strong></p>
				</div>
				<div class="panel-body">		
					<form class="form-horizontal" id="loginform" action="" method="POST">
						<?php echo csrf_field(); ?>
						<div class="form-group form-group-sm">
							<label class="col-sm-2 control-label" for="txt_user_name">Username</label>
							<div class="col-sm-8">
								<input type="text" id="txt_user_name" name="username" placeholder="Registered Username" required class="form-control" autofocus>
							</div>
						</div>
						<div class="form-group form-group-sm">
							<label class="col-sm-2 control-label" for="txt_password">Password</label>
							<div class="col-sm-8">
								<input type="password" id="password" name="password" placeholder="Password" placeholder="Password" required class="form-control">
							</div>
						</div>
						<div class="form-group form-group-sm">
							<div class="col-sm-2 col-sm-offset-4">
								<button id="btn_login" name="btn_login" type="submit" class="btn btn-primary">&nbsp;Submit</button>
							</div>
							<div class="col-sm-2">
								<button id="btn_cancel" name="btn_cancel" type="reset" class="btn btn-success">&nbsp;Cancel</button>
							</div>
						</div>
					</form>
					<?php
					if (isset($_POST['btn_login'])){
						$username = trim($_POST['username']);
						$password = $_POST['password'];
						include('dbconfig.php');
						
						// SECURITY: Rate limiting - block after 5 failed attempts
						$clientIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
						$rateCheck = check_login_rate_limit($clientIP);
						if (!$rateCheck['allowed']) {
							echo '<script>cmodal("Account Locked", "' . e($rateCheck['message']) . '", "error", "index.php")</script>';
						} else {
						
						try {
							$stmt = $DB_con->prepare("SELECT user_id FROM hotspot_users WHERE 1");
							$stmt->execute(array());
						}
						catch(PDOException $e) {
							try {
								include('database.php');
								$stmt = $DB_con->prepare("SELECT user_id FROM hotspot_users WHERE 1");
								$stmt->execute(array());
							}
							catch(PDOException $e) {
								echo '<script>cmodal("Error", "Database connection failed. Please try again.", "error")</script>';
							}
						}
						
						$count = $stmt->rowCount();
						if( $count == 0 ) {
							// SECURITY: Auto-create admin with bcrypt hash (not SHA-1)
							$defaultHash = secure_password_hash('admin');
							$stmt = $DB_con->prepare("insert into hotspot_users (date_added, firstname, username, password, user_level, status, user_group, created_at)
								values(CURDATE(), 'Administrator', :username, :password, :level, 'Active', 1, NOW())");
							$stmt->execute(array(':username' => 'admin', ':password' => $defaultHash, ':level' => 1));
						}
						
						try {
							// SECURITY: Fetch by username only, verify password in PHP (supports bcrypt + SHA-1)
							$stmt = $DB_con->prepare("SELECT * FROM hotspot_users WHERE username=:username AND status=:status");
							$stmt->execute(array(':username' => $username, ':status' => 'Active'));
							$row = $stmt->fetch(PDO::FETCH_ASSOC);
						}
						catch(PDOException $e) {
							$row = false;
						}
	
						if ($row && secure_password_verify($password, $row['password'])) {
							// SECURITY: Auto-upgrade SHA-1 hashes to bcrypt on successful login
							if (password_needs_rehash_check($row['password'])) {
								$newHash = secure_password_hash($password);
								$upd = $DB_con->prepare("UPDATE hotspot_users SET password = :hash WHERE user_id = :id");
								$upd->execute([':hash' => $newHash, ':id' => $row['user_id']]);
							}
							
							// SECURITY: Regenerate session ID to prevent session fixation
							secure_session_regenerate();
							
							$_SESSION['id']=$row['user_id'];
							$_SESSION['username']=e($row['firstname']).' '.e($row['lastname']);
							$_SESSION['user_level']= (int)$row['user_level'];
							
							// Log successful login
							require_once 'audit_log.php';
							auditLog('login', 'User logged in successfully');
							
							echo '<script language="javascript">window.location.href ="index.php";</script>';
						}
						else
							{
							// Log failed login attempt (sanitize username for XSS)
							require_once 'audit_log.php';
							auditLog('login_failed', 'Failed login attempt for username: ' . e($username), 'anonymous');
							
							echo '<script>cmodal("Access Denied!", "No Active User account with the given Username/Password Combination!", "error", "index.php")</script>';
						}
						} // end rate limit check
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>