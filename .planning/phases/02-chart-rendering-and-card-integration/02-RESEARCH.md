# Phase 2: Chart Rendering and Card Integration - Research

**Researched:** 2026-02-09
**Domain:** Chart.js v4.4.0 mini line charts inside existing BU cards (vanilla JS, no bundler)
**Confidence:** HIGH

## Summary

This phase adds inline 12-week trend mini-charts inside each business unit card on the circulation dashboard. The data pipeline is complete from Phase 1 -- trend data is already embedded in the API response (`dashboardData.business_unit_trends`) and stored as a `data-bu-trend` DOM attribute on each card. The implementation is pure Chart.js v4.4.0 configuration work: creating small line charts with area fill that visually match the existing company-wide trend chart.

The technical approach is straightforward because the codebase already has an established pattern for creating Chart.js instances on dynamically rendered HTML. The `renderBusinessUnits()` function in `app.js` (line 762) already builds HTML via template literals, sets the container content, then loops through business units to create doughnut charts on the newly rendered canvases (lines 876-921). The trend mini-charts follow this identical pattern -- add a canvas element to the HTML template, then instantiate Chart.js after DOM insertion.

**Primary recommendation:** Add trend chart canvas to the BU card HTML template between the comparison bar and the donut section. Create charts in the same post-DOM-update loop that creates the doughnut charts. Use Chart.js progressive line animation for the draw-in effect. Use tooltip callbacks for the custom week-over-week change display.

## Standard Stack

### Core

| Library  | Version | Purpose         | Why Standard                                  |
| -------- | ------- | --------------- | --------------------------------------------- |
| Chart.js | 4.4.0   | Chart rendering | Already loaded via CDN in `index.php` line 21 |

### Supporting

No additional libraries needed. Everything required is built into Chart.js v4.4.0:

- Progressive line animation (native)
- Tooltip callbacks (native)
- Responsive canvas sizing (native)
- Area fill (native `fill: true`)

### Alternatives Considered

| Instead of                 | Could Use                  | Tradeoff                                                                                                 |
| -------------------------- | -------------------------- | -------------------------------------------------------------------------------------------------------- |
| Chart.js native tooltips   | External HTML tooltips     | More customizable HTML but adds complexity; native callbacks are sufficient for "W5: 1,234 (+12)" format |
| Progressive line animation | Default Chart.js animation | Default animation scales from center; progressive draws left-to-right as specified in CONTEXT.md         |

**Installation:** None required. Chart.js 4.4.0 is already loaded globally.

## Architecture Patterns

### Recommended Code Structure

No new files needed. All changes go into the existing `renderBusinessUnits()` function in `web/assets/js/core/app.js`.

```
web/assets/js/core/app.js
  renderBusinessUnits()     # Modify: add canvas to HTML template + chart creation
  (new helper function)     # Add: createBUTrendChart(canvasId, trendData)
```

### Pattern 1: Inline Canvas in Template Literal + Post-DOM Chart Creation

**What:** Add a `<canvas>` element to the BU card HTML template string, then create Chart.js instances after the DOM has been updated with the new content.

**When to use:** Always -- this is the established codebase pattern (used for doughnut charts already).

**Why this works:** The `renderBusinessUnits()` function already:

1. Builds HTML string with canvas elements (line 820: `<canvas id="${chartId}">`)
2. Sets the container's content (line 874)
3. Loops through units to create Chart.js instances (lines 876-921)

The trend chart canvas and Chart.js creation follow this exact same pattern.

**Existing pattern (doughnut chart, from app.js lines 818-826):**

```javascript
// In HTML template:
<div class="relative" style="width: 160px; height: 160px;">
    <canvas id="${chartId}"></canvas>
</div>

// After DOM update:
const ctx = document.getElementById(chartId);
businessUnitCharts[unitName] = new Chart(ctx, { type: 'doughnut', ... });
```

**New pattern (trend mini-chart) -- same approach:**

```javascript
// In HTML template (between comparison bar and donut section):
<div class="mt-4 pt-4 border-t border-gray-100">
  <div class="text-xs font-medium text-gray-500 mb-2">12-Week Trend</div>
  <div style="position: relative; height: 120px;">
    <canvas id="${trendChartId}"></canvas>
  </div>
</div>;

// After DOM update (in same loop as doughnut creation):
const trendCtx = document.getElementById(trendChartId);
createBUTrendChart(trendCtx, trendData);
```

### Pattern 2: Chart Instance Lifecycle Management

**What:** Store trend chart instances alongside doughnut charts, destroy them before re-rendering.

**When to use:** Every time `renderBusinessUnits()` is called (happens on navigation, refresh, comparison mode change).

**Critical detail:** The existing code at line 776 destroys all `businessUnitCharts` before re-rendering:

```javascript
Object.values(businessUnitCharts).forEach(chart => chart.destroy());
businessUnitCharts = {};
```

Trend chart instances MUST also be stored and destroyed. Two approaches:

1. Store trend charts in the same `businessUnitCharts` object with a different key (e.g., `"South Carolina_trend"`)
2. Create a separate `businessUnitTrendCharts` object

**Recommendation:** Use a separate object (`businessUnitTrendCharts = {}`) for clarity. Add its destroy loop next to the existing one.

### Pattern 3: Responsive Canvas Container

**What:** Use a `position: relative` container with fixed height, `responsive: true`, and `maintainAspectRatio: false`.

**Why:** Chart.js requires the canvas parent to be relatively positioned and dedicated to the canvas only (per Chart.js responsive docs). Setting `maintainAspectRatio: false` with a fixed container height gives us control over chart dimensions while still being width-responsive.

**Source:** Chart.js official responsive docs (verified via Context7)

```html
<!-- Container must have position: relative and explicit height -->
<div style="position: relative; height: 120px;">
  <canvas id="trendChart"></canvas>
</div>
```

```javascript
options: {
    responsive: true,
    maintainAspectRatio: false,  // Use container height, not aspect ratio
    // ...
}
```

### Anti-Patterns to Avoid

- **Setting canvas width/height attributes directly:** Chart.js manages canvas dimensions via the container. Setting `width`/`height` on the canvas element conflicts with Chart.js responsive behavior.
- **Creating charts before DOM insertion:** The canvas element must be in the DOM before `new Chart()`. The existing pattern (build HTML string -> set content -> loop to create charts) is correct.
- **Forgetting to destroy old instances:** Chart.js instances hold references to canvas elements. Not destroying before re-render causes memory leaks and "Canvas already in use" warnings.
- **Using `+=` for container content updates:** Already avoided in codebase, but worth noting -- appending to content destroys and recreates all child nodes, breaking existing Chart.js instances.

## Don't Hand-Roll

| Problem                           | Don't Build                       | Use Instead                                                                     | Why                                                                                                                                                                                |
| --------------------------------- | --------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Progressive line animation        | Custom requestAnimationFrame loop | Chart.js `animation.x` and `animation.y` with delay callbacks                   | Chart.js has a built-in progressive line sample that draws left-to-right. Verified via Context7 with exact code pattern.                                                           |
| Tooltip formatting                | External HTML tooltip system      | Chart.js `tooltip.callbacks.label` and `tooltip.callbacks.title`                | Native callbacks support full customization including return of styled strings. Sufficient for "W5: 1,234 (+12)" format.                                                           |
| Responsive sizing                 | Manual resize listeners           | Chart.js `responsive: true` + `maintainAspectRatio: false` + relative container | Chart.js handles ResizeObserver internally. Adding manual listeners creates conflicts.                                                                                             |
| Week-over-week change calculation | Pre-computing in PHP/API          | Compute in tooltip callback from dataset                                        | Data for adjacent weeks is already in the chart dataset. `context.dataIndex` gives the current index; `dataset.data[index - 1]` gives the previous week. No backend change needed. |

**Key insight:** The entire Phase 2 implementation is Chart.js configuration. Zero new libraries, zero new API endpoints, zero new HTML pages. It is adding a Chart.js config object and a canvas element to existing code.

## Common Pitfalls

### Pitfall 1: Progressive Animation Breaks Area Fill

**What goes wrong:** The progressive line animation (drawing left-to-right) uses `from: NaN` on the x-axis, which initially hides points. The `fill: true` option may not render correctly during animation because the fill needs points to be visible.

**Why it happens:** The `fill` plugin draws area between the line and the axis. During progressive animation, points start as NaN (invisible) and animate into position. The fill may appear jarring -- popping in fully once animation completes rather than drawing progressively.

**How to avoid:** Test the combination of `fill: true` with progressive animation. If fill behavior is unacceptable, two options:

1. Use standard Chart.js default animation (fade/scale in) instead of progressive -- simpler and fill works cleanly
2. Keep progressive animation but set `fill: false` during animation, then update to `fill: true` on animation complete

**Warning signs:** Area fill appears as a sudden block rather than gradually revealing. Test this early.

**Recommendation:** Start with the standard default animation (`animation: { duration: 1000 }`) which handles fill correctly. Only switch to progressive line if the user explicitly notices and requests the left-to-right draw effect. The default animation already provides a smooth line appearance.

### Pitfall 2: Chart Height Conflicts with Card Click Handler

**What goes wrong:** The entire BU card has an `onclick="openDetailPanel(...)"` handler. Chart.js canvas captures mouse events for tooltips/hover. Clicking on the chart area may simultaneously trigger the card's onclick AND Chart.js's internal handlers.

**Why it happens:** Event bubbling. Chart.js click events and the card's onclick both fire.

**How to avoid:** Since CONTEXT.md specifies "No click actions -- hover-only interaction", configure Chart.js with `events: ['mousemove', 'mouseout']` to exclude click events entirely from the chart. Additionally, add `onclick="event.stopPropagation()"` on the chart container div to prevent chart hover area clicks from opening the detail panel.

**Warning signs:** Clicking on the trend chart area opens the detail panel.

### Pitfall 3: Canvas ID Collisions

**What goes wrong:** The doughnut chart already uses `chart-${unitName}` as its canvas ID. If the trend chart uses a similar naming scheme, IDs collide.

**Why it happens:** Both charts exist in the same card, both need unique IDs.

**How to avoid:** Use a distinct prefix: `trend-${unitName}` for trend charts vs the existing `chart-${unitName}` for doughnut charts. The codebase already uses this convention in `renderBusinessUnitDetail()` (line 1447: `trend-${unitName.replace(...)}`).

### Pitfall 4: Empty/Sparse Trend Data Handling

**What goes wrong:** A BU might have fewer than 12 weeks of data, or no data at all. The chart needs to handle both gracefully.

**Why it happens:** New business units or data gaps. Per CONTEXT.md: "Always show 12 positions on X-axis" and "Missing/padded weeks show as zero value."

**How to avoid:** Before creating the chart, pad the trend data array to exactly 12 entries. If `trendData.length === 0`, show a "No data available" text message instead of an empty chart. The padding logic should:

1. Create labels W1-W12
2. Map existing data by week_num
3. Fill missing weeks with `0` for total_active (not `null` -- CONTEXT.md says "line dips to bottom")

### Pitfall 5: Mobile Card Layout with Added Chart

**What goes wrong:** Adding a 120px-height chart between the comparison bar and donut section significantly increases card height, especially on mobile where cards stack vertically.

**Why it happens:** The card already contains header, progress bar, donut chart (160px), vacation info, and delivery legend. Adding another 120px chart plus header and spacing could make cards 500+ pixels tall.

**How to avoid:** This is listed under "Claude's Discretion" in CONTEXT.md. Recommendation: use a compact height (100-120px) for the trend chart. On mobile screens (< 768px), consider reducing to 80px or hiding Y-axis padding to keep cards from becoming excessively tall.

## Code Examples

### Example 1: BU Trend Chart Configuration (Core Chart Config)

Source: Derived from existing `renderTrendChart()` in app.js (line 620) + Chart.js v4.4.0 docs (Context7)

```javascript
function createBUTrendChart(canvasId, trendData) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return null;

  // Handle no data case
  if (!trendData || trendData.length === 0) {
    // Show "No data available" message in container
    const container = ctx.parentNode;
    container.textContent = '';
    const msg = document.createElement('div');
    msg.className = 'flex items-center justify-center h-full text-sm text-gray-400';
    msg.textContent = 'No data available';
    container.appendChild(msg);
    return null;
  }

  // Pad to 12 weeks (CONTEXT.md: always show 12 positions)
  const labels = [];
  const dataPoints = [];
  for (let i = 1; i <= 12; i++) {
    labels.push('W' + i);
    const weekData = trendData.find(d => d.week_num === i);
    dataPoints.push(weekData ? weekData.total_active : 0);
  }

  // Calculate smart scale (filter out zero-padded values for scale)
  const realValues = dataPoints.filter(v => v > 0);
  let scaleMin, scaleMax;
  if (realValues.length > 0) {
    const min = Math.min(...realValues);
    const max = Math.max(...realValues);
    const padding = (max - min) * 0.15 || 50;
    scaleMin = Math.max(0, Math.floor((min - padding) / 10) * 10);
    scaleMax = Math.ceil((max + padding) / 10) * 10;
  } else {
    scaleMin = 0;
    scaleMax = 100;
  }

  return new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Total Active',
          data: dataPoints,
          borderColor: '#3b82f6', // Match company-wide chart
          backgroundColor: 'rgba(59, 130, 246, 0.1)', // Match company-wide chart
          tension: 0.4, // Match company-wide chart
          fill: true, // Match company-wide chart
          pointRadius: 3, // Slightly smaller than company-wide (4)
          pointHoverRadius: 5, // Slightly smaller than company-wide (6)
          pointBackgroundColor: '#3b82f6',
          pointBorderColor: '#fff',
          pointBorderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1000,
        easing: 'easeOutQuart',
      },
      interaction: {
        intersect: true, // CONTEXT.md: "highlights individual data point only"
        mode: 'nearest', // Find nearest point, not index-based crosshair
      },
      events: ['mousemove', 'mouseout'], // No click events (prevent card onclick conflict)
      plugins: {
        legend: { display: false }, // No legend for mini chart
        tooltip: {
          // Custom tooltip with week-over-week change
          callbacks: {
            title: function (tooltipItems) {
              return tooltipItems[0].label; // "W5"
            },
            label: function (context) {
              const value = context.parsed.y;
              const index = context.dataIndex;
              const formatted = formatNumber(value);

              // W1 (index 0): no change indicator
              if (index === 0) {
                return formatted;
              }

              // Calculate week-over-week change
              const prevValue = context.dataset.data[index - 1];
              if (prevValue === 0 || value === 0) {
                return formatted; // Skip change for zero-padded weeks
              }
              const change = value - prevValue;
              const sign = change >= 0 ? '+' : '';
              return formatted + ' (' + sign + formatNumber(change) + ')';
            },
            // Color-code the change
            labelTextColor: function (context) {
              const index = context.dataIndex;
              if (index === 0) return '#fff';
              const value = context.parsed.y;
              const prevValue = context.dataset.data[index - 1];
              if (prevValue === 0 || value === 0) return '#fff';
              const change = value - prevValue;
              if (change > 0) return '#4ade80'; // Green
              if (change < 0) return '#f87171'; // Red
              return '#fff'; // Neutral
            },
          },
        },
      },
      scales: {
        x: {
          display: true,
          grid: { display: false }, // Match company-wide chart
          ticks: {
            font: { size: 9 }, // Compact for mini chart
            maxRotation: 0,
          },
        },
        y: {
          display: false, // CONTEXT.md: "Y-axis labels: hidden"
          min: scaleMin,
          max: scaleMax,
        },
      },
    },
  });
}
```

### Example 2: Progressive Line Animation (Alternative -- if default not satisfying)

Source: Chart.js official progressive line sample (verified via Context7)

```javascript
// Only use this if the default animation (Example 1) doesn't feel like "draws in from left to right"
// This is the official Chart.js progressive line animation pattern

const totalDuration = 1500; // 1.5 seconds for 12 points
const delayBetweenPoints = totalDuration / 12;

const previousY = ctx =>
  ctx.index === 0
    ? ctx.chart.scales.y.getPixelForValue(100)
    : ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.index - 1].getProps(['y'], true).y;

const progressiveAnimation = {
  x: {
    type: 'number',
    easing: 'linear',
    duration: delayBetweenPoints,
    from: NaN,
    delay(ctx) {
      if (ctx.type !== 'data' || ctx.xStarted) return 0;
      ctx.xStarted = true;
      return ctx.index * delayBetweenPoints;
    },
  },
  y: {
    type: 'number',
    easing: 'linear',
    duration: delayBetweenPoints,
    from: previousY,
    delay(ctx) {
      if (ctx.type !== 'data' || ctx.yStarted) return 0;
      ctx.yStarted = true;
      return ctx.index * delayBetweenPoints;
    },
  },
};

// Use in chart config:
// options: { animation: progressiveAnimation, ... }
```

**Important caveat:** Progressive animation may cause area fill (`fill: true`) to render strangely. Test before committing to this approach. Default animation is the safer choice.

### Example 3: HTML Template Insertion Point

The chart HTML goes between the comparison bar (closing `</div>` after the progress bar) and the donut section (the `<div class="mt-4 pt-4 border-t border-gray-100">` block).

```javascript
// Current structure (lines 809-815 of app.js):
//   <div class="mb-4">           <!-- comparison bar -->
//       <div class="w-full bg-gray-200 rounded-full h-2">
//           <div class="progress-bar ..."></div>
//       </div>
//   </div>
//
//   *** INSERT TREND CHART HERE ***
//
//   <div class="mt-4 pt-4 border-t border-gray-100">   <!-- donut section -->

// New HTML to insert:
const trendChartId = `trend-${unitName.replace(/\s+/g, '-').toLowerCase()}`;

// Template string to add after the comparison bar div:
`
<div class="mt-4 pt-4 border-t border-gray-100">
    <div class="text-xs font-medium text-gray-500 mb-2">12-Week Trend</div>
    <div style="position: relative; height: 120px;" onclick="event.stopPropagation()">
        <canvas id="${trendChartId}"></canvas>
    </div>
</div>
`;
```

### Example 4: Chart Cleanup Pattern

```javascript
// Declare at top of file (alongside existing businessUnitCharts):
let businessUnitTrendCharts = {};

// In renderBusinessUnits(), add destruction alongside existing pattern:
Object.values(businessUnitCharts).forEach(chart => chart.destroy());
businessUnitCharts = {};
Object.values(businessUnitTrendCharts).forEach(chart => chart.destroy());
businessUnitTrendCharts = {};

// After chart creation:
const trendChartInstance = createBUTrendChart(trendChartId, trendData);
if (trendChartInstance) {
  businessUnitTrendCharts[unitName] = trendChartInstance;
}
```

## State of the Art

| Old Approach                                                  | Current Approach                                         | When Changed | Impact                                       |
| ------------------------------------------------------------- | -------------------------------------------------------- | ------------ | -------------------------------------------- |
| Chart.js v2/v3 `options.animation.duration`                   | v4 per-property animation (`animation.x`, `animation.y`) | Chart.js v3+ | Enables progressive line draw animation      |
| Chart.js v2 `tooltips.callbacks`                              | v4 `options.plugins.tooltip.callbacks`                   | Chart.js v3  | Tooltip config moved under plugins namespace |
| Manual canvas resize with `window.addEventListener('resize')` | `responsive: true` with ResizeObserver                   | Chart.js v3+ | No manual resize handling needed             |

**Deprecated/outdated:**

- `options.tooltips` (v2 namespace): Use `options.plugins.tooltip` in v4
- `options.legend` (v2 namespace): Use `options.plugins.legend` in v4
- Setting `canvas.width` / `canvas.height` attributes: Use container sizing with `maintainAspectRatio: false`

## Open Questions

1. **Progressive animation + fill compatibility**
   - What we know: Progressive line animation is a documented Chart.js pattern. Area fill is a separate feature. Both work independently.
   - What's unclear: Whether `fill: true` renders cleanly during progressive animation (points start as NaN). Context7 examples don't combine fill with progressive animation.
   - Recommendation: Start with default animation (`duration: 1000, easing: 'easeOutQuart'`). Only switch to progressive line if explicitly requested. Flag this as a test-and-verify item in the plan.

2. **Optimal mini-chart height**
   - What we know: CONTEXT.md says "compact to medium" and leaves height to Claude's discretion. Current card has ~500px total height with donut (160px) + vacation info.
   - What's unclear: Exact pixel height that balances visibility with card compactness.
   - Recommendation: Start with 120px. This is tall enough to see trends clearly, compact enough to not dominate the card. Can be adjusted during verification.

3. **Tooltip color-coding mechanism**
   - What we know: Chart.js `labelTextColor` callback can return different colors per tooltip item.
   - What's unclear: Whether `labelTextColor` applies to the entire label text or can color just a portion (the change indicator).
   - Recommendation: `labelTextColor` colors the entire label line. For "1,234 (+12)" where only "(+12)" should be green, we'd need an external HTML tooltip. However, coloring the entire line green/red is acceptable -- the color communicates direction even applied to the whole label. Keep it simple with `labelTextColor`.

## Sources

### Primary (HIGH confidence)

- `/websites/chartjs` (Context7) - Progressive line animation, responsive configuration, tooltip callbacks
- `/chartjs/chart.js` (Context7) - Tooltip callback API reference, configuration options
- `web/assets/js/core/app.js` (codebase) - Existing chart patterns, BU card HTML template, lifecycle management
- `web/index.php` (codebase) - CDN script tags confirming Chart.js 4.4.0, Tailwind CSS

### Secondary (MEDIUM confidence)

- `.planning/phases/02-chart-rendering-and-card-integration/02-CONTEXT.md` - User decisions constraining implementation
- `docs/DESIGN-SYSTEM.md` - Component patterns and anti-patterns

### Tertiary (LOW confidence)

- None -- all critical findings verified via Context7 or codebase inspection

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH - Chart.js 4.4.0 already loaded, verified via CDN tag in index.php
- Architecture: HIGH - Follows existing codebase pattern (doughnut chart creation in renderBusinessUnits)
- Pitfalls: HIGH - Canvas lifecycle, event bubbling, and responsive sizing are well-documented Chart.js concerns verified via Context7
- Animation: MEDIUM - Progressive animation is documented but interaction with fill not explicitly tested in docs
- Tooltip color-coding: MEDIUM - labelTextColor callback confirmed via Context7, but granular per-word coloring not possible without external tooltip

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (stable -- Chart.js 4.4.0 is locked, codebase patterns established)
