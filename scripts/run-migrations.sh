#!/bin/bash
# Run database migrations for development environment
# Usage: ./scripts/run-migrations.sh

set -e

# Get project root directory (parent of scripts/)
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}📊 Database Migration Runner (Development)${NC}"
echo ""

# Check if migrations directory exists
if [ ! -d "$PROJECT_ROOT/db_migrations" ]; then
  echo -e "${RED}❌ Error: db_migrations/ directory not found${NC}"
  exit 1
fi

# Check if container is running
if ! docker ps | grep -q circulation_db; then
  echo -e "${RED}❌ Error: circulation_db container is not running${NC}"
  echo "💡 Run: docker compose up -d"
  exit 1
fi

# Ensure migrations tracking table exists
echo -e "${YELLOW}🔧 Ensuring migrations tracking table exists...${NC}"
if [ -f "$PROJECT_ROOT/db_migrations/000_create_migrations_table.sql" ]; then
  docker exec -i circulation_db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" circulation_dashboard' \
    < "$PROJECT_ROOT/db_migrations/000_create_migrations_table.sql" 2>/dev/null || true
fi

# Get list of applied migrations
APPLIED_MIGRATIONS=$(docker exec circulation_db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" circulation_dashboard -N -e "SELECT migration_number FROM schema_migrations ORDER BY migration_number;"' 2>/dev/null || echo "")

# Find all migration files (skip 000 and 001 initial schema)
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
    docker exec circulation_db sh -c "mariadb -uroot -p\"\$MYSQL_ROOT_PASSWORD\" circulation_dashboard -e \"INSERT IGNORE INTO schema_migrations (migration_number, migration_name, applied_by) VALUES (1, 'initial_schema', 'system');\"" 2>/dev/null || true
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
  if docker exec -i circulation_db sh -c "mariadb -uroot -p\"\$MYSQL_ROOT_PASSWORD\" circulation_dashboard" < "$migration_file"; then
    # Record migration as applied
    docker exec circulation_db sh -c "mariadb -uroot -p\"\$MYSQL_ROOT_PASSWORD\" circulation_dashboard -e \"INSERT INTO schema_migrations (migration_number, migration_name, applied_by) VALUES ($migration_num, '$migration_name', '$USER');\""
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
  echo -e "${GREEN}✅ Database is up to date!${NC}"
  echo -e "${BLUE}   Total migrations: $(($APPLIED_COUNT + 1))${NC}" # +1 for migration 000
else
  echo -e "${GREEN}✅ Applied $PENDING_COUNT new migration(s)${NC}"
  echo -e "${BLUE}   Total migrations: $(($APPLIED_COUNT + $PENDING_COUNT + 1))${NC}"
fi
echo -e "${BLUE}════════════════════════════════════${NC}"
echo ""

# Show migration history
echo -e "${BLUE}📜 Migration History:${NC}"
docker exec circulation_db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" circulation_dashboard -e "SELECT migration_number, migration_name, applied_at, applied_by FROM schema_migrations ORDER BY migration_number;"'
