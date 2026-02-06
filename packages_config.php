<?php
/**
 * Package Configuration for WiFi Vouchers
 * 
 * This is the SINGLE SOURCE OF TRUTH for all voucher packages.
 * DO NOT duplicate pricing information elsewhere.
 * 
 * PACKAGE TYPES:
 * - 'duration': Traditional time-limited passes (limit-uptime from first login)
 * - 'window': Time-window passes (access during specific hours of the day)
 * 
 * WINDOW PASSES:
 * Window passes allow access only during specific hours. The RouterOS profile
 * will check the current time on login and:
 * 1. If outside the window: disconnect immediately
 * 2. If inside the window: schedule disconnect at window end
 * 
 * For windows that span midnight (e.g., 6PM-5AM), the end time is next day.
 */

/**
 * Currency symbol for display
 */
$CURRENCY_SYMBOL = '₱';

/**
 * Café branding for vouchers
 */
$CAFE_NAME = 'Mindspace Café';
$CAFE_TAGLINE = 'Fast & Reliable Internet';
$CAFE_CONTACT = '';

/**
 * Voucher expiry settings - days until unused voucher expires
 */
$VOUCHER_EXPIRY_DAYS = 30;

/**
 * All available packages - SINGLE SOURCE OF TRUTH
 * 
 * Each package has:
 * - id: Unique identifier (used in DB and internally)
 * - name: Display name for UI
 * - category: For grouping in UI ('individual', 'daily', 'unli')
 * - price: Price in local currency
 * - type: 'duration' or 'window'
 * 
 * For duration packages:
 * - limit_uptime: RouterOS uptime limit string (e.g., '1h', '3h', '1d', '1w')
 * 
 * For window packages:
 * - window_start: Start time in HH:MM format (24-hour)
 * - window_end: End time in HH:MM format (24-hour)
 * - window_description: Human-readable description
 * - spans_midnight: true if window crosses midnight (computed automatically)
 */
$PACKAGES = [
    // ==================
    // INDIVIDUAL PASSES
    // ==================
    'ind_1h' => [
        'id' => 'ind_1h',
        'name' => '1 Hour',
        'category' => 'individual',
        'price' => 50.00,
        'type' => 'duration',
        'limit_uptime' => '1h',
    ],
    'ind_3h' => [
        'id' => 'ind_3h',
        'name' => '3 Hours',
        'category' => 'individual',
        'price' => 138.00,
        'type' => 'duration',
        'limit_uptime' => '3h',
    ],
    'ind_5h' => [
        'id' => 'ind_5h',
        'name' => '5 Hours',
        'category' => 'individual',
        'price' => 188.00,
        'type' => 'duration',
        'limit_uptime' => '5h',
    ],
    
    // ==================
    // DAILY ACCESS (Time Windows)
    // ==================
    'day_8_18' => [
        'id' => 'day_8_18',
        'name' => 'Day Pass',
        'category' => 'daily',
        'price' => 238.00,
        'type' => 'window',
        'window_start' => '08:00',
        'window_end' => '18:00',
        'window_description' => '8AM - 6PM',
        'spans_midnight' => false,
        'profile_suffix' => 'DayPass',
    ],
    'night_18_5' => [
        'id' => 'night_18_5',
        'name' => 'Night Pass',
        'category' => 'daily',
        'price' => 208.00,
        'type' => 'window',
        'window_start' => '18:00',
        'window_end' => '05:00',
        'window_description' => '6PM - 5AM',
        'spans_midnight' => true,
        'profile_suffix' => 'NightPass',
    ],
    'ms_unli_8_5' => [
        'id' => 'ms_unli_8_5',
        'name' => 'Mindspace Unlimited',
        'category' => 'daily',
        'price' => 308.00,
        'type' => 'window',
        'window_start' => '08:00',
        'window_end' => '05:00',
        'window_description' => '8AM - 5AM (next day)',
        'spans_midnight' => true,
        'profile_suffix' => 'MindspaceUnli',
    ],
    
    // ==================
    // MINDSPACE UNLI PASSES
    // ==================
    'unli_week' => [
        'id' => 'unli_week',
        'name' => 'Weekly Pass',
        'category' => 'unli',
        'price' => 1888.00,
        'type' => 'duration',
        'limit_uptime' => '1w',
    ],
    'unli_15d' => [
        'id' => 'unli_15d',
        'name' => '15 Days Pass',
        'category' => 'unli',
        'price' => 2988.00,
        'type' => 'duration',
        'limit_uptime' => '15d',
    ],
    'unli_month' => [
        'id' => 'unli_month',
        'name' => 'Monthly Pass',
        'category' => 'unli',
        'price' => 5888.00,
        'type' => 'duration',
        'limit_uptime' => '30d',
    ],
];

/**
 * Category display names for UI grouping
 */
$PACKAGE_CATEGORIES = [
    'individual' => 'Individual Passes',
    'daily' => 'Daily Access (Time Window)',
    'unli' => 'Mindspace Unli Passes',
];

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Get all packages
 * @return array All packages
 */
function getAllPackages() {
    global $PACKAGES;
    return $PACKAGES;
}

/**
 * Get a specific package by ID
 * @param string $packageId Package ID
 * @return array|null Package data or null if not found
 */
function getPackage($packageId) {
    global $PACKAGES;
    return isset($PACKAGES[$packageId]) ? $PACKAGES[$packageId] : null;
}

/**
 * Get packages by category
 * @param string $category Category name
 * @return array Packages in that category
 */
function getPackagesByCategory($category) {
    global $PACKAGES;
    return array_filter($PACKAGES, function($pkg) use ($category) {
        return $pkg['category'] === $category;
    });
}

/**
 * Get all categories with their packages
 * @return array Categories with packages
 */
function getPackagesGroupedByCategory() {
    global $PACKAGES, $PACKAGE_CATEGORIES;
    $grouped = [];
    foreach ($PACKAGE_CATEGORIES as $catId => $catName) {
        $grouped[$catId] = [
            'name' => $catName,
            'packages' => getPackagesByCategory($catId)
        ];
    }
    return $grouped;
}

/**
 * Get package display name
 * @param string $packageId Package ID
 * @return string Display name
 */
function getPackageDisplayName($packageId) {
    $pkg = getPackage($packageId);
    if (!$pkg) return $packageId;
    
    if ($pkg['type'] === 'window' && isset($pkg['window_description'])) {
        return $pkg['name'] . ' (' . $pkg['window_description'] . ')';
    }
    return $pkg['name'];
}

/**
 * Get package price
 * @param string $packageId Package ID
 * @return float Price
 */
function getPackagePrice($packageId) {
    $pkg = getPackage($packageId);
    return $pkg ? $pkg['price'] : 0.00;
}

/**
 * Format price with currency symbol
 * @param float $price Price amount
 * @return string Formatted price
 */
function formatPrice($price) {
    global $CURRENCY_SYMBOL;
    return $CURRENCY_SYMBOL . number_format($price, 2);
}

/**
 * Get expiry date for new vouchers
 * @return string MySQL datetime format
 */
function getExpiryDate() {
    global $VOUCHER_EXPIRY_DAYS;
    return date('Y-m-d H:i:s', strtotime("+{$VOUCHER_EXPIRY_DAYS} days"));
}

/**
 * Check if a package is a window-based pass
 * @param string $packageId Package ID
 * @return bool True if window pass
 */
function isWindowPackage($packageId) {
    $pkg = getPackage($packageId);
    return $pkg && $pkg['type'] === 'window';
}

/**
 * Get the RouterOS limit-uptime for a package
 * For duration packages, returns the limit_uptime.
 * For window packages, returns a synthetic value based on window duration.
 * @param string $packageId Package ID
 * @return string|null limit-uptime value or null for window packages
 */
function getPackageLimitUptime($packageId) {
    $pkg = getPackage($packageId);
    if (!$pkg) return null;
    
    if ($pkg['type'] === 'duration') {
        return $pkg['limit_uptime'];
    }
    
    // For window packages, return null - they don't use traditional uptime
    return null;
}

/**
 * Get RouterOS profile name for a package
 * Window packages need dedicated profiles for time enforcement
 * @param string $packageId Package ID
 * @return string|null Profile name suffix for window packages
 */
function getWindowProfileName($packageId) {
    $pkg = getPackage($packageId);
    if (!$pkg || $pkg['type'] !== 'window') return null;
    return $pkg['profile_suffix'] ?? null;
}

// ========================================
// BACKWARD COMPATIBILITY FUNCTIONS
// These maintain compatibility with existing code
// that uses limit_uptime to look up prices
// ========================================

/**
 * Legacy: Get voucher price by uptime (for backward compatibility)
 * @deprecated Use getPackagePrice() instead
 * @param string $uptime Uptime string like '1h', '3h'
 * @return float Price
 */
function getVoucherPrice($uptime) {
    global $PACKAGES;
    
    // First try to find by limit_uptime
    foreach ($PACKAGES as $pkg) {
        if ($pkg['type'] === 'duration' && $pkg['limit_uptime'] === $uptime) {
            return $pkg['price'];
        }
    }
    
    // Fallback for very old vouchers with non-standard uptimes
    $legacyPrices = [
        '30m' => 25.00,
        '1h'  => 50.00,
        '3h'  => 138.00,
        '5h'  => 188.00,
        '6h'  => 188.00,
        '10h' => 300.00,
        '1d'  => 238.00,
        '1w'  => 1888.00,
        '15d' => 2988.00,
        '30d' => 5888.00,
    ];
    
    return isset($legacyPrices[$uptime]) ? $legacyPrices[$uptime] : 0.00;
}

/**
 * Legacy: Get friendly name for uptime value (for backward compatibility)
 * @deprecated Use getPackageDisplayName() instead
 * @param string $uptime Uptime string
 * @return string Display name
 */
function getUptimeName($uptime) {
    global $PACKAGES;
    
    // First try to find by limit_uptime
    foreach ($PACKAGES as $pkg) {
        if ($pkg['type'] === 'duration' && $pkg['limit_uptime'] === $uptime) {
            return $pkg['name'];
        }
    }
    
    // Fallback for legacy uptimes
    $names = [
        '30m' => '30 Minutes',
        '1h'  => '1 Hour',
        '3h'  => '3 Hours',
        '5h'  => '5 Hours',
        '6h'  => '6 Hours',
        '10h' => '10 Hours',
        '1d'  => '1 Day',
        '1w'  => '1 Week',
        '15d' => '15 Days',
        '30d' => '30 Days (Monthly)',
    ];
    return isset($names[$uptime]) ? $names[$uptime] : $uptime;
}

/**
 * Get all prices as array (for portal price list)
 * @return array Package prices keyed by display name
 */
function getAllPrices() {
    global $PACKAGES;
    $prices = [];
    foreach ($PACKAGES as $pkg) {
        $key = strtolower(str_replace(' ', '_', $pkg['name']));
        $prices[$key] = $pkg['price'];
    }
    return $prices;
}

/**
 * Get all packages as simple array for portal display
 * @return array Package info for portal
 */
function getPackagesForPortal() {
    global $PACKAGES, $PACKAGE_CATEGORIES;
    $result = [];
    
    foreach ($PACKAGE_CATEGORIES as $catId => $catName) {
        $categoryPackages = [];
        foreach ($PACKAGES as $pkg) {
            if ($pkg['category'] === $catId) {
                $displayName = $pkg['name'];
                if ($pkg['type'] === 'window' && isset($pkg['window_description'])) {
                    $displayName .= ' (' . $pkg['window_description'] . ')';
                }
                $categoryPackages[] = [
                    'id' => $pkg['id'],
                    'name' => $displayName,
                    'price' => $pkg['price']
                ];
            }
        }
        if (!empty($categoryPackages)) {
            $result[] = [
                'category' => $catName,
                'packages' => $categoryPackages
            ];
        }
    }
    
    return $result;
}

// ========================================
// QR CODE FUNCTIONS (from pricing_config.php)
// ========================================

/**
 * Generate QR code URL using QR Server API
 */
function generateQRCode($username, $password, $size = 100) {
    $data = "WiFi Login\nUser: $username\nPass: $password";
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}

/**
 * Generate WiFi QR Code (WIFI: format for auto-connect)
 */
function generateWifiQRCode($ssid, $password, $size = 100) {
    $data = "WIFI:T:WPA;S:{$ssid};P:{$password};;";
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}

$WIFI_SSID = 'MindspaceWiFi';

// ========================================
// ROUTEROS TIME-WINDOW ENFORCEMENT
// ========================================

/**
 * Generate RouterOS on-login script for time-window passes
 * 
 * This script checks if current time is within the allowed window:
 * 1. If outside window: disconnect user immediately
 * 2. If inside window: schedule disconnect at window end
 * 
 * @param array $pkg Package configuration
 * @param float $price Price for logging
 * @return string RouterOS script
 */
function generateWindowLoginScript($pkg, $price = 0) {
    $startTime = $pkg['window_start'];
    $endTime = $pkg['window_end'];
    $spansMidnight = $pkg['spans_midnight'] ?? false;
    $windowDesc = $pkg['window_description'] ?? '';
    
    // Convert times to minutes for comparison
    list($startHour, $startMin) = explode(':', $startTime);
    list($endHour, $endMin) = explode(':', $endTime);
    $startMinutes = intval($startHour) * 60 + intval($startMin);
    $endMinutes = intval($endHour) * 60 + intval($endMin);
    
    // Build the RouterOS script
    // The script uses RouterOS scripting language
    if ($spansMidnight) {
        // Window spans midnight (e.g., 18:00-05:00)
        // User is allowed if: currentTime >= startTime OR currentTime < endTime
        $script = <<<SCRIPT
:put (",window,{$price},{$windowDesc},,,$spansMidnight,");
{
:local currentTime [/system clock get time];
:local currentHour [:pick \$currentTime 0 2];
:local currentMin [:pick \$currentTime 3 5];
:local currentMinutes ((\$currentHour * 60) + \$currentMin);
:local startMinutes {$startMinutes};
:local endMinutes {$endMinutes};
:local allowed false;
:if (\$currentMinutes >= \$startMinutes) do={:set allowed true};
:if (\$currentMinutes < \$endMinutes) do={:set allowed true};
:if (\$allowed = false) do={
  :log warning ("Time-window pass: \$user denied - outside window {$windowDesc}");
  /ip hotspot active remove [find where user=\$user];
} else={
  :local endHour {$endHour};
  :local endMin {$endMin};
  :local schedTime ("\$endHour:\$endMin:00");
  :local schedDate [/system clock get date];
  :if (\$currentMinutes >= \$startMinutes) do={
    :local tomorrow [/system clock get date];
    :set schedDate \$tomorrow;
  };
  /system scheduler add name=("wnd_" . \$user) on-event=("/ip hotspot active remove [find where user=\$user]; /system scheduler remove [find where name=(\\\"wnd_\\\" . \\\"\$user\\\")]") start-time=\$schedTime interval=0 comment="Window pass auto-disconnect";
  :log info ("Time-window pass: \$user allowed until {$endTime}");
}
}
SCRIPT;
    } else {
        // Window within same day (e.g., 08:00-18:00)
        // User is allowed if: startTime <= currentTime < endTime
        $script = <<<SCRIPT
:put (",window,{$price},{$windowDesc},,,$spansMidnight,");
{
:local currentTime [/system clock get time];
:local currentHour [:pick \$currentTime 0 2];
:local currentMin [:pick \$currentTime 3 5];
:local currentMinutes ((\$currentHour * 60) + \$currentMin);
:local startMinutes {$startMinutes};
:local endMinutes {$endMinutes};
:if (\$currentMinutes < \$startMinutes || \$currentMinutes >= \$endMinutes) do={
  :log warning ("Time-window pass: \$user denied - outside window {$windowDesc}");
  /ip hotspot active remove [find where user=\$user];
} else={
  :local endHour {$endHour};
  :local endMin {$endMin};
  :local schedTime ("\$endHour:\$endMin:00");
  /system scheduler add name=("wnd_" . \$user) on-event=("/ip hotspot active remove [find where user=\$user]; /system scheduler remove [find where name=(\\\"wnd_\\\" . \\\"\$user\\\")]") start-time=\$schedTime interval=0 comment="Window pass auto-disconnect";
  :log info ("Time-window pass: \$user allowed until {$endTime}");
}
}
SCRIPT;
    }
    
    return $script;
}

/**
 * Get information about required RouterOS profiles for window passes
 * This helps with documentation and automatic profile creation
 * @return array Profile information
 */
function getRequiredWindowProfiles() {
    global $PACKAGES;
    $profiles = [];
    
    foreach ($PACKAGES as $pkg) {
        if ($pkg['type'] === 'window' && isset($pkg['profile_suffix'])) {
            $profiles[] = [
                'name' => $pkg['profile_suffix'],
                'package_id' => $pkg['id'],
                'description' => $pkg['name'] . ' - ' . ($pkg['window_description'] ?? ''),
                'window_start' => $pkg['window_start'],
                'window_end' => $pkg['window_end'],
                'spans_midnight' => $pkg['spans_midnight'] ?? false,
            ];
        }
    }
    
    return $profiles;
}
