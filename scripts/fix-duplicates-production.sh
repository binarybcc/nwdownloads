#!/bin/bash

# Fix duplicate subscriber records on production NAS
# Date: 2025-12-05

set -e

echo "Fixing duplicate subscriber records on Production NAS..."
echo ""

# Upload SQL script to NAS
echo "Uploading SQL script..."
ssh nas 'cat > /tmp/fix_duplicates.sql' < sql/06_fix_duplicate_subscribers.sql

# Execute SQL script
echo "Executing SQL script on production database..."
ssh nas << 'EOF'
/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard < /tmp/fix_duplicates.sql

echo ""
echo "Duplicate cleanup complete!"

# Clean up temp file
rm /tmp/fix_duplicates.sql
EOF

echo ""
echo "Production database fixed!"
echo ""
echo "Next step: Deploy updated upload.php to prevent future duplicates"
