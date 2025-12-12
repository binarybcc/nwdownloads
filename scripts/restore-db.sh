#!/bin/bash
# Import development database from compressed SQL file
# Usage: ./scripts/restore-db.sh [input_file]

set -e

# Load credentials
source .env.credentials 2>/dev/null || {
  echo "❌ Error: .env.credentials not found"
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

echo "📥 Restoring development database..."
echo "📍 Source: $INPUT_FILE"
echo "🎯 Target: Docker container 'circulation_db'"

# Show file info
FILE_SIZE=$(du -h "$INPUT_FILE" | cut -f1)
echo "📊 File size: $FILE_SIZE"

# Warning prompt
echo ""
echo "⚠️  WARNING: This will REPLACE all data in the development database!"
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "❌ Restore cancelled"
  exit 1
fi

# Decompress and import
echo "🔄 Importing data..."
gunzip < "$INPUT_FILE" | docker exec -i circulation_db mariadb \
  -u"$DEV_DB_USERNAME" \
  -p"$DEV_DB_PASSWORD" \
  "$DEV_DB_DATABASE"

echo ""
echo "✅ Database restored successfully!"
echo "🚀 Your development environment is now in sync!"
echo ""
echo "💡 Verify by running:"
echo "   docker exec circulation_db mariadb -u$DEV_DB_USERNAME -p$DEV_DB_PASSWORD -D$DEV_DB_DATABASE -e 'SELECT COUNT(*) FROM daily_snapshots;'"
