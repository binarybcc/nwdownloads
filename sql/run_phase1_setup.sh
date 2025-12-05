#!/bin/bash
# Phase 1A Database Setup Script
# Executes all SQL files to create publication-aware database foundation
# Date: 2025-12-01

echo "=========================================="
echo "Phase 1A: Database Foundation Setup"
echo "=========================================="
echo ""

# Database connection details
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="circulation_dashboard"
DB_USER="circ_dash"
DB_PASS="Barnaby358@Jones!"
DB_SOCKET="/run/mysqld/mysqld10.sock"

# Path to MariaDB client in Docker container
MYSQL_CMD="/usr/local/mariadb10/bin/mysql"

# Check if we're running inside Docker container
if [ ! -f "$MYSQL_CMD" ]; then
    echo "❌ Error: This script must run inside the Docker container"
    echo "   Or update MYSQL_CMD to point to your MariaDB client"
    exit 1
fi

echo "Step 1: Creating publication_schedule table..."
$MYSQL_CMD --socket="$DB_SOCKET" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 01_create_publication_schedule.sql
if [ $? -eq 0 ]; then
    echo "✅ publication_schedule table created"
else
    echo "❌ Failed to create table"
    exit 1
fi
echo ""

echo "Step 2: Seeding publication schedule data..."
$MYSQL_CMD --socket="$DB_SOCKET" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 02_seed_publication_schedule.sql
if [ $? -eq 0 ]; then
    echo "✅ Publication schedule seeded (13 rows)"
else
    echo "❌ Failed to seed data"
    exit 1
fi
echo ""

echo "Step 3: Creating weekly_summary view..."
$MYSQL_CMD --socket="$DB_SOCKET" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 03_create_weekly_summary_view.sql
if [ $? -eq 0 ]; then
    echo "✅ weekly_summary view created"
else
    echo "❌ Failed to create view"
    exit 1
fi
echo ""

echo "=========================================="
echo "Phase 1A Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Upload CSVs on print days (Wed, Thu, Sat)"
echo "2. After 1 week, weekly_summary will show data"
echo "3. Proceed to Phase 1B (API development)"
echo ""
