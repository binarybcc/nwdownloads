# Roadmap: Business Unit 12-Week Trend Charts

## Overview

This feature adds a compact, interactive 12-week trend chart to each business unit card on the circulation dashboard. Phase 1 ensures the API delivers per-business-unit trend data. Phase 2 renders the chart inside each card with proper styling, interactivity, and responsive layout. Two phases, clean dependency: data first, then visualization.

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Business Unit Trend Data** - API serves 12-week trend data filtered per business unit
- [ ] **Phase 2: Chart Rendering and Card Integration** - Interactive trend chart rendered inside each business unit card

## Phase Details

### Phase 1: Business Unit Trend Data

**Goal**: Each business unit card can fetch its own 12-week trend history from the API
**Depends on**: Nothing (first phase)
**Requirements**: DATA-01
**Success Criteria** (what must be TRUE):

1. Calling the API with a business unit parameter returns 12 weekly Total Active values for that business unit only
2. The data covers exactly 12 weeks (or as many weeks as exist if fewer than 12 are available)
3. Each data point includes the week label and the Total Active subscriber count for that business unit

**Plans:** 1 plan

Plans:

- [x] 01-01-PLAN.md -- Extend trend API with per-BU filtering and wire frontend data flow

### Phase 2: Chart Rendering and Card Integration

**Goal**: Users see an interactive trend chart inside each business unit card showing 12 weeks of subscriber history
**Depends on**: Phase 1
**Requirements**: CHART-01, CHART-02, CHART-03, CHART-04, CARD-01, CARD-02
**Success Criteria** (what must be TRUE):

1. Each business unit card displays a small line chart with blue area fill matching the style of the company-wide trend chart
2. The chart X-axis shows week labels (W1, W2, etc.) along the bottom
3. Hovering over any data point on the chart shows a tooltip with the exact subscriber count for that week
4. The chart Y-axis range is scaled to the specific business unit's data (not company totals)
5. The chart is positioned between the comparison bar and the donut chart, and fills the card width on any screen size
   **Plans**: TBD

Plans:

- [ ] 02-01: Create Chart.js line chart component with area fill, week labels, tooltips, and auto-scaled Y-axis
- [ ] 02-02: Integrate chart into business unit card layout with responsive sizing

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2

| Phase                                   | Plans Complete | Status      | Completed  |
| --------------------------------------- | -------------- | ----------- | ---------- |
| 1. Business Unit Trend Data             | 1/1            | Complete    | 2026-02-09 |
| 2. Chart Rendering and Card Integration | 0/2            | Not started | -          |
