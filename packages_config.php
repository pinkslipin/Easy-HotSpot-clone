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
 * - data_limit_gb: Default data limit in GB (0 = unlimited, auto-populates in voucher form)
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
        'data_limit_gb' => 3,
    ],
    'ind_1h_student' => [
        'id' => 'ind_1h_student',
        'name' => '1 Hour (Student)',
        'category' => 'individual',
        'price' => 39.00,
        'type' => 'duration',
        'limit_uptime' => '1h',
        'data_limit_gb' => 3,
    ],
    'ind_2h' => [
        'id' => 'ind_2h',
        'name' => '2 Hours',
        'category' => 'individual',
        'price' => 100.00,
        'type' => 'duration',
        'limit_uptime' => '2h',
        'data_limit_gb' => 6,
    ],
    'ind_3h' => [
        'id' => 'ind_3h',
        'name' => '3 Hours',
        'category' => 'individual',
        'price' => 138.00,
        'type' => 'duration',
        'limit_uptime' => '3h',
        'data_limit_gb' => 9,
    ],
    'ind_5h' => [
        'id' => 'ind_5h',
        'name' => '5 Hours',
        'category' => 'individual',
        'price' => 188.00,
        'type' => 'duration',
        'limit_uptime' => '5h',
        'data_limit_gb' => 15,
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
        'window_start' => '06:00',
        'window_end' => '18:00',
        'window_description' => '6AM - 6PM',
        'spans_midnight' => false,
        'profile_suffix' => 'DayPass',
        'data_limit_gb' => 15,
    ],
    'night_18_5' => [
        'id' => 'night_18_5',
        'name' => 'Night Pass',
        'category' => 'daily',
        'price' => 208.00,
        'type' => 'window',
        'window_start' => '18:00',
        'window_end' => '06:00',
        'window_description' => '6PM - 6AM',
        'spans_midnight' => true,
        'profile_suffix' => 'NightPass',
        'data_limit_gb' => 15,
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
        'data_limit_gb' => 15,
    ],
    
    // ==================
    // MINDSPACE UNLI PASSES
    // ==================
    'unli_3d' => [
        'id' => 'unli_3d',
        'name' => '3-Day Pass',
        'category' => 'unli',
        'price' => 810.00,
        'type' => 'duration',
        'limit_uptime' => '3d',
        'data_limit_gb' => 15,
    ],
    'unli_7d' => [
        'id' => 'unli_7d',
        'name' => '7-Day Pass',
        'category' => 'unli',
        'price' => 1519.00,
        'type' => 'duration',
        'limit_uptime' => '7d',
        'data_limit_gb' => 15,
    ],
    'unli_week' => [
        'id' => 'unli_week',
        'name' => 'Weekly Pass',
        'category' => 'unli',
        'price' => 1888.00,
        'type' => 'duration',
        'limit_uptime' => '1w',
        'data_limit_gb' => 15,
    ],
    'unli_15d' => [
        'id' => 'unli_15d',
        'name' => '15 Days Pass',
        'category' => 'unli',
        'price' => 2580.00,
        'type' => 'duration',
        'limit_uptime' => '15d',
        'data_limit_gb' => 15,
    ],
    'unli_month' => [
        'id' => 'unli_month',
        'name' => 'Monthly Pass',
        'category' => 'unli',
        'price' => 4980.00,
        'type' => 'duration',
        'limit_uptime' => '90d',
        'data_limit_gb' => 15,
    ],
    
    // ==================
    // BOARD REVIEWEE RATES
    // ==================
    'reviewee_day' => [
        'id' => 'reviewee_day',
        'name' => 'Day Pass (Reviewee)',
        'category' => 'reviewee',
        'price' => 290.00,
        'type' => 'window',
        'window_start' => '06:00',
        'window_end' => '18:00',
        'window_description' => '6AM - 6PM',
        'spans_midnight' => false,
        'profile_suffix' => 'RevieweeDay',
        'data_limit_gb' => 15,
    ],
    'reviewee_3d' => [
        'id' => 'reviewee_3d',
        'name' => '3-Day Pass (Reviewee)',
        'category' => 'reviewee',
        'price' => 804.00,
        'type' => 'duration',
        'limit_uptime' => '3d',
        'data_limit_gb' => 15,
    ],
];

/**
 * Category display names for UI grouping
 */
$PACKAGE_CATEGORIES = [
    'individual' => 'Individual Passes',
    'daily' => 'Daily Access (Time Window)',
    'unli' => 'Mindspace Unli Passes',
    'reviewee' => 'Board Reviewee Rates',
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
 * For window packages, calculates duration between window_start and window_end.
 * @param string $packageId Package ID
 * @return string|null limit-uptime value or null if not found
 */
function getPackageLimitUptime($packageId) {
    $pkg = getPackage($packageId);
    if (!$pkg) return null;
    
    if ($pkg['type'] === 'duration') {
        return $pkg['limit_uptime'];
    }
    
    // For window packages, calculate duration between window_start and window_end
    if ($pkg['type'] === 'window') {
        return calculateWindowUptime($pkg);
    }
    
    return null;
}

/**
 * Calculate actual uptime duration for a window package
 * @param array $pkg Package configuration
 * @return string RouterOS uptime format (e.g., '12h', '10h')
 */
function calculateWindowUptime($pkg) {
    if (!isset($pkg['window_start']) || !isset($pkg['window_end'])) {
        return '12h'; // Default fallback
    }
    
    $start = $pkg['window_start']; // e.g., "06:00"
    $end = $pkg['window_end'];     // e.g., "18:00"
    
    // Parse times
    [$startHour, $startMin] = explode(':', $start);
    [$endHour, $endMin] = explode(':', $end);
    
    $startSecs = (int)$startHour * 3600 + (int)$startMin * 60;
    $endSecs = (int)$endHour * 3600 + (int)$endMin * 60;
    
    // Calculate duration
    if ($pkg['spans_midnight'] ?? false) {
        // Window spans midnight: e.g., 18:00 to 06:00
        // Duration = (86400 - startSecs) + endSecs
        $durationSecs = (86400 - $startSecs) + $endSecs;
    } else {
        // Window within same day: e.g., 06:00 to 18:00
        $durationSecs = $endSecs - $startSecs;
    }
    
    // Convert seconds to RouterOS format (hh, mm, ss)
    $hours = intdiv($durationSecs, 3600);
    $mins = intdiv($durationSecs % 3600, 60);
    $secs = $durationSecs % 60;
    
    $result = '';
    if ($hours > 0) $result .= $hours . 'h';
    if ($mins > 0) $result .= $mins . 'm';
    if ($secs > 0) $result .= $secs . 's';
    
    return $result ?: '0s';
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
 * Generate RouterOS on-login script for time-window passes.
 *
 * Behaviour:
 *   - If the user logs in OUTSIDE the allowed window: kick immediately.
 *   - If the user logs in INSIDE the window: allow (RouterOS limit-uptime and
 *     the 5-minute ajax_expired.php cron enforce the window-end cutoff).
 *
 * Note: a RouterOS scheduler is intentionally NOT created here because
 * scheduler on-event scripts run without the $user variable in scope,
 * making self-removal unreliable.  The ajax_expired.php cron (every 5 min)
 * provides the window-end kick instead.
 *
 * @param array $pkg   Package configuration array from $PACKAGES
 * @param float $price Unused – kept for signature compatibility
 * @return string RouterOS script string
 */
function generateWindowLoginScript($pkg, $price = 0) {
    $startTime     = $pkg['window_start'];
    $endTime       = $pkg['window_end'];
    $spansMidnight = $pkg['spans_midnight'] ?? false;
    $windowDesc    = $pkg['window_description'] ?? '';

    list($startHour, $startMin) = explode(':', $startTime);
    list($endHour,   $endMin)   = explode(':', $endTime);
    $startSecs = (int)$startHour * 3600 + (int)$startMin * 60;
    $endSecs   = (int)$endHour   * 3600 + (int)$endMin   * 60;

    // Build script using string concatenation so PHP does not expand RouterOS
    // variables (e.g. $user, $curSecs) – all RouterOS vars stay literal.
    $readClock =
        ':local curH [:tonum [:pick [/system clock get time] 0 2]]; ' .
        ':local curM [:tonum [:pick [/system clock get time] 3 5]]; ' .
        ':local curSecs (($curH * 3600) + ($curM * 60)); ';

    $kickUser =
        ':log warning ("Window pass: $user denied - outside ' . $windowDesc . '"); ' .
        '/ip hotspot active remove [find where user=$user]; ';

    if ($spansMidnight) {
        // Window crosses midnight (e.g. 18:00–06:00).
        // User allowed if: curSecs >= startSecs  OR  curSecs < endSecs
        return
            $readClock .
            ':local startSecs ' . $startSecs . '; ' .
            ':local endSecs '   . $endSecs   . '; ' .
            ':local allowed false; ' .
            ':if ($curSecs >= $startSecs) do={ :set allowed true }; ' .
            ':if ($curSecs < $endSecs) do={ :set allowed true }; ' .
            ':if (!$allowed) do={ ' . $kickUser . '}';
    } else {
        // Window within same day (e.g. 06:00–18:00).
        // User allowed if: startSecs <= curSecs < endSecs
        return
            $readClock .
            ':local startSecs ' . $startSecs . '; ' .
            ':local endSecs '   . $endSecs   . '; ' .
            ':if ($curSecs < $startSecs || $curSecs >= $endSecs) do={ ' . $kickUser . '}';
    }
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

/**
 * Export packages for JavaScript (used in voucher form auto-population)
 * Returns JSON with id and data_limit_gb for each package
 * @return string JSON string safe for embedding in <script> tags
 */
function getPackagesForJavaScript() {
    global $PACKAGES;
    $packageData = [];
    
    foreach ($PACKAGES as $pkg) {
        $packageData[$pkg['id']] = [
            'id'             => $pkg['id'],
            'name'           => $pkg['name'],
            'data_limit_gb'  => $pkg['data_limit_gb'] ?? 0,
            'profile_suffix' => $pkg['profile_suffix'] ?? null,
        ];
    }
    
    return json_encode($packageData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
