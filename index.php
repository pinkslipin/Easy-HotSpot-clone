<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="favicon.ico" type="image/x-icon"/>
<?php
// Suppress deprecation warnings for PHP 8.x compatibility
error_reporting(E_ALL & ~E_DEPRECATED);

// SECURITY: Use hardened session + send security headers
require_once 'security_helper.php';
secure_session_start();

// SECURITY: HTTP security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

//Check whether the session variables present or not, and assign them to Ordinary variables, if present.
if (!isset($_SESSION['user_level']) || (trim($_SESSION['user_level']) == '' || (trim($_SESSION['user_level']) >= 4))) {
    header("location:login.php");
}
?>

<?php include('header.php'); ?>
<?php include('dbconfig.php'); ?>
<?php 
require_once 'config.php';
require_once 'expiry_check.php';

// Auto-check for expired vouchers on each page load
checkExpiredVouchers();

// Check if we're in mock mode for development
if (defined('MOCK_MODE') && MOCK_MODE === true) {
	// Development mode - use mock router
	require_once 'mock_router.php';
	$util = new MockRouterUtil();
	$client = new MockClient();
	include_once('home.php');
} else {
	// Production mode - connect to real MikroTik using modern library
	require_once 'routeros_api.php';
	try {
		$connection = createRouterConnection($host, $user, $pass);
		if ($connection['success']) {
			$util = $connection['util'];
			$client = $connection['client'];
			include_once('home.php');
		} else {
			error_log('Router connection error: ' . $connection['error']);
			echo 'Error Accessing Data. Check router settings.';
			include_once('settings.php');
		}
	}
	catch (Exception $e) {
		error_log('Router error: ' . $e->getMessage());
		echo 'Error Accessing Data. Check router settings.';
		include_once('settings.php');
	}
}
?>