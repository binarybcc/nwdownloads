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

# Step 1: Build and push multi-platform image (AMD64 + ARM64)
echo -e "${BLUE}Step 1: Building multi-platform Docker image...${NC}"
echo -e "${YELLOW}Platforms: linux/amd64 (NAS), linux/arm64 (Mac)${NC}"

if [ "$VERSION_TAG" != "latest" ]; then
    # Build with version tag AND latest tag
    docker buildx build \
        --platform linux/amd64,linux/arm64 \
        --tag ${FULL_IMAGE_NAME}:${VERSION_TAG} \
        --tag ${FULL_IMAGE_NAME}:latest \
        --push \
        .
else
    # Build with just latest tag
    docker buildx build \
        --platform linux/amd64,linux/arm64 \
        --tag ${FULL_IMAGE_NAME}:latest \
        --push \
        .
fi

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Multi-platform build and push successful${NC}"
    echo -e "${GREEN}  • AMD64 image for Synology NAS${NC}"
    echo -e "${GREEN}  • ARM64 image for Apple Silicon Mac${NC}"
else
    echo -e "${RED}✗ Build failed${NC}"
    exit 1
fi
echo ""

# Summary
echo -e "${GREEN}════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Multi-Platform Build Complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════${NC}"
echo ""
echo -e "Images pushed to Docker Hub:"
echo -e "  • ${FULL_IMAGE_NAME}:${VERSION_TAG}"
if [ "$VERSION_TAG" != "latest" ]; then
    echo -e "  • ${FULL_IMAGE_NAME}:latest"
fi
echo ""
echo -e "Platforms:"
echo -e "  • linux/amd64 (Synology NAS)"
echo -e "  • linux/arm64 (Apple Silicon Mac)"
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
