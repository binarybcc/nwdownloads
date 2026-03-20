#!/bin/bash

#
# Database Migration Helper Script
# Runs Phinx migrations on the NAS via SSH
#
# Usage:
#   ./migrate.sh         - Run all pending migrations
#   ./migrate.sh status  - Show migration status only
#

set -e

COMMAND=${1:-migrate}

echo "Running Phinx migrations on NAS..."
echo ""

# Run phinx on NAS via SSH
ssh nas "cd /volume1/homes/it/circulation-deploy && \
    /var/packages/PHP8.2/target/usr/local/bin/php82 vendor/bin/phinx $COMMAND"

echo ""
echo "Done!"
