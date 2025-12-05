# Deployment Strategy - Hybrid Approach

## 🎯 Decision: File Deployment + Occasional Container Deployment

**Primary Method**: File-level deployment via SSH
**Fallback Method**: Full container deployment via Docker Hub

---

## 📋 When to Use Each Method

### 🚀 File Deployment (Daily/Weekly)

**Use for:**
- ✅ CSS/JavaScript changes
- ✅ HTML updates
- ✅ PHP bug fixes
- ✅ Content changes
- ✅ Image/asset updates
- ✅ Configuration tweaks

**Trigger**: Any code change that doesn't affect infrastructure

**Process**:
```bash
# Use the deployment skill
/deploy-nwdownloads
```

**Time**: ~30 seconds
**Risk**: Low
**Rollback**: Restore files from backup

---

### 🐳 Container Deployment (Monthly/Quarterly or "When Things Get Sticky")

**Use for:**
- ✅ PHP version upgrades
- ✅ Apache configuration changes
- ✅ New PHP extensions
- ✅ Dockerfile modifications
- ✅ Environment drift fixes
- ✅ Major infrastructure changes
- ✅ **When production behaves differently than development**

**Trigger**: "Things getting sticky" = Environment inconsistencies

**Process**:
```bash
# 1. Build image locally
docker build -t yourusername/nwdownloads:v1.1.0 .

# 2. Test locally
docker run -p 8081:80 yourusername/nwdownloads:v1.1.0

# 3. Push to Docker Hub
docker push yourusername/nwdownloads:v1.1.0

# 4. Deploy on NAS
ssh it@192.168.1.254
cd /volume1/docker/nwdownloads
# Update image tag in docker-compose.yml
sudo docker compose pull
sudo docker compose up -d
```

**Time**: ~10 minutes
**Risk**: Low (atomic deployment)
**Rollback**: Change image tag back, restart

---

## 🔍 "Sticky Situations" - Triggers for Container Deployment

### Signs You Need Container Deployment:

1. **"It works on my machine"**
   - Development works, production doesn't
   - Same code, different behavior

2. **Missing dependencies**
   - PHP extension needed in production
   - Library version mismatch

3. **Configuration drift**
   - Production Apache config differs from dev
   - PHP.ini settings inconsistent

4. **After major OS updates**
   - Synology DSM upgrade
   - Docker version change

5. **Performance degradation**
   - Containers need fresh start
   - Cache/state issues

6. **Quarterly cleanup**
   - Reset production to known-good state
   - Clear accumulated cruft

---

## 📊 Decision Matrix

| Scenario | Method | Reason |
|----------|--------|--------|
| Fixed typo in HTML | File | Fast, simple change |
| Updated CSS styles | File | No infrastructure impact |
| Added new JS feature | File | Code change only |
| Bug fix in PHP | File | Logic change, not environment |
| Upgraded PHP 8.2 → 8.3 | Container | Infrastructure change |
| Added redis extension | Container | New dependency |
| Production acting weird | Container | Reset to clean state |
| Weekly CSS updates | File | Frequent small changes |
| Quarterly maintenance | Container | Full refresh |

---

## 🛠️ File Deployment Process

**Automated via skill:**

1. Build optimized CSS: `npm run build:css`
2. Update cache-busting: `./scripts/update-version.sh`
3. Deploy files via SSH cat
4. Restart containers manually

**Files deployed:**
- index.html
- upload.php
- api.php
- output.css
- 12 JavaScript files
- 9 image assets

**Total**: 25 files

---

## 🐳 Container Deployment Process (When Needed)

### Prerequisites:
- Docker Hub account (✅ configured)
- Credentials: `.env.dockerhub.credentials`

### Step 1: Prepare Image

**Modify Dockerfile to include web files:**
```dockerfile
FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
COPY ./web /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
```

**Update docker-compose.yml:**
```yaml
services:
  web:
    image: yourusername/nwdownloads:latest  # Use Docker Hub image
    # Remove volume mount for web files
    ports:
      - "8081:80"
```

### Step 2: Build & Push

```bash
# Build with version tag
docker build -t yourusername/nwdownloads:v1.0.0 .
docker tag yourusername/nwdownloads:v1.0.0 yourusername/nwdownloads:latest

# Push to Docker Hub
docker push yourusername/nwdownloads:v1.0.0
docker push yourusername/nwdownloads:latest
```

### Step 3: Deploy to Production

```bash
# SSH to NAS
ssh it@192.168.1.254

# Navigate to project
cd /volume1/docker/nwdownloads

# Pull latest image
sudo docker compose pull

# Recreate containers
sudo docker compose up -d --force-recreate

# Verify
sudo docker compose ps
```

### Step 4: Verify Deployment

```bash
# Check logs
sudo docker compose logs -f web

# Test application
curl http://192.168.1.254:8081/
```

---

## 🔄 Rollback Procedures

### File Deployment Rollback:
```bash
# Restore from backup
ssh it@192.168.1.254
cd /volume1/docker/nwdownloads
cp web/index.html.backup web/index.html
# ... restore other files
sudo docker compose restart
```

### Container Deployment Rollback:
```bash
# Revert to previous image version
ssh it@192.168.1.254
cd /volume1/docker/nwdownloads

# Update docker-compose.yml to previous version
# Change: yourusername/nwdownloads:v1.1.0
# To:     yourusername/nwdownloads:v1.0.0

sudo docker compose up -d
```

---

## 📈 Version Tracking

### File Deployment Versions:
- Tracked via cache-busting dates: `?v=20251205`
- Git commits track code changes
- Deployment skill logs show what was deployed

### Container Deployment Versions:
- Semantic versioning: `v1.0.0`, `v1.1.0`, `v2.0.0`
- Tags in Docker Hub
- Git tags align with image versions

**Recommended tagging:**
```bash
# Major.Minor.Patch
v1.0.0  # Initial production release
v1.0.1  # Bug fix
v1.1.0  # New feature
v2.0.0  # Breaking change
```

---

## 🎓 Best Practices

### File Deployment:
1. ✅ Always use deployment skill (ensures completeness)
2. ✅ Test in development first
3. ✅ Hard refresh browser after deployment
4. ✅ Verify all features work
5. ✅ Check browser console for errors

### Container Deployment:
1. ✅ Test image locally before pushing
2. ✅ Tag with version numbers
3. ✅ Keep `latest` tag updated
4. ✅ Document what changed in each version
5. ✅ Keep old images in Docker Hub for rollback

---

## 📝 Deployment Checklist

### Before ANY Deployment:
- [ ] Changes tested in development
- [ ] No errors in browser console
- [ ] Database queries work correctly
- [ ] No breaking changes to APIs

### File Deployment Specific:
- [ ] `npm run build:css` completed
- [ ] Cache-busting version updated
- [ ] All 25 files will be deployed

### Container Deployment Specific:
- [ ] Dockerfile tested locally
- [ ] Image builds successfully
- [ ] Version tag chosen
- [ ] Docker Hub credentials valid

### After Deployment:
- [ ] Containers restarted
- [ ] Health check passed
- [ ] User acceptance testing
- [ ] No errors in logs

---

## 🚨 Emergency Procedures

### Production Down After File Deployment:
1. Check container logs: `sudo docker compose logs`
2. Restart containers: `sudo docker compose restart`
3. If still broken: Restore files from backup
4. If still broken: Switch to container deployment

### Production Down After Container Deployment:
1. Check container logs
2. Rollback to previous image version
3. If still broken: Use older image version
4. If still broken: SSH in and fix manually

---

## 📖 Related Documentation

- **File Deployment Skill**: `~/.claude/skills/deploy-nwdownloads.md`
- **Cache-Busting**: `/docs/CACHE-BUSTING.md`
- **Tailwind Setup**: `/docs/TAILWIND-SETUP.md`
- **Docker Setup**: `/docs/DOCKER_SETUP.md`

---

**Strategy Decided**: 2025-12-05
**Primary Method**: File deployment (fast iteration)
**Secondary Method**: Container deployment (when sticky)
**Flexibility**: Use what makes sense for the situation
