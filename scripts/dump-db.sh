#!/bin/bash
# Export development database to compressed SQL file
# Usage: ./scripts/dump-db.sh [output_file]

set -e

# Get project root directory (parent of scripts/)
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Load credentials
source "$PROJECT_ROOT/.env.credentials" 2>/dev/null || {
  echo "❌ Error: .env.credentials not found in $PROJECT_ROOT"
  echo "💡 Run: cp .env.credentials.example .env.credentials"
  exit 1
}

# Output location (default to Dropbox, or specify custom path)
OUTPUT_FILE="${1:-$HOME/Dropbox/circulation_dev.sql.gz}"

echo "Dumping production database..."
echo "Source: NAS MariaDB (via ssh nas)"
echo "Output: $OUTPUT_FILE"

# Create directory if it doesn't exist
mkdir -p "$(dirname "$OUTPUT_FILE")"

# Dump and compress via SSH
ssh nas "/usr/local/mariadb10/bin/mysqldump -uroot \
  --single-transaction \
  --quick \
  --lock-tables=false \
  circulation_dashboard" | gzip > "$OUTPUT_FILE"

# Show file size
FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
echo ""
echo "Database exported successfully!"
echo "File size: $FILE_SIZE"
echo "Location: $OUTPUT_FILE"
echo ""
echo "💡 Next steps:"
echo "   1. Copy file to other workstation (or it's in Dropbox already)"
echo "   2. Run: ./scripts/restore-db.sh"
