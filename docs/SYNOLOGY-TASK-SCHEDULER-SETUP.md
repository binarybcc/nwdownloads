# Synology Task Scheduler Configuration

## Backup Schedule Setup

This document provides step-by-step instructions for configuring the automated backup schedule in Synology Task Scheduler.

### Schedule Requirements

**Backup Times:**
- **Sunday**: 23:30 (11:30 PM)
- **Wednesday**: 00:30 (12:30 AM)
- **Friday**: 00:30 (12:30 AM)

**Script Location:** `/volume1/homes/it/scripts/backup-circulation.sh`

---

## Configuration Steps

### 1. Access Task Scheduler

1. Log into Synology DSM web interface
2. Navigate to: **Control Panel** → **Task Scheduler**
3. Click **Create** → **Scheduled Task** → **User-defined script**

### 2. General Settings

**Task Name:** `Circulation Dashboard Backup`

**User:** `root` (required for database access)

**Enabled:** ☑ (checked)

**Event:** Leave blank

**Pre-task:** Leave blank

**Comments:** `Automated 3-copy rotation backup of circulation dashboard database and code`

### 3. Schedule Settings

**Run on the following days:**
- ☑ Sunday
- ☑ Wednesday
- ☑ Friday

**First run time:**
- **Sunday**: `23:30`
- **Wednesday**: `00:30`
- **Friday**: `00:30`

**Frequency:** Select "Run on the following days"

**Last run time:** Leave blank (will run once per day)

### 4. Task Settings

**Run command:**

```bash
/bin/bash /volume1/homes/it/scripts/backup-circulation.sh
```

**User-defined script:**

```bash
#!/bin/bash
# Circulation Dashboard Backup
# Runs automated 3-copy rotation backup

# Set path
export PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin:/usr/local/mariadb10/bin

# Execute backup script
/bin/bash /volume1/homes/it/scripts/backup-circulation.sh

# Exit with script's exit code
exit $?
```

**Send run details by email:** ☐ (unchecked - we'll add email notifications in Task 8)

**Send run details only when the script terminates abnormally:** ☐ (unchecked)

### 5. Click OK to Save

The task will now run automatically at the scheduled times.

---

## Verification Steps

### Test the Scheduled Task

1. In Task Scheduler, select "Circulation Dashboard Backup"
2. Click **Run** button (do not wait for scheduled time)
3. Wait 1-2 minutes for backup to complete
4. Click **Action** → **View Result** to see output
5. Verify backup completed successfully

### Check Backup Files

```bash
# SSH into NAS
ssh it@192.168.1.254

# Check latest backup
ls -lh /volume1/homes/newzware/backup/backup-1/

# View latest backup log
ls -lht /volume1/homes/newzware/backup/logs/ | head -5
tail -30 /volume1/homes/newzware/backup/logs/backup-$(date +%Y-%m-%d)-*.log
```

Expected output:
```
[YYYY-MM-DD HH:MM:SS] ✓ Backup completed successfully!
[YYYY-MM-DD HH:MM:SS] Total time: X seconds
```

### Monitor Schedule

The Task Scheduler will show:
- **Next Run Time**: Displays next scheduled execution
- **Last Run Time**: Shows when last backup ran
- **Last Run Result**: Shows success/failure status

---

## Troubleshooting

### Task Doesn't Run

**Check:**
1. Task is enabled (checkbox in Task Scheduler)
2. User is set to `root`
3. Script path is correct: `/volume1/homes/it/scripts/backup-circulation.sh`
4. Script has execute permissions: `chmod +x /volume1/homes/it/scripts/backup-circulation.sh`

### Backup Fails

**Check logs:**
```bash
tail -50 /volume1/homes/newzware/backup/logs/backup-*.log
```

**Common issues:**
- Insufficient disk space (< 5GB free)
- Database not accessible
- MariaDB binaries not in PATH
- Permission issues

### Email Notifications Not Working

Email notifications will be configured in Task 8. For now, monitor logs manually or check Task Scheduler result history.

---

## Schedule Reference

**Why these times?**

- **Sunday 23:30**: End of week backup (captures Saturday print day data)
- **Wednesday 00:30**: Mid-week backup (captures Tuesday digital/Wednesday print data)
- **Friday 00:30**: End of work week backup (captures Thursday print day data)

**Backup retention:**
- **backup-1**: Most recent (current week)
- **backup-2**: Previous backup (1 week old)
- **backup-3**: Oldest backup (2 weeks old)

Each backup run rotates: 3 → delete, 2 → 3, 1 → 2, new → 1

---

## Production Checklist

- [ ] Task created in Task Scheduler
- [ ] Schedule configured (Sun 23:30, Wed 00:30, Fri 00:30)
- [ ] User set to `root`
- [ ] Script path verified
- [ ] Test run completed successfully
- [ ] Backup files verified in backup-1/
- [ ] Logs show successful completion
- [ ] Next run time displays correctly

**Once all items are checked, Task 7 is complete!**
