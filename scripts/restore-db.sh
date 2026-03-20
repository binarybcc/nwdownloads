#!/bin/bash
# Import development database from compressed SQL file
# Usage: ./scripts/restore-db.sh [input_file]

set -e

# Get project root directory (parent of scripts/)
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Load credentials
source "$PROJECT_ROOT/.env.credentials" 2>/dev/null || {
  echo "❌ Error: .env.credentials not found in $PROJECT_ROOT"
  echo "💡 Run: cp .env.credentials.example .env.credentials"
  exit 1
}

# Input location (default to Dropbox, or specify custom path)
INPUT_FILE="${1:-$HOME/Dropbox/circulation_dev.sql.gz}"

# Check if file exists
if [ ! -f "$INPUT_FILE" ]; then
  echo "❌ Error: File not found: $INPUT_FILE"
  echo "💡 Make sure you've dumped the database first with: ./scripts/dump-db.sh"
  exit 1
fi

echo "📥 Restoring database..."
echo "📍 Source: $INPUT_FILE"
echo "🎯 Target: Production NAS MariaDB"

# Show file info
FILE_SIZE=$(du -h "$INPUT_FILE" | cut -f1)
echo "📊 File size: $FILE_SIZE"

# Warning prompt
echo ""
echo "⚠️  WARNING: This will REPLACE all data in the production database!"
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "❌ Restore cancelled"
  exit 1
fi

# Clear existing data first
echo "🧹 Clearing existing data..."
ssh nas "/usr/local/mariadb10/bin/mysql \
  -u$PROD_DB_USERNAME \
  -p'$PROD_DB_PASSWORD' \
  -S $PROD_DB_SOCKET \
  $PROD_DB_DATABASE \
  -e 'TRUNCATE TABLE IF EXISTS daily_snapshots;'"

echo "✅ Existing data cleared"
echo ""

# Decompress and import
echo "🔄 Importing data..."
gunzip < "$INPUT_FILE" | ssh nas "/usr/local/mariadb10/bin/mysql \
  -u$PROD_DB_USERNAME \
  -p'$PROD_DB_PASSWORD' \
  -S $PROD_DB_SOCKET \
  $PROD_DB_DATABASE"

echo ""
echo "✅ Database restored successfully!"
echo ""
echo "💡 Verify by running:"
echo "   ssh nas \"/usr/local/mariadb10/bin/mysql -u$PROD_DB_USERNAME -p'$PROD_DB_PASSWORD' -S $PROD_DB_SOCKET -D$PROD_DB_DATABASE -e 'SELECT COUNT(*) FROM daily_snapshots;'\""
