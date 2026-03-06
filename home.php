<?php 
require_once 'pricing_config.php'; 
// packages_config.php is now loaded via pricing_config.php
?>
<style>
/* Clean Dashboard Styles */
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.dashboard-header h1 {
    margin: 0 0 5px 0;
    font-size: 28px;
    font-weight: 700;
}
.dashboard-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}
.header-actions {
    margin-top: 15px;
}
.header-actions .btn {
    margin: 3px;
    border-radius: 20px;
    padding: 8px 18px;
    font-weight: 600;
    border: 2px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.15);
    color: white;
}
.header-actions .btn:hover {
    background: rgba(255,255,255,0.25);
    color: white;
}
.header-actions .btn-logout {
    background: rgba(255,67,46,0.8);
    border-color: rgba(255,67,46,0.5);
}

.action-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}
.action-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}
.action-card h4 {
    color: #333;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
    font-size: 16px;
}
.action-card h4 i {
    margin-right: 10px;
    opacity: 0.7;
}

.action-btn {
    display: inline-block;
    padding: 12px 20px;
    margin: 5px;
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    color: white;
    text-decoration: none;
}
.action-btn i {
    margin-right: 8px;
}

.btn-blue { background: linear-gradient(135deg, #28ABE3, #1a8fc2); }
.btn-green { background: linear-gradient(135deg, #72bf48, #5da03a); }
.btn-red { background: linear-gradient(135deg, #FF432E, #d63520); }
.btn-purple { background: linear-gradient(135deg, #800080, #660066); }
.btn-orange { background: linear-gradient(135deg, #FF6B35, #e55a2b); }
.btn-gold { background: linear-gradient(135deg, #FFD700, #e6c200); color: #333 !important; }
.btn-navy { background: linear-gradient(135deg, #000080, #000066); }
.btn-teal { background: linear-gradient(135deg, #20c997, #1aa179); }

.quick-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.stat-box {
    flex: 1;
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}
.stat-box .stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #667eea;
}
.stat-box .stat-label {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
}

@media (max-width: 768px) {
    .quick-stats { flex-direction: column; }
    .action-btn { display: block; margin: 8px 0; }
}
</style>
<body>
	<div class="container" style="padding-top: 30px;">
	<div class="no_print">
        
        <!-- Dashboard Header -->
        <div class="dashboard-header text-center">
            <h1><i class="fa fa-wifi"></i> MindSpace</h1>
            <p>WiFi Hotspot Voucher Management System</p>
            <div class="header-actions">
                <a href="dashboard.php" class="btn"><i class="fa fa-line-chart"></i> Dashboard</a>
                <a href="seats.php" class="btn"><i class="fa fa-th-large"></i> Seat Map</a>
                <a href="voucher.php" class="btn"><i class="fa fa-print"></i> Print Vouchers</a>
                <a href="portal.php" class="btn"><i class="fa fa-paint-brush"></i> Customize Portal</a>
                <button onclick="log_out()" class="btn btn-logout"><i class="fa fa-sign-out"></i> Logout (<?php echo htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin User', ENT_QUOTES, 'UTF-8'); ?>)</button>
            </div>
        </div>
		
        <!-- Quick Stats Row -->
        <?php
        require_once 'expiry_check.php';
        $stats = getVoucherStats();
        ?>
        <div class="quick-stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active Vouchers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #72bf48;"><?php echo $stats['used']; ?></div>
                <div class="stat-label">Used (Sold)</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #FF432E;"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">Expired</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #FFD700;"><?php echo formatPrice($stats['total_revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Main Action Cards -->
        <div class="row">
            <!-- Voucher Management -->
            <div class="col-md-6">
                <div class="action-card">
                    <h4><i class="fa fa-ticket"></i> Voucher Management</h4>
                    <a href="#single-user" data-toggle="modal" class="action-btn btn-blue">
                        <i class="fa fa-user-plus"></i> Create Single
                    </a>
                    <a href="#multi-user" data-toggle="modal" class="action-btn btn-green">
                        <i class="fa fa-users"></i> Create Batch
                    </a>
                    <a href="voucher.php" class="action-btn btn-purple">
                        <i class="fa fa-print"></i> Print Vouchers
                    </a>
                    <a href="#batch-manager" data-toggle="modal" class="action-btn btn-navy">
                        <i class="fa fa-th-list"></i> Manage Batches
                    </a>
                </div>
            </div>
            
            <!-- User Monitoring -->
            <div class="col-md-6">
                <div class="action-card">
                    <h4><i class="fa fa-users"></i> User Monitoring</h4>
                    <a href="#active-users" data-toggle="modal" class="action-btn btn-teal">
                        <i class="fa fa-wifi"></i> Active Now
                    </a>
                    <a href="#list-users" data-toggle="modal" class="action-btn btn-blue">
                        <i class="fa fa-list"></i> All Users
                    </a>
                    <a href="#remove-expired" data-toggle="modal" class="action-btn btn-orange">
                        <i class="fa fa-calendar-times-o"></i> Expired
                    </a>
                    <a href="#remove-selected" data-toggle="modal" class="action-btn btn-red">
                        <i class="fa fa-trash"></i> Remove Users
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- System & Settings -->
            <div class="col-md-6">
                <div class="action-card">
                    <h4><i class="fa fa-cogs"></i> System & Settings</h4>
                    <a href="#profiler" data-toggle="modal" class="action-btn btn-gold">
                        <i class="fa fa-sliders"></i> Bandwidth Profiles
                    </a>
                    <a href="#system-user" data-toggle="modal" class="action-btn btn-purple">
                        <i class="fa fa-user-secret"></i> System Users
                    </a>
                    <a href="#server-log" data-toggle="modal" class="action-btn btn-navy">
                        <i class="fa fa-list-alt"></i> Server Logs
                    </a>
                    <a href="index.php" class="action-btn btn-blue">
                        <i class="fa fa-refresh"></i> Refresh
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="col-md-6">
                <div class="action-card">
                    <h4><i class="fa fa-star"></i> Quick Links</h4>
                    <a href="dashboard.php" class="action-btn btn-green">
                        <i class="fa fa-line-chart"></i> Sales Dashboard
                    </a>
                    <a href="seats.php" class="action-btn btn-blue">
                        <i class="fa fa-th-large"></i> Seat Map
                    </a>
                    <a href="portal.php" class="action-btn btn-teal">
                        <i class="fa fa-paint-brush"></i> Captive Portal
                    </a>
                    <a href="#remove-uninitiated" data-toggle="modal" class="action-btn btn-orange">
                        <i class="fa fa-clock-o"></i> Unused Vouchers
                    </a>
                </div>
            </div>
        </div>
        
	</div>	
	
    <!-- End Main Body Section -->

		<!-- 1. End Single Guest User Creation Experiment Section -->
		<div class="child-modal modal fade" id="single-user" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
                    <div class="no_print">
						<div class="row">
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<div class="panel panel-primary">
									<div class="panel-heading"><h3 class="text-center">Single User Creation</h3></div>
									<div class="panel-body">
										<div class="form-horizontal">
											<div class="form-group form-group-sm">
												<div class="col-sm-6">
													<label class="col-sm-4 control-label" >User Name</label>
													<div class="col-sm-8">
														<input type="text" class="form-control" placeholder="Required Username *" name="uname" id="uname" required >
													</div>
												</div>
												<div class="col-sm-6">
													<label class="col-sm-4 control-label" >Password</label>
													<div class="col-sm-8">
														<input type="text" class="form-control" placeholder="Required Password *" name="psw" id="psw" required>
													</div>
												</div>
											</div>
											<div class="form-group form-group-sm">
												<div class="col-sm-6">						
													<label class="col-sm-4 control-label" >Package</label>
													<div class="col-sm-8">
														<select class="myCombo form-control" id="spackage_id" name="spackage_id" required>
															<?php 
															$grouped = getPackagesGroupedByCategory();
															foreach ($grouped as $catId => $catData): 
															?>
																<optgroup label="<?php echo htmlspecialchars($catData['name']); ?>">
																<?php foreach ($catData['packages'] as $pkg): 
																	$displayName = $pkg['name'];
																	if ($pkg['type'] === 'window' && isset($pkg['window_description'])) {
																		$displayName .= ' (' . $pkg['window_description'] . ')';
																	}
																	$selected = ($pkg['id'] === 'ind_1h') ? 'selected' : '';
																?>
																	<option value="<?php echo htmlspecialchars($pkg['id']); ?>" <?php echo $selected; ?>>
																		<?php echo htmlspecialchars($displayName) . ' - ' . formatPrice($pkg['price']); ?>
																	</option>
																<?php endforeach; ?>
																</optgroup>
															<?php endforeach; ?>
														</select>
													</div>
												</div>
												<div class="col-sm-6">						
													<label class="col-sm-4 control-label" >Bandwidth Profile</label>
													<div class="col-sm-8">
														<?php
														$util->setMenu('/ip hotspot user profile');
														echo '<select class="myCombo form-control" id="sprofile" name="sprofile" required>';
														foreach ($util->getAll() as $item) {
															echo '<option>', $item->getProperty('name'), '</option>';
														}
														echo '</select>'; ?>
													</div>
												</div>
											</div>
											<div class="form-group form-group-sm">
												<div class="col-sm-6">						
													<label class="col-sm-4 control-label" for="slimit_bytes">Data Limit (GB)</label>
													<div class="col-sm-8">
														<input type="number" class="form-control" title="Maximum usable data in GB, 0 for unlimited" name="slimit_bytes" id="slimit_bytes" min="0" value="0" required >
													</div>
												</div>
												<div class="col-sm-6 text-right" style="padding-top: 5px;">
													<button type="button" name="issuing" id="issuing" onClick="ajaxSingle()" class="btn btn-success" tabindex="5"><i class="fa fa-check"></i> Issue</button>
													<button type="button" class="btn btn-warning" data-dismiss="modal"><i class="fa fa-arrow-left"></i> BACK</button>
												</div>
											</div>
										</div>
									</div>	
								</div>
							</div>
						</div>
					</div>
					<div id="single"></div>
				</div>
			</div>
		</div>	
        <!-- 1. End Single Guest User Creation Experiment Section -->
		
		<!-- 2. Start Multi Guest User Creation Section -->
		<div class="child-modal modal fade" id="multi-user" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
                    <div class="no_print">
						<div class="row">
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<div class="panel panel-primary">
									<div class="panel-heading"><h3 class="text-center">Create Multiple Users</h3></div>
									<div class="panel-body">
										<div class="form-horizontal">
											<div class="form-group form-group-sm">
												<div class="col-sm-4">
													<label class="col-sm-6 control-label" for="no_of_users">How many Users</label>
													<div class="col-sm-6">
														<input type="number" min="2" max="150" id="no_of_users" name="no_of_users" value="2" autofocus >
													</div>
												</div>
												<div class="col-sm-4">
													<label class="col-sm-6 control-label" for="user_prefix">User name Prefix</label>
													<div class="col-sm-6">
														<input type="text" id="user_prefix" name="user_prefix">
													</div>
												</div>
												<div class="col-sm-4">						
													<label class="col-sm-6 control-label" for="pass_length">Password length</label>
													<div class="col-sm-6">
														<input type="number" min="4" max="10" id="pass_length" name="pass_length" value="5">
													</div>
												</div>
											</div>	
											<div class="form-group form-group-sm">
												<div class="col-sm-4">
													<label class="col-sm-6 control-label" for="package_id">Package</label>
													<div class="col-sm-6">
														<select class="myCombo" id="package_id" name="package_id" required>
															<?php 
															$grouped = getPackagesGroupedByCategory();
															foreach ($grouped as $catId => $catData): 
															?>
																<optgroup label="<?php echo htmlspecialchars($catData['name']); ?>">
																<?php foreach ($catData['packages'] as $pkg): 
																	$displayName = $pkg['name'];
																	if ($pkg['type'] === 'window' && isset($pkg['window_description'])) {
																		$displayName .= ' (' . $pkg['window_description'] . ')';
																	}
																	$selected = ($pkg['id'] === 'ind_1h') ? 'selected' : '';
																?>
																	<option value="<?php echo htmlspecialchars($pkg['id']); ?>" <?php echo $selected; ?>>
																		<?php echo htmlspecialchars($displayName) . ' - ' . formatPrice($pkg['price']); ?>
																	</option>
																<?php endforeach; ?>
																</optgroup>
															<?php endforeach; ?>
														</select>
													</div>
												</div>
												<div class="col-sm-4">						
													<label class="col-sm-6 control-label" for="profile">Bandwidth (Mbps) Profile</label>
													<div class="col-sm-6">
														<?php
														$util->setMenu('/ip hotspot user profile');
														echo '<select class="myCombo" id="profile" name="profile">';
														foreach ($util->getAll() as $item) {
															echo '<option>', $item->getProperty('name'), '</option>';
														}
														echo '</select>'; ?>
													</div>
												</div>
												<div class="col-sm-4">						
													<label class="col-sm-6 control-label">Username & Password</label>
													<div class="col-sm-6">
														<select class="myCombo" id="same_pass" name="same_pass">
															<option value="1">Same</option>									
															<option value="2">Different</option>
														</select>
													</div>
												</div>
											</div>	
											<div class="form-group form-group-sm">
												<div class="col-sm-4">
												<label class="col-sm-6 control-label" for="limit_bytes">Maximum Usage Limit(GB), 0 for NO Limit</label>
													<div class="col-sm-6">
														<input type="number" placeholder="Maximum usable data in GB" name="limit_bytes" id="limit_bytes" min="0" value="0" required >
													</div>
													<!--<label class="col-sm-6 control-label" for="limit_bytes">Maximum Usage Limit(GB)</label>
													<div class="col-sm-6">
														<select class="myCombo" id="limit_bytes" name="limit_bytes">
															<option value="0">NONE</option>									
															<option value="1">1 GB</option>
															<option value="5">5 GB</option>
															<option value="10">10 GB</option>
															<option value="20">20 GB</option>
															<option value="50">50 GB</option>
														</select>
													</div> -->
												</div>
												<div class="col-sm-4">						
													<label class="col-sm-6 control-label" for="pass_type">Password Type</label>
													<div class="col-sm-6">
														<select class="myCombo" id="pass_type" name="pass_type" value="sn" required>
															<option value="sn">abcd1234</option>
															<option value="s">abcd</option>
															<option value="c">DCBA</option>
															<option value="sc">BaCd</option>
															<option value="cn">1342ABCD</option>
															<option value="scn">1423aBcD</option>
															<option value="n">1234</option>															
														</select>
													</div>
												</div>													
												<div class="col-sm-2">
													<div class="pull-right">
														<button name="missuing" id="missuing" onClick="ajaxMultiple()" class="btn btn-success">&nbsp; Issue</button>
													</div>
												</div>
												<div class="col-sm-2">
													<div class="pull-left">
														<button data-dismiss="modal" class="btn btn-warning"><i class="icon-save icon-large"></i>&nbsp; BACK </button>
													</div>
												</div>
											</div>	
										</div>
									</div>	
								</div>
							</div>
						</div>
					</div>
					<div id="multiple"></div>					
				</div>
			</div>
		</div>	

        <!-- 2. End Multi Guest User Creation Section -->
		
		<!-- 3. Start List All Inactive Users Section -->
        <div class="child-modal modal fade" id="list-users" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>	
                    <div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
						<?php $util->setMenu('/ip hotspot user'); ?>
						<table cellpadding="0" cellspacing="0" border="0" class="table  table-bordered" id="table-01">
							<div class="alert alert-info">
								<strong><i class="icon-user icon-large"></i><h3 class="text-center">Users Not active at the moment</h3></strong>
							</div>
							<thead>
								<tr>
									<th>#</th>
									<th>User</th>
									<th>Profile</th>
									<th>Bytes In</th>
									<th>Bytes Out</th>
									<th>Total Permitted Usage</th>
									<th>Time Used</th>
									<th>Validity Limit</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$i = 0;
								foreach ($util->getAll() as $item) {
									$i++;
									if ($item->getProperty('limit-bytes-total')) {
										$limit_bytes_total = $item->getProperty('limit-bytes-total').' Bytes';
									}
									else { $limit_bytes_total = 'Unlimited'; }
									
									if ($item->getProperty('limit-uptime')) {
										$limit_uptime = $item->getProperty('limit-uptime');
									}
									else { $limit_uptime = 'Not Limited'; }
									if ($item->getProperty('uptime') == "0s") {
										echo '<tr>';
											echo '<td>'.$i.'</td>';
											echo '<td>', $item->getProperty('name'),'</td>';
											echo '<td>', $item->getProperty('profile'), '</td>';
											echo '<td>', $item->getProperty('bytes-in'), '</td>';
											echo '<td>', $item->getProperty('bytes-out'), '</td>';
											echo '<td>', $limit_bytes_total, '</td>';
											echo '<td>', $item->getProperty('uptime'), '</td>';
											echo '<td>', $limit_uptime, '</td>';
										echo '</tr>';
									}
								}?>
							</tbody>
						</table>
                    </div>
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
                </div>
            </div>
        </div>
        <!-- 3. End List All Inactive Users Section -->

		<!-- 4. Start List Active Users Section -->
        <div class="child-modal modal fade" id="active-users" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
                    <div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
						<div id="active-users-content">
							<div class="text-center" style="padding:40px;">
								<i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading active users...
							</div>
						</div>
                    </div>
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
                </div>
            </div>
        </div>
        <!-- 4. End List Active Users Section -->

		<!-- 4b. Extend User Time Modal -->
		<div class="modal fade" id="extend-user-modal" tabindex="-1" role="dialog" aria-labelledby="extendModalLabel">
			<div class="modal-dialog modal-sm" role="document">
				<div class="modal-content">
					<div class="modal-header" style="background:#f0ad4e;">
						<button type="button" class="close" data-dismiss="modal">&times;</button>
						<h4 class="modal-title" id="extendModalLabel"><i class="fa fa-clock-o"></i> Extend Time for <span id="extend-username-display"></span></h4>
					</div>
					<div class="modal-body">
						<input type="hidden" id="extend-username-value" value="">
						<div class="form-group">
							<label>Add Time:</label>
							<select id="extend-minutes" class="form-control">
								<option value="30">+30 Minutes</option>
								<option value="60" selected>+1 Hour</option>
								<option value="120">+2 Hours</option>
								<option value="180">+3 Hours</option>
								<option value="300">+5 Hours</option>
								<option value="360">+6 Hours</option>
							</select>
						</div>
						<p class="text-muted" style="font-size:12px;"><i class="fa fa-info-circle"></i> Time is added to the user's remaining limit. They will NOT be disconnected.</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-warning" onclick="submitExtend()"><i class="fa fa-check"></i> Extend Time</button>
					</div>
				</div>
			</div>
		</div>
		<!-- 4b. End Extend User Time Modal -->

		<!-- 5. Start Remove Selected users Section -->
        <div class="child-modal modal fade" id="remove-selected" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
					<form id="checkboxForm" class="form-horizontal">
						<div class="form-group">
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<?php $util->setMenu('/ip hotspot user'); ?>
								<table cellpadding="0" cellspacing="0" border="0" class="table  table-bordered" id="table-01">
									<div class="alert alert-info">
										<strong><i class="icon-user icon-large"></i><h3 class="text-center">List of users accounts</h3></strong>
									</div>
									<thead>
										<tr>
											<th>#</th>
											<th>User</th>
											<th>Profile</th>
											<th>Bytes In</th>
											<th>Bytes Out</th>
											<th>Total Permitted Usage</th>
											<th>Time Used</th>
											<th>Validity Limit</th>
											<?php if($_SESSION['user_level'] <= 2) { //Administrator/Unit Head Only
												echo '<th>Remove</th>';
											} ?>
										</tr>
									</thead>
									<tbody>
										<?php
										$i = 0;
										foreach ($util->getAll() as $item) {
											$i++;
											if ($item->getProperty('limit-bytes-total')) {
												$limit_bytes_total = $item->getProperty('limit-bytes-total').' Bytes';
											}
											else { $limit_bytes_total = 'Unlimited'; }
										
											if ($item->getProperty('limit-uptime')) {
												$limit_uptime = $item->getProperty('limit-uptime');
											}
											else { $limit_uptime = 'Not Limited'; }
										echo '<tr>';
											echo '<td>'.$i.'</td>';
											$rid = $item->getProperty('name');
											echo '<td>', $rid,'</td>';
											echo '<td>', $item->getProperty('profile'), '</td>';
											echo '<td>', $item->getProperty('bytes-in'), '</td>';
											echo '<td>', $item->getProperty('bytes-out'), '</td>';
											echo '<td>', $limit_bytes_total, '</td>';
											echo '<td>', $item->getProperty('uptime'), '</td>';
											echo '<td>', $limit_uptime, '</td>';
											if($_SESSION['user_level'] <= 2) { //Administrator/Unit Head Only
												echo '<td>';
												$chktrue = "";
												if (!empty($item->getProperty('limit-uptime'))) {
													if (!($item->getProperty('uptime') < $item->getProperty('limit-uptime'))) {
														$chktrue = "checked";
													}
												}
												//if (!($item->getProperty('uptime') < $item->getProperty('limit-uptime'))) $chktrue = "checked"; else $chktrue = "";
												echo '<label for="'.$rid.'"></label>
													<input type="checkbox" name="removal_list[]" value="'.$rid.'" id="'.$rid.'" '.$chktrue.' class="styled" /> &nbsp;&nbsp;&nbsp; ';
													echo '<a title="Delete the Guest User Account" id="id'.$i.'"  href="#delete'.$item->getProperty('name').'" data-toggle="modal"  class="btn btn-danger btn-lg"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></a>&nbsp;&nbsp;';
													include('modal_delete_guest.php'); ?>
													<?php
												echo '</td>';
											}
										echo '</tr>';
										} ?>
									</tbody>
								</table>
								<!--<div class="col-sm-2 col-sm-offset-3">
									<button name="removal" id="removeall" data-dismiss="modal" onClick="removeAllSelected(this.form);" class="btn btn-danger"><i class="icon-save icon-large"></i></a>&nbsp;Remove All</button>&nbsp;&nbsp;&nbsp;
								</div>
								<div class="col-sm-2"> -->
								<div class="col-sm-2 col-sm-offset-4">
									<button name="removal" id="removal" data-dismiss="modal" onClick="removeSelected(this.form);" class="btn btn-success"><i class="icon-save icon-large"></i></a>&nbsp;Remove Selected</button>&nbsp;&nbsp;&nbsp;
								</div>	
								<div class="col-sm-2">
									<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
        <!-- 5. End Remove Selected users Section -->		

		<!-- 6. Start Remove All Expired Guest Users Section -->
        <div class="child-modal modal fade" id="remove-expired" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
                    <div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
						<table cellpadding="0" cellspacing="0" border="0" class="table  table-bordered" id="table-01">
							<div class="alert alert-info">
								<strong><i class="icon-user icon-large"></i><h3 class="text-center">Validity expired users available in System</h3></strong>
							</div>
							<thead>
								<tr>
									<th>#</th>
									<th>Server</th>
									<th>User</th>
									<th>Profile</th>
									<th>Limit Uptime</th>
									<th>Uptime</th>
									<th>Limit Bytes Total</th>
									<th>Bytes In</th>
									<th>Bytes Out</th>
								</tr>
							</thead>
							<tbody>
								<?php
								try
									{
									require_once 'config.php';
									
									if (defined('MOCK_MODE') && MOCK_MODE === true) {
										// Mock mode - no real expired users to show
										require_once 'mock_router.php';
										echo '<tr><td colspan="9" class="text-center">Mock mode: No expired users tracking available</td></tr>';
									} else {
										// Real router mode - reuse existing $util connection from index.php
										// (opening a second connection per page load is wasteful and unnecessary)
										$util->setMenu('/ip/hotspot/user');
										$items = $util->getAll('.id,server,name,profile,limit-uptime,limit-bytes-total,uptime,bytes-in,bytes-out');

										// Parse RouterOS time to seconds for correct comparison.
										// String comparison is wrong: '30m' > '1h' because '3' > '1'.
										$parseRosDisplay = function($t) {
											if (empty($t)) return 0;
											$s = 0;
											if (preg_match('/^(\d+):(\d+):(\d+)$/', $t, $m)) return (int)$m[1]*3600+(int)$m[2]*60+(int)$m[3];
											if (preg_match('/(\d+)w/', $t, $m)) $s += (int)$m[1]*604800;
											if (preg_match('/(\d+)d/', $t, $m)) $s += (int)$m[1]*86400;
											if (preg_match('/(\d+)h/', $t, $m)) $s += (int)$m[1]*3600;
											if (preg_match('/(\d+)m/', $t, $m)) $s += (int)$m[1]*60;
											if (preg_match('/(\d+)s/', $t, $m)) $s += (int)$m[1];
											return $s;
										};

										$i = 0;
										foreach ($items as $item) {
											$limitUptime = $item->getProperty('limit-uptime');
											$uptime      = $item->getProperty('uptime');

											if (!empty($limitUptime) && $parseRosDisplay($limitUptime) > 0
												&& $parseRosDisplay($uptime) >= $parseRosDisplay($limitUptime)) {
												$i++;
												echo '<tr>';
													echo '<td>'.$i.'</td>';
													echo '<td>', $item->getProperty('server'),'</td>';
													echo '<td>', $item->getProperty('name'), '</td>';
													echo '<td>', $item->getProperty('profile'), '</td>';
													echo '<td>', $item->getProperty('limit-uptime'), '</td>';
													echo '<td>', $item->getProperty('uptime'),'</td>';
													echo '<td>', $item->getProperty('limit-bytes-total'), '</td>';
													echo '<td>', $item->getProperty('bytes-in'), '</td>';
													echo '<td>', $item->getProperty('bytes-out'), '</td>';
												echo '</tr>';
											}
										}

										if ($i === 0) {
											echo '<tr><td colspan="9" class="text-center">No expired users found</td></tr>';
										}
									}
								}
								catch (Exception $e) {
									echo '<script>cmodal("Access Denied!", "Error accessing validity expired users.", "error", "index.php")</script>';
								}
								?>
							</tbody>
						</table>
                    </div>
					<div class="col-sm-3 col-sm-offset-5">
						<button name="uissuing" id="uissuing" onClick="ajaxExpired();" class="btn btn-success"><i class="icon-save icon-large"></i></a>&nbsp;Remove All</button>&nbsp;&nbsp;&nbsp;
						<button data-dismiss="modal" class="btn btn-info" ><i class="icon-save icon-large"></i></a>&nbsp;BACK</button>
					</div>
                </div>
            </div>
        </div>
        <!-- 6. Start Remove All Expired Guest Users Section -->		
	
		<!-- 7. Start Server Log Section -->
        <div class="child-modal modal fade" id="server-log" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
                    <div class="row">
						<div class="col-sm-4 col-sm-offset-4 text-center" style="margin-bottom: 15px;">
							<button data-dismiss="modal" class="btn btn-info"><i class="fa fa-arrow-left"></i>&nbsp;BACK</button>
							<?php if($_SESSION['user_level'] == 1): ?>
							<button onclick="clearServerLogs();" class="btn btn-danger"><i class="fa fa-trash"></i>&nbsp;Clear Logs</button>
							<?php endif; ?>
						</div>
						<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
							<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="table-01">
								<div class="alert alert-info">
									<strong><i class="fa fa-list-alt"></i><h3 class="text-center">Activity Log</h3></strong>
								</div>
								<thead>
									<tr>
										<th>#</th>
										<th>Time</th>
										<th>Action</th>
										<th>Details</th>
									</tr>
								</thead>
								<tbody id="server-log-tbody">
									<?php
									require_once 'config.php';
									require_once 'audit_log.php';
									$i = 0;
									
									// Always show local audit logs (these can be cleared)
									$logs = getAuditLog(1000);
									if (empty($logs)) {
										echo '<tr><td colspan="4" class="text-center">No log entries found</td></tr>';
									} else {
										foreach ($logs as $entry) {
											$i++;
											echo '<tr>';
											echo '<td>'.$i.'</td>';
											echo '<td>'.date('Y-m-d H:i:s', strtotime($entry['created_at'])).'</td>';
											echo '<td><span class="label label-info">'.$entry['action'].'</span></td>';
											echo '<td>'.$entry['details'].' <em>(by '.$entry['username'].')</em></td>';
											echo '</tr>';
										}
									}
									?>
								</tbody>
							</table>
						</div>
						<div class="col-sm-4 col-sm-offset-4 text-center">
							<button data-dismiss="modal" class="btn btn-info"><i class="fa fa-arrow-left"></i>&nbsp;BACK</button>
						</div>						
					</div>
				<!--</div>-->
				</div>
			</div>
		</div>
        <!-- 7. End Server Log Section -->		

		<!-- 9. Start System User Management Section -->
		<div class="child-modal modal fade" id="system-user" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl"></div>
                    </div>
                </div>
                <div class="container">
                    <div class="no_print">
						<div class="row">
							<div class="col-sm-4 col-sm-offset-4">
								<a href="#change-password" data-toggle="modal" class="btn btn-primary btn-lg center-element"><span class="glyphicon glyphicon-edit" aria-hidden="true"></span>Change My Password</a>&nbsp;&nbsp;
							</div>
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="table-01">
									<div class="alert alert-info">
										<strong><i class="icon-user icon-large"></i><h3 class="text-center">System Users managing Activities</h3></strong>
									</div>
									<thead>
										<tr>
											<th>Username</th>
											<th>Password</th>                                 
											<th>Firstname</th>                                 
											<th>Lastname</th>
											<th>Level</th>
											<?php if($_SESSION['user_level'] == 1) { //Administrator Only
												echo '<th>Actions</th>';
											} ?>	
										</tr>
									</thead>
									<tbody>
										<?php 
										$stmt = $DB_con->prepare("SELECT * FROM hotspot_users WHERE 1");
										$stmt->execute(array());
										while($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
											$id=$row['user_id'];
											if ($row['status'] == 'Active') { echo '<tr class="alert-info">'; } else { echo '<tr class="alert-danger">'; }
											?>
												<td><?php echo $row['username']; ?></td> 
												<td><?php echo '..............'; ?></td> 
												<td><?php echo $row['firstname']; ?></td> 
												<td><?php echo $row['lastname']; ?></td>
												<?php 
												switch ($row['user_level']) {
													case 1 :
														echo '<td>Administrator</td>';
														break;
													case 2 :
														echo '<td>Unit Head</td>';
														break;
													case 3 :
														echo '<td>System User</td>';
														break;
												} 
												if($_SESSION['user_level'] == 1) { //Administrator Only
													echo '<td>';
														echo '<a title="Get Details of User & More Actions" id="'.$id.'" data-id="'.$id.'" name="'.$id.'"  href="#getUserModal" data-toggle="modal" class="btn btn-primary btn-lg"><i class="fa fa-caret-square-o-down" aria-hidden="true"></i></a>&nbsp;&nbsp;';
													echo '</td>';
												} ?>
											</tr>
											<?php
										} ?>
									</tbody>
								</table>
							</div>
							<div class="col-sm-2 col-sm-offset-5">
								<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>	
		<!-- 9. End System User Management Section -->		
		
		<!-- 10. Start Remove All Un-Initiated Guest Users Section -->
        <div class="child-modal modal fade" id="remove-uninitiated" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl">
                        </div>
                    </div>
                </div>
                <div class="container">
					<div class="col-sm-2 col-sm-offset-5">
						<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
					</div>
                    <div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
						<?php $util->setMenu('/ip hotspot user'); ?>
						<table cellpadding="0" cellspacing="0" border="0" class="table  table-bordered" id="table-01">
							<div class="alert alert-info">
								<strong><i class="icon-user icon-large"></i><h3 class="text-center">User accounts not yet initiated any activities, ie. accounts inactive at the moment</h3></strong>
							</div>
							<thead>
								<tr>
									<th>#</th>
									<th>Server</th>
									<th>User</th>
									<th>Profile</th>
									<th>Uptime Limit</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$i = 0;
								foreach ($util->getAll() as $item) {
									if ($item->getProperty('uptime') == 0) {
									$i++;										
										echo '<tr>';
											echo '<td>'.$i.'</td>';
											echo '<td>', $item->getProperty('server'),'</td>';
											echo '<td>', $item->getProperty('name'),'</td>';
											echo '<td>', $item->getProperty('profile'),'</td>';
											echo '<td>', $item->getProperty('limit-uptime'), '</td>';
										echo '</tr>';
									}
								} ?>
							</tbody>
						</table>
                    </div>
					<div class="col-sm-3 col-sm-offset-5">
						<?php if($_SESSION['user_level'] <= 2) {
							echo '<button name="uissuing" id="uissuing" onClick="ajaxUninitiated();" class="btn btn-success"><i class="icon-save icon-large"></i></a>&nbsp;Remove All</button>&nbsp;&nbsp;&nbsp;';
						} ?>	
						<button data-dismiss="modal" class="btn btn-info" ><i class="icon-save icon-large"></i></a>&nbsp;CANCEL</button>
					</div>
                </div>
            </div>
        </div>
        <!-- 10. Start Remove All Un-Initiated Guest Users Section -->
		
		<!-- 11. Start HotSpot User Profiles Management Section -->
		<div class="child-modal modal fade" id="profiler" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl"></div>
                    </div>
                </div>
                <div class="container">
                    <div class="no_print">
						<div class="row">
							<div class="col-sm-2 col-sm-offset-5">
								<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
							</div>
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<?php $util->setMenu('/ip hotspot user profile'); ?>								
								<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="table-01">
									<div class="alert alert-info">
										<strong><i class="icon-user icon-large"></i><h3 class="text-center">HotSpot User Profiles Available</h3></strong>
									</div>
									<thead>
										<tr>
											<th>#</th>
											<th>Name</th>
											<th>Session Timeout</th>                                 
											<th>Keepalive Timeout</th>
											<th>Shared Users</th>
											<th>Rate Limit(Rx/Tx)</th>
											<th>MAC Cookie Timeout</th>
											<?php if($_SESSION['user_level'] == 1) { //Administrator Only
												echo '<th>Actions</th>';
											} ?>	
										</tr>
									</thead>
									<tbody>
										<?php
										$i = 0;
										foreach ($util->getAll() as $item) {
											$i++;										
											echo '<tr>';
												echo '<td>'.$i.'</td>';
												echo '<td>', $item->getProperty('name'),'</td>';
												echo '<td>', $item->getProperty('session-timeout'), '</td>';
												echo '<td>', $item->getProperty('keepalive-timeout'), '</td>';
												echo '<td>', $item->getProperty('shared-users'), '</td>';
												echo '<td>', $item->getProperty('rate-limit'), '</td>';
												echo '<td>', $item->getProperty('mac-cookie-timeout'), '</td>';

												if($_SESSION['user_level'] == 1) { //Administrator Only
													echo '<td>';
													echo '<a title="Get Details of the Profile & More Actions" id="'.$id.'" data-id="'.$item->getProperty('name').'" name="'.$id.'"  href="#getProfileModal" data-toggle="modal" class="btn btn-primary btn-lg"><i class="fa fa-caret-square-o-down" aria-hidden="true"></i></a>';
												echo '</td>';
												} ?>
											</tr>
											<?php
										} ?>
									</tbody>
								</table>
							</div>
							<div class="col-sm-2 col-sm-offset-5">
								<button data-dismiss="modal" class="btn btn-info center-element" ><i class="icon-save icon-large"></i>&nbsp;BACK</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- 11. End HotSpot User Profiles Management Section -->
		
		<!-- 12. Start Batch Manager Modal -->
		<div class="child-modal modal fade" id="batch-manager" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-content">
                <div class="close-modal" data-dismiss="modal">
                    <div class="lr">
                        <div class="rl"></div>
                    </div>
                </div>
                <div class="container">
                    <div class="no_print">
						<div class="row">
							<div class="col-sm-4 col-sm-offset-4 text-center" style="margin-bottom: 15px;">
								<button data-dismiss="modal" class="btn btn-info"><i class="fa fa-arrow-left"></i>&nbsp;BACK</button>
								<a href="dashboard.php" class="btn btn-success"><i class="fa fa-line-chart"></i>&nbsp;Dashboard</a>
							</div>
							<div class="col-sm-12 col-md-12 thumbnail" style="box-shadow: 10px 10px 5px #888888;">
								<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered" id="table-01">
									<div class="alert alert-info">
										<strong><i class="fa fa-th-list"></i><h3 class="text-center">Batch Manager - Manage Voucher Batches</h3></strong>
									</div>
									<thead>
										<tr>
											<th>Batch ID</th>
											<th>Time Tier</th>
											<th>Total</th>
											<th>Active</th>
											<th>Used</th>
											<th>Revenue</th>
											<th>Created</th>
											<?php if($_SESSION['user_level'] <= 2): ?>
											<th>Actions</th>
											<?php endif; ?>
										</tr>
									</thead>
									<tbody>
										<?php 
										include('dbconfig.php');
										$stmt = $DB_con->prepare("SELECT 
											batch_id,
											limit_uptime,
											COUNT(*) as total,
											COUNT(CASE WHEN status = 'Active' THEN 1 END) as active,
											COUNT(CASE WHEN status = 'Used' OR status = 'Over' THEN 1 END) as used,
											COALESCE(SUM(price), 0) as revenue,
											MIN(created_on) as created
											FROM hotspot_vouchers 
											WHERE batch_id IS NOT NULL
											GROUP BY batch_id, limit_uptime
											ORDER BY created DESC");
										$stmt->execute();
										
										if ($stmt->rowCount() == 0) {
											echo '<tr><td colspan="8" class="text-center">No batches found. Create vouchers using "Add Multiple Users".</td></tr>';
										} else {
											while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
												echo '<tr>';
												echo '<td><strong>'.htmlspecialchars($row['batch_id']).'</strong></td>';
												echo '<td>'.getUptimeName($row['limit_uptime']).'</td>';
												echo '<td>'.$row['total'].'</td>';
												echo '<td style="color: #72bf48;"><strong>'.$row['active'].'</strong></td>';
												echo '<td style="color: #28ABE3;">'.$row['used'].'</td>';
												echo '<td><strong>'.formatPrice($row['revenue']).'</strong></td>';
												echo '<td>'.date('M d, Y', strtotime($row['created'])).'</td>';
												if($_SESSION['user_level'] <= 2) {
													echo '<td>';
													echo '<a href="voucher.php" class="btn btn-sm btn-primary" title="Print this batch"><i class="fa fa-print"></i></a> ';
													echo '<button onclick="deleteBatch(\''.htmlspecialchars($row['batch_id'], ENT_QUOTES).'\')" class="btn btn-sm btn-danger" title="Delete entire batch"><i class="fa fa-trash"></i></button>';
													echo '</td>';
												}
												echo '</tr>';
											}
										}
										?>
									</tbody>
								</table>
							</div>
							<div class="col-sm-4 col-sm-offset-4 text-center">
								<button data-dismiss="modal" class="btn btn-info"><i class="fa fa-arrow-left"></i>&nbsp;BACK</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- 12. End Batch Manager Modal -->
	</div>

<script>
function clearServerLogs() {
    if (!confirm('Are you sure you want to clear ALL activity logs? This cannot be undone.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax_clear_logs.php',
        type: 'POST',
        data: { csrf_token: CSRF_TOKEN },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Clear the table visually
                $('#server-log-tbody').html('<tr><td colspan="4" class="text-center">Logs cleared successfully!</td></tr>');
                alert('Activity logs cleared!');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to clear logs: ' + error);
        }
    });
}

// ── Extend User Time ──────────────────────────────────────────────────────
function openExtendModal(username) {
    $('#extend-username-display').text(username);
    $('#extend-username-value').val(username);
    $('#extend-minutes').val('60'); // default 1 hour
    $('#extend-user-modal').modal('show');
}

function submitExtend() {
    var username = $('#extend-username-value').val();
    var mins     = $('#extend-minutes').val();

    $.ajax({
        url: 'ajax_extend_user.php',
        type: 'POST',
        data: { csrf_token: CSRF_TOKEN, username: username, extend_minutes: mins },
        dataType: 'json',
        success: function(res) {
            $('#extend-user-modal').modal('hide');
            if (res.success) {
                cmodal('Time Extended', res.message, 'success');
                // Refresh the active users table to show updated time
                loadActiveUsers();
            } else {
                cmodal('Error', res.message, 'error');
            }
        },
        error: function() {
            $('#extend-user-modal').modal('hide');
            cmodal('Error', 'Failed to contact router. Please try again.', 'error');
        }
    });
}

// ── Live Active Users Table ───────────────────────────────────────────────
var activeUsersTimer = null;

function loadActiveUsers() {
    $.ajax({
        url: 'ajax_active_users.php',
        type: 'POST',
        data: { csrf_token: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            if (!res.success) {
                $('#active-users-content').html(
                    '<div class="alert alert-danger">Failed to load: ' + (res.message || 'Unknown error') + '</div>'
                );
                return;
            }
            var users = res.users;
            var html = '<div class="alert alert-info">' +
                '<strong><i class="icon-user icon-large"></i>' +
                '<h3 class="text-center">List of Users Active at the moment' +
                ' <small style="font-size:12px;color:#666;">(' + users.length + ' online &bull; refreshes every 30s)</small></h3></strong></div>' +
                '<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered">' +
                '<thead><tr>' +
                '<th>#</th><th>Server</th><th>Domain</th><th>User</th>' +
                '<th>IP Address</th><th>Session Uptime</th><th>Voucher Time Left</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            if (users.length === 0) {
                html += '<tr><td colspan="8" class="text-center">No active users at the moment.</td></tr>';
            } else {
                for (var i = 0; i < users.length; i++) {
                    var u = users[i];
                    var vStyle = u.expired ? 'color:red' : (u.voucherLeft === 'Unlimited' ? 'color:gray' : '');
                    html += '<tr>' +
                        '<td>' + (i+1) + '</td>' +
                        '<td>' + escHtml(u.server)  + '</td>' +
                        '<td>' + escHtml(u.domain)  + '</td>' +
                        '<td>' + escHtml(u.user)    + '</td>' +
                        '<td>' + escHtml(u.address)  + '</td>' +
                        '<td>' + escHtml(u.sessionUp) + '</td>' +
                        '<td><strong style="' + vStyle + '">' + escHtml(u.voucherLeft) + '</strong></td>' +
                        '<td>' +
                        '<button class="btn btn-xs btn-warning" onclick="openExtendModal(\'' + escHtml(u.user) + '\')"><i class="fa fa-clock-o"></i> Extend</button> ' +
                        '<button class="btn btn-xs btn-info"    onclick="resetUserSession(\'' + escHtml(u.user) + '\')"><i class="fa fa-refresh"></i> Reset</button> ' +
                        '<button class="btn btn-xs btn-danger"  onclick="kickActiveUser(\'' + escHtml(u.user) + '\')"><i class="fa fa-trash"></i> Remove</button>' +
                        '</td>' +
                        '</tr>';
                }
            }
            html += '</tbody></table>';
            $('#active-users-content').html(html);
        },
        error: function() {
            $('#active-users-content').html(
                '<div class="alert alert-danger">Could not reach the server. Check your connection.</div>'
            );
        }
    });
}

function escHtml(str) {
    if (!str) return '';
    return $('<span/>').text(str).html();
}

function kickActiveUser(username) {
    if (!confirm('REMOVE "' + username + '"?\n\nThis permanently deletes the user account and marks the voucher as used. The credentials will no longer work.\n\nUse "Reset" instead if you just want to disconnect the device and let the correct user log in.')) return;
    $.ajax({
        url    : 'ajax_rem_user.php',
        method : 'POST',
        data   : { csrf_token: CSRF_TOKEN, username: username },
        success: function() {
            loadActiveUsers();
        },
        error: function() {
            alert('Network error — could not remove user.');
        }
    });
}

function resetUserSession(username) {
    if (!confirm('Reset session for "' + username + '"?\n\nThis will:\n• Disconnect the device immediately\n• Clear the MAC address binding\n• Redirect the device to the login portal\n\nThe voucher stays valid — the correct user can log in with the same credentials.')) return;
    $.ajax({
        url    : 'ajax_kick_session.php',
        method : 'POST',
        data   : { csrf_token: CSRF_TOKEN, username: username },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                loadActiveUsers();
            } else {
                alert('Error: ' + (res.message || 'Reset failed.'));
            }
        },
        error: function() {
            alert('Network error — could not reset session.');
        }
    });
}

// Load on modal open, start 30s refresh; stop on close
// NOTE: Use 'show/hide.bs.modal' instead of 'shown/hidden.bs.modal' because
// these child-modals have no .modal-dialog wrapper, so the Bootstrap 3 fade
// transition callback (which fires 'shown') never executes.
$('#active-users').on('show.bs.modal', function() {
    loadActiveUsers();
    activeUsersTimer = setInterval(loadActiveUsers, 30000);
});
$('#active-users').on('hide.bs.modal', function() {
    if (activeUsersTimer) { clearInterval(activeUsersTimer); activeUsersTimer = null; }
});
</script>
		
</body>
<?php
include('modal_change_pass.php');
include('modal_delete_guest.php');
include('modal_get_profiles.php');
include('modal_get_user.php');
?>
