#!/bin/bash
# Build and Push Script for NWDownloads Circulation Dashboard
# Builds the Docker image and pushes to Docker Hub

set -e  # Exit on error

# Configuration
DOCKER_USERNAME="binarybcc"
IMAGE_NAME="nwdownloads-circ"
FULL_IMAGE_NAME="${DOCKER_USERNAME}/${IMAGE_NAME}"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  NWDownloads Circulation Dashboard Build${NC}"
echo -e "${BLUE}════════════════════════════════════════════════${NC}"
echo ""

# Get version tag (optional)
if [ -z "$1" ]; then
    VERSION_TAG="latest"
    echo -e "${YELLOW}No version specified, using 'latest' tag${NC}"
else
    VERSION_TAG="$1"
    echo -e "${GREEN}Building version: ${VERSION_TAG}${NC}"
fi
echo ""

# Step 1: Build the image
echo -e "${BLUE}Step 1: Building Docker image...${NC}"
docker build -t ${FULL_IMAGE_NAME}:${VERSION_TAG} .

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Build successful${NC}"
else
    echo -e "${RED}✗ Build failed${NC}"
    exit 1
fi
echo ""

# Step 2: Tag as latest (if not already)
if [ "$VERSION_TAG" != "latest" ]; then
    echo -e "${BLUE}Step 2: Tagging as 'latest'...${NC}"
    docker tag ${FULL_IMAGE_NAME}:${VERSION_TAG} ${FULL_IMAGE_NAME}:latest
    echo -e "${GREEN}✓ Tagged as latest${NC}"
    echo ""
fi

# Step 3: Push version tag
echo -e "${BLUE}Step 3: Pushing ${VERSION_TAG} to Docker Hub...${NC}"
docker push ${FULL_IMAGE_NAME}:${VERSION_TAG}

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Push successful: ${VERSION_TAG}${NC}"
else
    echo -e "${RED}✗ Push failed${NC}"
    exit 1
fi
echo ""

# Step 4: Push latest tag (if not already pushed)
if [ "$VERSION_TAG" != "latest" ]; then
    echo -e "${BLUE}Step 4: Pushing 'latest' to Docker Hub...${NC}"
    docker push ${FULL_IMAGE_NAME}:latest

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Push successful: latest${NC}"
    else
        echo -e "${RED}✗ Push failed${NC}"
        exit 1
    fi
    echo ""
fi

# Summary
echo -e "${GREEN}════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Build and Push Complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════${NC}"
echo ""
echo -e "Images pushed:"
echo -e "  • ${FULL_IMAGE_NAME}:${VERSION_TAG}"
if [ "$VERSION_TAG" != "latest" ]; then
    echo -e "  • ${FULL_IMAGE_NAME}:latest"
fi
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo -e "  1. Deploy to Production:"
echo -e "     ${YELLOW}sshpass -p 'Mojave48ice' ssh it@192.168.1.254${NC}"
echo -e "     ${YELLOW}cd /volume1/docker/nwdownloads${NC}"
echo -e "     ${YELLOW}sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull${NC}"
echo -e "     ${YELLOW}sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d${NC}"
echo ""
echo -e "  2. Verify deployment:"
echo -e "     ${YELLOW}http://192.168.1.254:8081/${NC}"
echo ""
