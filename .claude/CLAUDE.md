# NWDownloads Project - Circulation Dashboard

## 📦 Version & Engineering Standards

**Current Version:** v2.0.0
**Version Location:** `package.json`
**Changelog:** `CHANGELOG.md` (root)

### Versioning Protocol (AUTOMATED)

This project follows **Semantic Versioning (SemVer)**:

- PATCH (2.0.x): Bug fixes, typos, security patches
- MINOR (2.x.0): New features, backwards compatible
- MAJOR (x.0.0): Breaking changes

**Automated Release Process** (using `standard-version`):

```bash
# Automatic version bump based on commits since last release
npm run release              # Auto-detects: feat=MINOR, fix=PATCH
npm run release:patch        # Force PATCH (bug fixes)
npm run release:minor        # Force MINOR (new features)
npm run release:major        # Force MAJOR (breaking changes)
npm run release:dry-run      # Preview without making changes
```

**What `standard-version` does automatically:**

1. ✅ Analyzes conventional commits since last release
2. ✅ Determines appropriate version bump (MAJOR/MINOR/PATCH)
3. ✅ Updates `package.json` version
4. ✅ Generates/updates `CHANGELOG.md` with grouped changes
5. ✅ Creates git commit: `chore(release): vX.Y.Z`
6. ✅ Creates git tag: `vX.Y.Z`

**Then push the release:**

```bash
git push --follow-tags origin master
```

**Manual override** (only if `standard-version` fails):

1. Update version in `package.json`
2. Update `CHANGELOG.md` with changes under appropriate section
3. Create git tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z: description"`

### Commit Message Format (MANDATORY)

All commits use **Conventional Commits**:

```
<type>(<scope>): <description>

feat(upload): add vacation file support
fix(api): correct date timezone handling
docs(readme): update deployment instructions
refactor(db): optimize snapshot queries
```

**Types:** feat, fix, docs, style, refactor, test, chore, perf, security

### Branch Strategy

- `master` - Production-ready code
- `feature/*` - New features
- `fix/*` - Bug fixes
- `hotfix/*` - Urgent production fixes

### Code Conventions

- **PHP:** snake_case for variables/functions, PascalCase for classes
- **JavaScript:** camelCase for variables/functions
- **Files:** kebab-case for web assets, snake_case for PHP

---

## 🚨 CRITICAL: PRODUCTION IS NOT DOCKER 🚨

# ⛔ PRODUCTION DOES NOT USE DOCKER ⛔

# ⛔ PRODUCTION DOES NOT USE DOCKER ⛔

# ⛔ PRODUCTION DOES NOT USE DOCKER ⛔

**PRODUCTION ENVIRONMENT:**

- **Native Synology Apache + PHP + MariaDB**
- **NO Docker containers**
- **NO docker-compose**
- **Files served directly from `/volume1/web/circulation/`**
- **Deployment via file copy ONLY**

**DEVELOPMENT ENVIRONMENT:**

- Uses Docker for local testing
- Runs at http://localhost:8081

**NEVER run Docker commands on production NAS!**

---

## Project Overview

Newspaper circulation dashboard for tracking subscriber metrics across multiple business units and publications.

## 📚 Documentation Reference

**Primary Documentation** (Updated December 10, 2025):

- `/docs/DESIGN-SYSTEM.md` - **Component library and UI patterns** (READ FIRST when working on UI!)
  - Metric card grid layouts
  - Standard metric card pattern
  - ContextMenu component
  - SubscriberTablePanel component
  - Anti-patterns to avoid
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

## 🔐 CREDENTIAL SETUP (FIRST-TIME SETUP)

**IMPORTANT: Before running ANY deployment or database commands, you must set up credential files.**

### Quick Setup (30 seconds):

```bash
# 1. Copy the example file
cp .env.credentials.example .env.credentials

# 2. Edit with your actual credentials
nano .env.credentials
# (Replace all "your_*_here" values with real credentials)

# 3. Test it works
source .env.credentials && echo "SSH Host: $SSH_HOST"
# Should output: SSH Host: 192.168.1.254

# 4. Done! All commands in this file will now work.
```

### Credential File Locations:

**For Deployment Scripts** (Project-Specific):

- Template: `.env.credentials.example` (committed to git)
- Your copy: `.env.credentials` (gitignored, contains real credentials)
- Usage: `source .env.credentials` before running commands

**For Reference** (Global, Human-Readable):

- Location: `~/docs/CREDENTIALS.md`
- **CONFIDENTIAL** - Complete credential reference
- Never committed to git
- Contains connection strings, setup instructions, rotation schedule

### Error Handling:

**If commands fail with "command not found" or empty variables:**

```bash
# Check if credential file exists
ls -la .env.credentials

# Verify file is sourced
source .env.credentials
echo "Test: SSH_HOST=$SSH_HOST"

# If empty, check for syntax errors in .env.credentials
cat .env.credentials | grep -v "^#" | grep "="
```

---

## ⚠️ PRODUCTION OPERATIONS PROTOCOL (MANDATORY)

**Before ANY production database, deployment, or infrastructure operation, Claude MUST:**

1. **Set up credentials** - Source `.env.credentials` file (see setup section above)
2. **Read the documentation** - Contains all connection details and workflows
3. **Check KNOWLEDGE-BASE.md** - Complete deployment workflows with commands
4. **Follow the 3-attempt rule** - If it takes more than 3 attempts, you didn't read the docs

**Key files to check BEFORE executing:**

- `.env.credentials` - **MUST be sourced first:** `source .env.credentials`
- `~/docs/CREDENTIALS.md` - Complete credential reference (SSH, DB, Docker Hub)
- `/docs/KNOWLEDGE-BASE.md` - System reference (deployment, configuration)
- `/docs/TROUBLESHOOTING.md` - Decision trees for common issues
- `.claude/CLAUDE.md` - This file (quick reference and protocols)

**Critical production details:**

- Production uses **native Synology services** (NOT Docker)
- Database: MariaDB 10 via Unix socket (path in `$PROD_DB_SOCKET`)
- Credentials: Stored in `.env.credentials` (must source before use)
- Web directory: `/volume1/web/circulation/`
- Deployment: Git clone at `/volume1/homes/it/circulation-deploy` → rsync to production

**If you see Claude:**

- Trying multiple connection attempts (3+)
- Guessing at hostnames or credentials
- Getting "connection refused" or "access denied" repeatedly
- **Call it out immediately:** "Stop. Did you read the documentation first?"

## Environment Naming Convention

**PRODUCTION**: Synology NAS deployment

- **Location**: `/volume1/web/circulation/` on Synology NAS (192.168.1.254)
- **Access URL**: `https://cdash.upstatetoday.com`
- **Purpose**: Live, stable deployment for actual use
- **Database**: Native Synology MariaDB 10 via Unix socket (`/run/mysqld/mysqld10.sock`)
  - Credentials: See `~/docs/CREDENTIALS.md` or `.env.credentials`
- **Web Server**: Native Synology Web Station (PHP 8.2 + Apache)
- **Deployment Method**: Git pull from GitHub → rsync to production directory
- **Deployment Script**: `/volume1/homes/it/deploy-circulation.sh`

**DEVELOPMENT**: OrbStack/Local deployment

- **Location**: `$PROJECT_ROOT` (see Multi-Workstation Setup above for your specific path)
- **Access URL**: `http://localhost:8081/`
- **Purpose**: Testing, development, and experimentation
- **Database**: Local MariaDB container
  - Credentials: See `~/docs/CREDENTIALS.md` or `.env.credentials`
- **Web Server**: Local PHP container via Docker
- **Deployment Method**: Docker Compose on local machine

## 🔀 GIT WORKFLOW - PULL REQUEST PROTOCOL (MANDATORY)

**⚠️ CRITICAL RULE: NEVER COMMIT DIRECTLY TO MASTER/MAIN BRANCH**

**All changes MUST go through Pull Requests (PRs). No exceptions.**

### Why Pull Requests?

**Safety:**

- ✅ Code review before deployment
- ✅ Catch bugs before Production
- ✅ Test in Development environment first
- ✅ Easy rollback if issues occur

**Documentation:**

- ✅ Clear history of what changed and why
- ✅ Discussion and decisions preserved
- ✅ PR descriptions explain context

**Quality:**

- ✅ `@claude` can review code automatically
- ✅ Automated tests can run
- ✅ Team collaboration and feedback

### The Proper Workflow (For Every Feature/Fix):

**Step 1: Start with a Feature Branch**

```bash
# BEFORE making ANY changes
git checkout master
git pull origin master  # Get latest code

# Create descriptive branch name
git checkout -b feature/add-weekly-trends
# OR
git checkout -b fix/dashboard-loading-error
```

**Branch Naming Convention:**

- `feature/description` - New functionality
- `fix/description` - Bug fixes
- `refactor/description` - Code improvements
- `docs/description` - Documentation updates

**Step 2: Make Changes and Commit**

```bash
# Make your changes to files
# Test locally in Development environment

git add .
git commit -m "Add weekly trend comparison chart

- Added trend chart component
- Updated dashboard layout
- Added API endpoint for trend data
- Tested with last 4 weeks of data"
```

**Commit Message Format:**

```
Short summary (50 chars or less)

Detailed explanation:
- What changed
- Why it changed
- How to test it
```

**Step 3: Push Branch to GitHub**

```bash
git push -u origin feature/add-weekly-trends
```

**Step 4: Create Pull Request**

```bash
gh pr create \
  --title "Add weekly trend comparison chart" \
  --body "## Summary
Adds a new chart showing week-over-week subscriber trends by business unit.

## Changes:
- Added trend chart component to dashboard
- Created new API endpoint: /api/get_weekly_trends.php
- Updated dashboard layout to accommodate chart
- Added database query for trend calculations

## Testing:
- ✅ Tested with last 4 weeks of data
- ✅ Verified on Development environment (localhost:8081)
- ✅ Checked mobile and desktop layouts
- ✅ Validated data accuracy against raw reports

## Database Impact:
- No schema changes
- Uses existing daily_snapshots table
- Query performance: <100ms

## Deployment Notes:
- Test on Development first
- Deploy to Production after approval
- No additional configuration needed"
```

**Step 5: Review with Claude**

```bash
# In GitHub, add comment to PR:
@claude review this code for:
- Security vulnerabilities
- Performance issues
- Code quality
- Database query optimization
```

**Step 6: Test on Development**

```bash
# Switch to your branch
git checkout feature/add-weekly-trends

# Test locally
docker compose up -d
# Open http://localhost:8081 and verify changes
```

**Step 7: Merge When Ready**

```bash
# Option A: Via CLI
gh pr merge --squash  # Squashes commits into one

# Option B: Via GitHub Web
# Click "Merge pull request" button
```

**Step 8: Clean Up**

```bash
# Switch back to master
git checkout master
git pull origin master  # Get the merged changes

# Delete local branch (no longer needed)
git branch -d feature/add-weekly-trends

# Delete remote branch (optional, usually auto-deleted)
git push origin --delete feature/add-weekly-trends
```

### Quick Reference Commands:

**Start New Work:**

```bash
git checkout master && git pull && git checkout -b feature/my-feature
```

**Create PR:**

```bash
git push -u origin $(git branch --show-current)
gh pr create --title "Title" --body "Description"
```

**Review PR:**

```bash
gh pr view --web  # Opens in browser
gh pr checks      # Show status checks
```

**Merge PR:**

```bash
gh pr merge --squash
```

**Clean Up:**

```bash
git checkout master && git pull && git branch -d feature/my-feature
```

### Common Scenarios:

**Small Bug Fix:**

```bash
git checkout -b fix/typo-in-upload-form
# Fix the typo
git add web/upload_unified.php
git commit -m "Fix typo in upload form label"
git push -u origin fix/typo-in-upload-form
gh pr create --title "Fix typo in upload form" --body "Corrects 'Uplaod' to 'Upload'"
gh pr merge --squash
git checkout master && git pull
```

**Large Feature:**

```bash
git checkout -b feature/subscriber-alerts
# Work on feature over several commits
git commit -m "Add alert data model"
git commit -m "Add alert UI components"
git commit -m "Add email notification system"
git push -u origin feature/subscriber-alerts
gh pr create --title "Add subscriber alert system" --body "[detailed description]"
# Wait for review, make adjustments
# @claude review this PR
# Merge when approved
```

**Emergency Production Fix:**

```bash
git checkout -b hotfix/database-connection-error
# Fix the critical issue
git add .
git commit -m "Fix database connection timeout in production"
git push -u origin hotfix/database-connection-error
gh pr create --title "HOTFIX: Database connection timeout" --body "Critical fix for production issue"
# Get quick review
gh pr merge --squash
# Deploy to production immediately
```

### What NOT to Do:

**❌ WRONG - Direct to Master:**

```bash
git checkout master
git add .
git commit -m "Add feature"
git push origin master  # NEVER DO THIS!
```

**❌ WRONG - No Testing:**

```bash
git push origin feature/untested-feature
gh pr create
gh pr merge  # Without testing!
```

**❌ WRONG - Vague PR Description:**

```bash
gh pr create --title "Changes" --body "Updated stuff"
```

**✅ RIGHT - Proper Workflow:**

```bash
git checkout -b feature/descriptive-name
# Make changes
# Test thoroughly in Development
git commit -m "Clear description of what and why"
git push -u origin feature/descriptive-name
gh pr create --title "Clear title" --body "Detailed description with testing notes"
# @claude review this PR
# Test again
gh pr merge --squash
```

### Claude's Responsibilities:

**When implementing features, Claude MUST:**

1. ✅ Create a feature branch (NEVER commit to master)
2. ✅ Make changes on the branch
3. ✅ Test in Development environment
4. ✅ Create PR with detailed description
5. ✅ Wait for approval before merging
6. ✅ Clean up branch after merge

**Claude will ask: "Should I create a PR for review, or merge directly?"**

**If you say "merge" - Claude will:**

1. Create PR
2. Merge immediately
3. Clean up branch

**If you say "review" - Claude will:**

1. Create PR
2. Wait for your approval
3. Then merge and clean up

### Integration with Deployment Workflow

**Full Development → Production Flow:**

1. Create feature branch
2. Make changes in Development environment
3. Test thoroughly locally (http://localhost:8081)
4. Create Pull Request
5. Review (manual or `@claude` review)
6. Merge to master on GitHub
7. Deploy to Production via Git deployment script
8. Verify Production deployment at https://cdash.upstatetoday.com

**Never make changes directly in Production** - always test in Development first.
**Never commit directly to master** - always use Pull Requests.

### Git-Based Deployment to Production

**GitHub Repository**: `binarybcc/nwdownloads`
**URL**: https://github.com/binarybcc/nwdownloads

**Production Deployment Architecture:**

- Native Synology Web Station (NO Docker)
- Git clone at: `/volume1/homes/it/circulation-deploy`
- Production directory: `/volume1/web/circulation/`
- Deployment: Git pull → rsync to production
- SSH deploy key: `~/.ssh/github_circulation_deploy` (read-only)

**Development Environment** (`docker-compose.yml`):

- Uses **volume mounts** for live code editing
- Changes to `./web/` directory reflect immediately in browser
- No rebuilding required - fast iteration
- Database: Docker container

**Production Environment:**

- Native Synology services (Apache + PHP 8.2 + MariaDB 10)
- Files served from `/volume1/web/circulation/`
- Database via Unix socket: `/run/mysqld/mysqld10.sock`
- Web Service Portal: `https://cdash.upstatetoday.com`

**Development → Production Flow:**

1. Make changes in Development environment (with volume mounts)
2. Test thoroughly locally at http://localhost:8081/
3. Create PR and merge to master on GitHub
4. SSH into NAS: `sshpass -p 'Mojave48ice' ssh it@192.168.1.254`
5. Run deployment script: `~/deploy-circulation.sh`
6. Verify Production deployment at https://cdash.upstatetoday.com

**Deployment Script Details:**

```bash
# Location: /volume1/homes/it/deploy-circulation.sh
# What it does:
1. Pulls latest code from GitHub master branch
2. Syncs web/ directory to /volume1/web/circulation/
3. Preserves production-specific files (.htaccess, .build_number)
4. Fixes file permissions automatically (644 for files, 755 for directories)
```

**Critical Rules:**

- **GitHub is the single source of truth** - never push from NAS
- **Never make changes directly in Production** - always test in Development first
- **Deploy via deployment script only** - preserves production configuration
- **Production-specific files** (`.htaccess`, `.build_number`) are never overwritten

**SSH Deploy Key Setup:**

- Public key added to GitHub as read-only deploy key
- Private key: `/volume1/homes/it/.ssh/github_circulation_deploy`
- SSH config at: `~/.ssh/config` (uses deploy key for github.com)

**Documentation**: See `/docs/KNOWLEDGE-BASE.md` (Deployment section) for complete workflow details

## Key Technical Notes

### Synology-Specific Considerations:

- `.env` files may not be read properly by Docker Compose (hardcode values in `docker-compose.yml`)
- Use `sudo` for all Docker commands via SSH
- SSH credentials stored in approved Bash commands
- SCP/SFTP disabled - use SSH cat method for file transfers

### Database Connection:

- **Development**: Uses hostname `db` (Docker DNS works on OrbStack)
  - Credentials: `circ_dash` / `Barnaby358@Jones!`
- **Production**: Uses Unix socket `/run/mysqld/mysqld10.sock` (native Synology MariaDB 10)
  - Credentials: `root` / `P@ta675N0id`
  - Connection from PHP: `PDO("mysql:unix_socket=/run/mysqld/mysqld10.sock;dbname=circulation_dashboard")`
  - CLI access: `mysql -uroot -pP@ta675N0id -S /run/mysqld/mysqld10.sock circulation_dashboard`

## Production Management Commands

### Production (Native Synology Services):

```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Deploy code updates from GitHub
~/deploy-circulation.sh

# Check MariaDB status
sudo systemctl status mariadb10

# Access database directly
mysql -uroot -pP@ta675N0id -S /run/mysqld/mysqld10.sock circulation_dashboard

# Check Web Station status (via GUI only)
# Navigate to: DSM Control Panel > Web Station

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

### Development (Docker, local):

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
/database/                    - Database management (init, migrations, seeds)
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

### ⚠️ CRITICAL: Upload Interface Location

**CANONICAL UPLOAD INTERFACE:** `upload_unified.php`
**URL:** https://cdash.upstatetoday.com/upload_unified.php

- This is the ONLY upload interface - handles both Subscribers AND Vacations
- `upload.html` redirects to `upload_unified.php` (do not use directly)
- Any code/documentation referencing uploads MUST use `upload_unified.php`
- This has been the standard for 2+ weeks - do not regress!

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
1. Open: http://localhost:8081/upload_unified.php
2. Select file type (Subscribers or Vacations tab)
3. Click "Choose File" and select the CSV
4. Click "Upload and Process Data"
5. Wait 10-30 seconds for processing (~8,000 rows)
6. Review import summary showing:
   - New records added
   - Existing records updated
   - Total subscribers by business unit
7. Click "View Dashboard" to see updated data
```

**Production:**

```
1. Open: https://cdash.upstatetoday.com/upload_unified.php
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

### Deploy Code Updates to Production (Git-Based Deployment):

```bash
# Step 1: Merge changes to master on GitHub (via PR workflow)
# See ~/docs/git-workflow-examples.md for detailed PR workflows

# Step 2: Deploy to Production
# Load credentials
source .env.credentials
sshpass -p "$SSH_PASSWORD" ssh $SSH_USER@$SSH_HOST
~/deploy-circulation.sh

# Step 3: Verify deployment
# Open: https://cdash.upstatetoday.com
# Check version number in footer
# Verify new features/fixes are live
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

1. Pulls latest code from GitHub master branch to `/volume1/homes/it/circulation-deploy`
2. Syncs `web/` subdirectory to `/volume1/web/circulation/` via rsync
3. Preserves production-specific files (`.htaccess`, `.build_number`)
4. Fixes file permissions automatically (644 for files, 755 for directories)

**Note:** Code files are deployed via Git deployment script ONLY. Never copy .php or .html files directly to production directory.

### Manual File Copy to Production (Emergency Only):

```bash
# ONLY for emergency hotfixes when Git deployment can't be used
# Always follow up by committing to GitHub and running proper deployment

# Load credentials
source .env.credentials

# Copy single file
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "cat > /volume1/web/circulation/api.php" < web/api.php

# Fix permissions after manual copy
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" "chmod 644 /volume1/web/circulation/api.php"
```

### Check Database:

```bash
# Load credentials
source .env.credentials

# Production (native MariaDB via Unix socket)
sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" \
  "mysql -u'$PROD_DB_USERNAME' -p'$PROD_DB_PASSWORD' -S '$PROD_DB_SOCKET' '$PROD_DB_DATABASE' -e 'SHOW TABLES;'"

# Development (Docker container)
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" -e "SHOW TABLES;"
```

### Verify Upload Data:

```bash
# Load credentials
source .env.credentials

# Check latest snapshots
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" -e "
  SELECT snapshot_date, paper_code, paper_name, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC, paper_code
  LIMIT 20;
"

# Check date range
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" -e "
  SELECT
    MIN(snapshot_date) as earliest,
    MAX(snapshot_date) as latest,
    COUNT(*) as total_records
  FROM daily_snapshots;
"
```

---

**Remember**: Always use "Production" and "Development" when discussing deployments to maintain clarity.
