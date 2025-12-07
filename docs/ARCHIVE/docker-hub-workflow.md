# Docker Hub Workflow

## Overview

This project uses a **hybrid approach** for Docker image management:

- **Development**: Volume mounts for live code editing (`docker-compose.yml`)
- **Production**: Pre-built images from Docker Hub (`docker-compose.prod.yml`)

**Docker Hub Repository**: `binarybcc/nwdownloads-circ`
**URL**: https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ/

---

## Architecture

### Development Setup
```yaml
# docker-compose.yml
volumes:
  - ./web:/var/www/html  # Live editing - changes reflect immediately
```

**Advantages:**
- Edit code and refresh browser instantly
- No image rebuilding required
- Fast iteration during development

**Disadvantages:**
- Requires local source code
- Not portable across machines

### Production Setup
```yaml
# docker-compose.prod.yml
image: binarybcc/nwdownloads-circ:latest
# NO volume mounts - files are baked into the image
```

**Advantages:**
- Fully containerized and portable
- Pull and run anywhere with Docker
- Version-controlled deployments
- Consistent across environments

**Disadvantages:**
- Requires rebuild + push for code changes
- Slightly slower deployment workflow

---

## Complete Workflow

### 1. Development (Local Machine)

**Start development environment:**
```bash
cd /Users/johncorbin/Desktop/projs/nwdownloads
docker compose up -d
```

**Access:** http://localhost:8081/

**Make changes:**
- Edit files in `./web/` directory
- Refresh browser to see changes
- No rebuild needed

**Stop environment:**
```bash
docker compose down
```

### 2. Build and Push to Docker Hub

**When ready to deploy to production:**

```bash
# Build and push with automatic version
./build-and-push.sh

# Or build with specific version tag
./build-and-push.sh v1.2.3
```

**What the script does:**
1. Builds Docker image with current code
2. Tags as `latest` and optionally version tag (e.g., `v1.2.3`)
3. Pushes both tags to Docker Hub
4. Shows deployment instructions

**Manual steps (if not using script):**
```bash
# Build image
docker build -t binarybcc/nwdownloads-circ:latest .

# Optional: Tag with version
docker tag binarybcc/nwdownloads-circ:latest binarybcc/nwdownloads-circ:v1.2.3

# Push to Docker Hub
docker push binarybcc/nwdownloads-circ:latest
docker push binarybcc/nwdownloads-circ:v1.2.3  # If versioned
```

**First time?** You'll need to login to Docker Hub:
```bash
docker login
# Username: binarybcc
# Password: [your Docker Hub password]
```

### 3. Deploy to Production (Synology NAS)

**Option A: Using the production compose file**

```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Navigate to project directory
cd /volume1/docker/nwdownloads

# Pull latest image from Docker Hub
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull

# Recreate containers with new image
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# Verify deployment
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

**Option B: Quick update (if already deployed)**

```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Navigate and pull latest
cd /volume1/docker/nwdownloads
sudo /usr/local/bin/docker pull binarybcc/nwdownloads-circ:latest

# Restart web container with new image
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate web
```

**Access production:** http://192.168.1.254:8081/

### 4. Verify Deployment

**Check container status:**
```bash
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

**View logs:**
```bash
# Web container logs
sudo /usr/local/bin/docker logs circulation_web

# Database logs
sudo /usr/local/bin/docker logs circulation_db
```

**Test application:**
- Open http://192.168.1.254:8081/
- Verify dashboard loads
- Check data displays correctly

---

## Version Management

### Tagging Strategy

**Latest tag:**
- Always points to most recent stable build
- Used for production by default
- Automatically updated with each push

**Version tags (optional):**
- Use semantic versioning: `v1.2.3`
- Allows rollback to specific versions
- Recommended for major releases

**Example versioning workflow:**

```bash
# Regular update (latest only)
./build-and-push.sh

# Major release (version + latest)
./build-and-push.sh v1.0.0

# Bug fix release
./build-and-push.sh v1.0.1

# Feature release
./build-and-push.sh v1.1.0
```

### Rollback to Previous Version

**If something goes wrong in production:**

```bash
# SSH into NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254
cd /volume1/docker/nwdownloads

# Pull specific version
sudo /usr/local/bin/docker pull binarybcc/nwdownloads-circ:v1.0.0

# Update docker-compose.prod.yml to use specific version
# Edit: image: binarybcc/nwdownloads-circ:v1.0.0

# Restart with specific version
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate
```

---

## File Transfer to Production

**For configuration files only** (code goes via Docker Hub):

```bash
# Copy docker-compose.prod.yml to NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 "cat > /volume1/docker/nwdownloads/docker-compose.prod.yml" < docker-compose.prod.yml

# Copy database init scripts
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 "cat > /volume1/docker/nwdownloads/db_init/init.sql" < db_init/init.sql
```

**Note:** Application code (`./web/`) should NEVER be copied directly to production. It's baked into the Docker image and deployed via Docker Hub.

---

## Troubleshooting

### Build fails

**Error: "Cannot connect to the Docker daemon"**
```bash
# Start Docker Desktop
# Or check Docker service status
docker info
```

**Error: "COPY failed: no such file or directory"**
```bash
# Ensure you're in project root directory
cd /Users/johncorbin/Desktop/projs/nwdownloads
./build-and-push.sh
```

### Push fails

**Error: "denied: requested access to the resource is denied"**
```bash
# Login to Docker Hub
docker login

# Verify you're logged in
docker info | grep Username
```

**Error: "repository does not exist"**
- Verify repository exists: https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ/
- Check repository name matches exactly

### Production deployment fails

**Error: "pull access denied"**
```bash
# Repository might be private - login on NAS
sudo /usr/local/bin/docker login
# Enter credentials: binarybcc / [password]
```

**Container won't start:**
```bash
# Check logs for errors
sudo /usr/local/bin/docker logs circulation_web

# Common issues:
# - Database not ready (wait for health check)
# - Port 8081 already in use
# - Environment variables missing
```

---

## Quick Reference

### Development Commands
```bash
# Start dev environment
docker compose up -d

# View logs
docker compose logs -f web

# Restart after config change
docker compose restart

# Stop environment
docker compose down
```

### Build & Deploy Commands
```bash
# Build and push to Docker Hub
./build-and-push.sh

# Deploy to production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254
cd /volume1/docker/nwdownloads
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d
```

### Verification Commands
```bash
# Check running containers (production)
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps

# Test web service
curl http://192.168.1.254:8081/

# Check image details
docker images binarybcc/nwdownloads-circ
```

---

## Best Practices

1. **Always test in development first**
   - Make changes locally
   - Test thoroughly with volume mounts
   - Only push to production when stable

2. **Use version tags for major releases**
   - Enables rollback if needed
   - Documents release history
   - Easier to track what's deployed

3. **Keep production compose file in sync**
   - Update on NAS when making infrastructure changes
   - Version control both compose files

4. **Monitor production logs**
   - Check logs after deployment
   - Watch for errors or warnings
   - Verify functionality

5. **Database backups before major updates**
   - Production database persists via volume
   - Backup before schema changes
   - Test restore procedure

---

## Summary

**Development workflow:**
1. Edit code locally with volume mounts
2. Test changes at http://localhost:8081/
3. Iterate quickly without rebuilding

**Production workflow:**
1. Test thoroughly in development
2. Build and push image: `./build-and-push.sh`
3. Pull and deploy on NAS via SSH
4. Verify at http://192.168.1.254:8081/

**Key advantage:** Clean separation between development (fast iteration) and production (stable, containerized deployment).
