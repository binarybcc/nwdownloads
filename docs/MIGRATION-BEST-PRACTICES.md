# Database Migration Best Practices

**Created:** 2025-12-22
**Purpose:** Prevent catastrophic data loss from unsafe migrations

---

## 🚨 The Golden Rules

1. **NEVER use `DROP TABLE` in migrations** - Use `ALTER TABLE` instead
2. **ALWAYS run migrations through `run-migration.sh`** - Automatic backups + tracking
3. **NEVER run migrations directly with `mysql <`** - No safety net
4. **ALL migrations must be idempotent** - Safe to re-run without data loss
5. **Test in development first** - ALWAYS

---

## How We Lost Data (December 15, 2025)

**What Happened:**

- Migration `001_initial_schema.sql` was re-run on production
- It contained `DROP TABLE IF EXISTS` for ALL data tables
- All data was permanently deleted (daily_snapshots, raw_uploads, subscriber_snapshots)
- Only 2 CSV uploads from after the disaster survived

**Root Cause:**

- No migration tracking (could re-run migrations)
- Destructive "initial schema" migration not separated from incremental migrations
- No pre-migration backups
- No safeguards against running on production

**Lesson Learned:**
Never trust that a migration won't be re-run. Design every migration to be safe even if run 100 times.

---

## Migration System Components

### 1. Migration Tracking (`migration_log` table)

**Purpose:** Records which migrations have been run to prevent re-runs

```sql
-- Check if migration already ran
SELECT * FROM migration_log WHERE migration_file = '010_add_column.sql';
```

**Tracked Information:**

- Migration filename and number
- Execution timestamp and duration
- Success/failure status
- File checksum (detect changes)
- Backup location
- Error messages if failed

### 2. Migration Runner (`scripts/run-migration.sh`)

**What it does:**

1. ✅ Checks if migration already ran
2. ✅ Creates automatic backup
3. ✅ Records migration start
4. ✅ Runs migration
5. ✅ Updates tracking table
6. ✅ Provides rollback instructions if failed

**Usage:**

```bash
# Development
./scripts/run-migration.sh 010_add_user_preferences.sql development

# Production (with automatic backup)
./scripts/run-migration.sh 010_add_user_preferences.sql production
```

### 3. Migration Status Checker (`scripts/check-migrations.sh`)

**Shows:**

- Which migrations have been run
- Which migrations are pending
- Execution status and timing
- Backup status

**Usage:**

```bash
# Check development status
./scripts/check-migrations.sh development

# Check production status
./scripts/check-migrations.sh production
```

---

## Writing Safe Migrations

### ✅ DO: Idempotent Operations

**Idempotent = Safe to run multiple times without changing the result**

```sql
-- ✅ GOOD - Safe to re-run
CREATE TABLE IF NOT EXISTS new_table (
    id INT PRIMARY KEY,
    name VARCHAR(100)
);

-- ✅ GOOD - Safe to re-run (MariaDB 10.0.2+)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email VARCHAR(100);

-- ✅ GOOD - Safe to re-run
CREATE INDEX IF NOT EXISTS idx_email ON users(email);

-- ✅ GOOD - Upsert pattern
INSERT INTO settings (key, value)
VALUES ('max_upload_size', '10MB')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

### ❌ DON'T: Destructive Operations

```sql
-- ❌ BAD - Deletes all data if re-run
DROP TABLE IF EXISTS daily_snapshots;

-- ❌ BAD - Loses data if re-run
ALTER TABLE users DROP COLUMN email;

-- ❌ BAD - Can't re-run safely
ALTER TABLE users ADD COLUMN email VARCHAR(100);  -- Fails second time

-- ❌ BAD - Truncates data
TRUNCATE TABLE daily_snapshots;

-- ❌ BAD - Deletes data
DELETE FROM daily_snapshots WHERE snapshot_date < '2025-01-01';
```

### How to Make Changes Safely

#### Adding a Column

```sql
-- ✅ Safe way (MariaDB 10.0.2+)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone VARCHAR(20);

-- ✅ Alternative if IF NOT EXISTS not available
-- Check column doesn't exist first (procedural approach)
SET @col_exists = (SELECT COUNT(*)
                   FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'phone');

SET @query = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN phone VARCHAR(20)',
    'SELECT "Column already exists"');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

#### Renaming a Column

```sql
-- ✅ Safe way - Add new, copy data, keep old
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_address VARCHAR(100);

UPDATE users
SET email_address = email
WHERE email_address IS NULL AND email IS NOT NULL;

-- Don't drop the old column in the migration!
-- Do that in a future migration after verifying data migrated correctly
```

#### Changing Data

```sql
-- ❌ BAD - Not idempotent
UPDATE users SET status = 'active';

-- ✅ GOOD - Idempotent with WHERE clause
UPDATE users
SET status = 'active'
WHERE status IS NULL;

-- ✅ EVEN BETTER - Check first
UPDATE users
SET status = 'active'
WHERE status IS NULL
  AND status != 'active';  -- Prevents changes on re-run
```

#### Removing a Column (Dangerous!)

```sql
-- Don't do this in a migration! Instead:

-- Step 1: Stop using the column in code (deploy code first)
-- Step 2: Wait 1 week to ensure no dependencies
-- Step 3: Create a migration that comments it as deprecated:

ALTER TABLE users
MODIFY COLUMN old_column VARCHAR(100) COMMENT 'DEPRECATED - Remove after 2025-02-01';

-- Step 4: Schedule manual removal with backup
-- (This is too dangerous for automated migration)
```

---

## Migration File Naming

**Format:** `NNN_descriptive_name.sql`

- `NNN` = Three-digit number (001, 002, 010, etc.)
- Use leading zeros for proper sorting
- Descriptive name with underscores
- Always use `.sql` extension

**Examples:**

```
010_add_user_email_column.sql
011_create_notifications_table.sql
012_add_indexes_for_performance.sql
013_migrate_old_rates_data.sql
```

**Number Gaps:**

- Leave gaps (010, 020, 030) for future insertions
- If you need to insert between 010 and 020, use 015
- Never renumber existing migrations!

---

## Migration Template

```sql
-- ============================================================================
-- Migration: [NNN]_[description]
-- ============================================================================
-- Created: YYYY-MM-DD
-- Purpose: [Clear description of what this migration does and why]
--
-- Dependencies:
--   - Requires migration XXX to be run first
--   - Modifies table: [table_name]
--
-- Rollback:
--   - [How to undo this migration if needed]
--
-- Testing:
--   - [ ] Tested on development
--   - [ ] Verified idempotent (can re-run safely)
--   - [ ] Backup created before production run
-- ============================================================================

-- Check prerequisites
-- (e.g., verify required tables exist)

-- Perform migration
CREATE TABLE IF NOT EXISTS new_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Description of table purpose';

-- Verification query
SELECT 'Migration completed successfully' AS status;

-- Optional: Show created objects
SHOW CREATE TABLE new_table;

-- ============================================================================
-- Migration Complete
-- ============================================================================
```

---

## Running Migrations

### Development Workflow

```bash
# 1. Write migration
vim database/migrations/010_add_user_preferences.sql

# 2. Test locally first
./scripts/run-migration.sh 010_add_user_preferences.sql development

# 3. Verify it worked
./scripts/check-migrations.sh development

# 4. Commit to git
git add database/migrations/010_add_user_preferences.sql
git commit -m "Add migration: user preferences table"

# 5. Push to GitHub
git push
```

### Production Workflow

```bash
# 1. Ensure migration is tested in development
./scripts/check-migrations.sh development

# 2. Check production status
./scripts/check-migrations.sh production

# 3. SSH into production
ssh it@192.168.1.254

# 4. Pull latest code
cd /volume1/homes/it/circulation-deploy
git pull

# 5. Run migration (automatic backup included!)
./scripts/run-migration.sh 010_add_user_preferences.sql production

# 6. Verify success
./scripts/check-migrations.sh production

# 7. Verify application works
# Open https://cdash.upstatetoday.com and test
```

---

## Emergency Rollback

**If a migration fails:**

1. **Don't panic** - Backup was created automatically

2. **Check the error:**

   ```bash
   ./scripts/check-migrations.sh production
   ```

3. **Restore from backup:**

   ```bash
   # Find the backup file
   ls -lht backups/pre-migration/ | head

   # Restore (Production)
   mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard < backups/pre-migration/[backup_file]

   # OR (Development)
   docker exec -i circulation_db mariadb -ucirc_dash -p circulation_dashboard < backups/pre-migration/[backup_file]
   ```

4. **Fix the migration** and try again

---

## Common Mistakes to Avoid

### 1. Running Migrations Manually

```bash
# ❌ NEVER DO THIS
mysql -u root -p circulation_dashboard < 010_add_column.sql

# ✅ ALWAYS DO THIS
./scripts/run-migration.sh 010_add_column.sql production
```

**Why:** Manual runs skip:

- Duplicate run checks
- Automatic backups
- Execution tracking
- Error recording
- Rollback instructions

### 2. Non-Idempotent Migrations

```sql
-- ❌ BAD - Fails on second run
ALTER TABLE users ADD COLUMN email VARCHAR(100);

-- ✅ GOOD - Safe to re-run
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100);
```

### 3. Mixing DDL and DML

```sql
-- ⚠️ RISKY - Schema and data changes together
CREATE TABLE new_table (...);
INSERT INTO new_table SELECT * FROM old_table;
DROP TABLE old_table;

-- ✅ BETTER - Separate migrations
-- Migration 010: Create new_table
-- Migration 011: Copy data (with verification)
-- Migration 012: Deprecate old_table (don't drop!)
```

### 4. No Verification Queries

```sql
-- ❌ BAD - No confirmation migration worked
CREATE TABLE users (...);

-- ✅ GOOD - Verify the change
CREATE TABLE users (...);

SELECT 'Users table created' AS status;
SHOW CREATE TABLE users;
SELECT COUNT(*) as column_count FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'users';
```

---

## Testing Migrations

### Before Running on Production

1. **Test in development:**

   ```bash
   ./scripts/run-migration.sh 010_migration.sql development
   ```

2. **Verify idempotency** - Run it twice:

   ```bash
   # Should succeed both times
   ./scripts/run-migration.sh 010_migration.sql development
   ./scripts/run-migration.sh 010_migration.sql development
   ```

3. **Check data integrity:**

   ```bash
   docker exec -i circulation_db mariadb -ucirc_dash -p circulation_dashboard -e "SELECT COUNT(*) FROM daily_snapshots;"
   ```

4. **Test application still works:**
   - Open http://localhost:8081
   - Click through all features
   - Upload a CSV file
   - Verify dashboard loads

### After Running on Production

1. **Verify migration succeeded:**

   ```bash
   ./scripts/check-migrations.sh production
   ```

2. **Check application:**
   - Open https://cdash.upstatetoday.com
   - Test all features
   - Check for errors in browser console

3. **Verify data:**

   ```bash
   # Count records
   mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "SELECT COUNT(*) FROM daily_snapshots;"

   # Spot check data
   mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "SELECT * FROM daily_snapshots ORDER BY snapshot_date DESC LIMIT 5;"
   ```

---

## Backup Management

### Automatic Backups

- Created before every migration by `run-migration.sh`
- Stored in: `backups/pre-migration/`
- Filename format: `YYYYMMDD_HHMMSS_pre_[migration_name].sql`

### Manual Backups

```bash
# Production
mysqldump -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard > backup_$(date +%Y%m%d_%H%M%S).sql

# Development
docker exec circulation_db mysqldump -ucirc_dash -p circulation_dashboard > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Backup Retention

- **Keep for 30 days minimum**
- **Keep pre-major-release backups indefinitely**
- **Test restore process quarterly**

---

## When Things Go Wrong

### Migration Failed

1. Check error message:

   ```bash
   ./scripts/check-migrations.sh production
   ```

2. Restore from backup (instructions provided by run-migration.sh)

3. Fix migration file

4. Test in development

5. Try again on production

### Migration Succeeded But Broke Application

1. **Immediate rollback:**

   ```bash
   # Restore database from pre-migration backup
   mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard < backups/pre-migration/[backup_file]
   ```

2. **Investigate:**
   - What changed?
   - What's broken?
   - Why didn't development testing catch it?

3. **Fix and re-deploy:**
   - Fix migration
   - Test thoroughly in development
   - Run again on production

### Accidental Data Deletion

**If `DROP TABLE` or `DELETE` was run:**

1. **Immediately restore from most recent backup**
2. **Don't run any more queries** (prevents overwriting redo logs)
3. **Contact database administrator if available**
4. **Document incident:**
   - What happened
   - What was lost
   - How to prevent in future

---

## Summary Checklist

**Before Writing a Migration:**

- [ ] Understand what needs to change
- [ ] Plan idempotent approach
- [ ] Avoid `DROP TABLE`, `TRUNCATE`, `DELETE`
- [ ] Use `IF NOT EXISTS` / `IF EXISTS`

**Before Running Migration:**

- [ ] Test in development
- [ ] Test idempotency (run twice)
- [ ] Verify application still works
- [ ] Check migration status

**Running Migration:**

- [ ] Use `run-migration.sh` (never manual SQL)
- [ ] Verify backup created
- [ ] Monitor execution
- [ ] Check for errors

**After Migration:**

- [ ] Verify migration succeeded
- [ ] Test application functionality
- [ ] Spot-check data integrity
- [ ] Keep backup for 30 days

---

**Remember:** A few extra minutes of caution prevents hours of disaster recovery.

**Last Updated:** 2025-12-22
