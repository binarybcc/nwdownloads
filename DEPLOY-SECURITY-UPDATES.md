# 🚀 Deployment Guide: Security Updates to Production

**Date Prepared:** 2025-12-06
**Deploy Date:** Monday (from office computer)
**What's Being Deployed:** Comprehensive security hardening
**Prerequisites:** HTTPS already configured on production ✅

---

## 📋 What Was Done (Background)

On 2025-12-06, we implemented **5 major security improvements** to the circulation dashboard:

1. **Brute Force Protection** - Blocks after 5 failed login attempts (15-min lockout)
2. **CSRF Protection** - Token-based form validation
3. **Session Regeneration** - Prevents session fixation attacks
4. **Session Security Flags** - HttpOnly, SameSite=Strict, Secure (conditional)
5. **Security Logging** - Failed attempts and CSRF violations logged

**Files Changed:**
- `web/brute_force_protection.php` (NEW)
- `web/login.php` (security integration)
- `web/config.php` (session security flags)
- `docs/SECURITY-IMPROVEMENTS-TODO.md` (marked complete)

**Git Commit:** `73d96aa` - "Implement comprehensive security hardening"

**Status:**
- ✅ Code committed to GitHub
- ✅ HTTPS configured on production (cdash.upstatetoday.com with HSTS)
- ⏸️ **Waiting:** Docker image build and deployment

---

## 🎯 Deployment Steps (Monday)

### **Step 1: Navigate to Project**

```bash
cd $PROJECT_ROOT
# Should be: /Users/johncorbin/Desktop/projs/nwdownloads/
# (direnv will auto-load environment)
```

### **Step 2: Verify You're on Latest Code**

```bash
# Pull latest changes from GitHub
git pull origin master

# Verify security files exist
ls -lh web/brute_force_protection.php
# Should show: 3.9K file created Dec 6

# Check commit history
git log --oneline -3
# Should see: 73d96aa Implement comprehensive security hardening
```

### **Step 3: Build and Push Docker Image**

```bash
# Build and push to Docker Hub
./build-and-push.sh

# Optional: Tag with version number
# ./build-and-push.sh 1.1.0
```

**What happens:**
- Builds Docker image with security updates
- Tags as `latest` (and optional version)
- Pushes to `binarybcc/nwdownloads-circ` on Docker Hub

**Expected output:**
```
Building web image...
✓ Image built successfully
✓ Tagged as binarybcc/nwdownloads-circ:latest
✓ Pushing to Docker Hub...
✓ Push complete!
```

⏱️ **Takes:** 2-3 minutes (depending on internet speed)

---

### **Step 4: Deploy to Production (Synology NAS)**

**SSH into production:**
```bash
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no -p 22 it@192.168.1.254
```

**Once connected to NAS:**
```bash
# Navigate to project directory
cd /volume1/docker/nwdownloads

# Pull latest image from Docker Hub
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull

# Stop current containers
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml down

# Start with new image
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# Verify containers are running
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
```

**Expected output from `ps`:**
```
NAME                IMAGE                                    STATUS
circulation_web     binarybcc/nwdownloads-circ:latest       Up X seconds (healthy)
circulation_db      mariadb:10.11                           Up X seconds (healthy)
```

**Exit SSH:**
```bash
exit
```

⏱️ **Takes:** 1-2 minutes

---

## ✅ Verification (Critical!)

### **Test 1: HTTPS and Login Work**

**Visit:** https://cdash.upstatetoday.com

**Expected:**
- ✅ 🔒 Padlock icon in address bar
- ✅ Page loads without errors
- ✅ Login page displays

### **Test 2: CSRF Token Present**

1. Right-click page → View Page Source
2. Search for: `csrf_token`
3. **Should find:**
   ```html
   <input type="hidden" name="csrf_token" value="...64-char hex string...">
   ```

### **Test 3: Secure Session Cookies**

1. Open browser DevTools (F12)
2. Go to **Application** tab → **Cookies** → `https://cdash.upstatetoday.com`
3. Find `PHPSESSID` cookie
4. **Verify flags:**
   - ✅ `Secure` = true
   - ✅ `HttpOnly` = true
   - ✅ `SameSite` = Strict

### **Test 4: Brute Force Protection**

**Test the lockout mechanism:**

1. Go to login page
2. Enter **wrong password** 5 times
3. On 6th attempt, should see:
   ```
   Too many failed attempts. Please try again in 15 minutes.
   ```

4. Wait 15 minutes OR clear session cookies to reset

5. Login with **correct credentials** - should work

### **Test 5: Session Regeneration**

1. Before login, note session cookie value (DevTools → Cookies → PHPSESSID)
2. Login successfully
3. Check session cookie value again
4. **Should be different** (session ID regenerated)

---

## 🎉 Success Criteria

**All 5 tests pass = Deployment successful!**

After successful deployment, you have:
- 🔒 HTTPS with Let's Encrypt certificate
- 🛡️ HSTS enabled (strict transport security)
- 🚫 Brute force protection (5 attempts / 15 min)
- 🔐 CSRF protection (token validation)
- 🔄 Session regeneration (anti-fixation)
- 🍪 Secure session cookies (all flags set)
- 📊 Security logging (failed attempts tracked)

**Risk Level:** 🟢 **LOW** - Production ready for public internet

---

## 🚨 Troubleshooting

### **Build Script Fails**

**Error:** `./build-and-push.sh: command not found`
```bash
# Make script executable
chmod +x build-and-push.sh
# Try again
./build-and-push.sh
```

**Error:** `Cannot connect to Docker daemon`
```bash
# Start Docker Desktop
open -a Docker
# Wait 30 seconds, try again
```

### **Docker Hub Push Fails**

**Error:** `denied: requested access to the resource is denied`
```bash
# Login to Docker Hub
docker login
# Username: binarybcc
# Password: [your Docker Hub password]
# Try push again
./build-and-push.sh
```

### **Production Pull Fails**

**Error:** `Cannot connect to NAS`
```bash
# Verify NAS is accessible
ping 192.168.1.254
# Check SSH password is correct
```

**Error:** `Image not found on Docker Hub`
```bash
# Verify image was pushed successfully
# Visit: https://hub.docker.com/r/binarybcc/nwdownloads-circ/tags
# Should see "latest" tag updated today
```

### **CSRF Token Not Present After Deployment**

```bash
# SSH back into NAS
ssh it@192.168.1.254

# Check container logs for errors
cd /volume1/docker/nwdownloads
sudo docker logs circulation_web --tail 50

# Look for PHP errors mentioning csrf or brute_force
# If errors found, container might need rebuild
```

### **Secure Cookie Flag Not Set**

**Verify HTTPS is actually being used:**
```bash
# In browser, check URL starts with https:// not http://
# If http://, check reverse proxy configuration
```

**Force clear cache:**
```bash
# Hard refresh: Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)
# Clear all cookies for site
# Try again
```

---

## 📝 Rollback Plan (If Needed)

If deployment causes issues:

**Quick Rollback:**
```bash
# SSH to NAS
ssh it@192.168.1.254
cd /volume1/docker/nwdownloads

# Pull previous version (if tagged)
sudo docker pull binarybcc/nwdownloads-circ:1.0.0
sudo docker tag binarybcc/nwdownloads-circ:1.0.0 binarybcc/nwdownloads-circ:latest

# Restart containers
sudo docker compose -f docker-compose.prod.yml down
sudo docker compose -f docker-compose.prod.yml up -d
```

**Or revert code:**
```bash
# On local machine
git log --oneline
# Find commit BEFORE security updates (ad2f7d3)
git checkout ad2f7d3
./build-and-push.sh
# Deploy old image to production
```

---

## 🔍 Post-Deployment Monitoring

**First 24 hours after deployment:**

1. **Check error logs regularly:**
   ```bash
   ssh it@192.168.1.254
   sudo docker logs circulation_web --tail 100 --follow
   # Watch for errors mentioning csrf, brute_force, session
   ```

2. **Monitor user feedback:**
   - Can users login successfully?
   - Any reports of "too many attempts" errors?
   - Any CSRF validation failures?

3. **Check Docker Hub for image:**
   - Visit: https://hub.docker.com/r/binarybcc/nwdownloads-circ/tags
   - Verify `latest` tag shows today's date

---

## 📚 Reference Documentation

**Related files to review:**
- `docs/SECURITY-IMPROVEMENTS-TODO.md` - What was implemented
- `PRODUCTION-CHECKLIST.md` - General production operations
- `web/brute_force_protection.php` - How lockout works
- `test_security.sh` - Local security tests

**Key commits:**
- `73d96aa` - Security hardening (this deployment)
- `ad2f7d3` - CSS fixes and subscriber_snapshots table
- `8398d9d` - Docker Hub workflow setup

---

## ⏰ Estimated Timeline

**Total deployment time: ~10-15 minutes**

- Step 1 (Navigate): 30 seconds
- Step 2 (Verify code): 1 minute
- Step 3 (Build & push): 2-3 minutes
- Step 4 (Deploy to NAS): 1-2 minutes
- Verification tests: 5 minutes

**Best time to deploy:** Monday morning, before users access dashboard

---

## 🎯 Quick Commands Summary

**Local machine:**
```bash
cd $PROJECT_ROOT
git pull origin master
./build-and-push.sh
```

**Production (via SSH):**
```bash
ssh it@192.168.1.254
cd /volume1/docker/nwdownloads
sudo docker compose -f docker-compose.prod.yml pull
sudo docker compose -f docker-compose.prod.yml down
sudo docker compose -f docker-compose.prod.yml up -d
sudo docker compose -f docker-compose.prod.yml ps
exit
```

**Verify:**
```
Visit: https://cdash.upstatetoday.com
Check: CSRF token, secure cookies, login works, brute force protection
```

---

**Questions for Monday-Claude:**
- "Deploy security updates" → Point to this file
- "What security was added?" → See "What Was Done" section
- "How do I deploy?" → Follow "Deployment Steps"
- "Something broke!" → See "Troubleshooting" section

**Last updated:** 2025-12-06
**Status:** Ready for Monday deployment 🚀
