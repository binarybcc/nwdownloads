#!/bin/bash
# ============================================================================
# Migration Status Checker
# ============================================================================
# Created: 2025-12-22
# Purpose: Check which migrations have been run and their status
#
# Usage:
#   ./scripts/check-migrations.sh [environment]
#
# Examples:
#   ./scripts/check-migrations.sh development
#   ./scripts/check-migrations.sh production
# ============================================================================

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

ENVIRONMENT="${1:-development}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Load credentials based on environment
if [ "$ENVIRONMENT" = "production" ]; then
    if [ -f "$PROJECT_ROOT/.env.credentials" ]; then
        source "$PROJECT_ROOT/.env.credentials"
        DB_HOST="$PROD_DB_SOCKET"
        DB_USER="$PROD_DB_USERNAME"
        DB_PASS="$PROD_DB_PASSWORD"
        DB_NAME="$PROD_DB_DATABASE"
        DB_TYPE="socket"
    fi
else
    DB_HOST="database"
    DB_USER="${DB_USER:-circ_dash}"
    DB_PASS="${DB_PASSWORD:-Barnaby358@Jones!}"
    DB_NAME="${DB_NAME:-circulation_dashboard}"
    DB_TYPE="host"
fi

# Database query helper
run_mysql() {
    if [ "$DB_TYPE" = "socket" ]; then
        /usr/local/mariadb10/bin/mysql -u"$DB_USER" -p"$DB_PASS" -S "$DB_HOST" "$DB_NAME" "$@"
    else
        docker exec -i circulation_db mariadb -u"$DB_USER" -p"$DB_PASS" -D "$DB_NAME" "$@"
    fi
}

echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Migration Status - $ENVIRONMENT${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""

# Check if migration_log exists
TABLE_EXISTS=$(run_mysql -e "SHOW TABLES LIKE 'migration_log';" 2>/dev/null | grep -c "migration_log" || echo "0")

if [ "$TABLE_EXISTS" = "0" ]; then
    echo -e "${YELLOW}⚠ Migration tracking not initialized${NC}"
    echo ""
    echo "Run this to initialize:"
    echo "  ./scripts/run-migration.sh 000_migration_tracking.sql $ENVIRONMENT"
    exit 0
fi

# Show all migrations
echo -e "${BLUE}All Migrations:${NC}"
echo ""
run_mysql -e "
SELECT
    migration_number as '#',
    migration_file as 'Migration File',
    status as 'Status',
    DATE_FORMAT(executed_at, '%Y-%m-%d %H:%i') as 'Executed',
    CONCAT(COALESCE(execution_time_seconds, 0), 's') as 'Time',
    CASE WHEN backup_created THEN 'Yes' ELSE 'No' END as 'Backup'
FROM migration_log
ORDER BY migration_number;
"

echo ""

# Show available migration files not yet run
echo -e "${BLUE}Available Migrations in database/migrations/:${NC}"
echo ""

MIGRATIONS_DIR="$PROJECT_ROOT/database/migrations"
cd "$MIGRATIONS_DIR"

for migration_file in *.sql; do
    # Skip if it's the tracking migration
    if [ "$migration_file" = "000_migration_tracking.sql" ]; then
        continue
    fi

    # Check if this migration has been run
    RUN_STATUS=$(run_mysql -e "SELECT status FROM migration_log WHERE migration_file = '$migration_file';" 2>/dev/null | tail -n 1 || echo "not_run")

    if [ "$RUN_STATUS" = "not_run" ] || [ -z "$RUN_STATUS" ]; then
        echo -e "${YELLOW}⧖ PENDING:   $migration_file${NC}"
    elif [ "$RUN_STATUS" = "completed" ]; then
        echo -e "${GREEN}✓ COMPLETED: $migration_file${NC}"
    elif [ "$RUN_STATUS" = "failed" ]; then
        echo -e "${RED}✗ FAILED:    $migration_file${NC}"
    else
        echo -e "${BLUE}● $RUN_STATUS: $migration_file${NC}"
    fi
done

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""
echo "To run a migration:"
echo "  ./scripts/run-migration.sh [migration_file] $ENVIRONMENT"
echo ""
echo "Example:"
echo "  ./scripts/run-migration.sh 010_add_user_preferences.sql $ENVIRONMENT"
echo ""
