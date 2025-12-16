# Database Initialization

This directory contains SQL scripts for initializing the Circulation Dashboard database with schema and seed data.

## Purpose

These scripts run automatically when the Development environment starts with a fresh database (Docker Compose).

## Initialization Scripts

### `01_initial_data.sql`
**Purpose:** Core data setup
- Creates `publication_schedule` table
- Inserts publication metadata for all papers (TJ, TA, TR, LJ, WRN, FN)
- Defines print/digital publishing days per publication
- Sets business unit assignments (Wyoming, Michigan, South Carolina)

**Key Data:**
```sql
-- Example: The Journal (TJ) in South Carolina
INSERT INTO publication_schedule VALUES
  ('TJ', 'The Journal', 'South Carolina',
   'Wednesday,Saturday', 'Tuesday,Wednesday,Thursday,Friday,Saturday');
```

### `02_subscriber_snapshots.sql`
**Purpose:** Creates core metrics table
- Defines `daily_snapshots` table schema
- Primary key: `(snapshot_date, paper_code)`
- Tracks daily subscriber counts by paper
- Metrics: total_active, deliverable, mail/carrier/digital, on_vacation

**IMPORTANT:** This script creates the table structure but does NOT populate data. Data is loaded via weekly CSV uploads (see `/docs/KNOWLEDGE-BASE.md` - Upload Process).

### `03_add_week_columns.sql`
**Purpose:** Adds week-based metrics
- Adds `year` column (INT, e.g., 2025)
- Adds `week_num` column (INT, 1-52)
- Enables week-over-week trend analysis
- Used by dashboard's weekly comparison features

### `04_add_source_tracking_columns.sql`
**Purpose:** Audit trail for data origins
- Adds `data_source` ENUM ('manual', 'upload', 'backfill')
- Adds `upload_filename` VARCHAR(255)
- Tracks how each snapshot entered the system
- Enables data quality auditing

## Execution Order

**Scripts run in numerical order (01 → 02 → 03 → 04):**

1. `01_initial_data.sql` - Base metadata
2. `02_subscriber_snapshots.sql` - Main data table
3. `03_add_week_columns.sql` - Week metrics
4. `04_add_source_tracking_columns.sql` - Audit columns

This order ensures dependencies are met (e.g., table must exist before adding columns).

## When These Scripts Run

### Development (Docker Compose)

**Automatic initialization:**
```bash
docker compose down -v  # Destroys database volume
docker compose up -d    # Recreates database and runs all init scripts
```

**Docker executes scripts via:**
- `docker-entrypoint-initdb.d/` directory (mounted in `docker-compose.yml`)
- MariaDB container automatically runs `.sql` files in alphabetical order on first startup

### Production (Synology NAS)

**Manual execution only:**
```bash
# SSH into production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Run scripts in order
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock circulation_dashboard \
  < /volume1/homes/it/circulation-deploy/database/init/01_initial_data.sql

mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock circulation_dashboard \
  < /volume1/homes/it/circulation-deploy/database/init/02_subscriber_snapshots.sql

# ... etc
```

**IMPORTANT:** Production database is persistent and rarely reset. These scripts were used for initial setup but are not typically re-run.

## Relationship to Migrations

**Initialization vs Migrations:**

- **Init scripts** (`database/init/`) - Fresh database setup (schema + core data)
- **Migration scripts** (`database/migrations/`) - Schema changes over time

**When to use init scripts:**
- Setting up Development environment for first time
- Creating test databases
- Onboarding new developers

**When to use migrations:**
- Adding new features to existing database
- Modifying schema in Production
- Incremental schema evolution

## Verifying Initialization

**Check if initialization completed:**
```bash
# Development
docker exec circulation_db mariadb \
  -ucirc_dash -p"Barnaby358@Jones!" -D circulation_dashboard \
  -e "SELECT COUNT(*) as publications FROM publication_schedule;"

# Should return: 6 (TJ, TA, TR, LJ, WRN, FN)
```

## Resetting Development Database

**Complete reset:**
```bash
# WARNING: Destroys all data!
docker compose down -v
docker compose up -d

# Verify clean state
docker exec circulation_db mariadb \
  -ucirc_dash -p"Barnaby358@Jones!" -D circulation_dashboard \
  -e "SELECT COUNT(*) FROM daily_snapshots;"

# Should return: 0 (no snapshots, awaiting CSV upload)
```

## Customizing Initialization

**Adding new publications:**
1. Edit `01_initial_data.sql`
2. Add INSERT statement with publication details
3. Rebuild Development database: `docker compose down -v && docker compose up -d`

**Modifying table structure:**
1. Edit `02_subscriber_snapshots.sql` for base structure
2. Or create new migration in `database/migrations/` for changes to existing databases

## Data Population

**After initialization, the database contains:**
- ✅ Publication metadata (6 papers)
- ✅ Empty `daily_snapshots` table (structure only)
- ❌ NO subscriber data (must upload CSV)

**To populate subscriber data:**
1. Export "All Subscriber Report" from Newzware
2. Upload CSV via `http://localhost:8081/upload.html`
3. Review import summary
4. Verify data on dashboard

See: `/docs/KNOWLEDGE-BASE.md` - Weekly Data Upload Process

## Troubleshooting

**Scripts don't run on docker compose up:**
- Ensure volume is destroyed first: `docker compose down -v`
- Check Docker logs: `docker compose logs db`
- Verify scripts are in `database/init/` directory

**"Table already exists" errors:**
- Database volume persists between restarts
- Use `docker compose down -v` to fully reset

**Missing publication_schedule data:**
- Verify `01_initial_data.sql` ran successfully
- Check logs for SQL errors during initialization

## Documentation

See `/docs/KNOWLEDGE-BASE.md` for complete database schema and data flow documentation.
