# Database Migration Helper

Help create and manage database migrations for the Circulation Dashboard project.

## When to use this skill

Use this skill when:
- Creating a new database table
- Adding/modifying columns
- Creating indexes or constraints
- Any database schema change
- Checking migration status
- Deploying migrations to production

## What this skill does

1. **Create New Migration**
   - Finds next available migration number
   - Creates properly named migration file
   - Provides template with best practices
   - Reminds to test before committing

2. **Check Migration Status**
   - Shows applied migrations
   - Lists pending migrations
   - Displays migration history

3. **Deploy Migrations**
   - Runs migrations on development
   - Guides production deployment process

## Migration Workflow

### Creating a New Migration

**Step 1: Describe the change**
Ask the user: "What database change do you need?" (e.g., "Add user preferences table")

**Step 2: Find next migration number**
```bash
ls -1 database/migrations/*.sql | grep -oE '^database/migrations/[0-9]+' | sed 's/database\/migrations\///' | sort -n | tail -1
```

**Step 3: Create migration file**
Format: `{number}_{description}.sql`
Example: `002_add_user_preferences.sql`

**Step 4: Write SQL template**
```sql
-- Migration: {Description}
-- Created: {Date}
-- Purpose: {Detailed explanation}

-- Forward migration
CREATE TABLE IF NOT EXISTS table_name (
  id INT PRIMARY KEY AUTO_INCREMENT,
  column_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rollback (for reference):
-- DROP TABLE IF EXISTS table_name;
```

**Step 5: Test on development**
```bash
./scripts/run-migrations.sh
```

**Step 6: Verify schema**
```bash
docker exec circulation_db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" circulation_dashboard -e "DESCRIBE table_name;"'
```

**Step 7: Commit to Git**
```bash
git add database/migrations/{number}_{description}.sql
git commit -m "Migration: {description}"
```

### Checking Migration Status

**Development:**
```bash
./scripts/run-migrations.sh  # Shows status at the end
```

**Or check directly:**
```bash
docker exec circulation_db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" circulation_dashboard -e "SELECT * FROM schema_migrations ORDER BY migration_number;"'
```

**Production:**
```bash
# Only from johncorbin workstation
./scripts/run-migrations-production.sh
```

### Best Practices Checklist

Before creating migration, verify:
- [ ] Migration is for SCHEMA changes only (not data)
- [ ] Using sequential numbering (next available number)
- [ ] Descriptive filename (e.g., `add_analytics_table` not `new_stuff`)
- [ ] Includes comments explaining purpose
- [ ] Uses `IF NOT EXISTS` / `IF EXISTS` for safety
- [ ] Tested on development first
- [ ] Rollback SQL included in comments

### Common Migration Patterns

**Add Table:**
```sql
CREATE TABLE IF NOT EXISTS new_table (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Add Column:**
```sql
ALTER TABLE existing_table
ADD COLUMN new_column VARCHAR(100) DEFAULT NULL AFTER existing_column;
```

**Add Index:**
```sql
CREATE INDEX idx_column_name ON table_name(column_name);
```

**Modify Column:**
```sql
ALTER TABLE table_name
MODIFY COLUMN column_name VARCHAR(500) NOT NULL;
```

### Error Handling

**If migration fails:**
1. Check SQL syntax
2. Check for naming conflicts
3. Verify dependencies (referenced tables exist)
4. Roll back manually if needed
5. Fix migration file
6. Re-run migrations

**Never:**
- Edit a migration file that's been committed and applied
- Skip migration numbers
- Combine schema and data changes

## Quick Commands

**Create migration:** `/db-migration create`
**Check status:** `/db-migration status`
**Run dev migrations:** `/db-migration run`
**Deploy to production:** `/db-migration deploy`

## Important Notes

- **Development**: Both workstations run migrations after `git pull`
- **Production**: Only `johncorbin` workstation deploys to production
- **Data sync**: Separate from migrations (use dump/restore scripts)
- **Real data**: Development uses real data, so test carefully!

## Troubleshooting

**Problem: "Migration already applied"**
- Solution: Create a new migration instead of editing existing

**Problem: "Table already exists"**
- Solution: Use `CREATE TABLE IF NOT EXISTS`

**Problem: Workstations out of sync**
- Solution: Both workstations run `./scripts/run-migrations.sh`

**Problem: Need to rollback**
- Solution: Create new migration that reverses changes (don't delete old one)
