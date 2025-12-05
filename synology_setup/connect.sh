#!/bin/bash

###############################################################################
# Synology SSH Connection Helper
# Reads credentials from .env.ssh and connects to your NAS
###############################################################################

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if .env.ssh exists
if [ ! -f "$(dirname "$0")/.env.ssh" ]; then
    echo -e "${RED}❌ Error: .env.ssh file not found${NC}"
    echo ""
    echo "Please create .env.ssh from .env.ssh.example:"
    echo "  cp .env.ssh.example .env.ssh"
    echo "  nano .env.ssh"
    echo ""
    exit 1
fi

# Load environment variables
export $(grep -v '^#' "$(dirname "$0")/.env.ssh" | xargs)

echo -e "${GREEN}🔌 Connecting to Synology NAS...${NC}"
echo ""
echo "Host: $SSH_HOST"
echo "Port: $SSH_PORT"
echo "User: $SSH_USER"
echo ""

# Check if using SSH key or password
if [ -n "$SSH_KEY_PATH" ] && [ -f "$SSH_KEY_PATH" ]; then
    echo -e "${GREEN}Using SSH key: $SSH_KEY_PATH${NC}"
    ssh -i "$SSH_KEY_PATH" -p "$SSH_PORT" "$SSH_USER@$SSH_HOST"
else
    echo -e "${YELLOW}Using password authentication${NC}"
    echo "Tip: For better security, consider using SSH keys instead"
    echo ""
    ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST"
fi

echo ""
echo -e "${GREEN}✅ Disconnected from NAS${NC}"
