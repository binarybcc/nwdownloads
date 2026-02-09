# Phase 1 Plan 1: Extend Trend API with Per-BU Filtering and Wire Frontend Data Flow

Per-business-unit 12-week Total Active trend data served via overview API with null-padded missing weeks, MAX(snapshot_date) dedup, and frontend data-attribute wiring for Phase 2 chart rendering.

## Metadata

- **Phase:** 01-business-unit-trend-data
- **Plan:** 01
- **Completed:** 2026-02-09
- **Duration:** ~4 minutes
- **Tasks:** 2/2

## What Was Built

### Task 1: Add business unit trend functions and embed in overview response

**Commit:** `b0c5ba4`
**Files modified:** `web/api/legacy.php`

Added two new PHP functions placed before `getOverviewEnhanced()`:

1. **`getBusinessUnitTrendData(PDO $pdo, string $businessUnit, int $weekNum, int $year): array`**
   - Iterates 12 ISO weeks (oldest W1 to newest W12)
   - Queries `daily_snapshots` with `business_unit` filter and `paper_code != 'FN'`
   - Uses MAX(snapshot_date) subquery to handle multiple snapshots per calendar week
   - Tracks `$lastNonNullValue` for correct week-over-week change calculation
   - Null-pads missing weeks with `['label' => 'WN', 'total_active' => null, 'change' => null]`
   - First non-null entry always has `change: null` (no prior value to compare)

2. **`getAllBusinessUnitTrends(PDO $pdo, int $weekNum, int $year): array`**
   - Batch wrapper iterating `['South Carolina', 'Wyoming', 'Michigan']`
   - Returns associative array keyed by unit name

3. **Wired into `getOverviewEnhanced()`:**
   - Calls `getAllBusinessUnitTrends($pdo, $week_num, $year)` after `$by_edition` processing
   - Adds `'business_unit_trends' => $business_unit_trends` to the return array

### Task 2: Wire frontend to store business unit trend data

**Commit:** `8a3de0e`
**Files modified:** `web/assets/js/core/app.js`

1. Extracts `buTrends` from `dashboardData.business_unit_trends` in `renderBusinessUnits()`
2. Makes `trendData` available per card iteration in the business unit loop
3. Adds `data-bu-trend` attribute containing JSON-encoded trend data to each card's DOM element

## Decisions Made

| Decision                                                  | Rationale                                                                                     | Outcome                                                      |
| --------------------------------------------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| Embed trends in overview response (not separate endpoint) | Single HTTP request, data always in sync with current week, tiny payload (~2KB)               | business_unit_trends key added to existing overview response |
| MAX(snapshot_date) subquery for dedup                     | Handles multiple CSV uploads in same week per CONTEXT.md "latest snapshot date wins" decision | Correct single-row-per-week aggregation                      |
| Sequential W1-W12 labels (not date-based)                 | Per CONTEXT.md decision, simpler for chart X-axis rendering                                   | Labels match spec exactly                                    |
| data-bu-trend DOM attribute                               | Enables Phase 2 to read trend data from DOM or JavaScript scope                               | Flexible access pattern for chart rendering                  |

## Verification Results

- API returns `business_unit_trends` with 3 business units (South Carolina, Wyoming, Michigan)
- Each unit has exactly 12 entries with sequential W1-W12 labels
- Missing weeks null-padded (W1-W7 for current data)
- First non-null entry change is null for all units
- Change calculations verified correct (e.g., SC: 3106-3112=-6, 3109-3106=3)
- All existing API keys preserved (no regressions)
- Dashboard loads without errors (200 response, no PHP errors)
- app.js served correctly after lint-staged formatting

## Deviations from Plan

None -- plan executed exactly as written.

## Next Phase Readiness

Phase 2 (Chart Rendering and Card Integration) can proceed:

- `dashboardData.business_unit_trends` is populated on every dashboard load
- `trendData` array is available in the card rendering loop
- `data-bu-trend` attribute exists on each card for DOM-based access
- Data shape is exactly what Phase 2 expects: `[{label: "W1", total_active: int|null, change: int|null}, ...]`
