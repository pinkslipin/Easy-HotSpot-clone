<?php
/**
 * Single User Voucher Creation
 * 
 * Creates a single hotspot user voucher on the router and in the database.
 * Now supports package-based pricing including time-window passes.
 */

require_once 'config.php';
require_once 'packages_config.php';
require_once 'security_helper.php';

// SECURITY: Require authenticated user
require_auth();
csrf_require();

// Load multi-router support
require_once 'load_balancer.php';

// Initialize router connection (mock or real)
$assigned_router = null; // Track which router we're using

if (defined('MOCK_MODE') && MOCK_MODE === true) {
	require_once 'mock_router.php';
	$util = new MockRouterUtil();
	$client = new MockClient();
	$assigned_router = 'converge'; // Default for mock mode
} else {
	// Use modern RouterOS API library (works with RouterOS 6.43+ and 7.x)
	require_once 'routeros_api.php';
	
	// Phase 3: Load-Balanced User Assignment
	// Randomly assign user to one of the routers
	$load_balancer = new LoadBalancer($DB_con);
	$assigned_router = $load_balancer->getRandomRouter();
	
	// Get configuration for assigned router
	$router_config = getRouterConfig($assigned_router);
	$connection = createRouterConnection($router_config['ip'], $router_config['user'], $router_config['pass'], $router_config['port']);
	
	if (!$connection['success']) {
		echo '<script>cmodalOkCancel("ERROR", "Router connection failed: '.addslashes($connection['error']).'", "error");</script>';
		exit;
	}
	$util = $connection['util'];
	$client = $connection['client'];
}

if (isset($_POST['name'])) $username = trim($_POST['name']);
if (isset($_POST['psd'])) $password = trim($_POST['psd']);

// Package-based voucher creation
$package_id = null;
if (isset($_POST['package_id'])) {
	$package_id = $_POST['package_id'];
}
// Backward compatibility: also accept limit_uptime if package_id is not provided
if (!$package_id && isset($_POST['limit_uptime'])) {
	$legacy_uptime = $_POST['limit_uptime'];
	// Try to find matching package
	foreach (getAllPackages() as $pkg) {
		if ($pkg['type'] === 'duration' && $pkg['limit_uptime'] === $legacy_uptime) {
			$package_id = $pkg['id'];
			break;
		}
	}
	// If no match, use legacy uptime directly
	if (!$package_id) {
		$package_id = 'ind_1h'; // Default fallback
	}
}

if (isset($_POST['limit_bytes'])) $limit_bytes = $_POST['limit_bytes'];
if (isset($_POST['profile'])) $profile = $_POST['profile'];
if (session_status() === PHP_SESSION_NONE) session_start();

// Get package information
$package = getPackage($package_id);
if (!$package) {
	echo '<script>cmodalOkCancel("ERROR", "Invalid package ID: '.htmlspecialchars($package_id).'", "error");</script>';
	exit;
}

$price = $package['price'];
$package_name = getPackageDisplayName($package_id);
$package_type = $package['type'];

// Determine limit_uptime for router
if ($package['type'] === 'duration') {
	$limit_uptime = $package['limit_uptime'];
} else {
	// Window packages don't use traditional limit-uptime
	// We set a long duration and let the on-login script handle time enforcement
	$limit_uptime = '1d'; // Will be managed by profile script
}

$util->setMenu('/ip hotspot user');
$iv = count($util);

if ((!empty($username)) and (!empty($password)) and (!empty($profile))) {
	if (intval($limit_bytes) != 0) {
		$limit_bytes_total = (intval($limit_bytes) * 1024 * 1024 * 1024 );
		$util->add(
			array(
				'name' => "$username",
				'password' => "$password",
				'disabled' => "no",
				'limit-uptime' => "$limit_uptime",
				'limit-bytes-total' => "$limit_bytes_total",
				'profile' => "$profile",
				'comment' => "PKG:$package_id",
			)
		);
	}
	else
		{
			$util->add(
			array(
				'name' => "$username",
				'password' => "$password",
				'disabled' => "no",
				'limit-uptime' => "$limit_uptime",
				'profile' => "$profile",
				'comment' => "PKG:$package_id",
			)
		);
		$limit_bytes = 0; // For Adding it to Local database
	}		

	if ($iv != count($util)) {
		include('dbconfig.php');
		
		$stmt = $DB_con->prepare("SELECT booking_id from hotspot_vouchers ORDER BY booking_id DESC LIMIT 1");
		$stmt->execute(array());
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$booking_id = ($row && isset($row['booking_id'])) ? $row['booking_id'] : 0;
		$booking_id++;
		$uid = $booking_id.'-1-'.date('dmY');
		
		// Generate batch ID based on package ID and timestamp (not uptime)
		$batch_id = strtoupper($package_id) . '-' . date('mdHi');
		
		// Get expiry date from config
		$expires_on = getExpiryDate();
		
		// Store limit_uptime from package (empty for window packages)
		$db_limit_uptime = ($package['type'] === 'duration') ? $package['limit_uptime'] : '';
		
		$stmt = $DB_con->prepare("INSERT INTO hotspot_vouchers (created_on, created_by, creator, user_name, password, printed_times,
			printed_last, status, group_of, booking_id, limit_uptime, limit_bytes, profile, uid, batch_id, price, expires_on,
			package_id, package_name, package_type, assigned_router)
			VALUES(NOW(), :created_by, :creator, :user_name, :password, :printed_times, :printed_last, :status, :group_of, 
			:booking_id, :limit_uptime, :limit_bytes, :profile, :uid, :batch_id, :price, :expires_on,
			:package_id, :package_name, :package_type, :assigned_router)");
		$stmt->execute(array(
			':created_by' => $_SESSION['username'], 
			':creator' => $_SESSION['id'], 
			':user_name' => $username, 
			':password' => $password,
			':printed_times' => 0, 
			':printed_last' => '', 
			':status' => 'Active', 
			':group_of' => 1,
			':booking_id' => $booking_id, 
			':limit_uptime' => $db_limit_uptime, 
			':limit_bytes' => $limit_bytes,
			':profile' => $profile, 
			':uid' => $uid, 
			':batch_id' => $batch_id, 
			':price' => $price, 
			':expires_on' => $expires_on,
			':package_id' => $package_id,
			':package_name' => $package_name,
			':package_type' => $package_type,
			':assigned_router' => $assigned_router
		));	
		
		// Log the voucher creation
		require_once 'audit_log.php';
		auditLog('voucher_create', "Created single voucher for $package_name ($package_id) assigned to $assigned_router");
			
		// here starts Echo String
		$echo_text ='			
			<div class="container">
				<div class="row" style="padding-top:20px;">	
					<div class="col-sm-12 col-md-12">
						<div class="panel panel-primary">
							<div class="panel-heading"><h3 class="text-center">Hotspot User Voucher</h3></div>
							<div class="panel-body">
								<form id="userForm" class="form-horizontal" method="GET" action="" enctype="multipart/form-data">
									<div class="col-sm-12 col-md-12">
										<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="example">
											<thead>
												<tr>
													<th width="10%"></th>
													<th colspan="2">Username</th>                                 
													<th colspan="2">Password</th>
												</tr>
											</thead>
											<tbody>';
											$echo_text .= '<tr>
													<td width="10%" style="margin:0px; border: 0px; padding: 2px;"><img src="images/logo.png" width="250" height="50"></td>
													<td colspan="2"><input type="text" name="username" class="form-control" value="Username : '.$username.'" placeholder="User Name" readonly></td>
													<td colspan="2"><input type="text" name="password" class="form-control" value="Password : '.$password.'" placeholder="Password"  readonly></td>
												</tr>
												<tr>';
												if (intval($limit_bytes) != 0) {
													$echo_text .= '<td colspan="5">Package: '.$package_name.' ('.formatPrice($price).'); Data Limit: '.round($limit_bytes_total/1073741824, 2).' GB; Profile: '.$profile.'</td>';
													}
												else
													{
													$echo_text .= '<td colspan="5">Package: '.$package_name.' ('.formatPrice($price).'); Profile: '.$profile.'</td>';
													}
												$echo_text .= '
												</tr>
											</tbody>
										</table>
									</div>
								</form>
							</div>
						</div>
					</div>
					<div class="col-sm-3 col-sm-offset-5">
						<button onclick="window.print();" class="btn btn-primary" ><i class="icon-save icon-large"></i></a>&nbsp;PRINT</button>&nbsp;&nbsp;
						<button onclick="document.getElementById("single").style.display="none !important;" type="reset" class="btn btn-danger"><i class="icon-save icon-large"></i></a>&nbsp;Reset</button>&nbsp;&nbsp;
						<button data-dismiss="modal" class="btn btn-info" ><i class="icon-save icon-large"></i></a>&nbsp;BACK</button>&nbsp;&nbsp;
					</div>
				</div>
			</div>';
				echo $echo_text;
		}
	else
		{
		echo '<script>cmodalOkCancel("ERROR/DUPLICATE", " Username '.$username.' is not added, Found as Duplicate. Try some other name", "error");</script>';
	}	
}
//End Adding a Guest User
?>