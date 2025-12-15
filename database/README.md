# Database Management

**Consolidated:** 2025-12-15
**Purpose:** Single organized location for all database-related files

## Directory Structure

### `/database/migrations/`
Migration scripts for schema changes. Includes:
- Phinx PHP migrations (if using Phinx migration tool)
- Manual SQL migrations (numbered 000_, 001_, etc.)

### `/database/init/`
Initial database setup scripts. Run these in order to set up a fresh database:
- `01_initial_data.sql` - Base tables and historical data
- `02_subscriber_snapshots.sql` - Subscriber snapshot table
- `03_add_week_columns.sql` - Week-based columns
- `04_add_source_tracking_columns.sql` - Source tracking

### `/database/seeds/`
Seed data for testing and development (optional data, not required for production)

## Usage

**Fresh database setup:**
```bash
# Run init scripts in order
mysql -u root -p < database/init/01_initial_data.sql
mysql -u root -p < database/init/02_subscriber_snapshots.sql
mysql -u root -p < database/init/03_add_week_columns.sql
mysql -u root -p < database/init/04_add_source_tracking_columns.sql
```

**Apply migrations:**
```bash
# If using Phinx
vendor/bin/phinx migrate

# Or run manual migrations
mysql -u root -p < database/migrations/000_create_migrations_table.sql
mysql -u root -p < database/migrations/001_initial_schema.sql
```

## Migration Strategy

Currently using **manual SQL migrations** (not Phinx). The Phinx configuration exists for future use if needed.
