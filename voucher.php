<?php
// Start session FIRST before any HTML output
if ( !isset($_SESSION) ) session_start();
require_once 'pricing_config.php';
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
										$batch_stmt = $DB_con->prepare("SELECT DISTINCT batch_id, limit_uptime, COUNT(*) as count, MIN(created_on) as created 
											FROM hotspot_vouchers 
											WHERE status = 'Active' AND batch_id IS NOT NULL 
											GROUP BY batch_id, limit_uptime 
											ORDER BY created DESC");
										$batch_stmt->execute();
										while ($batch = $batch_stmt->fetch(PDO::FETCH_ASSOC)) {
											$batch_label = $batch['batch_id'] . ' (' . $batch['count'] . ' vouchers, ' . $batch['limit_uptime'] . ')';
											echo '<option value="' . htmlspecialchars($batch['batch_id']) . '">' . htmlspecialchars($batch_label) . '</option>';
										}
										?>
									</select>
								</div>
							</div>
							
							<div class="form-group">
								<div class="col-xs-4">
									<img src="images/shot1.png" width="240" height="100" class="center">
									<button name="voucher1" id="voucher1" class="btn btn-success center-element"  tabindex="1" title="Single Account Per Row(Plain List)">Single Account Per Row(Plain List)</button></a>
								</div>
								<div class="col-xs-4">
									<img src="images/shot2.png" width="240" height="100" class="center">
									<button name="voucher2" id="voucher2" class="btn btn-primary center-element"  tabindex="2" title="2 Accounts per Row(Plain List)">2 Accounts per Row(Plain List)</button>
								</div>
								<div class="col-xs-4">
									<img src="images/shot3.png" width="240" height="100" class="center">
									<button name="voucher3" id="voucher3"  class="btn btn-info center-element" title="Only use with Accounts having same username and password" tabindex="3">3 Accounts per Row(Plain List)</button>
								</div>
							</div>
							<div class="form-group">
								<div class="col-xs-4">
									<img src="images/shot4.png" width="240" height="100" class="center">
									<button name="voucher4" id="voucher4" class="btn btn-danger center-element" tabindex="4" title="2 Rows for Single Account(Plain List)">2 Rows for Single Account(Plain List)</button>
								</div>
								<div class="col-xs-4">
									<img src="images/shot5.png" width="240" height="100" class="center">
									<button name="voucher5" id="voucher5" class="btn btn-warning center-element" tabindex="5" title="Single Voucher/row - ID Card Format, Suitable for printing on envelope type sheets">Single Voucher/row - ID Card Format</button>
								</div>
								<div class="col-xs-4">
									<img src="images/shot6.png" width="240" height="100" class="center">
									<button name="voucher6" id="voucher6" class="btn btn-primary center-element" tabindex="5" title="3 Vouchers/row - ID Card Format, Suitable for printing on A4/similar size Sheets">3 Vouchers/row - ID Card Format</button>
								</div>
							</div>
							<div class="form-group">
								<div class="col-xs-6">
									<div class="panel panel-success">
										<div class="panel-heading text-center"><strong><i class="fa fa-coffee"></i> Café Style with Price</strong></div>
										<div class="panel-body text-center">
											<button name="voucher7" id="voucher7" class="btn btn-success btn-lg center-element" tabindex="6" title="Professional café voucher cards with price display">
												<i class="fa fa-coffee"></i> Café Voucher Cards
											</button>
										</div>
									</div>
								</div>
								<div class="col-xs-6">
									<div class="panel panel-info">
										<div class="panel-heading text-center"><strong><i class="fa fa-qrcode"></i> With QR Codes</strong></div>
										<div class="panel-body text-center">
											<button name="voucher8" id="voucher8" class="btn btn-info btn-lg center-element" tabindex="7" title="Café vouchers with QR codes for easy scanning">
												<i class="fa fa-qrcode"></i> QR Code Vouchers
											</button>
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
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.$row['user_name'].'</td>';
							echo '<td>Password: '.$row['password'].'</td>';
							echo '<td>Uptime Limit: '.$row['limit_uptime'].'</td>';
							echo '<td>Usage Limit: '.$limit_bytes.'</td>';
							echo '<td>Bandwidth Profile: '.$row['profile'].'</td>';
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
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.$row['user_name'].'</td>';
							echo '<td>Password: '.$row['password'].'</td>';
							echo '<td></td>';
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>Username: '.$row['user_name'].'</td>';
								echo '<td>Password: '.$row['password'].'</td>';
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
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
						echo '<tr>';
							echo '<td>'.$sn.'</td>';
							echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>ID & Psd: '.$row['user_name'].'</td>';
							echo '<td></td>';
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>ID & Psd: '.$row['user_name'].'</td>';
								echo '<td></td>';
							}	
							if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
								$sn += 1;
								$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
								echo '<td>'.$sn.'</td>';
								echo '<td><img src="images/success.png" width="50px" height="50px"></td>';
								echo '<td>ID & Psd: '.$row['user_name'].'</td>';
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
						$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
						echo '<tr>';
							echo '<td rowspan="2">'.$sn.'</td>';
							echo '<td rowspan="2"><img src="images/success.png" width="50px" height="50px"></td>';
							echo '<td>Username: '.$row['user_name'].'</td>';
							echo '<td>Password: '.$row['password'].'</td>';
							echo '<td><strong>Counting Starts from 1st Login</strong></td>';
							echo '</tr>';
							echo '<tr>';
							echo '<td>Uptime Limit: '.$row['limit_uptime'].'</td>';
							echo '<td>Usage Limit: '.$limit_bytes.'</td>';
							echo '<td>Bandwidth Profile: '.$row['profile'].'</td>';
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
		$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
		?>
		<div class="row">
			<div class="col-sm-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<ul class="list-group list-group-flush">
							<li class="list-group-item">ID: <?php echo $sn.' ['.$row['uid'].']'; ?></li>
							<li class="list-group-item">User Name: <?php echo $row['user_name']; ?></li>
							<li class="list-group-item">Password : <?php echo $row['password']; ?></li>
							<li class="list-group-item">Uptime Limit : <?php echo $row['limit_uptime']; ?></li>
							<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
							<li class="list-group-item">Bandwidth Profile  : <?php echo $row['profile']; ?></li>
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
		$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
		?>
		<div class="row">
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.$row['uid'].']'; ?></li>
								<li class="list-group-item">User Name: <?php echo $row['user_name']; ?></li>
								<li class="list-group-item">Password : <?php echo $row['password']; ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo $row['limit_uptime']; ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo $row['profile']; ?></li>
							</ul>
						</div>
					</div>	
				</div>
			</div>		
			<?php if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$sn += 1;
			$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
			?>
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.$row['uid'].']'; ?></li>
								<li class="list-group-item">User Name: <?php echo $row['user_name']; ?></li>
								<li class="list-group-item">Password : <?php echo $row['password']; ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo $row['limit_uptime']; ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo $row['profile']; ?></li>
							</ul>
						</div>
					</div>	
				</div>
			</div>
			<?php } ?>
			<?php  if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$sn += 1;
			$limit_bytes = ($row['limit_bytes'] == 0) ? 'None' : $row['limit_bytes'].' Gb';
			?>			
			<div class="col-xs-4">
				<div class="card card-inverse">
					<img class="card-img-top" src="images/logo.png" style="background-origin: content-box; background-size: cover; border-radius: 10px 10px 0px 0px;" alt="Card image/Logo">
					<div class="card-img-overlay">
						<h6 class="card-title text-center">WIFI HOTSPOT</h6>
						<div class="card-block">
							<ul class="list-group list-group-flush">
								<li class="list-group-item">ID: <?php echo $sn.' ['.$row['uid'].']'; ?></li>
								<li class="list-group-item">User Name: <?php echo $row['user_name']; ?></li>
								<li class="list-group-item">Password : <?php echo $row['password']; ?></li>
								<li class="list-group-item">Uptime Limit : <?php echo $row['limit_uptime']; ?></li>
								<li class="list-group-item">Usage Limit  : <?php echo $limit_bytes; ?></li>
								<li class="list-group-item">Bandwidth Profile  : <?php echo $row['profile']; ?></li>
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
		$price = isset($row['price']) ? $row['price'] : getVoucherPrice($row['limit_uptime']);
		$validity = getUptimeName($row['limit_uptime']);
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
					<div class="value"><?php echo $row['user_name']; ?></div>
				</div>
				<div class="cafe-voucher-credentials">
					<label>Password</label>
					<div class="value"><?php echo $row['password']; ?></div>
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
		$price = isset($row['price']) ? $row['price'] : getVoucherPrice($row['limit_uptime']);
		$validity = getUptimeName($row['limit_uptime']);
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
						<div class="value"><?php echo $row['user_name']; ?></div>
					</div>
					<div class="qr-voucher-credentials">
						<label>Password</label>
						<div class="value"><?php echo $row['password']; ?></div>
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
