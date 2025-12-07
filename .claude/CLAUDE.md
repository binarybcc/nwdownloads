# NWDownloads Project - Circulation Dashboard

## Project Overview
Newspaper circulation dashboard for tracking subscriber metrics across multiple business units and publications.

## 📚 Documentation Reference

**Primary Documentation** (Updated December 7, 2025):
- `/docs/KNOWLEDGE-BASE.md` - Comprehensive reference covering:
  - System architecture & database schemas
  - Frontend structure & API endpoints  
  - Tech stack & business context
  - Docker deployment strategies
  - Common operations & performance notes
- `/docs/TROUBLESHOOTING.md` - Complete troubleshooting guide with:
  - Decision tree diagnostics for 9 common issue categories
  - Step-by-step solutions with copy-paste commands
  - Quick diagnostic commands reference
- `/docs/cost_analysis.md` - Real-world development cost analysis & ROI

**Recent Deployment Documentation:**
- `/docs/DEPLOYMENT-2025-12-07.md` - Multi-platform builds + setTimeout fix deployment

**Archived Documentation:**
- `/docs/ARCHIVE/` - Historical files including:
  - Superseded JSON knowledge base files (architecture.json, knowledge-base.json, etc.)
  - Legacy markdown documentation (33+ files)

**Note:** This CLAUDE.md file provides quick-reference commands and critical production protocols. For comprehensive technical details, refer to KNOWLEDGE-BASE.md and TROUBLESHOOTING.md above.

## 📍 Multi-Workstation Setup

**This project is developed across multiple computers with different file paths.**

**Primary Workstation (johncorbin):**
- Path: `/Users/johncorbin/Desktop/projs/nwdownloads/`

**Secondary Workstation (user):**
- Path: `/Users/user/Development/work/_active/nwdownloads/`

**In all documentation below, `$PROJECT_ROOT` refers to your local project directory.**

### Automatic Environment Setup (direnv)

**This project uses direnv to automatically set `$PROJECT_ROOT` when you enter the directory.**

**One-time setup per workstation:**

```bash
# 1. Verify direnv is installed
direnv version

# 2. Enable direnv in your shell (add to ~/.zshrc or ~/.bashrc)
eval "$(direnv hook zsh)"    # For zsh
# OR
eval "$(direnv hook bash)"   # For bash

# 3. Reload your shell
source ~/.zshrc   # or source ~/.bashrc

# 4. Navigate to project and allow direnv
cd /path/to/nwdownloads
direnv allow

# Done! PROJECT_ROOT is now auto-set when you cd into this directory
```

**How it works:**
- When you `cd` into the project, direnv automatically runs `.envrc`
- `.envrc` sets `PROJECT_ROOT=$(pwd)` - automatically adapts to each computer
- When you leave the directory, variables are unset automatically
- No conflicts with other projects!

## ⚠️ PRODUCTION OPERATIONS PROTOCOL (MANDATORY)

**Before ANY production database, deployment, or infrastructure operation, Claude MUST:**

1. **Read the documentation** - Contains all connection details, credentials, and workflows
2. **Check KNOWLEDGE-BASE.md** - Complete deployment workflows with commands
3. **Follow the 3-attempt rule** - If it takes more than 3 attempts, you didn't read the docs

**Key files to check BEFORE executing:**
- `/docs/KNOWLEDGE-BASE.md` - Complete system reference (deployment, credentials, configuration)
- `/docs/TROUBLESHOOTING.md` - Decision trees for common issues
- `.claude/CLAUDE.md` - This file (quick reference and protocols)

**Critical production details:**
- Database hostname from web container: `database` (Docker Compose service name, NOT IP address)
- Database credentials: `root` / `RootPassword456!`
- All Docker commands require sudo via SSH
- File transfer uses SSH cat method (SCP disabled)

**If you see Claude:**
- Trying multiple connection attempts (3+)
- Guessing at hostnames or credentials
- Getting "connection refused" or "access denied" repeatedly
- **Call it out immediately:** "Stop. Did you read the documentation first?"

## Environment Naming Convention

**PRODUCTION**: Synology NAS deployment
- **Location**: `/volume1/docker/nwdownloads/` on Synology NAS (192.168.1.254)
- **Access URL**: `http://192.168.1.254:8081/`
- **Purpose**: Live, stable deployment for actual use
- **Database**: MariaDB 10.11 container (`circulation_db`)
- **Web Server**: PHP 8.2 + Apache container (`circulation_web`)
- **Deployment Method**: Docker Compose via SSH

**DEVELOPMENT**: OrbStack/Local deployment
- **Location**: `$PROJECT_ROOT` (see Multi-Workstation Setup above for your specific path)
- **Access URL**: `http://localhost:8081/`
- **Purpose**: Testing, development, and experimentation
- **Database**: Local MariaDB container
- **Web Server**: Local PHP container
- **Deployment Method**: Docker Compose on local machine

## Deployment Workflow

### Docker Hub Hybrid Approach

**Repository**: `binarybcc/nwdownloads-circ`
**URL**: https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ/

**Development Environment** (`docker-compose.yml`):
- Uses **volume mounts** for live code editing
- Changes to `./web/` directory reflect immediately in browser
- No rebuilding required - fast iteration
- Files: `docker-compose.yml` (default config)

**Production Environment** (`docker-compose.prod.yml`):
- Uses **pre-built images** from Docker Hub
- Application code **baked into image** (no volume mounts)
- Fully containerized and portable
- Files: `docker-compose.prod.yml` (production config)

**Development → Production Flow:**
1. Make changes in Development environment (with volume mounts)
2. Test thoroughly locally at http://localhost:8081/
3. Build and push to Docker Hub: `./build-and-push.sh`
4. Deploy to Production by pulling latest image
5. Verify Production deployment at http://192.168.1.254:8081/

**Critical Rules:**
- **Never make changes directly in Production** - always test in Development first
- **Never copy code files to Production** - deploy via Docker Hub only
- **Configuration files only** via SSH (docker-compose.prod.yml, db_init scripts)

**Documentation**: See `/docs/KNOWLEDGE-BASE.md` (Docker & Deployment section) for complete workflow details

## Key Technical Notes

### Synology-Specific Considerations:
- `.env` files may not be read properly by Docker Compose (hardcode values in `docker-compose.yml`)
- Use `sudo` for all Docker commands via SSH
- SSH credentials stored in approved Bash commands
- SCP/SFTP disabled - use SSH cat method for file transfers

### Database Connection:
- **Development**: Uses hostname `db` (Docker DNS works on OrbStack)
- **Production**: Uses Docker Compose service name `database` (Docker network DNS works correctly)
  - From web container: `mysql -h database -p` or `PDO("mysql:host=database;...")`
  - Credentials: root / RootPassword456!

## Docker Management Commands

### Production (via SSH):
```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no -p 22 it@192.168.1.254

# Navigate to project
cd /volume1/docker/nwdownloads

# IMPORTANT: Always use docker-compose.prod.yml in production
# View running containers
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps

# Pull latest image from Docker Hub
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull

# Deploy with latest image
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# View logs
sudo /usr/local/bin/docker logs circulation_web
sudo /usr/local/bin/docker logs circulation_db

# Restart containers (without rebuilding)
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml restart

# Stop containers
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml down

# Force recreate (useful after image update)
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate
```

### Development (local):
```bash
# Navigate to project
cd $PROJECT_ROOT

# All standard docker compose commands work
docker compose ps
docker compose logs
docker compose restart
docker compose down
docker compose up -d
```

## Publications Tracked

### Wyoming (Business Unit: Wyoming)
- **TJ** (The Journal) - Print: Wed/Sat, Digital: Tue-Sat
- **TR** (The Ranger) - Print: Wed/Sat, Digital: Wed/Sat
- **LJ** (The Lander Journal) - Print: Wed/Sat, Digital: Wed/Sat
- **WRN** (Wind River News) - Print: Thu, Digital: Thu

### Michigan (Business Unit: Michigan)
- **TA** (The Advertiser) - Print: Wed only, no digital

### South Carolina (Business Unit: South Carolina)
- **TJ** (The Journal) - Print: Wed/Sat, Digital: Tue-Sat

**Note**: FN (Former News) represents sold/discontinued publications

## Data Notes

### Recent Data Cleanup (Dec 2, 2025)
- Deleted all pre-2025 data due to rate system change in January 2025
- Old subscription rates retired, making 2024 data incomplete/inaccurate
- Current data range: Jan 4, 2025 onwards (250 records)
- 2026 will be first year with valid year-over-year comparisons
- See: `/docs/KNOWLEDGE-BASE.md` (Data State section) for details

### Database Schema
- `daily_snapshots` - Daily circulation metrics by paper/business unit
- `publication_schedule` - Print/digital publication days by paper

## File Organization

```
/web/                         - PHP application and API
/sql/                         - Database initialization scripts
/db_init/                     - Database setup files
/docs/                        - Documentation
  /KNOWLEDGE-BASE.md          - Comprehensive system reference
  /TROUBLESHOOTING.md         - Decision tree troubleshooting guide
  /cost_analysis.md           - Development cost analysis
  /DEPLOYMENT-2025-12-07.md   - Recent deployment guide
  /ARCHIVE/                   - Historical docs (JSON KB files + 33 markdown files)
/docker-compose.yml           - Development config (volume mounts)
/docker-compose.prod.yml      - Production config (Docker Hub images)
/Dockerfile                   - Web container build definition
/build-and-push.sh            - Script to build and push to Docker Hub
/.envrc                       - direnv config (auto-sets PROJECT_ROOT)
/.env.example                 - Environment template
```

## Weekly Data Upload Process

### Overview
The dashboard uses an **UPSERT** (Update or Insert) system for importing weekly circulation data from Newzware's "All Subscriber Report".

**How UPSERT Works:**
- **New snapshots**: Automatically inserted into database
- **Existing snapshots**: Updated with latest subscriber counts
- **Date filter**: Only imports data from January 1, 2025 onwards
- **Safe operation**: Never deletes data, only adds or updates

### Step-by-Step Upload Process

**1. Export Data from Newzware**
- Run "All Subscriber Report" query in Newzware Ad-Hoc Query Builder
- Export results as CSV
- File saves as: `AllSubscriberReportYYYYMMDDHHMMSS.csv` (e.g., `AllSubscriberReport20251202135758.csv`)

**2. Upload to Dashboard**

**Development:**
```
1. Open: http://localhost:8081/upload.html
2. Click "Choose File" and select the CSV
3. Click "Upload and Process Data"
4. Wait 10-30 seconds for processing (~8,000 rows)
5. Review import summary showing:
   - New records added
   - Existing records updated
   - Total subscribers by business unit
6. Click "View Dashboard" to see updated data
```

**Production:**
```
1. Open: http://192.168.1.254:8081/upload.html
2. Follow same steps as Development
3. Dashboard automatically refreshes with new week's data
```

### What Gets Imported

**From AllSubscriberReport CSV:**
- **Paper Code** (Ed column) - TJ, TA, TR, LJ, WRN, FN
- **Delivery Type** (DEL column) - MAIL, CARR, INTE
- **Vacation Status** (Zone column) - VAC indicators

**Calculated Metrics:**
- `total_active` - Count of all subscribers
- `mail_delivery` - Subscribers with MAIL delivery
- `carrier_delivery` - Subscribers with CARR delivery
- `digital_only` - Subscribers with INTE delivery
- `on_vacation` - Subscribers with VAC in zone
- `deliverable` - total_active minus on_vacation

### Upload Results Example

```
✅ Import Successful!
Date Range: 2025-12-02 to 2025-12-02
New Records Added: 5
Existing Records Updated: 0
Total Records Processed: 5
Processing Time: 2.3 seconds

📊 Summary by Business Unit:
South Carolina: 3,106 subscribers
  Papers: TJ (1 snapshots)

Michigan: 2,909 subscribers
  Papers: TA (1 snapshots)

Wyoming: 1,610 subscribers
  Papers: TR, LJ, WRN (3 snapshots)
```

### Weekly Workflow

**Recommended Schedule: Every Saturday Morning**
1. Run All Subscriber Report in Newzware (captures current week)
2. Upload CSV to Production dashboard
3. Verify metrics look correct
4. Review weekly trends on dashboard

**Why Weekly?**
- Aligns with publication schedules (Wed/Sat print days)
- Provides consistent week-over-week comparison
- Saturday captures full week's data

### Troubleshooting

**Error: "CSV does not appear to be an All Subscriber Report"**
- Solution: Ensure you're exporting the "All Subscriber Report" query, not individual exports
- Required columns: Ed, ISS, DEL (system auto-trims whitespace)

**Error: "No valid data found"**
- Solution: Check that report includes active subscribers
- Date filter automatically excludes pre-2025 data

**Import seems slow:**
- Normal processing time: 10-30 seconds for ~8,000 rows
- Large files (>10MB) will be rejected

**Numbers look wrong:**
- Verify upload summary matches expected subscriber counts
- Check dashboard for business unit breakdowns
- Compare to previous week's numbers for reasonableness

### Database Schema

**daily_snapshots Table:**
```sql
PRIMARY KEY (snapshot_date, paper_code)  -- Enables UPSERT
- snapshot_date: DATE (e.g., '2025-12-02')
- paper_code: VARCHAR(10) (TJ, TA, TR, LJ, WRN, FN)
- paper_name: VARCHAR(100)
- business_unit: VARCHAR(50)
- total_active: INT
- deliverable: INT
- mail_delivery: INT
- carrier_delivery: INT
- digital_only: INT
- on_vacation: INT
- created_at: TIMESTAMP
- updated_at: TIMESTAMP (tracks last update)
```

## Common Tasks

### Deploy Code Updates to Production (Image-Based Deployment):
```bash
# Step 1: Build and push to Docker Hub (from either workstation)
cd $PROJECT_ROOT
./build-and-push.sh

# Step 2: Deploy to Production (SSH into NAS)
sshpass -p 'Mojave48ice' ssh it@192.168.1.254
cd /volume1/docker/nwdownloads
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# Step 3: Verify deployment
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
# Open: http://192.168.1.254:8081/
```

**Note:** Code files are deployed via Docker Hub images ONLY. Never copy .php or .html files directly to production.

### Deploy Configuration Files to Production:
```bash
# For docker-compose.prod.yml, db_init scripts, or other config files
# (NOT application code - that goes via Docker Hub)
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 "cat > /volume1/docker/nwdownloads/docker-compose.prod.yml" < docker-compose.prod.yml
```

### Check Database:
```bash
# Production (using root credentials)
sudo /usr/local/bin/docker exec circulation_db mariadb -uroot -pRootPassword456! -D circulation_dashboard -e "SHOW TABLES;"

# Development (using application user credentials)
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "SHOW TABLES;"
```

### Verify Upload Data:
```bash
# Check latest snapshots
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "
  SELECT snapshot_date, paper_code, paper_name, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC, paper_code
  LIMIT 20;
"

# Check date range
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "
  SELECT
    MIN(snapshot_date) as earliest,
    MAX(snapshot_date) as latest,
    COUNT(*) as total_records
  FROM daily_snapshots;
"
```

---

**Remember**: Always use "Production" and "Development" when discussing deployments to maintain clarity.
