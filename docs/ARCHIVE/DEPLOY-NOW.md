# Quick Deploy to Production - Manual Steps

The deployment bundle is already on your NAS at:
`/volume1/docker/deployment-bundle-v2.1.1/`

## Run These Commands on Production NAS

### Option 1: Use the Automated Script (Recommended)

```bash
ssh it@192.168.1.254
cd /volume1/docker/deployment-bundle-v2.1.1
sudo bash deploy.sh
```

### Option 2: Manual Step-by-Step (If Script Fails)

```bash
# SSH to NAS
ssh it@192.168.1.254

# Step 1: Pull the Docker Hub image
sudo docker pull binarybcc/nwdownloads-circ:latest

# Step 2: Stop existing containers
cd /volume1/docker/nwdownloads
sudo docker-compose down

# Step 3: Update docker-compose.yml
sudo cp /volume1/docker/deployment-bundle-v2.1.1/docker-compose.yml /volume1/docker/nwdownloads/

# Step 4: Create required directories
sudo mkdir -p /volume1/docker/nwdownloads/web
sudo mkdir -p /volume1/docker/nwdownloads/hotfolder
sudo mkdir -p /volume1/docker/nwdownloads/db_init

# Step 5: Copy SQL files
sudo cp /volume1/docker/deployment-bundle-v2.1.1/sql/*.sql /volume1/docker/nwdownloads/db_init/

# Step 6: Start containers
cd /volume1/docker/nwdownloads
sudo docker-compose up -d

# Step 7: Check status
sudo docker-compose ps
sudo docker-compose logs -f web
```

## What Changed

### docker-compose.yml now uses Docker Hub:
```yaml
web:
  image: binarybcc/nwdownloads-circ:latest  # ← Pulls from Docker Hub!
```

### Before (old way):
- Had to build image on production
- Architecture issues
- Manual file copying

### After (new way):
- Pull pre-built image from Docker Hub
- Multi-platform support (ARM64 + AMD64)
- True "drag and drop" deployment

## After Deployment

1. **Copy web files to `/volume1/docker/nwdownloads/web/`:**
   ```bash
   # You'll need upload.html, api.php, index.html, etc.
   ```

2. **Access the dashboard:**
   - http://192.168.1.254:8081
   - http://192.168.1.254:8081/upload.html

3. **Upload CSV data via upload.html**

## Troubleshooting

### Check container status:
```bash
sudo docker-compose ps
```

### View logs:
```bash
sudo docker-compose logs -f
```

### Restart if needed:
```bash
sudo docker-compose restart
```

### Update to newest version anytime:
```bash
sudo docker pull binarybcc/nwdownloads-circ:latest
sudo docker-compose up -d
```

## Why This Is Better

✅ **No more architecture problems** - Multi-platform image works everywhere
✅ **No more building on production** - Just pull and run
✅ **Faster deployments** - Download is faster than building
✅ **Consistent everywhere** - Same image on Mac and NAS
✅ **Easy updates** - Just pull latest and restart

## The Docker Hub Advantage

**Development (Mac):**
```bash
# Build once, push to Docker Hub
docker buildx build --platform linux/amd64,linux/arm64 --push .
```

**Production (NAS):**
```bash
# Pull and run - that's it!
docker pull binarybcc/nwdownloads-circ:latest
docker-compose up -d
```

This is how Docker is meant to work!
