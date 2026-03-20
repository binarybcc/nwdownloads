# Troubleshooting Guide - NWDownloads Circulation Dashboard

> **Last Updated:** December 7, 2025  
> **Format:** Symptom → Diagnosis → Solution

This guide uses decision tree logic to help diagnose and fix common issues.

---

## Quick Diagnostic Commands

**Run these first when troubleshooting (SSH into NAS first: `ssh nas`):**

```bash
# Check web accessibility
curl http://192.168.1.254:8081

# Test API response
curl http://192.168.1.254:8081/api.php?action=overview

# Check error logs
tail -50 /volume1/web/circulation/error.log

# Test database connectivity
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e 'SELECT 1;'
```

**Check data (on NAS):**

```bash
# Total records
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SELECT COUNT(*) FROM daily_snapshots;'

# Date range
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SELECT MIN(snapshot_date), MAX(snapshot_date) FROM daily_snapshots;'

# Latest data
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SELECT * FROM daily_snapshots ORDER BY snapshot_date DESC LIMIT 5;'
```

---

## 🔍 Common Issues Index

**Database Connection Issues:**

- Incorrect socket path
- MariaDB service not running
- Wrong credentials

**Deployment Issues:**

- Old code still deployed (re-run deploy script)
- File permissions incorrect
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
- Network latency

---

## 1. Dashboard Won't Load

**Symptom:** `https://cdash.upstatetoday.com` or `http://192.168.1.254:8081` won't load

### Step 1: Is the NAS accessible?

```bash
ping 192.168.1.254
```

**If NO:** Check network connection to NAS.

**If YES, go to Step 2.**

### Step 2: Is the web server running?

```bash
curl http://192.168.1.254:8081
```

**If NO (connection refused):**

- **SSH into NAS:** `ssh nas`
- **Check if port is in use:**
  ```bash
  netstat -an | grep 8081
  ```
- **Check Apache/Web Station is running** in Synology DSM

**If YES, go to Step 3.**

### Step 3: Is the database accessible?

```bash
# SSH into NAS, then:
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e "SELECT 1;"
```

**If NO:** Check MariaDB 10 service is running on NAS.

**If YES, go to Step 4.**

### Step 4: Application error

**Check error logs (on NAS):**

```bash
tail -100 /volume1/web/circulation/error.log
# Look for: PHP errors, Apache errors, 500 status codes
```

**Check browser console:**

- Open DevTools: `F12` → Console tab
- Look for JavaScript errors

---

## 2. Database Connection Failed

**Symptom:** API returns "Database connection failed"

### Step 1: Is MariaDB running?

```bash
# SSH into NAS, then:
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e "SELECT 1;"
```

**If NO:** Start MariaDB 10 service via Synology DSM Package Center.

**If YES, go to Step 2.**

### Step 2: Can PHP reach the database?

**Verify database connection in `web/config.php`:**

- Should use Unix socket: `/run/mysqld/mysqld10.sock`

### Step 3: Are credentials correct?

**Production credentials:**

- Socket: `/run/mysqld/mysqld10.sock`
- Database: `circulation_dashboard`
- Credentials: See `.env.credentials`

**If credentials are wrong:**

- Update `web/config.php` or `.env.credentials`
- No restart needed (PHP reads config on each request)

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
   # SSH into NAS
   tail -50 /volume1/web/circulation/error.log
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
# SSH into NAS, check error logs
tail -100 /volume1/web/circulation/error.log

# Verify database is responsive
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e 'SELECT 1;'
```

If needed, increase `php.ini`: `max_execution_time`

---

## 4. Charts Not Rendering

**Symptom:** Dashboard loads but charts don't appear

### Step 1: Check browser console

**Open DevTools:** `F12` → Console tab

**Common JavaScript Errors:**

| Error                            | Diagnosis           | Solution                                                       |
| -------------------------------- | ------------------- | -------------------------------------------------------------- |
| `Chart is not defined`           | Chart.js not loaded | Verify Chart.js CDN in `index.php`                             |
| `CircDashboard is not defined`   | Module load order   | Ensure `app.js` loads first                                    |
| `Failed to fetch` or `500 error` | API error           | Test: `https://cdash.upstatetoday.com/api.php?action=overview` |

**If errors found, fix them**

**If no errors, go to Step 2** ↓

### Step 2: Is there data in database?

```bash
# SSH into NAS, then:
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SELECT COUNT(*) FROM daily_snapshots;'
```

**If count is 0:**

- Upload CSV data: https://cdash.upstatetoday.com/upload_unified.php

**If count > 0, go to Step 3** ↓

### Step 3: Does API return data?

```bash
curl https://cdash.upstatetoday.com/api.php?action=overview
```

**If NO (error or empty):**

- Check error logs on NAS: `tail -50 /volume1/web/circulation/error.log`
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

## 5. Production Deployment Fails

**Symptom:** Deployment to Synology NAS fails

### SSH Connection Fails

**Error:** Connection refused or timeout

**Solutions:**

```bash
# Verify NAS is accessible
ping 192.168.1.254

# Check SSH is enabled
# NAS Settings: Control Panel → Terminal & SNMP → Enable SSH service

# Connect via: ssh nas (uses key auth)
```

---

### Deploy Script Fails

**Error:** git pull or rsync fails

**Solutions:**

```bash
# SSH into NAS
ssh nas

# Check deploy script
cat ~/deploy-circulation.sh

# Manual deploy
cd /volume1/homes/it/circulation-deploy
git pull origin master
rsync -av --delete \
  --exclude='.htaccess' \
  --exclude='.build_number' \
  /volume1/homes/it/circulation-deploy/web/ \
  /volume1/web/circulation/
```

---

### Old Code Still Running

**Error:** Changes not visible after deployment

**Solutions:**

```bash
# SSH into NAS, re-run deploy
ssh nas
~/deploy-circulation.sh

# Verify files were updated
ls -lt /volume1/web/circulation/ | head -10

# Check PHP opcache if enabled
# May need to restart Apache via Synology Web Station
```

---

## 8. Data Looks Wrong

**Symptom:** Dashboard shows incorrect or unexpected data

### "No data for this week"

**Diagnosis:** No snapshot for selected week

**Solutions:**

1. **Upload CSV for this week:**
   - URL: https://cdash.upstatetoday.com/upload_unified.php

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
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "SELECT * FROM daily_snapshots WHERE snapshot_date = (SELECT MAX(snapshot_date) FROM daily_snapshots);"

# Check for duplicates
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "SELECT snapshot_date, paper_code, COUNT(*) FROM daily_snapshots GROUP BY snapshot_date, paper_code HAVING COUNT(*) > 1;"
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
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SHOW PROCESSLIST;'

# Verify indexes exist
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SHOW INDEX FROM daily_snapshots;'
# Expected: PRIMARY (snapshot_date, paper_code), date_idx, paper_idx

# Check container resources
# Check NAS resource usage via Synology DSM Resource Monitor
```

---

### CSV Upload

**Normal time:** 10-30 seconds for ~8,000 rows

**If slower:**

```bash
# Check file size (normal: ~1-2 MB)
ls -lh AllSubscriberReport*.csv

# Monitor database during upload
# Check NAS resource usage via Synology DSM Resource Monitor

# Check for locks
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e 'SHOW OPEN TABLES WHERE In_use > 0;'
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
   # SSH into NAS
   tail -200 /volume1/web/circulation/error.log > debug.log
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
