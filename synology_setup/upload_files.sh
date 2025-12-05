#!/bin/bash

###############################################################################
# Upload Web Files to Synology
# Transfers web dashboard files from local Mac to Synology NAS
###############################################################################

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Load environment variables
if [ ! -f "$(dirname "$0")/.env.ssh" ]; then
    echo -e "${RED}❌ Error: .env.ssh file not found${NC}"
    exit 1
fi

export $(grep -v '^#' "$(dirname "$0")/.env.ssh" | xargs)

echo -e "${BLUE}📤 Uploading files to Synology NAS...${NC}"
echo ""

# 1. Upload web files
echo -e "${GREEN}1. Uploading web files...${NC}"
scp -r -P "$SSH_PORT" "$LOCAL_WEB_DIR"/* "$SSH_USER@$SSH_HOST:$REMOTE_WEB_DIR/"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Web files uploaded successfully${NC}"
else
    echo -e "${RED}❌ Failed to upload web files${NC}"
    exit 1
fi

echo ""

# 2. Upload Python import script
echo -e "${GREEN}2. Uploading Python import script...${NC}"
scp -P "$SSH_PORT" "$LOCAL_SCRIPTS_DIR/3_import_to_database.py" "$SSH_USER@$SSH_HOST:$REMOTE_SCRIPTS_DIR/"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Python script uploaded successfully${NC}"
else
    echo -e "${RED}❌ Failed to upload Python script${NC}"
    exit 1
fi

echo ""

# 3. Upload .env file (if exists)
if [ -f "$LOCAL_WEB_DIR/../.env" ]; then
    echo -e "${GREEN}3. Uploading .env configuration...${NC}"
    scp -P "$SSH_PORT" "$LOCAL_WEB_DIR/../.env" "$SSH_USER@$SSH_HOST:/volume1/circulation/"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ .env file uploaded successfully${NC}"
    else
        echo -e "${YELLOW}⚠️  Warning: Failed to upload .env file${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  No .env file found (this is okay if not yet created)${NC}"
fi

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Upload complete!${NC}"
echo ""
echo "Files uploaded to:"
echo "  - Web files: $REMOTE_WEB_DIR/"
echo "  - Scripts: $REMOTE_SCRIPTS_DIR/"
echo ""
echo "Next steps:"
echo "  1. SSH into your NAS: ./connect.sh"
echo "  2. Test the web interface at: http://$SSH_HOST/circulation"
echo "  3. Run the import script if ready"
echo ""
