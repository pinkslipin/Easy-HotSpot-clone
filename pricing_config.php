<?php
/**
 * Pricing Configuration for Café WiFi Vouchers
 * 
 * IMPORTANT: This file is now a COMPATIBILITY WRAPPER.
 * The actual package configuration is in packages_config.php
 * 
 * All new code should use packages_config.php directly.
 * This file maintains backward compatibility with existing code.
 */

// Include the new packages configuration (single source of truth)
require_once __DIR__ . '/packages_config.php';

/**
 * Legacy $VOUCHER_PRICES array for backward compatibility
 * This is auto-generated from packages_config.php
 * @deprecated Use getAllPackages() from packages_config.php instead
 */
$VOUCHER_PRICES = [];
foreach (getAllPackages() as $pkg) {
    if ($pkg['type'] === 'duration' && isset($pkg['limit_uptime'])) {
        $VOUCHER_PRICES[$pkg['limit_uptime']] = $pkg['price'];
    }
}
// Add window packages with their IDs as keys
foreach (getAllPackages() as $pkg) {
    if ($pkg['type'] === 'window') {
        $VOUCHER_PRICES[$pkg['id']] = $pkg['price'];
    }
}

// Note: $VOUCHER_EXPIRY_DAYS, $CURRENCY_SYMBOL, $CAFE_NAME, $CAFE_TAGLINE, $CAFE_CONTACT
// are now defined in packages_config.php

// Note: formatPrice(), getVoucherPrice(), getUptimeName(), getExpiryDate(), 
// generateQRCode(), generateWifiQRCode() are now defined in packages_config.php

// $WIFI_SSID is now defined in packages_config.php
$WIFI_SSID = 'CafeWiFi'; // Change this to your actual WiFi SSID
?>
