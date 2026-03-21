---
phase: 06-call-status-ui-and-export
plan: 02
subsystem: ui
tags: [sheetjs, xlsx-js-style, inline-styles, call-status, sort, tooltip, xlsx-export]

# Dependency graph
requires:
  - phase: 06-call-status-ui-and-export
    plan: 01
    provides: call_status, last_call_datetime, call_agent fields on API response; call_data_as_of sync timestamp
  - phase: 04-call-log-scraper
    provides: call_logs table with phone_normalized and call_direction data
provides:
  - Status column with colored phone icons in subscriber table (green/orange/red)
  - 4px colored left border on table rows by call status
  - Sort toggle on Status header (not-contacted first default)
  - Native tooltip with call direction, date/time, agent initials
  - Sync timestamp display in panel header
  - XLSX export with status-based row fills, merged timestamp header row, 3 new columns
affects: []

# Tech tracking
tech-stack:
  added: [xlsx-js-style@1.2.0 (CDN, replaces xlsx-latest)]
  patterns:
    [
      getCallStatusColor() color mapping helper for status-based UI,
      getExportStatusFill() for XLSX fill colors,
      Row shifting in SheetJS to insert merged header row,
      rowOffset pattern for freeze/autofilter when timestamp row present,
    ]

key-files:
  created: []
  modified:
    [
      web/assets/js/components/subscriber-table-panel.js,
      web/assets/js/utils/export-utils.js,
      web/api/legacy.php,
      web/assets/js/charts/chart-context-integration.js,
      web/index.php,
    ]

key-decisions:
  - 'Switched from community xlsx-latest to xlsx-js-style@1.2.0 CDN -- community SheetJS does not support .s cell styles'
  - 'Used ws["!views"] instead of ws["!freeze"] for frozen panes in xlsx-js-style'
  - 'Added COLLATE utf8mb4_general_ci to LEFT JOIN ON clause to resolve collation mismatch between call_logs and subscriber_snapshots'
  - 'escapeHtml() helper for tooltip title attributes despite low XSS risk from own DB data'

patterns-established:
  - 'Status color mapping: getCallStatusColor() returns { border, iconBg, label } for UI, getExportStatusFill() returns SheetJS fill for XLSX'
  - 'Sort toggle pattern: data-sort-status attribute + handleSortToggle() re-renders table container'
  - 'rowOffset pattern in exportToExcel() for optional merged header row above column headers'

requirements-completed: [UI-01, UI-02, UI-03, UI-04, UI-05, UI-06]

# Metrics
duration: ~10min
completed: 2026-03-20
---

# Phase 06 Plan 02: Call Status UI and Export Summary

**Status column with colored phone icons, sort toggle, tooltips, and left border in subscriber table; XLSX export with status-colored row fills, merged timestamp header, and 3 new columns using xlsx-js-style**

## Performance

- **Duration:** ~10 min (including post-deployment fixes)
- **Started:** 2026-03-20T22:25:27Z
- **Completed:** 2026-03-20T22:35:00Z
- **Tasks:** 3 (2 auto + 1 human-verify checkpoint)
- **Files modified:** 5

## Accomplishments

- Subscriber table shows Status as the first column with green/orange/red phone icons and 4px colored left borders
- Clickable sort toggle on Status header (not-contacted first by default, togglable)
- Native tooltip on phone icon: "Placed . Mar 18, 2:15 PM . BC" or "No contact recorded"
- Sync timestamp ("Call data as of...") in panel header and as merged XLSX Row 1
- XLSX export rows filled with status colors (DCFCE7 green, FEF9C3 yellow, FEE2E2 red) instead of alternating teal
- Three new XLSX columns: Call Status, Last Contact, Agent

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Status column, left border, tooltip, sort toggle, and sync timestamp** - `ccd00a7` (feat)
2. **Task 2: Add status-based row fills, merged timestamp row, and 3 new columns to export** - `43ff1ac` (feat)
3. **Task 3: Human verification** - approved after post-deployment fixes

**Post-deployment fixes (by orchestrator):**

- `b6776aa` (fix) - Collation mismatch in LEFT JOIN, SheetJS library swap to xlsx-js-style, cache-bust
- `d2cb2d0` (fix) - Pass call_data_as_of from API response through chart-context-integration.js to subscriber panel and export

## Files Created/Modified

- `web/assets/js/components/subscriber-table-panel.js` - Added getCallStatusColor(), buildCallTooltip(), getStatusSortPriority(), sortSubscribers(), handleSortToggle(), attachSortHandler(), escapeHtml() methods; Status column with phone icon; 4px left border; sort toggle; sync timestamp display; syncTimestamp passthrough to export
- `web/assets/js/utils/export-utils.js` - Added getExportStatusFill(); 3 new columns in formatSubscriberDataForExport(); syncTimestamp parameter in exportSubscriberList(); merged timestamp Row 1 with cell shifting; status-based row fills replacing alternating teal; rowOffset for freeze/autofilter
- `web/api/legacy.php` - Added COLLATE utf8mb4_general_ci to LEFT JOIN ON clause (post-deploy fix)
- `web/assets/js/charts/chart-context-integration.js` - Captures call_data_as_of from API and passes to subscriber panel and export payload (post-deploy fix)
- `web/index.php` - Swapped SheetJS CDN from xlsx-latest to xlsx-js-style@1.2.0; cache-bust on JS files (post-deploy fix)

## Decisions Made

- Switched SheetJS CDN from community `xlsx-latest` to `xlsx-js-style@1.2.0` because the community edition does not support `.s` cell styles needed for row fills and header formatting
- Used `ws['!views']` property instead of `ws['!freeze']` which is the correct frozen pane API for xlsx-js-style
- Added `COLLATE utf8mb4_general_ci` to the LEFT JOIN ON clause in legacy.php to resolve collation mismatch between call_logs (unicode_ci) and subscriber_snapshots (general_ci)
- Added escapeHtml() helper for tooltip title attributes as a defensive measure, even though data comes from our own DB

## Deviations from Plan

### Post-Deployment Fixes

**1. [Rule 3 - Blocking] Collation mismatch in LEFT JOIN**

- **Found during:** Post-deployment verification
- **Issue:** call_logs table used utf8mb4_unicode_ci while subscriber_snapshots used utf8mb4_general_ci, causing JOIN failures
- **Fix:** Added `COLLATE utf8mb4_general_ci` to the ON clause in legacy.php
- **Files modified:** web/api/legacy.php
- **Committed in:** b6776aa

**2. [Rule 3 - Blocking] SheetJS community edition lacks cell style support**

- **Found during:** Post-deployment verification (XLSX rows had no color fills)
- **Issue:** CDN `xlsx-latest` is the community build which strips `.s` cell styles
- **Fix:** Swapped CDN to `xlsx-js-style@1.2.0` which preserves cell styling; changed `ws['!freeze']` to `ws['!views']`
- **Files modified:** web/index.php, web/assets/js/utils/export-utils.js
- **Committed in:** b6776aa

**3. [Rule 3 - Blocking] call_data_as_of not reaching subscriber panel**

- **Found during:** Post-deployment verification (sync timestamp not showing)
- **Issue:** chart-context-integration.js was not capturing call_data_as_of from API response or passing it to the panel/export
- **Fix:** Updated chart-context-integration.js to capture and forward call_data_as_of
- **Files modified:** web/assets/js/charts/chart-context-integration.js
- **Committed in:** d2cb2d0

---

**Total deviations:** 3 post-deployment fixes (all Rule 3 - Blocking)
**Impact on plan:** All fixes were necessary for the feature to work correctly. The SheetJS blocker was flagged in STATE.md as a known concern. No scope creep.

## Issues Encountered

- SheetJS community edition (`xlsx-latest`) does not support cell styles -- this was a known concern in STATE.md. Resolved by switching to `xlsx-js-style@1.2.0` which is specifically built for styled XLSX output.
- MariaDB collation mismatch between tables required explicit COLLATE in JOIN clause -- a common issue when tables are created at different times with different defaults.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 6 is complete: call status UI and XLSX export are live in production
- All UI requirements (UI-01 through UI-06) satisfied
- Remaining v2.1 work: Phase 4 Plan 02 (scraper scheduling/deployment) and Phase 5 (expiration chart expansion if not already done)
- Known blocker resolved: SheetJS styling concern from STATE.md is now addressed with xlsx-js-style

## Self-Check: PASSED

All files and commits verified:

- 06-02-SUMMARY.md: FOUND
- ccd00a7 (Task 1): FOUND
- 43ff1ac (Task 2): FOUND
- b6776aa (post-deploy fix 1): FOUND
- d2cb2d0 (post-deploy fix 2): FOUND
- subscriber-table-panel.js: FOUND
- export-utils.js: FOUND

---

_Phase: 06-call-status-ui-and-export_
_Completed: 2026-03-20_
