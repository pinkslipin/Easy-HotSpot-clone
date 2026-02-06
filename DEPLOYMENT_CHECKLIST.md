# 🚀 Easy-HotSpot Deployment Checklist

## Client Transfer Guide — February 8, 2026

**From:** Your development PC  
**To:** Client's refurbished laptop  
**Hardware:** Converge FiberX Business 400Mbps + MikroTik hAP ac3 + Laptop  
**Project:** 279 files, ~6.4MB

---

## PHASE 0: Prepare on YOUR PC (Do This Tonight)

### 0.1 Export the Database

Open PowerShell on your dev machine:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root mikrotik > C:\temp\wrdmstr\Easy-HotSpot-clone\mikrotik_export.sql
```

This exports all 3 tables:
- `hotspot_users` (1 row — admin account, bcrypt-hashed password)
- `hotspot_vouchers` (empty — clean slate for client)
- `audit_log` (50 rows — can be cleared on client)

### 0.2 Copy Project to USB Drive

```powershell
# Plug in USB drive (assume it mounts as D:)
Copy-Item -Recurse "C:\temp\wrdmstr\Easy-HotSpot-clone" "D:\Easy-HotSpot"
```

**Verify the USB has these critical files:**

| File | Why It's Critical |
|------|-------------------|
| `security_helper.php` | All auth, CSRF, bcrypt, sanitization |
| `config.php` | Router connection settings (currently MOCK_MODE=true) |
| `dbconfig.php` | Database credentials |
| `packages_config.php` | Voucher pricing/packages |
| `.htaccess` | Blocks access to sensitive files |
| `mikrotik_export.sql` | Database with admin account |
| `vendor/` folder | Composer dependencies (routeros-api-php library) |

### 0.3 What to Bring

```
□ USB drive with Easy-HotSpot project + database export
□ Ethernet cable (Cat5e or Cat6, at least 1 meter)
□ This checklist (print it or keep it on your phone)
□ Your phone (to test WiFi hotspot as a client)
□ WinBox portable (download from https://mikrotik.com/download — grab winbox64.exe, put on USB)
```

---

## PHASE 1: Inspect the Client's Laptop

**Time estimate: 15 minutes**

### 1.1 Physical Inspection

```
□ Power on — check screen for dead pixels, discoloration
□ Test all USB ports (plug in a USB drive)
□ Test ethernet port (plug in cable, check link light)
□ Test keyboard — every key, including function keys
□ Test trackpad — click, scroll, multi-finger gestures
□ Check battery — does it hold charge? (Settings → System → Power & Battery)
□ Check WiFi adapter works (connect to a phone hotspot briefly)
□ Listen for unusual noises (fan grinding, clicking HDD)
```

### 1.2 System Verification

```
□ Confirm Windows 10 or 11 (Settings → System → About)
□ Confirm Windows is ACTIVATED (Settings → System → Activation)
□ Check RAM: Win+R → "msinfo32" → "Installed Physical Memory"
   Minimum: 4GB | Recommended: 8GB
□ Check storage type: Win+R → "dfrgui" → Look for "Solid state drive"
   SSD strongly preferred. HDD will work but slower.
□ Check available disk space: needs at least 5GB free
□ Run Windows Update → install ALL updates → restart → repeat until clean
```

### 1.3 Security Baseline

```
□ Windows Defender is ON (Settings → Privacy & Security → Windows Security)
□ Firewall is ON
□ Create a non-admin Windows user for daily use (optional but recommended)
□ Set a strong Windows login password
```

---

## PHASE 2: Install Software on Client Laptop

**Time estimate: 20–30 minutes**

### 2.1 XAMPP (Web Server + PHP + MySQL)

| Detail | Value |
|--------|-------|
| **Download** | https://www.apachefriends.org/download.html |
| **Version** | 8.1.x or 8.2.x (must be PHP 8.0+) |
| **Install path** | `C:\xampp` (default) |
| **Components needed** | Apache, MySQL, PHP (uncheck others like Mercury, FileZilla, Tomcat) |

**After install:**
```
□ Open XAMPP Control Panel (run as Administrator)
□ Start Apache — verify port 80 is not blocked (Skype, IIS can conflict)
□ Start MySQL — verify port 3306 is not blocked
□ Open http://localhost in browser — should see XAMPP dashboard
□ Click "phpMyAdmin" — should open without errors
```

**If port 80 is taken:**
```
Open C:\xampp\apache\conf\httpd.conf
Find: Listen 80
Change to: Listen 8080
Then access via http://localhost:8080
```

### 2.2 VS Code (Code Editor)

| Detail | Value |
|--------|-------|
| **Download** | https://code.visualstudio.com/download |
| **Version** | Latest stable |
| **Why** | For config edits, debugging, Copilot access |

**Extensions to install in VS Code:**
```
□ GitHub Copilot (if client has subscription)
□ PHP Intelephense (for PHP syntax highlighting)
```

### 2.3 WinBox (MikroTik Management)

| Detail | Value |
|--------|-------|
| **Download** | https://mikrotik.com/download (scroll to "Useful tools and utilities") |
| **File** | winbox64.exe (portable, no install needed) |
| **Why** | GUI for configuring the hAP ac3 router |

Put `winbox64.exe` on the Desktop or in a `C:\Tools\` folder.

### 2.4 PuTTY (Optional — SSH Terminal)

| Detail | Value |
|--------|-------|
| **Download** | https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html |
| **Why** | Backup option for router access via SSH |

### 2.5 Web Browser

```
□ Chrome or Firefox (latest version) — for accessing the hotspot management panel
□ Set http://localhost/hotspot/ as a bookmarked tab
```

---

## PHASE 3: Deploy Easy-HotSpot to Client Laptop

**Time estimate: 15 minutes**

### 3.1 Copy Project Files

```powershell
# From USB drive (assume D:)
Copy-Item -Recurse "D:\Easy-HotSpot" "C:\xampp\htdocs\hotspot"
```

### 3.2 Import Database

```powershell
# Make sure MySQL is running in XAMPP first
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS mikrotik;"
C:\xampp\mysql\bin\mysql.exe -u root mikrotik < "C:\xampp\htdocs\hotspot\mikrotik_export.sql"
```

**Verify import:**
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "USE mikrotik; SHOW TABLES; SELECT username FROM hotspot_users;"
```

Expected output:
```
+--------------------+
| Tables_in_mikrotik |
+--------------------+
| audit_log          |
| hotspot_users      |
| hotspot_vouchers   |
+--------------------+

+----------+
| username |
+----------+
| admin    |
+----------+
```

### 3.3 Verify Web Access

```
□ Open browser → http://localhost/hotspot/
□ Should see login page with CSRF token
□ Login with: admin / admin
□ Should redirect to main dashboard
□ Verify voucher creation form loads
□ Verify no PHP errors displayed
```

### 3.4 Test Security

```
□ http://localhost/hotspot/dbconfig.php → should show 403 Forbidden
□ http://localhost/hotspot/config.php → should show 403 Forbidden
□ http://localhost/hotspot/security_helper.php → should show 403 Forbidden
□ http://localhost/hotspot/composer.json → should show 403 Forbidden
□ Try accessing any ajax_ endpoint without login → should be blocked
```

---

## PHASE 4: Configure the MikroTik hAP ac3

**Time estimate: 30–45 minutes**

### 4.1 Physical Setup

```
□ Unbox the hAP ac3
□ Connect power adapter
□ Connect Converge ONU LAN port → hAP ac3 ether1 (internet/WAN port)
□ Connect laptop ethernet → hAP ac3 ether2 (any LAN port)
□ Wait 30 seconds for boot
```

### 4.2 First Access via WinBox

```
□ Open winbox64.exe
□ Click "Neighbors" tab — the hAP ac3 should appear
□ Click on its MAC address
□ Login: admin / (blank password)
□ Accept the license if prompted
```

### 4.3 Initial Security (DO THIS FIRST!)

Run these in WinBox terminal (or the terminal tab):

```routeros
# Set a strong admin password IMMEDIATELY
/user set admin password=Ch4ng3Th1sN0w!2026

# Create a dedicated API user for Easy-HotSpot
/user add name=hotspot-api password=H0tsp0tAP1$ecure! group=full

# Set router identity
/system identity set name="ClientHotspot"

# Disable services you don't need (attack surface reduction)
/ip service disable telnet,ftp,www-ssl,api-ssl,ssh

# Enable only what we need
/ip service enable api,www,winbox
```

**⚠️ WRITE DOWN THE PASSWORDS. Store them in a secure location (not a text file on the desktop).**

### 4.4 Check RouterOS Version

```routeros
/system resource print
```

Look for `version:` — must be 6.43 or higher (hAP ac3 ships with 7.x).

**If update available:**
```routeros
/system package update check-for-updates
/system package update install
# Router will reboot — wait 1–2 minutes, reconnect via WinBox
```

### 4.5 Configure Internet (Converge Connection)

**Option A: If Converge ONU is in BRIDGE MODE (preferred):**
```routeros
# You need PPPoE credentials from Converge
/interface pppoe-client add name=pppoe-converge interface=ether1 user=YOUR_PPPOE_USER password=YOUR_PPPOE_PASS disabled=no add-default-route=yes use-peer-dns=yes

# Verify connection
/interface pppoe-client print
# Status should show "connected"
/ping 8.8.8.8 count=5
```

**Option B: If Converge ONU stays in ROUTER MODE (default):**
```routeros
# ONU handles PPPoE, hAP ac3 gets IP via DHCP on ether1
/ip dhcp-client add interface=ether1 disabled=no

# Verify
/ip dhcp-client print
# Should show "bound" status with an IP
/ping 8.8.8.8 count=5
/ping google.com count=5
```

**⚠️ IMPORTANT: Call Converge and ask if they can set the ONU to bridge mode. Double-NAT (Option B) works but can cause issues with VoIP, gaming, and some applications. For a business, bridge mode is cleaner.**

### 4.6 Configure Hotspot

```routeros
# Create IP pool for hotspot clients (customers)
/ip pool add name=hs-pool ranges=10.10.10.100-10.10.10.254

# Assign hotspot IP to the bridge (covers WiFi + LAN ports)
/ip address add address=10.10.10.1/24 interface=bridge comment="Hotspot network"

# DHCP for hotspot clients
/ip dhcp-server network add address=10.10.10.0/24 gateway=10.10.10.1 dns-server=8.8.8.8,8.8.4.4
/ip dhcp-server add name=dhcp-hotspot address-pool=hs-pool interface=bridge disabled=no

# NAT masquerade (so hotspot clients can reach internet)
/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1 comment="Hotspot NAT"

# Create the hotspot server
/ip hotspot add name=client-hotspot interface=bridge address-pool=hs-pool profile=default disabled=no

# Firewall: allow API from LAN only (port 8728)
/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=10.10.10.0/24 action=accept comment="Allow Hotspot API"
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=drop comment="Block external API"

# Create user profiles matching your pricing tiers
/ip hotspot user profile add name=1hour rate-limit=5M/5M shared-users=1
/ip hotspot user profile add name=2hours rate-limit=5M/5M shared-users=1
/ip hotspot user profile add name=5hours rate-limit=10M/10M shared-users=1
/ip hotspot user profile add name=whole-day rate-limit=10M/10M shared-users=1
/ip hotspot user profile add name=1week rate-limit=10M/10M shared-users=1
```

### 4.7 Configure WiFi

```routeros
# Set WiFi names and passwords for the management network vs hotspot
# 2.4 GHz (wider range, slower)
/interface wireless set wlan1 ssid="ClientWiFi" mode=ap-bridge band=2ghz-b/g/n

# 5 GHz (shorter range, faster)
/interface wireless set wlan2 ssid="ClientWiFi-5G" mode=ap-bridge band=5ghz-a/n/ac
```

**Note:** The hotspot captive portal will intercept connections automatically. Clients connect to WiFi → get redirected to login page → enter voucher credentials.

---

## PHASE 5: Connect Easy-HotSpot to the Real Router

**Time estimate: 10 minutes**

### 5.1 Update config.php

Open `C:\xampp\htdocs\hotspot\config.php` in VS Code:

```php
// Change this:
define('MOCK_MODE', true);

// To this:
define('MOCK_MODE', false);

// Update credentials to match what you set in Step 4.3:
$host = "10.10.10.1";              // Router's hotspot IP
$user = "hotspot-api";             // API user you created
$pass = "H0tsp0tAP1$ecure!";      // API user password
```

### 5.2 Test Connection

```
□ Login to http://localhost/hotspot/
□ Go to http://localhost/hotspot/test_connection.php
□ Click "Test Connection"
□ Should show: ✓ Connection Successful! Router Identity: ClientHotspot
□ Click "Test Hotspot Operations"
□ Should list the profiles you created (1hour, 2hours, etc.)
□ Click "Test Ports"
□ Port 8728 should show ✓
```

### 5.3 Create First Real Voucher

```
□ Go to main dashboard
□ Create a SINGLE test voucher (username: test001, profile: 1hour)
□ On WinBox terminal: /ip hotspot user print
□ Verify "test001" appears in the router's user list
□ Connect your PHONE to the hotspot WiFi
□ Should get redirected to captive portal login page
□ Enter test001 credentials
□ Should get internet access
□ Check speed on fast.com — should be within the profile's rate-limit
□ Done? Delete the test user via the dashboard
```

---

## PHASE 6: Production Hardening

**Time estimate: 15 minutes**

### 6.1 Change the Default Admin Password

```
□ Login to http://localhost/hotspot/
□ Click on your username or go to System Users
□ Change password from "admin" to something strong
□ Use format: 1 uppercase + 1 lowercase + 1 number + 1 symbol + 8 chars minimum
   Example: Cl1entH0t$pot!2026
□ TEST the new password by logging out and back in
□ WRITE IT DOWN for the client
```

### 6.2 Create Client's User Account

```
□ Go to System Users → Add User
□ Username: (client's chosen name)
□ User Level: 2 (Unit Head — can create vouchers but can't manage system users)
□ Status: Active
□ Note the temporary password shown
□ Have the client login and change their password immediately
```

### 6.3 Set MySQL Password (Currently Blank!)

```powershell
# In XAMPP, MySQL root has no password. Fix this:
C:\xampp\mysql\bin\mysql.exe -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'MyS3cur3DBpa$$!';"
```

Then update `C:\xampp\htdocs\hotspot\dbconfig.php`:
```php
$DB_pass = "MyS3cur3DBpa$$!";    // Was empty ""
```

Also update phpMyAdmin config so it still works:
```
Open C:\xampp\phpMyAdmin\config.inc.php
Find: $cfg['Servers'][$i]['password'] = '';
Change to: $cfg['Servers'][$i]['password'] = 'MyS3cur3DBpa$$!';
```

### 6.4 Windows Power Settings

```
□ Settings → System → Power & sleep
□ Screen: 15 minutes (on battery), 30 minutes (plugged in)
□ Sleep: NEVER (on battery), NEVER (plugged in)
□ Advanced: Disable hibernate (powercfg /h off in admin PowerShell)
```

### 6.5 XAMPP Auto-Start (So It Survives Reboots)

```
□ Open XAMPP Control Panel → Config (top right)
□ Check: "Autostart Apache"
□ Check: "Autostart MySQL"
□ Also check: "Start Control Panel Minimized"
□ Create a shortcut to XAMPP Control Panel in: shell:startup
   (Win+R → "shell:startup" → paste shortcut there)
```

### 6.6 Windows Firewall

```
□ Ensure port 80 (Apache) is allowed for INBOUND on Private networks only
□ Ensure port 3306 (MySQL) is BLOCKED from external access
□ The hotspot management panel should ONLY be accessible from localhost
   (Customers on WiFi should NOT be able to reach http://laptop-ip/hotspot/)
```

To block external access to the web panel, add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<Directory "C:/xampp/htdocs/hotspot">
    Require local
</Directory>
```

Then restart Apache.

---

## PHASE 7: Verify Everything Works

### 7.1 Full Test Matrix

| Test | How | Expected Result |
|------|-----|-----------------|
| Login | Browser → localhost/hotspot | Login page loads, CSRF token present |
| Auth | Login as admin | Redirects to dashboard |
| Create voucher | Add Single User | Voucher appears in dashboard AND router |
| Batch create | Add Multiple Users (5) | 5 vouchers created, visible in voucher print |
| Print vouchers | Voucher menu → Print | Printable voucher cards render |
| Phone connects | Connect phone to hotspot WiFi | Captive portal appears |
| Phone login | Enter voucher credentials | Internet access granted |
| Speed test | fast.com on phone | Speed within profile rate-limit |
| Voucher expires | Wait for time limit | User gets disconnected |
| Delete user | Remove from dashboard | Removed from router too |
| Batch delete | Delete batch | All vouchers in batch removed |
| Security | Access dbconfig.php directly | 403 Forbidden |
| Security | Access without login | Redirected to login |
| Dashboard | Sales dashboard | Shows today's voucher stats |
| Password change | System Users → Change Pass | Password updates, can re-login |
| Backup | Settings → Backup | Database backup creates .sql file |

### 7.2 Stress Test (Optional but Recommended)

```
□ Create 50 vouchers in one batch
□ Connect 3–5 devices to hotspot simultaneously
□ All should get captive portal and internet after login
□ Check router CPU: /system resource print (should be under 50%)
□ Check laptop RAM usage in Task Manager (should be under 70%)
```

---

## PHASE 8: Client Handoff

### 8.1 Documents to Give the Client

```
□ Admin username and password (written on paper, not digital)
□ MySQL root password
□ MikroTik admin password
□ MikroTik API user password
□ Converge PPPoE credentials (if bridge mode)
□ This checklist (leave a printed copy)
□ Quick-reference card: "How to create vouchers" (1-page cheat sheet)
```

### 8.2 Train the Client On

```
□ How to create single vouchers
□ How to create batch vouchers
□ How to print vouchers
□ How to check sales dashboard
□ How to remove expired users
□ What to do if "Error Accessing Data" appears (restart Apache + MySQL)
□ What to do if internet goes down (check Converge, restart router)
□ Who to call for support (your contact info)
```

### 8.3 Ongoing Maintenance

```
□ Clear audit logs monthly (Settings → Clear Logs)
□ Database backup weekly (Settings → Backup)
□ Windows Update monthly (schedule for off-hours)
□ RouterOS update quarterly (System → Packages → Check for Updates)
□ Check XAMPP is running after any restart
```

---

## 📋 Copilot Verification Prompt

**Copy-paste this entire prompt into GitHub Copilot on the CLIENT's laptop after Phase 3 is complete. It will verify the deployment is correct:**

---

```
You are a cybersecurity expert and systems engineer. I just deployed the Easy-HotSpot cafe WiFi voucher system on this client laptop. I need you to verify everything is working correctly.

The setup is:
- XAMPP with PHP 8.1+ and MariaDB on this Windows laptop
- Easy-HotSpot project at C:\xampp\htdocs\hotspot\
- MikroTik hAP ac3 router connected on the LAN
- Database "mikrotik" with tables: hotspot_users, hotspot_vouchers, audit_log

Please do the following checks IN ORDER:

1. **PHP Syntax Check**: Run `C:\xampp\php\php.exe -l` against every .php file in C:\xampp\htdocs\hotspot\ (excluding vendor/ and PEAR2/). Report any syntax errors.

2. **Security File Check**: Verify these files exist and are not empty:
   - security_helper.php (must contain csrf_token, require_auth, secure_password_hash functions)
   - .htaccess (must contain FilesMatch rules blocking dbconfig.php, config.php, security_helper.php)

3. **Config Check**: Read config.php and tell me:
   - Is MOCK_MODE true or false?
   - What IP/user/pass is configured for the router?
   - Does it have display_errors=0?

4. **Database Check**: Connect to MySQL and verify:
   - Database "mikrotik" exists
   - All 3 tables exist (hotspot_users, hotspot_vouchers, audit_log)
   - Admin user exists in hotspot_users
   - Admin password is bcrypt-hashed (starts with $2y$)

5. **HTTP Security Test**: Use PowerShell Invoke-WebRequest to test:
   - http://localhost/hotspot/login.php returns 200
   - http://localhost/hotspot/dbconfig.php returns 403
   - http://localhost/hotspot/config.php returns 403
   - http://localhost/hotspot/security_helper.php returns 403
   - http://localhost/hotspot/composer.json returns 403

6. **CSRF Test**: Fetch login.php and verify the HTML contains a csrf_token hidden input field.

7. **Auth Guard Test**: Try accessing http://localhost/hotspot/ajax_adduser.php via GET without a session — should be blocked.

8. **Router Connection Test** (only if MOCK_MODE is false): Use PowerShell to test TCP connectivity to the router IP on port 8728.

Report a PASS/FAIL summary for each check with details on any failures.
```

---

## 🆘 Emergency Troubleshooting

| Problem | Solution |
|---------|----------|
| **XAMPP won't start Apache** | Port 80 conflict. Check: `netstat -ano \| findstr :80`. Kill the process or change Apache port. |
| **Can't login to hotspot panel** | Clear browser cache. Check MySQL is running. Verify dbconfig.php password matches. |
| **"Error Accessing Data"** | Router connection failed. Check config.php IP address. Run test_connection.php. Verify API service is enabled on router. |
| **Phone doesn't get captive portal** | Hotspot not configured on router. Check: `/ip hotspot print`. Ensure the WiFi interface is part of the bridge with the hotspot. |
| **Vouchers don't work on router** | Profile names in Easy-HotSpot must EXACTLY match router profile names. Check: `/ip hotspot user profile print`. |
| **Slow internet for customers** | Check rate-limit on profiles. Check if one user is hogging bandwidth. Consider enabling connection-rate or simple queue. |
| **Laptop restarted, panel is down** | XAMPP didn't auto-start. Open XAMPP Control Panel → Start Apache + MySQL. See Phase 6.5. |
| **Lost admin password** | Reset via MySQL: `UPDATE hotspot_users SET password='$2y$12$...' WHERE username='admin';` (generate a bcrypt hash first). |

---

*Guide prepared for deployment on February 8, 2026*  
*Easy-HotSpot v2.0 — Hardened Edition*  
*Converge FiberX Business 400Mbps + MikroTik hAP ac3*
