<?php
/**
 * Portal Preview - Mobile Friendly Test Page
 * Access this from your phone to see how the login page looks
 * NO LOGIN REQUIRED - This is just a preview
 */

// Portal configuration file path
$portalConfigFile = __DIR__ . '/portal_config.json';

// Get portal configuration
function getPortalConfigPreview() {
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

// Get pricing config
require_once 'pricing_config.php';

$config = getPortalConfigPreview();

// Build price list HTML
$priceListHTML = '';
if ($config['show_price_list']) {
    global $VOUCHER_PRICES;
    $priceListHTML = '<div class="price-list"><h3>WiFi Packages</h3><ul>';
    foreach ($VOUCHER_PRICES as $duration => $price) {
        $label = getUptimeName($duration);
        $priceListHTML .= "<li><span class='time'>{$label}</span><span class='price'>₱" . number_format($price, 2) . "</span></li>";
    }
    $priceListHTML .= '</ul></div>';
}

// Terms HTML
$termsHTML = '';
if ($config['terms_enabled']) {
    $termsHTML = '<div class="terms"><label><input type="checkbox" required> ' . htmlspecialchars($config['terms_text']) . '</label></div>';
}

// Output the captive portal HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['title']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, <?php echo $config['background_color']; ?> 0%, <?php echo $config['background_gradient']; ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 120px;
            max-height: 120px;
            border-radius: 10px;
        }
        h1 {
            text-align: center;
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        .input-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .input-group input:focus {
            outline: none;
            border-color: <?php echo $config['button_color']; ?>;
        }
        .login-btn {
            width: 100%;
            padding: 15px;
            background: <?php echo $config['button_color']; ?>;
            color: <?php echo $config['button_text_color']; ?>;
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
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .price-list li:last-child {
            border-bottom: none;
        }
        .price-list .time {
            font-weight: 500;
            color: #333;
        }
        .price-list .price {
            color: <?php echo $config['button_color']; ?>;
            font-weight: 700;
            font-size: 18px;
        }
        .terms {
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
        .terms input {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .footer {
            text-align: center;
            margin-top: 25px;
            color: #999;
            font-size: 12px;
        }
        .preview-badge {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #ff6b6b;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        }
        <?php echo $config['custom_css']; ?>
    </style>
</head>
<body>
    <div class="preview-badge">📱 PREVIEW MODE</div>
    
    <div class="login-container">
        <div class="logo">
            <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="Logo" onerror="this.style.display='none'">
        </div>
        
        <h1><?php echo htmlspecialchars($config['title']); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars($config['subtitle']); ?></p>
        
        <form onsubmit="alert('This is just a preview! In production, this would authenticate with the router.'); return false;">
            <div class="input-group">
                <label>Username / Voucher Code</label>
                <input type="text" name="username" placeholder="Enter your voucher code" required>
            </div>
            
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <?php echo $priceListHTML; ?>
            <?php echo $termsHTML; ?>
            
            <button type="submit" class="login-btn">Connect to WiFi</button>
            
            <?php if ($config['support_contact']): ?>
            <p style="text-align: center; margin-top: 15px; color: #666; font-size: 14px;">
                Need help? <?php echo htmlspecialchars($config['support_contact']); ?>
            </p>
            <?php endif; ?>
        </form>
        
        <div class="footer">
            <?php echo htmlspecialchars($config['footer_text']); ?>
        </div>
    </div>
</body>
</html>
