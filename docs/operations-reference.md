# Operations Reference

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## Versioning Protocol (AUTOMATED)

Uses **Semantic Versioning** with `standard-version`:

```bash
npm run release              # Auto-detects: feat=MINOR, fix=PATCH
npm run release:patch        # Force PATCH
npm run release:minor        # Force MINOR
npm run release:major        # Force MAJOR
npm run release:dry-run      # Preview without changes
```

What it does automatically:

1. Analyzes conventional commits since last release
2. Determines version bump (MAJOR/MINOR/PATCH)
3. Updates `package.json` version
4. Generates/updates `CHANGELOG.md`
5. Creates commit: `chore(release): vX.Y.Z`
6. Creates tag: `vX.Y.Z`

Then push: `git push --follow-tags origin master`

**Manual override** (only if `standard-version` fails):

1. Update version in `package.json`
2. Update `CHANGELOG.md`
3. Create tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z: description"`

## Multi-Workstation Setup

| Workstation          | Path                                                |
| -------------------- | --------------------------------------------------- |
| Primary (johncorbin) | `/Users/johncorbin/Desktop/projs/nwdownloads/`      |
| Secondary (user)     | `/Users/user/Development/work/_active/nwdownloads/` |

Uses **direnv** to auto-set `$PROJECT_ROOT`. One-time setup:

```bash
direnv version
eval "$(direnv hook zsh)"    # Add to ~/.zshrc
source ~/.zshrc
cd /path/to/nwdownloads && direnv allow
```

## Credential Setup

**IMPORTANT: Source `.env.credentials` before any deployment/database commands.**

```bash
cp .env.credentials.example .env.credentials
nano .env.credentials  # Replace placeholder values
source .env.credentials && echo "SSH Host: $SSH_HOST"
```

| File                       | Purpose                                       |
| -------------------------- | --------------------------------------------- |
| `.env.credentials.example` | Template (committed to git)                   |
| `.env.credentials`         | Your credentials (gitignored)                 |
| `~/docs/CREDENTIALS.md`    | Global credential reference (never committed) |

## Environment Details

|                   | Production                                                          | Development                                |
| ----------------- | ------------------------------------------------------------------- | ------------------------------------------ |
| **Location**      | `/volume1/web/circulation/` on NAS                                  | `$PROJECT_ROOT` local                      |
| **URL**           | `https://cdash.upstatetoday.com`                                    | Local PHP dev server or direct file access |
| **Database**      | MariaDB 10 via Unix socket `$PROD_DB_SOCKET`                        | Local MariaDB or same NAS DB               |
| **Web Server**    | Synology Web Station (PHP 8.2 + Apache)                             | Local PHP 8.2                              |
| **Credentials**   | `$PROD_DB_USERNAME` / `$PROD_DB_PASSWORD`                           | `$DEV_DB_USERNAME` / `$DEV_DB_PASSWORD`    |
| **DB Connection** | `PDO("mysql:unix_socket=$PROD_DB_SOCKET;dbname=$PROD_DB_DATABASE")` | Same socket or localhost                   |

## Deployment Commands

### Git-Based Deployment (Standard)

```bash
# After merging PR to master:
source .env.credentials
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST"
~/deploy-circulation.sh
# Verify: https://cdash.upstatetoday.com
```

**Deployment script** (`/volume1/homes/it/deploy-circulation.sh`):

1. Pulls latest from GitHub master
2. Syncs `web/` to `/volume1/web/circulation/` via rsync
3. Preserves `.htaccess`, `.build_number`
4. Fixes permissions (644 files, 755 directories)

### Emergency Manual Copy (Use Sparingly)

```bash
source .env.credentials
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "cat > /volume1/web/circulation/api.php" < web/api.php
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "chmod 644 /volume1/web/circulation/api.php"
```

## Production Management Commands

```bash
source .env.credentials

# SSH into NAS
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST"

# Deploy
~/deploy-circulation.sh

# Database access
mysql -u"$PROD_DB_USERNAME" -p"$PROD_DB_PASSWORD" -S "$PROD_DB_SOCKET" "$PROD_DB_DATABASE"

# Error logs
tail -f /volume1/web/circulation/error.log

# Fix permissions
find /volume1/web/circulation/ -type f -name '*.php' -exec chmod 644 {} \;
find /volume1/web/circulation/ -type d -exec chmod 755 {} \;

# Recent changes
cd /volume1/homes/it/circulation-deploy && git log --oneline -10
```

## Database Queries

```bash
source .env.credentials

# Production (via SSH)
ssh nas
/usr/local/mariadb10/bin/mysql -u"$PROD_DB_USERNAME" -p"$PROD_DB_PASSWORD" -S "$PROD_DB_SOCKET" "$PROD_DB_DATABASE" -e 'SHOW TABLES;'

# Check latest snapshots (on NAS)
/usr/local/mariadb10/bin/mysql -u"$PROD_DB_USERNAME" -p"$PROD_DB_PASSWORD" -S "$PROD_DB_SOCKET" "$PROD_DB_DATABASE" -e "
  SELECT snapshot_date, paper_code, paper_name, total_active, deliverable
  FROM daily_snapshots ORDER BY snapshot_date DESC, paper_code LIMIT 20;"
```

## Synology-Specific Notes

- Web files served from `/volume1/web/circulation/`
- DB binary: `/usr/local/mariadb10/bin/mysql` (not system `mysql`)
- PHP CLI: `/var/packages/PHP8.2/target/usr/local/bin/php82`
- SSH via `ssh nas` (key auth configured)
- GitHub is the single source of truth - never push from NAS
