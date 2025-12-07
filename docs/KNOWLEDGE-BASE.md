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
- [Docker & Deployment](#docker--deployment)
- [Common Operations](#common-operations)
- [Troubleshooting](#troubleshooting)

---

## Project Overview

### What It Is
A Docker-based newspaper circulation tracking system for monitoring subscriber metrics across multiple business units and publications.

### Tech Stack
- **Backend:** PHP 8.2 (vanilla, no framework)
- **Frontend:** Vanilla JavaScript ES6+ with Chart.js 4.4
- **Database:** MariaDB 10.11
- **Styling:** Tailwind CSS 3.4 (21KB optimized)
- **Infrastructure:** Docker + Docker Compose

### Business Units & Publications

| Business Unit | Publications | Codes |
|---------------|-------------|-------|
| **Wyoming** | The Ranger, Lander Journal, Wind River News | TR, LJ, WRN |
| **Michigan** | The Advertiser | TA |
| **South Carolina** | The Journal | TJ |

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

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `snapshot_date` | DATE | Date of snapshot | 2025-12-07 |
| `paper_code` | VARCHAR(10) | Publication code | TJ, TA, TR, LJ, WRN |
| `business_unit` | VARCHAR(50) | Business unit | Wyoming, Michigan, South Carolina |
| `total_active` | INT | All active subscribers | 8100 |
| `deliverable` | INT | Active minus vacation | 7900 |
| `mail_delivery` | INT | USPS delivery | 7200 |
| `carrier_delivery` | INT | Local carrier | 400 |
| `digital_only` | INT | Internet-only | 500 |
| `on_vacation` | INT | Vacation hold | 200 |

**UPSERT Logic:** `ON DUPLICATE KEY UPDATE` with week-based precedence (later day-of-week wins)

**Indexes:**
- Primary: `(snapshot_date, paper_code)`
- Secondary: `snapshot_date`, `paper_code`, `business_unit`

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

| Action | Parameters | Returns | Use Case |
|--------|-----------|---------|----------|
| `overview` | `date` (optional) | Daily snapshot with comparisons | Main dashboard |
| `weekly_summary` | `year`, `week` | Aggregated weekly data | Trend analysis |
| `business_unit_detail` | `unit`, `date` (opt) | Unit breakdown with papers | Detail panel |
| `paper_detail` | `code`, `date` (opt) | Paper metrics | Drill-down |
| `get_trend` | `type`, `metric`, `time_range` | Historical data points | TrendSlider |
| `view_subscribers` | `unit`, `metric_type` (opt) | Subscriber list (max 10k) | Table panel |

### Security
- **SQL Injection:** PDO prepared statements (all queries)
- **Auth:** Newzware credentials via `auth_check.php`
- **Session Timeout:** 2 hours
- **Brute Force:** `brute_force_protection.php`

---

## Docker & Deployment

### Development vs Production Strategy

| | Development | Production |
|---|-------------|------------|
| **Compose File** | `docker-compose.yml` | `docker-compose.prod.yml` |
| **Code Source** | Volume mounts (`./web:/var/www/html`) | Baked into image |
| **Benefits** | Fast iteration, hot reload | Immutable, portable |
| **Drawbacks** | Requires local source | Slower deployment |

### Multi-Platform Support

```bash
# Platforms supported
- linux/amd64 (Synology NAS)
- linux/arm64 (Apple Silicon Mac)

# Auto-selects correct architecture from Docker Hub
```

### Build & Push Workflow

```bash
# 1. Make changes in development
cd $PROJECT_ROOT

# 2. Test locally
docker compose up -d
# Test at http://localhost:8081

# 3. Build multi-platform image
./build-and-push.sh           # Tags as 'latest'
./build-and-push.sh v1.2.3    # Tags as version + latest

# 4. Deploy to production (see below)
```

### Production Deployment (Synology NAS)

**Location:** `192.168.1.254:/volume1/docker/nwdownloads`

```bash
# 1. SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# 2. Navigate to project
cd /volume1/docker/nwdownloads

# 3. Pull latest image
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull

# 4. Deploy
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# 5. Verify
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

**Critical Rules:**
- ❌ NEVER make changes directly in production
- ✅ ALWAYS test in development first
- ❌ NEVER copy code files to production (use Docker Hub only)
- ✅ Configuration files only via SSH

---

## Common Operations

### Check Container Status

```bash
# Development
docker compose ps

# Production
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

### View Logs

```bash
# Development
docker compose logs -f           # All services
docker compose logs -f web       # Web only
docker compose logs -f database  # DB only

# Production
sudo /usr/local/bin/docker logs circulation_web
sudo /usr/local/bin/docker logs circulation_db
```

### Database Access

```bash
# Development (interactive)
docker exec -it circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard

# Query
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "SELECT COUNT(*) FROM daily_snapshots;"
```

### Database Backup

```bash
# Create backup
docker exec circulation_db mariadb-dump -uroot -pRootPassword456! circulation_dashboard > backup_$(date +%Y%m%d).sql

# Restore
cat backup_20251207.sql | docker exec -i circulation_db mariadb -uroot -pRootPassword456! circulation_dashboard
```

### Upload CSV Data

**URL:** `http://localhost:8081/upload.html` (dev) or `http://192.168.1.254:8081/upload.html` (prod)

**File Format:** All Subscriber Report CSV from Newzware

**Required Columns:** `Ed`, `ISS`, `DEL`

**Processing Time:** 10-30 seconds for ~8,000 rows

---

## Troubleshooting

### Build Failures

**Error:** Cannot connect to Docker daemon  
**Solution:** Start Docker Desktop or check service: `docker info`

**Error:** COPY failed: no such file  
**Solution:** Ensure in project root: `cd $PROJECT_ROOT && ./build-and-push.sh`

**Error:** bad interpreter: `/bin/bash^M`  
**Solution:** Fix line endings:
```bash
sed -i '' 's/\r$//' build-and-push.sh && chmod +x build-and-push.sh
```

### Push Failures

**Error:** Access denied  
**Solution:** `docker login` (username: binarybcc)

**Error:** Repository not found  
**Solution:** Verify at https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ

### Deployment Failures

**Container won't start:**
1. Check logs: `sudo /usr/local/bin/docker logs circulation_web`
2. Wait for DB health check
3. Check port 8081: `netstat -an | grep 8081`
4. Verify environment variables in `docker-compose.prod.yml`

**Database connection failed:**
```bash
# Test connectivity
sudo /usr/local/bin/docker exec circulation_web php -r "\$pdo = new PDO('mysql:host=database;dbname=circulation_dashboard', 'circ_dash', 'Barnaby358@Jones!'); echo 'Connected!';"
```

**Old code still running:**
```bash
# Verify image digest
sudo /usr/local/bin/docker inspect circulation_web | grep Image

# Force recreate
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate
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

### 1. Docker Hub Hybrid Approach (Dec 2025)
**Decision:** Development uses volume mounts, Production uses pre-built images  
**Rationale:** Fast iteration in dev, stability in prod

### 2. Week-Based Uploads (Dec 2025)
**Decision:** Later day-of-week data replaces earlier in same week  
**Rationale:** Prevents accidental overwrite of better data with stale snapshots  
**Implementation:** `upload.php` lines 412-484

### 3. Multi-Platform Builds (Dec 7, 2025)
**Decision:** Build native images for AMD64 and ARM64  
**Rationale:** Eliminate QEMU emulation overhead on NAS  
**Performance Gain:** 10-30%

### 4. Event-Based UI (Dec 7, 2025)
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

### Docker
- **Container Startup:** ~5s web, ~30s database
- **Multi-arch Performance:** Native execution (no emulation)

---

## Quick Reference

### URLs

| Environment | Dashboard | Upload |
|-------------|-----------|--------|
| **Development** | http://localhost:8081/ | http://localhost:8081/upload.html |
| **Production** | http://192.168.1.254:8081/ | http://192.168.1.254:8081/upload.html |

### Repositories
- **GitHub:** https://github.com/binarybcc/nwdownloads
- **Docker Hub:** https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ

### Credentials

**Database (Development):**
- Root: `RootPassword456!`
- App User: `circ_dash` / `Barnaby358@Jones!`

**Synology SSH:**
- Host: `192.168.1.254`
- User: `it`
- Password: `Mojave48ice`

---

## File Organization

### Root Files
- `build-and-push.sh` - Multi-platform Docker build script
- `docker-compose.yml` - Development config (volume mounts)
- `docker-compose.prod.yml` - Production config (Docker Hub images)
- `Dockerfile` - Web container build definition
- `.envrc` - direnv config (auto-sets PROJECT_ROOT)
- `.env` - Environment variables (NOT committed)

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
- Multi-platform Docker builds (AMD64 + ARM64)
- Fixed setTimeout race condition (event-based)
- Environment variable migration (.env)
- Week-based upload precedence system
- Build script line ending fix

### December 6, 2025
- Chart interactions system refactored
- TrendSlider component (759 lines)
- Replaced ChartTransitionManager

### December 2, 2025
- Data cleanup - deleted all pre-2025 data
- Reason: Rate system change made historical data incomparable

---

**End of Knowledge Base**
