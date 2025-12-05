# Hotfolder Automation Design & Implementation

## Document Version
- **Version**: 1.0
- **Date**: 2025-12-01
- **Status**: Design Complete
- **Author**: Claude Code
- **Project**: Circulation Dashboard - Automated CSV Processing

---

## Executive Summary

Design a fully automated system that:
1. Watches a designated folder for new CSV file exports from Newzware
2. Automatically detects when all 3 required files are present
3. Validates files before processing
4. Uploads data to the circulation dashboard database
5. Sends notifications on success/failure
6. Archives processed files for audit trail
7. Operates autonomously without manual intervention

---

## Business Requirements

### Current Manual Process (To Be Replaced)

**Steps:**
1. Export 3 CSVs from Newzware manually
2. Save to desktop or downloads folder
3. Open browser to upload interface
4. Select all 3 files
5. Click upload button
6. Wait for confirmation
7. Move files somewhere safe (maybe?)

**Problems:**
- ❌ Easy to forget on publication days
- ❌ Manual file selection prone to errors
- ❌ No validation before upload
- ❌ No record of what was uploaded when
- ❌ Requires someone to remember

### Proposed Automated Process

**Steps:**
1. Export 3 CSVs from Newzware to hotfolder (one-click)
2. ✅ **Everything else happens automatically**

**Benefits:**
- ✅ Can't forget (runs continuously)
- ✅ Validates files before processing
- ✅ Automatic audit trail
- ✅ Email notifications on errors
- ✅ Recovery from failures
- ✅ Historical archive

---

## Architecture Overview

### Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     HOTFOLDER SYSTEM                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. HOTFOLDER WATCHER (on Mac or NAS)                          │
│     - Monitors: /volume1/circulation/hotfolder/incoming/       │
│     - Detects: New CSV files                                   │
│     - Triggers: Processing when 3 files present                │
│                                                                 │
│  2. FILE VALIDATOR                                             │
│     - Checks: File names match pattern                         │
│     - Validates: CSV structure and headers                     │
│     - Ensures: All 3 required files present                    │
│                                                                 │
│  3. PROCESSOR                                                   │
│     - Calls: upload.php via internal API                       │
│     - Monitors: Processing status                              │
│     - Handles: Errors and retries                              │
│                                                                 │
│  4. ARCHIVER                                                    │
│     - Moves: Processed files to archive folder                 │
│     - Timestamps: Files with processing date                   │
│     - Maintains: 90-day rolling archive                        │
│                                                                 │
│  5. NOTIFIER                                                    │
│     - Logs: All processing events                              │
│     - Emails: Errors to administrator                          │
│     - Updates: Dashboard status indicator                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Options

### Option 1: Mac-Based Watcher (Recommended for Simplicity)

**Location**: Runs on your Mac
**Technology**: Python script + launchd (macOS scheduler)
**Watches**: Network share (/Volumes/circulation/hotfolder)

**Pros:**
- ✅ Easy to develop and test
- ✅ Direct file system access
- ✅ Can send macOS notifications
- ✅ Familiar Python environment
- ✅ Simple to start/stop/debug

**Cons:**
- ❌ Mac must be on and connected
- ❌ Network share must be mounted
- ❌ Won't work if Mac is sleeping

**Best For**: Getting started quickly, testing the workflow

---

### Option 2: NAS-Based Watcher (Recommended for Production)

**Location**: Runs on Synology NAS
**Technology**: Python/Bash script + cron job
**Watches**: Local folder (/volume1/circulation/hotfolder)

**Pros:**
- ✅ Runs 24/7 without Mac
- ✅ Native file system access
- ✅ No network dependencies
- ✅ More reliable
- ✅ Lower latency

**Cons:**
- ❌ Slightly more complex setup
- ❌ Requires SSH access to configure
- ❌ Debugging requires SSH

**Best For**: Production deployment after testing

---

### Option 3: Hybrid Approach (Recommended Overall) ⭐

**Phase 1**: Start with Mac-based watcher (this week)
- Quick setup
- Test the workflow
- Iterate on error handling

**Phase 2**: Migrate to NAS-based (after 1-2 weeks)
- Once proven stable
- Deploy to NAS for 24/7 operation
- Mac script stays as backup

---

## Detailed Design: Hybrid Approach

### Directory Structure

```
/volume1/circulation/hotfolder/
├── incoming/              # Drop CSV files here
│   ├── .processing/       # Temp folder during upload (prevents race conditions)
│   └── .failed/          # Files that failed validation
├── archive/              # Successfully processed files
│   ├── 2025-12-01/       # Organized by date
│   ├── 2025-12-04/
│   └── 2025-12-07/
├── logs/                 # Processing logs
│   ├── hotfolder.log     # Main log file
│   └── error.log         # Error-only log
└── config/               # Configuration
    └── settings.json     # Email, timing, etc.
```

### File Naming Convention

**Required Files** (detected automatically):
```
subscriptions_*.csv       (e.g., subscriptions_latest.csv, subscriptions_20251204.csv)
vacations_*.csv          (e.g., vacations_latest.csv, vacations_20251204.csv)
rates_*.csv              (e.g., rates_corrected.csv, rates_20251204.csv)
```

**Archive Naming** (automatic):
```
2025-12-04/
  ├── subscriptions_latest.csv            (original)
  ├── vacations_latest.csv                (original)
  ├── rates_corrected.csv                 (original)
  ├── upload_result.json                  (API response)
  └── processing.log                      (detailed log)
```

---

## Component Specifications

### 1. File Watcher

**Functionality:**
- Monitors `incoming/` folder every 30 seconds (configurable)
- Detects new CSV files by modification time
- Waits for file stability (no size changes for 5 seconds)
- Triggers processing when 3 files detected

**Pseudo-code:**
```python
while True:
    files = scan_incoming_folder()

    if has_all_three_required_files(files):
        if files_are_stable(files):  # No writes in progress
            trigger_processing(files)
        else:
            wait_for_stability()
    else:
        log_debug("Waiting for files: " + missing_files_list)

    sleep(30)  # Check every 30 seconds
```

**Edge Cases:**
- Files still being written (check file size stability)
- Extra files in folder (ignore non-matching patterns)
- Duplicate processing (use lock files)
- Network interruptions (retry logic)

---

### 2. File Validator

**Validation Checks:**

#### A. File Name Validation
```python
def validate_file_names(files):
    required_patterns = [
        r'^subscriptions_.*\.csv$',
        r'^vacations_.*\.csv$',
        r'^rates_.*\.csv$'
    ]

    for pattern in required_patterns:
        if not any(re.match(pattern, f.name) for f in files):
            raise ValidationError(f"Missing file matching: {pattern}")
```

#### B. File Size Validation
```python
def validate_file_sizes(files):
    min_sizes = {
        'subscriptions': 100 * 1024,  # 100 KB minimum
        'vacations': 50 * 1024,       # 50 KB minimum
        'rates': 10 * 1024            # 10 KB minimum
    }

    for file in files:
        file_type = get_file_type(file.name)
        if file.size < min_sizes[file_type]:
            raise ValidationError(f"{file.name} is suspiciously small")
```

#### C. CSV Structure Validation
```python
def validate_csv_structure(file):
    with open(file, 'r') as f:
        reader = csv.reader(f)
        headers = next(reader)

        expected_headers = {
            'subscriptions': ['sp_num', 'sp_stat', 'sp_rate_id', 'sp_route', 'sp_vac_ind'],
            'vacations': ['vd_sp_id', 'vd_beg_date', 'vd_end_date'],
            'rates': ['sub_rate_id', 'edition', 'description']
        }

        file_type = get_file_type(file.name)
        if headers != expected_headers[file_type]:
            raise ValidationError(f"{file.name} has incorrect headers")

        # Check for minimum row count
        row_count = sum(1 for row in reader)
        if row_count < 10:
            raise ValidationError(f"{file.name} has too few rows")
```

**Validation Response:**
```json
{
    "valid": true,
    "files": [
        {"name": "subscriptions_latest.csv", "size": 178934, "rows": 8584, "status": "ok"},
        {"name": "vacations_latest.csv", "size": 448230, "rows": 15970, "status": "ok"},
        {"name": "rates_corrected.csv", "size": 11234, "rows": 387, "status": "ok"}
    ],
    "warnings": [],
    "errors": []
}
```

---

### 3. Processor

**Processing Steps:**

```python
def process_files(files):
    # 1. Move to .processing folder (prevents duplicate processing)
    processing_dir = move_to_processing(files)

    try:
        # 2. Call upload API
        result = upload_to_dashboard(files)

        # 3. Check result
        if result['success']:
            # 4. Archive files
            archive_successful(files, result)

            # 5. Send success notification
            notify_success(result)

            # 6. Clean up
            remove_from_processing(processing_dir)
        else:
            # 7. Handle failure
            move_to_failed(files)
            notify_failure(result['error'])

    except Exception as e:
        # 8. Handle unexpected errors
        log_error(e)
        move_to_failed(files)
        notify_failure(str(e))
```

**API Call:**
```python
def upload_to_dashboard(files):
    url = "http://192.168.1.254:8080/upload.php"

    form_data = {
        'subscriptions': open(files['subscriptions'], 'rb'),
        'vacations': open(files['vacations'], 'rb'),
        'rates': open(files['rates'], 'rb')
    }

    response = requests.post(url, files=form_data, timeout=60)

    return response.json()
```

---

### 4. Archiver

**Functionality:**
- Creates date-stamped archive folder
- Copies files (preserves originals during processing)
- Saves API response as JSON
- Saves processing log
- Rotates old archives (keeps 90 days)

**Implementation:**
```python
def archive_successful(files, result):
    # Create archive folder
    archive_date = datetime.now().strftime('%Y-%m-%d')
    archive_path = f"/volume1/circulation/hotfolder/archive/{archive_date}"
    os.makedirs(archive_path, exist_ok=True)

    # Copy files
    for file in files:
        shutil.copy(file, archive_path)

    # Save result
    with open(f"{archive_path}/upload_result.json", 'w') as f:
        json.dump(result, f, indent=2)

    # Save log
    with open(f"{archive_path}/processing.log", 'w') as f:
        f.write(get_current_log())

    # Rotate old archives
    rotate_archives(days_to_keep=90)
```

---

### 5. Notifier

**Notification Types:**

#### A. Success Notification (Optional)
```
Subject: ✅ Circulation Data Uploaded Successfully

Date: 2025-12-04
Papers Updated: 5
Records Processed: 3,482

TJ - The Journal       | Active: 3,025 | Vacation: 2 | Deliverable: 3,023
TA - The Advertiser    | Active:   352 | Vacation: 0 | Deliverable:   352
TR - The Register      | Active:    45 | Vacation: 0 | Deliverable:    45
LJ - Lake Journal      | Active:    35 | Vacation: 0 | Deliverable:    35
WRN - Wyoming Review   | Active:    25 | Vacation: 0 | Deliverable:    25

View Dashboard: http://192.168.1.254:8080/
```

#### B. Error Notification (Critical)
```
Subject: ❌ Circulation Data Upload FAILED

Date: 2025-12-04
Error: Missing required CSV files

Expected Files:
- subscriptions_*.csv ✅ Found
- vacations_*.csv ❌ MISSING
- rates_*.csv ✅ Found

Action Required:
1. Export vacations CSV from Newzware
2. Place in: /volume1/circulation/hotfolder/incoming/
3. System will retry automatically

Files have been moved to: /volume1/circulation/hotfolder/incoming/.failed/
```

#### C. Validation Error
```
Subject: ⚠️ Circulation Data Validation Warning

Date: 2025-12-04
Warning: subscriptions_latest.csv has only 10 rows

Expected: 3,000+ rows
Actual: 10 rows

This may indicate:
- Incorrect export from Newzware
- Incomplete file transfer
- Data filtering issue

Files have been moved to .failed folder.
Please verify export and try again.
```

---

## Configuration File

**Location**: `/volume1/circulation/hotfolder/config/settings.json`

```json
{
  "watcher": {
    "poll_interval_seconds": 30,
    "file_stability_seconds": 5,
    "incoming_folder": "/volume1/circulation/hotfolder/incoming",
    "archive_folder": "/volume1/circulation/hotfolder/archive",
    "log_folder": "/volume1/circulation/hotfolder/logs"
  },
  "validator": {
    "min_file_sizes": {
      "subscriptions": 102400,
      "vacations": 51200,
      "rates": 10240
    },
    "min_row_counts": {
      "subscriptions": 100,
      "vacations": 10,
      "rates": 50
    },
    "required_headers": {
      "subscriptions": ["sp_num", "sp_stat", "sp_rate_id", "sp_route", "sp_vac_ind"],
      "vacations": ["vd_sp_id", "vd_beg_date", "vd_end_date"],
      "rates": ["sub_rate_id", "edition", "description"]
    }
  },
  "processor": {
    "api_url": "http://192.168.1.254:8080/upload.php",
    "timeout_seconds": 120,
    "retry_attempts": 3,
    "retry_delay_seconds": 10
  },
  "archiver": {
    "archive_retention_days": 90,
    "compress_archives": false
  },
  "notifier": {
    "email_enabled": true,
    "email_to": "admin@example.com",
    "email_from": "circulation-dashboard@example.com",
    "smtp_server": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_username": "user@gmail.com",
    "smtp_password": "app_password_here",
    "notify_on_success": false,
    "notify_on_error": true,
    "notify_on_validation_error": true
  },
  "logging": {
    "log_level": "INFO",
    "max_log_size_mb": 10,
    "log_rotation_count": 5
  }
}
```

---

## Implementation Plan

### Phase 1: Mac-Based Development (This Week)

**Day 1-2: Core Development**
- [ ] Create Python script structure
- [ ] Implement file watcher
- [ ] Implement file validator
- [ ] Implement processor (API call)
- [ ] Test with sample files

**Day 3: Integration**
- [ ] Create hotfolder directories on NAS
- [ ] Mount NAS share on Mac
- [ ] Test end-to-end with real data
- [ ] Add error handling

**Day 4: Polish**
- [ ] Add archiving
- [ ] Add logging
- [ ] Add email notifications (optional)
- [ ] Create launchd plist for auto-start

**Day 5: Production Testing**
- [ ] Run for one publication cycle (Wed-Thu-Sat)
- [ ] Monitor for issues
- [ ] Refine based on results

### Phase 2: NAS Migration (Week 2)

**Day 1: Port to NAS**
- [ ] Transfer Python script to NAS
- [ ] Test script on NAS environment
- [ ] Adjust paths for local file system

**Day 2: Scheduling**
- [ ] Create cron job
- [ ] Test auto-start
- [ ] Verify logging

**Day 3: Production Deployment**
- [ ] Switch from Mac to NAS
- [ ] Monitor for 1 week
- [ ] Keep Mac script as backup

---

## Python Script Structure

```
hotfolder_watcher.py                # Main script
├── config/
│   └── settings.py                 # Configuration loader
├── watcher/
│   ├── file_monitor.py            # Watches incoming folder
│   └── stability_checker.py       # Ensures files are complete
├── validator/
│   ├── file_validator.py          # Name/size validation
│   └── csv_validator.py           # CSV structure validation
├── processor/
│   ├── api_client.py              # Calls upload.php
│   └── retry_handler.py           # Retry logic
├── archiver/
│   ├── file_archiver.py           # Moves to archive
│   └── rotation_manager.py        # Deletes old archives
├── notifier/
│   ├── email_notifier.py          # Sends emails
│   └── log_notifier.py            # Writes logs
└── utils/
    ├── logger.py                  # Logging setup
    └── file_utils.py              # File operations
```

---

## Error Handling & Recovery

### Common Scenarios

#### Scenario 1: Only 2 of 3 Files Uploaded
**Detection**: Watcher doesn't see all 3 required patterns
**Action**: Wait indefinitely, log every 5 minutes
**Recovery**: User adds missing file, processing triggers automatically

#### Scenario 2: Network Timeout During Upload
**Detection**: API call timeout (120 seconds)
**Action**: Move files to .processing, retry 3 times with 10-second delays
**Recovery**: If all retries fail, move to .failed and notify

#### Scenario 3: Invalid CSV Structure
**Detection**: CSV validator finds wrong headers or too few rows
**Action**: Move files to .failed immediately (no retry)
**Notification**: Email admin with validation details
**Recovery**: User fixes export, places corrected files in incoming/

#### Scenario 4: Duplicate Processing
**Detection**: Files already in .processing folder
**Action**: Skip processing, log warning
**Recovery**: Previous process completes, or admin manually moves files

#### Scenario 5: Disk Full
**Detection**: OS error during file copy
**Action**: Log error, leave files in incoming/, notify admin
**Recovery**: Admin frees space, watcher retries on next cycle

---

## Testing Strategy

### Unit Tests
```python
# test_validator.py
def test_valid_subscription_file():
    assert validate_file("subscriptions_latest.csv") == True

def test_invalid_file_name():
    with pytest.raises(ValidationError):
        validate_file("invalid_name.csv")

def test_too_small_file():
    with pytest.raises(ValidationError):
        validate_file_size("tiny.csv", size=100)
```

### Integration Tests
```python
# test_end_to_end.py
def test_full_workflow():
    # 1. Place 3 valid CSVs in incoming/
    place_test_files()

    # 2. Run watcher for 60 seconds
    run_watcher(timeout=60)

    # 3. Verify files archived
    assert archive_folder_contains_files()

    # 4. Verify database updated
    assert database_has_new_snapshot()
```

### Manual Tests
- [ ] Drop 3 files, verify auto-processing
- [ ] Drop 2 files, verify waiting
- [ ] Drop invalid CSV, verify moved to .failed
- [ ] Disconnect network during upload, verify retry
- [ ] Process same files twice, verify duplicate detection

---

## Monitoring & Maintenance

### Daily Checks (Automated)
- ✅ Watcher process running
- ✅ No files stuck in .processing
- ✅ No files in .failed folder
- ✅ Logs under 10 MB

### Weekly Checks (Manual)
- Review error.log for patterns
- Check archive folder size
- Verify email notifications working
- Test with sample files

### Monthly Maintenance
- Review and update configuration
- Check for Python/library updates
- Archive old logs
- Verify backup processes

---

## Security Considerations

### File Access
- Hotfolder readable/writable by circulation user only
- Archive folder read-only after creation
- Config file contains sensitive SMTP credentials (chmod 600)

### API Security
- Upload.php already validates file types
- Upload.php uses prepared statements (SQL injection safe)
- Hotfolder script runs as non-root user

### Network Security
- API calls stay within local network (192.168.x.x)
- No external internet access required
- SMTP credentials encrypted in config

---

## Cost & Resource Requirements

### Development Time
- Phase 1 (Mac): 8-12 hours
- Phase 2 (NAS): 4-6 hours
- Testing: 2-4 hours
- **Total: 14-22 hours over 2 weeks**

### Ongoing Resources
- Disk space: ~50 MB per day (archives)
- CPU: Negligible (checks every 30 seconds)
- Memory: ~20 MB (Python script)
- Network: Minimal (only during upload)

### ROI
- **Manual process**: 5 minutes × 3 times/week = 15 min/week = **13 hours/year**
- **Automation cost**: 20 hours one-time
- **Break-even**: After ~18 months
- **Benefit**: Eliminates human error, more reliable, audit trail

---

## Alternative Approaches Considered

### 1. Scheduled Cron Job (Rejected)
**Why**: Requires files to be in place at exact time, doesn't handle late exports

### 2. Web-Based Drag & Drop with Auto-Detect (Rejected)
**Why**: Still requires manual browser action, not truly automated

### 3. Direct Newzware Integration (Rejected)
**Why**: Requires Newzware API access, too complex, vendor lock-in

### 4. Cloud-Based Processing (Rejected)
**Why**: Data must stay local, introduces security concerns, monthly costs

---

## Future Enhancements

### Phase 3 (Future)
- [ ] Web dashboard showing hotfolder status
- [ ] Real-time notifications via Slack/Teams
- [ ] Automatic retry on publication days only
- [ ] Historical processing analytics
- [ ] Predictive upload timing ("File expected in 10 minutes")

### Phase 4 (Advanced)
- [ ] Machine learning for file validation anomalies
- [ ] Integration with Google Analytics API
- [ ] Automated report generation and email
- [ ] Mobile app notifications

---

## Rollout Plan

### Week 1: Development & Testing
- Build Mac-based watcher
- Test with sample data
- Run alongside manual process (parallel)

### Week 2: Production Trial
- Use hotfolder for all uploads
- Monitor closely
- Keep manual backup ready

### Week 3: Full Deployment
- Migrate to NAS-based watcher
- Disable manual upload interface (optional)
- Document for team

### Week 4: Optimization
- Tune timing parameters
- Refine notifications
- Add monitoring dashboard

---

## Success Criteria

**Phase 1 Complete When:**
- ✅ Can drop 3 files and see automatic processing
- ✅ Errors caught and reported
- ✅ Files archived correctly
- ✅ Database updated with new data

**Phase 2 Complete When:**
- ✅ Runs on NAS 24/7
- ✅ Processed 3+ publication cycles without issues
- ✅ Error notifications working
- ✅ Mac no longer required

**Overall Success:**
- ✅ Zero manual uploads for 1 month
- ✅ 100% processing success rate
- ✅ No missed publication days
- ✅ Audit trail for all uploads

---

## Questions to Resolve

### Before Starting Implementation:

1. **Email Setup**:
   - What email address for notifications?
   - SMTP server available? (Gmail, Office365, local?)
   - Should we send success notifications or only errors?

2. **NAS Access**:
   - Preferred location for hotfolder? (/volume1/circulation/hotfolder/ OK?)
   - Need to create new user for hotfolder script?
   - Python 3 already installed on NAS?

3. **Newzware Workflow**:
   - Can you do a "Save As" directly to network share?
   - Or will you export to desktop first, then drag to hotfolder?
   - Any Newzware scheduled export capability?

---

## Appendix: Quick Start Commands

### Create Hotfolder Structure
```bash
mkdir -p /volume1/circulation/hotfolder/{incoming,incoming/.processing,incoming/.failed,archive,logs,config}
chmod 755 /volume1/circulation/hotfolder
```

### Mount NAS Share on Mac
```bash
# Create mount point
mkdir -p /Volumes/circulation

# Mount share
mount -t smbfs //it@192.168.1.254/circulation /Volumes/circulation

# Or add to fstab for auto-mount
```

### Run Watcher Manually (Mac)
```bash
cd /path/to/hotfolder_watcher
python3 hotfolder_watcher.py --config config/settings.json
```

### Install as Service (Mac)
```bash
# Copy launchd plist
cp com.circulation.hotfolder.plist ~/Library/LaunchAgents/

# Load service
launchctl load ~/Library/LaunchAgents/com.circulation.hotfolder.plist

# Check status
launchctl list | grep circulation
```

### Install as Cron (NAS)
```bash
# Edit crontab
crontab -e

# Add line to run every minute
* * * * * /usr/bin/python3 /volume1/circulation/hotfolder/hotfolder_watcher.py

# View cron logs
tail -f /var/log/cron.log
```

---

## Document History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2025-12-01 | Initial design | Claude Code |

---

**Next Step**: Review this design and answer the 3 questions above, then proceed with Phase 1 implementation.
