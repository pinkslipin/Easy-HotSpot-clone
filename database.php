<?php
require_once 'dbconfig.php';

$stmt = $DB_con->prepare("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
$stmt->execute(array());

$stmt = $DB_con->prepare("SET time_zone = '+05:30'");
$stmt->execute(array());

$stmt = $DB_con->prepare("CREATE TABLE IF NOT EXISTS `hotspot_users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(200) NOT NULL,
  `date_added` date NOT NULL,
  `firstname` varchar(30) NOT NULL,
  `lastname` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `created_at` datetime NOT NULL,
  `username` varchar(30) NOT NULL,
  `user_level` int(11) NOT NULL DEFAULT '3',
  `user_group` int(1) NOT NULL,
  `image_path` varchar(50) NOT NULL,
  `thumb_path` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8");
try { $stmt->execute(array()); } catch (Exception $e) { /* Table may already exist */ }

$stmt = $DB_con->prepare("CREATE TABLE IF NOT EXISTS `hotspot_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(30) DEFAULT NULL,
  `creator` int(3) DEFAULT NULL,
  `user_name` varchar(30) DEFAULT NULL,
  `password` varchar(30) DEFAULT NULL,
  `printed_times` int(3) DEFAULT NULL,
  `printed_last` varchar(30) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `group_of` int(4) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `limit_uptime` varchar(30) DEFAULT NULL,
  `limit_bytes` varchar(30) DEFAULT NULL,
  `profile` varchar(30) DEFAULT NULL,
  `uid` VARCHAR(30) NOT NULL,
  `batch_id` VARCHAR(50) DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `expires_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8");
try { $stmt->execute(array()); } catch (Exception $e) { /* Table may already exist */ }

// Add new columns if they dont exist (for existing databases)
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `batch_id` VARCHAR(50) DEFAULT NULL");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) DEFAULT 0.00");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `expires_on` DATETIME DEFAULT NULL");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }

// Package-based pricing columns (added for new pricing system)
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `package_id` VARCHAR(50) DEFAULT NULL");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `package_name` VARCHAR(100) DEFAULT NULL");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }
$stmt = $DB_con->prepare("ALTER TABLE `hotspot_vouchers` ADD COLUMN IF NOT EXISTS `package_type` VARCHAR(20) DEFAULT NULL");
try { $stmt->execute(array()); } catch (Exception $e) { /* Column may already exist */ }

echo "<h2 style='color: green;'>✓ Database initialized successfully!</h2>";
echo "<p><a href='reset_admin.php'>Next: Reset Admin Password</a></p>";
?>