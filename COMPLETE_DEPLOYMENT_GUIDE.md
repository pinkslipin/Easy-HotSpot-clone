# Complete MikroTik Hotspot Deployment Guide
**For MikroTik hAP ac3 + ZTE Converge ISP + Easy-HotSpot System**

---

## 🎯 Network Architecture Overview

```
Internet
   ↓
ZTE Converge Router (192.168.1.1) - Bridge Mode
   ↓
ether1 [WAN] ← MikroTik hAP ac3 (192.168.1.2) → [bridge/ether2-5] → Management PC
   ↓                                                ↓
192.168.88.0/24 (Management Network)     10.10.10.0/24 (Hotspot Network)
```

**Critical Concept:** 
- **Management PC**: Connects to ether2-5 (bridge) → 192.168.88.x → Full access, no hotspot login
- **Guest WiFi**: Connects to WiFi → 10.10.10.x → Requires voucher login

---

## 📋 Prerequisites Checklist

- [ ] Physical Hardware:
  - MikroTik hAP ac3 router
  - ZTE Converge router (500Mbps business plan)
  - Management PC with Ethernet port
  - 2x Ethernet cables
  
- [ ] Software Installed:
  - WinBox 4.0rc3+ on management PC
  - XAMPP with PHP 8.2.12+ on management PC
  - VS Code (optional, for editing)
  
- [ ] Credentials Ready:
  - MikroTik admin password (new, secure password)
  - Database admin password
  - API user password

---

## 🔌 STEP 1: Physical Setup

### Cable Connections:

1. **ZTE Converge** (LAN port) → **MikroTik ether1** (WAN)
2. **MikroTik ether5** → **Management PC** (Ethernet port)

### Verify ZTE Converge Settings:

- Ensure ZTE is in **Router Mode** (not Bridge Mode)
- DHCP should be enabled on ZTE
- Default: 192.168.1.1 gateway, DHCP range 192.168.1.2-254

### Test Basic Connectivity:

Connect PC directly to ZTE temporarily:
```powershell
ipconfig /all
# Should show: IP 192.168.1.x, Gateway 192.168.1.1
ping 8.8.8.8
# Should succeed
```

---

## 🔄 STEP 2: Reset MikroTik to Factory Defaults

### Via Hardware Reset Button:

1. **Unplug power** from MikroTik
2. **Press and hold** the reset button
3. **Plug in power** while holding reset
4. **Keep holding** until LED starts flashing (5-10 seconds)
5. **Release** button
6. Wait 1 minute for router to fully boot

### Via WinBox (if accessible):

```routeros
/system reset-configuration no-defaults=yes skip-backup=yes
```

**After reset:**
- All settings erased
- Default IP: 192.168.88.1
- Default username: admin
- Default password: (blank)

---

## 🖥️ STEP 3: Initial Router Access & Security

### Connect to Router:

1. **Physical**: PC connected to ether5
2. **Network Settings**: Set PC to obtain IP automatically
3. **Verify PC IP**:
   ```powershell
   ipconfig
   # Should show: 192.168.88.x
   ping 192.168.88.1
   # Should succeed
   ```

### Login via WinBox:

1. Open **WinBox**
2. Click **Neighbors** tab → Select **MikroTik** (192.168.88.1)
3. Login: `admin` | Password: (leave blank)
4. Click **Connect**

### First-Time Security Setup:

When WinBox opens, you'll see "Default Configuration" prompt:

**✅ SELECT:** 
- [x] **home-ap** (for access point with CAPsMAN disabled)

This creates:
- Bridge interface (ether2-5 + WiFi)
- Default DHCP server
- Basic firewall
- NAT masquerade

Click **OK** and wait 30 seconds.

---

## 🔐 STEP 4: Secure the Router

### Set Admin Password:

In WinBox terminal:
```routeros
/user set admin password=YourSecurePassword123!
```

### Create API User (for Easy-HotSpot system):

```routeros
/user add name=hotspot-api password=Pinkslippy1@ group=full
```

### Disable Unnecessary Services:

```routeros
/ip service disable telnet,ftp,www
/ip service set api address=192.168.88.0/24,10.10.10.0/24
/ip service set winbox address=192.168.88.0/24
```

This:
- Disables insecure services
- Restricts API access to local networks only
- Restricts WinBox to management network

---

## 🌐 STEP 5: Configure WAN Connection

### Set ether1 as WAN:

```routeros
/interface list member add interface=ether1 list=WAN
```

### Configure DHCP Client (for dynamic IP from ISP):

```routeros
/ip dhcp-client add interface=ether1 disabled=no use-peer-dns=yes add-default-route=yes
```

### Verify WAN Connection:

```routeros
/ip dhcp-client print
# Should show: status=bound, address=192.168.1.x

/ip route print
# Should show default route via 192.168.1.1

/ping 8.8.8.8 count=4
# Should succeed
```

**If ping fails:** Check ZTE Converge router, verify ether1 cable.

---

## 🏠 STEP 6: Configure Management Network

The default configuration already created:
- Bridge with ether2-5
- IP: 192.168.88.1/24
- DHCP server: 192.168.88.10-254

### Verify Bridge Configuration:

```routeros
/interface bridge port print
# Should show ether2-5 in bridge

/ip address print
# Should show 192.168.88.1/24 on bridge

/ip dhcp-server print
# Should show "defconf" server on bridge

/ip dhcp-server network print
# Should show 192.168.88.0/24 network
```

### Update DNS for Management Network:

```routeros
/ip dhcp-server network set 0 dns-server=8.8.8.8,8.8.4.4
```

### Test from PC:

```powershell
ipconfig /renew
ipconfig /all
# Should show: 192.168.88.x, Gateway 192.168.88.1, DNS 8.8.8.8

ping 192.168.88.1
ping 8.8.8.8
nslookup google.com
# All should succeed
```

**✅ CHECKPOINT:** You should now have full internet access on your PC!

---

## 📶 STEP 7: Create Hotspot Network

### Create Hotspot IP Pool:

```routeros
/ip pool add name=hs-pool ranges=10.10.10.100-10.10.10.254
```

### Add Hotspot IP to Bridge:

```routeros
/ip address add address=10.10.10.1/24 interface=bridge network=10.10.10.0
```

### Create Hotspot DHCP Server:

```routeros
/ip dhcp-server add name=hs-dhcp interface=bridge address-pool=hs-pool disabled=no

/ip dhcp-server network add address=10.10.10.0/24 gateway=10.10.10.1 dns-server=8.8.8.8,8.8.4.4
```

---

## 🎫 STEP 8: Configure MikroTik Hotspot

### Run Hotspot Setup:

```routeros
/ip hotspot setup
```

**Answer prompts as follows:**

```
hotspot interface: bridge
local address of network: 10.10.10.1/24
masquerade network: yes
address pool of network: hs-pool
select certificate: none
smtp server: 0.0.0.0
dns servers: 8.8.8.8,8.8.4.4
dns name: mindspace.hotspot
name of local hotspot user: (leave blank - press Enter)
password for the user: (leave blank - press Enter)
```

### Configure Hotspot Settings:

```routeros
/ip hotspot set 0 name=mindspace-hotspot idle-timeout=10m keepalive-timeout=5m

/ip hotspot profile set default shared-users=3 rate-limit=1M/1M login-by=http-chap,http-pap
```

This sets:
- Idle timeout: 10 minutes
- 3 devices per account
- Default rate limit: 1 Mbps (will be overridden by profiles)

---

## ⚡ STEP 9: Create Speed Profiles

These match your package pricing:

```routeros
/ip hotspot user profile add name=50pesos rate-limit=2M/2M shared-users=1
/ip hotspot user profile add name=100pesos rate-limit=5M/5M shared-users=1
/ip hotspot user profile add name=150pesos rate-limit=10M/10M shared-users=2
/ip hotspot user profile add name=200pesos rate-limit=20M/20M shared-users=2
/ip hotspot user profile add name=250pesos rate-limit=30M/30M shared-users=2
/ip hotspot user profile add name=300pesos rate-limit=40M/40M shared-users=3
/ip hotspot user profile add name=350pesos rate-limit=50M/50M shared-users=3
/ip hotspot user profile add name=400pesos rate-limit=75M/75M shared-users=3
/ip hotspot user profile add name=500pesos rate-limit=100M/100M shared-users=4
/ip hotspot user profile add name=1000pesos rate-limit=150M/150M shared-users=5
/ip hotspot user profile add name=2000pesos rate-limit=200M/200M shared-users=5
/ip hotspot user profile add name=5888pesos rate-limit=300M/300M shared-users=10
```

### Verify Profiles:

```routeros
/ip hotspot user profile print
```

---

## 🔥 STEP 10: Critical Firewall Configuration

**THIS IS THE MOST IMPORTANT STEP!** This prevents the hotspot from blocking your management PC.

### Add Management Network Bypass Rules:

```routeros
/ip firewall filter add chain=forward action=accept src-address=192.168.88.0/24 place-before=0 comment="Allow management network bypass"

/ip firewall filter add chain=input action=accept src-address=192.168.88.0/24 protocol=tcp dst-port=8728 place-before=0 comment="Allow API from management"

/ip firewall nat add chain=srcnat action=masquerade src-address=192.168.88.0/24 out-interface=ether1 comment="Management NAT"
```

**What this does:**
- Management network (192.168.88.x) bypasses ALL hotspot restrictions
- Management PC can access router API
- Management traffic is properly NATted to internet

### Verify Firewall Configuration:

```routeros
/ip firewall filter print
# First rules should be management bypass rules

/ip firewall nat print
# Should show management NAT rule
```

---

## 📡 STEP 11: Configure WiFi

### WiFi 1 (2.4GHz) - Hotspot Network:

```routeros
/interface wireless set wlan1 disabled=no mode=ap-bridge ssid="MindSpace WiFi" frequency=auto band=2ghz-b/g/n channel-width=20/40mhz-XX wireless-protocol=802.11 security-profile=default

/interface wireless security-profiles set default mode=none
```

### WiFi 2 (5GHz) - Hotspot Network:

```routeros
/interface wireless set wlan2 disabled=no mode=ap-bridge ssid="MindSpace WiFi 5G" frequency=auto band=5ghz-a/n/ac channel-width=20/40/80mhz-XXXX wireless-protocol=802.11 security-profile=default
```

### Add WiFi to Bridge:

```routeros
/interface bridge port add interface=wlan1 bridge=bridge
/interface bridge port add interface=wlan2 bridge=bridge
```

### Verify WiFi Active:

```routeros
/interface wireless print
# Both should show R (running)
```

---

## 💾 STEP 12: Database Configuration

### Import Database:

1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create database: `mikrotik`
3. Import file: `mikrotik_export.sql`
4. Verify 3 tables: `hotspot_users`, `hotspot_vouchers`, `audit_log`

### Default Admin Login:

- Username: `admin`
- Password: `admin123` (CHANGE THIS IMMEDIATELY!)

### Update Admin Password in Database:

```sql
UPDATE sysusers 
SET password = MD5('YourNewPassword') 
WHERE username = 'admin';
```

---

## ⚙️ STEP 13: Easy-HotSpot System Configuration

### Update config.php:

File: `c:\Users\MindSpace\Documents\GitHub\Easy-HotSpot-clone\config.php`

```php
<?php 
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

define('MOCK_MODE', false);

$host = "192.168.88.1";       // MikroTik gateway IP
$user = "hotspot-api";         // API username
$pass = "Pinkslippy1@";        // API password
?>
```

### Update dbconfig.php:

File: `c:\Users\MindSpace\Documents\GitHub\Easy-HotSpot-clone\dbconfig.php`

```php
<?php
	$DB_host = "localhost";
	$DB_user = "root";
	$DB_pass = "WildcatY3rn";
	$DB_name = "mikrotik";

	try {
		$DB_con = new PDO("mysql:host={$DB_host}", $DB_user, $DB_pass);
		$DB_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$dbname = "`".str_replace("`","``",$DB_name)."`";
		$DB_con->query("CREATE DATABASE IF NOT EXISTS $dbname");
		$DB_con->query("use $dbname");
	} catch(PDOException $e) {
		error_log('Database connection error: ' . $e->getMessage());
		die('Database connection failed. Please check configuration.');
	}
?>
```

---

## 🧪 STEP 14: System Testing

### Test 1: API Connection

1. Open: http://localhost/Easy-HotSpot-clone/test_connection.php
2. Should show: **"✅ Successfully connected to MikroTik router"**

If fails:
- Check [config.php](config.php) credentials
- Verify `/ip service print` shows API enabled
- Check PC can ping 192.168.88.1

### Test 2: Login to Admin Panel

1. Open: http://localhost/Easy-HotSpot-clone/login.php
2. Login: `admin` / `YourNewPassword`
3. Should redirect to dashboard

### Test 3: Create Test Voucher

1. Click **"+ Create User"**
2. Fill form:
   - Username: `test001`
   - Password: `test001`
   - Profile: `100pesos`
   - Limit: `4h` (4 hours)
   - Uptime: `4h`
3. Click **"Add User"**
4. Should show success message

### Test 4: Verify on Router

In WinBox terminal:
```routeros
/ip hotspot user print
# Should show test001 with 5M/5M rate limit
```

### Test 5: WiFi Guest Login

1. Connect phone/laptop to **"MindSpace WiFi"**
2. Should get IP: 10.10.10.x
3. Open browser → Should redirect to login page
4. Enter: `test001` / `test001`
5. Should get internet access
6. Check speed: ~5 Mbps (100pesos profile)

### Test 6: Management PC Not Affected

1. **Keep management PC on ether5**
2. Should have IP: 192.168.88.x
3. Should access internet **without hotspot login**
4. Can access admin panel directly

---

## 🔍 Troubleshooting Guide

### Problem: PC on ether5 can't access internet

**Symptoms:** "Destination net unreachable" when pinging gateway

**Cause:** Hotspot firewall blocking management network

**Solution:**
```routeros
# Remove any hotspot IP from bridge except 10.10.10.1
/ip address print
# If you see duplicate 192.168.88.1, remove it:
/ip address remove [find address~"192.168.88.1" and interface!="bridge"]

# Ensure management bypass rule is FIRST:
/ip firewall filter print
# If management bypass is not at top:
/ip firewall filter move [find comment="Allow management network bypass"] destination=0
```

### Problem: Router loses internet after configuration

**Cause:** DHCP lease expired on WAN

**Solution:**
```routeros
/ip dhcp-client print
# If status != "bound":
/ip dhcp-client renew 0

# Verify:
/ping 8.8.8.8
```

### Problem: Vouchers created but guests can't login

**Cause:** Hotspot not binding to correct interface or IP

**Solution:**
```routeros
/ip hotspot print detail
# Should show: interface=bridge, address=10.10.10.1/24

/ip hotspot host print
# Should list connected devices
```

### Problem: DHCP server shows INVALID

**Cause:** Overlapping IP pools or networks

**Solution:**
```routeros
/ip dhcp-server print
# If shows "I" flag:

/ip dhcp-server network print
# Check for duplicate networks

/ip pool print
# Check for overlapping ranges

# Remove duplicates
```

### Problem: DNS not working for guests

**Solution:**
```routeros
/ip dns print
# Should show: allow-remote-requests=yes

/ip dns set allow-remote-requests=yes servers=8.8.8.8,8.8.4.4

# Check DHCP network:
/ip dhcp-server network print detail
# Should show dns-server=8.8.8.8,8.8.4.4
```

---

## 📊 Monitoring & Maintenance

### Check Active Hotspot Users:

```routeros
/ip hotspot active print
```

### Check Hotspot Host Database:

```routeros
/ip hotspot host print
```

### View Logs:

```routeros
/log print where topics~"hotspot"
```

### Backup Configuration:

```routeros
/export file=backup-2026-02-11
```

Download from Files menu in WinBox.

---

## 🎯 Final Network Layout

### Physical Connections:
```
Internet → ZTE Converge (192.168.1.1)
              ↓
          ether1 (WAN - DHCP client)
              ↓
      MikroTik hAP ac3
              ↓
    ┌─────────┴─────────┐
    │      Bridge       │
    │  (192.168.88.1)   │
    │  (10.10.10.1)     │
    └──┬──┬──┬──┬──┬──┬┘
       │  │  │  │  │  │
    eth2 3  4  5  w1 w2
       │
  Management PC
 (192.168.88.x)
```

### Logical Networks:

| Network | Subnet | Gateway | DHCP Range | Purpose |
|---------|--------|---------|------------|---------|
| Management | 192.168.88.0/24 | 192.168.88.1 | .10-.254 | Admin PC, no hotspot |
| Hotspot | 10.10.10.0/24 | 10.10.10.1 | .100-.254 | Guest WiFi, voucher required |
| WAN | 192.168.1.0/24 | 192.168.1.1 | Dynamic | ISP connection |

---

## ✅ Deployment Checklist

- [ ] Hardware physically connected (ZTE → ether1, PC → ether5)
- [ ] Router reset to factory defaults
- [ ] Admin password changed
- [ ] API user created (hotspot-api)
- [ ] WAN connection active (ether1 gets IP from ZTE)
- [ ] Management network working (192.168.88.0/24)
- [ ] Hotspot network created (10.10.10.0/24)
- [ ] Hotspot service configured on bridge
- [ ] Speed profiles created (50pesos to 5888pesos)
- [ ] Firewall rules allow management bypass
- [ ] WiFi enabled and broadcasting
- [ ] Database imported and admin password changed
- [ ] config.php updated with correct credentials
- [ ] Test connection successful
- [ ] Admin panel accessible (http://localhost/Easy-HotSpot-clone)
- [ ] Test voucher created and verified
- [ ] Guest WiFi login tested successfully
- [ ] Management PC has internet without hotspot login

---

## 🚀 You're Live!

Your Easy-HotSpot system is now fully deployed and ready for business!

### Next Steps:

1. **Create bulk vouchers** for your packages
2. **Print voucher cards** with credentials
3. **Monitor usage** via admin panel
4. **Check logs** regularly for issues
5. **Backup configuration** weekly

### Support Resources:

- MikroTik Wiki: https://wiki.mikrotik.com/wiki/Manual:Hotspot
- Easy-HotSpot Issues: Check project documentation
- Router Command Reference: `/system resource print`

**Happy Hotspot Management! 🎉**
