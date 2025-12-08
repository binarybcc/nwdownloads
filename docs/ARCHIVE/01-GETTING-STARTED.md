# Getting Started with NWDownloads Circulation Dashboard

**Version:** 1.0 | **Last Updated:** December 7, 2025

## Overview

The NWDownloads Circulation Dashboard is a modern newspaper circulation tracking system that monitors subscriber metrics across **5 publications** spanning **3 business units** (Wyoming, Michigan, South Carolina). The system transforms weekly CSV data exports from Newzware into an interactive web-based analytics platform.

**Key Features:**
- 📊 Real-time circulation metrics dashboard
- 📈 Interactive charts with trend visualization
- 📅 Week-over-week, month-over-month, year-over-year comparisons
- 📤 CSV import with smart week-based precedence
- 🎯 Multi-level drill-down (business unit → paper → subscription details)
- ⌨️ Keyboard shortcuts for power users
- 📥 Export to Excel, CSV, and PDF

---

## Quick Start Checklist

### Prerequisites

- ✅ Docker Desktop or OrbStack installed
- ✅ direnv installed (optional but recommended)
- ✅ Git installed
- ✅ Access to Newzware for CSV exports
- ✅ Newzware authentication credentials

### 5-Minute Setup

```bash
# 1. Clone the repository
cd ~/Development/work/_active/
git clone https://github.com/binarybcc/nwdownloads.git
cd nwdownloads

# 2. Set up environment variables (one-time)
cp .env.example .env
# Edit .env with your credentials (see Environment Setup below)

# 3. Start the containers
docker compose up -d

# 4. Wait for database to initialize (~30 seconds)
docker compose logs -f database

# Look for: "mariadbd: ready for connections"

# 5. Access the dashboard
open http://localhost:8081/
```

**You're done!** 🎉 The dashboard should load the login page.

---

## Detailed Setup Guide

### 1. Environment Configuration

The `.env` file contains database credentials and configuration. **Never commit this file to git** (it's already in `.gitignore`).

**Create .env from template:**

```bash
cp .env.example .env
```

**Edit .env with your values:**

```bash
# Database Configuration
DB_HOST=database
DB_PORT=3306
DB_NAME=circulation_dashboard
DB_ROOT_PASSWORD=RootPassword456!      # Change in production!
DB_USER=circ_dash
DB_PASSWORD=Barnaby358@Jones!          # Change in production!
```

**Security Note:** The example credentials work for local development but should be changed for any shared or production environment.

### 2. Multi-Workstation Setup (Optional)

This project uses **direnv** to automatically set `PROJECT_ROOT` when you enter the directory.

**One-time setup per workstation:**

```bash
# Install direnv (if not installed)
brew install direnv  # macOS
# or: apt install direnv  # Linux

# Add to your shell config (~/.zshrc or ~/.bashrc)
eval "$(direnv hook zsh)"    # For zsh
# OR
eval "$(direnv hook bash)"   # For bash

# Reload your shell
source ~/.zshrc   # or source ~/.bashrc

# Navigate to project and allow direnv
cd /path/to/nwdownloads
direnv allow
```

**How it works:** When you `cd` into the project, `PROJECT_ROOT` is automatically set. When you leave, it's unset. No conflicts with other projects!

### 3. Container Management

**Start containers:**
```bash
docker compose up -d
```

**Check status:**
```bash
docker compose ps

# Expected output:
# NAME              STATUS
# circulation_db    Up (healthy)
# circulation_web   Up
```

**View logs:**
```bash
# All containers
docker compose logs -f

# Specific container
docker compose logs -f web
docker compose logs -f database
```

**Stop containers:**
```bash
docker compose down
```

**Restart containers:**
```bash
docker compose restart
```

**Rebuild after code changes:**
```bash
# In development, code changes are live (volume mounts)
# Just refresh your browser - no rebuild needed!

# Only rebuild if you change Dockerfile or docker-compose.yml:
docker compose down
docker compose up -d --build
```

### 4. Database Initialization

The database is automatically initialized on first container start using SQL scripts in `/db_init/`.

**Initialization includes:**
- `daily_snapshots` table (primary circulation data)
- `publication_schedule` table (print/digital publication days)
- `subscriber_snapshots` table (individual subscriber records)
- Publication schedule seed data

**Verify initialization:**
```bash
docker exec circulation_db mariadb -uroot -pRootPassword456! -D circulation_dashboard -e "SHOW TABLES;"

# Expected output:
# +--------------------------------+
# | Tables_in_circulation_dashboard |
# +--------------------------------+
# | daily_snapshots                |
# | publication_schedule           |
# | subscriber_snapshots           |
# +--------------------------------+
```

---

## First Data Upload

### 1. Export Data from Newzware

1. Log in to Newzware Ad-Hoc Query Builder
2. Run the **"All Subscriber Report"** query
3. Export as CSV
4. File saves as: `AllSubscriberReportYYYYMMDDHHMMSS.csv`

**Example:** `AllSubscriberReport20251207151030.csv`

### 2. Upload to Dashboard

**Access upload page:**
```
http://localhost:8081/upload.html
```

**Upload process:**
1. Click "Choose File" and select your CSV
2. Click "Upload and Process Data"
3. Wait 10-30 seconds for processing (~8,000 rows)
4. Review import summary showing:
   - New records added
   - Existing records updated
   - Total subscribers by business unit
5. Click "View Dashboard" to see your data

**Expected result:**

```
✅ Import Successful!
Date Range: 2025-12-07 to 2025-12-07
New Records Added: 5
Existing Records Updated: 0
Total Records Processed: 5

📊 Summary by Business Unit:
South Carolina: 3,106 subscribers
Michigan: 2,909 subscribers
Wyoming: 1,610 subscribers
```

### 3. Week-Based Upload Rules

**Important:** The system uses **smart precedence** to prevent data loss:

- ✅ **Saturday data replaces Friday data** (later in week wins)
- ✅ **Friday data replaces Thursday data**
- ✅ **You can re-upload the same day** (overwrites)
- ❌ **Tuesday data is REJECTED if Friday data exists** (prevents overwriting better data)

**Why?** Later-in-week snapshots are more accurate (captures full week's activity). This prevents accidentally overwriting Saturday's complete data with Tuesday's partial snapshot.

**See also:** [03-DATA-MANAGEMENT.md](03-DATA-MANAGEMENT.md) for complete upload rules.

---

## Using the Dashboard

### Authentication

**Default login:**
- Uses **Newzware authentication** (same credentials as Newzware system)
- No local account creation needed
- Session expires after 2 hours of inactivity

**First login:**
1. Navigate to http://localhost:8081/
2. Enter your Newzware username and password
3. Dashboard loads automatically after successful authentication

### Dashboard Overview

**Main sections:**
```
┌─────────────────────────────────────────┐
│  Key Metrics (4 cards)                  │
│  - Total Active, Vacation, Deliverable  │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  90-Day Trend Chart                     │
│  (Line chart with 3-month history)     │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  Delivery Type Breakdown                │
│  (Donut chart: Mail/Carrier/Digital)   │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  Business Unit Cards (6 cards)          │
│  - Click for detailed drill-down        │
└─────────────────────────────────────────┘
```

### Interactive Features

**📊 Chart Interactions:**
- **Right-click** any chart bar → Context menu
  - "View Historical Trend" → Opens trend slider
  - "View Subscribers" → Shows subscriber list
  - Export options

**📈 Trend Slider:**
- Slide-in panel showing historical data
- Time range selector: 4, 8, 12, or 52 weeks
- Color continuity from source chart
- Keyboard shortcut: **ESC** to close

**⌨️ Keyboard Shortcuts:**
- `Ctrl+K` (or `Cmd+K` on Mac) - Open export menu
- `ESC` - Close trend slider or detail panel
- `Arrow keys` - Navigate time ranges in trend slider

**📥 Export Options:**
- **CSV** - Raw data with headers
- **Excel** - Styled workbook with charts
- **PDF** - Print-friendly report

### Comparison Modes

**Week over Week:**
- Compares current week to same week last year
- Default mode

**Month over Month:**
- Compares current month to previous month
- Click "Month over Month" button

**Year over Year:**
- Compares current year to previous year
- Click "Year over Year" button

**Custom Date Range:**
- Use date picker to select specific week
- Comparison adjusts automatically

---

## Common Tasks

### Weekly Data Update

**Recommended schedule:** Every Saturday morning

```bash
# 1. Run All Subscriber Report in Newzware
# 2. Export to CSV
# 3. Upload via http://localhost:8081/upload.html
# 4. Verify metrics look correct on dashboard
```

**Why Saturday?** Captures full week's data and aligns with publication schedules (most papers publish Wed/Sat).

### View Recent Changes

```bash
# Check last 10 database snapshots
docker exec circulation_db mariadb -uroot -pRootPassword456! -D circulation_dashboard -e "
  SELECT snapshot_date, paper_code, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC, paper_code
  LIMIT 10;
"
```

### Check Database Size

```bash
docker exec circulation_db mariadb -uroot -pRootPassword456! -D circulation_dashboard -e "
  SELECT
    COUNT(*) as total_snapshots,
    MIN(snapshot_date) as earliest_date,
    MAX(snapshot_date) as latest_date
  FROM daily_snapshots;
"
```

### Export Database Backup

```bash
# Create backup
docker exec circulation_db mariadb-dump -uroot -pRootPassword456! circulation_dashboard > backup_$(date +%Y%m%d).sql

# Restore from backup
cat backup_20251207.sql | docker exec -i circulation_db mariadb -uroot -pRootPassword456! circulation_dashboard
```

---

## Troubleshooting Quick Reference

### "Connection refused" when accessing dashboard

**Cause:** Containers not running or not healthy

**Solution:**
```bash
docker compose ps  # Check status
docker compose logs web  # Check for errors
docker compose up -d  # Restart if needed
```

### CSV upload fails with "CSV does not appear to be an All Subscriber Report"

**Cause:** Wrong CSV format or missing required columns

**Solution:**
- Ensure you're exporting the **"All Subscriber Report"** query (not individual exports)
- Required columns: `Ed` (paper code), `ISS` (issue date), `DEL` (delivery type)
- System auto-trims whitespace, so slight variations are OK

### Dashboard shows "No data for this week"

**Cause:** No snapshot uploaded for selected week

**Solution:**
- Upload CSV data for this week via upload.html
- Or select a different week using the date picker
- Check if week filter is correct (system uses Sunday-Saturday weeks)

### Charts not rendering

**Cause:** JavaScript error or data format issue

**Solution:**
1. Open browser console (F12)
2. Check for errors
3. Try hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
4. Check browser console for specific error messages

**For more troubleshooting:** See [07-TROUBLESHOOTING.md](07-TROUBLESHOOTING.md)

---

## Next Steps

Now that you have the system running:

1. **📚 Learn the Architecture** → [02-ARCHITECTURE.md](02-ARCHITECTURE.md)
2. **📊 Master Data Management** → [03-DATA-MANAGEMENT.md](03-DATA-MANAGEMENT.md)
3. **🚀 Deploy to Production** → [04-DEPLOYMENT.md](04-DEPLOYMENT.md)
4. **🔌 Explore the API** → [05-API-REFERENCE.md](05-API-REFERENCE.md)
5. **✨ Discover Features** → [06-FEATURES.md](06-FEATURES.md)

---

## Getting Help

**Documentation:**
- This guide for setup and basic usage
- [07-TROUBLESHOOTING.md](07-TROUBLESHOOTING.md) for common issues
- `.claude/CLAUDE.md` for project-specific configuration

**Code:**
- GitHub: https://github.com/binarybcc/nwdownloads
- Docker Hub: https://hub.docker.com/r/binarybcc/nwdownloads-circ

**Logs:**
```bash
docker compose logs -f    # All containers
docker compose logs web   # Web server only
docker compose logs database  # Database only
```

---

**Last Updated:** December 7, 2025
**Contributors:** Claude Sonnet 4.5
**License:** Internal use only
