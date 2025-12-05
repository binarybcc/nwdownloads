#!/bin/bash

# Auto-update cache-busting version dates in index.html
# Usage: ./scripts/update-version.sh

set -e

PROJECT_DIR="/Users/johncorbin/Desktop/projs/nwdownloads"
INDEX_FILE="$PROJECT_DIR/web/index.html"

# Get current date in YYYYMMDD format
NEW_VERSION=$(date +%Y%m%d)

echo "🔄 Updating cache-busting version to: $NEW_VERSION"

# Check if index.html exists
if [ ! -f "$INDEX_FILE" ]; then
    echo "❌ Error: index.html not found at $INDEX_FILE"
    exit 1
fi

# Backup the original file
cp "$INDEX_FILE" "$INDEX_FILE.backup"
echo "✅ Created backup: index.html.backup"

# Update all version parameters using sed
# This finds all ?v=YYYYMMDD patterns and replaces them with today's date
sed -i.tmp -E "s/\?v=[0-9]{8}/\?v=$NEW_VERSION/g" "$INDEX_FILE"
rm "$INDEX_FILE.tmp"

echo "✅ Updated all version parameters to ?v=$NEW_VERSION"

# Show what changed
echo ""
echo "📝 Changes made:"
grep -n "?v=$NEW_VERSION" "$INDEX_FILE" | head -5
echo "   ... (and more)"

echo ""
echo "🎉 Version update complete!"
echo ""
echo "Next steps:"
echo "  1. Review the changes: git diff web/index.html"
echo "  2. Deploy to production: Use /deploy-nwdownloads skill"
echo "  3. Restart containers on NAS"
