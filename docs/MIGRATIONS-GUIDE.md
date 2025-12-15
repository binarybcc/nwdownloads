# Database Migrations Guide

## Problem This Solves

When working across multiple computers, database schema changes need to be synchronized. Git syncs code but NOT database data. **Migrations solve this.**

## Quick Start

### After Pulling Code (Check for New Migrations)

```bash
# Check if there are pending migrations
ls database/migrations/

# If you see new migration files, run them manually:
docker compose exec database mysql -uroot -pMojave48ice circulation_dashboard < database/init/03_add_week_columns.sql
```

## Migration Files

Migrations are stored in `/database/migrations/` and tracked by Phinx.

**Current Migrations:**
- `20251208134338_initial_schema.php` - Base schema (for reference)
- `20251208134358_add_week_columns_to_daily_snapshots.php` - Adds week_num and year columns

## How to Create a New Migration

```bash
# Install phinx globally (one-time setup)
composer global require robmorgan/phinx

# Create new migration
~/.composer/vendor/bin/phinx create YourMigrationName

# Edit the generated file in database/migrations/
```

## How to Run Migrations (Manual Method - Current)

Since phinx isn't in the Docker container yet, run migrations manually:

**Development:**
```bash
# Copy the SQL from the migration PHP file
# Run it manually:
docker compose exec database mysql -uroot -pMojave48ice circulation_dashboard << 'EOF'
ALTER TABLE daily_snapshots ADD COLUMN week_num INT;
EOF
```

**Production (Synology NAS):**
```bash
# SSH to NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Run migration
cd /volume1/docker/nwdownloads
sudo /usr/local/bin/docker exec circulation_db mysql -uroot -pRootPassword456! circulation_dashboard < /path/to/migration.sql
```

## Future: Automated Migrations (TODO)

**Goal:** Add Phinx to Docker image so migrations run automatically on container startup.

**Required changes:**
1. Add phinx to `composer.json` in Docker image
2. Add migration script to container startup
3. Update `docker-compose.yml` to run migrations before web server starts

## Why Migrations Matter

**Without Migrations:**
- ❌ Manual SQL changes easily forgotten
- ❌ Different schema on each computer
- ❌ Production deployments risky
- ❌ No rollback capability
- ❌ Team collaboration difficult

**With Migrations:**
- ✅ Schema changes version controlled
- ✅ Automatic application on all machines
- ✅ Rollback support (up/down migrations)
- ✅ Safer production deployments
- ✅ Clear history of database changes

## Migration Best Practices

1. **One change per migration** - Don't combine unrelated schema changes
2. **Test before committing** - Run migration locally first
3. **Include rollback** - Write down() method for reversibility
4. **Descriptive names** - `AddUserEmailColumn` not `UpdateUsers`
5. **Commit migrations with code** - Schema + code changes together

## Troubleshooting

**"Column already exists" error:**
```bash
# Check what's already been applied
docker compose exec database mysql -uroot -pMojave48ice circulation_dashboard -e "DESCRIBE daily_snapshots;"
```

**"Table doesn't exist" error:**
```bash
# Check database initialization
docker compose exec database mysql -uroot -pMojave48ice -e "SHOW DATABASES;"
docker compose exec database mysql -uroot -pMojave48ice circulation_dashboard -e "SHOW TABLES;"
```

**Reset everything and start fresh:**
```bash
# WARNING: This deletes ALL data!
docker compose down -v
docker compose up -d
# Database will reinitialize from database/init/*.sql files
```
