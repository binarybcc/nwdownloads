---
phase: 03-data-foundation
plan: 01
subsystem: importer
tags: [php, csv, date-parsing, newstarts, newzware, auto-import]

# Dependency graph
requires: []
provides:
  - 'NewStartsImporter::parseDate() handles both M/D/YY and YYYY-MM-DD date formats'
  - 'Failed Mar 9 and Mar 16 CSVs available for reprocessing after deploy'
affects: [auto-import, new-starts, newstarts-processor]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - 'parseDate() checks YYYY-MM-DD format first (most specific), falls back to M/D/YY'

key-files:
  created: []
  modified:
    - web/lib/NewStartsImporter.php

key-decisions:
  - 'Detect YYYY-MM-DD before M/D/YY in parseDate() — more specific pattern first, avoids ambiguity'
  - 'Return $dateStr unchanged for YYYY-MM-DD (already in target format) rather than reformatting'

patterns-established:
  - 'Date parser: most-specific format first, validate with checkdate(), return null on invalid'

requirements-completed: [IMPORT-01, IMPORT-02]

# Metrics
duration: 5min
completed: 2026-03-20
---

# Phase 3 Plan 01: NewStarts Date Parsing Fix Summary

**Dual-format parseDate() in NewStartsImporter that accepts both YYYY-MM-DD (auto-export) and M/D/YY (manual export), fixing silent import failures for all Monday auto-imports since 2026-03-02**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-03-20T16:35:54Z
- **Completed:** 2026-03-20T16:41:00Z
- **Tasks:** 1 of 2 complete (Task 2 at checkpoint — requires deploy + human verification)
- **Files modified:** 1

## Accomplishments

- Fixed `parseDate()` to handle both `YYYY-MM-DD` (Newzware auto-export via launchd) and `M/D/YY` (manual Newzware export) formats
- Updated docblock to document both supported date formats
- Opened PR #46 (`fix/new-starts-date-parsing`) for code review before deploy
- Prepared NAS reprocess commands for checkpoint execution

## Task Commits

Each task committed atomically:

1. **Task 1: Add YYYY-MM-DD format support to parseDate()** - `f80cfd8` (fix)
2. **Task 2: Deploy fix and reprocess failed CSVs on NAS** - PENDING (checkpoint:human-verify)

## Files Created/Modified

- `web/lib/NewStartsImporter.php` — Added YYYY-MM-DD detection block before M/D/YY regex in `parseDate()`; updated docblock comment

## Decisions Made

- YYYY-MM-DD check placed before M/D/YY regex — more specific pattern matched first, no ambiguity
- Return `$dateStr` directly for YYYY-MM-DD (already in target format) — no reformatting needed
- Used `explode('-', $dateStr)` with `checkdate()` for validation parity with the M/D/YY path

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - root cause was known from research (see `.claude/handoff.md` and `project_newstarts_bug.md` memory file). Fix was straightforward.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- **Task 2 at checkpoint:** After PR #46 is reviewed and merged to master, run:
  1. `~/deploy-circulation.sh` on the Mac (deploys to NAS via rsync)
  2. `ssh nas "mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260309021018.csv /volume1/homes/newzware/inbox/ && mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260316021038.csv /volume1/homes/newzware/inbox/"`
  3. `ssh nas "/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/auto_process.php"`
  4. Verify both files appear in `completed/` and new starts data shows on https://cdash.upstatetoday.com
- **Plan 02** can begin once this checkpoint is cleared

---

_Phase: 03-data-foundation_
_Completed: 2026-03-20 (partial — at checkpoint after Task 1)_
