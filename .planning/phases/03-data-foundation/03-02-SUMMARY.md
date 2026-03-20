---
phase: 03-data-foundation
plan: 02
subsystem: database
tags: [mariadb, migration, phone-normalization, call-logs, subscriber-snapshots]

# Dependency graph
requires: []
provides:
  - call_logs table with phone_normalized CHAR(10) indexed column
  - subscriber_snapshots.phone_normalized column backfilled for existing rows
  - AllSubscriberImporter.normalizePhone() for ingest-time phone normalization
affects:
  - 04-call-log-scraper (reads call_logs table, needs phone_normalized for matching)
  - 06-call-status-ui (reads joined call_logs + subscriber_snapshots)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - 'Phone normalization: bare 10-digit CHAR(10), strip non-digits, handle 11-digit with leading 1'
    - 'Migration style: plain SQL with comment header, rollback instructions, backfill in same file'

key-files:
  created:
    - database/migrations/014_add_call_logs_table.sql
    - database/migrations/015_add_phone_normalized_to_subscriber_snapshots.sql
  modified:
    - web/lib/AllSubscriberImporter.php

key-decisions:
  - 'Backfill 015 uses REGEXP_REPLACE + RIGHT(x, 10) for rightmost-10-digit strategy matching PHP logic'
  - 'normalizePhone() placed after parseDate() as a private utility method in same class'
  - 'phone_normalized added to subscriber_records array before INSERT — not computed at query time'

patterns-established:
  - "normalizePhone pattern: preg_replace('/\\D/', '', $phone), strip leading 1 from 11-digit, return 10-char or null"
  - 'Migration numbering: plain SQL files 014+ for v2.1 features, sequential from existing 013'

requirements-completed: [CALL-02]

# Metrics
duration: 2min
completed: 2026-03-20
---

# Phase 03 Plan 02: Data Foundation — Phone Normalization Summary

**call_logs table created and phone_normalized column added to subscriber_snapshots with 94,943/94,991 row backfill (99.95%); AllSubscriberImporter now populates phone_normalized at ingest via normalizePhone()**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-20T16:35:48Z
- **Completed:** 2026-03-20T16:37:50Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Created call_logs table with full BroadWorks schema: direction enum, timestamp, duration, raw/normalized phone, extension, source group, and UNIQUE KEY dedup constraint
- Added phone_normalized CHAR(10) to subscriber_snapshots with index and backfilled 94,943 existing rows (99.95% coverage) using REGEXP_REPLACE
- Added private normalizePhone() to AllSubscriberImporter that strips non-digits, handles 11-digit country-code numbers, and wires into both the subscriber_records array and the INSERT statement

## Task Commits

Each task was committed atomically:

1. **Task 1: Create SQL migrations for call_logs and phone_normalized** - `da16ec4` (feat)
2. **Task 2: Add normalizePhone() to AllSubscriberImporter** - `5c88c7f` (feat)

## Files Created/Modified

- `database/migrations/014_add_call_logs_table.sql` - CREATE TABLE call_logs with indexes and UNIQUE KEY dedup
- `database/migrations/015_add_phone_normalized_to_subscriber_snapshots.sql` - ALTER + backfill for subscriber_snapshots
- `web/lib/AllSubscriberImporter.php` - normalizePhone() method + subscriber record array + INSERT statement

## Decisions Made

- REGEXP_REPLACE backfill strategy uses `RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10)` — takes rightmost 10 digits — to match the PHP normalizePhone() behavior of stripping leading country code
- normalizePhone() placed after parseDate() as a logical grouping of input-processing private methods

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- call_logs table is ready to receive data from Phase 4 (BroadWorks web scraper)
- subscriber_snapshots.phone_normalized is indexed and populated; JOIN performance will be good
- Any new CSV uploads via upload_unified.php will automatically populate phone_normalized going forward
- Concern noted in STATE.md: MariaDB minor version on NAS — confirm REGEXP_REPLACE availability for production migration (015 uses REGEXP_REPLACE in backfill)

## Self-Check: PASSED

- FOUND: database/migrations/014_add_call_logs_table.sql
- FOUND: database/migrations/015_add_phone_normalized_to_subscriber_snapshots.sql
- FOUND: .planning/phases/03-data-foundation/03-02-SUMMARY.md
- FOUND: da16ec4 (Task 1 commit)
- FOUND: 5c88c7f (Task 2 commit)

---

_Phase: 03-data-foundation_
_Completed: 2026-03-20_
