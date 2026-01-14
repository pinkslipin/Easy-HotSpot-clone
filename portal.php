<?php
/**
 * Captive Portal Customizer
 * 
 * Allows customization of the MikroTik hotspot login page
 */
if (!isset($_SESSION)) session_start();
require_once 'dbconfig.php';
require_once 'config.php';
require_once 'pricing_config.php';
require_once 'audit_log.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Portal configuration file path
$portalConfigFile = __DIR__ . '/portal_config.json';

/**
 * Get portal configuration
 */
function getPortalConfig() {
    global $portalConfigFile;
    
    $defaults = [
        'title' => 'Welcome to Our WiFi',
        'subtitle' => 'Please login to continue',
        'logo_url' => 'images/logo.png',
        'background_color' => '#667eea',
        'background_gradient' => '#764ba2',
        'text_color' => '#ffffff',
        'button_color' => '#28ABE3',
        'button_text_color' => '#ffffff',
        'show_price_list' => true,
        'custom_css' => '',
        'footer_text' => 'Powered by Easy HotSpot',
        'terms_enabled' => false,
        'terms_text' => 'By logging in, you agree to our terms of service.',
        'support_contact' => '',
        'wifi_name' => 'CafeWiFi'
    ];
    
    if (file_exists($portalConfigFile)) {
        $saved = json_decode(file_get_contents($portalConfigFile), true);
        if ($saved) {
            return array_merge($defaults, $saved);
        }
    }
    
    return $defaults;
}

/**
 * Save portal configuration
 */
function savePortalConfig($config) {
    global $portalConfigFile;
    
    return file_put_contents($portalConfigFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_portal'])) {
    $config = [
        'title' => isset($_POST['title']) ? $_POST['title'] : 'Welcome to Our WiFi',
        'subtitle' => isset($_POST['subtitle']) ? $_POST['subtitle'] : '',
        'logo_url' => isset($_POST['logo_url']) ? $_POST['logo_url'] : 'images/logo.png',
        'background_color' => isset($_POST['background_color']) ? $_POST['background_color'] : '#667eea',
        'background_gradient' => isset($_POST['background_gradient']) ? $_POST['background_gradient'] : '#764ba2',
        'text_color' => isset($_POST['text_color']) ? $_POST['text_color'] : '#ffffff',
        'button_color' => isset($_POST['button_color']) ? $_POST['button_color'] : '#28ABE3',
        'button_text_color' => isset($_POST['button_text_color']) ? $_POST['button_text_color'] : '#ffffff',
        'show_price_list' => isset($_POST['show_price_list']) ? true : false,
        'custom_css' => isset($_POST['custom_css']) ? $_POST['custom_css'] : '',
        'footer_text' => isset($_POST['footer_text']) ? $_POST['footer_text'] : '',
        'terms_enabled' => isset($_POST['terms_enabled']) ? true : false,
        'terms_text' => isset($_POST['terms_text']) ? $_POST['terms_text'] : '',
        'support_contact' => isset($_POST['support_contact']) ? $_POST['support_contact'] : '',
        'wifi_name' => isset($_POST['wifi_name']) ? $_POST['wifi_name'] : 'CafeWiFi'
    ];
    
    if (savePortalConfig($config)) {
        $message = 'Portal settings saved successfully!';
        $messageType = 'success';
        auditLog(AUDIT_PORTAL_UPDATE, 'Portal customization updated');
    } else {
        $message = 'Error saving portal settings.';
        $messageType = 'danger';
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'html') {
    $config = getPortalConfig();
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="login.html"');
    echo generatePortalHTML($config);
    exit;
}

/**
 * Generate the portal HTML for MikroTik
 */
function generatePortalHTML($config) {
    global $VOUCHER_PRICES, $CURRENCY_SYMBOL;
    
    $priceListHTML = '';
    if ($config['show_price_list']) {
        $priceListHTML = '<div class="price-list"><h3>WiFi Packages</h3><ul>';
        foreach ($VOUCHER_PRICES as $time => $price) {
            $name = getUptimeName($time);
            $priceListHTML .= "<li><span class='time'>$name</span><span class='price'>$CURRENCY_SYMBOL" . number_format($price, 2) . "</span></li>";
        }
        $priceListHTML .= '</ul></div>';
    }
    
    $termsHTML = '';
    if ($config['terms_enabled'] && !empty($config['terms_text'])) {
        $termsHTML = '<div class="terms"><label><input type="checkbox" name="agree" required> ' . htmlspecialchars($config['terms_text']) . '</label></div>';
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$config['title']}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, {$config['background_color']} 0%, {$config['background_gradient']} 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, {$config['background_color']} 0%, {$config['background_gradient']} 100%);
            color: {$config['text_color']};
            padding: 30px;
            text-align: center;
        }
        .login-header img {
            max-width: 100px;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .login-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: {$config['button_color']};
        }
        .login-btn {
            width: 100%;
            padding: 15px;
            background: {$config['button_color']};
            color: {$config['button_text_color']};
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .price-list {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        .price-list h3 {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }
        .price-list ul {
            list-style: none;
        }
        .price-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .price-list li:last-child {
            border-bottom: none;
        }
        .price-list .time {
            font-weight: 500;
        }
        .price-list .price {
            color: {$config['button_color']};
            font-weight: 700;
        }
        .terms {
            margin: 15px 0;
            font-size: 12px;
            color: #666;
        }
        .terms input {
            margin-right: 8px;
        }
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            font-size: 12px;
            color: #999;
        }
        {$config['custom_css']}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="{$config['logo_url']}" alt="Logo">
            <h1>{$config['title']}</h1>
            <p>{$config['subtitle']}</p>
        </div>
        <div class="login-body">
            <form name="login" action="\$(link-login-only)" method="post">
                <input type="hidden" name="dst" value="\$(link-orig)" />
                <input type="hidden" name="popup" value="true" />
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                
                $priceListHTML
                $termsHTML
                
                <button type="submit" class="login-btn">Connect to WiFi</button>
            </form>
        </div>
        <div class="login-footer">
            {$config['footer_text']}
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}

$config = getPortalConfig();
?>
<!DOCTYPE html>
<html lang="en">
<?php include('header.php'); ?>
<style>
.portal-container {
    padding: 20px;
}

.portal-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.portal-section h4 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
    font-weight: 600;
}

.form-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: none;
}

.color-preview {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 2px solid #ddd;
    display: inline-block;
    vertical-align: middle;
    margin-left: 10px;
}

.preview-frame {
    border: 2px solid #ddd;
    border-radius: 12px;
    overflow: hidden;
    background: #f8f9fa;
}

.preview-frame iframe {
    width: 100%;
    height: 600px;
    border: none;
}

.nav-button {
    display: inline-block;
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    margin: 5px;
    border: none;
    cursor: pointer;
}

.nav-button.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.nav-button.success {
    background: #72bf48;
    color: white;
}

.nav-button.info {
    background: #28ABE3;
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
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
}

.help-text {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}
</style>
<body>
<?php
// Get server IP for QR code - try to find the correct LAN IP
$serverIP = '192.168.254.104'; // Default to known working IP

// Try to auto-detect if possible
if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '::1' && $_SERVER['SERVER_ADDR'] != '127.0.0.1') {
    $serverIP = $_SERVER['SERVER_ADDR'];
}

// Allow manual override via query param: ?ip=192.168.1.100
if (isset($_GET['ip'])) {
    $serverIP = $_GET['ip'];
}

$previewURL = "http://{$serverIP}/easy-hotspot/portal_preview.php";
$qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($previewURL);
?>
<div class="container portal-container">
    <!-- Header -->
    <div class="no_print text-center" style="margin-bottom: 30px;">
        <h1 style="color: #333; font-weight: 700;"><i class="fa fa-paint-brush"></i> Captive Portal Customizer</h1>
        <p style="color: #666;">Design your WiFi login page for MikroTik hotspot</p>
        <div style="margin-top: 15px;">
            <a href="index.php" class="nav-button primary"><i class="fa fa-home"></i> Main Menu</a>
            <a href="?export=html" class="nav-button success"><i class="fa fa-download"></i> Export HTML</a>
            <button type="button" class="nav-button info" onclick="document.getElementById('qr-modal').style.display='flex'"><i class="fa fa-qrcode"></i> Test on Phone</button>
        </div>
    </div>
    
    <!-- QR Code Modal -->
    <div id="qr-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; padding:30px; border-radius:15px; text-align:center; max-width:350px;">
            <h3 style="margin-bottom:15px;"><i class="fa fa-qrcode"></i> Scan with Phone</h3>
            <img src="<?php echo $qrCodeURL; ?>" alt="QR Code" style="margin:15px 0;">
            <p style="font-size:12px; color:#666; word-break:break-all;"><?php echo $previewURL; ?></p>
            <p style="font-size:14px; color:#333; margin-top:10px;">📱 Scan this QR code to preview the portal on your phone!</p>
            <button onclick="document.getElementById('qr-modal').style.display='none'" class="nav-button secondary" style="margin-top:15px;"><i class="fa fa-times"></i> Close</button>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <form method="post">
        <div class="row">
            <!-- Settings Column -->
            <div class="col-md-6">
                <!-- Basic Settings -->
                <div class="portal-section">
                    <h4><i class="fa fa-cog"></i> Basic Settings</h4>
                    
                    <div class="form-group">
                        <label>WiFi Network Name (SSID)</label>
                        <input type="text" name="wifi_name" class="form-control" value="<?php echo htmlspecialchars($config['wifi_name']); ?>" placeholder="e.g., CafeWiFi">
                        <span class="help-text">Used for QR codes and display</span>
                    </div>
                    
                    <div class="form-group">
                        <label>Page Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($config['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Subtitle</label>
                        <input type="text" name="subtitle" class="form-control" value="<?php echo htmlspecialchars($config['subtitle']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Logo URL</label>
                        <input type="text" name="logo_url" class="form-control" value="<?php echo htmlspecialchars($config['logo_url']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Footer Text</label>
                        <input type="text" name="footer_text" class="form-control" value="<?php echo htmlspecialchars($config['footer_text']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Support Contact</label>
                        <input type="text" name="support_contact" class="form-control" value="<?php echo htmlspecialchars($config['support_contact']); ?>" placeholder="Phone or email">
                    </div>
                </div>
                
                <!-- Colors -->
                <div class="portal-section">
                    <h4><i class="fa fa-eyedropper"></i> Colors</h4>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Background Start</label>
                                <input type="color" name="background_color" class="form-control" value="<?php echo $config['background_color']; ?>" style="height: 45px;">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Background End</label>
                                <input type="color" name="background_gradient" class="form-control" value="<?php echo $config['background_gradient']; ?>" style="height: 45px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Text Color</label>
                                <input type="color" name="text_color" class="form-control" value="<?php echo $config['text_color']; ?>" style="height: 45px;">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Button Color</label>
                                <input type="color" name="button_color" class="form-control" value="<?php echo $config['button_color']; ?>" style="height: 45px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Button Text Color</label>
                        <input type="color" name="button_text_color" class="form-control" value="<?php echo $config['button_text_color']; ?>" style="height: 45px; width: 50%;">
                    </div>
                </div>
                
                <!-- Options -->
                <div class="portal-section">
                    <h4><i class="fa fa-list"></i> Options</h4>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_price_list" <?php echo $config['show_price_list'] ? 'checked' : ''; ?>>
                            Show price list on login page
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms_enabled" <?php echo $config['terms_enabled'] ? 'checked' : ''; ?>>
                            Require agreement to terms
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Terms Text</label>
                        <textarea name="terms_text" class="form-control" rows="2"><?php echo htmlspecialchars($config['terms_text']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Custom CSS (Advanced)</label>
                        <textarea name="custom_css" class="form-control" rows="4" placeholder="Add custom CSS here..."><?php echo htmlspecialchars($config['custom_css']); ?></textarea>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="text-center" style="margin: 20px 0;">
                    <button type="submit" name="save_portal" class="nav-button success btn-lg">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
            
            <!-- Preview Column -->
            <div class="col-md-6">
                <div class="portal-section">
                    <h4><i class="fa fa-eye"></i> Live Preview </h4>
                    <div class="preview-frame">
                        <iframe id="preview-iframe" srcdoc="<?php echo htmlspecialchars(generatePortalHTML($config)); ?>"></iframe>
                    </div>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="?export=html" class="nav-button info">
                            <i class="fa fa-download"></i> Download login.html
                        </a>
                    </div>
                    <div class="help-text text-center" style="margin-top: 10px;">
                        Upload this file to your MikroTik router's hotspot files (Files > hotspot > login.html)
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Live Preview JavaScript -->
    <script>
    // Price list data from PHP
    var priceList = <?php echo json_encode(getAllPrices()); ?>;
    
    function generatePriceListHTML() {
        var html = '<ul>';
        for (var key in priceList) {
            var label = key.replace('_', ' ').toUpperCase();
            html += '<li><span class="time">' + label + '</span><span class="price">₱' + priceList[key] + '</span></li>';
        }
        html += '</ul>';
        return html;
    }
    
    function updatePreview() {
        console.log('Updating preview...');
        
        // Get values using vanilla JS
        var getValue = function(name) {
            var el = document.querySelector('input[name="' + name + '"], textarea[name="' + name + '"]');
            return el ? el.value : '';
        };
        var getChecked = function(name) {
            var el = document.querySelector('input[name="' + name + '"]');
            return el ? el.checked : false;
        };
        
        var title = getValue('title') || 'Welcome to Our WiFi';
        var subtitle = getValue('subtitle') || '';
        var logoUrl = getValue('logo_url') || 'images/logo.png';
        var footerText = getValue('footer_text') || '';
        var supportContact = getValue('support_contact') || '';
        var bgColor = getValue('background_color') || '#667eea';
        var bgGradient = getValue('background_gradient') || '#764ba2';
        var textColor = getValue('text_color') || '#ffffff';
        var buttonColor = getValue('button_color') || '#28ABE3';
        var buttonTextColor = getValue('button_text_color') || '#ffffff';
        var showPriceList = getChecked('show_price_list');
        var termsEnabled = getChecked('terms_enabled');
        var termsText = getValue('terms_text') || '';
        
        var priceListHTML = showPriceList ? '<div class="price-list"><h3>WiFi Rates</h3>' + generatePriceListHTML() + '</div>' : '';
        var termsHTML = termsEnabled ? '<div class="terms"><label><input type="checkbox" required> ' + termsText + '</label></div>' : '';
        var supportHTML = supportContact ? '<p style="font-size: 12px; margin-top: 15px;">Need help? ' + supportContact + '</p>' : '';
        
        var html = '<!DOCTYPE html>' +
            '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">' +
            '<style>' +
            '* { box-sizing: border-box; margin: 0; padding: 0; }' +
            'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, ' + bgColor + ' 0%, ' + bgGradient + ' 100%); padding: 20px; }' +
            '.login-container { background: white; border-radius: 20px; padding: 40px; max-width: 400px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }' +
            '.logo { text-align: center; margin-bottom: 20px; }' +
            '.logo img { max-width: 120px; height: auto; }' +
            'h1 { text-align: center; color: #333; font-size: 24px; margin-bottom: 10px; }' +
            '.subtitle { text-align: center; color: #666; margin-bottom: 25px; }' +
            '.input-group { margin-bottom: 15px; }' +
            '.input-group label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; }' +
            '.input-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; }' +
            '.login-btn { width: 100%; padding: 15px; background: ' + buttonColor + '; color: ' + buttonTextColor + '; border: none; border-radius: 10px; font-size: 18px; font-weight: 600; cursor: pointer; }' +
            '.price-list { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 10px; }' +
            '.price-list h3 { text-align: center; margin-bottom: 15px; color: #333; }' +
            '.price-list ul { list-style: none; }' +
            '.price-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }' +
            '.price-list li:last-child { border-bottom: none; }' +
            '.price-list .time { font-weight: 500; }' +
            '.price-list .price { color: ' + buttonColor + '; font-weight: 700; }' +
            '.terms { margin: 15px 0; font-size: 12px; color: #666; }' +
            '.footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }' +
            '</style></head><body>' +
            '<div class="login-container">' +
            '<div class="logo"><img src="' + logoUrl + '" alt="Logo" onerror="this.style.display=\'none\'"></div>' +
            '<h1>' + title + '</h1>' +
            '<p class="subtitle">' + subtitle + '</p>' +
            '<form>' +
            '<div class="input-group"><label>Username / Voucher Code</label><input type="text" name="username" placeholder="Enter your code"></div>' +
            '<div class="input-group"><label>Password</label><input type="password" name="password" placeholder="Enter password"></div>' +
            priceListHTML +
            termsHTML +
            '<button type="submit" class="login-btn">Connect to WiFi</button>' +
            supportHTML +
            '</form>' +
            '<div class="footer">' + footerText + '</div>' +
            '</div></body></html>';
        
        // Update iframe
        var iframe = document.getElementById('preview-iframe');
        if (iframe) {
            iframe.srcdoc = html;
        }
    }
    
    // Use vanilla JS to ensure it works even if jQuery has issues
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Portal customizer loaded');
        
        // Get all input elements that should trigger preview update
        var textInputs = document.querySelectorAll('input[name="title"], input[name="subtitle"], input[name="logo_url"], input[name="footer_text"], input[name="support_contact"], input[name="wifi_name"]');
        var colorInputs = document.querySelectorAll('input[type="color"]');
        var checkboxInputs = document.querySelectorAll('input[name="show_price_list"], input[name="terms_enabled"]');
        var textareas = document.querySelectorAll('textarea[name="terms_text"], textarea[name="custom_css"]');
        
        // Bind text inputs
        textInputs.forEach(function(input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('keyup', updatePreview);
        });
        
        // Bind color inputs
        colorInputs.forEach(function(input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });
        
        // Bind checkboxes
        checkboxInputs.forEach(function(input) {
            input.addEventListener('change', updatePreview);
        });
        
        // Bind textareas
        textareas.forEach(function(textarea) {
            textarea.addEventListener('input', updatePreview);
        });
        
        console.log('Event listeners attached to', textInputs.length, 'text inputs,', colorInputs.length, 'color inputs');
    });
    </script>
    
    <!-- Instructions -->
    <div class="portal-section">
        <h4><i class="fa fa-info-circle"></i> How to Use</h4>
        <ol style="line-height: 2;">
            <li>Customize your portal using the settings above</li>
            <li>Click <strong>"Save Settings"</strong> to save your configuration</li>
            <li>Click <strong>"Download login.html"</strong> to export the file</li>
            <li>Open WinBox and connect to your MikroTik router</li>
            <li>Go to <strong>Files</strong> in WinBox</li>
            <li>Navigate to the <strong>hotspot</strong> folder</li>
            <li>Upload (drag & drop) the <strong>login.html</strong> file</li>
            <li>Test by connecting a device to your hotspot!</li>
        </ol>
    </div>
    
    <!-- Footer -->
    <div class="text-center" style="margin-top: 20px; padding: 20px; color: #666;">
        <a href="index.php" class="nav-button primary"><i class="fa fa-home"></i> Back to Main Menu</a>
    </div>
</div>
</body>
</html>
