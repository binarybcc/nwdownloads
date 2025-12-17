# Automated File Processing System Design

**Date:** December 16, 2025
**Status:** Approved Design
**Author:** Claude (in collaboration with John Corbin)

---

## Executive Summary

Replace manual CSV uploads with automated SFTP-based file processing system. Files arrive at `/volume1/homes/newzware/inbox/` via SFTP, are automatically processed every Monday at 00:03 AM, and moved to `completed/` or `failed/` folders based on results. Manual upload remains as backup/emergency option.

**Key Features:**
- ✅ Weekly automated processing (Monday 00:03 AM)
- ✅ Email alerts for failures, dashboard banners for success
- ✅ Configurable filename patterns via Settings page
- ✅ Full audit trail in database
- ✅ Backfill support for missing weeks
- ✅ Expandable processor and notification architecture
- ✅ Manual trigger option for ad-hoc processing

---

## 1. System Architecture Overview

**High-Level Flow:**

```
┌─────────────────┐
│ Newzware SFTP   │
│ → inbox/        │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Cron Job (00:03 Monday)             │
│ /volume1/web/circulation/           │
│   process-inbox.php                 │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 1. Scan inbox/ for CSV files        │
│ 2. Match filename → processor       │
│ 3. Move to processing/              │
│ 4. Execute processor                │
│ 5. Move to completed/ or failed/    │
│ 6. Log to database                  │
│ 7. Send notifications (multi-type)  │
└─────────────────────────────────────┘
```

**Expandability Architecture:**

### Processor Registry Pattern
- Processors auto-register themselves (plugin-like)
- Add new processor = drop new file in `/processors/` directory
- No changes to core orchestrator code required

### Database-Driven Configuration
- Filename patterns stored in database, not hardcoded
- Notification rules configurable per file type
- Validation rules configurable per processor

### Notification Plugin System
- Support for Email, Dashboard, Slack, SMS, Webhook
- Add new notifier = implement `INotifier` interface
- Configure which notifiers per file type in Settings

### Flexible Scheduling
- Multiple cron schedules supported
- Per-file-type processing schedules possible
- Ad-hoc manual trigger from Settings page

**Key Components:**

1. **`process-inbox.php`** - Main orchestrator script (called by cron)
2. **`FileProcessor` class** - Handles file detection, validation, routing
3. **`file_processing_log` table** - Audit trail of all processing attempts
4. **`file_processing_patterns` table** - Filename → processor mapping (editable in Settings)
5. **Settings page additions** - Configure patterns, email addresses, view processing history
6. **Email notification system** - Sends failure alerts
7. **Dashboard notification system** - Shows success banners

---

## 2. File Detection & Processing Logic

**Processor Registry:**

```php
// /web/processors/BaseProcessor.php
interface IFileProcessor {
    public function getName(): string;
    public function getDefaultPatterns(): array;
    public function validate(string $filepath): bool;
    public function process(string $filepath): ProcessResult;
}

// /web/processors/AllSubscriberProcessor.php
class AllSubscriberProcessor implements IFileProcessor {
    public function getDefaultPatterns(): array {
        return ['AllSubscriberReport*.csv'];
    }

    public function process(string $filepath): ProcessResult {
        // Calls existing upload.php logic
    }
}

// Auto-discovery: Scan /processors/ directory, instantiate all processors
```

**File Matching Logic:**

1. **Scan inbox/** for `*.csv` files
2. **Sort by filename timestamp** (newest first per type)
3. **Match against patterns** from database (or defaults if not configured)
4. **Process current + backfill files:**
   - **Current week file:** Process newest file for current week
   - **Backfill files:** Process older files (6-8 days old) representing previous weeks
     - Check if week already has data in database
     - If missing → process as backfill (using existing backfill logic)
     - If exists → skip with "already processed" note
   - **True duplicates:** Skip files representing same week as already processed
   - **Age cutoff:** Files older than 60 days → move to failed/ with "too old" note
5. **Validate file** (processor-specific rules):
   - Size check (< 10MB)
   - Required columns present
   - **Complete data required** (all expected papers present)
     - Newzware always sends complete files
     - Partial files = validation failure

**Example Processing Run:**

```
inbox/ contains:
- AllSubscriberReport20251216120000.csv (Dec 16, week 50) → PROCESS (current)
- AllSubscriberReport20251209120000.csv (Dec 9, week 49)  → CHECK if week 49 exists
  - If missing → PROCESS (backfill)
  - If exists → SKIP "already processed"
- AllSubscriberReport20251202120000.csv (Dec 2, week 48)  → CHECK if week 48 exists
  - If missing → PROCESS (backfill)
  - If exists → SKIP "already processed"
```

**Expandability:**
- Add new processor → drop file in `/processors/`
- System auto-discovers on next run
- Configure custom patterns in Settings if needed
- Override validation rules per processor

---

## 3. Database Schema

**New Tables:**

```sql
-- Audit trail of all processing attempts
CREATE TABLE file_processing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,              -- 'allsubscriber', 'vacation', 'renewals'
    processor_class VARCHAR(100) NOT NULL,       -- 'AllSubscriberProcessor'
    status ENUM('processing', 'completed', 'failed', 'skipped') NOT NULL,
    records_processed INT DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    processing_duration_seconds DECIMAL(10,2) NULL,
    file_size_bytes INT NULL,
    file_moved_to VARCHAR(255) NULL,            -- 'completed/', 'failed/'
    is_backfill BOOLEAN DEFAULT FALSE,
    backfill_weeks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filename (filename),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
);

-- Configurable filename patterns (Settings page)
CREATE TABLE file_processing_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(255) NOT NULL,               -- 'AllSubscriberReport*.csv'
    processor_class VARCHAR(100) NOT NULL,       -- 'AllSubscriberProcessor'
    description TEXT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,            -- System defaults vs user-added
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pattern (pattern)
);

-- Settings additions (existing settings table or new config table)
-- Configuration keys:
-- - notification_emails: TEXT, comma-separated email addresses
-- - auto_processing_enabled: BOOLEAN, default TRUE
-- - max_file_age_days: INT, default 60
```

**Default Pattern Seeds:**

```sql
INSERT INTO file_processing_patterns (pattern, processor_class, description, is_default, enabled) VALUES
('AllSubscriberReport*.csv', 'AllSubscriberProcessor', 'Newzware All Subscriber Report (weekly circulation data)', TRUE, TRUE),
('SubscribersOnVacation*.csv', 'VacationProcessor', 'Newzware Subscribers On Vacation export', TRUE, TRUE),
('*Renewal*.csv', 'RenewalProcessor', 'Renewal and churn tracking data', TRUE, TRUE);
```

---

## 4. Settings Page Configuration

**Settings Page Additions:**

### 1. File Processing Configuration Section

```
┌─────────────────────────────────────────────┐
│ 📁 Automated File Processing                │
├─────────────────────────────────────────────┤
│ ☑ Enable automatic processing               │
│   Process files from SFTP inbox weekly      │
│                                             │
│ Next scheduled run: Monday, Dec 23 at 00:03 │
│                                             │
│ [▶ Run Now (Manual Trigger)]               │
└─────────────────────────────────────────────┘
```

### 2. Filename Pattern Configuration

```
┌─────────────────────────────────────────────┐
│ 📄 Filename Patterns                        │
├─────────────────────────────────────────────┤
│ Pattern                    │ Processor      │ Actions │
│ AllSubscriberReport*.csv   │ All Subscriber │ [Edit] [Delete] │
│ SubscribersOnVacation*.csv │ Vacation Data  │ [Edit] [Delete] │
│ *Renewal*.csv              │ Renewal/Churn  │ [Edit] [Delete] │
│                                                                │
│ [+ Add New Pattern]                                           │
└───────────────────────────────────────────────────────────────┘
```

**Pattern Edit Dialog:**
- Pattern (with wildcard support: `*`, `?`)
- Processor selection (dropdown of available processors)
- Description (for documentation)
- Enabled checkbox

### 3. Notification Settings

```
┌─────────────────────────────────────────────┐
│ 📧 Notification Settings                    │
├─────────────────────────────────────────────┤
│ Email addresses (comma-separated):          │
│ [john@upstatetoday.com, it@upstatetoday.com]│
│                                             │
│ ☑ Email on failure (errors/validation)     │
│ ☑ Dashboard banner on success              │
│                                             │
│ [Send Test Email]                           │
└─────────────────────────────────────────────┘
```

### 4. Processing History (Last 30 Days)

```
┌──────────────────────────────────────────────────────────────┐
│ 📊 Recent Processing History                                 │
├──────────────────────────────────────────────────────────────┤
│ Date       │ File                      │ Status    │ Records │
│ Dec 16     │ AllSubscriber...20251216  │ ✅ Success│ 8,625   │
│ Dec 16     │ Vacation...20251216       │ ✅ Success│ 142     │
│ Dec 9      │ AllSubscriber...20251209  │ ⚠ Skipped │ -       │
│ Dec 2      │ AllSubscriber...20251202  │ ❌ Failed │ -       │
│                                                               │
│ [View Full History] [Download Log CSV]                       │
└──────────────────────────────────────────────────────────────┘
```

**Processing History Features:**
- Click row to see full details (error messages, processing time, etc.)
- Filter by status, date range, file type
- Download as CSV for analysis
- Link to file in `completed/` or `failed/` folder

---

## 5. Notification System

**Notification Architecture (Expandable):**

```php
// /web/notifications/INotifier.php
interface INotifier {
    public function sendSuccess(ProcessResult $result): void;
    public function sendFailure(ProcessResult $result): void;
}

// /web/notifications/EmailNotifier.php
class EmailNotifier implements INotifier {
    public function sendFailure(ProcessResult $result): void {
        $to = $this->getEmailAddresses();
        $subject = "⚠️ File Processing Failed: {$result->filename}";
        $body = $this->buildFailureEmail($result);
        mail($to, $subject, $body);
    }

    // Success emails optional (dashboard is sufficient)
}

// /web/notifications/DashboardNotifier.php
class DashboardNotifier implements INotifier {
    public function sendSuccess(ProcessResult $result): void {
        // Store in session or database for banner display
        $_SESSION['processing_notifications'][] = [
            'type' => 'success',
            'message' => "✅ {$result->filename} processed successfully",
            'records' => $result->records_processed,
            'timestamp' => time()
        ];
    }
}

// Future expandability:
// - SlackNotifier (webhook integration)
// - SMSNotifier (Twilio integration)
// - WebhookNotifier (custom endpoints)
```

**Email Content Template (Failure):**

```
Subject: ⚠️ File Processing Failed: AllSubscriberReport20251216120000.csv

Automated file processing failed at 00:03 on December 16, 2025.

FILE DETAILS:
- Filename: AllSubscriberReport20251216120000.csv
- Type: All Subscriber Report
- Size: 2.4 MB
- Location: /volume1/homes/newzware/failed/

ERROR:
Missing required papers: TA (Michigan)
File appears incomplete. Expected 5 papers, found 4.

ACTION REQUIRED:
1. Check Newzware SFTP export settings
2. Verify complete file at source
3. Re-export and SFTP to inbox/ folder
   OR
4. Upload manually via dashboard: https://cdash.upstatetoday.com/upload.html

PROCESSING LOG:
View details: https://cdash.upstatetoday.com/settings.php#processing-history

---
This is an automated message from the Circulation Dashboard.
To update notification settings: https://cdash.upstatetoday.com/settings.php
```

**Dashboard Banner Template (Success):**

```html
<div class="notification-banner success">
    <div class="icon">✅</div>
    <div class="content">
        <strong>File processing completed successfully</strong>
        <ul>
            <li>AllSubscriberReport20251216120000.csv (8,625 records)</li>
            <li>SubscribersOnVacation20251216120000.csv (142 records)</li>
        </ul>
    </div>
    <div class="actions">
        <a href="/settings.php#processing-history">View Details</a>
        <button onclick="dismissNotification()">Dismiss</button>
    </div>
</div>
```

**Notification Rules:**

| Event | Email | Dashboard | When |
|-------|-------|-----------|------|
| Processing started | No | No | Too noisy |
| File validated | No | No | Too noisy |
| File processed (success) | No | Yes | Visual confirmation |
| File skipped (duplicate) | No | No | Normal operation |
| File failed (validation) | Yes | No | Immediate attention needed |
| File failed (processing) | Yes | No | Immediate attention needed |
| System error | Yes | No | Critical issue |

---

## 6. Cron Job Setup

**Synology Cron Configuration (Option 1: CLI):**

```bash
# SSH into NAS
ssh it@192.168.1.254

# Edit root crontab
sudo crontab -e

# Add this line (runs every Monday at 00:03 AM)
3 0 * * 1 /usr/bin/php /volume1/web/circulation/process-inbox.php >> /var/log/circulation/cron.log 2>&1

# Verify crontab
sudo crontab -l
```

**Alternative: Synology Task Scheduler (Option 2: GUI)**

```
1. Open DSM Control Panel
2. Navigate to: Task Scheduler
3. Click: Create > Scheduled Task > User-defined script

General Settings:
- Task name: "Process Circulation Files"
- User: root (or http if PHP has appropriate permissions)
- Enabled: ✓

Schedule Settings:
- Run on the following days: Monday
- First run time: 00:03
- Frequency: Every week
- Last run time: (leave blank for indefinite)

Task Settings:
- User-defined script:
  /usr/bin/php /volume1/web/circulation/process-inbox.php

- Send run details by email: (optional, your email)
- Send run details only when script terminates abnormally: ✓
```

**What process-inbox.php Does:**

1. **Initialize**
   - Load configuration from database/environment
   - Connect to database
   - Set up error logging
   - Check if auto-processing is enabled

2. **Scan inbox/**
   - Find all `*.csv` files
   - Get file metadata (size, timestamp, etc.)
   - Sort by filename timestamp

3. **Match patterns**
   - Load patterns from `file_processing_patterns` table
   - Match each file to appropriate processor
   - Prioritize by processor order

4. **Process files**
   - Move file to `processing/` folder
   - Log `processing` status to database
   - Execute processor
   - Handle success/failure

5. **Move files**
   - Success → move to `completed/`
   - Failure → move to `failed/`
   - Update database log with final status

6. **Send notifications**
   - Email for any failures
   - Dashboard banner for successes
   - Batch notifications (one email for all failures)

7. **Clean up**
   - Archive `completed/` files older than 90 days
   - Alert if `processing/` has stuck files (>24 hours)
   - Rotate log files

**Logging Strategy:**

| Log Type | Location | Purpose | Rotation |
|----------|----------|---------|----------|
| Cron output | `/var/log/circulation/cron.log` | Execution confirmation | Weekly |
| Processing details | Database `file_processing_log` | Audit trail | 90 days |
| PHP errors | `/volume1/web/circulation/error.log` | Debugging | Daily |
| Email notifications | Email archive | Failure history | Indefinite |

**Monitoring:**

- **Settings page** shows "Last run: Dec 16, 2025 at 00:03 AM (2 files processed)"
- **Failed runs** send email alert automatically
- **Processing history table** shows all recent activity with status indicators
- **Stuck files alert** if any file remains in `processing/` >24 hours

---

## 7. Error Handling & File Lifecycle

**File State Machine:**

```
inbox/
  ↓
[Validate] → FAIL → failed/ + email alert + log
  ↓ PASS
processing/
  ↓
[Process] → FAIL → failed/ + email alert + log + rollback
  ↓ SUCCESS
completed/ + dashboard banner + log
```

**Error Categories & Handling:**

### 1. VALIDATION_ERROR (Pre-Processing)

**Triggers:**
- File too large (>10MB)
- Missing required columns
- Incomplete data (missing papers)
- Invalid file format
- Corrupt/unreadable CSV

**Action:**
- Move to `failed/`
- Send email alert with validation error details
- Log to database with error message
- **Do NOT attempt processing**

### 2. PROCESSING_ERROR (During Import)

**Triggers:**
- Database connection failure
- SQL errors (constraint violations, etc.)
- Week data conflict
- PHP execution errors
- Memory/timeout issues

**Action:**
- Rollback database transaction
- Move to `failed/`
- Send email alert with technical error details
- Log full stack trace to database

### 3. SYSTEM_ERROR (Infrastructure)

**Triggers:**
- Out of disk space
- Permissions error (can't move file)
- PHP timeout
- Unable to send email

**Action:**
- Leave file in `processing/` (for retry)
- Send emergency email if possible
- Log critical error
- Next cron run will detect stuck file and alert

**Recovery Procedures:**

### Scenario 1: Failed File Needs Retry

```
Problem: File failed validation (incomplete data)

Steps:
1. Fix issue in Newzware (ensure complete export)
2. Re-export and SFTP corrected file to inbox/
3. Option A: Wait for next Monday 00:03 cron
   Option B: Use manual trigger in Settings page
4. System will process corrected file
5. Old failed file can be deleted from failed/
```

### Scenario 2: False Positive Failure

```
Problem: File marked as failed but is actually valid

Steps:
1. SSH into NAS: ssh it@192.168.1.254
2. Move file back: mv /volume1/homes/newzware/failed/[filename] /volume1/homes/newzware/inbox/
3. Option A: Wait for next Monday 00:03 cron
   Option B: Use manual trigger in Settings page
4. Review validation rules in Settings to prevent recurrence
```

### Scenario 3: Emergency Processing Needed

```
Problem: Can't wait until Monday, need immediate processing

Steps:
Option A (Recommended): Manual Upload
1. Download file from failed/ or inbox/
2. Upload via https://cdash.upstatetoday.com/upload.html
3. Immediate processing, bypasses automation

Option B: Manual Trigger
1. SSH to NAS or access Settings page
2. Click "Run Now (Manual Trigger)" button
3. Processes all files in inbox/ immediately
```

### Scenario 4: Backfill Multiple Missing Weeks

```
Problem: Several weeks of data missing, multiple files in inbox/

Steps:
1. SFTP all missing week files to inbox/
2. Files will be processed oldest-to-newest (by week, not filename)
3. Existing weeks will be skipped
4. Missing weeks will be processed as backfill
5. Review processing history to confirm all weeks imported
```

**File Retention Policy:**

| Folder | Retention | Archive Location | Notes |
|--------|-----------|------------------|-------|
| `inbox/` | 0 days | N/A | Cleared immediately after processing |
| `processing/` | 0 days | N/A | Should be empty; files >24h = alert |
| `completed/` | 90 days | `/archive/YYYY/` | Automatic archive on day 91 |
| `failed/` | Indefinite | Manual review | Requires manual cleanup |
| `/archive/` | Indefinite | N/A | Long-term storage for audit |

**Stuck File Detection:**

```php
// In process-inbox.php
$stuck_files = scandir('/volume1/homes/newzware/processing/');
foreach ($stuck_files as $file) {
    $age_hours = (time() - filemtime($file)) / 3600;
    if ($age_hours > 24) {
        // Send alert email
        $this->emailNotifier->sendAlert(
            "⚠️ Stuck file detected: {$file} (in processing/ for {$age_hours} hours)"
        );
    }
}
```

---

## 8. Implementation Phases

### Phase 1: Core Infrastructure (Week 1)

**Deliverables:**
- [ ] Database schema (tables created)
- [ ] `process-inbox.php` orchestrator
- [ ] `IFileProcessor` interface
- [ ] `AllSubscriberProcessor` (wraps existing upload.php logic)
- [ ] `file_processing_log` logging
- [ ] Basic file movement (inbox → processing → completed/failed)

**Testing:**
- Manual trigger via CLI
- Single file processing
- Verify logging

### Phase 2: Notification System (Week 1-2)

**Deliverables:**
- [ ] `INotifier` interface
- [ ] `EmailNotifier` implementation
- [ ] `DashboardNotifier` implementation
- [ ] Email templates
- [ ] Dashboard banner component

**Testing:**
- Send test emails
- Verify dashboard banners display correctly
- Test notification dismissal

### Phase 3: Settings Page UI (Week 2)

**Deliverables:**
- [ ] File processing configuration section
- [ ] Filename pattern CRUD interface
- [ ] Notification settings form
- [ ] Processing history table
- [ ] Manual trigger button

**Testing:**
- Add/edit/delete patterns
- Update email addresses
- View processing history
- Manual trigger execution

### Phase 4: Additional Processors (Week 2-3)

**Deliverables:**
- [ ] `VacationProcessor` (wraps upload_vacations.php)
- [ ] `RenewalProcessor` (wraps upload_renewals.php)
- [ ] Processor auto-discovery
- [ ] Default pattern seeds

**Testing:**
- Process vacation files
- Process renewal files
- Verify all three processors work in single cron run

### Phase 5: Cron Setup & Production Testing (Week 3)

**Deliverables:**
- [ ] Synology cron job configured
- [ ] Log rotation setup
- [ ] File retention/archival script
- [ ] Stuck file detection
- [ ] Production documentation

**Testing:**
- Full end-to-end cron execution
- Multiple file types in single run
- Backfill file processing
- Error scenarios (missing papers, corrupt files)
- Email delivery verification

### Phase 6: Monitoring & Optimization (Week 4)

**Deliverables:**
- [ ] Processing history analytics
- [ ] Performance optimization
- [ ] Error pattern analysis
- [ ] Documentation updates

**Testing:**
- Load testing (large files)
- Edge cases (empty inbox, all failures, etc.)
- Recovery procedures verification

---

## 9. Success Criteria

**System is considered successful when:**

1. ✅ **Automated processing works reliably**
   - Cron runs every Monday at 00:03 AM without manual intervention
   - Files processed within 5 minutes
   - Success rate >95%

2. ✅ **Notifications are timely and accurate**
   - Failure emails received within 5 minutes
   - Dashboard banners display on next login
   - No false positives/negatives

3. ✅ **Settings page is user-friendly**
   - Non-technical users can configure patterns
   - Processing history clearly shows status
   - Manual trigger works instantly

4. ✅ **Error handling is robust**
   - No data corruption on failures
   - Failed files clearly identified
   - Recovery procedures documented and tested

5. ✅ **System is expandable**
   - New processor added without code changes to orchestrator
   - New notification method added via interface implementation
   - Patterns configurable without developer assistance

6. ✅ **Manual upload remains functional**
   - Backup option available for emergencies
   - No dependency on automation for critical operations

---

## 10. Future Enhancements (Post-MVP)

**Not in initial scope, but architecture supports:**

1. **Multiple Processing Schedules**
   - Different cron times for different file types
   - Example: Renewals daily, circulation weekly

2. **File Preprocessing**
   - Automatic file format conversion
   - CSV normalization (handle different column orders)

3. **Advanced Notifications**
   - Slack integration for team alerts
   - SMS for critical failures
   - Custom webhooks for integration with other systems

4. **Data Validation Rules**
   - Configurable business rules per file type
   - Example: "TA must have >2,500 subscribers or alert"
   - Threshold warnings (not failures)

5. **Automated Recovery**
   - Retry failed files with exponential backoff
   - Self-healing for transient errors

6. **Processing Analytics**
   - Average processing time trends
   - File size growth tracking
   - Error pattern analysis

7. **Multi-Source Support**
   - Support for multiple SFTP sources
   - Different folder structures per source

---

## Appendix A: File Structure

```
/volume1/web/circulation/
├── process-inbox.php              # Main cron orchestrator
├── processors/                     # Processor plugins
│   ├── IFileProcessor.php         # Interface definition
│   ├── AllSubscriberProcessor.php # All Subscriber Report handler
│   ├── VacationProcessor.php      # Vacation data handler
│   └── RenewalProcessor.php       # Renewal/churn handler
├── notifications/                  # Notification plugins
│   ├── INotifier.php              # Interface definition
│   ├── EmailNotifier.php          # Email notifications
│   └── DashboardNotifier.php      # Dashboard banners
├── settings_file_processing.php   # Settings page addition
└── api/
    └── file_processing.php        # API endpoints (manual trigger, history, etc.)

/volume1/homes/newzware/           # SFTP directory
├── inbox/                         # Files arrive here
├── processing/                    # Files being processed (transient)
├── completed/                     # Successfully processed (90 day retention)
├── failed/                        # Failed files (manual review)
└── archive/                       # Long-term storage
    └── YYYY/                      # Yearly folders
        └── completed_files/       # Archived completed files
```

---

## Appendix B: Configuration Reference

**Database Settings Table:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `notification_emails` | TEXT | (empty) | Comma-separated email addresses |
| `auto_processing_enabled` | BOOLEAN | TRUE | Enable/disable automatic processing |
| `max_file_age_days` | INT | 60 | Files older than this = reject |
| `completed_retention_days` | INT | 90 | Archive completed files after N days |
| `send_success_emails` | BOOLEAN | FALSE | Email on success (noisy, not recommended) |

**Default Filename Patterns:**

| Pattern | Processor | Description |
|---------|-----------|-------------|
| `AllSubscriberReport*.csv` | AllSubscriberProcessor | Weekly circulation snapshots |
| `SubscribersOnVacation*.csv` | VacationProcessor | Vacation hold tracking |
| `*Renewal*.csv` | RenewalProcessor | Renewal and churn data |
| `*Churn*.csv` | RenewalProcessor | Alternative renewal filename |

---

## Appendix C: Testing Checklist

**Pre-Production Testing:**

- [ ] Single file processing (success path)
- [ ] Single file processing (validation failure)
- [ ] Single file processing (processing error)
- [ ] Multiple files same type (newest wins)
- [ ] Multiple files different types (all process)
- [ ] Backfill file processing (1 week old)
- [ ] Backfill file processing (multiple weeks)
- [ ] Duplicate week file (skipped correctly)
- [ ] Incomplete file (missing papers, rejected)
- [ ] Corrupt file (unreadable CSV, rejected)
- [ ] Large file (near 10MB limit, accepted)
- [ ] Oversized file (>10MB, rejected)
- [ ] Email notification (failure received)
- [ ] Dashboard notification (success displayed)
- [ ] Manual trigger (Settings page button)
- [ ] Pattern CRUD (add/edit/delete patterns)
- [ ] Processing history (view past runs)
- [ ] Cron execution (actual Monday 00:03 run)
- [ ] Log rotation (old logs archived)
- [ ] File archival (completed/ files moved after 90 days)
- [ ] Stuck file detection (processing/ file >24h alerts)
- [ ] Recovery: Move failed file back to inbox
- [ ] Recovery: Manual upload bypass

---

**End of Design Document**

---

**Approvals:**

- [x] John Corbin (Product Owner) - December 16, 2025
- [ ] Ready for Implementation

**Next Steps:**

1. Review design document for any final questions
2. Confirm ready to proceed with implementation
3. Set up git worktree for isolated development
4. Create detailed implementation plan with task breakdown
