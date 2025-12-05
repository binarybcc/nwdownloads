# Install PHP MySQL Extension on Synology

## 🎯 Current Status

✅ **Dashboard is loading!** You saw it in the browser!
❌ **Missing:** PHP MySQL extension needed for database connection

**Error message:** "Database connection failed: could not find driver"
**Cause:** PHP doesn't have `pdo_mysql` or `mysqli` extensions installed

---

## 📦 Solution: Install via Package Center

### Step 1: Open Package Center

1. Open **DSM** (Synology web interface)
2. Click **Package Center**
3. Search for **"PHP"**

---

### Step 2: Install PHP Extensions

**Look for one of these packages:**
- **"PHP 8.0"** or **"PHP 8.2"** (if not already installed)
- **"PHP Extensions"** or **"PHP MySQL"**

**Install either:**
1. **Option A:** "PHP 8.2" (latest version)
2. **Option B:** Look for additional packages like "phpMyAdmin" which might bundle MySQL extensions

---

### Step 3: Alternative - Script Language Settings

If you can't find a separate package:

1. **Web Station** → **Script Language Settings**
2. Click **PHP** tab
3. Find your PHP version (8.0 or 8.2)
4. Click **Edit** or **Extensions**
5. Look for checkboxes:
   - ☐ `pdo_mysql`
   - ☐ `mysqli`
6. **Check both boxes**
7. Click **OK** or **Apply**

---

### Step 4: Restart PHP-FPM

After installing/enabling extensions:

```bash
ssh it@192.168.1.254
echo Mojave48ice | sudo -S systemctl restart php-fpm
```

Or via DSM:
- **Web Station** → **Action** → **Restart**

---

### Step 5: Test Dashboard

Refresh your browser:
```
http://192.168.1.254/circulation/
```

**If successful:**
- Error message changes to: **"No data available. Please run data import first."**
- This is GOOD! It means database connection works, just needs data.

---

## 🔍 Troubleshooting

### Can't Find PHP MySQL Extension Package

**Synology might bundle it differently.** Try:

1. **Install phpMyAdmin** (if not already installed)
   - phpMyAdmin requires MySQL extensions
   - Installing it will install the dependencies

2. **Check installed packages:**
   - Package Center → Installed
   - Look for any PHP-related packages

3. **Manual install via command line** (advanced):
   ```bash
   ssh it@192.168.1.254
   sudo /usr/syno/bin/synoservicecfg --hard-enable php80-extension
   ```

---

## 📸 What to Look For in Package Center

### Search Terms:
- "PHP 8"
- "PHP Extensions"
- "phpMyAdmin"
- "MySQL"

### Package Names (varies by DSM version):
- PHP 8.0
- PHP 8.2
- PHP Extensions
- Web Station (should already be installed)

---

## ✅ How to Verify It's Installed

### Method 1: Browser Test
Refresh dashboard - error should change from "could not find driver" to "No data available"

### Method 2: Command Line
```bash
ssh it@192.168.1.254
php -m | grep -i pdo
# Should show: PDO, pdo_mysql, pdo_sqlite
```

### Method 3: Check PHP Info
Create a test file:
```bash
echo "<?php phpinfo(); ?>" | sudo tee /volume1/web/circulation/info.php
```

Visit: `http://192.168.1.254/circulation/info.php`
Look for section: **"PDO drivers" → should list "mysql"**

**Don't forget to delete this file after testing!**
```bash
sudo rm /volume1/web/circulation/info.php
```

---

## 🚀 After Extension is Installed

Once PHP MySQL extension is installed:

1. ✅ Refresh dashboard → Error changes
2. ✅ Dashboard connects to database
3. ✅ Shows "No data available" (waiting for CSV import)
4. ✅ Ready for next step: Data import!

---

## 💡 Alternative: Use Default PHP Profile

If you can't install extensions, we can try switching to a different PHP profile that might have MySQL support:

**In DSM:**
1. Web Station → Web Service
2. Find "circulation" service
3. Edit → Change PHP profile
4. Try different profile (if available)
5. Save and test

---

## 📋 Next Steps After Extension Installed

1. ✅ Verify dashboard connects to database
2. Export Newzware CSV files
3. Upload CSV files to NAS
4. Run Python import script
5. See live data in dashboard!

---

**Let me know once you've installed the PHP MySQL extension and we'll test the database connection!** 🚀
