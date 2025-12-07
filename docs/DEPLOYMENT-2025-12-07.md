# Production Deployment Guide - December 7, 2025

## 🎯 What's Being Deployed

**Git Commit:** `ad8eca6` - Infrastructure improvements and critical bug fixes
**Docker Image:** `binarybcc/nwdownloads-circ:latest`
**Platforms:** Multi-architecture (AMD64 + ARM64)

### Key Changes in This Deployment

1. **Multi-Platform Docker Builds** - Native AMD64 images (no more emulation overhead)
2. **setTimeout Race Condition Fix** - Event-based coordination (Gemini code review finding)
3. **Environment Variable Migration** - Credentials externalized to .env
4. **Week-Based Upload Precedence** - Smart day-of-week logic for CSV uploads

---

## 📋 Pre-Deployment Checklist

- ✅ Code committed to GitHub: `ad8eca6`
- ✅ Multi-platform image built and pushed to Docker Hub
- ✅ Both architectures verified (AMD64 + ARM64)
- ✅ Local development tested successfully
- ⏳ Production deployment pending

---

## 🚀 Deployment Steps

### Step 1: Connect to Production NAS

```bash
ssh it@192.168.1.254
# Password: Mojave48ice
```

### Step 2: Navigate to Project Directory

```bash
cd /volume1/docker/nwdownloads
```

### Step 3: Pull Latest Docker Image

```bash
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull
```

**Expected Output:**
```
Pulling web ... done
```

### Step 4: Deploy Updated Containers

```bash
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d
```

**Expected Output:**
```
Recreating circulation_web ... done
```

### Step 5: Verify Deployment

**Check container status:**
```bash
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

**Expected:**
- `circulation_db` - Status: `Up` and `(healthy)`
- `circulation_web` - Status: `Up`

**View logs for errors:**
```bash
sudo /usr/local/bin/docker logs circulation_web --tail 50
```

**Look for:**
- ✅ Apache started successfully
- ✅ No PHP errors
- ❌ Any 500 errors or warnings

### Step 6: Functional Testing

**1. Access Dashboard:**
```
http://192.168.1.254:8081/
```

**Expected:** Login page loads correctly

**2. Test Login:**
- Use Newzware credentials
- Should redirect to dashboard after successful auth

**3. Test Dashboard Rendering:**
- Verify metrics display correctly
- Check that business unit cards load
- Confirm charts render without errors

**4. Test Upload Functionality:**
```
http://192.168.1.254:8081/upload.html
```
- Upload a recent AllSubscriberReport CSV
- Verify import succeeds
- Check dashboard updates with new data

---

## 🧪 Testing the Race Condition Fix

The setTimeout race condition fix should eliminate random UI initialization failures.

**What to Test:**
1. Refresh dashboard multiple times (10+)
2. Test on slower network (if possible)
3. Verify keyboard shortcuts work consistently
4. Check that export menu initializes properly

**Before (Old Code):** Random failures when loading took >500ms
**After (New Code):** UI enhancements initialize reliably when dashboard renders

---

## 🔍 Monitoring Points

### Week-Based Upload System

**New Behavior:**
- Saturday data replaces Friday data ✅
- Friday data replaces Thursday data ✅
- Tuesday data REJECTED if Friday exists ⚠️

**How to Test:**
1. Upload a Tuesday AllSubs report (Day 2 of week)
2. Upload a Friday AllSubs report (Day 5 of week) - Should succeed and replace Tuesday
3. Try uploading Tuesday report again - Should be rejected with message:
   ```
   "Later-in-week data already exists. Cannot overwrite Friday data with Tuesday data."
   ```

### Performance Improvements

**Multi-Platform Images:**
- **Before:** NAS emulated ARM64 images via QEMU (~10-30% overhead)
- **After:** Native AMD64 execution (full performance)

**To Measure:**
- Time dashboard load: Should be faster
- Check container resource usage: `sudo /usr/local/bin/docker stats circulation_web`

---

## 🐛 Troubleshooting

### Issue: Container Won't Start

**Symptom:** `docker compose ps` shows `Exited` status

**Solution:**
```bash
# Check logs
sudo /usr/local/bin/docker logs circulation_web

# Common issues:
# 1. Database not ready - wait 30 seconds and retry
# 2. Port 8081 in use - check with: netstat -an | grep 8081
# 3. File permissions - check /volume1/docker/nwdownloads ownership
```

### Issue: Dashboard Shows "Connection Failed"

**Symptom:** API calls return errors

**Solution:**
```bash
# Test database connectivity from web container
sudo /usr/local/bin/docker exec circulation_web php -r "
  \$pdo = new PDO('mysql:host=database;dbname=circulation_dashboard', 'circ_dash', 'Barnaby358@Jones!');
  echo 'DB Connected!';
"
```

### Issue: Old Code Still Running

**Symptom:** Race condition still occurs or changes not visible

**Solution:**
```bash
# Verify image digest matches Docker Hub
sudo /usr/local/bin/docker inspect circulation_web | grep Image

# Force recreate containers
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate
```

### Issue: CSV Upload Fails

**Symptom:** "Week-based precedence error"

**Solution:**
- This is expected behavior!
- Earlier-in-week data cannot overwrite later-in-week data
- Use Saturday reports for final weekly snapshot
- Document which day-of-week reports are uploaded

---

## 📊 Success Criteria

- ✅ All containers running and healthy
- ✅ Dashboard loads without errors
- ✅ Login authentication works
- ✅ Metrics display correctly
- ✅ CSV upload processes successfully
- ✅ Charts render without console errors
- ✅ Keyboard shortcuts work (Ctrl+K for export menu)
- ✅ No setTimeout race condition failures observed

---

## 🔙 Rollback Plan

If issues occur, rollback to previous version:

```bash
# Check previous image
sudo /usr/local/bin/docker images binarybcc/nwdownloads-circ

# Rollback to previous digest
sudo /usr/local/bin/docker pull binarybcc/nwdownloads-circ@sha256:<previous-digest>

# Restart with old version
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate
```

**Note:** Get previous digest from Docker Hub history if needed.

---

## 📝 Post-Deployment Tasks

### 1. Monitor for 24 Hours

- Check dashboard daily for errors
- Review container logs periodically
- Watch for CSV upload issues

### 2. Document Any Issues

If problems occur, document:
- What action triggered the issue
- Error messages from logs
- Steps to reproduce
- Screenshots if UI-related

### 3. Update Deployment Log

Record deployment outcome in project notes:
- Date/time deployed
- Any issues encountered
- Resolution steps taken
- Performance observations

---

## 🔗 Quick Reference Links

**Production Dashboard:** http://192.168.1.254:8081/
**Production Upload:** http://192.168.1.254:8081/upload.html
**Docker Hub Repository:** https://hub.docker.com/r/binarybcc/nwdownloads-circ
**GitHub Repository:** https://github.com/binarybcc/nwdownloads
**Latest Commit:** https://github.com/binarybcc/nwdownloads/commit/ad8eca6

---

## ⚠️ Important Notes

### Environment Variables (Production)

**Production uses hardcoded values in `docker-compose.prod.yml`**, NOT the .env file.

The .env file is for **development only**.

If you need to update production credentials, modify `docker-compose.prod.yml` directly on the NAS.

### Multi-Architecture Benefits

**First deployment with native AMD64 images!**

Previous deployments used ARM64 images that were emulated on the NAS. This deployment includes native AMD64 images specifically built for the Synology architecture.

**Expected benefits:**
- Faster container startup
- Lower CPU usage
- More reliable operation
- Better performance under load

---

## 📞 Support Information

**If deployment fails:**
1. Document the error
2. Check troubleshooting section above
3. Review container logs thoroughly
4. Consider rollback if critical

**Emergency contacts:**
- System: Synology NAS at 192.168.1.254
- Database: MariaDB 10.11 (circulation_dashboard)
- Web: PHP 8.2 + Apache

---

**Deployment Date:** December 7, 2025
**Prepared By:** Claude Sonnet 4.5
**Deployment Window:** Tomorrow (your convenience)

✨ **Good luck with the deployment!**
