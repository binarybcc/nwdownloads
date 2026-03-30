# Prompt: Switch AllSubscriber Import to Daily (Mon-Sat)

**Context:** This prompt was prepared on 2026-03-29 from the home workstation after analyzing the import pipeline. Give this to Claude at the office to execute the cron change on the NAS.

---

## What We Decided

Switch the AllSubscriberReport import from weekly to Monday-Saturday to keep subscriber data fresh (expirations, payments, vacation holds reflected next business day). No code changes needed -- the existing `process-inbox.php` and `AllSubscriberImporter.php` handle daily runs without modification because:

- Each import DELETEs and re-INSERTs the current week's data (keyed by `week_num + year`)
- Overlapping 7-day lookback windows are safe (full snapshot, not deltas)
- Historical weeks are protected by the backfill algorithm (stops at real data)
- Saturday's import becomes the final historical record for each week
- Trend charts are unaffected -- only the current week's data point refreshes daily

## What Needs to Happen

### Step 1: Confirm Current Cron Schedule

SSH into the NAS and check the current cron entry for `process-inbox.php`:

```bash
ssh nas
crontab -l | grep -i "process-inbox\|circulation"
```

Also check the Synology Task Scheduler in DSM (Control Panel > Task Scheduler) in case it's configured there instead of crontab.

Record the current schedule time (expected: Monday 00:03 AM or similar).

### Step 2: Confirm Newzware Export Schedule

Verify how the AllSubscriberReport CSV currently lands in `/volume1/homes/newzware/inbox/`. Check:

```bash
ls -la /volume1/homes/newzware/inbox/
ls -la /volume1/homes/newzware/completed/ | tail -10
```

The completed directory will show historical filenames with timestamps, confirming the current export schedule and timing.

### Step 3: Update Newzware Export to Daily (Mon-Sat)

Change the Newzware ad-hoc query cron to run the "All Subscriber Report" export Monday through Saturday. The export should complete and land the CSV in `/volume1/homes/newzware/inbox/` before `process-inbox.php` runs.

### Step 4: Update process-inbox.php Cron to Mon-Sat

Change the cron schedule from weekly Monday to Monday-Saturday, keeping the **same time**:

```
# BEFORE (weekly Monday):
# 3 0 * * 1  /var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/process-inbox.php

# AFTER (Mon-Sat, same time):
# 3 0 * * 1-6  /var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/process-inbox.php
```

**Important:** Adjust the actual time/path to match what you find in Step 1. The key change is `* * 1` (Monday only) to `* * 1-6` (Monday through Saturday). Sunday is excluded (day 0 or 7).

If using Synology Task Scheduler instead of crontab, update the "Repeat" setting from "Weekly - Monday" to "Daily" and exclude Sunday.

### Step 5: Verify

After both crons are updated, confirm with a dry run if possible:

```bash
# Check a file is in the inbox (or place a test one)
ls /volume1/homes/newzware/inbox/

# Manual trigger to verify processing works
/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/process-inbox.php

# Check it completed
ls /volume1/homes/newzware/completed/ | tail -5

# Check dashboard reflects fresh data
# Visit https://cdash.upstatetoday.com and check latest snapshot date
```

### Step 6: Monitor First Week

Check `file_processing_log` after a few days to confirm daily runs are succeeding:

```sql
SELECT filename, status, records_processed, started_at, processing_duration_seconds
FROM file_processing_log
ORDER BY started_at DESC
LIMIT 10;
```

## Risks

- **None to data integrity** -- the system handles this natively
- **Disk usage** -- ~5-6x more rows in `raw_uploads` (stores full CSV each run). At ~8K rows per CSV this is negligible
- **If Newzware export is late** -- `process-inbox.php` finds empty inbox, logs "No files to process", exits cleanly. Next day catches up normally
