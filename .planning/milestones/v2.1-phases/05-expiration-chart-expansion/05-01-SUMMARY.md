---
phase: 05-expiration-chart-expansion
plan: 01
subsystem: ui, api
tags: [chart.js, php, javascript, expiration-chart, color-gradient]

# Dependency graph
requires:
  - phase: 01-business-unit-trend-data
    provides: BU trend API and detail panel infrastructure
  - phase: 02-chart-rendering
    provides: Chart.js rendering pipeline and context menu integration
provides:
  - 8-bucket expiration SQL with Week +3 through Week +6
  - 8-color red-to-green gradient in Chart.js expiration bar chart
  - Subscriber drill-down for all 8 expiration buckets
  - Updated chart title metadata to 8-Week Expiration View
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - 'array_fill() for repetitive PDO parameter arrays'
    - 'in_array() for multi-value condition checks in getMetricCount'

key-files:
  created: []
  modified:
    - web/api/legacy.php
    - web/assets/js/components/detail_panel.js
    - web/assets/js/charts/chart-layout-manager.js
    - web/index.php

key-decisions:
  - 'Used array_fill(0, 16, $snapshotDate) instead of 16 explicit array entries for cleaner parameter binding'
  - 'This Week color changed from orange-400 to amber-500 for smoother 8-stop gradient per UI-SPEC'

patterns-established:
  - '8-bucket expiration pattern: Past Due, This Week, Next Week, Week +2 through Week +6'

requirements-completed: [CHART-01, CHART-02, CHART-03]

# Metrics
duration: 16min
completed: 2026-03-20
---

# Phase 5 Plan 1: Expiration Chart Expansion Summary

**8-bar expiration chart with red-to-green color gradient, subscriber drill-down on all buckets, and 49-day SQL horizon**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-03-20T18:59:22Z
- **Completed:** 2026-03-20T19:15:52Z
- **Tasks:** 3 (2 auto + 1 human-verify checkpoint)
- **Files modified:** 4

## Accomplishments

- Extended SQL CASE from 4 to 8 expiration buckets (Past Due through Week +6) with correct 17-parameter binding
- Added 4 new switch cases in getExpirationSubscribers() and getMetricCount() for drill-down and metric counting
- Implemented 8-color red-to-green gradient (red-500 -> amber-500 -> amber-400 -> yellow-300 -> yellow-200 -> lime-300 -> green-300 -> green-400)
- Updated chart title metadata and HTML heading to "8-Week Expiration View"

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend PHP backend -- SQL CASE, subscriber drill-down, and metric count** - `5c96038` (feat)
2. **Task 2: Update JS color gradient and chart title metadata** - `f6eb5ae` (feat)
3. **Task 3: Visual verification checkpoint** - approved by user (no code commit)

**Additional fix:** `95e7648` (fix) - Updated hardcoded "4-Week View" heading in index.php

## Files Created/Modified

- `web/api/legacy.php` - 8-bucket SQL CASE, 4 new getExpirationSubscribers switch cases, 4 new getMetricCount elseif branches, 4 new mock data cases, shifted "Later" cutoff to +6 weeks
- `web/assets/js/components/detail_panel.js` - 8-color gradient in renderExpirationChart() backgroundColors map
- `web/assets/js/charts/chart-layout-manager.js` - Chart title and description updated to "8-Week"
- `web/index.php` - HTML heading updated from "4-Week View" to "8-Week View"

## Decisions Made

- Used `array_fill(0, 16, $snapshotDate)` instead of 16 explicit entries for cleaner parameter array construction
- Changed "This Week" color from orange-400 to amber-500 per UI-SPEC color contract for smoother 8-stop gradient
- Used `in_array()` for the week parameter condition check in getMetricCount instead of extending the `||` chain

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Hardcoded "4-Week View" heading in index.php**

- **Found during:** Task 3 (human verification)
- **Issue:** HTML heading in index.php line 1144 still said "4-Week View" after backend/JS changes
- **Fix:** Updated heading text to "8-Week View"
- **Files modified:** web/index.php
- **Committed in:** `95e7648`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Minor oversight in plan -- heading was not listed as a modification target. Fix was necessary for visual consistency.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 8-week expiration chart fully operational for all 3 business units
- Context menu drill-down works for all 8 buckets
- Ready for production deployment via standard git pull + rsync workflow

---

## Self-Check: PASSED

All 4 modified files verified on disk. All 3 commit hashes verified in git log.

---

_Phase: 05-expiration-chart-expansion_
_Completed: 2026-03-20_
