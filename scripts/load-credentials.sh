#!/bin/bash
# Credential Loading Helper with Error Handling
# Usage: source scripts/load-credentials.sh

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env.credentials exists
if [ ! -f .env.credentials ]; then
    echo -e "${RED}❌ ERROR: .env.credentials file not found!${NC}"
    echo ""
    echo -e "${YELLOW}First-time setup required:${NC}"
    echo "1. Copy the example file:"
    echo "   ${GREEN}cp .env.credentials.example .env.credentials${NC}"
    echo ""
    echo "2. Edit with your actual credentials:"
    echo "   ${GREEN}nano .env.credentials${NC}"
    echo ""
    echo "3. Replace all 'your_*_here' values with real credentials"
    echo ""
    echo "4. Test by running:"
    echo "   ${GREEN}source scripts/load-credentials.sh${NC}"
    echo ""
    return 1 2>/dev/null || exit 1
fi

# Source the credential file
source .env.credentials

# Validate required variables
REQUIRED_VARS=(
    "SSH_HOST"
    "SSH_USER"
    "SSH_PASSWORD"
    "PROD_DB_PASSWORD"
    "DEV_DB_PASSWORD"
)

MISSING_VARS=()

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        MISSING_VARS+=("$var")
    fi
done

# Report status
if [ ${#MISSING_VARS[@]} -eq 0 ]; then
    echo -e "${GREEN}✅ Credentials loaded successfully!${NC}"
    echo "SSH Host: $SSH_HOST"
    echo "SSH User: $SSH_USER"
    echo "Production DB: $PROD_DB_DATABASE"
    echo "Development DB: $DEV_DB_DATABASE"
    return 0 2>/dev/null || exit 0
else
    echo -e "${RED}❌ ERROR: Missing required variables in .env.credentials:${NC}"
    for var in "${MISSING_VARS[@]}"; do
        echo "  - $var"
    done
    echo ""
    echo -e "${YELLOW}Fix by editing .env.credentials and setting all variables${NC}"
    return 1 2>/dev/null || exit 1
fi
