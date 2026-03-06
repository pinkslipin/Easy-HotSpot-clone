<?php 
/**
 * MikroTik Router Configuration
 * 
 * MOCK_MODE: Set to true when you don't have a physical MikroTik router
 *            This allows testing voucher creation locally
 * 
 * When you get a real router, set MOCK_MODE to false and configure: 
 * - $host: Your MikroTik router's IP address
 * - $user: RouterOS username with API access
 * - $pass: RouterOS password
 * 
 * LIBRARY INFO:
 * This system uses evilfreelancer/routeros-api-php library
 * Compatible with RouterOS 6.43+ and RouterOS 7.x
 */

// Suppress deprecation warnings for older PHP compatibility
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

// ============ MOCK MODE ============
// Set to false when connecting to a real MikroTik router
define('MOCK_MODE', false);

// ============ ROUTER CREDENTIALS ============
$host = "192.168.88.1";       // MikroTik hAP ac3 hotspot gateway IP
$user = "hotspot-api";      // RouterOS API username  
$pass = "Pinkslippy1@";     // RouterOS API password

// ============ HOTSOFT INTEGRATION (Optional) ============
// Uncomment and set a secret key if you call hotsoft.php from an external
// hotel / POS system over the network.  While commented out, hotspot.php
// is restricted to localhost (127.0.0.1) calls only.
// define('HOTSOFT_API_KEY', 'change-this-to-a-strong-secret');
?>