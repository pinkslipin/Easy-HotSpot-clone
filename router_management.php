<?php
/**
 * Router Management Dashboard
 *
 * Displays router status, user distribution, recent migrations,
 * and allows manual control of active router and health checks.
 */

require_once 'security_helper.php';
require_once 'config.php';
require_once 'router_manager.php';
require_once 'health_check.php';
require_once 'dbconfig.php';

secure_session_start();
require_auth();

// Check if multi-router tables exist
$tables_exist = true;
$missing_table = null;
try {
    $stmt = $DB_con->query("SHOW TABLES LIKE 'router_status'");
    if (!$stmt->fetch()) {
        $tables_exist = false;
        $missing_table = 'router_status';
    }
} catch (Exception $e) {
    $tables_exist = false;
    $missing_table = 'unknown';
}

$router_manager = new RouterManager($DB_con);
$health_checker = new HealthChecker($DB_con);

// Handle form submissions
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'check_health':
            $health_checker->checkAllRouters();
            $message = 'Health check completed.';
            $message_type = 'success';
            break;

        case 'set_active':
            if (isset($_POST['router_id'])) {
                $router = getRouterConfig($_POST['router_id']);
                if ($router) {
                    $router_manager->setActiveRouter($_POST['router_id']);
                    require_once 'audit_log.php';
                    auditLog('router_change', 'Changed active router to: ' . $_POST['router_id']);
                    $message = 'Active router changed to: ' . htmlspecialchars($router['name']);
                    $message_type = 'success';
                } else {
                    $message = 'Router not found.';
                    $message_type = 'info';
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Management - Multi-Router System</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        body { padding: 24px; background: linear-gradient(135deg, #f2f4f8, #e6eaf2); font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .section-header h1 { margin: 0; font-weight: 700; letter-spacing: 0.2px; }
        .muted-subtext { margin: 4px 0 0 0; color: #6c757d; }
        .action-buttons a { margin-left: 8px; }
        .back-btn { border-radius: 8px; padding: 8px 14px; font-weight: 600; }
        .router-card { background: #ffffff; padding: 20px; margin-bottom: 22px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08); }
        .router-card h2 { margin-top: 0; font-weight: 700; color: #1f2937; }
        .router-status-online { color: #1aaa55; font-weight: bold; }
        .router-status-offline { color: #e55353; font-weight: bold; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 700; box-shadow: 0 6px 12px rgba(0,0,0,0.05); }
        .status-online { background: #e5f7ed; color: #1b7b3c; }
        .status-offline { background: #fde8e8; color: #b42318; }
        .status-active { background: #e7ecff; color: #2d3a8c; }
        .router-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 22px; }
        .info-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #eef2f7; font-size: 14px; }
        .info-label { font-weight: 700; color: #4b5563; }
        .info-value { color: #1f2937; font-weight: 600; }
        .chart-container { margin: 16px 0 8px 0; }
        .user-bar { display: flex; margin: 10px 0; align-items: center; gap: 10px; }
        .user-bar-label { width: 170px; font-weight: 700; color: #4b5563; }
        .user-bar-fill { flex: 1; background: #eef2f7; border-radius: 10px; height: 32px; position: relative; overflow: hidden; }
        .user-bar-progress { height: 100%; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; box-shadow: inset 0 -2px 6px rgba(0,0,0,0.1); }
        .router-converge { background: linear-gradient(90deg, #2563eb, #1d4ed8); }
        .router-globe { background: linear-gradient(90deg, #7c3aed, #5b21b6); }
        .distribution-row { display: flex; align-items: center; gap: 14px; margin: 12px 0; }
        .distribution-label { width: 180px; font-weight: 700; color: #1f2937; }
        .distribution-meter { flex: 1; background: #eef2f7; border-radius: 12px; height: 32px; position: relative; overflow: hidden; }
        .distribution-progress { height: 100%; border-radius: 12px; display: flex; align-items: center; justify-content: flex-end; padding-right: 12px; color: #f8fafc; font-weight: 700; font-size: 13px; box-shadow: inset 0 -2px 6px rgba(0,0,0,0.1); }
        .distribution-meta { min-width: 140px; text-align: right; font-weight: 700; color: #4b5563; }
        .empty-state { padding: 14px; border: 1px dashed #cbd5e1; background: #f8fafc; border-radius: 10px; color: #475569; font-weight: 600; }
        .migration-history { max-height: 320px; overflow-y: auto; }
        .migration-item { padding: 12px; border: 1px solid #eef2f7; border-radius: 10px; margin-bottom: 10px; font-size: 13px; background: #f9fafb; }
        .migration-item:hover { background: #f1f5f9; }
        .alert-message { padding: 15px; margin-bottom: 18px; border-radius: 10px; }
        .alert-success { background: #e7f9ef; border: 1px solid #c3e6cb; color: #0f5132; }
        .alert-info { background: #e6f2ff; border: 1px solid #b6daff; color: #0c4a73; }
        .btn-group { margin-top: 15px; }
        .btn-group button { margin-right: 6px; margin-bottom: 6px; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="container">
        <div class="section-header">
            <div>
                <h1><i class="fa fa-router"></i> Multi-Router Management</h1>
                <p class="muted-subtext">Monitor and control load balancing and failover across multiple routers.</p>
            </div>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-outline-primary back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <?php if (!$tables_exist): ?>
            <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
                <h3 style="color: #ff6b6b; margin-top: 0;"><i class="fa fa-warning"></i> System Not Initialized</h3>
                <p><strong>The multi-router system database tables have not been created yet.</strong></p>
                <p>You must run the initialization script once:</p>
                <p style="text-align: center;">
                    <a href="initialize_routers.php" style="background: #ffc107; color: black; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block;">
                        <i class="fa fa-database"></i> INITIALIZE MULTI-ROUTER SYSTEM
                    </a>
                </p>
                <p style="font-size: 12px; color: #666; margin-bottom: 0;">This will create the necessary database tables and set up router status tracking. After running it, delete initialize_routers.php for security.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert-message alert-<?php echo htmlspecialchars($message_type); ?>">
                <i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Router Status Overview -->
        <div class="router-card">
            <h2><i class="fa fa-heartbeat"></i> Router Status</h2>
            <div class="router-grid">
                <?php
                if (!$tables_exist) {
                    echo '<p style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;"><strong>✗ Database tables not initialized.</strong> Router status information is not available until you run the initialization script above.</p>';
                } else {
                    try {
                        $routers = getAllRouters();
                        $active_router = $router_manager->getActiveRouter();

                        foreach ($routers as $router_id => $config) {
                            $status = $router_manager->getRouterStatus($router_id);
                            $is_online = $status['is_online'] ?? 0;
                            $response_time = $status['last_heartbeat'] ?? 'Never';
                            $user_count = $router_manager->getUserCount($router_id);
                            $border_color = $router_id === 'converge' ? '#007bff' : '#6f42c1';
                ?>
                            <div class="router-card" style="border-left: 4px solid <?php echo $border_color; ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3><?php echo htmlspecialchars($config['name']); ?></h3>
                                    <div>
                                        <span class="status-badge <?php echo $is_online ? 'status-online' : 'status-offline'; ?>">
                                            <?php echo $is_online ? '🟢 Online' : '🔴 Offline'; ?>
                                        </span>
                                        <?php if ($router_id === $active_router): ?>
                                            <span class="status-badge status-active"><i class="fa fa-star"></i> Active</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <span class="info-label">IP Address:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($config['ip']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value">
                                        <span class="<?php echo $is_online ? 'router-status-online' : 'router-status-offline'; ?>">
                                            <?php echo $is_online ? 'Online' : 'Offline'; ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Active Users:</span>
                                    <span class="info-value"><?php echo $user_count; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Last Check:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($response_time); ?></span>
                                </div>

                                <?php if ($router_id !== $active_router): ?>
                                    <form method="POST" style="margin-top: 15px;">
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="router_id" value="<?php echo htmlspecialchars($router_id); ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fa fa-refresh"></i> Make Active
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                <?php
                        }
                    } catch (Exception $e) {
                        echo '<p style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;">'
                           . '<strong>✗ Error loading router status:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- User Distribution -->
        <div class="router-card">
            <h2><i class="fa fa-pie-chart"></i> User Distribution</h2>
            <div class="chart-container">
                <?php
                if (!$tables_exist) {
                    echo '<p style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;">'
                      . 'User distribution data not available until initialization is complete.'
                      . '</p>';
                } else {
                    try {
                        $distribution = $router_manager->getUserDistribution();
                        $total_users = 0;
                        foreach ($distribution as $item) {
                            $total_users += $item['count'];
                        }

                        $has_users = $total_users > 0;

                        if (!$has_users) {
                            echo '<div class="empty-state"><i class="fa fa-info-circle"></i> No active users yet. Users will appear here once they connect.</div>';
                        }

                        foreach ($routers as $router_id => $config) {
                            $count = 0;
                            foreach ($distribution as $item) {
                                if ($item['assigned_router'] === $router_id) {
                                    $count = $item['count'];
                                    break;
                                }
                            }
                            $percentage = $total_users > 0 ? ($count / $total_users) * 100 : 0;
                            $bar_class = $router_id === 'converge' ? 'router-converge' : 'router-globe';
                ?>
                            <div class="distribution-row">
                                <div class="distribution-label"><?php echo htmlspecialchars($config['name']); ?></div>
                                <div class="distribution-meter">
                                    <div class="distribution-progress <?php echo $bar_class; ?>" style="width: <?php echo $has_users ? $percentage : 0; ?>%;">
                                        <?php echo $has_users ? round($percentage, 1) . '% ' : '0% '; ?>
                                    </div>
                                </div>
                                <div class="distribution-meta"><?php echo $count; ?> users</div>
                            </div>
                <?php
                        }
                        echo '<p style="color: #4b5563; margin-top: 12px; font-size: 13px; font-weight: 700;">'
                           . '<i class="fa fa-users"></i> Total Active Users: <strong>' . $total_users . '</strong></p>';
                    } catch (Exception $e) {
                        echo '<p style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;">'
                           . '<strong>✗ Error loading user distribution:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- Recent Migrations -->
        <div class="router-card">
            <h2><i class="fa fa-exchange"></i> Recent Migrations</h2>
            <div class="migration-history">
                <?php
                try {
                    $stmt = $DB_con->prepare("SELECT * FROM router_load_balance ORDER BY migrated_at DESC LIMIT 20");
                    $stmt->execute();
                    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($migrations)) {
                        echo '<p style="text-align: center; color: #999; padding: 20px;">No migrations recorded yet.</p>';
                    } else {
                        foreach ($migrations as $mig) {
                            echo '<div class="migration-item">'
                               . '<strong>' . htmlspecialchars($mig['user_name']) . '</strong><br>'
                               . htmlspecialchars($mig['from_router']) . ' → ' . htmlspecialchars($mig['to_router']) . '<br>'
                               . '<small style="color: #999;">' . htmlspecialchars($mig['reason']) . ' | '
                               . date('M d, Y H:i:s', strtotime($mig['migrated_at'])) . '</small>'
                               . '</div>';
                        }
                    }
                } catch (Exception $e) {
                    echo '<p style="color: red;">Error loading migrations: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            </div>
        </div>

        <!-- System Controls -->
        <div class="router-card">
            <h2><i class="fa fa-cogs"></i> System Controls</h2>
            <form method="POST" class="btn-group">
                <input type="hidden" name="action" value="check_health">
                <button type="submit" class="btn btn-warning">
                    <i class="fa fa-refresh"></i> Force Health Check
                </button>
            </form>
            <p style="margin-top: 15px; font-size: 12px; color: #666;">
                Health checks run automatically every 30 seconds. Use this button to manually trigger an immediate check.
            </p>
        </div>

    </div>

    <script src="js/jquery-2.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>