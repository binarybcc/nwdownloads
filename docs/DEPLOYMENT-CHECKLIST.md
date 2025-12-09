# Deployment Checklist - Circulation Dashboard

## 🚀 Complete Deployment Regiment

This checklist ensures all code, assets, database migrations, and data integrity checks are handled in one sweep.

---

## Pre-Deployment Checklist

### 1. Code Review & Testing
- [ ] All changes tested in Development environment (http://localhost:8081)
- [ ] Pull Request created and reviewed
- [ ] PR merged to master on GitHub
- [ ] Local master branch updated (`git checkout master && git pull`)

### 2. Database Changes
- [ ] Database migrations created in `sql/` directory
- [ ] Migration files numbered sequentially (e.g., `05_add_vacation_dates.sql`)
- [ ] Migrations tested in Development environment
- [ ] Migration creates indexes if needed
- [ ] Migration uses `IF NOT EXISTS` for safety

### 3. Asset Verification
- [ ] Compiled CSS (`web/assets/output.css`) committed to repository
- [ ] JavaScript files updated if needed
- [ ] Images/icons included if added
- [ ] File sizes reasonable (CSS should be ~22KB)

---

## Deployment Process

### Option 1: Automated Deployment (Recommended)

**Single command deployment with all checks:**

```bash
# SSH into production NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Run enhanced deployment script
~/scripts/deploy-production.sh
```

**What the script does:**
1. ✅ Pulls latest code from GitHub
2. ✅ Runs database migrations (tracks what's been run)
3. ✅ Verifies vacation data integrity (fixes on_vacation flags)
4. ✅ Syncs all files to production
5. ✅ Fixes file permissions
6. ✅ Verifies CSS files deployed
7. ✅ Runs post-deployment checks

### Option 2: Manual Deployment (Emergency/Troubleshooting)

**If automated script fails, follow these steps:**

#### Step 1: Deploy Code
```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Pull latest code
cd /volume1/homes/it/circulation-deploy
git pull origin master

# Sync files to production
rsync -av --delete \
  --exclude='.htaccess' \
  --exclude='.build_number' \
  --exclude='*.backup' \
  /volume1/homes/it/circulation-deploy/web/ \
  /volume1/web/circulation/
```

#### Step 2: Run Database Migrations
```bash
# Run each migration file in sql/ directory
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  < /volume1/homes/it/circulation-deploy/sql/05_add_vacation_dates.sql
```

#### Step 3: Fix Data Integrity
```bash
# Fix on_vacation flags if needed
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e "UPDATE subscriber_snapshots SET on_vacation = 1 WHERE vacation_start IS NOT NULL AND on_vacation = 0;"
```

#### Step 4: Fix Permissions
```bash
find /volume1/web/circulation/ -type f -name '*.php' -exec chmod 644 {} \;
find /volume1/web/circulation/ -type f -name '*.js' -exec chmod 644 {} \;
find /volume1/web/circulation/ -type f -name '*.css' -exec chmod 644 {} \;
find /volume1/web/circulation/ -type d -exec chmod 755 {} \;
```

---

## Post-Deployment Verification

### 1. Visual Verification
- [ ] Open https://cdash.upstatetoday.com
- [ ] Dashboard loads with proper styling (no giant cloud icon)
- [ ] Business unit cards display in 3-column layout
- [ ] Vacation sections show counts (not all zeros)
- [ ] Hover effects work on vacation sections
- [ ] Click vacation section to see subscriber list
- [ ] Charts render correctly

### 2. Data Verification
```bash
# SSH into production and verify data
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Check vacation counts
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e "SELECT business_unit, SUM(on_vacation) as vacation_count
      FROM subscriber_snapshots
      WHERE snapshot_date = (SELECT MAX(snapshot_date) FROM subscriber_snapshots)
      GROUP BY business_unit;"
```

**Expected output:**
```
business_unit      | vacation_count
South Carolina     | 26
Michigan           | 2
Wyoming            | 1
```

### 3. File Verification
```bash
# Check CSS file exists and has correct size
ls -lh /volume1/web/circulation/assets/output.css
# Should show ~22KB file

# Check recent deployment files
ls -lt /volume1/web/circulation/ | head -10
```

### 4. Migration Log Verification
```bash
# Check which migrations have been run
cat /volume1/web/circulation/.migrations.log
```

---

## Troubleshooting

### Issue: CSS Not Loading (Giant Cloud Icon)
**Cause:** `output.css` missing or too small

**Fix:**
```bash
# Check CSS file size
ls -lh /volume1/web/circulation/assets/output.css

# If missing or < 10KB, re-deploy
cd /volume1/homes/it/circulation-deploy
git pull origin master
cp web/assets/output.css /volume1/web/circulation/assets/
chmod 644 /volume1/web/circulation/assets/output.css
```

### Issue: Vacation Data Shows All Zeros
**Cause:** `on_vacation` flag not set

**Fix:**
```bash
# Update on_vacation flags
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e "UPDATE subscriber_snapshots
      SET on_vacation = 1
      WHERE vacation_start IS NOT NULL
      AND on_vacation = 0;"

# Verify fix
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e "SELECT SUM(on_vacation) as vacation_count FROM subscriber_snapshots;"
```

### Issue: Database Migration Failed
**Cause:** Migration already partially run or syntax error

**Fix:**
1. Check migration log: `cat /volume1/web/circulation/.migrations.log`
2. Check if columns exist: `DESCRIBE subscriber_snapshots;`
3. Manually fix issues
4. Re-run migration with `IF NOT EXISTS` clauses

### Issue: Permission Denied Errors
**Cause:** File permissions incorrect after deployment

**Fix:**
```bash
# Reset all permissions
find /volume1/web/circulation/ -type f -exec chmod 644 {} \;
find /volume1/web/circulation/ -type d -exec chmod 755 {} \;
```

---

## Deployment History Tracking

### Creating Migration Files

**Naming convention:** `##_description.sql`
- Numbers should be sequential
- Use descriptive names
- Always include `IF NOT EXISTS` clauses

**Example:**
```sql
-- Migration: 06_add_email_notifications.sql
-- Date: 2025-12-09

-- Add email notification columns
ALTER TABLE subscriber_snapshots
ADD COLUMN IF NOT EXISTS notification_email VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS notification_enabled TINYINT(1) DEFAULT 0;

-- Create index
CREATE INDEX IF NOT EXISTS idx_notification_email
ON subscriber_snapshots(notification_email);
```

### Migration Log Location
- **Production:** `/volume1/web/circulation/.migrations.log`
- **Format:** One migration filename per line

### Checking Migration Status
```bash
# SSH into production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# View migration log
cat /volume1/web/circulation/.migrations.log

# Check table structure
/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e "DESCRIBE subscriber_snapshots;"
```

---

## Quick Reference Commands

### Deploy Everything
```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 '~/scripts/deploy-production.sh'
```

### Check Deployment Status
```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 'ls -lt /volume1/web/circulation/ | head -5'
```

### Verify Vacation Data
```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 \
  "/usr/local/mariadb10/bin/mysql -uroot -pP@ta675N0id -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e 'SELECT business_unit, SUM(on_vacation) FROM subscriber_snapshots GROUP BY business_unit;'"
```

### Check CSS File
```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 'stat -f%z /volume1/web/circulation/assets/output.css'
```

---

## Emergency Rollback

If deployment breaks production:

```bash
# SSH into production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Rollback code to previous commit
cd /volume1/homes/it/circulation-deploy
git log --oneline -5  # Find previous commit hash
git checkout <previous-commit-hash>

# Re-deploy old version
~/scripts/deploy-production.sh

# Database rollback (if migration caused issues)
# You'll need to manually reverse the migration
# Check .migrations.log to see what was run
```

---

**Last Updated:** 2025-12-09
**Next Review:** After next major deployment
