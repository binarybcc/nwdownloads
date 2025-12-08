#!/bin/bash
#
# Deployment script for Circulation Dashboard (Native Web Station)
# Auto-increments build number and deploys to NAS
#

set -e  # Exit on error

PROJECT_ROOT="/Users/johncorbin/Desktop/projs/nwdownloads"
NAS_HOST="192.168.1.254"
NAS_USER="it"
NAS_PASSWORD="Mojave48ice"
NAS_PATH="/volume1/web/circulation"

echo "🚀 Circulation Dashboard Deployment"
echo "===================================="

# Increment build number
cd "$PROJECT_ROOT/web"
if [ -f .build_number ]; then
    CURRENT_BUILD=$(cat .build_number)
    NEW_BUILD=$((CURRENT_BUILD + 1))
    echo "$NEW_BUILD" > .build_number
    echo "✅ Build number incremented: $CURRENT_BUILD → $NEW_BUILD"
else
    echo "1" > .build_number
    NEW_BUILD=1
    echo "✅ Build number initialized: $NEW_BUILD"
fi

# Get version info
source version.php 2>/dev/null || true
MAJOR=2
MINOR=0
VERSION="v${MAJOR}.${MINOR}.${NEW_BUILD}"
echo "📦 Deploying version: $VERSION"

# Deploy via rsync
echo ""
echo "📤 Deploying files to NAS..."
rsync -avz --chmod=D755,F644 --exclude='.git' --exclude='node_modules' --exclude='*.md' \
  -e "sshpass -p '$NAS_PASSWORD' ssh -p 22 -o StrictHostKeyChecking=no" \
  "$PROJECT_ROOT/web/" \
  "${NAS_USER}@${NAS_HOST}:${NAS_PATH}/"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Deployment complete!"
    echo "🌐 Version $VERSION is live at https://cdash.upstatetoday.com/"
    echo ""
    echo "Build info:"
    echo "  - Version: $VERSION"
    echo "  - Build: $NEW_BUILD"
    echo "  - Deployed: $(date '+%Y-%m-%d %H:%M:%S')"
else
    echo ""
    echo "❌ Deployment failed!"
    # Rollback build number
    echo "$CURRENT_BUILD" > .build_number
    exit 1
fi
