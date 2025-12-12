#!/bin/bash
# Run database migrations on PRODUCTION environment (Synology NAS)
# Usage: ./scripts/run-migrations-production.sh
# IMPORTANT: Only run from johncorbin workstation (has production access)

set -e

# Get project root directory (parent of scripts/)
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Load credentials
source "$PROJECT_ROOT/.env.credentials" 2>/dev/null || {
  echo "❌ Error: .env.credentials not found in $PROJECT_ROOT"
  echo "💡 Run: cp .env.credentials.example .env.credentials"
  exit 1
}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}📊 Database Migration Runner (PRODUCTION)${NC}"
echo -e "${RED}⚠️  WARNING: This will modify the PRODUCTION database!${NC}"
echo ""

# Check if migrations directory exists
if [ ! -d "$PROJECT_ROOT/db_migrations" ]; then
  echo -e "${RED}❌ Error: db_migrations/ directory not found${NC}"
  exit 1
fi

# Confirm production deployment
read -p "Deploy migrations to PRODUCTION (Synology NAS)? (yes/NO): " -r
echo ""

if [[ ! $REPLY =~ ^yes$ ]]; then
  echo -e "${YELLOW}❌ Deployment cancelled${NC}"
  exit 1
fi

# Helper function to run SQL on production
run_production_sql() {
  local sql="$1"
  sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" \
    "mysql -u'$PROD_DB_USERNAME' -p'$PROD_DB_PASSWORD' -S '$PROD_DB_SOCKET' '$PROD_DB_DATABASE' -e \"$sql\""
}

# Helper function to run SQL file on production
run_production_sql_file() {
  local file="$1"
  sshpass -p "$SSH_PASSWORD" ssh "$SSH_USER@$SSH_HOST" \
    "mysql -u'$PROD_DB_USERNAME' -p'$PROD_DB_PASSWORD' -S '$PROD_DB_SOCKET' '$PROD_DB_DATABASE'" < "$file"
}

# Ensure migrations tracking table exists
echo -e "${YELLOW}🔧 Ensuring migrations tracking table exists...${NC}"
if [ -f "$PROJECT_ROOT/db_migrations/000_create_migrations_table.sql" ]; then
  run_production_sql_file "$PROJECT_ROOT/db_migrations/000_create_migrations_table.sql" 2>/dev/null || true
fi

# Get list of applied migrations
APPLIED_MIGRATIONS=$(run_production_sql "SELECT migration_number FROM schema_migrations ORDER BY migration_number;" 2>/dev/null | tail -n +2 || echo "")

# Find all migration files
PENDING_COUNT=0
APPLIED_COUNT=0

echo -e "${BLUE}📋 Checking for pending migrations...${NC}"
echo ""

for migration_file in "$PROJECT_ROOT/db_migrations"/*.sql; do
  if [ ! -f "$migration_file" ]; then
    continue
  fi

  # Extract migration number from filename
  filename=$(basename "$migration_file")
  migration_num=$(echo "$filename" | grep -oE '^[0-9]+' || echo "")

  if [ -z "$migration_num" ]; then
    echo -e "${YELLOW}⚠️  Skipping invalid filename: $filename${NC}"
    continue
  fi

  # Skip migration 000 (migrations table itself)
  if [ "$migration_num" = "000" ]; then
    continue
  fi

  # Skip migration 001 (initial schema - already applied)
  if [ "$migration_num" = "001" ]; then
    # Mark as applied if not already
    run_production_sql "INSERT IGNORE INTO schema_migrations (migration_number, migration_name, applied_by) VALUES (1, 'initial_schema', 'production_deploy');" 2>/dev/null || true
    continue
  fi

  # Check if migration has been applied
  if echo "$APPLIED_MIGRATIONS" | grep -q "^${migration_num}$"; then
    APPLIED_COUNT=$((APPLIED_COUNT + 1))
    continue
  fi

  # Apply pending migration
  PENDING_COUNT=$((PENDING_COUNT + 1))
  migration_name=$(echo "$filename" | sed 's/^[0-9]*_//' | sed 's/.sql$//')

  echo -e "${YELLOW}▶️  Applying migration $migration_num: $migration_name${NC}"

  # Run the migration
  if run_production_sql_file "$migration_file"; then
    # Record migration as applied
    run_production_sql "INSERT INTO schema_migrations (migration_number, migration_name, applied_by) VALUES ($migration_num, '$migration_name', 'production_deploy');"
    echo -e "${GREEN}   ✅ Migration $migration_num applied successfully${NC}"
  else
    echo -e "${RED}   ❌ Migration $migration_num failed!${NC}"
    exit 1
  fi

  echo ""
done

# Summary
echo ""
echo -e "${BLUE}════════════════════════════════════${NC}"
if [ $PENDING_COUNT -eq 0 ]; then
  echo -e "${GREEN}✅ Production database is up to date!${NC}"
  echo -e "${BLUE}   Total migrations: $(($APPLIED_COUNT + 1))${NC}"
else
  echo -e "${GREEN}✅ Applied $PENDING_COUNT new migration(s) to PRODUCTION${NC}"
  echo -e "${BLUE}   Total migrations: $(($APPLIED_COUNT + $PENDING_COUNT + 1))${NC}"
fi
echo -e "${BLUE}════════════════════════════════════${NC}"
echo ""

# Show migration history
echo -e "${BLUE}📜 Production Migration History:${NC}"
run_production_sql "SELECT migration_number, migration_name, applied_at, applied_by FROM schema_migrations ORDER BY migration_number;"
