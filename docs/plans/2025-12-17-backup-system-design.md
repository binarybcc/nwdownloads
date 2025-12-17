# Backup System Design
**Created:** 2025-12-17
**Status:** Approved
**Implementation:** Parallel development (separate from Phase 1 automated file processing)

## Overview

Create an automated backup system for the Circulation Dashboard to protect against data loss and code corruption. This system was motivated by a recent incident where incomplete test data wiped the production database, highlighting the need for reliable backups and easy restore capabilities.

## Requirements Summary

**What We're Protecting:**
- Database: MariaDB `circulation_dashboard` with ~8 tables and growing data
- Code: Web application files in `/volume1/web/circulation/` (PHP, HTML, JS, CSS, config)

**What We're NOT Backing Up:**
- CSV source files in `/volume1/homes/newzware/inbox/processing/completed/failed/`
- Rationale: These are already in a safe location outside the web directory and not at risk from deployments or code changes

**Backup Schedule:**
- **Sunday 11:30 PM** - Pre-import safety net (30-min buffer before Monday 00:03 AM import)
- **Wednesday 00:30 AM** - Post-import verification
- **Friday 00:30 AM** - Mid-week changes

**Retention Policy:**
- Keep 3 most recent backups (backup-1, backup-2, backup-3)
- Simple rotation: newest becomes backup-1, oldest backup-3 is deleted

**Restore Capabilities:**
- Granular restore: Database only, Code only, or Full system
- GUI-based restore with strong confirmation dialogs
- Pre-restore snapshots for safety

---

## System Architecture & Directory Structure

**Backup Location:** `/volume1/homes/newzware/backup/`

```
/volume1/homes/newzware/
├── inbox/          (existing - SFTP arrival folder)
├── processing/     (existing - files being processed)
├── completed/      (existing - successful imports)
├── failed/         (existing - failed imports)
└── backup/         (NEW)
    ├── backup-1/   (most recent - Friday or Wednesday)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    ├── backup-2/   (middle backup)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    ├── backup-3/   (oldest backup)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    └── logs/
        ├── backup-2025-12-15-233000.log
        ├── backup-2025-12-13-003000.log
        └── backup-2025-12-11-003000.log
```

**Rotation Logic:**

Each backup run (Sun 11:30 PM, Wed 00:30 AM, Fri 00:30 AM):
1. Delete `backup-3/` if it exists
2. Rename `backup-2/` → `backup-3/`
3. Rename `backup-1/` → `backup-2/`
4. Create new `backup-1/` directory
5. Perform database and code backups into `backup-1/`

**What Gets Backed Up:**

- **database/**: Full MariaDB dump of `circulation_dashboard` database (gzipped)
- **code/**: Complete archive of `/volume1/web/circulation/` directory (all files, preserving structure)

This structure allows granular restores - you can restore just database, just code, or everything.

---

## Backup Scripts & Automation

**Main Backup Script:** `/volume1/homes/it/scripts/backup-circulation.sh`

**Script Operations:**

1. **Pre-backup validation:**
   - Check disk space (require at least 5GB free)
   - Verify MariaDB is accessible
   - Create timestamped log file in `logs/backup-YYYY-MM-DD-HHMMSS.log`

2. **Perform rotation:**
   ```bash
   BACKUP_DIR="/volume1/homes/newzware/backup"

   # Rotate backups
   rm -rf "$BACKUP_DIR/backup-3"
   mv "$BACKUP_DIR/backup-2" "$BACKUP_DIR/backup-3"
   mv "$BACKUP_DIR/backup-1" "$BACKUP_DIR/backup-2"
   mkdir -p "$BACKUP_DIR/backup-1/database"
   mkdir -p "$BACKUP_DIR/backup-1/code"
   ```

3. **Database backup:**
   ```bash
   mysqldump -uroot -p'P@ta675N0id' \
     -S /run/mysqld/mysqld10.sock \
     circulation_dashboard | gzip > \
     "$BACKUP_DIR/backup-1/database/circulation_dashboard.sql.gz"
   ```

4. **Code backup:**
   ```bash
   tar czf "$BACKUP_DIR/backup-1/code/web-files.tar.gz" \
     -C /volume1/web circulation/
   ```

5. **Validation:**
   - Test database archive: `gunzip -t circulation_dashboard.sql.gz`
   - Test code archive: `tar tzf web-files.tar.gz`
   - Record backup sizes and MD5 checksums
   - Count database tables (expect ~8 tables)

6. **Notification:**
   - **On success**: Log details to file (no email)
   - **On failure**: Log error + send email alert using existing Settings email config

**Cron Setup (Synology Task Scheduler):**

- **Task name:** "Circulation Dashboard Backup"
- **User:** `root` (needs MariaDB access)
- **Schedule:** Custom schedule
  - Sunday: 23:30 (11:30 PM)
  - Wednesday: 00:30 (12:30 AM)
  - Friday: 00:30 (12:30 AM)
- **Command:** `/volume1/homes/it/scripts/backup-circulation.sh`
- **Send run details:** On errors only

---

## Restore Scripts & GUI Execution

**Three restore scripts in `/volume1/homes/it/scripts/`:**

### 1. restore-database.sh

**Usage:** `restore-database.sh <backup-number>`

**Purpose:** Restores database from specified backup (1, 2, or 3)

**Operations:**
1. Validate backup exists and .sql.gz file is readable
2. Verify MariaDB is running
3. Create pre-restore snapshot (emergency rollback)
4. Drop existing `circulation_dashboard` database
5. Create fresh `circulation_dashboard` database
6. Restore from `backup-X/database/circulation_dashboard.sql.gz`
7. Verify table count matches expected (~8 tables)
8. Log restore operation with before/after record counts

### 2. restore-code.sh

**Usage:** `restore-code.sh <backup-number>`

**Purpose:** Restores web application files from specified backup

**Operations:**
1. Validate backup exists and .tar.gz file is readable
2. Create timestamped backup of current code (safety net)
3. Extract `backup-X/code/web-files.tar.gz` to temporary directory
4. Verify extraction succeeded and file count is reasonable
5. Replace `/volume1/web/circulation/` with backup files
6. Fix file permissions (644 for files, 755 for directories)
7. Verify site loads (curl test to index.php)
8. Log restore operation

### 3. restore-full.sh

**Usage:** `restore-full.sh <backup-number>`

**Purpose:** Restores entire system (database + code) from specified backup

**Operations:**
1. Validate backup exists
2. Run `restore-database.sh <backup-number>`
3. Run `restore-code.sh <backup-number>`
4. Verify both completed successfully
5. Log full system restore

### GUI Execution with Security

**CRITICAL SECURITY NOTE:** The GUI must validate all inputs before passing to shell scripts to prevent command injection.

**PHP Implementation (in settings.php):**

```php
<?php
// settings.php - Restore handler

// SECURITY: Strict input validation
$backup_number = $_POST['backup_number'] ?? '';
$restore_type = $_POST['restore_type'] ?? '';
$confirmation = $_POST['confirmation'] ?? '';

// VALIDATE: Backup number must be 1, 2, or 3 (whitelist)
if (!in_array($backup_number, ['1', '2', '3'], true)) {
    die('Invalid backup number. Must be 1, 2, or 3.');
}

// VALIDATE: Restore type must be exact match (whitelist)
$valid_types = ['database', 'code', 'full'];
if (!in_array($restore_type, $valid_types, true)) {
    die('Invalid restore type. Must be database, code, or full.');
}

// VALIDATE: User typed "CONFIRM" exactly
if ($confirmation !== 'CONFIRM') {
    die('Confirmation text must be exactly "CONFIRM"');
}

// SECURITY: Use escapeshellarg() even though input is validated
// This provides defense-in-depth
$safe_backup_number = escapeshellarg($backup_number);

// Build command with validated, escaped inputs
$script_map = [
    'database' => '/volume1/homes/it/scripts/restore-database.sh',
    'code' => '/volume1/homes/it/scripts/restore-code.sh',
    'full' => '/volume1/homes/it/scripts/restore-full.sh'
];

$script_path = $script_map[$restore_type];
$command = escapeshellcmd($script_path) . ' ' . $safe_backup_number;

// Execute and capture output
$output = [];
$return_code = 0;
exec($command . ' 2>&1', $output, $return_code);

// Return result to user
if ($return_code === 0) {
    echo "✓ Restore completed successfully\n";
    echo implode("\n", $output);
} else {
    echo "✗ Restore failed\n";
    echo implode("\n", $output);
}
?>
```

**Security Layers:**

1. **Input Whitelisting**: Only accept 1, 2, 3 for backup number
2. **Type Whitelisting**: Only accept database, code, full for restore type
3. **Confirmation Requirement**: Must type "CONFIRM" exactly
4. **escapeshellarg()**: Escape the backup number (defense-in-depth)
5. **escapeshellcmd()**: Escape the script path (defense-in-depth)
6. **No String Concatenation**: Use array mapping for script paths
7. **Admin Authentication**: Requires login to access Settings page

**GUI Execution Flow:**

1. **User selects options** in Settings panel:
   - Which backup to restore from (backup-1, backup-2, or backup-3)
   - What to restore (Database / Code / Full System)

2. **GUI shows detailed confirmation dialog:**
   ```
   ⚠️ WARNING: Restore Database from backup-1?

   Backup Details:
   - Created: 2025-12-15 11:30 PM
   - Database size: 2.4 MB
   - Tables: 8 tables
   - Records: ~7,600 snapshots

   Current Database:
   - Last modified: 2025-12-17 10:23 AM
   - Records: ~15 snapshots

   THIS WILL DELETE ALL CURRENT DATA
   Type "CONFIRM" to proceed: [________]

   [Cancel] [Restore Database]
   ```

3. **User types "CONFIRM"** (prevents accidental clicks)

4. **PHP validates all inputs** (whitelist validation)

5. **GUI executes restore script** via validated exec() call

6. **GUI shows result:**
   - Success message with verification details
   - Or error message with troubleshooting steps
   - Link to restore log file

**Key Safety Features:**
- Strict input validation (whitelist only)
- Multiple security layers (defense-in-depth)
- Require typing "CONFIRM" (not just clicking)
- Show before/after comparison
- Admin authentication required
- Pre-restore snapshots created by scripts
- Verbose logging of all operations
- Rollback capability if restore fails

---

## Settings Panel UI Integration

**Location:** Add new "Backup & Restore" tab/section to existing `/web/settings.php`

**UI Layout:**

```
┌─────────────────────────────────────────────────────────────┐
│ Settings - Backup & Restore                                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ 📊 BACKUP STATUS                                            │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Next Backup: Sunday 11:30 PM (in 3 days)               │ │
│ │ Last Backup: Friday 00:30 AM (Success ✓)               │ │
│ │ Disk Space: 45.2 GB free                                │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ 📦 AVAILABLE BACKUPS                                        │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ backup-1: Dec 15, 2025 11:30 PM                        │ │
│ │   Database: 2.4 MB | Code: 15.3 MB | Age: 2 days      │ │
│ │                                                         │ │
│ │ backup-2: Dec 13, 2025 00:30 AM                        │ │
│ │   Database: 2.3 MB | Code: 15.1 MB | Age: 4 days      │ │
│ │                                                         │ │
│ │ backup-3: Dec 11, 2025 00:30 AM                        │ │
│ │   Database: 2.2 MB | Code: 14.9 MB | Age: 6 days      │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ 🔄 RESTORE SYSTEM                                           │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Select Backup:  [ backup-1 ▼ ]                         │ │
│ │ Restore Type:   [ Database ▼ ] (Database/Code/Full)    │ │
│ │                                                         │ │
│ │ [Preview Restore Details]  [Restore System]            │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ 📋 RECENT BACKUP LOG (Last 10 Backups)                     │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 2025-12-15 11:30 PM - Backup successful (17.7 MB)     │ │
│ │ 2025-12-13 00:30 AM - Backup successful (17.4 MB)     │ │
│ │ 2025-12-11 00:30 AM - Backup successful (17.1 MB)     │ │
│ │ 2025-12-09 11:30 PM - Backup successful (16.9 MB)     │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

**Data Sources (PHP):**

```php
// Read backup directory structure
$backups = [];
for ($i = 1; $i <= 3; $i++) {
    $backup_dir = "/volume1/homes/newzware/backup/backup-$i";
    if (is_dir($backup_dir)) {
        $db_file = "$backup_dir/database/circulation_dashboard.sql.gz";
        $code_file = "$backup_dir/code/web-files.tar.gz";

        $backups[$i] = [
            'exists' => true,
            'database_size' => file_exists($db_file) ? filesize($db_file) : 0,
            'code_size' => file_exists($code_file) ? filesize($code_file) : 0,
            'timestamp' => filemtime($backup_dir),
            'age_days' => floor((time() - filemtime($backup_dir)) / 86400)
        ];
    }
}

// Parse recent log files
$log_dir = "/volume1/homes/newzware/backup/logs";
$logs = glob("$log_dir/backup-*.log");
rsort($logs); // Most recent first
$recent_logs = array_slice($logs, 0, 10);

// Calculate next backup time based on cron schedule
// Sun 23:30, Wed 00:30, Fri 00:30
function calculateNextBackupTime() {
    $now = time();
    $day_of_week = (int)date('w', $now); // 0=Sunday, 3=Wednesday, 5=Friday

    // Define backup times for each day
    $backup_times = [
        0 => strtotime('next Sunday 23:30'), // Sunday 11:30 PM
        3 => strtotime('next Wednesday 00:30'), // Wednesday 12:30 AM
        5 => strtotime('next Friday 00:30') // Friday 12:30 AM
    ];

    // Find next scheduled backup
    $next = null;
    foreach ($backup_times as $day => $timestamp) {
        if ($timestamp > $now && ($next === null || $timestamp < $next)) {
            $next = $timestamp;
        }
    }

    return $next;
}
```

**Integration with Existing Settings:**
- Uses same authentication (must be logged in as admin)
- Follows existing Settings page design patterns (tabs, cards, forms)
- Shares email configuration for failure notifications
- Consistent styling with rest of application

---

## Error Handling, Validation & Notifications

### Backup Failure Scenarios & Handling

**1. Disk Space Exhaustion**
- **Detection:** Check free space before backup (require 5GB minimum)
- **Action:** Skip backup, keep existing backups, send email alert
- **Log Message:** "Backup skipped: Disk space low (2.1 GB free, need 5 GB)"
- **Recovery:** Admin deletes old files or adds storage

**2. Database Dump Fails**
- **Detection:** `mysqldump` returns non-zero exit code
- **Action:** Log error with full mysqldump output, keep previous backups, send email alert
- **Log Message:** "Database backup failed: [mysqldump error message]"
- **Recovery:** Check MariaDB status, review error log, verify credentials

**3. File Archive Fails**
- **Detection:** `tar` command fails or produces invalid archive
- **Action:** Log error, validate with `tar tzf`, retry once
- **Log Message:** "Code backup failed: [tar error message]"
- **Recovery:** Check file permissions, disk errors, file locks

**4. Backup Validation Fails**
- **Detection:** Cannot decompress .sql.gz or extract .tar.gz
- **Action:** Mark backup as invalid, keep previous backup, send email alert
- **Log Message:** "Backup validation failed: Archive appears corrupted"
- **Recovery:** Investigate corruption source, may need manual backup

**5. Rotation Fails**
- **Detection:** Cannot rename or delete backup directories
- **Action:** Log error, skip rotation but attempt new backup anyway
- **Log Message:** "Rotation failed: [permission/disk error]"
- **Recovery:** Check directory permissions and ownership

### Email Notifications

Uses existing email configuration from Settings panel (SMTP settings already configured).

**Success Notification (logged, no email sent):**
```
Subject: [INFO] Circulation Backup - Dec 15, 2025 11:30 PM
Body:
✓ Database backup: 2.4 MB (7,623 records, 8 tables)
✓ Code backup: 15.3 MB (142 files)
✓ Rotation completed successfully
✓ Validation passed: All archives verified
Duration: 12 seconds
Next backup: Wednesday, Dec 17, 2025 00:30 AM
```

**Failure Notification (email sent immediately):**
```
Subject: [ALERT] Circulation Backup FAILED - Dec 15, 2025 11:30 PM
Body:
✗ Backup failed: Disk space low (2.1 GB free, need 5 GB)

Status:
- Previous backups: Still available and valid
- System: Dashboard operational
- Action required: Free disk space or add storage

Details:
- Log file: /volume1/homes/newzware/backup/logs/backup-2025-12-15-233000.log
- Backup directory: /volume1/homes/newzware/backup/
- Current disk usage: 92.8 GB used, 2.1 GB free

Recommended Actions:
1. Delete old files from /volume1/homes/newzware/completed/
2. Remove unused Docker images/containers
3. Check for large log files
4. Add additional storage if needed

Next scheduled backup: Wednesday, Dec 17, 2025 00:30 AM
```

### Restore Validation

**Pre-restore checks (before destructive operations):**
1. Verify backup files exist and are readable
2. Test archive integrity (`gunzip -t` and `tar tzf`)
3. Check database connectivity
4. Ensure sufficient disk space for extraction
5. Create pre-restore snapshot (safety net)
6. Verify user has admin permissions

**Post-restore verification:**
1. Database: Count tables, verify record counts are reasonable
2. Code: Verify site loads (HTTP 200 response)
3. Check file permissions are correct
4. Log all operations with timestamps for audit trail

### Logging Strategy

**Backup logs:** `/volume1/homes/newzware/backup/logs/backup-YYYY-MM-DD-HHMMSS.log`

Format:
```
[2025-12-15 23:30:00] Starting backup process
[2025-12-15 23:30:01] Checking disk space: 45.2 GB free ✓
[2025-12-15 23:30:01] Rotating backups...
[2025-12-15 23:30:01]   - Deleted backup-3/
[2025-12-15 23:30:01]   - Renamed backup-2/ → backup-3/
[2025-12-15 23:30:01]   - Renamed backup-1/ → backup-2/
[2025-12-15 23:30:01]   - Created backup-1/
[2025-12-15 23:30:02] Backing up database...
[2025-12-15 23:30:08]   - Database dump: 2.4 MB (7,623 records, 8 tables) ✓
[2025-12-15 23:30:08] Backing up code...
[2025-12-15 23:30:11]   - Code archive: 15.3 MB (142 files) ✓
[2025-12-15 23:30:11] Validating backups...
[2025-12-15 23:30:12]   - Database archive valid ✓
[2025-12-15 23:30:12]   - Code archive valid ✓
[2025-12-15 23:30:12] Backup completed successfully (12 seconds)
```

**Restore logs:** `/volume1/homes/newzware/backup/logs/restore-YYYY-MM-DD-HHMMSS.log`

Format:
```
[2025-12-17 10:45:00] Starting database restore from backup-1
[2025-12-17 10:45:00] User: admin (via Settings GUI)
[2025-12-17 10:45:01] Pre-restore validation...
[2025-12-17 10:45:01]   - Backup exists ✓
[2025-12-17 10:45:01]   - Archive valid ✓
[2025-12-17 10:45:01]   - Database accessible ✓
[2025-12-17 10:45:02] Creating pre-restore snapshot...
[2025-12-17 10:45:03]   - Snapshot saved: /tmp/pre-restore-20251217-104502.sql.gz
[2025-12-17 10:45:03] Dropping existing database...
[2025-12-17 10:45:04] Restoring from backup...
[2025-12-17 10:45:15]   - Database restored (7,623 records, 8 tables) ✓
[2025-12-17 10:45:15] Post-restore verification...
[2025-12-17 10:45:16]   - Table count: 8 ✓
[2025-12-17 10:45:16]   - Record count: 7,623 ✓
[2025-12-17 10:45:16] Restore completed successfully (16 seconds)
```

---

## Implementation Notes

### File Permissions

All scripts must be executable by root:
```bash
chmod 755 /volume1/homes/it/scripts/backup-circulation.sh
chmod 755 /volume1/homes/it/scripts/restore-database.sh
chmod 755 /volume1/homes/it/scripts/restore-code.sh
chmod 755 /volume1/homes/it/scripts/restore-full.sh
```

Backup directory ownership:
```bash
chown -R it:users /volume1/homes/newzware/backup/
chmod 755 /volume1/homes/newzware/backup/
```

### Dependencies

**Required commands:**
- `mysqldump` - MariaDB backup utility
- `mysql` - MariaDB restore utility
- `tar` - File archiving
- `gzip`/`gunzip` - Compression
- `md5sum` - Checksum validation

**PHP requirements:**
- `exec()` function enabled (not disabled in php.ini)
- Sufficient execution time (set `max_execution_time = 300` for restore operations)
- `escapeshellarg()` and `escapeshellcmd()` functions available

### Security Checklist

**Before Production Deployment:**
1. ✓ Validate all user inputs with whitelists
2. ✓ Use `escapeshellarg()` on all dynamic parameters
3. ✓ Use `escapeshellcmd()` on script paths
4. ✓ Require admin authentication for Settings page
5. ✓ Require typing "CONFIRM" for destructive operations
6. ✓ Never trust user input - validate everything
7. ✓ Use defense-in-depth (multiple security layers)
8. ✓ Log all restore operations with user info
9. ✓ Test command injection attacks in development
10. ✓ Review PHP security best practices

### Testing Checklist

**Before Production Deployment:**
1. ✓ Test backup script manually (all 3 rotations)
2. ✓ Verify backup archives are valid (can decompress/extract)
3. ✓ Test restore-database.sh on development
4. ✓ Test restore-code.sh on development
5. ✓ Test restore-full.sh on development
6. ✓ Verify Settings UI displays backup status correctly
7. ✓ Test GUI restore workflow (with confirmation)
8. ✓ Test input validation (try injecting invalid backup numbers)
9. ✓ Test email notifications (success and failure)
10. ✓ Verify cron schedule triggers at correct times
11. ✓ Test disk space failure scenario
12. ✓ Attempt command injection attacks (should be blocked)

### Deployment Strategy

**Phase 1: Setup & Testing**
1. Create backup directory structure
2. Deploy backup scripts with proper permissions
3. Test manual backup execution
4. Verify backups are valid and can be extracted

**Phase 2: Automation**
1. Configure cron schedule in Synology Task Scheduler
2. Wait for first automated backup
3. Verify success in logs
4. Monitor for 1 week (3 backup cycles)

**Phase 3: Restore Capability**
1. Deploy restore scripts with proper permissions
2. Test restore on development environment
3. Add Settings UI for backup management
4. Implement input validation and security measures
5. Test full restore workflow from GUI
6. Verify command injection protection

**Phase 4: Monitoring**
1. Configure email notifications
2. Test failure scenarios
3. Document restore procedures for team
4. Schedule quarterly restore drills (verify backups are usable)

### Future Enhancements (Post-MVP)

**Nice-to-Have Features:**
- Off-site backup copy (to different NAS volume or cloud storage)
- Backup before every automated import (not just weekly schedule)
- Database integrity checks as part of backup validation
- Automated restore testing (periodic test restores to verify backups)
- Backup encryption (if compliance requires it)
- Backup retention policy configuration in Settings UI
- Slack/Discord notifications in addition to email
- Historical backup size trending chart
- Backup compression level configuration
- Differential/incremental backups for large databases

---

## Summary

This backup system provides:
- ✅ **Automated protection** against data loss and code corruption
- ✅ **Simple 3-copy rotation** with predictable Monday/Wednesday/Friday schedule
- ✅ **Granular restore** options (database, code, or full system)
- ✅ **GUI-based management** with safety confirmations
- ✅ **Strong security** with input validation and defense-in-depth
- ✅ **Email alerts** on failures
- ✅ **Comprehensive logging** for audit trail
- ✅ **Pre-restore snapshots** for emergency rollback

The system is designed to be:
- **Reliable:** Multiple validation checks, tested archives
- **Safe:** Strong confirmations, pre-restore snapshots, detailed logging
- **Secure:** Input validation, command injection protection, multiple security layers
- **Maintainable:** Self-contained in GUI, clear error messages, comprehensive logs
- **Scalable:** Grows with database size, configurable retention policy

This design addresses the immediate need (protection against data loss incidents) while providing a foundation for future enhancements.
