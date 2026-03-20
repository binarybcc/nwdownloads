# Production Deployment Guide - December 7, 2025

> **NOTE:** This deployment originally used Docker. The project has since migrated to native Synology Apache + PHP 8.2 + MariaDB 10. This document is kept for historical reference.

## What Was Deployed

**Git Commit:** `ad8eca6` - Infrastructure improvements and critical bug fixes

### Key Changes in This Deployment

1. **setTimeout Race Condition Fix** - Event-based coordination (Gemini code review finding)
2. **Environment Variable Migration** - Credentials externalized to .env
3. **Week-Based Upload Precedence** - Smart day-of-week logic for CSV uploads

---

## Deployment Steps (Current Method)

### Step 1: Connect to Production NAS

```bash
ssh nas
```

### Step 2: Run Deploy Script

```bash
~/deploy-circulation.sh
```

### Step 3: Verify Deployment

**Check error logs:**

```bash
tail -50 /volume1/web/circulation/error.log
```

### Step 4: Functional Testing

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
- Check NAS resource usage via Synology DSM Resource Monitor

---

## Troubleshooting

### Issue: Site Not Loading

**Solution:**

```bash
ssh nas
tail -50 /volume1/web/circulation/error.log
# Check file permissions
ls -la /volume1/web/circulation/
```

### Issue: Dashboard Shows "Connection Failed"

**Solution:**

```bash
# SSH into NAS, test DB connectivity
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock -e "SELECT 1;"
```

### Issue: Old Code Still Running

**Solution:**

```bash
# Re-run deploy script
ssh nas
~/deploy-circulation.sh
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

## Rollback Plan

If issues occur, rollback to previous version:

```bash
ssh nas
cd /volume1/homes/it/circulation-deploy
git log --oneline -5  # Find previous commit hash
git checkout <previous-commit-hash>
~/deploy-circulation.sh
```

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

**Production Dashboard:** https://cdash.upstatetoday.com
**Production Upload:** https://cdash.upstatetoday.com/upload_unified.php
**GitHub Repository:** https://github.com/binarybcc/nwdownloads
**Latest Commit:** https://github.com/binarybcc/nwdownloads/commit/ad8eca6

---

## Important Notes

Production runs natively on Synology Apache + PHP 8.2 + MariaDB 10 at `/volume1/web/circulation/`. No Docker is used. Credentials are managed via `.env.credentials` and `web/config.php`.

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
