# Troubleshooting Guide - NWDownloads Circulation Dashboard

> **Last Updated:** December 7, 2025  
> **Format:** Symptom → Diagnosis → Solution

This guide uses decision tree logic to help diagnose and fix common issues.

---

## 📋 Quick Diagnostic Commands

**Run these first when troubleshooting:**

```bash
# Check container status
docker compose ps

# Check web logs
docker compose logs web --tail 50

# Check database logs
docker compose logs database --tail 50

# Test database connectivity
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -e 'SELECT 1;'

# Test web accessibility
curl http://localhost:8081

# Test API response
curl http://localhost:8081/api.php?action=overview
```

**Check data:**

```bash
# Total records
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SELECT COUNT(*) FROM daily_snapshots;'

# Date range
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SELECT MIN(snapshot_date), MAX(snapshot_date) FROM daily_snapshots;'

# Latest data
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SELECT * FROM daily_snapshots ORDER BY snapshot_date DESC LIMIT 5;'
```

---

## 🔍 Common Issues Index

**Database Connection Issues:**
- Incorrect hostname (use `database` not `localhost`)
- Database container not healthy
- Wrong credentials
- Network connectivity problem

**Deployment Issues:**
- Old image still running (force recreate)
- Image not pushed to Docker Hub
- Not logged in to Docker Hub
- Configuration file mismatch

**CSV Upload Issues:**
- Wrong CSV format (not All Subscriber Report)
- Missing required columns (Ed, ISS, DEL)
- Week-based precedence rejection (expected behavior)
- Pre-2025 data (filtered out)

**Rendering Issues:**
- JavaScript errors (check console)
- No data in database
- API errors
- Browser cache

**Performance Issues:**
- Missing database indexes
- Large CSV files
- Container resource limits
- Network latency

---

## 1. Dashboard Won't Load

**Symptom:** `http://localhost:8081` or `http://192.168.1.254:8081` won't load

### Step 1: Are containers running?

```bash
docker compose ps
```

**If NO:**

```bash
# Development
docker compose up -d

# Production
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

# Check logs for startup errors
docker compose logs
```

**If YES, go to Step 2** ↓

### Step 2: Is database healthy?

```bash
docker compose ps
# Look for "healthy" status on circulation_db
```

**If NO (starting or unhealthy):**

- **Wait 30 seconds** - First startup takes ~30 seconds
- **Check database logs:**
  ```bash
  docker compose logs database
  # Look for: "mariadbd: ready for connections"
  ```
- **Restart database:**
  ```bash
  docker compose restart database
  ```

**If YES, go to Step 3** ↓

### Step 3: Is port 8081 accessible?

```bash
curl http://localhost:8081
# Or: curl http://192.168.1.254:8081 (production)
```

**If NO (connection refused):**

- **Check if port is in use:**
  ```bash
  lsof -i :8081
  # OR
  netstat -an | grep 8081
  ```
- **Verify port mapping:**
  - Check `docker-compose.yml` has `ports: - "8081:80"`
- **Restart web container:**
  ```bash
  docker compose restart web
  ```

**If YES, go to Step 4** ↓

### Step 4: Application error

**Check web logs:**
```bash
docker compose logs web --tail 100
# Look for: PHP errors, Apache errors, 500 status codes
```

**Check browser console:**
- Open DevTools: `F12` → Console tab
- Look for JavaScript errors

---

## 2. Database Connection Failed

**Symptom:** API returns "Database connection failed"

### Step 1: Is database running?

```bash
docker compose ps
```

**If NO:** See ["Dashboard Won't Load"](#1-dashboard-wont-load) above

**If YES, go to Step 2** ↓

### Step 2: Can web container reach database?

```bash
docker exec circulation_web php -r "\$pdo = new PDO('mysql:host=database;dbname=circulation_dashboard', 'circ_dash', 'Barnaby358@Jones!'); echo 'Connected!';"
```

**If NO (connection refused):**

**✅ Verify database hostname:**
- File: `web/config.php`
- **Correct:** `database` (Docker Compose service name)
- **Incorrect:** `localhost`, `127.0.0.1`, or IP address

**Check Docker network:**
```bash
docker network ls
# Look for: circulation_network
```

**Recreate containers:**
```bash
docker compose down && docker compose up -d
```

**If YES, go to Step 3** ↓

### Step 3: Are credentials correct?

**Development credentials:**
- Host: `database`
- User: `circ_dash`
- Password: `Barnaby358@Jones!`
- Database: `circulation_dashboard`

**If credentials are wrong:**

**Development:**
- Update `.env` file with correct credentials
- Restart: `docker compose up -d --force-recreate`

**Production:**
- Update `docker-compose.prod.yml` (hardcoded values, NOT `.env`)
- Restart: `sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate`

---

## 3. CSV Upload Fails

**Symptom:** Upload fails with error message

### Error: "CSV does not appear to be All Subscriber Report"

**Diagnosis:** CSV format validation failed

**Causes:**
- Wrong CSV file (not All Subscriber Report)
- Missing required columns: `Ed`, `ISS`, `DEL`

**Solutions:**

1. **Verify CSV is from Newzware:**
   - Must be "All Subscriber Report" query export
   - Required columns: `Ed` (paper code), `ISS` (issue date), `DEL` (delivery type)

2. **Check CSV has data rows:**
   - Open CSV in spreadsheet
   - Verify subscriber rows exist (not just headers)

3. **Re-export from Newzware:**
   - Log in to Newzware Ad-Hoc Query Builder
   - Run "All Subscriber Report" query
   - Export as CSV
   - Try upload again

---

### Error: "No valid data found"

**Diagnosis:** CSV parsed but no valid records

**Causes:**
- All dates are pre-2025 (filtered out)
- No active subscribers in export

**Solutions:**

1. **Check snapshot dates:**
   - **Only 2025-01-01 onwards is imported**
   - Pre-2025 excluded due to rate system change

2. **Verify CSV has active subscribers:**
   - `Ed` column should have: TJ, TA, TR, LJ, WRN

3. **Check upload logs:**
   ```bash
   docker compose logs web | grep upload
   ```

---

### Error: "Later-in-week data already exists"

**Diagnosis:** Week-based precedence rejection

⚠️ **This is EXPECTED behavior, not an error!**

**Week Precedence Rules:**
- Saturday ➡️ Replaces Friday ✅
- Friday ➡️ Replaces Thursday ✅
- Tuesday ❌ Rejected if Friday exists
- Same day ➡️ Overwrites ✅
- Monday → Assigned to previous week's Saturday

**Solutions:**

1. **Use Saturday reports for weekly snapshots** (recommended)
   - Saturday captures full week's activity

2. **Re-upload same day or later:**
   - Can replace Friday with Saturday ✅
   - Can replace Friday with Friday ✅  
   - Cannot replace Friday with Tuesday ❌

3. **Establish consistent upload schedule**
   - Recommendation: Every Saturday

---

### Error: "File too large"

**Diagnosis:** File exceeds upload limit (10 MB)

**Solutions:**

```bash
# Check file size (typical: ~1-2 MB for 8,000 rows)
ls -lh AllSubscriberReport*.csv
```

If file is legitimately large, increase PHP limits:
- `php.ini`: `upload_max_filesize`
- `php.ini`: `post_max_size`

---

### Error: Processing timeout

**Diagnosis:** Upload times out during processing

**Normal processing time:** 10-30 seconds for ~8,000 rows

**Solutions:**

```bash
# Check web container logs
docker compose logs web --tail 100

# Verify database is responsive
docker exec circulation_db mariadb -uroot -pRootPassword456! -e 'SELECT 1;'
```

If needed, increase `php.ini`: `max_execution_time`

---

## 4. Charts Not Rendering

**Symptom:** Dashboard loads but charts don't appear

### Step 1: Check browser console

**Open DevTools:** `F12` → Console tab

**Common JavaScript Errors:**

| Error | Diagnosis | Solution |
|-------|-----------|----------|
| `Chart is not defined` | Chart.js not loaded | Verify Chart.js CDN in `index.php` |
| `CircDashboard is not defined` | Module load order | Ensure `app.js` loads first |
| `Failed to fetch` or `500 error` | API error | Test: `http://localhost:8081/api.php?action=overview` |

**If errors found, fix them**

**If no errors, go to Step 2** ↓

### Step 2: Is there data in database?

```bash
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SELECT COUNT(*) FROM daily_snapshots;'
```

**If count is 0:**
- Upload CSV data: `http://localhost:8081/upload.html`

**If count > 0, go to Step 3** ↓

### Step 3: Does API return data?

```bash
curl http://localhost:8081/api.php?action=overview
```

**If NO (error or empty):**
- Check web logs: `docker compose logs web --tail 50`
- See ["Database Connection Failed"](#2-database-connection-failed) above

**If YES, go to Step 4** ↓

### Step 4: Frontend rendering issue

**Solutions:**

1. **Hard refresh browser:**
   - Windows/Linux: `Ctrl+Shift+R`
   - Mac: `Cmd+Shift+R`

2. **Clear browser cache:**
   - Clear site data in browser settings

3. **Verify DashboardRendered event:**
   - File: `app.js` line 409
   - Should have: `document.dispatchEvent(new Event('DashboardRendered'));`

---

## 5. Docker Build Fails

**Symptom:** `./build-and-push.sh` or `docker build` fails

### Error: "Cannot connect to Docker daemon"

**Diagnosis:** Docker not running

**Solutions:**
- Start Docker Desktop (macOS/Windows)
- Check Docker service: `docker info`

---

### Error: "COPY failed: no such file or directory"

**Diagnosis:** Not in project root or missing files

**Solutions:**

```bash
# Ensure in project root
cd $PROJECT_ROOT

# Verify Dockerfile exists
ls -la Dockerfile

# Check all COPY paths exist
```

---

### Error: "bad interpreter: /bin/bash^M"

**Diagnosis:** Windows line endings (CRLF) instead of Unix (LF)

**Solutions:**

```bash
# Fix line endings
sed -i '' 's/\r$//' build-and-push.sh

# Make executable
chmod +x build-and-push.sh

# Run again
./build-and-push.sh
```

---

### Error: "Multi-platform build is not supported"

**Diagnosis:** Default Docker driver doesn't support multi-platform

**Solutions:**

```bash
# Create buildx builder
docker buildx create --name multiarch-builder --driver docker-container --use

# Bootstrap builder
docker buildx inspect --bootstrap

# Run build again
./build-and-push.sh
```

---

## 6. Docker Push Fails

**Symptom:** Pushing to Docker Hub fails

### Error: "denied: requested access to the resource is denied"

**Diagnosis:** Not logged in to Docker Hub

**Solutions:**

```bash
# Login to Docker Hub
docker login
# Username: binarybcc

# Verify logged in
docker info | grep Username
# Expected: Username: binarybcc
```

---

### Error: "repository does not exist"

**Diagnosis:** Repository name incorrect or doesn't exist

**Solutions:**

1. **Verify repository exists:**
   - URL: https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ/

2. **Check image name:**
   - Correct: `binarybcc/nwdownloads-circ`
   - File: `build-and-push.sh`

---

## 7. Production Deployment Fails

**Symptom:** Deployment to Synology NAS fails

### SSH Connection Fails

**Error:** Connection refused or timeout

**Solutions:**

```bash
# Verify NAS is accessible
ping 192.168.1.254

# Check SSH is enabled
# NAS Settings: Control Panel → Terminal & SNMP → Enable SSH service

# Verify credentials
# Correct: user=it, password=Mojave48ice
```

---

### Docker Pull Fails

**Error:** Pull access denied or image not found

**Solutions:**

```bash
# Login to Docker Hub on NAS
sudo /usr/local/bin/docker login
# Credentials: binarybcc / [password]

# Verify image exists on Docker Hub
# URL: https://hub.docker.com/repository/docker/binarybcc/nwdownloads-circ/tags
# Check: latest tag exists and recently updated

# Test internet connectivity
ping hub.docker.com
```

---

### Container Won't Start

**Error:** Container exits or shows unhealthy

**Solutions:**

```bash
# Check container logs
sudo /usr/local/bin/docker logs circulation_web --tail 100
```

**Common issues:**
- Database not ready → Wait for health check
- Port 8081 in use → `netstat -an | grep 8081`
- Environment variables missing → Check `docker-compose.prod.yml`
- File permissions → Check `/volume1/docker/nwdownloads` ownership

---

### Old Code Still Running

**Error:** Changes not visible after deployment

**Diagnosis:** Container using old image

**Solutions:**

```bash
# Verify image digest
sudo /usr/local/bin/docker inspect circulation_web | grep Image

# Force recreate containers
sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d --force-recreate

# Check Docker Hub image timestamp
# Verify 'latest' tag was actually pushed recently
```

---

## 8. Data Looks Wrong

**Symptom:** Dashboard shows incorrect or unexpected data

### "No data for this week"

**Diagnosis:** No snapshot for selected week

**Solutions:**

1. **Upload CSV for this week:**
   - URL: `http://localhost:8081/upload.html`

2. **Select different week:**
   - Click date picker, choose week with data

3. **Check week calculation:**
   - System uses Sunday-Saturday weeks (ISO 8601 with Sunday start)

---

### Subscriber Counts Seem Wrong

**Diagnosis:** Data accuracy issue

**Solutions:**

```bash
# Check database directly
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "SELECT * FROM daily_snapshots WHERE snapshot_date = (SELECT MAX(snapshot_date) FROM daily_snapshots);"

# Check for duplicates
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e "SELECT snapshot_date, paper_code, COUNT(*) FROM daily_snapshots GROUP BY snapshot_date, paper_code HAVING COUNT(*) > 1;"
```

**Verify:**
- Upload summary matched expectations
- Compare to Newzware source CSV
- Check totals in spreadsheet

---

### Year-over-Year Shows "No Data"

**Diagnosis:** Not enough historical data

**Explanation:**
- Pre-2025 data was deleted due to rate system change
- Current data range: January 4, 2025 onwards
- **2026 will be first year with valid YoY comparisons**

**Solutions:**
- Use week-over-week or month-over-month instead
- Accept limitation (old data was incomparable)

---

## 9. Performance Issues

**Symptom:** Dashboard loads slowly or times out

### Initial Page Load

**Normal time:** 2-3 seconds first visit, <500ms cached

**If slower:**

```bash
# Check database query performance
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SHOW PROCESSLIST;'

# Verify indexes exist
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SHOW INDEX FROM daily_snapshots;'
# Expected: PRIMARY (snapshot_date, paper_code), date_idx, paper_idx

# Check container resources
docker stats circulation_web circulation_db
```

---

### CSV Upload

**Normal time:** 10-30 seconds for ~8,000 rows

**If slower:**

```bash
# Check file size (normal: ~1-2 MB)
ls -lh AllSubscriberReport*.csv

# Monitor database during upload
docker stats circulation_db

# Check for locks
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard -e 'SHOW OPEN TABLES WHERE In_use > 0;'
```

---

### Chart Rendering

**Normal time:** ~100ms per chart

**If slower:**
- Check browser console: `F12` → Console
- Verify Chart.js loaded: Type `Chart` in console
- Hard refresh: `Ctrl+Shift+R` or `Cmd+Shift+R`

---

## 🆘 Escalation Path

**If troubleshooting fails:**

1. **Document exact error** and steps to reproduce
2. **Gather logs:**
   ```bash
   docker compose logs > debug.log
   ```
3. **Check recent changes:**
   ```bash
   git log
   ```
4. **Consider rollback:**
   - See `deployment-procedures.json` → `rollback_production`
5. **Review recent commits** for breaking changes

---

**End of Troubleshooting Guide**
