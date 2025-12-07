# Architecture Refactor Guide

**Created:** 2025-12-06
**Purpose:** Step-by-step prompts for Sonnet 4.5 to refactor the dashboard JavaScript architecture
**Based on:** Opus 4.5 architectural review

---

## Overview

This guide contains everything needed to direct Claude Sonnet 4.5 through a 3-fix refactoring of the dashboard JavaScript. The fixes address:

1. **Fix #1:** Consolidate `app.js` and `app_phase2_enhancements.js` (eliminates the override trap)
2. **Fix #2:** Centralize global state into a namespace (improves maintainability)
3. **Fix #3:** Add dependency documentation headers (prevents future load-order bugs)

**Estimated total time:** 2-3 hours including testing

---

## Before You Start

### Prerequisites
- Local development environment running (`docker compose up`)
- Dashboard accessible at http://localhost:8081/
- Browser with developer tools (Chrome recommended)

### How to Open Browser Console (for error checking)
1. Open Chrome to http://localhost:8081/
2. Press `F12` (or right-click anywhere → "Inspect")
3. Click "Console" tab at top
4. Red text = errors (copy these to report to Sonnet)

---

## Session Setup Prompt

**Copy this to Sonnet at the start of your session:**

```
## Context for This Session

I'm refactoring the NWDownloads circulation dashboard based on an architectural
review from Opus 4.5. I am not a programmer - I'll be directing you through
specific prompts and reporting test results.

The project is at: /Users/user/Development/work/_active/nwdownloads/

We're doing 3 fixes in order:
1. Consolidate app.js and app_phase2_enhancements.js (HIGH priority)
2. Centralize global state into namespace (MEDIUM priority)
3. Add dependency documentation headers (LOW priority)

After each fix, I'll test manually and report results.

If I report an issue, please:
1. Explain what likely went wrong (in simple terms)
2. Provide the fix
3. Tell me what to test again

Let's start with the safety check before making any changes.
```

---

## Safety Check Prompt

**Run this FIRST, before any changes:**

```
## Safety Check - Before Making Changes

Before making any changes, please:

1. Read web/assets/app.js and confirm you see the renderDashboard() function
2. Read web/assets/app_phase2_enhancements.js and confirm you see the override pattern
   (look for "window.renderDashboard = function")
3. Tell me how many lines each file has
4. Create a git tag for rollback safety:
   git tag pre-refactor-backup

Report what you found, then wait for me to say "proceed with Fix #1".
```

---

## Fix #1: Consolidate Files

**This is the most important fix. Copy this entire prompt to Sonnet:**

```
## Task: Consolidate app.js and app_phase2_enhancements.js

### Background
app_phase2_enhancements.js overrides two functions from app.js:
- `renderDashboard()` (lines 703-725 in Phase 2)
- `renderBusinessUnits()` (lines 557-701 in Phase 2)

This causes the app.js versions to be dead code. We need to merge these files.

### Instructions

1. **Read both files:**
   - web/assets/app.js
   - web/assets/app_phase2_enhancements.js

2. **In app.js, REPLACE these functions with Phase 2 versions:**
   - Replace `renderDashboard()` (app.js lines 333-356) with Phase 2's version (lines 703-725)
   - Replace `renderBusinessUnits()` (app.js lines 674-836) with Phase 2's version (lines 557-701)

3. **Copy these NEW functions from Phase 2 into app.js** (they don't exist in app.js):
   - `exportToCSV()`
   - `exportToExcel()`
   - `exportToPDF()`
   - `toggleExportMenu()`
   - `renderAnalytics()`
   - `toggleBusinessUnitDetails()`
   - `renderBusinessUnitDetail()`
   - `renderUnitTrendChart()`
   - `renderUnitDeliveryTrend()`
   - `renderComparisonWithTrend()`
   - `capitalizeFirst()`
   - The document click listener for closing export menu (around line 185 in Phase 2)

4. **Keep app.js's existing functions that Phase 2 doesn't override:**
   - All navigation functions (navigatePreviousWeek, navigateNextWeek, etc.)
   - `renderEmptyState()`
   - `renderPeriodDisplay()`
   - `renderKeyMetrics()`
   - `renderComparisonBadge()`
   - `calculateSmartScale()`
   - `renderTrendChart()`
   - `renderDeliveryChart()`
   - `renderPaperCards()`
   - `refreshData()`
   - `showError()`
   - All utility functions (formatNumber, formatChange)
   - All constants (API_BASE, BUSINESS_UNITS, PAPER_INFO)
   - The DOMContentLoaded event listener

5. **Remove the override pattern code from the merged file:**
   - Delete lines like `const originalRenderBusinessUnits = window.renderBusinessUnits;`
   - Delete lines like `window.renderBusinessUnits = function() {`
   - Delete lines like `const originalRenderDashboard = window.renderDashboard;`
   - Delete lines like `window.renderDashboard = function() {`
   - Just have normal function declarations: `function renderBusinessUnits() {`

6. **Update web/index.php:**
   - Find the script tag for app_phase2_enhancements.js (around line 895)
   - Remove that entire line
   - Keep the app.js script tag

7. **Update the version comment** at top of app.js:
   Change to: `* Circulation Dashboard v2 - Consolidated (Phase 2 merged)`
   Add: `* Refactored: 2025-12-06 - Eliminated override pattern`

8. **After making changes, show me:**
   - The first 30 lines of the updated app.js (to verify header)
   - The script tags section from index.php (to verify Phase 2 removed)
   - Confirmation of total line count of new app.js

Do NOT delete app_phase2_enhancements.js yet - I'll do that after testing.
```

---

## Fix #1: Smoke Test Checklist

**After Sonnet completes Fix #1, test these manually:**

| # | What to Do | Expected Result | Pass? |
|---|------------|-----------------|-------|
| 1 | Open http://localhost:8081/ | Page loads, no blank screen | |
| 2 | Look at "Key Metrics" section | 4 cards with numbers (not "Loading..." or "--") | |
| 3 | Look at "By Business Unit" section | 3 cards: South Carolina, Michigan, Wyoming | |
| 4 | Check for donut charts | Each business unit card has a donut chart inside | |
| 5 | Look for comparison badges | Green or red badges with arrows on cards | |
| 6 | Click on "Michigan" card | Panel slides in from right side | |
| 7 | Click the X button in panel | Panel closes | |
| 8 | Click "Previous Week" button | Week label changes, numbers may change | |
| 9 | Click "This Week" button | Returns to original week | |
| 10 | Change dropdown to "Same Week Last Year" | Comparison data changes | |
| 11 | Press F12, check Console tab | No red error messages | |

**If ALL pass:** Tell Sonnet:
```
Fix #1 verified successfully. All smoke tests pass.
Please delete web/assets/app_phase2_enhancements.js now.
Then commit with message: "Consolidate Phase 2 into app.js - eliminate override pattern"
```

**If ANY fail:** See "Troubleshooting" section below.

---

## Fix #2: Centralize State

**After Fix #1 is complete and committed, copy this to Sonnet:**

```
## Task: Centralize Global State into Namespace

### Background
After consolidating app.js, we still have global variables scattered across files.
We need to move them into a single namespaced object for maintainability.

### Step 1: Create the namespace at the TOP of app.js

After the header comment block, add this:

/**
 * CircDashboard Namespace
 * Centralized state management for the dashboard
 */
const CircDashboard = window.CircDashboard || {};

CircDashboard.state = {
    // Data from API
    dashboardData: null,
    dataRange: null,

    // Navigation state
    currentDate: null,      // null = latest, or 'YYYY-MM-DD'
    compareMode: 'previous', // 'yoy', 'previous', 'none'

    // Chart instances
    charts: {
        trend: null,
        delivery: null,
        businessUnits: {},   // keyed by unit name
    },

    // UI instances
    flatpickrInstance: null,
};

### Step 2: Keep the existing global variable declarations

Leave these in place (after the namespace):
let dashboardData = null;
let trendChart = null;
let deliveryChart = null;
let businessUnitCharts = {};
let currentDate = null;
let compareMode = 'previous';
let dataRange = null;
let flatpickrInstance = null;

### Step 3: Update assignments to write to BOTH locations

Find every place where these globals are assigned and update to dual-write.

**For dashboardData:**
Find: `dashboardData = result.data;` (or similar)
Change to: `dashboardData = CircDashboard.state.dashboardData = result.data;`

**For dataRange:**
Find: `dataRange = result.data.data_range;` (or similar)
Change to: `dataRange = CircDashboard.state.dataRange = result.data.data_range;`

**For currentDate:**
Find all: `currentDate = ` assignments
Change to: `currentDate = CircDashboard.state.currentDate = `

**For compareMode:**
Find: `compareMode = e.target.value;` (or similar)
Change to: `compareMode = CircDashboard.state.compareMode = e.target.value;`

**For trendChart:**
Find: `trendChart = new Chart(`
Change to: `trendChart = CircDashboard.state.charts.trend = new Chart(`

**For deliveryChart:**
Find: `deliveryChart = new Chart(`
Change to: `deliveryChart = CircDashboard.state.charts.delivery = new Chart(`

**For businessUnitCharts:**
Find: `businessUnitCharts[unitName] = new Chart(`
Change to: `businessUnitCharts[unitName] = CircDashboard.state.charts.businessUnits[unitName] = new Chart(`

**For flatpickrInstance:**
Find: `flatpickrInstance = flatpickr(`
Change to: `flatpickrInstance = CircDashboard.state.flatpickrInstance = flatpickr(`

### Step 4: Do the same for detail_panel.js

At the TOP of detail_panel.js, add:

// Extend CircDashboard namespace for detail panel
const CircDashboard = window.CircDashboard || {};
CircDashboard.detailPanel = CircDashboard.detailPanel || {
    charts: {
        expiration: null,
        rateDistribution: null,
        subscriptionLength: null,
    },
    currentBusinessUnit: null,
    currentSnapshotDate: null,
    data: null,
    availableBusinessUnits: ['South Carolina', 'Michigan', 'Wyoming'],
};

Then update assignments in detail_panel.js similarly (dual-write to both the local variable and the namespace).

### Step 5: Show me the results

After making changes, show me:
1. The namespace block you added to app.js (first ~30 lines after header)
2. One example of a dual-write assignment
3. The namespace block you added to detail_panel.js
```

---

## Fix #2: Targeted Test

**After Sonnet completes Fix #2:**

| # | What to Do | Expected Result | Pass? |
|---|------------|-----------------|-------|
| 1 | Refresh http://localhost:8081/ | Page loads normally | |
| 2 | Press F12, go to Console tab | No red errors | |
| 3 | In console, type: `CircDashboard.state` and press Enter | Shows object with dashboardData, charts, etc. | |
| 4 | Click "Previous Week" then "This Week" | Navigation works | |
| 5 | Click any business unit card | Detail panel opens | |

**If ALL pass:** Tell Sonnet:
```
Fix #2 verified successfully. State centralization working.
Please commit with message: "Centralize state into CircDashboard namespace"
```

---

## Fix #3: Add Documentation

**After Fix #2 is complete and committed, copy this to Sonnet:**

```
## Task: Add Dependency Documentation Headers to All JS Files

### Background
The dashboard has multiple JS files that must load in a specific order.
We need to document the dependencies so future developers understand the load order.

### Instructions

Add a documentation block at the TOP of each file (after any existing header comment).

### File 1: app.js

Add after the existing header comment:

/**
 * LOAD ORDER: 1 of 11 - Must load first
 *
 * DEPENDENCIES:
 * - Chart.js (CDN): Chart rendering
 * - Flatpickr (CDN): Date picker
 *
 * PROVIDES:
 * - CircDashboard.state: Central state object
 * - BUSINESS_UNITS, PAPER_INFO: Configuration constants
 * - loadDashboardData(), renderDashboard(): Core functions
 * - formatNumber(), formatChange(): Utilities
 * - All dashboard rendering and navigation functions
 */

### File 2: state-icons.js

Add at top:

/**
 * state-icons.js - State Icon Utilities
 * Provides state abbreviations and icon images for business units
 *
 * LOAD ORDER: 2 of 11
 *
 * DEPENDENCIES: None
 *
 * PROVIDES:
 * - getStateAbbr(businessUnit): Returns state abbreviation
 * - getStateIconImg(businessUnit): Returns img tag for state icon
 */

### File 3: chart-layout-manager.js

Add at top:

/**
 * chart-layout-manager.js - Chart Layout Management
 * Handles responsive chart sizing and layout
 *
 * LOAD ORDER: 3 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart.js must be initialized
 *
 * PROVIDES:
 * - ChartLayoutManager utilities
 */

### File 4: donut-to-state-animation.js

Add at top:

/**
 * donut-to-state-animation.js - Chart Animations
 * Animated transitions for donut charts
 *
 * LOAD ORDER: 4 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart.js instances
 *
 * PROVIDES:
 * - Donut animation utilities
 */

### File 5: detail_panel.js

Add after the namespace block:

/**
 * LOAD ORDER: 5 of 11
 *
 * DEPENDENCIES:
 * - app.js: dashboardData, formatNumber, BUSINESS_UNITS, API_BASE
 * - state-icons.js: getStateAbbr, getStateIconImg
 *
 * PROVIDES:
 * - openDetailPanel(businessUnit, snapshotDate)
 * - closeDetailPanel()
 * - switchBusinessUnit(unit)
 * - CircDashboard.detailPanel state
 */

### File 6: ui-enhancements.js

Add at top:

/**
 * ui-enhancements.js - UI Polish and Enhancements
 * Keyboard shortcuts, accessibility improvements
 *
 * LOAD ORDER: 6 of 11
 *
 * DEPENDENCIES:
 * - app.js: Core functions
 * - detail_panel.js: Panel functions for shortcuts
 *
 * PROVIDES:
 * - initializeUIEnhancements()
 * - updateExportMenuAria()
 */

### File 7: context-menu.js

Add at top:

/**
 * context-menu.js - Chart Context Menus
 * Right-click context menus for chart interactions
 *
 * LOAD ORDER: 7 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart instances
 *
 * PROVIDES:
 * - Context menu creation and handling
 */

### File 8: export-utils.js

Add at top:

/**
 * export-utils.js - Data Export Utilities
 * CSV, PDF, Excel export functionality
 *
 * LOAD ORDER: 8 of 11
 *
 * DEPENDENCIES:
 * - app.js: dashboardData, formatNumber
 * - XLSX (CDN): Excel export
 * - jsPDF, html2canvas (CDN): PDF export
 *
 * PROVIDES:
 * - Export utility functions
 */

### File 9: subscriber-table-panel.js

Add at top:

/**
 * subscriber-table-panel.js - Subscriber Data Table
 * Displays subscriber lists from chart drill-down
 *
 * LOAD ORDER: 9 of 11
 *
 * DEPENDENCIES:
 * - app.js: API_BASE, formatNumber
 * - detail_panel.js: currentBusinessUnit, currentSnapshotDate
 *
 * PROVIDES:
 * - Subscriber table rendering and pagination
 */

### File 10: chart-transition-manager.js

Add at top:

/**
 * chart-transition-manager.js - Chart Transitions
 * Manages animated transitions between chart states
 *
 * LOAD ORDER: 10 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart.js instances
 *
 * PROVIDES:
 * - ChartTransitionManager
 */

### File 11: chart-context-integration.js

Add at top:

/**
 * chart-context-integration.js - Context Menu Integration
 * Connects context menus to chart click handlers
 *
 * LOAD ORDER: 11 of 11 - Must load last
 *
 * DEPENDENCIES:
 * - All previous files must be loaded
 * - context-menu.js, subscriber-table-panel.js, chart-transition-manager.js
 *
 * PROVIDES:
 * - Integrates all chart interaction features
 */

### After adding all headers

Show me the first 15 lines of each file to confirm headers were added.
```

---

## Fix #3: Quick Verification

**After Sonnet completes Fix #3:**

| # | What to Do | Expected Result | Pass? |
|---|------------|-----------------|-------|
| 1 | Refresh http://localhost:8081/ | Page still loads | |
| 2 | Check Console for errors | No red errors | |

**If passes:** Tell Sonnet:
```
Fix #3 verified. Documentation added.
Please commit with message: "Add dependency documentation headers to all JS files"
```

---

## Final Cleanup Prompt

**After all 3 fixes are committed:**

```
## Final Cleanup: Normalize Cache-Busting Versions

In web/index.php, find all script tags with version parameters (the ?v=2025XXXX parts).

Change ALL of them to use today's date: ?v=20251206

This ensures consistent cache invalidation after the refactor.

Show me the updated script tags section when done.
```

Then tell Sonnet:
```
Please commit with message: "Normalize cache-busting versions after refactor"
```

---

## Troubleshooting Templates

**If the page is completely blank:**
```
The dashboard page is completely blank after your changes.
No content appears at all.
Please check for JavaScript syntax errors in app.js - likely a missing
bracket, parenthesis, or semicolon from the merge.
```

**If you see "Loading..." that never resolves:**
```
The dashboard shows "Loading..." indefinitely in the [Key Metrics / Business Units / etc.] section.
The data never appears. Other sections may work.
Please check that renderDashboard() is calling all the render functions,
and that loadDashboardData() is setting dashboardData correctly.
```

**If business unit cards don't show comparison badges:**
```
The business unit cards appear but don't have the green/red comparison badges.
Before the refactor, these showed +/- numbers with arrows.
Please check that renderBusinessUnits() includes the comparison badge logic
from the Phase 2 version.
```

**If clicking a card doesn't open the panel:**
```
When I click on a business unit card (like Michigan), nothing happens.
The panel should slide in from the right.
Please check that the onclick handler is still present on the card HTML
and that openDetailPanel function exists.
```

**If charts don't appear:**
```
The charts (trend line, donut charts) are not rendering.
The containers are there but empty.
Please check that renderTrendChart() and renderDeliveryChart() are being
called, and that the Chart.js assignments are correct.
```

**If you see a red error in console:**
```
I see this error in the browser console:

[PASTE THE EXACT ERROR TEXT HERE]

Please fix this error.
```

---

## Rollback Instructions

**If something goes catastrophically wrong and you need to start over:**

Tell Sonnet:
```
The refactor has broken the dashboard beyond repair.
Please restore from the backup:

git checkout pre-refactor-backup -- web/assets/app.js
git checkout pre-refactor-backup -- web/assets/app_phase2_enhancements.js
git checkout pre-refactor-backup -- web/index.php

Then verify the dashboard works at http://localhost:8081/
```

---

## Summary Checklist

- [ ] Session setup prompt given to Sonnet
- [ ] Safety check completed (git tag created)
- [ ] Fix #1 completed
- [ ] Fix #1 smoke test passed
- [ ] app_phase2_enhancements.js deleted
- [ ] Fix #1 committed
- [ ] Fix #2 completed
- [ ] Fix #2 targeted test passed
- [ ] Fix #2 committed
- [ ] Fix #3 completed
- [ ] Fix #3 quick verification passed
- [ ] Fix #3 committed
- [ ] Final cleanup (cache-busting) done
- [ ] Final commit made
- [ ] Deploy to production when ready

---

*Document created by Opus 4.5 architectural review session, 2025-12-06*
