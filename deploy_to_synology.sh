#!/bin/bash
# Deploy Circulation Dashboard to Synology via Docker

set -e  # Exit on any error

SYNOLOGY_HOST="it@192.168.1.254"
SYNOLOGY_PATH="/volume1/docker/circulation"
PASSWORD="Mojave48ice"

echo "=========================================="
echo "Deploying Circulation Dashboard to Docker"
echo "=========================================="
echo ""

# Step 1: Create directory on Synology
echo "Step 1: Creating directory on Synology..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no $SYNOLOGY_HOST \
  "mkdir -p $SYNOLOGY_PATH/{web,db_init,hotfolder}"
echo "✓ Directory created"
echo ""

# Step 2: Copy project files
echo "Step 2: Copying project files..."
echo "  - Dockerfile"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no Dockerfile $SYNOLOGY_HOST:$SYNOLOGY_PATH/
echo "  - docker-compose.yml"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no docker-compose.yml $SYNOLOGY_HOST:$SYNOLOGY_PATH/
echo "  - .env"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no .env $SYNOLOGY_HOST:$SYNOLOGY_PATH/
echo "  - .dockerignore"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no .dockerignore $SYNOLOGY_HOST:$SYNOLOGY_PATH/
echo "  - Web files"
sshpass -p "$PASSWORD" scp -r -o StrictHostKeyChecking=no web/* $SYNOLOGY_HOST:$SYNOLOGY_PATH/web/
echo "  - Database initialization"
if [ -f "db_init/01_initial_data.sql" ]; then
    sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no db_init/01_initial_data.sql $SYNOLOGY_HOST:$SYNOLOGY_PATH/db_init/
    echo "    ✓ Database dump included"
else
    echo "    ⚠ No database dump found (will start with empty database)"
fi
echo "✓ Files copied"
echo ""

# Step 3: Build and start containers
echo "Step 3: Building and starting Docker containers..."
echo "  (This may take 5-10 minutes on first build)"
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no $SYNOLOGY_HOST << 'ENDSSH'
cd /volume1/docker/circulation

# Stop existing containers if any
docker-compose down 2>/dev/null || true

# Build images
echo "  - Building web image..."
docker-compose build web

# Start services
echo "  - Starting services..."
docker-compose up -d

# Wait for services to be healthy
echo "  - Waiting for services to be healthy..."
sleep 10

# Check status
docker-compose ps

echo ""
echo "✓ Containers started"
ENDSSH

echo ""
echo "=========================================="
echo "Deployment Complete!"
echo "=========================================="
echo ""
echo "Dashboard URL: http://192.168.1.254:8080/circulation/"
echo ""
echo "To view logs:"
echo "  ssh it@192.168.1.254"
echo "  cd $SYNOLOGY_PATH"
echo "  docker-compose logs -f"
echo ""
echo "To restart services:"
echo "  docker-compose restart"
echo ""
