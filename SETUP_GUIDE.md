# Easy-HotSpot Local Development Setup Guide

## Quick Start (5 Minutes)

### Step 1: Install XAMPP

**Windows:**
1. Download from https://www.apachefriends.org/download.html
2. Run installer → Install to `C:\xampp`
3. Open XAMPP Control Panel
4. Start **Apache** and **MySQL** (click Start buttons)

**macOS:**
1. Download MAMP from https://www.mamp.info/en/downloads/
2. Drag to Applications
3. Open MAMP → Click "Start Servers"

### Step 2: Copy Files

```bash
# Windows (PowerShell)
Copy-Item -Recurse "C:\path\to\Easy-HotSpot-clone" "C:\xampp\htdocs\easy-hotspot"

# macOS/Linux
cp -r ~/Easy-HotSpot-clone /Applications/MAMP/htdocs/easy-hotspot
```

### Step 3: Configure Database

Edit `dbconfig.php`:

```php
<?php
    $DB_host = "localhost";
    $DB_user = "root";
    $DB_pass = "";           // Empty for XAMPP, "root" for MAMP
    $DB_name = "mikrotik";
?>
```

### Step 4: Enable Mock Mode (No MikroTik Needed!)

Edit `config.php` - make sure this line says `true`:

```php
define('MOCK_MODE', true);
```

### Step 5: Access the App

1. Open browser: http://localhost/easy-hotspot/
2. Login with: **admin** / **admin**
3. Start creating vouchers!

---

## Understanding the Voucher System

### What Happens When You Create a Voucher

```
┌─────────────────────────────────────────────────────────────────┐
│  YOU CLICK "Add Single User" with:                               │
│  - Username: cafe001                                             │
│  - Password: abc123                                              │
│  - Time Limit: 2h (2 hours)                                      │
│  - Profile: 5Mbps                                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  MOCK MODE (Development):                                        │
│  → Saves to mock_data/hotspot_users.json                        │
│  → Saves to MySQL hotspot_vouchers table                        │
│                                                                  │
│  REAL MODE (Production):                                         │
│  → Sends API command to MikroTik router                         │
│  → Router adds user to /ip hotspot user                         │
│  → Saves to MySQL hotspot_vouchers table                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  PRINTABLE VOUCHER DISPLAYED:                                    │
│  ┌─────────────────────────────────────────┐                    │
│  │  ☕ CAFÉ WIFI                            │                    │
│  │  Username: cafe001                       │                    │
│  │  Password: abc123                        │                    │
│  │  Valid for: 2 hours                      │                    │
│  │  Speed: 5Mbps                            │                    │
│  └─────────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Customizing Voucher Types

### Where to Add New Time Limits

**File:** `home.php` (around line 150-160)

Look for the uptime input field:
```php
<input type="text" placeholder="10m" title="eg.5h30m" name="slimit_uptime" id="slimit_uptime">
```

**Time Format Examples:**
- `30m` = 30 minutes
- `1h` = 1 hour
- `2h30m` = 2 hours 30 minutes
- `1d` = 1 day
- `1w` = 1 week
- `1d12h` = 1 day 12 hours

### Where to Add Speed Profiles

Speed profiles come from the MikroTik router (or mock data in dev mode).

**Mock Mode:** Edit `mock_router.php`:
```php
'profiles' => [
    ['name' => '1Mbps', 'rate-limit' => '1M/1M', 'shared-users' => '1'],
    ['name' => '2Mbps', 'rate-limit' => '2M/2M', 'shared-users' => '1'],
    ['name' => '5Mbps', 'rate-limit' => '5M/5M', 'shared-users' => '1'],
    ['name' => '10Mbps', 'rate-limit' => '10M/10M', 'shared-users' => '1'],
    // Add your custom profiles here:
    ['name' => 'Premium-20Mbps', 'rate-limit' => '20M/20M', 'shared-users' => '1'],
],
```

**Real MikroTik:** Create profiles via Winbox or terminal:
```
/ip hotspot user profile add name=Premium-20Mbps rate-limit=20M/20M shared-users=1
```

---

## Key Files Reference

| File | Purpose | Customize For |
|------|---------|---------------|
| `config.php` | Router connection settings | MikroTik IP, credentials, mock mode |
| `dbconfig.php` | Database connection | MySQL credentials |
| `home.php` | Main dashboard UI | Layout, buttons, form fields |
| `ajax_adduser.php` | Single voucher creation logic | Voucher creation process |
| `ajax_addusers.php` | Bulk voucher creation | Batch generation |
| `voucher.php` | Voucher printing | Print layouts |
| `mock_router.php` | Dev mode simulation | Test profiles, mock data |

---

## When You Get a Real MikroTik Router

1. Edit `config.php`:
   ```php
   define('MOCK_MODE', false);  // Switch to real mode
   
   $host = "192.168.88.1";      // Your router IP
   $user = "api-user";          // API username you created
   $pass = "secure-password";   // API password
   ```

2. On the MikroTik router, enable API:
   ```
   /ip service set api disabled=no
   ```

3. Create an API user:
   ```
   /user add name=api-user password=secure-password group=full
   ```

4. Make sure hotspot is configured:
   ```
   /ip hotspot setup
   ```

---

## Troubleshooting

### "Error Accessing Data" on login
- Check if MySQL is running
- Verify dbconfig.php credentials
- Check if MOCK_MODE is true in config.php

### Blank page after login
- Check PHP error log: `C:\xampp\php\logs\php_error_log`
- Enable errors in index.php: `ini_set('display_errors', 1);`

### Can't see profiles in dropdown
- In mock mode: Check mock_router.php has profiles defined
- In real mode: Verify MikroTik has hotspot profiles configured

### Database tables not created
- Tables auto-create on first login attempt
- Check MySQL user has CREATE TABLE permission
