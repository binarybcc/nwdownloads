# NWDownloads Circulation Dashboard - Knowledge Base

> **Last Updated:** December 7, 2025  
> **Version:** 1.8.1  
> **Status:** Production

---

## 📋 Table of Contents

- [Project Overview](#project-overview)
- [System Architecture](#system-architecture)
- [Database Schema](#database-schema)
- [Frontend Architecture](#frontend-architecture)
- [API Endpoints](#api-endpoints)
- [Deployment](#deployment)
- [Common Operations](#common-operations)
- [Troubleshooting](#troubleshooting)

---

## Project Overview

### What It Is

A newspaper circulation tracking system for monitoring subscriber metrics across multiple business units and publications, running natively on Synology NAS.

### Tech Stack

- **Backend:** PHP 8.2 (vanilla, no framework)
- **Frontend:** Vanilla JavaScript ES6+ with Chart.js 4.4
- **Database:** MariaDB 10 (native on Synology NAS)
- **Styling:** Tailwind CSS 3.4 (21KB optimized)
- **Infrastructure:** Synology NAS (Apache + PHP 8.2 + MariaDB 10)

### Business Units & Publications

| Business Unit      | Publications                                | Codes       |
| ------------------ | ------------------------------------------- | ----------- |
| **Wyoming**        | The Ranger, Lander Journal, Wind River News | TR, LJ, WRN |
| **Michigan**       | The Advertiser                              | TA          |
| **South Carolina** | The Journal                                 | TJ          |

**Excluded:** FN (Former News - sold/discontinued)

### Current Data State

- **Record Count:** ~250 snapshots
- **Date Range:** January 1, 2025 onwards
- **Why Limited History:** Rate system changed in Jan 2025, making pre-2025 data incomparable
- **YoY Comparisons:** Will be available starting 2026

---

## System Architecture

### 3-Tier Pattern

```
┌─────────────────┐
│  Presentation   │  Vanilla JS + Tailwind CSS
│  (index.php)    │  38KB HTML + embedded PHP
└────────┬────────┘
         │
┌────────▼────────┐
│  Application    │  PHP 8.2 REST API
│  (api.php)      │  1,783 lines
└────────┬────────┘
         │
┌────────▼────────┐
│  Data Layer     │  MariaDB 10.11
│  (daily_snaps)  │  <100ms query time
└─────────────────┘
```

### Data Flow

**CSV Import:**

```
CSV → upload.php → UPSERT → daily_snapshots → confirmation
```

**Dashboard Render:**

```
Browser → index.php → api.php?action=overview → SQL → JSON → Chart.js
```

**Drill-Down:**

```
Chart click → Context menu → TrendSlider/DetailPanel → api.php → data
```

---

## Database Schema

### `daily_snapshots` (Primary Table)

**Purpose:** Store daily circulation metrics with week-based precedence

**Primary Key:** `(snapshot_date, paper_code)`

**Key Columns:**

| Column             | Type        | Description            | Example                           |
| ------------------ | ----------- | ---------------------- | --------------------------------- |
| `snapshot_date`    | DATE        | Date of snapshot       | 2025-12-07                        |
| `paper_code`       | VARCHAR(10) | Publication code       | TJ, TA, TR, LJ, WRN               |
| `business_unit`    | VARCHAR(50) | Business unit          | Wyoming, Michigan, South Carolina |
| `total_active`     | INT         | All active subscribers | 8100                              |
| `deliverable`      | INT         | Active minus vacation  | 7900                              |
| `mail_delivery`    | INT         | USPS delivery          | 7200                              |
| `carrier_delivery` | INT         | Local carrier          | 400                               |
| `digital_only`     | INT         | Internet-only          | 500                               |
| `on_vacation`      | INT         | Vacation hold          | 200                               |

**UPSERT Logic:** `ON DUPLICATE KEY UPDATE` with week-based precedence (later day-of-week wins)

**Indexes:**

- Primary: `(snapshot_date, paper_code)`
- Secondary: `snapshot_date`, `paper_code`, `business_unit`

---

## Database Migrations

**Migration System** (Added: Dec 11, 2025)

### Why Migrations?

**Problem:** Active development creates new tables/columns across multiple workstations
**Solution:** Version-controlled SQL migration files tracked in Git

### Architecture

```
db_migrations/
├── 000_create_migrations_table.sql  # Migration tracking system
├── 001_initial_schema.sql           # Base schema (auto-generated)
├── 002_add_feature.sql              # Future migrations
└── ...
```

**Tracking Table:** `schema_migrations`

- Records which migrations have been applied
- Prevents duplicate application
- Shows deployment history

### Workflow

**Creating a New Migration:**

```bash
# 1. Create numbered migration file (next number in sequence)
nano db_migrations/002_add_analytics_table.sql

# 2. Write SQL
CREATE TABLE analytics_events (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_type VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

# 3. Test on development
./scripts/run-migrations.sh

# 4. Commit to Git
git add db_migrations/002_add_analytics_table.sql
git commit -m "Migration: Add analytics events table"
git push
```

**On Other Workstation:**

```bash
git pull
./scripts/run-migrations.sh  # Automatically applies new migration
```

**Deploy to Production:**

```bash
# Only from johncorbin workstation (has production access)
./scripts/run-migrations-production.sh
```

### Migration Scripts

**Development:** `./scripts/run-migrations.sh`

- Runs on local database
- Colorized output with progress tracking
- Shows migration history after completion

**Production:** `./scripts/run-migrations-production.sh`

- Runs on Synology NAS via SSH
- Uses credentials from `.env.credentials`
- Requires manual confirmation (`yes` to proceed)
- Only run from johncorbin workstation

### Best Practices

**DO:**

- ✅ Create separate migration for each logical change
- ✅ Use descriptive migration names (`002_add_user_preferences.sql`)
- ✅ Test on development before committing
- ✅ Include rollback SQL in comments if needed
- ✅ Run migrations on both workstations after git pull

**DON'T:**

- ❌ Edit existing migration files (create new ones instead)
- ❌ Skip migration numbers (use sequential: 002, 003, 004...)
- ❌ Put data changes in migrations (schema only)
- ❌ Run production migrations without testing first

### Migration File Template

```sql
-- Migration: [Description]
-- Created: [Date]
-- Author: [Your Name]

-- Add new feature/table/column
CREATE TABLE new_feature (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ...
);

-- Rollback (commented out, for reference):
-- DROP TABLE new_feature;
```

### Troubleshooting

**"Migration already applied" error:**

- Migration was run previously
- Check: `SELECT * FROM schema_migrations;`
- Fix: Create new migration instead of editing existing

**"Table already exists" error:**

- Use `CREATE TABLE IF NOT EXISTS` for safety
- Or check schema_migrations table first

**Different schema on workstations:**

- Run `./scripts/run-migrations.sh` on both
- Ensures both have same migrations applied
- Check migration history: last step of script output

### Integration with Data Sync

**Schema (migrations)** and **Data (dump/restore)** are separate:

- **Schema changes** → Migration files in Git
- **Data sync** → `dump-db.sh` / `restore-db.sh`

**Both workstations must:**

1. Run migrations to sync schema (via Git)
2. Optionally sync data (via Dropbox dumps)

This keeps database structure consistent while allowing flexible data management.

---

## Frontend Architecture

### State Management Pattern

**Centralized Singleton:** `window.CircDashboard.state`

```javascript
CircDashboard.state = {
  dashboardData: null,        // Current snapshot from API
  dataRange: {min_date, max_date},
  currentDate: null,          // Selected week (null = current)
  compareMode: 'yoy',         // 'yoy' | 'previous' | 'none'
  charts: {
    trend: Chart.js instance,
    delivery: Chart.js instance,
    businessUnits: {}
  }
}
```

**Benefits:**

- Single source of truth
- Easy debugging (`console.log(CircDashboard.state)`)
- No scattered globals
- Clean teardown/refresh

### Module Load Order (CRITICAL)

```
1. app.js (1,525 lines) ← MUST load first
2. state-icons.js
3. chart-layout-manager.js
4. donut-to-state-animation.js
5. detail_panel.js (900 lines)
6. ui-enhancements.js
7. context-menu.js
8. export-utils.js
9. subscriber-table-panel.js
10. trend-slider.js (759 lines)
11. chart-context-integration.js ← MUST load last
```

**Why Load Order Matters:** `chart-context-integration.js` wires event handlers and must load after `trend-slider.js` or click handlers fail.

### Chart Interaction System

**Entry Point:** Right-click any chart bar

**Context Menu Options:**

1. **View Historical Trend** → Opens TrendSlider (4/8/12/52 week views)
2. **View Subscribers** → Opens SubscriberTablePanel (exportable list)
3. **Export Data** → CSV/Excel/PDF export

**Keyboard Shortcuts:**

- `Ctrl+K` / `Cmd+K`: Export menu
- `ESC`: Close panels
- Arrow keys: Navigate time ranges

---

## API Endpoints

**Base URL:** `api.php`  
**Auth:** Session-based via `auth_check.php`

### Core Endpoints

| Action                 | Parameters                     | Returns                         | Use Case       |
| ---------------------- | ------------------------------ | ------------------------------- | -------------- |
| `overview`             | `date` (optional)              | Daily snapshot with comparisons | Main dashboard |
| `weekly_summary`       | `year`, `week`                 | Aggregated weekly data          | Trend analysis |
| `business_unit_detail` | `unit`, `date` (opt)           | Unit breakdown with papers      | Detail panel   |
| `paper_detail`         | `code`, `date` (opt)           | Paper metrics                   | Drill-down     |
| `get_trend`            | `type`, `metric`, `time_range` | Historical data points          | TrendSlider    |
| `view_subscribers`     | `unit`, `metric_type` (opt)    | Subscriber list (max 10k)       | Table panel    |

### Security

- **SQL Injection:** PDO prepared statements (all queries)
- **Auth:** Newzware credentials via `auth_check.php`
- **Session Timeout:** 2 hours
- **Brute Force:** `brute_force_protection.php`

---

## Deployment

### Production Environment

**Location:** `/volume1/web/circulation/` on Synology NAS (192.168.1.254)
**URL:** https://cdash.upstatetoday.com (also http://192.168.1.254:8081)
**Web Server:** Synology Web Station (Apache + PHP 8.2)
**Database:** MariaDB 10 via Unix socket

### Deployment Workflow

```bash
# 1. Make changes locally and push to GitHub
git push origin master

# 2. SSH into NAS
ssh nas

# 3. Run deployment script
~/deploy-circulation.sh

# 4. Verify at https://cdash.upstatetoday.com
```

**What the deployment script does:**

1. Pulls latest from GitHub master
2. Syncs `web/` to `/volume1/web/circulation/` via rsync
3. Preserves `.htaccess`, `.build_number`
4. Fixes permissions (644 files, 755 directories)

**Critical Rules:**

- ❌ NEVER make changes directly in production
- ✅ ALWAYS test locally first
- ✅ Deploy via git pull + rsync on NAS

---

## Common Operations

### View Logs

```bash
# SSH into NAS
ssh nas

# Error logs
tail -f /volume1/web/circulation/error.log
```

### Database Access

```bash
# SSH into NAS, then:
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard

# Query example
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "SELECT COUNT(*) FROM daily_snapshots;"
```

### Database Backup

```bash
# SSH into NAS, then:
/usr/local/mariadb10/bin/mysqldump -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard > backup_$(date +%Y%m%d).sql
```

### Upload CSV Data

**URL:** https://cdash.upstatetoday.com/upload_unified.php (Production)

**File Format:** All Subscriber Report CSV from Newzware

**Required Columns:** `Ed`, `ISS`, `DEL`

**Processing Time:** 10-30 seconds for ~8,000 rows

---

## Troubleshooting

### Deployment Failures

**Site not loading:**

1. SSH into NAS: `ssh nas`
2. Check Apache is running
3. Check error logs: `tail -f /volume1/web/circulation/error.log`
4. Verify file permissions: `ls -la /volume1/web/circulation/`

**Database connection failed:**

```bash
# SSH into NAS, then test:
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e "SELECT 1;"
```

**Old code still running after deploy:**

```bash
# SSH into NAS, re-run deploy
~/deploy-circulation.sh
```

### CSV Upload Issues

**Error:** CSV doesn't appear to be All Subscriber Report
**Solution:** Ensure query includes columns: `Ed`, `ISS`, `DEL`

**Error:** Later-in-week data already exists
**Solution:** Expected behavior - cannot overwrite Saturday with Tuesday data (week-based precedence)

---

## Week-Based Upload Rules

**Precedence:** Later day-of-week wins

**Day Order:** Monday < Tuesday < Wednesday < Thursday < Friday < Saturday < Sunday

**Examples:**

- ✅ Saturday replaces Friday
- ✅ Friday replaces Thursday
- ❌ Tuesday rejected if Friday exists
- ✅ Same day overwrites

**Rationale:** Later-in-week snapshots capture more complete weekly activity

**Special Case:** Monday uploads assigned to previous week's Saturday

---

## Critical Architecture Decisions

### 1. Native NAS Deployment (Mar 2026)

**Decision:** Run directly on Synology Apache + PHP 8.2 + MariaDB 10 (no Docker)
**Rationale:** Simpler operations, direct file access, no container overhead

### 2. Week-Based Uploads (Dec 2025)

**Decision:** Later day-of-week data replaces earlier in same week  
**Rationale:** Prevents accidental overwrite of better data with stale snapshots  
**Implementation:** `upload.php` lines 412-484

### 3. Event-Based UI (Dec 7, 2025)

**Decision:** Replace `setTimeout(500)` with `DashboardRendered` event  
**Rationale:** Eliminate race conditions on slow connections  
**Found By:** Gemini code review

---

## Performance Characteristics

### Database

- **Query Time:** <100ms for most operations
- **CSV Import:** 10-30 seconds for ~8,000 rows
- **Primary Key Lookup:** O(1) via composite key
- **Index Usage:** All major queries use indexes

### Frontend

- **Page Load (First Visit):** 2-3 seconds
- **Page Load (Cached):** <500ms
- **Chart Render:** ~100ms per chart
- **Total Assets:** ~150KB JS + 21KB CSS

---

## Quick Reference

### URLs

| Environment         | Dashboard                      | Upload                                            |
| ------------------- | ------------------------------ | ------------------------------------------------- |
| **Production**      | https://cdash.upstatetoday.com | https://cdash.upstatetoday.com/upload_unified.php |
| **Production (IP)** | http://192.168.1.254:8081/     | http://192.168.1.254:8081/upload_unified.php      |

### Repository

- **GitHub:** https://github.com/binarybcc/nwdownloads

### Access

- **SSH:** `ssh nas` (passwordless key auth)
- **DB:** `/usr/local/mariadb10/bin/mysql` with socket on NAS
- **Credentials:** See `.env.credentials` and `~/docs/CREDENTIALS.md`

---

## File Organization

### Root Files

- `.envrc` - direnv config (auto-sets PROJECT_ROOT)
- `.env.credentials` - Environment variables for deployment (NOT committed)

### Key Directories

- `/web/` - PHP application and API
- `/web/assets/` - Frontend JavaScript and CSS (11 JS files)
- `/sql/` - Database schema files
- `/db_init/` - Database initialization scripts
- `/docs/` - Documentation
- `/tests/` - Test infrastructure (Vitest)

### Critical Files

- `/web/api.php` - 1,783 lines (REST API)
- `/web/upload.php` - 628 lines (CSV processing)
- `/web/index.php` - 38KB (Dashboard UI)
- `/web/assets/app.js` - 1,525 lines (Core frontend)
- `/web/assets/trend-slider.js` - 759 lines (Trend viz)

---

## Recent Changes

### December 7, 2025

- Fixed setTimeout race condition (event-based)
- Environment variable migration (.env)
- Week-based upload precedence system

### December 6, 2025

- Chart interactions system refactored
- TrendSlider component (759 lines)
- Replaced ChartTransitionManager

### December 2, 2025

- Data cleanup - deleted all pre-2025 data
- Reason: Rate system change made historical data incomparable

---

**End of Knowledge Base**
