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
- Rationale: Already in safe location outside web directory, not at risk from deployments

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
├── inbox/          (existing)
├── processing/     (existing)
├── completed/      (existing)
├── failed/         (existing)
└── backup/         (NEW)
    ├── backup-1/   (most recent)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    ├── backup-2/   (middle)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    ├── backup-3/   (oldest)
    │   ├── database/
    │   │   └── circulation_dashboard.sql.gz
    │   └── code/
    │       └── web-files.tar.gz
    └── logs/
        └── backup-YYYY-MM-DD-HHMMSS.log
```

**Rotation Logic:**
1. Delete `backup-3/` if exists
2. Rename `backup-2/` → `backup-3/`
3. Rename `backup-1/` → `backup-2/`
4. Create new `backup-1/` directory
5. Perform database and code backups into `backup-1/`

---

## Backup Scripts & Automation

**Main Script:** `/volume1/homes/it/scripts/backup-circulation.sh`

**Operations:**
1. Pre-flight checks (disk space ≥5GB, database accessible)
2. Rotation (delete backup-3, shift 2→3, 1→2, create new 1)
3. Database backup (`mysqldump | gzip`)
4. Code backup (`tar czf` of `/volume1/web/circulation/`)
5. Validation (`gunzip -t`, `tar tzf`)
6. Logging to `logs/backup-YYYY-MM-DD-HHMMSS.log`
7. Email notification on failure

**Cron Schedule (Synology Task Scheduler):**
- Task: "Circulation Dashboard Backup"
- User: root
- Schedule: Sun 23:30, Wed 00:30, Fri 00:30
- Command: `/volume1/homes/it/scripts/backup-circulation.sh`

---

## Restore Scripts

**Three restore scripts:**

### 1. restore-database.sh
- Validates backup number (1, 2, or 3)
- Creates pre-restore snapshot for safety
- Drops existing database
- Restores from backup
- Verifies table/record counts

### 2. restore-code.sh  
- Validates backup number
- Creates safety backup of current code
- Extracts to temp directory
- Replaces production files (preserving .htaccess, .build_number)
- Fixes permissions (644 files, 755 dirs)
- Verifies site loads

### 3. restore-full.sh
- Runs restore-database.sh
- Runs restore-code.sh
- Logs full system restore

---

## Settings Panel UI

**Location:** New "Backup & Restore" tab in `web/settings.php`

**Components:**

1. **Backup Status Card**
   - Next backup time
   - Last backup timestamp
   - Free disk space

2. **Available Backups Card**
   - Lists backup-1, backup-2, backup-3
   - Shows timestamp, sizes, age

3. **Restore System Card**
   - Dropdown: Select backup (1, 2, 3)
   - Dropdown: Select type (Database, Code, Full)
   - Button: Preview Details
   - Button: Restore System (requires "CONFIRM")

4. **Recent Backup Log**
   - Last 10 backup runs
   - Success/failure status
   - Timestamps

**API Endpoints:**

- `api_backup_status.php` - Returns backup info (status, available backups, logs)
- `api_backup_restore.php` - Executes restore with security validation

---

## Security Model (6-Layer Defense-in-Depth)

**Critical:** PHP shell execution requires multiple validation layers to prevent command injection.

**Layer 1: Whitelist Validation - Backup Number**
```php
if (!in_array($backup_number, ['1', '2', '3'], true)) {
    throw new Exception('Invalid backup number');
}
```

**Layer 2: Whitelist Validation - Restore Type**
```php
$valid_types = ['database', 'code', 'full'];
if (!in_array($restore_type, $valid_types, true)) {
    throw new Exception('Invalid restore type');
}
```

**Layer 3: Confirmation Validation**
```php
if ($confirmation !== 'CONFIRM') {
    throw new Exception('Must type CONFIRM exactly');
}
```

**Layer 4: Array Mapping (No String Concatenation)**
```php
$script_map = [
    'database' => '/volume1/homes/it/scripts/restore-database.sh',
    'code' => '/volume1/homes/it/scripts/restore-code.sh',
    'full' => '/volume1/homes/it/scripts/restore-full.sh'
];
$script_path = $script_map[$restore_type];
```

**Layer 5: escapeshellarg() for Parameters**
```php
$safe_backup_number = escapeshellarg($backup_number);
```

**Layer 6: escapeshellcmd() for Script Path**
```php
$safe_script_path = escapeshellcmd($script_path);
```

**Additional Security:**
- Admin authentication required (Settings page login)
- Audit logging of all restore attempts
- No direct user input to shell
- All paths hardcoded or mapped

---

## Error Handling & Notifications

**Backup Failures:**
- Disk space low → Skip backup, keep existing, email alert
- Database dump fails → Log error, email alert
- Archive validation fails → Mark invalid, email alert
- Rotation fails → Log, attempt backup anyway

**Email Notifications:**
- Success: Logged only (no email)
- Failure: Immediate email with error, log location, next backup time

**Restore Validation:**
- Pre-restore: Verify backup exists, test integrity, check DB connectivity
- Post-restore: Count tables, verify record counts, test site loads

---

## Implementation Notes

**File Permissions:**
```bash
chmod 755 /volume1/homes/it/scripts/backup-circulation.sh
chmod 755 /volume1/homes/it/scripts/restore-*.sh
chown -R it:users /volume1/homes/newzware/backup/
```

**Dependencies:**
- mysqldump, mysql (MariaDB utilities)
- tar, gzip (archiving)
- md5sum (checksums)
- PHP with exec() enabled

**Testing Checklist:**
1. Manual backup execution (3 rotations)
2. Verify archives are valid
3. Test each restore script
4. Settings UI displays correctly
5. GUI restore workflow works
6. Email notifications send
7. Cron triggers on schedule

---

## Summary

**Protection Provided:**
- ✅ Database corruption/loss
- ✅ Code corruption from bad deployments
- ✅ Accidental data deletion
- ✅ Testing mistakes

**Restore Capabilities:**
- ✅ Database only (fast recovery)
- ✅ Code only (fast recovery)
- ✅ Full system (complete rollback)

**Automation:**
- ✅ Sun/Wed/Fri schedule
- ✅ 3-copy rotation
- ✅ Email alerts
- ✅ GUI management

**Security:**
- ✅ 6-layer validation
- ✅ No command injection risk
- ✅ Audit logging
- ✅ Admin-only access

This design addresses the immediate need (data loss protection) while providing foundation for future enhancements.
