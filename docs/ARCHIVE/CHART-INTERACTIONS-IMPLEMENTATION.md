# Chart Interactions Implementation Guide

**Created:** 2025-12-07
**Purpose:** Complete documentation of chart interaction features, aggregation system, and troubleshooting
**Status:** Production-ready

---

## Overview

This document covers the complete implementation of interactive chart features in the circulation dashboard:
- Historical trend visualization (TrendSlider)
- Subscriber list drill-down
- Excel/CSV export functionality
- Subscription length aggregation system

## Architecture

### Component Overview

```
User clicks chart bar
    ↓
Context Menu appears (context-menu.js)
    ↓
User selects action:
    ├─ "View Historical Trend" → TrendSlider (trend-slider.js)
    ├─ "View Subscribers" → SubscriberTablePanel (subscriber-table-panel.js)
    │                           ↓
    │                       Export buttons → ExportUtils (export-utils.js)
    └─ Context closes
```

### File Load Order (Critical)

Files MUST load in this exact order (defined in web/index.php):

1. **app.js** - Core state and utilities
2. **state-icons.js** - State abbreviations
3. **chart-layout-manager.js** - Responsive sizing
4. **donut-to-state-animation.js** - Chart animations
5. **detail_panel.js** - Detail panel rendering
6. **ui-enhancements.js** - Keyboard shortcuts
7. **context-menu.js** - Right-click menus
8. **export-utils.js** - Export functions
9. **subscriber-table-panel.js** - Subscriber table
10. **trend-slider.js** - Historical trends ⚠️ CRITICAL: Must load before integration
11. **chart-context-integration.js** - Wires everything together (MUST BE LAST)

**Why order matters:**
- `chart-context-integration.js` references functions from ALL previous files
- `trend-slider.js` must exist before integration wires click handlers
- Export utils must be loaded before subscriber panel tries to call them

---

## Feature 1: Historical Trend Visualization

### Implementation: TrendSlider

**File:** `web/assets/trend-slider.js`

**Purpose:** Show historical trends for a specific metric (e.g., "12 M (1 Year)" subscriptions over last 4 weeks)

**Key Features:**
- Separate slide-in panel (doesn't replace existing chart)
- Color continuity from source chart bar
- Chart.js area chart with gradient fill
- Time range selector (4 weeks, 8 weeks, 12 weeks)
- Keyboard shortcuts (ESC to close, arrow keys for range)

**Data Flow:**
```javascript
// 1. User right-clicks bar in detail panel chart
// 2. Context menu captures bar color
const color = chart.data.datasets[0].backgroundColor[index];

// 3. Integration calls TrendSlider with context
window.trendSlider.open({
    chartType: 'subscription_length',
    metric: '12 M (1 Year)',
    color: '#3B82F6',  // Bar color from source chart
    businessUnit: 'Wyoming',
    snapshotDate: '2025-12-02',
    timeRange: '4weeks'
});

// 4. TrendSlider fetches data from API
GET api.php?action=get_trend&business_unit=Wyoming&metric_type=subscription_length&metric_value=12%20M%20(1%20Year)&time_range=4weeks&end_date=2025-12-02

// 5. Renders Chart.js area chart with matching colors
```

**Color System:**
- Primary color: Matches source chart bar exactly
- Border: 20% darker than primary for definition
- Gradient: Fades from primary to transparent (30% opacity)

**Aggregation Support:**
For aggregated metrics (e.g., "12 M (1 Year)" represents multiple rate codes), TrendSlider:
1. Checks if `window.subscriptionLengthOriginalLabels[metric]` exists
2. Fetches trend data for each original label separately
3. Aggregates counts by date
4. Displays combined trend

---

## Feature 2: Subscription Length Aggregation

### Problem

The database contains subscription lengths in multiple formats representing the same duration:
- `12M`, `12 M`, `1Y`, `1 Y`, `52Wk` all mean "1 year"
- Each creates separate bars in charts
- User sees fragmented data instead of consolidated view

### Solution: Normalization + Aggregation

**Three-tier approach:**

#### Tier 1: Database Query Normalization (api.php)

All database queries normalize at query time using SQL CASE statements:

```php
// In detail panel queries
CASE
    WHEN ss.subscription_length IN ('12 M', '12M', '1 Y', '1Y') THEN '12 M (1 Year)'
    WHEN ss.subscription_length IN ('6 M', '6M') THEN '6 M (6 Months)'
    WHEN ss.subscription_length IN ('4 M', '4M') THEN '4 M (4 Months)'
    WHEN ss.subscription_length IN ('3 M', '3M') THEN '3 M (3 Months)'
    WHEN ss.subscription_length IN ('1 M', '1M') THEN '1 M (1 Month)'
    ELSE ss.subscription_length
END as subscription_length
```

**Critical:** All three types of queries must use identical normalization:
1. Detail panel aggregation queries
2. Trend data queries (`getMetricCount`)
3. Subscriber list queries (`getSubscriptionLengthSubscribers`)

#### Tier 2: Frontend Aggregation (detail_panel.js)

After receiving normalized data, frontend further aggregates:

```javascript
function normalizeSubscriptionLength(length) {
    const normalized = {
        '12 M': '12 M (1 Year)',
        '12M': '12 M (1 Year)',
        '1 Y': '12 M (1 Year)',
        '1Y': '12 M (1 Year)',
        '52Wk': '12 M (1 Year)',
        // ... more mappings
    };
    return normalized[length] || length;
}

// Aggregate chart data
const aggregated = {};
const originalLabelsMap = {};

chartData.forEach(d => {
    const normalized = normalizeSubscriptionLength(d.subscription_length);
    if (aggregated[normalized]) {
        aggregated[normalized] += d.count;
        originalLabelsMap[normalized].add(d.subscription_length);
    } else {
        aggregated[normalized] = d.count;
        originalLabelsMap[normalized] = new Set([d.subscription_length]);
    }
});

// Convert Sets to Arrays for API compatibility
window.subscriptionLengthOriginalLabels = {};
for (const [normalized, originalSet] of Object.entries(originalLabelsMap)) {
    window.subscriptionLengthOriginalLabels[normalized] = Array.from(originalSet);
}
```

**Key Points:**
- Uses `Set` to automatically deduplicate original labels
- Stores original labels in global `window.subscriptionLengthOriginalLabels` for drill-down
- Example: `{'12 M (1 Year)': ['12M', '1Y']}`

#### Tier 3: Drill-Down Aggregation (chart-context-integration.js)

When user clicks "View Subscribers" or "View Trend" on aggregated metric:

```javascript
// Check if this is an aggregated metric
const isAggregated = chartType === 'subscription_length' &&
    window.subscriptionLengthOriginalLabels &&
    window.subscriptionLengthOriginalLabels[metric];

if (isAggregated) {
    const originalLabels = window.subscriptionLengthOriginalLabels[metric];

    // Query each original label separately
    for (const originalLabel of originalLabels) {
        const url = `api.php?action=get_subscribers&metric_value=${encodeURIComponent(originalLabel)}...`;
        const response = await fetch(url);
        const result = await response.json();

        // Combine results
        allSubscribers = allSubscribers.concat(result.data.subscribers);
    }
}
```

---

## Feature 3: Subscriber List Drill-Down

### Implementation: SubscriberTablePanel

**File:** `web/assets/subscriber-table-panel.js`

**Purpose:** Display detailed subscriber list for a specific metric

**Data Structure:**

Panel accepts flexible data format:
```javascript
subscriberPanel.show({
    title: '12 M (1 Year) Subscribers - Wyoming',
    subtitle: '150 subscribers • Snapshot: 2025-12-02',
    data: [/* array of subscribers */],  // OR {subscribers: [...]}
    exportData: {  // Required for exports
        business_unit: 'Wyoming',
        metric: '12 M (1 Year)',
        count: 150,
        snapshot_date: '2025-12-02',
        subscribers: [/* same array */]
    }
});
```

**Why both `data` and `exportData`?**
- `data`: Simple array for display (backward compatible)
- `exportData`: Structured object with metadata for export filename generation

**Table Features:**
- Sticky header (scrolls with content)
- Alternating row colors (#F0FDFA / white)
- Hover highlighting (#CCFBF1)
- Delivery type badges (color-coded)
- Responsive width (75% of viewport)
- Keyboard support (ESC to close)

---

## Feature 4: Export Functionality

### Implementation: ExportUtils

**File:** `web/assets/export-utils.js`

**Export Flow:**

```javascript
// 1. User clicks "Export to Excel" in subscriber panel
handleExportExcel() {
    const exportPayload = this.data.exportData;  // Get structured data
    exportSubscriberList(exportPayload, 'excel');
}

// 2. exportSubscriberList formats data
function exportSubscriberList(subscriberData, exportType) {
    const { business_unit, metric, count, snapshot_date, subscribers } = subscriberData;

    // Generate filename
    const filename = `${business_unit}_${metric}_${snapshot_date}`.replace(/[^a-z0-9_-]/gi, '_');
    // Example: "Wyoming_12_M_(1_Year)_2025-12-02"

    // Format data for export
    const formattedData = formatSubscriberDataForExport(subscribers);

    // Export
    exportToExcel(formattedData, filename, {
        sheetName: `${metric} (${count})`
    });
}

// 3. exportToExcel creates styled workbook
function exportToExcel(data, filename, options) {
    const ws = XLSX.utils.json_to_sheet(data);

    // Apply styling (header colors, alternating rows, borders)
    // Add frozen header row
    // Add auto-filter
    // Calculate optimal column widths

    const timestamp = formatDateForFilename(new Date());
    XLSX.writeFile(wb, `${filename}_${timestamp}.xlsx`);
}
```

**Excel Styling:**
- Header: Teal background (#0891B2), white bold text, centered
- Rows: Alternating colors (#F0FDFA / white)
- Borders: Thin gray (#E5E7EB) on all cells
- Frozen: First row always visible
- Auto-filter: Enabled on all columns
- Column widths: Auto-calculated from content (12-50 characters)

**CSV Export:**
- UTF-8 BOM for Excel compatibility
- Quoted fields with escaped quotes
- Standard comma-separated format

**Filename Format:**
```
BusinessUnit_Metric_SnapshotDate_YYYYMMDD_HHMMSS.xlsx
Example: Wyoming_12_M_(1_Year)_2025-12-02_20251207_153045.xlsx
```

---

## Critical Integration Patterns

### Pattern 1: Context Passing

**Problem:** How to pass business unit, snapshot date, metric info from chart click to exports?

**Solution:** Context chaining through all components:

```javascript
// Step 1: Chart right-click captures context
showChartContextMenu(e.clientX, e.clientY, {
    chartType: 'subscription_length',
    metric: label,
    count: count,
    color: color,
    businessUnit: currentBusinessUnit,  // From detail panel global
    snapshotDate: currentSnapshotDate   // From detail panel global
});

// Step 2: Menu action passes full context
handleChartMenuAction('subscribers', context);

// Step 3: showSubscriberList includes context in panel data
subscriberPanel.show({
    title: `${metric} Subscribers - ${businessUnit}`,
    subtitle: `${count} subscribers • Snapshot: ${snapshotDate}`,
    data: allSubscribers,
    exportData: {  // ← Context bundled for exports
        business_unit: businessUnit,
        metric: metric,
        count: allSubscribers.length,
        snapshot_date: snapshotDate,
        subscribers: allSubscribers
    }
});

// Step 4: Export handlers use exportData
handleExportExcel() {
    exportSubscriberList(this.data.exportData, 'excel');
}
```

### Pattern 2: Flexible Data Structures

**Problem:** API returns data in different formats (`{data: {subscribers: []}}` vs `{data: []}`)

**Solution:** Flexible accessors that handle both:

```javascript
// In subscriber-table-panel.js
buildPanelHTML() {
    const { title, subtitle, data } = this.data;

    // Accept both formats
    const subscribers = Array.isArray(data)
        ? data
        : (data?.subscribers || []);

    const count = subscribers.length;
    // ... rest of rendering
}
```

### Pattern 3: Color Continuity

**Problem:** How to match trend chart colors to source bar colors?

**Solution:** Capture and pass color through context:

```javascript
// 1. Capture color at click time
const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
const index = elements[0].index;
const backgroundColor = chart.data.datasets[0].backgroundColor;
const color = Array.isArray(backgroundColor)
    ? backgroundColor[index]  // Array of colors
    : backgroundColor;         // Single color

// 2. Pass to TrendSlider
window.trendSlider.open({
    // ... other context
    color: color || '#3B82F6'  // Fallback to blue
});

// 3. Apply to trend chart
datasets: [{
    backgroundColor: hexToRGBA(this.context.color, 0.3),  // 30% opacity gradient
    borderColor: darkenColor(this.context.color, 20),     // 20% darker border
    // ...
}]
```

---

## Common Issues and Fixes

### Issue 1: Trend Charts Show Zero Counts

**Symptom:** Y-axis shows 0-1, tooltip shows "Count: 0" for all data points

**Root Cause:** API normalization mismatch
- Detail panel queries normalize at query time (CASE statement)
- Trend/subscriber queries used exact string matching
- Query for `'12 M (1 Year)'` doesn't match database `'12M'` or `'1Y'`

**Fix:** Add same CASE normalization to ALL queries

```php
// In getMetricCount() and getSubscriptionLengthSubscribers()
WHERE (
    CASE
        WHEN ss.subscription_length IN ('12 M', '12M', '1 Y', '1Y') THEN '12 M (1 Year)'
        ELSE ss.subscription_length
    END
) = :metric_value
```

**Files Changed:**
- `web/api.php` (lines 1572-1591, lines 1360-1397)

**How to Verify:**
- Right-click "12 M (1 Year)" bar → "View Historical Trend"
- Y-axis should show actual subscriber counts (100+), not 0-1
- Tooltip should show real counts

---

### Issue 2: Duplicate Labels in Aggregation

**Symptom:** Console shows `['12 M (1 Year)', '12 M (1 Year)']` for original labels

**Root Cause:** Using array `.push()` allowed duplicates

**Fix:** Use `Set` for automatic deduplication

```javascript
// Before (allowed duplicates)
originalLabelsMap[normalized] = [];
chartData.forEach(d => {
    originalLabelsMap[normalized].push(d.subscription_length);
});

// After (deduplicates automatically)
originalLabelsMap[normalized] = new Set([d.subscription_length]);
chartData.forEach(d => {
    originalLabelsMap[normalized].add(d.subscription_length);
});

// Convert to array for API
window.subscriptionLengthOriginalLabels[normalized] = Array.from(originalSet);
```

**Files Changed:**
- `web/assets/detail_panel.js` (lines 673-705)

---

### Issue 3: View Subscribers Returns "Invalid Action"

**Symptom:** Console error: `{success: false, error: 'Invalid action: get_subscribers'}`

**Root Cause:** PHP OPcache serving stale compiled code after API changes

**Fix:** Clear OPcache

```bash
docker exec circulation_web php -r "opcache_reset();"
```

**When to Clear:**
- After ANY change to PHP files (.php)
- JavaScript changes only need browser hard refresh (Cmd+Shift+R)

**Files Affected:**
- All .php files in `web/` directory
- Particularly `web/api.php` after adding new actions

---

### Issue 4: View Subscribers Shows 0 Subscribers

**Symptom:** Panel opens but shows "0 subscribers", even though API returns data

**Root Cause:** Data structure access mismatch
- API returns: `{success: true, data: {subscribers: [...], count: 150}}`
- Code was accessing: `result.data` (gets object with subscribers property)
- Panel expected: array directly

**Fix:** Access nested property correctly

```javascript
// Before (wrong)
allSubscribers = result.data;  // Gets {subscribers: [...], count: 150}

// After (correct)
allSubscribers = result.data?.subscribers || [];  // Gets array directly
```

**Files Changed:**
- `web/assets/chart-context-integration.js` (lines 268-286)

---

### Issue 5: Export Buttons Don't Work

**Symptom:** Clicking "Export to Excel" or "Export to CSV" does nothing

**Root Cause:** Missing metadata in export payload
- `exportSubscriberList()` expects: `{business_unit, metric, count, snapshot_date, subscribers}`
- Panel was passing: `[/* array of subscribers */]`
- Export function couldn't generate filename without metadata

**Fix:** Pass both display data AND export metadata

```javascript
// Update caller to include exportData
subscriberPanel.show({
    title: `${metric} Subscribers - ${businessUnit}`,
    subtitle: `${count} subscribers • Snapshot: ${snapshotDate}`,
    data: allSubscribers,  // For display
    exportData: {          // For exports
        business_unit: businessUnit,
        metric: metric,
        count: allSubscribers.length,
        snapshot_date: snapshotDate,
        subscribers: allSubscribers
    }
});

// Update handlers to use exportData
handleExportExcel() {
    const exportPayload = this.data.exportData || this.data.data;
    exportSubscriberList(exportPayload, 'excel');
}
```

**Files Changed:**
- `web/assets/chart-context-integration.js` (lines 303-309)
- `web/assets/subscriber-table-panel.js` (lines 366, 380)

---

## Testing Checklist

### After Making Changes to Chart Interactions

**Basic Functionality:**
- [ ] Right-click any chart bar shows context menu
- [ ] "View Historical Trend" opens TrendSlider
- [ ] Trend colors match source bar color
- [ ] Time range selector works (4w, 8w, 12w)
- [ ] ESC closes trend slider
- [ ] "View Subscribers" opens subscriber table
- [ ] Table shows correct subscriber count
- [ ] "Export to Excel" downloads .xlsx file
- [ ] "Export to CSV" downloads .csv file
- [ ] Excel file has styling (colors, frozen header, filters)
- [ ] Filename includes business unit, metric, date

**Aggregated Metrics:**
- [ ] Subscription length chart shows consolidated bars (not 12M, 1Y separate)
- [ ] Trend for "12 M (1 Year)" shows combined data from all variants
- [ ] Subscriber list for "12 M (1 Year)" includes all matching rate codes
- [ ] Export includes all aggregated subscribers

**Cross-Browser:**
- [ ] Chrome: All features work
- [ ] Safari: All features work
- [ ] Firefox: All features work

**Cache Busting:**
- [ ] Hard refresh (Cmd+Shift+R) after JavaScript changes loads new code
- [ ] PHP changes require OPcache clear
- [ ] Version parameters in script tags are current

---

## Deployment Checklist

### Before Deploying Chart Interaction Changes

1. **Test Locally:**
   - Run full testing checklist above
   - Verify all console logs removed (no 🔍 debug logs)
   - Check browser console for errors

2. **Update Version:**
   ```bash
   # In web/index.php, update cache-busting versions
   ?v=20251207  # Today's date
   ```

3. **Build and Push:**
   ```bash
   cd /Users/user/Development/work/_active/nwdownloads
   ./build-and-push.sh
   ```

4. **Deploy to Production:**
   ```bash
   # SSH to NAS
   sshpass -p 'Mojave48ice' ssh it@192.168.1.254
   cd /volume1/docker/nwdownloads

   # Pull latest image
   sudo /usr/local/bin/docker compose -f docker-compose.prod.yml pull

   # Restart with new image
   sudo /usr/local/bin/docker compose -f docker-compose.prod.yml up -d

   # Verify
   sudo /usr/local/bin/docker compose -f docker-compose.prod.yml ps
   ```

5. **Verify Production:**
   - Open http://192.168.1.254:8081/
   - Test at least one chart interaction
   - Check browser console for errors

---

## Architecture Decisions

### Why TrendSlider Instead of In-Place Transitions?

**Original Approach:** ChartTransitionManager
- Animated existing chart to transform into trend chart
- Complex state management (tracking original config to restore)
- 500+ lines of transition orchestration code
- Difficult to maintain

**New Approach:** TrendSlider
- Separate slide-in panel alongside original chart
- Simple: open/close, no state restoration needed
- Color continuity maintained through context passing
- User can reference original chart while viewing trend
- ~200 lines of straightforward code

**Decision Rationale:**
- Simpler is better (fewer bugs, easier maintenance)
- Better UX (can see both charts simultaneously)
- Easier to add features (time range selector, etc.)

### Why Three Tiers of Normalization?

**Why not normalize once?**

Different contexts need different levels:

1. **Database (SQL CASE):**
   - Ensures queries match data regardless of format
   - Can't rely on database always being clean
   - Historical data may have inconsistencies

2. **Frontend (JavaScript):**
   - Handles display aggregation
   - Combines data from different snapshots
   - Provides mapping for drill-down queries

3. **Drill-Down (API):**
   - Queries original labels to get all matching subscribers
   - Aggregates results client-side
   - Maintains data accuracy

**Could we normalize database data once?**
- Yes, but requires data migration
- Historical data may keep getting imported
- Three-tier approach is defensive programming

---

## Future Enhancements (Not Yet Implemented)

### Phase 2 Considerations

**Advanced Trend Features:**
- Export trend chart as image
- Compare trends between business units
- Annotations for significant events
- Zoom/pan for longer time ranges

**Enhanced Subscriber Lists:**
- Pagination for >1000 subscribers
- Column sorting
- Advanced filtering
- Inline editing

**Better Aggregation:**
- Automatic detection of equivalent subscription lengths
- Admin interface to configure aggregation rules
- Visual indicator showing which labels are aggregated

**Performance:**
- Cache trend data client-side
- Lazy load subscriber lists (virtual scrolling)
- Prefetch next time range

---

## File Reference

### Modified Files (This Implementation)

| File | Lines Changed | Purpose |
|------|--------------|---------|
| `web/assets/trend-slider.js` | New file (418 lines) | Historical trend visualization |
| `web/assets/chart-context-integration.js` | 239-310 | Wiring trends and subscribers |
| `web/assets/detail_panel.js` | 673-705 | Aggregation logic |
| `web/assets/subscriber-table-panel.js` | 113-117, 363-386 | Flexible data handling, export fixes |
| `web/api.php` | 1360-1397, 1572-1591 | Normalization in queries |
| `web/index.php` | Script tags | Load order update |

### Deleted Files

| File | Reason |
|------|--------|
| `web/assets/chart-transition-manager.js` | Replaced by TrendSlider (simpler approach) |

### Dependencies

**External Libraries (CDN):**
- Chart.js 4.4.0 - Chart rendering
- SheetJS (XLSX) - Excel export
- jsPDF + html2canvas - PDF export (future)
- Flatpickr - Date picker

**Internal Dependencies:**
- app.js provides: `formatNumber()`, `API_BASE`, `currentBusinessUnit`, `currentSnapshotDate`
- detail_panel.js provides: Global state for current view
- export-utils.js provides: `exportSubscriberList()`, `exportToExcel()`, `exportToCSV()`

---

## Troubleshooting Quick Reference

| Symptom | Most Likely Cause | Fix |
|---------|------------------|-----|
| Trend shows zero counts | API normalization mismatch | Add CASE to query |
| Duplicate labels | Using array instead of Set | Use Set for deduplication |
| "Invalid action" error | Stale PHP OPcache | Clear OPcache |
| Empty subscriber list | Data structure access | Use `result.data.subscribers` |
| Export doesn't work | Missing metadata | Pass `exportData` object |
| Panel doesn't appear | JavaScript not loaded | Hard refresh browser |
| Colors don't match | Color not captured | Check `backgroundColor` index |

---

## Support Resources

**Documentation:**
- This file (CHART-INTERACTIONS-IMPLEMENTATION.md)
- ARCHITECTURE-REFACTOR-GUIDE.md - General architecture
- DETAIL-PANEL-ENHANCEMENTS-SUMMARY.md - Detail panel features

**Code Comments:**
- Each file has LOAD ORDER header
- Functions have JSDoc comments
- Complex logic has inline explanations

**Testing:**
- See "Testing Checklist" section above
- Console logs (temporarily add 🔍 prefix for debugging)

---

*Document created: 2025-12-07*
*Last updated: 2025-12-07*
*Author: Claude Sonnet 4.5*
*Review: Pending deployment to production*
