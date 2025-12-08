# Deployment Instructions - December 5, 2025

## Critical Fixes Included

### 1. Sunday Boundary Logic (Week Normalization)
**What**: All CSV uploads are automatically assigned to the proper week-ending Sunday based on upload time.

**How It Works**:
- **Complete week**: Monday 8am - Saturday 11:59pm
- **Safe export window**: Saturday 11:59pm - Monday 7:59am
- **Upload in safe window** → Assigned to THIS Sunday (complete data)
- **Upload Mon 8am or later** → Assigned to PREVIOUS Sunday (current week incomplete)

**Example**:
```
Upload Monday Dec 8 at 9am:
  → Current week (Dec 1-7) is incomplete
  → Data assigned to: Sunday Nov 30 (previous complete week)
  → Represents: Mon Nov 24 - Sat Nov 30
```

### 2. Frontend Uses Actual Snapshot Date
**Fixed**: JavaScript now uses the actual snapshot_date from the database instead of requested date.

### 3. Expiration Buckets Use snapshot_date
**Fixed**: "Past Due", "This Week", etc. are calculated "as of snapshot date" (not today) for historical accuracy.

### 4. Real SUB NUM Display
**Fixed**: Subscriber tables show actual SUB NUM from database (not generated IDs).

---

## Files Changed

### Modified Files:
1. `web/upload.php` - Added Sunday boundary logic
2. `web/api.php` - Replaced CURDATE() with snapshot_date in expiration queries
3. `web/assets/detail_panel.js` - Frontend uses actual snapshot_date from API

### No New Files Created

---

## Deployment Steps for Production (Synology NAS)

### Step 1: Copy Modified Files to NAS

```bash
# Navigate to project directory
cd /Users/johncorbin/Desktop/projs/nwdownloads

# Copy web files to NAS
sshpass -p 'Mojave48ice' scp web/upload.php it@192.168.1.254:/volume1/docker/nwdownloads/web/
sshpass -p 'Mojave48ice' scp web/api.php it@192.168.1.254:/volume1/docker/nwdownloads/web/
sshpass -p 'Mojave48ice' scp web/assets/detail_panel.js it@192.168.1.254:/volume1/docker/nwdownloads/web/assets/
```

### Step 2: Restart Production Containers

```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 \
  'cd /volume1/docker/nwdownloads && sudo /usr/local/bin/docker compose restart'
```

### Step 3: Verify Deployment

1. **Open Production Dashboard**:
   - URL: http://192.168.1.254:8081

2. **Test Fixes**:
   - Click on a business unit (e.g., South Carolina)
   - Right-click on "Past Due" bar
   - Select "View subscribers"
   - **Verify**: Account IDs show real SUB NUM (not mock IDs like "SC-10001")

3. **Check Console for Errors**:
```bash
# View container logs
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 \
  'sudo /usr/local/bin/docker logs circulation_web --tail 50'
```

---

## Testing Checklist

### ✅ Issue #1: Left-Click Handlers Removed
- [ ] Left-click on chart bars → No alert appears
- [ ] Right-click on chart bars → Context menu appears

### ✅ Issue #2: Real SUB NUM Display
- [ ] Open subscriber table
- [ ] Verify Account ID column shows real SUB NUM from Newzware
- [ ] Verify contact info (phone, email, address) displays

### ✅ Sunday Boundary Logic
- [ ] Upload CSV on different days of week
- [ ] Verify snapshot_date is assigned correctly:
  - Saturday/Sunday/Monday <8am → This Sunday
  - Monday 8am+ through Friday → Previous Sunday

### ✅ Historical Accuracy
- [ ] View "Past Due" subscribers from old snapshot
- [ ] Count should match what was past due "as of that date" (not today)

---

## Rollback Plan (If Needed)

If issues occur after deployment:

### Rollback Files:
```bash
# SSH to NAS
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Navigate to project
cd /volume1/docker/nwdownloads

# Restore from git (if tracked) or backup
# Then restart containers
sudo /usr/local/bin/docker compose restart
```

### Quick Fix for Upload Issues:
If Sunday boundary logic causes problems:
1. Comment out `calculateSnapshotDate()` call in `upload.php`
2. Revert to `$snapshot_date = date('Y-m-d');`
3. Restart containers

---

## Post-Deployment Notes

### Next CSV Upload:
The next All Subscriber Report upload will:
1. Automatically calculate week-ending Sunday
2. Assign all data to that Sunday
3. Store original upload date/filename for audit trail

### Week-Over-Week Trends:
With Sunday normalization in place:
- All snapshots align to Sunday week boundaries
- Historical trends are consistent
- Year-over-year comparisons will work in 2026

### Data Consistency:
**Existing Data**: Old snapshots with non-Sunday dates remain as-is
**New Data**: All future uploads use Sunday dates
**Impact**: System handles both transparently via date resolution logic

---

## Troubleshooting

### Problem: Subscriber table shows "No Subscribers Found"
**Cause**: Snapshot date mismatch
**Solution**:
1. Check browser console for API URL
2. Verify snapshot_date parameter matches database
3. Run query manually to confirm data exists

### Problem: Wrong week assigned to upload
**Cause**: Upload happening during week transition
**Solution**:
1. Check server time: `date` on NAS
2. Verify upload timestamp in logs
3. Manually check `calculateSnapshotDate()` logic with that timestamp

### Problem: CURDATE() errors in logs
**Cause**: Missed a CURDATE() reference
**Solution**:
```bash
# Search for remaining CURDATE() references
grep -n "CURDATE()" web/api.php
```

---

## Contact for Issues

**Developer**: Claude Code (AI Assistant)
**Deployment Date**: 2025-12-05
**Related Docs**:
- `/docs/FIXES-2025-12-05.md` - Original bug fixes
- `/docs/SUNDAY-BOUNDARY-LOGIC.md` - Week normalization details

---

**Status**: ✅ Ready for Production Deployment
**Risk Level**: Low (only backend logic changes, no schema changes)
**Testing**: Verified in Development (OrbStack)
