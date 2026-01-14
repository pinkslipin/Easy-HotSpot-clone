<?php
/**
 * Sales Dashboard - Track daily/weekly/monthly sales and voucher statistics
 */
if (!isset($_SESSION)) session_start();
require_once 'dbconfig.php';
require_once 'pricing_config.php';
require_once 'expiry_check.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

/**
 * Get sales data for a specific period
 */
function getSalesData($period = 'today') {
    global $DB_con;
    
    switch ($period) {
        case 'today':
            $where = "DATE(created_on) = CURDATE()";
            break;
        case 'yesterday':
            $where = "DATE(created_on) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $where = "created_on >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where = "created_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'all':
        default:
            $where = "1=1";
            break;
    }
    
    try {
        $stmt = $DB_con->prepare("SELECT 
            COUNT(*) as total_vouchers,
            COALESCE(SUM(price), 0) as total_revenue,
            COUNT(CASE WHEN status = 'Active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'Used' OR status = 'Over' THEN 1 END) as used,
            COUNT(CASE WHEN status = 'Expired' THEN 1 END) as expired
            FROM hotspot_vouchers 
            WHERE $where");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['total_vouchers' => 0, 'total_revenue' => 0, 'active' => 0, 'used' => 0, 'expired' => 0];
    }
}

/**
 * Get sales by time tier
 */
function getSalesByTier($period = 'month') {
    global $DB_con;
    
    switch ($period) {
        case 'today':
            $where = "DATE(created_on) = CURDATE()";
            break;
        case 'week':
            $where = "created_on >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
        default:
            $where = "created_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
    
    try {
        $stmt = $DB_con->prepare("SELECT 
            limit_uptime,
            COUNT(*) as count,
            COALESCE(SUM(price), 0) as revenue
            FROM hotspot_vouchers 
            WHERE $where
            GROUP BY limit_uptime
            ORDER BY count DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get daily sales for chart (last 7 days)
 */
function getDailySales($days = 7) {
    global $DB_con;
    
    try {
        $stmt = $DB_con->prepare("SELECT 
            DATE(created_on) as sale_date,
            COUNT(*) as vouchers,
            COALESCE(SUM(price), 0) as revenue
            FROM hotspot_vouchers 
            WHERE created_on >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_on)
            ORDER BY sale_date ASC");
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get batch statistics
 */
function getBatchStats() {
    global $DB_con;
    
    try {
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
            ORDER BY created DESC
            LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Get current period from request
$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$todayData = getSalesData('today');
$weekData = getSalesData('week');
$monthData = getSalesData('month');
$allData = getSalesData('all');
$tierData = getSalesByTier('month');
$dailySales = getDailySales(7);
$batchStats = getBatchStats();

// Run expiry check
checkExpiredVouchers();
?>
<!DOCTYPE html>
<html lang="en">
<?php include('header.php'); ?>
<style>
/* Dashboard Styles */
.dashboard-container {
    padding: 20px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-left: 4px solid #667eea;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.15);
}

.stat-card.blue { border-left-color: #28ABE3; }
.stat-card.green { border-left-color: #72bf48; }
.stat-card.red { border-left-color: #FF432E; }
.stat-card.purple { border-left-color: #800080; }
.stat-card.gold { border-left-color: #FFD700; }
.stat-card.orange { border-left-color: #FF6B35; }

.stat-card .stat-icon {
    font-size: 48px;
    opacity: 0.3;
    position: absolute;
    right: 20px;
    top: 20px;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.stat-card .stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-card .stat-change {
    font-size: 12px;
    margin-top: 10px;
}

.stat-card .stat-change.positive { color: #72bf48; }
.stat-card .stat-change.negative { color: #FF432E; }

.dashboard-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.dashboard-section h4 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
    font-weight: 600;
}

.tier-badge {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 20px;
    margin: 5px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
}

.tier-badge .tier-count {
    background: rgba(255,255,255,0.3);
    border-radius: 10px;
    padding: 2px 8px;
    margin-left: 5px;
    font-size: 12px;
}

.period-selector {
    margin-bottom: 20px;
}

.period-selector .btn {
    margin-right: 5px;
    border-radius: 20px;
    padding: 8px 20px;
}

.period-selector .btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
}

.batch-table {
    width: 100%;
}

.batch-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px;
    text-align: left;
}

.batch-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.batch-table tr:hover {
    background: #f8f9fa;
}

.progress-mini {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}

.progress-mini .progress-bar {
    height: 100%;
    border-radius: 4px;
}

.daily-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    height: 200px;
    padding: 20px 0;
}

.chart-bar {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    margin: 0 5px;
}

.chart-bar-fill {
    width: 40px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    border-radius: 5px 5px 0 0;
    min-height: 5px;
    transition: height 0.3s ease;
}

.chart-bar-label {
    font-size: 11px;
    color: #666;
    margin-top: 10px;
    text-align: center;
}

.chart-bar-value {
    font-size: 10px;
    color: #333;
    font-weight: 600;
    margin-top: 5px;
}

.nav-button {
    display: inline-block;
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    margin: 5px;
}

.nav-button.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.nav-button.secondary {
    background: #f8f9fa;
    color: #333;
    border: 2px solid #ddd;
}

.nav-button:hover {
    transform: translateY(-2px);
    text-decoration: none;
    color: inherit;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.nav-button.primary:hover {
    color: white;
}

@media print {
    .no_print { display: none !important; }
}
</style>
<body>
<div class="container dashboard-container">
    <!-- Header -->
    <div class="no_print text-center" style="margin-bottom: 30px;">
        <h1 style="color: #333; font-weight: 700;"><i class="fa fa-line-chart"></i> Sales Dashboard</h1>
        <p style="color: #666;">Monitor your café WiFi voucher sales and statistics</p>
        <div style="margin-top: 15px;">
            <a href="index.php" class="nav-button primary"><i class="fa fa-home"></i> Main Menu</a>
            <a href="voucher.php" class="nav-button secondary"><i class="fa fa-print"></i> Print Vouchers</a>
            <button onclick="window.print();" class="nav-button secondary"><i class="fa fa-download"></i> Export Report</button>
        </div>
    </div>
    
    <!-- Today's Overview -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card green" style="position: relative;">
                <i class="fa fa-money stat-icon"></i>
                <div class="stat-value"><?php echo formatPrice($todayData['total_revenue']); ?></div>
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-change positive">
                    <i class="fa fa-ticket"></i> <?php echo $todayData['total_vouchers']; ?> vouchers sold
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card blue" style="position: relative;">
                <i class="fa fa-calendar stat-icon"></i>
                <div class="stat-value"><?php echo formatPrice($weekData['total_revenue']); ?></div>
                <div class="stat-label">This Week</div>
                <div class="stat-change positive">
                    <i class="fa fa-ticket"></i> <?php echo $weekData['total_vouchers']; ?> vouchers
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card purple" style="position: relative;">
                <i class="fa fa-calendar-o stat-icon"></i>
                <div class="stat-value"><?php echo formatPrice($monthData['total_revenue']); ?></div>
                <div class="stat-label">This Month</div>
                <div class="stat-change positive">
                    <i class="fa fa-ticket"></i> <?php echo $monthData['total_vouchers']; ?> vouchers
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card gold" style="position: relative;">
                <i class="fa fa-database stat-icon"></i>
                <div class="stat-value"><?php echo formatPrice($allData['total_revenue']); ?></div>
                <div class="stat-label">All Time</div>
                <div class="stat-change positive">
                    <i class="fa fa-ticket"></i> <?php echo $allData['total_vouchers']; ?> total
                </div>
            </div>
        </div>
    </div>
    
    <!-- Voucher Status Overview -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card" style="position: relative;">
                <i class="fa fa-check-circle stat-icon" style="color: #72bf48;"></i>
                <div class="stat-value" style="color: #72bf48;"><?php echo $allData['active']; ?></div>
                <div class="stat-label">Active Vouchers</div>
                <div class="progress-mini" style="margin-top: 15px;">
                    <div class="progress-bar" style="width: <?php echo $allData['total_vouchers'] > 0 ? ($allData['active'] / $allData['total_vouchers'] * 100) : 0; ?>%; background: #72bf48;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="position: relative;">
                <i class="fa fa-user-check stat-icon" style="color: #28ABE3;"></i>
                <div class="stat-value" style="color: #28ABE3;"><?php echo $allData['used']; ?></div>
                <div class="stat-label">Used Vouchers</div>
                <div class="progress-mini" style="margin-top: 15px;">
                    <div class="progress-bar" style="width: <?php echo $allData['total_vouchers'] > 0 ? ($allData['used'] / $allData['total_vouchers'] * 100) : 0; ?>%; background: #28ABE3;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="position: relative;">
                <i class="fa fa-times-circle stat-icon" style="color: #FF432E;"></i>
                <div class="stat-value" style="color: #FF432E;"><?php echo $allData['expired']; ?></div>
                <div class="stat-label">Expired Vouchers</div>
                <div class="progress-mini" style="margin-top: 15px;">
                    <div class="progress-bar" style="width: <?php echo $allData['total_vouchers'] > 0 ? ($allData['expired'] / $allData['total_vouchers'] * 100) : 0; ?>%; background: #FF432E;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sales by Tier & Daily Chart -->
    <div class="row">
        <div class="col-md-6">
            <div class="dashboard-section">
                <h4><i class="fa fa-pie-chart"></i> Sales by Time Tier (Last 30 Days)</h4>
                <?php if (empty($tierData)): ?>
                    <p class="text-muted text-center">No sales data available</p>
                <?php else: ?>
                    <div style="text-align: center;">
                        <?php foreach ($tierData as $tier): ?>
                            <div class="tier-badge">
                                <?php echo getUptimeName($tier['limit_uptime']); ?>
                                <span class="tier-count"><?php echo $tier['count']; ?> sold</span>
                                <br><small><?php echo formatPrice($tier['revenue']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="dashboard-section">
                <h4><i class="fa fa-bar-chart"></i> Daily Sales (Last 7 Days)</h4>
                <?php if (empty($dailySales)): ?>
                    <p class="text-muted text-center">No sales data available</p>
                <?php else: ?>
                    <?php
                    $maxRevenue = max(array_column($dailySales, 'revenue'));
                    if ($maxRevenue == 0) $maxRevenue = 1;
                    ?>
                    <div class="daily-chart">
                        <?php foreach ($dailySales as $day): ?>
                            <?php $height = ($day['revenue'] / $maxRevenue) * 150; ?>
                            <div class="chart-bar">
                                <div class="chart-bar-fill" style="height: <?php echo max($height, 5); ?>px;"></div>
                                <div class="chart-bar-label"><?php echo date('D', strtotime($day['sale_date'])); ?></div>
                                <div class="chart-bar-value"><?php echo formatPrice($day['revenue']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Batches -->
    <div class="dashboard-section">
        <h4><i class="fa fa-th-list"></i> Recent Batch Performance</h4>
        <?php if (empty($batchStats)): ?>
            <p class="text-muted text-center">No batch data available</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="batch-table">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Time Tier</th>
                            <th>Total</th>
                            <th>Active</th>
                            <th>Used</th>
                            <th>Revenue</th>
                            <th>Created</th>
                            <th>Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batchStats as $batch): ?>
                            <?php $usagePercent = $batch['total'] > 0 ? ($batch['used'] / $batch['total'] * 100) : 0; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($batch['batch_id']); ?></strong></td>
                                <td><?php echo getUptimeName($batch['limit_uptime']); ?></td>
                                <td><?php echo $batch['total']; ?></td>
                                <td><span style="color: #72bf48;"><?php echo $batch['active']; ?></span></td>
                                <td><span style="color: #28ABE3;"><?php echo $batch['used']; ?></span></td>
                                <td><strong><?php echo formatPrice($batch['revenue']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($batch['created'])); ?></td>
                                <td style="width: 150px;">
                                    <div class="progress-mini">
                                        <div class="progress-bar" style="width: <?php echo $usagePercent; ?>%; background: #667eea;"></div>
                                    </div>
                                    <small><?php echo round($usagePercent); ?>% used</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <div class="no_print text-center" style="margin-top: 20px; padding: 20px; color: #666;">
        <p>Dashboard generated on <?php echo date('F d, Y h:i A'); ?></p>
        <a href="index.php" class="nav-button primary"><i class="fa fa-home"></i> Back to Main Menu</a>
    </div>
</div>
</body>
</html>
