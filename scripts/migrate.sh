#!/bin/bash

#
# Database Migration Helper Script
# Runs migrations using Docker exec to access database from inside web container
#
# Usage:
#   ./migrate.sh         - Run all pending migrations
#   ./migrate.sh status  - Show migration status only
#

set -e

COMMAND=${1:-migrate}

echo "🔄 Running Phinx migrations via Docker..."
echo ""

# Run phinx from host, but it will connect via Docker network
docker exec circulation_web bash -c "
    cd /var/www/html &&
    ~/.composer/vendor/bin/phinx $COMMAND
"

echo ""
echo "✅ Done!"
