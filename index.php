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
			// Router connection failed - log and show settings page for admin to fix
			error_log('Router connection error: ' . $connection['error']);
			if ($_SESSION['user_level'] == 1) {
				// Show settings page for admin to debug
				echo '<div class="alert alert-danger" style="margin:20px;">';
				echo '<strong>Router Connection Failed</strong><br>';
				echo 'Error: ' . htmlspecialchars($connection['error']) . '<br>';
				echo '<a href="settings.php" class="btn btn-primary">Check Router Settings</a>';
				echo '</div>';
				include_once('settings.php');
			} else {
				// Show generic error for non-admin users
				echo '<div class="alert alert-danger" style="margin:20px;">';
				echo '<strong>System Error</strong><br>';
				echo 'The system is currently unable to access the router. Please try again in a moment.';
				echo '</div>';
				// Still show home page shell so they can navigate
				include_once('home.php');
			}
		}
	}
	catch (Exception $e) {
		// Router exception - log and show error
		error_log('Router error: ' . $e->getMessage());
		if ($_SESSION['user_level'] == 1) {
			// Show settings page for admin to debug
			echo '<div class="alert alert-danger" style="margin:20px;">';
			echo '<strong>Router Connection Exception</strong><br>';
			echo 'Error: ' . htmlspecialchars($e->getMessage()) . '<br>';
			echo '<a href="settings.php" class="btn btn-primary">Check Router Settings</a>';
			echo '</div>';
			include_once('settings.php');
		} else {
			// Show generic error for non-admin users
			echo '<div class="alert alert-danger" style="margin:20px;">';
			echo '<strong>System Error</strong><br>';
			echo 'An error occurred while connecting to the router. Please try again.';
			echo '</div>';
			// Still show home page shell so they can navigate
			include_once('home.php');
		}
	}
}
?>