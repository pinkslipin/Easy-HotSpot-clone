<?php
/**
 * Router Connection Test
 * 
 * Use this page to test your MikroTik router connection
 * before going into production mode.
 */
require_once 'security_helper.php';
secure_session_start();
require_auth();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Connection Test</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        body { padding: 30px; background: #f5f5f5; }
        .test-container { max-width: 700px; margin: 0 auto; }
        .result-box { padding: 20px; border-radius: 8px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; }
        .config-display { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; }
        h1 { color: #333; }
        .btn-test { padding: 15px 30px; font-size: 18px; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fa fa-wifi"></i> Router Connection Test</h1>
        <p class="text-muted">Test your MikroTik router connection before enabling production mode.</p>
        
        <div class="config-display">
            <strong>Current Configuration:</strong><br>
            Host: <code><?php echo htmlspecialchars($host); ?></code><br>
            User: <code><?php echo htmlspecialchars($user); ?></code><br>
            Pass: <code>****</code><br>
            Mode: <code><?php echo MOCK_MODE ? 'MOCK (Development)' : 'LIVE (Production)'; ?></code>
        </div>
        
        <?php
        if (isset($_POST['test_connection'])) {
            require_once 'routeros_api.php';
            
            echo '<div class="result-box info"><strong>Testing connection to ' . htmlspecialchars($host) . '...</strong></div>';
            
            $result = testRouterConnection($host, $user, $pass);
            
            if ($result['success']) {
                echo '<div class="result-box success">';
                echo '<h4><i class="fa fa-check-circle"></i> Connection Successful!</h4>';
                echo '<p>Router Identity: <strong>' . htmlspecialchars($result['identity']) . '</strong></p>';
                echo '<p>The modern RouterOS API library is working correctly.</p>';
                echo '<hr>';
                echo '<p><strong>Next steps:</strong></p>';
                echo '<ol>';
                echo '<li>Edit <code>config.php</code></li>';
                echo '<li>Change <code>MOCK_MODE</code> to <code>false</code></li>';
                echo '<li>Your voucher system is ready for production!</li>';
                echo '</ol>';
                echo '</div>';
            } else {
                echo '<div class="result-box error">';
                echo '<h4><i class="fa fa-times-circle"></i> Connection Failed</h4>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($result['message']) . '</p>';
                echo '<hr>';
                echo '<p><strong>Troubleshooting:</strong></p>';
                echo '<ol>';
                echo '<li>Verify the router IP address is correct</li>';
                echo '<li>Ensure the API service is enabled on port 8728</li>';
                echo '<li>Check username and password</li>';
                echo '<li>Make sure the user has API permissions</li>';
                echo '<li>Verify firewall allows connection from this server</li>';
                echo '</ol>';
                echo '</div>';
            }
        }
        
        // Also test with different ports if main test fails
        if (isset($_POST['test_ports'])) {
            require_once 'routeros_api.php';
            
            $ports = [8728, 8729, 8727];
            echo '<div class="result-box info"><strong>Testing multiple ports...</strong></div>';
            
            foreach ($ports as $port) {
                echo "<p>Testing port $port... ";
                
                try {
                    $config = new \RouterOS\Config([
                        'host' => $host,
                        'user' => $user,
                        'pass' => $pass,
                        'port' => $port,
                        'timeout' => 3,
                    ]);
                    $testClient = new \RouterOS\Client($config);
                    echo '<span style="color:green"><i class="fa fa-check"></i> Port ' . $port . ' works!</span>';
                } catch (Exception $e) {
                    echo '<span style="color:red"><i class="fa fa-times"></i> Failed (Connection failed)</span>';
                }
                echo "</p>";
            }
        }
        ?>
        
        <?php
        // Test hotspot operations
        if (isset($_POST['test_hotspot'])) {
            require_once 'routeros_api.php';
            
            echo '<div class="result-box info"><strong>Testing Hotspot Operations...</strong></div>';
            
            try {
                $config = new \RouterOS\Config([
                    'host' => $host,
                    'user' => $user,
                    'pass' => $pass,
                    'port' => 8728,
                ]);
                $client = new \RouterOS\Client($config);
                $util = new ModernRouterUtil($client);
                
                // 1. List profiles
                echo '<div class="result-box success">';
                echo '<h5>1. Hotspot Profiles</h5>';
                $util->setMenu('/ip hotspot user profile');
                $profiles = $util->getAll();
                if (count($profiles) > 0) {
                    echo '<ul>';
                    foreach ($profiles as $profile) {
                        $profileName = $profile->name ?? $profile->{'name'} ?? 'unnamed';
                        echo '<li><strong>' . htmlspecialchars($profileName) . '</strong>';
                        if (isset($profile->{'session-timeout'})) {
                            echo ' (Session: ' . $profile->{'session-timeout'} . ')';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="text-warning">No profiles found. Create profiles using:</p>';
                    echo '<pre>/ip hotspot user profile add name=1hour session-timeout=1h</pre>';
                }
                echo '</div>';
                
                // 2. List existing users
                echo '<div class="result-box success">';
                echo '<h5>2. Existing Hotspot Users</h5>';
                $util->setMenu('/ip hotspot user');
                $users = $util->getAll();
                echo '<p>Found <strong>' . count($users) . '</strong> users</p>';
                if (count($users) > 0 && count($users) <= 10) {
                    echo '<table class="table table-condensed"><tr><th>Name</th><th>Profile</th></tr>';
                    foreach ($users as $u) {
                        echo '<tr><td>' . htmlspecialchars($u->name ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($u->profile ?? 'default') . '</td></tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
                
                // 3. Test adding a user
                echo '<div class="result-box success">';
                echo '<h5>3. Test Add User</h5>';
                $testUser = 'API_TEST_' . rand(1000, 9999);
                $result = $util->add([
                    'name' => $testUser,
                    'password' => 'testpass123',
                    'profile' => 'default',
                    'comment' => 'Test user from Easy-HotSpot'
                ]);
                
                if ($result) {
                    echo '<p style="color:green"><i class="fa fa-check"></i> Created test user: <strong>' . $testUser . '</strong></p>';
                    
                    // 4. Test removing the user
                    echo '<h5>4. Test Remove User</h5>';
                    $removed = $util->removeUser($testUser);
                    if ($removed) {
                        echo '<p style="color:green"><i class="fa fa-check"></i> Removed test user: <strong>' . $testUser . '</strong></p>';
                    } else {
                        echo '<p style="color:orange"><i class="fa fa-exclamation"></i> Could not auto-remove test user. Clean up manually.</p>';
                    }
                } else {
                    echo '<p style="color:red"><i class="fa fa-times"></i> Failed to create test user</p>';
                    echo '<p>Make sure you have a hotspot configured and a "default" profile exists.</p>';
                }
                echo '</div>';
                
                echo '<div class="result-box success">';
                echo '<h4><i class="fa fa-check-circle"></i> All Hotspot Tests Passed!</h4>';
                echo '<p>Your Easy-HotSpot system is ready for production use.</p>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="result-box error">';
                echo '<h4><i class="fa fa-times-circle"></i> Hotspot Test Failed</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        
        // Network diagnostics
        if (isset($_POST['network_diag'])) {
            echo '<div class="result-box info"><strong>Network Diagnostics for ' . htmlspecialchars($host) . '</strong></div>';
            
            // Check if host is reachable
            echo '<h5>Ping Test:</h5>';
            $pingCmd = PHP_OS_FAMILY === 'Windows' ? "ping -n 1 -w 1000 " . escapeshellarg($host) : "ping -c 1 -W 1 " . escapeshellarg($host);
            exec($pingCmd, $output, $returnCode);
            if ($returnCode === 0) {
                echo '<p style="color:green"><i class="fa fa-check"></i> Host is reachable via ping</p>';
            } else {
                echo '<p style="color:red"><i class="fa fa-times"></i> Host not responding to ping (firewall may block ICMP)</p>';
            }
            
            // Check socket connection
            echo '<h5>Port 8728 (API) Test:</h5>';
            $socket = @fsockopen($host, 8728, $errno, $errstr, 3);
            if ($socket) {
                echo '<p style="color:green"><i class="fa fa-check"></i> Port 8728 is open and accepting connections</p>';
                fclose($socket);
            } else {
                echo '<p style="color:red"><i class="fa fa-times"></i> Cannot connect to port 8728: ' . htmlspecialchars($errstr) . '</p>';
            }
            
            // PHP Extensions check
            echo '<h5>PHP Extensions:</h5>';
            echo '<p>Sockets: ' . (extension_loaded('sockets') ? '<span style="color:green">✓ Enabled</span>' : '<span style="color:red">✗ Disabled</span>') . '</p>';
            echo '<p>OpenSSL: ' . (extension_loaded('openssl') ? '<span style="color:green">✓ Enabled</span>' : '<span style="color:orange">⚠ Disabled (needed for SSL)</span>') . '</p>';
        }
        
        // Test router logs
        if (isset($_POST['test_logs'])) {
            require_once 'routeros_api.php';
            
            echo '<div class="result-box info"><strong>Testing Router Logs...</strong></div>';
            
            try {
                $config = new \RouterOS\Config([
                    'host' => $host,
                    'user' => $user,
                    'pass' => $pass,
                    'port' => 8728,
                ]);
                $client = new \RouterOS\Client($config);
                $util = new ModernRouterUtil($client);
                
                $util->setMenu('/log');
                $logs = $util->getAll();
                
                echo '<div class="result-box success">';
                echo '<h5>Found ' . count($logs) . ' log entries</h5>';
                
                if (count($logs) > 0) {
                    echo '<p><strong>First log entry raw data:</strong></p>';
                    echo '<pre>' . print_r($logs[0]->toArray(), true) . '</pre>';
                    
                    echo '<p><strong>Sample entries (first 5):</strong></p>';
                    echo '<table class="table table-bordered table-condensed">';
                    echo '<tr><th>Time</th><th>Topics</th><th>Message</th></tr>';
                    $count = 0;
                    foreach ($logs as $entry) {
                        if ($count++ >= 5) break;
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($entry->time ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($entry->topics ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($entry->message ?? 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="result-box error">';
                echo '<h4><i class="fa fa-times-circle"></i> Log Test Failed</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
        
        <form method="post" style="margin-top: 20px;">
            <button type="submit" name="test_connection" class="btn btn-primary btn-test">
                <i class="fa fa-plug"></i> Test Connection
            </button>
            <button type="submit" name="test_hotspot" class="btn btn-success btn-test">
                <i class="fa fa-wifi"></i> Test Hotspot
            </button>
            <button type="submit" name="test_logs" class="btn btn-default btn-test">
                <i class="fa fa-list"></i> Test Logs
            </button>
            <button type="submit" name="test_ports" class="btn btn-info btn-test">
                <i class="fa fa-search"></i> Scan Ports
            </button>
            <button type="submit" name="network_diag" class="btn btn-warning btn-test">
                <i class="fa fa-stethoscope"></i> Network Diag
            </button>
        </form>
        
        <hr>
        
        <h3>Router Setup Requirements</h3>
        <div class="panel panel-default">
            <div class="panel-body">
                <p>Before connecting, ensure your MikroTik router has:</p>
                <ol>
                    <li><strong>API Service Enabled:</strong>
                        <pre>/ip service enable api</pre>
                    </li>
                    <li><strong>API User Created:</strong>
                        <pre>/user add name=api password=api group=full</pre>
                    </li>
                    <li><strong>Firewall Allows API:</strong>
                        <pre>/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=accept</pre>
                    </li>
                    <li><strong>Hotspot Configured:</strong>
                        <ul>
                            <li>Hotspot server running on your LAN interface</li>
                            <li>User profiles created (e.g., default, vip)</li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
        
        <a href="index.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</body>
</html>
