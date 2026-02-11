<?php
// Start session FIRST before any HTML output
require_once 'security_helper.php';
secure_session_start();
require_once 'pricing_config.php';
// packages_config.php is now loaded via pricing_config.php

/**
 * Get display name for a voucher row
 * Prefers package_name, falls back to limit_uptime
 */
function getVoucherDisplayName($row) {
	// First try package_name (stored with voucher)
	if (!empty($row['package_name'])) {
		return $row['package_name'];
	}
	// Then try package_id lookup
	if (!empty($row['package_id'])) {
		return getPackageDisplayName($row['package_id']);
	}
	// Fall back to limit_uptime for old vouchers
	if (!empty($row['limit_uptime'])) {
		return getUptimeName($row['limit_uptime']);
	}
	return 'Unknown';
}

/**
 * Get display price for a voucher row
 */
function getVoucherDisplayPrice($row) {
	// First try stored price
	if (isset($row['price']) && $row['price'] > 0) {
		return $row['price'];
	}
	// Then try package_id lookup
	if (!empty($row['package_id'])) {
		return getPackagePrice($row['package_id']);
	}
	// Fall back to limit_uptime lookup for old vouchers
	if (!empty($row['limit_uptime'])) {
		return getVoucherPrice($row['limit_uptime']);
	}
	return 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php
include ('header.php');
?>
<body>
	<div class="container" style="margin-top:50px;">
		<div class="no_print">
		<div class="row">
			<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
				<div class="panel panel-primary">
					<div class="panel-heading"><h3 class="text-center">HotSpot User Voucher Printing</h3></div>
					<div class="panel-body">
						<form class="form-horizontal" method="post">
							<div class="alert alert-info text-center"><strong>Select a batch to print vouchers</strong></div>
							
							<!-- Batch Selector -->
							<div class="form-group">
								<label class="col-sm-3 control-label">Select Batch:</label>
								<div class="col-sm-6">
									<select name="batch_selector" id="batch_selector" class="form-control" required>
										<option value="">-- Select a Batch --</option>
										<option value="ALL">All Active Vouchers</option>
										<?php
										include('dbconfig.php');
										$batch_stmt = $DB_con->prepare("SELECT DISTINCT batch_id, limit_uptime, package_id, package_name, COUNT(*) as count, MIN(created_on) as created 
											FROM hotspot_vouchers 
											WHERE status = 'Active' AND batch_id IS NOT NULL 
											GROUP BY batch_id, limit_uptime, package_id, package_name 
											ORDER BY created DESC");
										$batch_stmt->execute();
										while ($batch = $batch_stmt->fetch(PDO::FETCH_ASSOC)) {
											// Display package name if available, otherwise limit_uptime
											$pkg_display = !empty($batch['package_name']) ? $batch['package_name'] : 
												(!empty($batch['package_id']) ? getPackageDisplayName($batch['package_id']) : $batch['limit_uptime']);
											$batch_label = $batch['batch_id'] . ' (' . $batch['count'] . ' vouchers, ' . $pkg_display . ')';
											echo '<option value="' . htmlspecialchars($batch['batch_id']) . '">' . htmlspecialchars($batch_label) . '</option>';
										}
										?>
									</select>
								</div>
							</div>
							
							<!-- PRIMARY VOUCHER STYLES -->
							<div class="row" style="margin-bottom: 20px;">
								<div class="col-xs-6">
									<div class="panel panel-success">
										<div class="panel-heading text-center"><strong><i class="fa fa-coffee"></i> Café Style with Price</strong></div>
										<div class="panel-body text-center">
											<p style="color: #666; margin-bottom: 15px;">Professional cards showing package name, price, and validity</p>
											<button name="voucher7" id="voucher7" class="btn btn-success btn-lg center-element" tabindex="1" title="Professional café voucher cards with price display">
												<i class="fa fa-coffee"></i> Café Voucher Cards
											</button>
										</div>
									</div>
								</div>
								<div class="col-xs-6">
									<div class="panel panel-info">
										<div class="panel-heading text-center"><strong><i class="fa fa-qrcode"></i> With QR Codes</strong></div>
										<div class="panel-body text-center">
											<p style="color: #666; margin-bottom: 15px;">Scannable QR codes for easy credential sharing</p>
											<button name="voucher8" id="voucher8" class="btn btn-info btn-lg center-element" tabindex="2" title="Café vouchers with QR codes for easy scanning">
												<i class="fa fa-qrcode"></i> QR Code Vouchers
											</button>
										</div>
									</div>
								</div>
							</div>
							
							<!-- LEGACY FORMATS (Collapsible) -->
							<div class="panel panel-default">
								<div class="panel-heading" style="cursor: pointer;" data-toggle="collapse" data-target="#legacy-formats">
									<h4 class="panel-title">
										<i class="fa fa-chevron-down"></i> Legacy Plain List Formats (click to expand)
									</h4>
								</div>
								<div id="legacy-formats" class="panel-collapse collapse">
									<div class="panel-body">
										<div class="row">
											<div class="col-xs-4">
												<img src="images/shot1.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher1" id="voucher1" class="btn btn-default btn-sm center-element" tabindex="3">Single Account/Row</button>
											</div>
											<div class="col-xs-4">
												<img src="images/shot2.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher2" id="voucher2" class="btn btn-default btn-sm center-element" tabindex="4">2 Accounts/Row</button>
											</div>
											<div class="col-xs-4">
												<img src="images/shot3.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher3" id="voucher3" class="btn btn-default btn-sm center-element" tabindex="5">3 Accounts/Row</button>
											</div>
										</div>
										<div class="row" style="margin-top: 15px;">
											<div class="col-xs-4">
												<img src="images/shot4.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher4" id="voucher4" class="btn btn-default btn-sm center-element" tabindex="6">2 Rows/Account</button>
											</div>
											<div class="col-xs-4">
												<img src="images/shot5.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher5" id="voucher5" class="btn btn-default btn-sm center-element" tabindex="7">ID Card Single</button>
											</div>
											<div class="col-xs-4">
												<img src="images/shot6.png" width="200" height="80" class="center" style="margin-bottom:10px;">
												<button name="voucher6" id="voucher6" class="btn btn-default btn-sm center-element" tabindex="8">ID Card 3/Row</button>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="form-group">
								<div class="col-xs-4 col-xs-offset-4">
									<button name="submit" type="submit" class="btn btn-success" tabindex="8"><i class="icon-save icon-large"></i>Reset</button>
									<button name="submit" type="submit" class="btn btn-primary" tabindex="9"><i class="icon-save icon-large"></i>Refresh Page</button>
									<a href="index.php" class="btn btn-danger" tabindex="10"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
								</div>	
							</div>							
						</form>
					</div>
				</div>	
			</div>
		</div>
		</div>
<?php
if (isset($_POST['voucher1'])) {
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
	}
else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available meeting the current options, Try Selecting a different period", "error")</script>';
	}
	?>
	<div class="child-modal">
	<div class="row">
		<div class="col-sm-2 col-sm-offset-6"><button onclick="window.print();" class="btn btn-primary"><i class="icon-save icon-large"></i></a>&nbsp;PRINT</button></div>
		<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
			<table cellpadding="0" cellspacing="0" border="1" class="table table-bordered" id="example">
				<caption class="text-center">HOTSPOT USER LIST - <?php echo date('d-m-Y'); ?></caption>
				<div class="alert alert-info">
					<h1 class="text-center"><strong>HotSpot User Voucher</strong></h1>
				</div>
				<thead>
					<tr>
						<th>#</th>
						<th></th>
						<th>Username</th>
						<th>Password</th>
						<th>Limit Uptime</th>
						<th>Limit Bytes</th>
						<th>Bandwidth Profile</th>
						
					</tr>
				</thead>
				<tbody>
					<?php
					$sn = 0;
					while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$sn += 1;
						$id = $row['id'];
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Password: '.htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Uptime Limit: '.htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Usage Limit: '.$limit_bytes.'</td>';
							echo '<td>Bandwidth Profile: '.htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8').'</td>';
						echo '</tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<div class="col-sm-3 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>						
	</div>
	</div>
	<?php
	}
}	?>
<?php 
if (isset($_POST['voucher2'])) {
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
		}
	else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available for Printing, Create Vouchers First from the Main menu, Add Multiple Users", "error")</script>';
	}
	?>
	<div class="child-modal">
	<div class="row">
		<div class="col-sm-2 col-sm-offset-6"><button onclick="window.print();" class="btn btn-primary"><i class="icon-save icon-large"></i></a>&nbsp;PRINT</button></div>
		<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
			<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="example">
				<caption class="text-center">HOTSPOT USER LIST - <?php echo date('d-m-Y'); ?></caption>
				<div class="alert alert-info">
					<h1 class="text-center"><strong>HotSpot User Voucher - 2 in 1 Row(Plain)</strong></h1>
				</div>
				<thead>
					<tr>
						<th>#</th>
						<th></th>
						<th>Username</th>
						<th>Password</th>
						<th></th>
						<th>#</th>
						<th></th>
						<th>Username</th>
						<th>Password</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$sn = 0;
					while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$sn += 1;
						$id = $row['id'];
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Password: '.htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td></td>';
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>Username: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
								echo '<td>Password: '.htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8').'</td>';
							}	
						echo '</tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<div class="col-sm-3 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>						
	</div>
	</div>
	<?php
	}
}
?>
<?php 
if (isset($_POST['voucher3'])) { //3 Units per Line, For Same Username and Password Accounts
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
		}
	else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available for Printing, Create Vouchers First from the Main menu, Add Multiple Users", "error")</script>';
	}
	?>
	<div class="child-modal">
	<div class="row">
		<div class="col-sm-2 col-sm-offset-6"><button onclick="window.print();" class="btn btn-primary"><i class="icon-save icon-large"></i></a>&nbsp;PRINT</button></div>
		<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
			<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="example">
				<caption class="text-center">HOTSPOT USER LIST - <?php echo date('d-m-Y'); ?></caption>
				<div class="alert alert-info">
					<h1 class="text-center"><strong>HotSpot User Voucher - 3 in 1 Row(Plain, For Same Username & Password Accounts)</strong></h1>
				</div>
				<thead>
					<tr>
						<th>#</th>
						<th></th>
						<th>Details</th>
						<th></th>
						<th>#</th>
						<th></th>
						<th>Details</th>
						<th></th>
						<th>#</th>
						<th></th>
						<th>Details</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$sn = 0;
					while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$sn += 1;
						$id = $row['id'];
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>ID & Psd: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td></td>';
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>ID & Psd: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
								echo '<td></td>';
							}	
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>ID & Psd: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
							}	
						echo '</tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<div class="col-sm-3 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>						
	</div>
	</div>
	<?php
	}
}
?>
<?php
if (isset($_POST['voucher4'])) { //1 Account per 2 Rows; Little decorative
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
	}
else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available meeting the current options, Try Selecting a different period", "error")</script>';
	}
	?>
	<div class="child-modal">
	<div class="row">
		<div class="col-sm-2 col-sm-offset-6"><button onclick="window.print();" class="btn btn-primary"><i class="icon-save icon-large"></i></a>&nbsp;PRINT</button></div>
		<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
			<table cellpadding="0" cellspacing="0" border="2" class="table table-bordered" id="table-01">
				<caption class="text-center">HOTSPOT USER LIST - <?php echo date('d-m-Y'); ?></caption>
				<div class="alert alert-info">
					<h1 class="text-center"><strong>HotSpot User Voucher - 1 Account spanning 2 rows</strong></h1>
				</div>
				<thead>
					<tr>
						<th>#</th>
						<th></th>
						<th>Username</th>
						<th>Password</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$sn = 0;
					while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$sn += 1;
						$id = $row['id'];
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
						echo '<tr>';
							echo '<td rowspan="2">'.$sn.'</td>';
							echo '<td rowspan="2"><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Password: '.htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td><strong>Counting Starts from 1st Login</strong></td>';
							echo '</tr>';
							echo '<tr>';
							echo '<td>Uptime Limit: '.htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8').'</td>';
							echo '<td>Usage Limit: '.$limit_bytes.'</td>';
							echo '<td>Bandwidth Profile: '.htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8').'</td>';
						echo '</tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<div class="col-sm-3 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>						
	</div>
	</div>
	<?php
	}
}	?>

<?php
if (isset($_POST['voucher5'])) { //1 Account per Card Format; Little decorative
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
	}
else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available meeting the current options, Try Selecting a different period", "error")</script>';
	}
	echo '<div class="col-sm-6 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>';
		
	$sn = 0;
	echo '<div class="card-deck">';
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sn += 1;
		$id = $row['id'];
		$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
		?>
		<div class="row">
			<div class="col-sm-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<ul class="list-group list-group-flush">
							<li class="list-group-item">ID: <?php echo $sn.' ['.htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8').']'; ?></li>
							<li class="list-group-item">User Name: <?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></li>
							<li class="list-group-item">Password : <?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></li>
							<li class="list-group-item">Uptime Limit : <?php echo htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8'); ?></li>
							<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
							<li class="list-group-item">Bandwidth Profile  : <?php echo htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8'); ?></li>
						</ul>
					</div>
				</div>	
			</div>
		</div>	
		<?php
		}
		?>
		</div>
		<div class="col-sm-6 col-sm-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>						
	</div>
	<?php
	}
}	?>


<?php
if (isset($_POST['voucher6'])) { //Card Format;
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue,  Consult Administrator", "information", "index.php");</script>';
	}
else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","NO entries available meeting the current options, Try Selecting a different period", "error")</script>';
	}
	echo '<div class="col-xs-6 col-xs-offset-5">
			<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
			<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
			<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
		</div>';
		
	$sn = 0;
	echo '<div class="card-deck-wrapper">
	<div class="card-deck">';
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sn += 1;
		$id = $row['id'];
		$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
		?>
		<div class="row">
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8').']'; ?></li>
								<li class="list-group-item">User Name: <?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Password : <?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8'); ?></li>
							</ul>
						</div>
					</div>	
				</div>
			</div>		
			<?php if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$sn += 1;
			$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
			?>
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8').']'; ?></li>
								<li class="list-group-item">User Name: <?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Password : <?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8'); ?></li>
							</ul>
						</div>
					</div>	
				</div>
			</div>
			<?php } ?>
			<?php  if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$sn += 1;
			$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : htmlspecialchars($row['limit_bytes'], ENT_QUOTES, 'UTF-8').' Gb';
			?>			
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8').']'; ?></li>
								<li class="list-group-item">User Name: <?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Password : <?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo htmlspecialchars($row['limit_uptime'], ENT_QUOTES, 'UTF-8'); ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo htmlspecialchars($row['profile'], ENT_QUOTES, 'UTF-8'); ?></li>
							</ul>
						</div>
					</div>	
				</div>
			</div>
		</div>	
		<?php
			}
	}
	echo '</div>';
	echo '</div>';
	?>
	<div class="col-xs-6 col-xs-offset-5">
		<button onclick="window.print();" class="btn btn-danger"><i class="icon-save icon-large"></i>PRINT</button>&nbsp;&nbsp;&nbsp;
		<button name="submit" type="submit" class="btn btn-success" tabindex="6"><i class="icon-save icon-large"></i>Reset</button>&nbsp;&nbsp;&nbsp;
		<a href="index.php" class="btn btn-info" tabindex="7"><i class="icon-arrow-left icon-large"></i>Main Menu</a>
	</div>						
</div>
<?php
}
}

// VOUCHER 7 - Café Style Cards with Price
if (isset($_POST['voucher7'])) {
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue, Consult Administrator", "information", "index.php");</script>';
	}
	else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","No vouchers available. Create vouchers first.", "error")</script>';
	}
	?>
	<style>
		.cafe-voucher {
			width: 280px;
			border: 2px solid #333;
			border-radius: 15px;
			margin: 10px;
			padding: 0;
			display: inline-block;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			font-family: 'Arial', sans-serif;
			box-shadow: 0 4px 6px rgba(0,0,0,0.3);
			page-break-inside: avoid;
		}
		.cafe-voucher-header {
			background: rgba(0,0,0,0.3);
			padding: 12px;
			border-radius: 13px 13px 0 0;
			text-align: center;
		}
		.cafe-voucher-header h3 {
			margin: 0;
			font-size: 18px;
			font-weight: bold;
		}
		.cafe-voucher-header small {
			opacity: 0.8;
			font-size: 11px;
		}
		.cafe-voucher-body {
			padding: 15px;
			background: white;
			color: #333;
		}
		.cafe-voucher-price {
			text-align: center;
			background: #ff6b6b;
			color: white;
			padding: 8px;
			font-size: 24px;
			font-weight: bold;
			margin: -15px -15px 15px -15px;
		}
		.cafe-voucher-credentials {
			background: #f8f9fa;
			border: 2px dashed #ddd;
			border-radius: 8px;
			padding: 12px;
			margin-bottom: 10px;
			text-align: center;
		}
		.cafe-voucher-credentials label {
			display: block;
			font-size: 10px;
			color: #666;
			margin-bottom: 2px;
			text-transform: uppercase;
		}
		.cafe-voucher-credentials .value {
			font-size: 16px;
			font-weight: bold;
			font-family: 'Courier New', monospace;
			color: #333;
			letter-spacing: 1px;
		}
		.cafe-voucher-info {
			font-size: 11px;
			color: #666;
			text-align: center;
			border-top: 1px solid #eee;
			padding-top: 10px;
			margin-top: 10px;
		}
		.cafe-voucher-info span {
			display: inline-block;
			margin: 0 8px;
		}
		.cafe-voucher-footer {
			background: rgba(0,0,0,0.2);
			padding: 8px;
			border-radius: 0 0 13px 13px;
			text-align: center;
			font-size: 10px;
		}
		@media print {
			.cafe-voucher {
				box-shadow: none;
				border: 2px solid #333;
			}
			.no_print { display: none !important; }
		}
	</style>
	
	<div class="no_print" style="text-align: center; margin-bottom: 20px;">
		<button onclick="window.print();" class="btn btn-danger btn-lg"><i class="fa fa-print"></i> PRINT VOUCHERS</button>&nbsp;&nbsp;
		<button name="submit" type="submit" class="btn btn-success"><i class="icon-save icon-large"></i> Reset</button>&nbsp;&nbsp;
		<a href="index.php" class="btn btn-info"><i class="icon-arrow-left icon-large"></i> Main Menu</a>
	</div>
	
	<div style="text-align: center;">
	<?php
	$sn = 0;
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sn++;
		$price = getVoucherDisplayPrice($row);
		$validity = getVoucherDisplayName($row);
		$expires = isset($row['expires_on']) && $row['expires_on'] ? date('M d, Y', strtotime($row['expires_on'])) : 'N/A';
		?>
		<div class="cafe-voucher">
			<div class="cafe-voucher-header">
				<h3><?php echo $CAFE_NAME; ?></h3>
				<small><?php echo $CAFE_TAGLINE; ?></small>
			</div>
			<div class="cafe-voucher-body">
				<div class="cafe-voucher-price">
					<?php echo formatPrice($price); ?>
				</div>
				<div class="cafe-voucher-credentials">
					<label>Username</label>
					<div class="value"><?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div class="cafe-voucher-credentials">
					<label>Password</label>
					<div class="value"><?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div class="cafe-voucher-info">
					<span><strong>⏱ <?php echo $validity; ?></strong></span>
					<span>|</span>
					<span>Valid until: <?php echo $expires; ?></span>
				</div>
			</div>
			<div class="cafe-voucher-footer">
				Connect to WiFi • Open browser • Enter credentials • Enjoy!
			</div>
		</div>
		<?php
	}
	?>
	</div>
	
	<div class="no_print" style="text-align: center; margin-top: 20px;">
		<button onclick="window.print();" class="btn btn-danger btn-lg"><i class="fa fa-print"></i> PRINT VOUCHERS</button>&nbsp;&nbsp;
		<a href="index.php" class="btn btn-info"><i class="icon-arrow-left icon-large"></i> Main Menu</a>
	</div>
<?php
	}
}

// VOUCHER 8 - QR Code Vouchers
if (isset($_POST['voucher8'])) {
	if (!(($_SESSION['user_level'] <= 3) AND ($_SESSION['user_level'] >= 1))) {
		echo '<script>cmodalOkCancel("Access Denied", "User Rights Issue, Consult Administrator", "information", "index.php");</script>';
	}
	else
	{
	include('dbconfig.php');
	$batch_filter = isset($_POST['batch_selector']) ? $_POST['batch_selector'] : '';
	if ($batch_filter === '' || $batch_filter === 'ALL') {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status");
		$stmt->execute(array(':status' => 'Active'));
	} else {
		$stmt = $DB_con->prepare("SELECT * FROM hotspot_vouchers WHERE status = :status AND batch_id = :batch_id");
		$stmt->execute(array(':status' => 'Active', ':batch_id' => $batch_filter));
	}
	if ($stmt->rowCount() == 0) {
		echo '<script>cmodal("No Data Found","No vouchers available. Create vouchers first.", "error")</script>';
	}
	?>
	<style>
		.qr-voucher {
			width: 300px;
			border: 2px solid #333;
			border-radius: 12px;
			margin: 10px;
			padding: 0;
			display: inline-block;
			background: white;
			font-family: 'Arial', sans-serif;
			box-shadow: 0 4px 6px rgba(0,0,0,0.2);
			page-break-inside: avoid;
		}
		.qr-voucher-header {
			background: linear-gradient(135deg, #28ABE3 0%, #1a7bb3 100%);
			padding: 12px;
			border-radius: 10px 10px 0 0;
			text-align: center;
			color: white;
		}
		.qr-voucher-header h3 {
			margin: 0;
			font-size: 16px;
			font-weight: bold;
		}
		.qr-voucher-body {
			padding: 15px;
			display: flex;
			align-items: center;
		}
		.qr-voucher-qr {
			flex: 0 0 100px;
			text-align: center;
			padding-right: 15px;
			border-right: 2px dashed #ddd;
		}
		.qr-voucher-qr img {
			width: 90px;
			height: 90px;
		}
		.qr-voucher-qr small {
			display: block;
			font-size: 9px;
			color: #999;
			margin-top: 5px;
		}
		.qr-voucher-details {
			flex: 1;
			padding-left: 15px;
		}
		.qr-voucher-credentials {
			margin-bottom: 10px;
		}
		.qr-voucher-credentials label {
			display: block;
			font-size: 10px;
			color: #666;
			margin-bottom: 2px;
			text-transform: uppercase;
		}
		.qr-voucher-credentials .value {
			font-size: 14px;
			font-weight: bold;
			font-family: 'Courier New', monospace;
			color: #333;
			background: #f8f9fa;
			padding: 5px 8px;
			border-radius: 4px;
			letter-spacing: 1px;
		}
		.qr-voucher-price {
			text-align: center;
			background: #FF6B35;
			color: white;
			padding: 5px 10px;
			font-size: 18px;
			font-weight: bold;
			border-radius: 15px;
			margin-top: 10px;
		}
		.qr-voucher-footer {
			background: #f8f9fa;
			padding: 8px;
			border-radius: 0 0 10px 10px;
			text-align: center;
			font-size: 10px;
			color: #666;
			border-top: 1px solid #eee;
		}
		.qr-voucher-footer span {
			display: inline-block;
			margin: 0 5px;
		}
		@media print {
			.qr-voucher {
				box-shadow: none;
				border: 2px solid #333;
			}
			.no_print { display: none !important; }
		}
	</style>
	
	<div class="no_print" style="text-align: center; margin-bottom: 20px;">
		<button onclick="window.print();" class="btn btn-danger btn-lg"><i class="fa fa-print"></i> PRINT VOUCHERS</button>&nbsp;&nbsp;
		<button name="submit" type="submit" class="btn btn-success"><i class="icon-save icon-large"></i> Reset</button>&nbsp;&nbsp;
		<a href="index.php" class="btn btn-info"><i class="icon-arrow-left icon-large"></i> Main Menu</a>
	</div>
	
	<div style="text-align: center;">
	<?php
	$sn = 0;
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sn++;
		$price = getVoucherDisplayPrice($row);
		$validity = getVoucherDisplayName($row);
		$expires = isset($row['expires_on']) && $row['expires_on'] ? date('M d', strtotime($row['expires_on'])) : 'N/A';
		$qrUrl = generateQRCode($row['user_name'], $row['password'], 100);
		?>
		<div class="qr-voucher">
			<div class="qr-voucher-header">
				<h3><i class="fa fa-wifi"></i> <?php echo $CAFE_NAME; ?></h3>
			</div>
			<div class="qr-voucher-body">
				<div class="qr-voucher-qr">
					<img src="<?php echo $qrUrl; ?>" alt="QR Code">
					<small>Scan for login info</small>
				</div>
				<div class="qr-voucher-details">
					<div class="qr-voucher-credentials">
						<label>Username</label>
						<div class="value"><?php echo htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="qr-voucher-credentials">
						<label>Password</label>
						<div class="value"><?php echo htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="qr-voucher-price">
						<?php echo formatPrice($price); ?>
					</div>
				</div>
			</div>
			<div class="qr-voucher-footer">
				<span><i class="fa fa-clock-o"></i> <?php echo $validity; ?></span>
				<span>|</span>
				<span><i class="fa fa-calendar"></i> Expires: <?php echo $expires; ?></span>
			</div>
		</div>
		<?php
	}
	?>
	</div>
	
	<div class="no_print" style="text-align: center; margin-top: 20px;">
		<button onclick="window.print();" class="btn btn-danger btn-lg"><i class="fa fa-print"></i> PRINT VOUCHERS</button>&nbsp;&nbsp;
		<a href="index.php" class="btn btn-info"><i class="icon-arrow-left icon-large"></i> Main Menu</a>
	</div>
<?php
	}
}
?>
	</div>
</body>
