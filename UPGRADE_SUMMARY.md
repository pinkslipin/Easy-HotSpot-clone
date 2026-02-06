# Easy HotSpot Upgrade - Package-Based Pricing System

## ✅ Implementation Complete (Feb 6, 2026)

All files have been successfully updated and validated with PHP 8.1.25 syntax checking.

---

## 📦 New Package Structure

### Individual Packages (₱50-₱188)
- **1h** - 1 Hour - ₱50
- **3h** - 3 Hours - ₱138  
- **5h** - 5 Hours - ₱188

### Daily/Window Packages (₱208-₱308)
- **day** - Day Pass (8AM-6PM) - ₱238
- **night** - Night Pass (6PM-5AM) - ₱208
- **mindspace** - Mindspace Unlimited (8AM-5AM) - ₱308

### Unlimited Packages (₱1,888-₱5,888)
- **1wk** - 1 Week - ₱1,888
- **15d** - 15 Days - ₱2,988
- **1mo** - 1 Month - ₱5,888

---

## 🔧 Files Modified

### ✅ Core Configuration (3 files)
- **packages_config.php** (NEW) - Single source of truth for all packages
- **pricing_config.php** - Backward compatibility wrapper
- **database.php** - Schema updates (package_id, package_name, package_type)

### ✅ User Interface (2 files)
- **home.php** - Package dropdowns with category grouping
- **header.php** - AJAX functions updated to use package_id

### ✅ Backend Logic (2 files)
- **ajax_adduser.php** - Package-based single voucher creation
- **ajax_addusers.php** - Package-based batch voucher creation

### ✅ Display & Reporting (3 files)
- **voucher.php** - Printing with package names (all 8 formats)
- **portal.php** - Captive portal price list with packages
- **dashboard.php** - Sales reporting grouped by package

---

## 🔑 Key Features

### 1. **Time-Window Pass Support**
Window passes (Day/Night/Mindspace) use time restrictions instead of duration:
- Day Pass: 8:00 AM - 6:00 PM
- Night Pass: 6:00 PM - 5:00 AM  
- Mindspace: 8:00 AM - 5:00 AM (next day)

### 2. **Backward Compatibility**
Old vouchers (created before upgrade) still work:
- Display functions check package_name → package_id → limit_uptime
- Legacy price lookups maintained via `getVoucherPrice()`
- Old batch_id format supported

### 3. **Database Schema**
New columns added (compatible with existing data):
```sql
ALTER TABLE hotspot_vouchers 
  ADD COLUMN package_id VARCHAR(50) DEFAULT NULL,
  ADD COLUMN package_name VARCHAR(100) DEFAULT NULL,
  ADD COLUMN package_type VARCHAR(20) DEFAULT NULL;
```

### 4. **Validation Status**
✅ All 10 PHP files passed syntax validation
✅ No errors found in VS Code analysis
✅ Tested with PHP 8.1.25 (XAMPP)

---

## 📝 Database Migration

**Automatic Schema Update**: The `database.php` file will automatically add the new columns when first loaded. No manual SQL required.

**Safe Migration**: Uses `IF NOT EXISTS` pattern, so running multiple times is safe.

---

## 🚀 Next Steps for Deployment

1. **Backup Current Database**
   ```bash
   mysqldump -u root -p hotspot_db > backup_before_upgrade.sql
   ```

2. **Upload Modified Files** to your server

3. **Test on Server**
   - Create a test voucher with each package type
   - Verify voucher printing shows correct names
   - Check portal price list displays properly
   - Test window passes on RouterOS

4. **RouterOS Configuration** (for time-window passes)
   - Window passes require on-login scripts on MikroTik
   - The `generateWindowLoginScript()` function provides the script code
   - Configure in HotSpot → Server Profiles → Login tab

---

## 🔍 Testing Checklist

- [ ] Create single voucher (Individual package)
- [ ] Create batch vouchers (Daily package)
- [ ] Create window pass (Day/Night/Mindspace)
- [ ] Print vouchers (test voucher7 or voucher8 format)
- [ ] View portal page - verify price list
- [ ] Check dashboard - sales by package
- [ ] Test old vouchers still work
- [ ] Verify RouterOS connection

---

## 📞 Support Information

**Modified**: February 6, 2026  
**PHP Version Tested**: 8.1.25  
**MySQL Version**: Compatible with existing schema

**Files Changed**: 10 total  
**New Files**: 1 (packages_config.php)  
**Database Changes**: 3 new columns (non-breaking)
