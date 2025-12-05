# NWDownloads Project - Circulation Dashboard

## Project Overview
Newspaper circulation dashboard for tracking subscriber metrics across multiple business units and publications.

## Environment Naming Convention

**PRODUCTION**: Synology NAS deployment
- **Location**: `/volume1/docker/nwdownloads/` on Synology NAS (192.168.1.254)
- **Access URL**: `http://192.168.1.254:8081/`
- **Purpose**: Live, stable deployment for actual use
- **Database**: MariaDB 10.11 container (`circulation_db`)
- **Web Server**: PHP 8.2 + Apache container (`circulation_web`)
- **Deployment Method**: Docker Compose via SSH

**DEVELOPMENT**: OrbStack/Local deployment
- **Location**: `/Users/johncorbin/Desktop/projs/nwdownloads/`
- **Access URL**: `http://localhost:8081/`
- **Purpose**: Testing, development, and experimentation
- **Database**: Local MariaDB container
- **Web Server**: Local PHP container
- **Deployment Method**: Docker Compose on local machine

## Deployment Workflow

**Development → Production Flow:**
1. Make changes in Development environment
2. Test thoroughly locally
3. Create archive of changes
4. Deploy to Production via SSH/SCP
5. Verify Production deployment

**Never make changes directly in Production** - always test in Development first.

## Key Technical Notes

### Synology-Specific Considerations:
- Docker DNS resolution doesn't work with hostnames (use IP addresses)
- `.env` files may not be read properly by Docker Compose (hardcode values in `docker-compose.yml`)
- Database container IP: `172.26.0.2` (may change on restart)
- Use `sudo` for all Docker commands via SSH
- SSH credentials stored in approved Bash commands

### Database Connection:
- **Development**: Uses hostname `db` (Docker DNS works on OrbStack)
- **Production**: Uses IP `172.26.0.2` (hardcoded due to Synology DNS issues)

## Docker Management Commands

### Production (via SSH):
```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no -p 22 it@192.168.1.254

# Navigate to project
cd /volume1/docker/nwdownloads

# View running containers
sudo /usr/local/bin/docker compose ps

# View logs
sudo /usr/local/bin/docker logs circulation_web
sudo /usr/local/bin/docker logs circulation_db

# Restart containers
sudo /usr/local/bin/docker compose restart

# Stop containers
sudo /usr/local/bin/docker compose down

# Start containers
sudo /usr/local/bin/docker compose up -d

# Rebuild and restart
sudo /usr/local/bin/docker compose up -d --build --force-recreate
```

### Development (local):
```bash
# Navigate to project
cd /Users/johncorbin/Desktop/projs/nwdownloads

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
- See: `/docs/data-cleanup-2025-12-02.md` for full details

### Database Schema
- `daily_snapshots` - Daily circulation metrics by paper/business unit
- `publication_schedule` - Print/digital publication days by paper

## File Organization

```
/web/              - PHP application and API
/sql/              - Database initialization scripts
/db_init/          - Database setup files
/docs/             - Documentation
/docker-compose.yml - Container orchestration
/Dockerfile        - Web container build
/.env.example      - Environment template
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

### Update Code on Production:
1. Test changes in Development
2. Create clean archive (exclude temp files)
3. SCP to NAS
4. Extract and restart containers

### Check Database:
```bash
# Production
sudo /usr/local/bin/docker exec circulation_db mariadb -ucircuser -pChangeThisPassword123! -D circulation_dashboard -e "SHOW TABLES;"

# Development
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
