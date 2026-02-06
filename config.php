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
define('MOCK_MODE', true);

// ============ ROUTER CREDENTIALS ============
$host = "192.168.254.113";  // MikroTik router IP address
$user = "api";              // RouterOS API username  
$pass = "api";              // RouterOS API password
?>