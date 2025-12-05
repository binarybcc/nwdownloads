# 🚀 10-Day MVP - Quick Start Guide

## Overview
Host everything on your Synology NAS with public web access.

**What you'll build:**
- MariaDB database (stores historical data)
- Python script (processes daily Newzware exports)
- Web dashboard (beautiful, interactive charts)
- Password protection (secure access)

**Access:** `https://your-synology-url.com/circulation` or `http://your-ip:5000/circulation`

---

## Prerequisites

### On Synology:
- [ ] DSM 7.0 or higher
- [ ] Docker installed (Package Center)
- [ ] MariaDB 10 installed (Package Center)
- [ ] Web Station installed (Package Center)
- [ ] Python 3 installed (via DSM or Docker)

### Files Needed:
- [ ] Newzware subscription export (CSV)
- [ ] Newzware vacation export (CSV)
- [ ] Newzware rates export (CSV)

---

## 10-Day Timeline

### **Day 1-2: Database Setup** (Monday-Tuesday)
**What:** Install MariaDB, create tables
**Time:** 2 hours
**You'll do:** Follow step-by-step instructions
**Output:** Working database ready for data

### **Day 3-4: Data Import** (Wednesday-Thursday)
**What:** Python script imports data
**Time:** 3 hours
**You'll do:** Run script, verify data loads
**Output:** Historical data in database

### **Day 5-7: Build Dashboard** (Friday-Sunday)
**What:** Create web interface with charts
**Time:** 6 hours
**You'll do:** Upload files, configure settings
**Output:** Working dashboard visible in browser

### **Day 8-9: Security & Polish** (Monday-Tuesday)
**What:** Add password, make it pretty
**Time:** 3 hours
**You'll do:** Enable auth, test on phone/tablet
**Output:** Secure, mobile-friendly dashboard

### **Day 10: Deploy & Train** (Wednesday)
**What:** Go live, train staff
**Time:** 2 hours
**You'll do:** Share URL, show how to use
**Output:** Team using dashboard daily!

---

## File Structure on Synology

```
/volume1/
├── docker/
│   └── circulation/          # Docker container files
│       ├── .env              # Database credentials
│       └── docker-compose.yml
│
├── circulation/              # Main application folder
│   ├── data/                 # Daily Newzware exports
│   │   ├── subscriptions_latest.csv
│   │   ├── vacations_latest.csv
│   │   └── rates_latest.csv
│   │
│   ├── scripts/              # Python scripts
│   │   ├── import_to_database.py
│   │   └── requirements.txt
│   │
│   └── web/                  # Dashboard files
│       ├── index.html
│       ├── api.php
│       ├── auth.php
│       └── assets/
│           ├── app.js
│           └── style.css
│
└── web/                      # Web Station root
    └── circulation -> /volume1/circulation/web  (symlink)
```

---

## Step-by-Step Setup

### Step 1: Install Required Packages

1. **Open Package Center**
2. Install these packages:
   - MariaDB 10
   - Web Station
   - Docker (optional, for Python environment)
   - PHP 8.0+

### Step 2: Create Folder Structure

**Via File Station:**
1. Create `/circulation` folder
2. Create subfolders: `/data`, `/scripts`, `/web`
3. Upload the setup files I'm providing

**Via SSH (alternative):**
```bash
cd /volume1
mkdir -p circulation/{data,scripts,web/assets}
chmod -R 755 circulation
```

### Step 3: Set Up Database

1. Follow: `1_install_mariadb.md`
2. Run SQL: `2_create_database_tables.sql`
3. Verify tables exist

### Step 4: Configure Python Script

1. Edit `3_import_to_database.py`
2. Update database password
3. Set file paths
4. Test run

### Step 5: Set Up Web Dashboard

1. Configure Web Station (PHP 8.0+)
2. Upload dashboard files to `/circulation/web`
3. Set permissions
4. Test access

### Step 6: Enable External Access (Optional)

**For secure external access:**
1. Go to **Control Panel** → **External Access** → **DDNS**
2. Enable Synology DDNS (free subdomain)
3. Go to **Security** → **Certificate**
4. Enable Let's Encrypt certificate (free SSL)
5. Go to **External Access** → **Router Configuration**
6. Forward port 443 (HTTPS) to Synology

**Result:** Access via `https://yourname.synology.me/circulation`

---

## Daily Workflow (After Setup)

### Morning Routine (6 AM - Automated):

1. **Export from Newzware** (Ad-Hoc Query Builder)
   - Run 3 queries (subscriptions, vacations, rates)
   - Save to Synology `/circulation/data/` folder
   - Overwrite `_latest.csv` files

2. **Python Script Runs** (Scheduled Task)
   - Reads CSV files
   - Processes data
   - Stores in database
   - Takes 30 seconds

3. **Dashboard Auto-Updates**
   - Reads new data from database
   - Charts update automatically
   - No manual refresh needed

---

## Access URLs

### Internal (LAN):
```
http://192.168.1.XXX/circulation
```

### External (Internet):
```
https://yourname.synology.me/circulation
```

### Mobile App:
- Use DS File app to access files
- Use Safari/Chrome for dashboard
- Save to home screen for app-like experience

---

## Security Setup

### Level 1: Simple Password (MVP)
- Single shared password
- Good for: Small team, quick setup
- Time: 10 minutes

### Level 2: Synology Account Integration
- Use existing Synology user accounts
- Good for: Multiple users, audit trail
- Time: 30 minutes

### Level 3: Two-Factor Authentication
- Requires Synology 2FA app
- Good for: External access
- Time: 15 minutes

**We'll start with Level 1 for MVP**

---

## What You'll See

### Dashboard Homepage:
- **Big Numbers:** Total Active, On Vacation, Deliverable
- **Bar Chart:** Subscriptions by paper (TJ, TA, TR, LJ, WRN)
- **Line Chart:** 90-day trend
- **Breakdown:** Delivery types (Mail, Digital, Carrier)

### Clicking on a Paper:
- **Detailed metrics** for that paper only
- **Historical trends** over time
- **Rate package** breakdown
- **Vacation schedule**

### Mobile View:
- **Stacked layout** (one column)
- **Swipe navigation**
- **Touch-friendly** buttons

---

## Cost Summary

**One-Time:**
- $0 (using existing Synology)

**Monthly:**
- $0 (no hosting fees)

**Annual:**
- $0 (Synology DDNS is free)

**Optional:**
- Domain name: $12/year (if you want custom URL)

**Total: $0** 🎉

---

## Support & Troubleshooting

### Common Issues:

**"Can't connect to database"**
→ Check MariaDB is running in Package Center

**"Permission denied"**
→ Run: `chmod -R 755 /volume1/circulation`

**"Charts not loading"**
→ Check browser console (F12) for errors

**"Slow on mobile"**
→ Normal on first load, fast after cached

### Getting Help:

1. Check Synology DSM logs
2. Check `import_log` table in database
3. Review error messages in browser console
4. Ask me! I'll guide you through any issues

---

## Next Steps

**Ready to start? Here's what to do RIGHT NOW:**

1. ✅ Read this entire guide
2. ✅ Ensure Synology prerequisites are met
3. ✅ Open `1_install_mariadb.md` and begin
4. ✅ Follow each step in order
5. ✅ Ask me questions as you go!

**I'll be here every step of the way. Let's build this! 🚀**

---

**Questions before starting?**
- Not sure about any prerequisites?
- Need help with SSH access?
- Want clarification on any steps?

**Just ask and I'll help!**
