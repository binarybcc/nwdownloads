---
phase: 08-trend-expansion-log-retention
plan: 01
subsystem: api, ui
tags: [php, javascript, trend-charts, chartjs, xlsx-export]

# Dependency graph
requires:
  - phase: none
    provides: existing 12-week trend infrastructure
provides:
  - 13-week default trend data across all API endpoints
  - 13-week trend slider with backward-compatible 12weeks support
  - Updated BU card labels and XLSX export sheet names
affects: [08-02-log-retention]

# Tech tracking
tech-stack:
  added: []
  patterns: [backward-compatible API parameter expansion]

key-files:
  created: []
  modified:
    - web/api/legacy.php
    - web/assets/js/core/app.js
    - web/assets/js/components/trend-slider.js

key-decisions:
  - "Kept '12weeks' in $weeksMap and label maps for backward URL compatibility"
  - "Replaced '12weeks' button with '13weeks' in trend slider UI (users select visually)"
  - "Left anomaly detection '12 weeks' text unchanged (separate analysis window)"
  - "Fixed missed XLSX book_append_sheet sheet name not listed in plan"

patterns-established:
  - "Backward-compatible API parameter expansion: add new key, keep old key functional"

requirements-completed: [TREND-01]

# Metrics
duration: 2min
completed: 2026-03-21
---

# Phase 8 Plan 1: Trend Expansion Summary

**13-week default trend views across backend API (legacy.php) and frontend (app.js, trend-slider.js) with backward-compatible 12weeks URL support**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-21T16:36:26Z
- **Completed:** 2026-03-21T16:38:04Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Backend API now returns 13 data points by default for BU card trend charts
- All API defaults changed from '12weeks' to '13weeks' (getHistoricalTrend, get_trend case)
- $weeksMap expanded with '13weeks' => 13 while preserving '12weeks' => 12
- Frontend labels updated from "12-Week Trend" to "13-Week Trend" (4 locations)
- Trend slider defaults to 13 weeks with button array showing 4/13/26/52 week options
- Anomaly detection "12 weeks" text preserved (separate analysis window)

## Task Commits

Each task was committed atomically:

1. **Task 1: Expand backend trend defaults to 13 weeks** - `8242b55` (feat)
2. **Task 2: Update frontend labels and trend slider to 13-week defaults** - `3ff3eaf` (feat)

## Files Created/Modified
- `web/api/legacy.php` - Backend: 13-week defaults in getBusinessUnitTrendData(), getHistoricalTrend(), get_trend, $weeksMap
- `web/assets/js/core/app.js` - Frontend: "13-Week Trend" labels (BU card, XLSX export, detail panel)
- `web/assets/js/components/trend-slider.js` - Slider: 13weeks default, button array, label maps

## Decisions Made
- Kept '12weeks' in $weeksMap and both JS label maps for backward URL/parameter compatibility
- Replaced '12weeks' button with '13weeks' in the trend slider button array (users select visually, no need for both)
- Left anomaly detection "12 weeks" text unchanged per research Pitfall 4 (separate analysis window)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed missed XLSX sheet name reference**
- **Found during:** Task 2 (frontend label updates)
- **Issue:** Plan listed 3 locations in app.js but XLSX.utils.book_append_sheet at line 1339 was a 4th "12-Week Trend" occurrence not explicitly called out
- **Fix:** Changed sheet name from '12-Week Trend' to '13-Week Trend' at line 1339
- **Files modified:** web/assets/js/core/app.js
- **Verification:** grep -c "12-Week Trend" returns 0
- **Committed in:** 3ff3eaf (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Minor plan omission corrected. The XLSX sheet name was referenced in plan but only the comment line was identified, not the actual book_append_sheet call.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 13-week trend expansion complete, ready for 08-02 (log retention)
- End-to-end verification deferred to milestone deployment

---
*Phase: 08-trend-expansion-log-retention*
*Completed: 2026-03-21*

## Self-Check: PASSED
