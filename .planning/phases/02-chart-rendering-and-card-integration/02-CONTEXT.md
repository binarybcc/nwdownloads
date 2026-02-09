# Phase 2: Chart Rendering and Card Integration - Context

**Gathered:** 2026-02-09
**Status:** Ready for planning

<domain>
## Phase Boundary

Render an interactive 12-week trend chart inside each business unit card on the circulation dashboard. The chart uses Chart.js, displays weekly Total Active subscriber counts, and matches the visual style of the existing company-wide trend chart. Data is already available from Phase 1 via `dashboardData.business_unit_trends` and `data-bu-trend` DOM attributes.

</domain>

<decisions>
## Implementation Decisions

### Chart visual style

- Exact same blue line + area fill as the company-wide trend chart — no per-BU color differentiation
- Grid lines: match whatever the existing company-wide trend chart does
- Y-axis labels: hidden (rely on tooltips for exact values — keeps chart compact in card)
- Animate line drawing on load (draws in from left to right)

### Tooltip & interaction

- Tooltip shows: week label + subscriber count + week-over-week change (e.g., "W5: 1,234 (+12)")
- Change indicator is color-coded: green for increase, red for decrease
- First data point (W1) shows count only, no change indicator (no prior week to compare)
- Hover highlights the individual data point only — no vertical crosshair line
- No click actions — hover-only interaction, charts are informational

### Card layout integration

- Section header "12-Week Trend" displayed above the chart
- Chart positioned between the comparison bar and the donut chart

### Data edge cases

- Always show 12 positions on X-axis, even with fewer weeks of data
- Missing/padded weeks show as zero value — line dips to bottom then rises when data starts
- If BU has no trend data at all: show "No data available" text message in the chart area
- First week (W1) tooltip omits the change indicator entirely

### Claude's Discretion

- Chart height (approximate range: compact to medium, whatever fits card proportions best)
- Responsive behavior on mobile/narrow screens (simplify or maintain as appropriate)
- Visual separators between chart and adjacent card sections (divider lines vs spacing — match existing card patterns)

</decisions>

<specifics>
## Specific Ideas

- Chart should be an exact visual clone of the company-wide trend chart style (blue line, area fill) — consistency across the dashboard is the priority
- Animation on load adds polish — line draws in smoothly
- Tooltip with week-over-week change (color-coded green/red) provides quick insight without needing to compare data points visually

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

_Phase: 02-chart-rendering-and-card-integration_
_Context gathered: 2026-02-09_
