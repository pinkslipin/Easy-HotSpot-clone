# MikroTik HotSpot Deployment - Quick Start Prompt

**Copy and paste this entire prompt into your next chat session with Claude Opus 4.6**

---

## 🎯 Mission: Deploy Easy-HotSpot voucher system on MikroTik hAP ac3 in under 2 hours

## 📊 Current Status

**Hardware Setup:**
- ✅ MikroTik hAP ac3 router connected to ZTE Converge 500Mbps ISP
- ✅ ZTE Converge on ether1 (WAN) - working, router has internet
- ✅ Management PC connected to ether2 - has internet access
- ✅ WinBox 4.0rc3 installed and can access router
- ✅ XAMPP with PHP 8.2.12 running on Windows PC
- ✅ VS Code with Easy-HotSpot-clone project open
- ✅ putty 0.83


**Current Router State:**
- Router identity: MindspaceHotspot
- Admin credentials: Set and working
- PC on ether2 has internet via MikroTik
- Router getting WAN IP from ZTE Converge (192.168.1.x)
- Default bridge configuration active

**What's Ready:**
- Database: `easy_hotspot` created with 3 tables (hotspot_users, sysusers, audit_log)
- Admin login configured in database
- RouterOS API library migrated from PEAR2 to modern evilfreelancer/routeros-api-php
- All PHP files updated and syntax-validated
- config.php ready with credentials

**Project Location:**
`c:\Users\MindSpace\Documents\GitHub\Easy-HotSpot-clone\`

---

## 🎯 Goal for This Session

Deploy a fully functional WiFi hotspot voucher system where:

1. **Management PC** (ether2-5) → Gets 192.168.88.x IP → Full internet access WITHOUT hotspot login → Can manage system via http://localhost/Easy-HotSpot-clone
2. **Guest WiFi** → Gets 10.10.10.x IP → REQUIRES voucher login → Speed limited by package purchased
3. **12 pricing tiers**: ₱50 (2Mbps) to ₱5,888 (300Mbps) with time limits

---

## ⚠️ CRITICAL LESSONS FROM PREVIOUS SESSION

### **The #1 Issue That Will Break Everything:**

**HOTSPOT FIREWALL BLOCKING MANAGEMENT NETWORK**

**Symptom:** After configuring hotspot, your PC gets IP but ping to gateway shows "Destination net unreachable"

**Root Cause:** Hotspot firewall treats management PC (192.168.88.x) as unauthenticated guest and blocks it

**The Fix (MUST DO in Step 10):**
```routeros
/ip firewall filter add chain=forward action=accept src-address=192.168.88.0/24 place-before=0 comment="Allow management network bypass"
```

This MUST be the FIRST firewall rule (place-before=0) to bypass hotspot authentication for management network.

### **Other Critical Points:**

1. **Don't switch cables rapidly** - Windows network stack gets confused, needs time to stabilize
2. **Run ipconfig /release, /renew, arp -d** after any network changes
3. **Verify each checkpoint** before proceeding - don't skip validation steps
4. **DNS must be configured** in DHCP server network settings
5. **Bridge should have TWO IPs**: 192.168.88.1 for management AND 10.10.10.1 for hotspot

---

## 📖 Complete Guide Available

I have a comprehensive step-by-step guide at:
`COMPLETE_DEPLOYMENT_GUIDE.md`

**Read this first!** It has 14 steps covering:
- Physical setup verification
- Router security hardening  
- WAN configuration
- Management network setup
- Hotspot network creation
- Speed profile creation (12 packages)
- **Critical firewall bypass rules**
- WiFi configuration
- Database setup
- System testing

---

## 🚀 Recommended Approach

### **Strategy: Follow the guide systematically, validate each checkpoint**

**Time Budget (2 hours total):**
- Steps 1-6 (Basic setup): 30 minutes
- Steps 7-10 (Hotspot + firewall): 40 minutes  
- Steps 11-13 (WiFi + database + config): 30 minutes
- Step 14 (Testing): 20 minutes

### **Key Validation Points:**

After Step 6, verify:
```powershell
ping 192.168.88.1  # Should succeed
ping 8.8.8.8       # Should succeed
nslookup google.com # Should resolve
```

After Step 10 (firewall), verify:
```routeros
/ip firewall filter print
# First rule MUST be management bypass with place-before=0
```

After Step 14, verify:
- ✅ Admin panel accessible: http://localhost/Easy-HotSpot-clone
- ✅ Can create test voucher
- ✅ Voucher appears on router: `/ip hotspot user print`
- ✅ Guest connects to WiFi and sees login page
- ✅ Guest can login with voucher
- ✅ Management PC still has internet without login

---

## 📋 Information You'll Need

### **Credentials (for reference):**

MikroTik Router:
- Admin user: `admin`
- Admin pass: `YourSecurePassword123!` (change as needed)
- API user: `hotspot-api`
- API pass: `Pinkslippy1@`

Database (phpMyAdmin):
- Server: `localhost`
- User: `root`
- Password: (blank or your MySQL password)
- Database: `easy_hotspot`

Easy-HotSpot Admin Panel:
- Username: `admin`
- Password: `admin123` (update via phpMyAdmin)

### **Network Plan:**

| Network | Subnet | Gateway | DHCP Range | Purpose |
|---------|--------|---------|------------|---------|
| Management | 192.168.88.0/24 | 192.168.88.1 | .10-.254 | Admin PC, no login |
| Hotspot | 10.10.10.0/24 | 10.10.10.1 | .100-.254 | Guests, voucher required |
| WAN | 192.168.1.0/24 | 192.168.1.1 | Dynamic | ISP (ZTE) |

### **Speed Profiles to Create:**

```routeros
50pesos   → 2M/2M   (1 device)
100pesos  → 5M/5M   (1 device)
150pesos  → 10M/10M (2 devices)
200pesos  → 20M/20M (2 devices)
250pesos  → 30M/30M (2 devices)
300pesos  → 40M/40M (3 devices)
350pesos  → 50M/50M (3 devices)
400pesos  → 75M/75M (3 devices)
500pesos  → 100M/100M (4 devices)
1000pesos → 150M/150M (5 devices)
2000pesos → 200M/200M (5 devices)
5888pesos → 300M/300M (10 devices)
```

---

## 🎯 Your Task as AI Assistant

**Primary Objective:** Guide me through COMPLETE_DEPLOYMENT_GUIDE.md efficiently to complete deployment in under 2 hours.

**Your Approach Should Be:**

1. **Start by reading COMPLETE_DEPLOYMENT_GUIDE.md** - understand the full workflow
2. **Ask about current router state** - what's already configured? (since PC on ether2 has internet, some default config exists)
3. **Identify starting point** - which step should we begin at?
4. **Guide step-by-step** - give me commands, wait for output, validate before proceeding
5. **Focus on checkpoints** - ensure each major step works before moving on
6. **Watch for pitfalls** - especially the firewall bypass rules in Step 10
7. **Be concise but complete** - I need clear commands, not lengthy explanations
8. **Troubleshoot efficiently** - if something fails, diagnose quickly with targeted commands

**Communication Style:**
- Give me 3-5 commands at a time
- Wait for my output before proceeding
- Validate results immediately
- If something fails, diagnose with specific commands
- Keep momentum - don't over-explain unless I ask

**Critical Success Factors:**
- ✅ Management PC keeps internet access throughout (192.168.88.x)
- ✅ Firewall bypass rule is FIRST in filter chain
- ✅ Both IPs exist on bridge (192.168.88.1 + 10.10.10.1)
- ✅ Test voucher works end-to-end
- ✅ Complete in under 2 hours

---

## 🔧 Troubleshooting Commands Ready

If PC loses internet after hotspot setup:
```routeros
/ip firewall filter print
/ip firewall filter move [find comment="Allow management network bypass"] destination=0
```

If DHCP not working:
```routeros
/ip dhcp-server print
/ip dhcp-server network print detail
/ip pool print
```

If API connection fails:
```routeros
/ip service print
/ip service set api address=192.168.88.0/24,10.10.10.0/24
```

If guests can't login:
```routeros
/ip hotspot print detail
/ip hotspot active print
/log print where topics~"hotspot"
```

---

## ✅ Success Criteria

**Session Complete When:**
- [ ] Management PC has stable internet on 192.168.88.x
- [ ] WiFi broadcasting "MindSpace WiFi" and "MindSpace WiFi 5G"
- [ ] Admin panel http://localhost/Easy-HotSpot-clone shows dashboard
- [ ] Can create voucher via admin panel
- [ ] Voucher appears on router `/ip hotspot user print`
- [ ] Guest connects to WiFi, sees login page
- [ ] Guest enters voucher, gets internet with correct speed limit
- [ ] All 12 speed profiles configured
- [ ] Configuration backed up via `/export file=hotspot-complete`

---

## 🚀 Let's Start!

**First Command for You, AI:**

"I'm ready to deploy the MikroTik hotspot system. My PC is on ether2 with internet. Please read COMPLETE_DEPLOYMENT_GUIDE.md and tell me:
1. What's my current router configuration? (run diagnostic commands)
2. Which step should we start at?
3. What are the next 5 commands I need to run?

Let's complete this in under 2 hours. Be concise and efficient!"

---

**END OF PROMPT - Copy everything above into new chat**
