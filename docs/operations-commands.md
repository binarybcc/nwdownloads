# Operations Commands Reference

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

**IMPORTANT:** Always `source .env.credentials` before running any production command.

## Production (Native Synology Services)

```bash
# Load credentials first
source .env.credentials

# SSH into NAS
sshpass -p "$SSH_PASSWORD" ssh $SSH_USER@$SSH_HOST

# Deploy code updates from GitHub
~/deploy-circulation.sh

# Check MariaDB status
sudo systemctl status mariadb10

# Access database directly
mysql -u"$PROD_DB_USERNAME" -p"$PROD_DB_PASSWORD" -S "$PROD_DB_SOCKET" "$PROD_DB_DATABASE"

# View PHP error logs
tail -f /volume1/web/circulation/error.log

# Check file permissions
ls -la /volume1/web/circulation/

# Fix permissions if needed
find /volume1/web/circulation/ -type f -name '*.php' -exec chmod 644 {} \;
find /volume1/web/circulation/ -type d -exec chmod 755 {} \;

# View recent changes
cd /volume1/homes/it/circulation-deploy
git log --oneline -10
```

## Deploy Code Updates to Production

```bash
# Step 1: Merge changes to master on GitHub (via PR workflow)
# See docs/git-pr-workflow.md for PR workflows

# Step 2: Deploy to Production
source .env.credentials
sshpass -p "$SSH_PASSWORD" ssh $SSH_USER@$SSH_HOST
~/deploy-circulation.sh

# Step 3: Verify deployment
# Open: https://cdash.upstatetoday.com
# Check version number in footer
```

**Deployment Script Output:**

```
==> Circulation Dashboard Deployment
==> Pulling latest code from GitHub...
Already up to date.
==> Syncing files to production...
[rsync output showing changed files]
==> Fixing file permissions...
==> Deployment complete!
==> Dashboard: https://cdash.upstatetoday.com
```

**What the deployment script does:**

1. Pulls latest code from GitHub master branch
2. Syncs `web/` directory to `/volume1/web/circulation/` via rsync
3. Preserves production-specific files (`.htaccess`, `.build_number`)
4. Fixes file permissions automatically (644 for files, 755 for dirs)

## Manual File Copy to Production (Emergency Only)

```bash
# ONLY for emergency hotfixes when Git deployment can't be used
source .env.credentials

# Copy single file
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "cat > /volume1/web/circulation/api.php" < web/api.php

# Fix permissions after manual copy
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "chmod 644 /volume1/web/circulation/api.php"
```

## Check Database

```bash
source .env.credentials

# Production (native MariaDB via Unix socket)
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" \
  "mysql -u'$PROD_DB_USERNAME' -p'$PROD_DB_PASSWORD' -S '$PROD_DB_SOCKET' '$PROD_DB_DATABASE' -e 'SHOW TABLES;'"

# Production (via SSH)
ssh nas "/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard -e 'SHOW TABLES;'"
```

## Verify Upload Data

```bash
# Check latest snapshots
ssh nas "/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard -e \"
  SELECT snapshot_date, paper_code, paper_name, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC, paper_code
  LIMIT 20;\""

# Check date range
ssh nas "/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard -e \"
  SELECT
    MIN(snapshot_date) as earliest,
    MAX(snapshot_date) as latest,
    COUNT(*) as total_records
  FROM daily_snapshots;\""
```
