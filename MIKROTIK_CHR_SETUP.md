# MikroTik CHR (Cloud Hosted Router) Setup Guide for Easy-HotSpot

This guide will help you set up a virtual MikroTik router in VirtualBox to test your café WiFi voucher system before purchasing physical hardware.

---

## Table of Contents
1. [Download MikroTik CHR](#1-download-mikrotik-chr)
2. [VirtualBox Setup](#2-virtualbox-setup)
3. [First Boot & Initial Access](#3-first-boot--initial-access)
4. [Configure Router for API Access](#4-configure-router-for-api-access)
5. [Configure Hotspot](#5-configure-hotspot)
6. [Test Connection from Easy-HotSpot](#6-test-connection-from-easy-hotspot)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Download MikroTik CHR

### Step 1.1: Get the CHR Image
1. Go to: https://mikrotik.com/download
2. Scroll down to **Cloud Hosted Router**
3. Download the **VDI** (VirtualBox) image
   - File: `chr-7.x.vdi.zip` (or latest stable version)
   - Example: `chr-7.16.vdi.zip`

### Step 1.2: Extract the Image
1. Extract the ZIP file to a folder (e.g., `C:\VMs\MikroTik\`)
2. You'll get a file like `chr-7.16.vdi`

> **Note:** CHR is free for up to 1 Mbps throughput (perfect for testing!)

---

## 2. VirtualBox Setup

### Step 2.1: Create New VM
1. Open VirtualBox
2. Click **New**
3. Configure:
   - **Name:** `MikroTik-CHR`
   - **Type:** `Linux`
   - **Version:** `Other Linux (64-bit)`
4. Click **Next**

### Step 2.2: Memory (RAM)
- **Recommended:** 256 MB (minimum 128 MB)
- Click **Next**

### Step 2.3: Hard Disk
1. Select **Use an existing virtual hard disk file**
2. Click the folder icon
3. Click **Add**
4. Browse to and select your extracted `chr-7.x.vdi` file
5. Click **Choose**
6. Click **Next** → **Finish**

### Step 2.4: Network Configuration (IMPORTANT!)

**Option A: Bridged Adapter (Recommended for testing)**
This puts the VM on your same network as your PC.

1. Select the VM → **Settings** → **Network**
2. **Adapter 1:**
   - Enable: ✓
   - Attached to: **Bridged Adapter**
   - Name: (select your active network adapter, e.g., "Realtek PCIe GbE")
3. Click **OK**

**Option B: Host-Only Network**
If bridged doesn't work, use this for isolated testing.

1. Go to **File** → **Host Network Manager**
2. Click **Create** to add a Host-Only network
3. Note the IP range (usually `192.168.56.0/24`)
4. In VM Settings → **Network** → **Adapter 1:**
   - Attached to: **Host-Only Adapter**
   - Name: Select the host-only network you created

### Step 2.5: Start the VM
1. Select `MikroTik-CHR`
2. Click **Start**
3. Wait for it to boot (takes about 10-30 seconds)

---

## 3. First Boot & Initial Access

### Step 3.1: Login to Console
When the VM boots, you'll see:
```
MikroTik Login:
```
- **Username:** `admin`
- **Password:** (leave blank, press Enter)

### Step 3.2: Accept License
You'll be asked to accept the license agreement. Type `y` and press Enter.

### Step 3.3: Find the Router's IP Address
Run this command in the router console:
```
/ip address print
```

If using DHCP (bridged mode), you should see an IP address assigned.
If no IP, add one manually:
```
/ip address add address=192.168.254.112/24 interface=ether1
```

### Step 3.4: Access via Web Browser (WinBox Alternative)
1. Open your browser
2. Go to: `http://<router-ip>` (e.g., `http://192.168.254.112`)
3. Login: `admin` / (no password)

---

## 4. Configure Router for API Access

### Step 4.1: Enable API Service
Run these commands in the RouterOS console:

```routeros
# Enable the API service
/ip service enable api

# Verify it's running
/ip service print where name=api
```

You should see:
```
# NAME    PORT  CERTIFICATE  ADDRESS                   
0 api     8728                0.0.0.0/0
```

### Step 4.2: Create API User
Create a dedicated user for the Easy-HotSpot system:

```routeros
# Create the api user with full permissions
/user add name=api password=api group=full

# Verify user was created
/user print
```

### Step 4.3: Configure Firewall (if needed)
Allow API access from your PC:

```routeros
# Allow API connections (port 8728)
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=accept comment="Allow API"

# Move the rule to the top (before any drop rules)
/ip firewall filter move [find comment="Allow API"] 0
```

### Step 4.4: Set Router Identity (Optional but recommended)
```routeros
/system identity set name="CafeHotspot-Test"
```

---

## 5. Configure Hotspot

### Step 5.1: Basic Hotspot Setup

```routeros
# Set up IP pool for hotspot clients
/ip pool add name=hotspot-pool ranges=10.10.10.100-10.10.10.254

# Add IP address to the hotspot interface
/ip address add address=10.10.10.1/24 interface=ether1

# Create DHCP server for hotspot
/ip dhcp-server network add address=10.10.10.0/24 gateway=10.10.10.1 dns-server=8.8.8.8
/ip dhcp-server add name=dhcp-hotspot address-pool=hotspot-pool interface=ether1

# Create the hotspot
/ip hotspot add name=cafe-hotspot interface=ether1 address-pool=hotspot-pool profile=default

# Enable the hotspot
/ip hotspot enable cafe-hotspot
```

### Step 5.2: Create User Profiles
Create profiles that match your pricing tiers:

```routeros
# 1 Hour profile (₱5)
/ip hotspot user profile add name=1hour rate-limit=2M/2M shared-users=1 session-timeout=1h

# 2 Hours profile (₱10)
/ip hotspot user profile add name=2hours rate-limit=3M/3M shared-users=1 session-timeout=2h

# 5 Hours profile (₱20)
/ip hotspot user profile add name=5hours rate-limit=5M/5M shared-users=1 session-timeout=5h

# 1 Day profile (₱50)
/ip hotspot user profile add name=1day rate-limit=5M/5M shared-users=1 session-timeout=1d

# 1 Week profile (₱200)
/ip hotspot user profile add name=1week rate-limit=5M/5M shared-users=1 session-timeout=1w

# View profiles
/ip hotspot user profile print
```

### Step 5.3: Create a Test User Manually
```routeros
# Create a test voucher user
/ip hotspot user add name=TEST001 password=pass123 profile=1hour

# View hotspot users
/ip hotspot user print
```

---

## 6. Test Connection from Easy-HotSpot

### Step 6.1: Update config.php
Edit your config.php with the CHR's IP address:

```php
<?php 
// Set to false to use real router
define('MOCK_MODE', false);

// Router credentials
$host = "192.168.254.112";  // Your CHR's IP address
$user = "api";              // API username
$pass = "api";              // API password
?>
```

### Step 6.2: Test Connection
1. Make sure XAMPP is running
2. Open: http://localhost/easy-hotspot/test_connection.php
3. Click **Test Connection**

### Step 6.3: Expected Results
If successful, you'll see:
- ✓ **Connection Successful!**
- Router Identity: **CafeHotspot-Test**

### Step 6.4: Test Voucher Creation
1. Go to: http://localhost/easy-hotspot/
2. Login with your admin account
3. Create a batch of vouchers
4. Check if they appear in the router:

In RouterOS console:
```routeros
/ip hotspot user print
```

---

## 7. Troubleshooting

### Problem: "Connection timed out"
**Solutions:**
1. Verify the router IP is correct: `/ip address print`
2. Check if API service is running: `/ip service print`
3. Ping the router from your PC: `ping 192.168.254.112`
4. Check VirtualBox network mode (should be Bridged)

### Problem: "Connection refused"
**Solutions:**
1. Enable API service: `/ip service enable api`
2. Check firewall rules: `/ip firewall filter print`
3. Add firewall rule: `/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=accept`

### Problem: "Authentication failed"
**Solutions:**
1. Verify user exists: `/user print`
2. Check password: `/user set api password=api`
3. Verify user group: `/user print detail`

### Problem: "Unable to add hotspot user"
**Solutions:**
1. Check if hotspot is configured: `/ip hotspot print`
2. Check if profile exists: `/ip hotspot user profile print`
3. Verify the profile name in your code matches exactly

### Problem: Can't ping the router
**Solutions:**
1. Try restarting the VM
2. Check VirtualBox network adapter settings
3. Try Host-Only network instead of Bridged
4. Check Windows Firewall settings

---

## Quick Reference Commands

### Common RouterOS Commands
```routeros
# View system info
/system resource print

# View identity
/system identity print

# View all hotspot users
/ip hotspot user print

# View active hotspot sessions
/ip hotspot active print

# View hotspot profiles
/ip hotspot user profile print

# Remove a user
/ip hotspot user remove [find name="TEST001"]

# Remove all users
/ip hotspot user remove [find]

# Reboot router
/system reboot
```

### Quick Setup Script
Copy and paste this entire block to quickly set up a test router:

```routeros
# Quick Setup Script for Easy-HotSpot Testing
/system identity set name="CafeHotspot-Test"
/ip service enable api
/user add name=api password=api group=full
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=accept comment="Allow API"
/ip hotspot user profile add name=1hour rate-limit=2M/2M shared-users=1 session-timeout=1h
/ip hotspot user profile add name=2hours rate-limit=3M/3M shared-users=1 session-timeout=2h
/ip hotspot user profile add name=5hours rate-limit=5M/5M shared-users=1 session-timeout=5h
/ip hotspot user profile add name=1day rate-limit=5M/5M shared-users=1 session-timeout=1d
/ip hotspot user profile add name=1week rate-limit=5M/5M shared-users=1 session-timeout=1w
```

---

## Next Steps

1. ✅ Virtual router working
2. ⬜ Test all voucher operations (create, delete, batch delete)
3. ⬜ Test with actual hotspot clients (connect phone to hotspot)
4. ⬜ Purchase physical MikroTik router when ready
5. ⬜ Apply same configuration to physical hardware

---

## Recommended Physical Hardware

For a café environment in Cebu, consider:

### Budget Option (~₱3,000-5,000)
- **MikroTik hAP lite (RB941-2nD)**
- Good for: Small café, up to 20 users

### Mid-Range Option (~₱5,000-8,000)
- **MikroTik hAP ac² (RBD52G-5HacD2HnD-TC)**
- Good for: Medium café, dual-band WiFi, up to 50 users

### Best Option (~₱10,000-15,000)
- **MikroTik hAP ax³ (C53UiG+5HPaxD2HPaxD)**
- Good for: Large café, WiFi 6, up to 100 users

All these routers support the same API used by Easy-HotSpot!

---

*Last Updated: January 2026*
*For Easy-HotSpot Café WiFi Voucher System - Cebu, Philippines*
