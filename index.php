<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="favicon.ico" type="image/x-icon"/>
<?php
// Suppress deprecation warnings for PHP 8.x compatibility
error_reporting(E_ALL & ~E_DEPRECATED);

//Start session
if ( !isset($_SESSION) ) session_start();
//Check whether the session variables present or not, and assign them to Ordinary variables, if present.
if (!isset($_SESSION['user_level']) || (trim($_SESSION['user_level']) == '' || (trim($_SESSION['user_level']) >= 4))) {
    header("location:login.php");
}
?>

<?php if ( !isset($_SESSION) ) session_start(); ?>

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
			echo "Error Accessing Data: " . $connection['error'];
			include_once('settings.php');
		}
	}
	catch (Exception $e) {
		echo "Error Accessing Data: " . $e->getMessage();
		include_once('settings.php');
	}
}
?>