---
phase: 09-csr-call-reporting
plan: 01
subsystem: api, ui
tags: [php, chart.js, pdo, call-logs, csr-reporting, tailwind]

# Dependency graph
requires:
  - phase: 04-call-log-scraper
    provides: call_logs table with source_group column (BC/CW)
provides:
  - CSR call report API endpoint (web/api/csr_report.php)
  - CSR report page with summary table and weekly stacked bar chart (web/csr_report.php)
  - CSR Call Activity card on settings page with live outgoing counts (web/settings.php)
affects: []

# Tech tracking
tech-stack:
  added: [chart.js 4.4.0 (CDN)]
  patterns: [settings-card-with-api-stats, stacked-bar-chart, skeleton-loading]

key-files:
  created:
    - web/api/csr_report.php
    - web/csr_report.php
  modified:
    - web/settings.php

key-decisions:
  - "CSR name mapping as PHP array in API (BC->Brittany Carroll, CW->Chloe Welch) with Unknown fallback"
  - "Stacked bar chart groups by CSR with placed/received/missed segments per week"
  - "Task 3 (human-verify) deferred to end-of-milestone deployment -- user cannot verify until deployed from another location"

patterns-established:
  - "Settings card with live API stats: fetch summary endpoint on DOMContentLoaded, update badge and per-item stats"
  - "Report page skeleton loading: show animate-pulse placeholders, swap to real content after fetch"

requirements-completed: [CSR-01]

# Metrics
duration: ~15min (Tasks 1-2 completed in prior session; Task 3 deferred)
completed: 2026-03-21
---

# Phase 9 Plan 01: CSR Call Reporting Summary

**CSR call report API with 60-day rolling window, dedicated report page with summary table and Chart.js stacked bar chart, and teal settings card with live outgoing call counts per CSR**

## Performance

- **Duration:** ~15 min (Tasks 1-2 in prior session)
- **Started:** 2026-03-21 (prior session)
- **Completed:** 2026-03-21
- **Tasks:** 2 of 3 (Task 3 human-verify deferred to deployment)
- **Files created/modified:** 3

## Accomplishments
- JSON API endpoint with summary and weekly breakdown modes, 60-day rolling window, CSR name mapping (BC/CW to human names)
- Dedicated report page with sortable summary table (Outgoing/Received/Missed/Total columns, totals row) and Chart.js stacked bar chart of weekly call volume
- Teal CSR Call Activity card on settings page that fetches live outgoing counts per CSR on page load
- Skeleton loading states for both table and chart on report page

## Task Commits

Each task was committed atomically:

1. **Task 1: Create CSR report API endpoint** - `f320175` (feat)
2. **Task 2: Create CSR report page and add settings card** - `a9aa50b` (feat)
3. **Task 3: Verify CSR reporting end-to-end** - DEFERRED (human-verify checkpoint deferred to end-of-milestone deployment)

## Files Created/Modified
- `web/api/csr_report.php` - JSON API with summary and weekly call data from call_logs table
- `web/csr_report.php` - Dedicated report page with summary table and stacked bar chart
- `web/settings.php` - Added 5th card (CSR Call Activity) with live API-loaded stats

## Decisions Made
- CSR name mapping implemented as a simple PHP associative array with "Unknown ({code})" fallback for unmapped source_group values
- Chart uses stacked bars grouped by CSR, with blue (placed), green (received), red (missed) color coding
- Task 3 (human-verify) deferred by user instruction -- cannot verify until deployed from another computer location; verifications added to end-of-milestone checklist

## Deviations from Plan

### Deferred Checkpoint

**Task 3 (human-verify) deferred to end-of-milestone deployment.**
- **Reason:** User cannot access the application from current location; deployment requires another computer
- **Impact:** Feature code is complete and syntax-verified but not visually confirmed against live data
- **Resolution:** User will verify all checkpoint items when v2.2 is deployed to production

No other deviations -- Tasks 1 and 2 executed exactly as written.

## Issues Encountered
None -- both implementation tasks completed without issues.

## User Setup Required
None -- no external service configuration required.

## Next Phase Readiness
- All v2.2 phase code is complete (Phases 7, 8, 9)
- End-of-milestone deployment needed to verify deferred checkpoints from Phases 7 and 9
- Ready for version bump and deployment

## Self-Check: PASSED

All files exist, all commits verified.

---
*Phase: 09-csr-call-reporting*
*Completed: 2026-03-21*
