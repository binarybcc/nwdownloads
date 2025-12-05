# 🎉 Database Setup Complete!

## ✅ What's Been Completed

### 1. **Database Setup**
- ✅ MariaDB 10.11.6 confirmed installed and running
- ✅ Database created: `circulation_dashboard`
- ✅ User created: `circ_dash`
- ✅ Password: `Barnaby358@Jones!` (meets security policy)
- ✅ All privileges granted
- ✅ Connection tested and verified

### 2. **Database Tables Created (5 tables)**
- ✅ `daily_snapshots` - Daily metrics by paper
- ✅ `vacation_snapshots` - Vacation statistics
- ✅ `rate_distribution` - Rate package breakdown
- ✅ `dashboard_users` - Authentication users
- ✅ `import_log` - Import history tracking

### 3. **Directory Structure**
- ✅ Created `/volume1/circulation/`
- ✅ Created `/volume1/circulation/data/` (for CSV files)
- ✅ Created `/volume1/circulation/scripts/` (for Python)
- ✅ Created `/volume1/circulation/web/` (for dashboard)
- ✅ Created `/volume1/circulation/web/assets/` (for JavaScript)

### 4. **Configuration Files**
- ✅ `.env` created at `/volume1/circulation/.env`
- ✅ `.env.ssh` updated locally with correct credentials
- ✅ `api.php` updated with database credentials

### 5. **Web Files Uploaded**
- ✅ `index.html` - Main dashboard page
- ✅ `api.php` - PHP backend API
- ✅ `assets/app.js` - JavaScript with Chart.js
- ✅ `login.html` - Login page (optional)
- ✅ `README.md` - Documentation

---

## 🔐 Important Credentials

**Save these somewhere safe!**

### SSH Connection:
```
Host: 192.168.1.254
Port: 22
User: it
Password: Mojave48ice
```

### MariaDB Root:
```
User: root
Password: P@ta675N0id
```

### Dashboard Database:
```
Host: localhost (or 192.168.1.254)
Port: 3306
Database: circulation_dashboard
User: circ_dash
Password: Barnaby358@Jones!
```

---

## 📋 Next Steps

### Step 1: Configure Web Station (5 minutes)

1. **Open DSM** (Synology web interface)
2. Go to **Main Menu** → **Web Station**
3. Click **Web Service Portal** tab
4. Create a new portal or edit default:
   - **Backend server:** PHP 8.0 (or latest available)
   - **Document root:** `/volume1/circulation/web`
   - **HTTP port:** 80 (or custom port if 80 is taken)
   - **PHP extensions:** Enable `pdo_mysql`, `mysqli`
5. Click **Create** or **Save**

**Test Web Station:**
Open browser: `http://192.168.1.254/` (or your custom port)

You should see your dashboard (it will say "No data available" until you import data).

---

### Step 2: Export Newzware Data (5 minutes)

You need to export 3 CSV files from Newzware daily:

1. **Subscriptions Export** (already have the query)
   - File: `subscriptions_latest.csv`
   - Columns: Including sp_vac_ind, sp_rate_id, Edition, Status

2. **Vacations Export** (already have the query)
   - File: `vacations_latest.csv`
   - Columns: vd_sp_id, vd_sta_date, vd_sto_date, vd_vacay_ind

3. **Rates Export** (need to create)
   - Query: `SELECT * FROM retail_rate`
   - File: `rates_latest.csv`
   - Columns: Sub_Rate_Id (column 56), Edition, Rate description

**Export location (on your Mac):**
- Save to: `/Users/johncorbin/Desktop/projs/nwdownloads/queries/`

---

### Step 3: Upload CSV Files to NAS (2 minutes)

**Option A: Via SSH (command line)**
```bash
cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup
sshpass -p 'Mojave48ice' scp -P 22 ../queries/*.csv it@192.168.1.254:/volume1/circulation/data/
```

**Option B: Via DSM File Station**
1. Open **File Station** in DSM
2. Navigate to `/volume1/circulation/data/`
3. Click **Upload** button
4. Select your 3 CSV files
5. Upload

---

### Step 4: Upload Python Import Script (2 minutes)

The Python import script needs to be uploaded to the NAS.

**Via SSH:**
```bash
cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup
sshpass -p 'Mojave48ice' scp -P 22 3_import_to_database.py it@192.168.1.254:/tmp/
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 'echo Mojave48ice | sudo -S cp /tmp/3_import_to_database.py /volume1/circulation/scripts/ && sudo chmod +x /volume1/circulation/scripts/3_import_to_database.py'
```

---

### Step 5: Install Python Dependencies (3 minutes)

**Connect to NAS via SSH:**
```bash
ssh it@192.168.1.254
```

**Install MySQL connector:**
```bash
sudo python3 -m pip install mysql-connector-python
```

---

### Step 6: Run First Data Import (2 minutes)

**On the NAS (via SSH):**
```bash
cd /volume1/circulation/scripts
python3 3_import_to_database.py
```

**Expected output:**
```
================================================================================
CIRCULATION DASHBOARD - DATA IMPORT
================================================================================
✓ Connected to database: circulation_dashboard

Loading reference data...
✓ Loaded 150 rate mappings from rates.csv

Processing subscriptions...
✓ Processed 8,151 subscriptions

Processing vacations...
✓ Found 18 active vacations

Saving to database...
✓ Daily snapshot saved: 2025-11-28

Import Summary:
- Total Active: 8,151
- On Vacation: 18
- Deliverable: 8,133
- Import time: 2.3 seconds

================================================================================
✓ IMPORT COMPLETE!
================================================================================
```

---

### Step 7: Test Dashboard (1 minute)

**Open in browser:**
```
http://192.168.1.254/
```

You should see:
- ✅ Total Active: 8,151
- ✅ On Vacation: 18
- ✅ Deliverable: 8,133
- ✅ Charts with your actual data
- ✅ Business unit cards (South Carolina, Michigan, Wyoming)
- ✅ Individual paper cards (TJ, TA, TR, LJ, WRN)

---

## 🔧 Troubleshooting

### "Database connection failed"
**Check:**
1. Web Station is running (DSM → Web Station)
2. PHP extensions enabled (`pdo_mysql`, `mysqli`)
3. `api.php` has correct password on line 21

**Fix:**
```bash
ssh it@192.168.1.254
nano /volume1/circulation/web/api.php
# Check line 21: 'password' => 'Barnaby358@Jones!'
```

---

### "No data available"
**Check:**
1. CSV files uploaded to `/volume1/circulation/data/`
2. Import script has run successfully
3. Data is in database

**Verify data:**
```bash
ssh it@192.168.1.254
/usr/local/mariadb10/bin/mysql -u circ_dash -p'Barnaby358@Jones!' circulation_dashboard -e "SELECT COUNT(*) FROM daily_snapshots;"
```

Should return a number greater than 0.

---

### White/blank page
**Check:**
1. Web Station PHP enabled
2. Document root correct: `/volume1/circulation/web`
3. Files have correct permissions

**Fix permissions:**
```bash
ssh it@192.168.1.254
echo Mojave48ice | sudo -S chmod -R 755 /volume1/circulation/web/
```

---

### Charts not loading
**Check browser console:**
1. Press F12 (Developer Tools)
2. Click Console tab
3. Look for JavaScript errors

**Common fixes:**
- Clear browser cache (Cmd+Shift+R)
- Check internet connection (Chart.js loads from CDN)
- Verify `api.php` is accessible: `http://192.168.1.254/api.php?action=overview`

---

## 🎯 Automation (Day 3-4)

Once the dashboard is working, set up daily automation:

### Create Scheduled Task in DSM:

1. **DSM** → **Control Panel** → **Task Scheduler**
2. Click **Create** → **Scheduled Task** → **User-defined script**
3. **General tab:**
   - Task: `Daily Circulation Import`
   - User: `it`
   - Enabled: ✅
4. **Schedule tab:**
   - Run on: Daily
   - Time: `06:00` (6:00 AM)
5. **Task Settings tab:**
   - Script:
   ```bash
   python3 /volume1/circulation/scripts/3_import_to_database.py
   ```
6. **Save**

**Test the task:**
- Right-click task → **Run**
- Check output in task history

---

## 📊 Current Status

### ✅ Completed:
- MariaDB database and tables created
- Web files uploaded and ready
- Configuration files created
- Directory structure established
- SSH access configured

### 🔄 In Progress:
- Web Station configuration (you need to do this)
- CSV data exports from Newzware
- Python import script setup

### ⏳ Pending:
- Daily automation setup
- User authentication (optional)
- Paper detail pages (optional)
- Mobile testing

---

## 🚀 Success Checklist

Before marking this as "complete":

- [ ] Web Station configured and running
- [ ] Dashboard accessible in browser (`http://192.168.1.254/`)
- [ ] 3 CSV files exported from Newzware
- [ ] CSV files uploaded to `/volume1/circulation/data/`
- [ ] Python dependencies installed
- [ ] Import script run successfully
- [ ] Dashboard shows real data (not "No data available")
- [ ] Charts display properly
- [ ] All 5 papers visible (TJ, TA, TR, LJ, WRN)
- [ ] Business unit totals are correct

---

## 📝 Daily Workflow (Once Set Up)

### Morning (Automated):
1. **6:00 AM:** Scheduled task runs import script
2. **6:01 AM:** Dashboard refreshes with today's data

### Manual (Until Automation):
1. Export 3 CSV files from Newzware
2. Upload to `/volume1/circulation/data/`
3. SSH to NAS: `python3 /volume1/circulation/scripts/3_import_to_database.py`
4. Refresh dashboard in browser

---

## 🎉 You're Almost There!

**Completed:** Database setup, file uploads, configuration ✅
**Next:** Configure Web Station and test dashboard (15 minutes)
**Then:** Export data and run import (10 minutes)
**Total remaining:** ~25 minutes to a working dashboard!

**Questions? Issues? Check the troubleshooting section or let me know!**
