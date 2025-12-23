#!/bin/bash
# ============================================================================
# Safe Migration Runner
# ============================================================================
# Created: 2025-12-22
# Purpose: Run database migrations with automatic backups and tracking
#
# Usage:
#   ./scripts/run-migration.sh [migration_file] [environment]
#
# Examples:
#   ./scripts/run-migration.sh 010_add_user_preferences.sql development
#   ./scripts/run-migration.sh 010_add_user_preferences.sql production
#
# Safety Features:
# - Checks if migration already ran (prevents re-runs)
# - Creates automatic backup before execution
# - Tracks migration in migration_log table
# - Records execution time and status
# - Provides rollback instructions if migration fails
# ============================================================================

set -euo pipefail  # Exit on error, undefined variables, pipe failures

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# Configuration
# ============================================================================

MIGRATION_FILE="${1:-}"
ENVIRONMENT="${2:-development}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
MIGRATIONS_DIR="$PROJECT_ROOT/database/migrations"
BACKUP_DIR="$PROJECT_ROOT/backups/pre-migration"

# Database credentials based on environment
if [ "$ENVIRONMENT" = "production" ]; then
    # Load production credentials from .env.credentials
    if [ -f "$PROJECT_ROOT/.env.credentials" ]; then
        source "$PROJECT_ROOT/.env.credentials"
        DB_HOST="$PROD_DB_SOCKET"
        DB_USER="$PROD_DB_USERNAME"
        DB_PASS="$PROD_DB_PASSWORD"
        DB_NAME="$PROD_DB_DATABASE"
        DB_TYPE="socket"
    else
        echo -e "${RED}ERROR: .env.credentials not found${NC}"
        exit 1
    fi
else
    # Development - Docker container
    DB_HOST="database"
    DB_USER="${DB_USER:-circ_dash}"
    DB_PASS="${DB_PASSWORD:-Barnaby358@Jones!}"
    DB_NAME="${DB_NAME:-circulation_dashboard}"
    DB_TYPE="host"
fi

# ============================================================================
# Helper Functions
# ============================================================================

print_header() {
    echo ""
    echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Database connection helper
run_mysql() {
    if [ "$DB_TYPE" = "socket" ]; then
        # Production - Unix socket
        /usr/local/mariadb10/bin/mysql -u"$DB_USER" -p"$DB_PASS" -S "$DB_HOST" "$DB_NAME" "$@"
    else
        # Development - Docker container
        docker exec -i circulation_db mariadb -u"$DB_USER" -p"$DB_PASS" -D "$DB_NAME" "$@"
    fi
}

# ============================================================================
# Validation
# ============================================================================

print_header "Safe Migration Runner"

# Check if migration file provided
if [ -z "$MIGRATION_FILE" ]; then
    print_error "No migration file specified"
    echo ""
    echo "Usage: $0 [migration_file] [environment]"
    echo ""
    echo "Example: $0 010_add_user_preferences.sql development"
    exit 1
fi

# Check if migration file exists
MIGRATION_PATH="$MIGRATIONS_DIR/$MIGRATION_FILE"
if [ ! -f "$MIGRATION_PATH" ]; then
    print_error "Migration file not found: $MIGRATION_PATH"
    exit 1
fi

# Extract migration number from filename
MIGRATION_NUMBER=$(echo "$MIGRATION_FILE" | grep -oE '^[0-9]+' || echo "0")

print_info "Migration: $MIGRATION_FILE"
print_info "Environment: $ENVIRONMENT"
print_info "Migration Number: $MIGRATION_NUMBER"
echo ""

# ============================================================================
# Step 1: Check Migration Tracking Table Exists
# ============================================================================

print_header "Step 1: Checking Migration Tracking System"

# Check if migration_log table exists
TABLE_EXISTS=$(run_mysql -e "SHOW TABLES LIKE 'migration_log';" 2>/dev/null | grep -c "migration_log" || echo "0")

if [ "$TABLE_EXISTS" = "0" ]; then
    print_warning "Migration tracking table doesn't exist - creating it now..."

    # Run migration tracking setup
    if [ -f "$MIGRATIONS_DIR/000_migration_tracking.sql" ]; then
        run_mysql < "$MIGRATIONS_DIR/000_migration_tracking.sql"
        print_success "Migration tracking table created"
    else
        print_error "Migration tracking setup file not found: 000_migration_tracking.sql"
        exit 1
    fi
else
    print_success "Migration tracking table exists"
fi

# ============================================================================
# Step 2: Check If Migration Already Ran
# ============================================================================

print_header "Step 2: Checking Migration Status"

ALREADY_RAN=$(run_mysql -e "SELECT COUNT(*) as count FROM migration_log WHERE migration_file = '$MIGRATION_FILE' AND status = 'completed';" 2>/dev/null | tail -n 1 || echo "0")

if [ "$ALREADY_RAN" != "0" ]; then
    print_error "Migration already ran successfully: $MIGRATION_FILE"
    echo ""
    run_mysql -e "SELECT migration_file, executed_at, execution_time_seconds, status FROM migration_log WHERE migration_file = '$MIGRATION_FILE';"
    echo ""
    print_warning "This migration has already been applied. Re-running could cause data loss."
    echo ""
    read -p "Do you want to re-run anyway? (yes/NO): " CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        print_info "Migration aborted by user"
        exit 0
    fi
    print_warning "Re-running migration as requested..."
fi

print_success "Migration has not been run yet (or user confirmed re-run)"

# ============================================================================
# Step 3: Create Backup
# ============================================================================

print_header "Step 3: Creating Pre-Migration Backup"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/${TIMESTAMP}_pre_${MIGRATION_FILE%.sql}.sql"

print_info "Backing up database to: $BACKUP_FILE"

if [ "$DB_TYPE" = "socket" ]; then
    # Production backup
    /usr/local/mariadb10/bin/mysqldump -u"$DB_USER" -p"$DB_PASS" -S "$DB_HOST" "$DB_NAME" > "$BACKUP_FILE"
else
    # Development backup
    docker exec circulation_db mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"
fi

if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    print_success "Backup created: $BACKUP_SIZE"
else
    print_error "Backup failed - aborting migration"
    exit 1
fi

# ============================================================================
# Step 4: Record Migration Start
# ============================================================================

# Special case: Skip recording for 000_migration_tracking.sql itself
# (chicken-and-egg: can't record in a table that doesn't exist yet)
if [ "$MIGRATION_FILE" != "000_migration_tracking.sql" ]; then
    print_header "Step 4: Recording Migration Start"

    # Calculate file checksum
    FILE_CHECKSUM=$(shasum -a 256 "$MIGRATION_PATH" | cut -d' ' -f1)

    # Record migration start in tracking table
    run_mysql -e "
    INSERT INTO migration_log (
        migration_file,
        migration_number,
        status,
        file_checksum,
        backup_created,
        backup_path,
        executed_by
    ) VALUES (
        '$MIGRATION_FILE',
        $MIGRATION_NUMBER,
        'running',
        '$FILE_CHECKSUM',
        TRUE,
        '$BACKUP_FILE',
        'run-migration.sh'
    )
    ON DUPLICATE KEY UPDATE
        status = 'running',
        executed_at = CURRENT_TIMESTAMP,
        file_checksum = '$FILE_CHECKSUM',
        backup_created = TRUE,
        backup_path = '$BACKUP_FILE';
    "

    print_success "Migration start recorded in tracking table"
else
    print_header "Step 4: Skipping Migration Tracking"
    print_info "Migration tracking table will be created by this migration"
fi

# ============================================================================
# Step 5: Run Migration
# ============================================================================

print_header "Step 5: Running Migration"

START_TIME=$(date +%s)

print_info "Executing: $MIGRATION_FILE"
echo ""

# Run the migration and capture output
if run_mysql < "$MIGRATION_PATH" 2>&1 | tee /tmp/migration_output.log; then
    END_TIME=$(date +%s)
    EXECUTION_TIME=$((END_TIME - START_TIME))

    print_success "Migration completed successfully in ${EXECUTION_TIME}s"

    # Update tracking table with success (skip for tracking migration itself)
    if [ "$MIGRATION_FILE" != "000_migration_tracking.sql" ]; then
        run_mysql -e "
        UPDATE migration_log
        SET status = 'completed',
            execution_time_seconds = $EXECUTION_TIME
        WHERE migration_file = '$MIGRATION_FILE';
        "
        print_success "Migration status updated to 'completed'"
    else
        # For the tracking migration, insert the record now that the table exists
        FILE_CHECKSUM=$(shasum -a 256 "$MIGRATION_PATH" | cut -d' ' -f1)
        run_mysql -e "
        INSERT INTO migration_log (
            migration_file,
            migration_number,
            status,
            file_checksum,
            execution_time_seconds,
            backup_created,
            backup_path,
            executed_by
        ) VALUES (
            '$MIGRATION_FILE',
            $MIGRATION_NUMBER,
            'completed',
            '$FILE_CHECKSUM',
            $EXECUTION_TIME,
            TRUE,
            '$BACKUP_FILE',
            'run-migration.sh'
        );
        "
        print_success "Migration tracking system initialized and self-recorded"
    fi

else
    END_TIME=$(date +%s)
    EXECUTION_TIME=$((END_TIME - START_TIME))
    ERROR_MSG=$(cat /tmp/migration_output.log | tail -20)

    print_error "Migration FAILED after ${EXECUTION_TIME}s"

    # Update tracking table with failure
    run_mysql -e "
    UPDATE migration_log
    SET status = 'failed',
        execution_time_seconds = $EXECUTION_TIME,
        error_message = '$(echo "$ERROR_MSG" | head -1000)'
    WHERE migration_file = '$MIGRATION_FILE';
    "

    print_error "Migration status updated to 'failed'"

    # Provide rollback instructions
    print_header "ROLLBACK INSTRUCTIONS"
    echo ""
    print_warning "The migration failed. You can restore from backup:"
    echo ""
    echo "  # Restore from backup:"
    if [ "$DB_TYPE" = "socket" ]; then
        echo "  mysql -u$DB_USER -p$DB_PASS -S $DB_HOST $DB_NAME < $BACKUP_FILE"
    else
        echo "  docker exec -i circulation_db mariadb -u$DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE"
    fi
    echo ""
    print_warning "Error details:"
    cat /tmp/migration_output.log

    exit 1
fi

# ============================================================================
# Summary
# ============================================================================

print_header "Migration Summary"

run_mysql -e "
SELECT
    migration_file,
    migration_number,
    status,
    executed_at,
    CONCAT(execution_time_seconds, 's') as execution_time,
    backup_created,
    LEFT(backup_path, 50) as backup_location
FROM migration_log
WHERE migration_file = '$MIGRATION_FILE';
"

echo ""
print_success "Migration completed successfully!"
echo ""
print_info "Backup stored at: $BACKUP_FILE"
print_info "Keep backups for at least 30 days"
echo ""
