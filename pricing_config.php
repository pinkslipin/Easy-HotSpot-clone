<?php
/**
 * Pricing Configuration for Café WiFi Vouchers
 * 
 * Edit the prices below to match your café's pricing.
 * The key is the uptime value, the value is the price in PHP (₱).
 */

$VOUCHER_PRICES = [
    '30m' => 10.00,   // 30 Minutes - ₱10
    '1h'  => 20.00,   // 1 Hour - ₱20
    '3h'  => 40.00,   // 3 Hours - ₱40
    '6h'  => 60.00,   // 6 Hours - ₱60
    '10h' => 80.00,   // 10 Hours - ₱80
    '1d'  => 100.00,  // 1 Day - ₱100
    '1w'  => 500.00,  // 1 Week - ₱500
];

/**
 * Voucher expiry settings
 * How many days until an unused voucher expires
 */
$VOUCHER_EXPIRY_DAYS = 30; // Vouchers expire 30 days after creation if unused

/**
 * Currency symbol
 */
$CURRENCY_SYMBOL = '₱';

/**
 * Café branding for vouchers
 */
$CAFE_NAME = 'Café WiFi';
$CAFE_TAGLINE = 'Fast & Reliable Internet';
$CAFE_CONTACT = ''; // Optional: phone or address

/**
 * Helper function to get price for a given uptime
 */
function getVoucherPrice($uptime) {
    global $VOUCHER_PRICES;
    return isset($VOUCHER_PRICES[$uptime]) ? $VOUCHER_PRICES[$uptime] : 0.00;
}

/**
 * Helper function to format price with currency
 */
function formatPrice($price) {
    global $CURRENCY_SYMBOL;
    return $CURRENCY_SYMBOL . number_format($price, 2);
}

/**
 * Helper function to get expiry date
 */
function getExpiryDate() {
    global $VOUCHER_EXPIRY_DAYS;
    return date('Y-m-d H:i:s', strtotime("+{$VOUCHER_EXPIRY_DAYS} days"));
}

/**
 * Get friendly name for uptime value
 */
function getUptimeName($uptime) {
    $names = [
        '30m' => '30 Minutes',
        '1h'  => '1 Hour',
        '3h'  => '3 Hours',
        '6h'  => '6 Hours',
        '10h' => '10 Hours',
        '1d'  => '1 Day',
        '1w'  => '1 Week',
    ];
    return isset($names[$uptime]) ? $names[$uptime] : $uptime;
}

/**
 * Generate QR code URL using QR Server API (free, no API key needed)
 * The QR code contains WiFi login credentials
 */
function generateQRCode($username, $password, $size = 100) {
    // Format: Simple text with credentials
    $data = "WiFi Login\nUser: $username\nPass: $password";
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}

/**
 * Generate WiFi QR Code (WIFI: format for auto-connect)
 * Note: This requires the hotspot SSID to be configured
 */
function generateWifiQRCode($ssid, $password, $size = 100) {
    // WIFI QR code format: WIFI:T:WPA;S:<SSID>;P:<PASSWORD>;;
    $data = "WIFI:T:WPA;S:{$ssid};P:{$password};;";
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}

/**
 * WiFi SSID for QR codes (configure this to your hotspot name)
 */
$WIFI_SSID = 'CafeWiFi'; // Change this to your actual WiFi SSID
?>
