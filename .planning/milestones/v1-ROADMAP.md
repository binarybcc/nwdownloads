# Milestone v1: Business Unit 12-Week Trend Charts

**Status:** SHIPPED 2026-02-09
**Phases:** 1-2
**Total Plans:** 2

## Overview

This feature adds a compact, interactive 12-week trend chart to each business unit card on the circulation dashboard. Phase 1 ensures the API delivers per-business-unit trend data. Phase 2 renders the chart inside each card with proper styling, interactivity, and responsive layout. Two phases, clean dependency: data first, then visualization.

## Phases

### Phase 1: Business Unit Trend Data

**Goal**: Each business unit card can fetch its own 12-week trend history from the API
**Depends on**: Nothing (first phase)
**Requirements**: DATA-01
**Plans**: 1 plan

Plans:

- [x] 01-01-PLAN.md -- Extend trend API with per-BU filtering and wire frontend data flow

**Details:**

- Added `getBusinessUnitTrendData()` and `getAllBusinessUnitTrends()` PHP functions
- Embedded `business_unit_trends` key in existing overview API response (single-request sync)
- MAX(snapshot_date) subquery for multi-upload dedup per calendar week
- Null-padded missing weeks with sequential W1-W12 labels
- Frontend wiring: `data-bu-trend` DOM attribute on each BU card
- Files modified: `web/api/legacy.php`, `web/assets/js/core/app.js`

### Phase 2: Chart Rendering and Card Integration

**Goal**: Users see an interactive trend chart inside each business unit card showing 12 weeks of subscriber history
**Depends on**: Phase 1
**Requirements**: CHART-01, CHART-02, CHART-03, CHART-04, CARD-01, CARD-02
**Plans**: 1 plan

Plans:

- [x] 02-01-PLAN.md -- Create BU trend chart helper, integrate into card template with responsive layout and tooltips

**Details:**

- Created `createBUTrendChart(canvasId, trendData)` helper function with no-data fallback
- Chart.js mini-charts with blue #3b82f6 line and rgba area fill (matches company-wide chart style)
- Auto-scaled Y-axis fitted to each BU's data range with 15% padding
- Color-coded hover tooltips showing formatted subscriber count and week-over-week change
- Chart event isolation via stopPropagation and events config (hover only, no click interference)
- `bu-trend-` canvas ID prefix to avoid collision with drill-down panel `trend-` IDs
- Nulls with spanGaps instead of zeros for missing weeks (visually cleaner)
- `businessUnitTrendCharts` lifecycle management (destroy before recreate on re-render)
- Files modified: `web/assets/js/core/app.js`

## Progress

| Phase                                   | Plans Complete | Status   | Completed  |
| --------------------------------------- | -------------- | -------- | ---------- |
| 1. Business Unit Trend Data             | 1/1            | Complete | 2026-02-09 |
| 2. Chart Rendering and Card Integration | 1/1            | Complete | 2026-02-09 |

---

## Milestone Summary

**Key Decisions:**

- Embed trends in overview response (not separate endpoint) — single HTTP request, data always in sync
- MAX(snapshot_date) subquery for dedup — handles multiple CSV uploads in same week
- Sequential W1-W12 labels (not date-based) — simpler for chart X-axis rendering
- data-bu-trend DOM attribute — flexible Phase 2 access pattern
- bu-trend- canvas ID prefix — avoids collision with drill-down panel
- Nulls with spanGaps instead of zeros — prevents misleading "dip to zero" visual
- Default animation instead of progressive line draw — safer with fill:true
- Tooltip reads pre-computed change from Phase 1 data via closure — no duplicate calculation

**Issues Resolved:**

- Canvas ID collision between card mini-charts and drill-down panel charts (auto-fixed with prefix)

**Issues Deferred:**

- None

**Technical Debt Incurred:**

- Commented console.log at app.js line 1820 (INFO severity, harmless)

---

_For current project status, see .planning/ROADMAP.md_
_Archived: 2026-02-09 as part of v1 milestone completion_
