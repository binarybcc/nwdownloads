# Web Station Setup for Circulation Dashboard

## 📋 Current Setup

From your screenshot, I can see:
- ✅ Web Station is installed and running
- ✅ Default Portal on ports 80/443
- ✅ WordPress running (alias: `wordpress`)
- ✅ phpMyAdmin running (alias: `phpmyadmin`)

**Python Version:** 3.9 (confirmed on NAS)

---

## 🚀 Quick Setup (5 Minutes)

### Option A: Create New Web Service (Recommended)

This creates a separate service accessible at: `http://192.168.1.254/circulation`

**Steps:**

1. **In Web Station, click "Web Service" in left sidebar**

2. **Click "Create" button**

3. **Configure the new service:**
   ```
   Service name: circulation
   Backend server: PHP 8.0 (or highest available)
   Virtual host: (leave empty to use default)
   Root directory: /volume1/circulation/web
   HTTP back-end server: (use default)
   PHP Extensions: Enable pdo_mysql, mysqli
   ```

4. **Click "Create"**

5. **Test immediately:**
   - Open browser: `http://192.168.1.254/circulation`
   - You should see the dashboard (with "No data available" until import)

---

### Option B: Add to Default Server (Alternative)

Add dashboard to the default server's document root.

**Steps:**

1. **Edit Default server:**
   - Select "Default server" row
   - Click "Edit" button
   - Note the document root path

2. **Create symlink:**
   ```bash
   ssh it@192.168.1.254
   echo Mojave48ice | sudo -S ln -s /volume1/circulation/web /volume1/web/circulation
   ```

3. **Test:**
   - Open: `http://192.168.1.254/circulation`

---

## 🔧 Detailed Setup Instructions

### Step 1: Navigate to Web Service

1. Open **Web Station**
2. Click **"Web Service"** in the left sidebar (the grid icon)
3. You'll see a list of existing services

---

### Step 2: Create New Service

1. Click the **"Create"** button at the top
2. A dialog will appear with configuration options

---

### Step 3: Configure Service

Fill in these fields:

**General Settings:**
- **Service name:** `circulation`
  - This will be your URL path: `/circulation`
  - Must be lowercase, no spaces

- **Backend server:**
  - Select **PHP 8.0** (or highest version available)
  - If only PHP 7.4 available, that works too

**Document Root:**
- Click the **folder icon** to browse
- Navigate to: `/volume1/circulation/web`
- Or manually type: `/volume1/circulation/web`

**HTTP Back-end Server:**
- Leave as default (usually Apache 2.4)

**PHP Settings (IMPORTANT!):**
- Click **"PHP Settings"** tab or expand section
- **Enable these extensions:**
  - ✅ `pdo_mysql` (required for database)
  - ✅ `mysqli` (backup compatibility)
  - ✅ `json` (usually enabled by default)

**Additional Settings:**
- **Enable HTTP compression:** ✅ (optional, for faster loading)
- **Enable HTTP/2:** ✅ (optional, modern browsers)

---

### Step 4: Create and Verify

1. Click **"Create"** or **"OK"**
2. Service should appear in list with status "Normal" (green)
3. If status shows error, click service and check error log

---

### Step 5: Test the Dashboard

**Open in browser:**
```
http://192.168.1.254/circulation
```

**Expected result:**
- ✅ Dashboard loads with modern design
- ✅ Shows "No data available" message (until you import data)
- ✅ Charts are visible but empty
- ✅ No PHP errors displayed

**If you see errors:**
- Check [Troubleshooting](#troubleshooting) section below

---

## 🧪 Test Database Connection

Once dashboard loads, test the API endpoint directly:

**Open in browser:**
```
http://192.168.1.254/circulation/api.php?action=overview
```

**Expected response (before data import):**
```json
{
    "success": false,
    "error": "No data available. Please run data import first.",
    "timestamp": "2025-11-28T10:45:00-05:00"
}
```

**This is GOOD!** It means:
- ✅ PHP is working
- ✅ Database connection successful
- ✅ API is working
- ❌ Just needs data (next step)

---

## 🐛 Troubleshooting

### Blank/White Page

**Cause:** PHP not enabled or path incorrect

**Fix:**
1. Go back to Web Service settings
2. Verify Backend server is PHP 8.0
3. Check document root is exactly: `/volume1/circulation/web`
4. Restart Web Station (Action → Restart)

---

### "Access Forbidden" (403 Error)

**Cause:** File permissions

**Fix:**
```bash
ssh it@192.168.1.254
echo Mojave48ice | sudo -S chmod -R 755 /volume1/circulation/web/
echo Mojave48ice | sudo -S chown -R http:http /volume1/circulation/web/
```

---

### "Database connection failed"

**Cause:** PHP extensions not enabled or password incorrect

**Check extensions:**
1. Web Station → Script Language Settings → PHP
2. Find PHP 8.0 → Extensions
3. Verify `pdo_mysql` is checked

**Check password:**
```bash
ssh it@192.168.1.254
nano /volume1/circulation/web/api.php
# Line 21: 'password' => 'Barnaby358@Jones!'
```

---

### "File not found" for /circulation

**Cause:** Service not created or wrong path

**Fix:**
1. Verify service exists in Web Service list
2. Check Status shows "Normal" (green)
3. If red/error, delete and recreate service

---

### Charts not loading

**Cause:** JavaScript error or internet connection (Chart.js CDN)

**Check:**
1. Press F12 (Developer Tools)
2. Look in Console tab for errors
3. Check Network tab - Chart.js should load from CDN

**Fix:**
- Clear browser cache (Cmd+Shift+R)
- Check internet connection
- Try different browser

---

## 📊 After Data Import

Once you import data, the dashboard should show:

**Key Metrics Cards:**
- Total Active: 8,151
- On Vacation: 18
- Deliverable: 8,133
- 30-Day Change: (varies)

**Charts:**
- 90-Day Trend (line chart)
- Delivery Type Distribution (donut chart)

**Business Units:**
- South Carolina: 3,111 (38.2%)
- Michigan: 2,909 (35.7%)
- Wyoming: 2,131 (26.1%)

**Individual Papers:**
- TJ, TA, TR, LJ, WRN (with clickable cards)

---

## 🔒 Security Considerations

### Current Setup (Good for Internal Use):
- ✅ Accessible only on local network (192.168.1.254)
- ✅ Database user restricted to localhost
- ✅ No public internet exposure

### If Exposing to Internet (NOT RECOMMENDED YET):
- ⚠️ Set up SSL/HTTPS (Let's Encrypt)
- ⚠️ Enable authentication (login.html)
- ⚠️ Configure firewall rules
- ⚠️ Use strong passwords
- ⚠️ Regular security updates

**For MVP:** Keep it local network only! 🔒

---

## 🎯 Alternative: Quick Test via PHP Built-in Server

If you want to test BEFORE setting up Web Station:

```bash
ssh it@192.168.1.254
cd /volume1/circulation/web
php -S 0.0.0.0:8080
```

Then open: `http://192.168.1.254:8080`

**Note:** This is only for testing! Use Web Station for production.

---

## ✅ Web Station Setup Checklist

After completing setup:

- [ ] Web Service created with name "circulation"
- [ ] Backend server: PHP 8.0 selected
- [ ] Document root: `/volume1/circulation/web`
- [ ] PHP extensions enabled: `pdo_mysql`, `mysqli`
- [ ] Service status shows "Normal" (green)
- [ ] Dashboard accessible: `http://192.168.1.254/circulation`
- [ ] API endpoint responds: `http://192.168.1.254/circulation/api.php?action=overview`
- [ ] No PHP errors displayed
- [ ] Ready for data import!

---

## 📝 Configuration Summary

**Service Details:**
```
Name: circulation
Type: Web Service
Backend: PHP 8.0+
Path: /volume1/circulation/web
URL: http://192.168.1.254/circulation
Alias: /circulation
Port: 80 (default portal)
PHP Extensions: pdo_mysql, mysqli, json
```

**File Locations:**
```
Web files: /volume1/circulation/web/
  ├── index.html (main dashboard)
  ├── api.php (backend API)
  ├── assets/app.js (JavaScript)
  └── login.html (optional auth)

Database: MariaDB 10.11.6
  ├── Name: circulation_dashboard
  ├── User: circ_dash
  └── Tables: 5 (ready for data)
```

---

## 🚀 Next Steps After Web Station Setup

1. **Verify dashboard loads** (should show "No data available")
2. **Export Newzware data** (3 CSV files)
3. **Upload CSV files** to `/volume1/circulation/data/`
4. **Upload Python script** to `/volume1/circulation/scripts/`
5. **Run data import**
6. **Refresh dashboard** - see real data!

---

## 💡 Pro Tips

**Bookmark the URLs:**
- Dashboard: `http://192.168.1.254/circulation`
- API Test: `http://192.168.1.254/circulation/api.php?action=overview`
- phpMyAdmin: `http://192.168.1.254/phpmyadmin`

**Add to home screen (mobile):**
- iOS/Android: "Add to Home Screen" for app-like experience

**Monitor logs:**
- Web Station → Log tab
- Shows PHP errors and access logs

---

**Ready to set up Web Station? Follow the steps above and let me know if you hit any issues!** 🎉
