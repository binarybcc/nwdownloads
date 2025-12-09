#!/bin/bash
# Enhanced Circulation Dashboard Deployment Script
# Handles code deployment, database migrations, and verification
# Location: /volume1/homes/it/scripts/deploy-production.sh

set -e  # Exit on error
set -o pipefail  # Exit on pipe failure

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_DIR="/volume1/homes/it/circulation-deploy"
PROD_DIR="/volume1/web/circulation"
WEB_DIR="${REPO_DIR}/web"
SQL_DIR="${REPO_DIR}/sql"
MIGRATION_LOG="${PROD_DIR}/.migrations.log"
DB_SOCKET="/run/mysqld/mysqld10.sock"
DB_NAME="circulation_dashboard"
DB_USER="root"
DB_PASS="P@ta675N0id"

# Logging function
log() {
    echo -e "${BLUE}==>${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Error handler
error_exit() {
    log_error "$1"
    exit 1
}

# Database query helper
db_query() {
    /usr/local/mariadb10/bin/mysql -u"${DB_USER}" -p"${DB_PASS}" -S "${DB_SOCKET}" "${DB_NAME}" -e "$1" 2>&1
}

# Check if migration has been run
migration_run() {
    local migration_file="$1"
    if [ ! -f "${MIGRATION_LOG}" ]; then
        return 1  # Migration log doesn't exist, migration hasn't run
    fi
    grep -q "^${migration_file}$" "${MIGRATION_LOG}" 2>/dev/null
}

# Record migration as run
record_migration() {
    local migration_file="$1"
    echo "${migration_file}" >> "${MIGRATION_LOG}"
    log_success "Recorded migration: ${migration_file}"
}

# Run database migration
run_migration() {
    local migration_file="$1"
    local migration_path="${SQL_DIR}/${migration_file}"

    if [ ! -f "${migration_path}" ]; then
        log_warning "Migration file not found: ${migration_file}"
        return 1
    fi

    if migration_run "${migration_file}"; then
        log "Migration already run: ${migration_file} (skipping)"
        return 0
    fi

    log "Running migration: ${migration_file}"

    # Run the migration
    if /usr/local/mariadb10/bin/mysql -u"${DB_USER}" -p"${DB_PASS}" -S "${DB_SOCKET}" "${DB_NAME}" < "${migration_path}"; then
        record_migration "${migration_file}"
        log_success "Migration completed: ${migration_file}"
    else
        error_exit "Migration failed: ${migration_file}"
    fi
}

# Verify vacation data integrity
verify_vacation_data() {
    log "Verifying vacation data integrity..."

    # Check for vacation dates without on_vacation flag in subscriber_snapshots
    local missing_flags=$(db_query "
        SELECT COUNT(*) as count
        FROM subscriber_snapshots
        WHERE vacation_start IS NOT NULL
        AND on_vacation = 0;
    " | tail -1)

    if [ "$missing_flags" != "0" ]; then
        log_warning "Found ${missing_flags} vacation records with missing on_vacation flag"
        log "Fixing on_vacation flags in subscriber_snapshots..."
        db_query "UPDATE subscriber_snapshots SET on_vacation = 1 WHERE vacation_start IS NOT NULL AND on_vacation = 0;" || error_exit "Failed to fix vacation flags"
        log_success "Fixed on_vacation flags in subscriber_snapshots"
    fi

    # Sync vacation counts from subscriber_snapshots to daily_snapshots
    log "Syncing vacation counts to daily_snapshots..."
    db_query "
        UPDATE daily_snapshots ds
        INNER JOIN (
            SELECT snapshot_date, paper_code, SUM(on_vacation) as vacation_count
            FROM subscriber_snapshots
            WHERE on_vacation = 1
            GROUP BY snapshot_date, paper_code
        ) ss ON ds.snapshot_date = ss.snapshot_date AND ds.paper_code = ss.paper_code
        SET ds.on_vacation = ss.vacation_count;
    " || error_exit "Failed to sync vacation counts to daily_snapshots"

    log_success "Vacation data integrity OK and synced"
}

# Verify CSS files
verify_css_files() {
    log "Verifying CSS files..."

    if [ ! -f "${PROD_DIR}/assets/output.css" ]; then
        log_error "Missing output.css file!"
        return 1
    fi

    local css_size=$(stat -f%z "${PROD_DIR}/assets/output.css" 2>/dev/null || echo "0")
    if [ "$css_size" -lt 10000 ]; then
        log_error "output.css is too small (${css_size} bytes), may be corrupted"
        return 1
    fi

    log_success "CSS files OK (output.css: ${css_size} bytes)"
}

# Main deployment
main() {
    log "Circulation Dashboard Deployment - $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""

    # Step 1: Pull latest code
    log "Step 1/7: Pulling latest code from GitHub..."
    cd "$REPO_DIR" || error_exit "Failed to change to repo directory"
    git pull origin master || error_exit "Git pull failed"
    log_success "Code updated"
    echo ""

    # Step 2: Run database migrations
    log "Step 2/7: Running database migrations..."

    # Ensure migration log exists
    touch "${MIGRATION_LOG}"

    # Run migrations in order
    for migration_file in $(ls "${SQL_DIR}"/*.sql 2>/dev/null | sort); do
        migration_name=$(basename "$migration_file")
        run_migration "$migration_name"
    done

    log_success "Database migrations complete"
    echo ""

    # Step 3: Verify vacation data integrity
    log "Step 3/7: Verifying vacation data integrity..."
    verify_vacation_data
    echo ""

    # Step 4: Sync files to production
    log "Step 4/7: Syncing files to production..."
    rsync -av --delete \
        --exclude='.htaccess' \
        --exclude='.build_number' \
        --exclude='.migrations.log' \
        --exclude='*.backup' \
        "${WEB_DIR}/" "${PROD_DIR}/" || error_exit "File sync failed"
    log_success "Files synced"
    echo ""

    # Step 5: Fix file permissions
    log "Step 5/7: Fixing file permissions..."
    find "$PROD_DIR" -type f -name '*.php' -exec chmod 644 {} \;
    find "$PROD_DIR" -type f -name '*.js' -exec chmod 644 {} \;
    find "$PROD_DIR" -type f -name '*.css' -exec chmod 644 {} \;
    find "$PROD_DIR" -type f -name '*.html' -exec chmod 644 {} \;
    find "$PROD_DIR" -type d -exec chmod 755 {} \;
    chmod 644 "$PROD_DIR/version.php" 2>/dev/null || true
    chmod 666 "$PROD_DIR/.build_number" 2>/dev/null || true
    log_success "Permissions fixed"
    echo ""

    # Step 6: Verify CSS files
    log "Step 6/7: Verifying CSS deployment..."
    verify_css_files || error_exit "CSS verification failed"
    echo ""

    # Step 7: Post-deployment verification
    log "Step 7/7: Post-deployment verification..."

    # Check database connection
    db_query "SELECT 1;" > /dev/null || error_exit "Database connection failed"
    log_success "Database connection OK"

    # Check vacation data counts
    local vacation_count=$(db_query "SELECT COUNT(*) FROM subscriber_snapshots WHERE on_vacation = 1;" | tail -1)
    log_success "Vacation records: ${vacation_count}"

    # Check total subscriber count
    local total_count=$(db_query "SELECT COUNT(*) FROM subscriber_snapshots;" | tail -1)
    log_success "Total subscriber records: ${total_count}"

    echo ""
    log_success "Deployment complete!"
    log "Dashboard: https://cdash.upstatetoday.com"
    echo ""
}

# Run main deployment
main "$@"
