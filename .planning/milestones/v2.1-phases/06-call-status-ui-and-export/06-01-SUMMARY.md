---
phase: 06-call-status-ui-and-export
plan: 01
subsystem: api
tags: [sql, left-join, row-number, window-function, mariadb, call-logs]

# Dependency graph
requires:
  - phase: 04-call-log-scraper
    provides: call_logs table with phone_normalized and call_direction data
  - phase: 03-data-foundation
    provides: subscriber_snapshots.phone_normalized column for JOIN matching
provides:
  - call_status, last_call_datetime, call_agent fields on expiration subscriber API response
  - call_data_as_of sync timestamp in getSubscribers() response
affects: [06-02-call-status-ui-and-export]

# Tech tracking
tech-stack:
  added: []
  patterns:
    [
      ROW_NUMBER window function for most-recent-per-group,
      reusable PHP subquery variable for DRY SQL,
    ]

key-files:
  created: []
  modified: [web/api/legacy.php]

key-decisions:
  - 'Reusable $callLogSubquery PHP variable instead of duplicating subquery in 8 branches'
  - 'call_data_as_of only included for expiration metric type (only metric that uses call data)'
  - 'Sort order: uncalled first (0), received/missed second (1), placed last (2) — prioritizes subscribers needing contact'

patterns-established:
  - 'Reusable SQL subquery as PHP variable: define once, interpolate in all branches'
  - 'LEFT JOIN with ROW_NUMBER() for most-recent-per-phone call matching'

requirements-completed: [UI-01, UI-06]

# Metrics
duration: 3min
completed: 2026-03-20
---

# Phase 06 Plan 01: Call Status API Summary

**LEFT JOIN call_logs via ROW_NUMBER() window function into all 8 expiration bucket queries, with call_data_as_of sync timestamp**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-20T22:19:51Z
- **Completed:** 2026-03-20T22:22:41Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- All 8 expiration bucket queries now return call_status, last_call_datetime, and call_agent per subscriber
- ROW_NUMBER() window function selects most recent call within 30 days per phone number
- call_data_as_of timestamp added to API response for sync indicator
- Sort order prioritizes uncalled subscribers first for retention workflow

## Task Commits

Each task was committed atomically:

1. **Task 1: Add LEFT JOIN call_logs to getExpirationSubscribers() and call_data_as_of to getSubscribers()** - `11c71b5` (feat)

**Plan metadata:** `8db02d6` (docs: complete plan)

## Files Created/Modified

- `web/api/legacy.php` - Added $callLogSubquery variable, updated all 8 expiration bucket SELECT/FROM/WHERE/ORDER BY clauses, added call_data_as_of to getSubscribers() return

## Decisions Made

- Used a single `$callLogSubquery` PHP variable interpolated into all 8 branch queries instead of duplicating the subquery — DRY approach that makes future changes to the JOIN logic single-point
- Only query call_data_as_of for expiration metric type since other metrics (rate, subscription_length) do not use call data
- Sort order puts uncalled subscribers first (NULL = 0), then received/missed (1), then placed (2) — matches retention workflow where staff need to contact uncalled subscribers first

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- API layer complete: expiration subscriber rows now include call_status, last_call_datetime, call_agent
- call_data_as_of available at top level for sync indicator badge
- Ready for Plan 02 (UI rendering of status column, row coloring, and XLSX export)

---

_Phase: 06-call-status-ui-and-export_
_Completed: 2026-03-20_
