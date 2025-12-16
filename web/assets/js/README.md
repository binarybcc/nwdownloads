# JavaScript Modular Architecture

This directory contains the reorganized JavaScript codebase for the Circulation Dashboard, structured by function and responsibility.

## Directory Structure

```
web/assets/js/
├── core/               # Main dashboard initialization and management
├── components/         # Reusable UI components
├── charts/            # Chart visualization and interactions
├── features/          # Standalone feature modules
└── utils/             # Utility functions and helpers
```

## Module Categories

### 📊 `core/` - Dashboard Core

**Main application entry points and dashboard management**

**`app.js`** (4,185 lines)
- Primary dashboard initialization and state management
- Data fetching and refresh logic
- Business unit switching
- Export functionality (CSV, Excel, PDF)
- Global functions: `refreshData()`, `exportToCSV()`, `exportToExcel()`, `exportToPDF()`
- Dependencies: All components, charts, features

**`churn_dashboard.js`** (795 lines)
- Subscriber churn analysis dashboard
- Trend visualization and details
- Week-over-week comparison
- Global functions: `showChurnTrend()`, `showChurnDetails()`
- Dependencies: Chart.js, fetch API

### 🧩 `components/` - UI Components

**Reusable interface components with encapsulated logic**

**`context-menu.js`** (183 lines)
- Right-click context menu for charts
- Generic menu positioning and event handling
- Used by: chart-context-integration.js

**`detail_panel.js`** (1,082 lines)
- Subscriber detail panel with filtering and search
- Table rendering and pagination
- Export functionality for filtered data
- Dependencies: export-utils.js

**`publication-revenue-detail.js`** (494 lines)
- Publication-specific revenue details
- Legacy subscriber analysis
- Rate comparison visualization
- Global functions: `openPublicationDetail()`, `closeRevenueDetail()`
- Dependencies: revenue-intelligence.js

**`revenue-opportunity-table.js`** (384 lines)
- Revenue opportunity analysis table
- Sortable columns and filtering
- Class: `RevenueOpportunityTable`

**`subscriber-table-panel.js`** (326 lines)
- Subscriber list table component
- Column sorting and filtering
- Class: `SubscriberTablePanel`
- Dependencies: export-utils.js

**`trend-slider.js`** (215 lines)
- Interactive slider for week selection
- Trend comparison visualization
- Touch and keyboard navigation

### 📈 `charts/` - Chart Visualization

**Chart rendering, interactions, and animations**

**`chart-context-integration.js`** (324 lines)
- Integrates context menus with Chart.js
- Subscriber table display from chart clicks
- Chart action handlers
- Functions: `createChartContextMenu()`, `initializeChartContextMenus()`
- Dependencies: context-menu.js, subscriber-table-panel.js

**`chart-layout-manager.js`** (326 lines)
- Dynamic chart layout switching
- Responsive grid/list views
- Layout state persistence
- Dependencies: Chart.js

**`donut-to-state-animation.js`** (188 lines)
- Animated transitions between views
- Donut chart to state icon animations
- Smooth visual effects
- Dependencies: state-icons.js

### ✨ `features/` - Feature Modules

**Standalone features with specific functionality**

**`backfill-indicator.js`** (71 lines)
- Visual indicator for backfilled data
- Automatic detection and display
- Lightweight feature module

**`revenue-intelligence.js`** (453 lines)
- Revenue analysis and legacy rate detection
- Opportunity identification
- Rate comparison logic
- Global function: `populateLegacyRateAnalysis()`
- Dependencies: publication-revenue-detail.js

**`vacation-display.js`** (283 lines)
- Vacation tracking and visualization
- Longest vacation detection
- Business unit vacation lists
- Global functions: `displayLongestVacationsOverall()`, `displayLongestVacationsForUnit()`

### 🛠️ `utils/` - Utilities

**Helper functions and shared utilities**

**`export-utils.js`** (251 lines)
- CSV, Excel (XLSX), PDF export functionality
- Table data extraction and formatting
- Dependencies: XLSX.js, html2canvas
- Used by: detail_panel.js, subscriber-table-panel.js

**`state-icons.js`** (68 lines)
- State abbreviation mappings
- Icon path generation
- Color scheme utilities
- Global functions: `getStateIconPath()`, `getStateAbbr()`, `getStateIconImg()`
- Used by: donut-to-state-animation.js

**`ui-enhancements.js`** (107 lines)
- UI polish and interaction improvements
- Animation helpers
- Visual feedback utilities

## File Dependencies

**Dependency Tree (Top-Down):**

```
HTML Pages (index.php, churn_dashboard.php)
  ↓
core/app.js, core/churn_dashboard.js
  ↓
components/* (detail_panel, subscriber-table-panel, etc.)
  ↓
charts/* (chart-context-integration, chart-layout-manager)
  ↓
features/* (revenue-intelligence, vacation-display)
  ↓
utils/* (export-utils, state-icons, ui-enhancements)
```

## Loading Order

**Scripts must load in dependency order:**

1. **External Libraries** (Chart.js, XLSX, flatpickr, html2canvas)
2. **Utils** (state-icons.js, ui-enhancements.js, export-utils.js)
3. **Features** (vacation-display.js, revenue-intelligence.js, backfill-indicator.js)
4. **Charts** (donut-to-state-animation.js, chart-layout-manager.js, chart-context-integration.js)
5. **Components** (context-menu.js, subscriber-table-panel.js, revenue-opportunity-table.js, publication-revenue-detail.js, detail_panel.js, trend-slider.js)
6. **Core** (app.js, churn_dashboard.js) - Last!

**Example (from index.php):**
```html
<!-- Utils -->
<script src="assets/js/utils/state-icons.js"></script>
<script src="assets/js/utils/ui-enhancements.js"></script>
<script src="assets/js/utils/export-utils.js"></script>

<!-- Features -->
<script src="assets/js/features/vacation-display.js"></script>
<script src="assets/js/features/revenue-intelligence.js"></script>
<script src="assets/js/features/backfill-indicator.js"></script>

<!-- Charts -->
<script src="assets/js/charts/donut-to-state-animation.js"></script>
<script src="assets/js/charts/chart-layout-manager.js"></script>
<script src="assets/js/charts/chart-context-integration.js"></script>

<!-- Components -->
<script src="assets/js/components/context-menu.js"></script>
<script src="assets/js/components/subscriber-table-panel.js"></script>
<script src="assets/js/components/revenue-opportunity-table.js"></script>
<script src="assets/js/components/publication-revenue-detail.js"></script>
<script src="assets/js/components/detail_panel.js"></script>
<script src="assets/js/components/trend-slider.js"></script>

<!-- Core - Load last! -->
<script src="assets/js/core/app.js"></script>
```

## Global Functions

**Functions available globally (called from HTML or other scripts):**

### From `core/app.js`:
- `refreshData()` - Reload dashboard data
- `exportToCSV()` - Export current view to CSV
- `exportToExcel()` - Export to Excel format
- `exportToPDF()` - Export dashboard as PDF
- `toggleExportMenu()` - Show/hide export options
- `toggleBusinessUnitDetails()` - Expand/collapse business unit sections
- `renderComparisonWithTrend()` - Render comparison chart with trend

### From `core/churn_dashboard.js`:
- `showChurnTrend()` - Display churn trend chart
- `showChurnDetails()` - Show detailed churn analysis

### From `components/publication-revenue-detail.js`:
- `openPublicationDetail()` - Open publication detail modal
- `closeRevenueDetail()` - Close detail modal
- `exportLegacySubscribers()` - Export legacy subscribers

### From `features/revenue-intelligence.js`:
- `populateLegacyRateAnalysis()` - Analyze legacy rates

### From `features/vacation-display.js`:
- `displayLongestVacationsOverall()` - Show longest vacations across all units
- `displayLongestVacationsForUnit()` - Show vacations for specific business unit

### From `utils/state-icons.js`:
- `getStateIconPath()` - Get SVG path for state icon
- `getStateAbbr()` - Get state abbreviation
- `getStateIconImg()` - Get HTML img tag for state
- `getStateColor()` - Get color for state

### From `charts/chart-context-integration.js`:
- `createChartContextMenu()` - Create context menu for chart
- `initializeChartContextMenus()` - Initialize all chart menus
- `cleanupChartContextMenus()` - Remove chart menu listeners

## Code Quality

**All files pass quality checks:**
- ✅ ESLint: 0 errors, 18 warnings (unused exports)
- ✅ Prettier formatted
- ✅ Proper JSDoc comments
- ✅ Global declarations in eslint.config.js

## Migration Notes

**Before reorganization (December 16, 2025):**
- 17 files in flat structure (`web/assets/*.js`)
- No clear separation of concerns
- Difficult to navigate and maintain

**After reorganization:**
- 17 files organized into 5 logical categories
- Clear dependency hierarchy
- Easier to find and modify code
- Improved maintainability

**Backward compatibility:**
- All global functions preserved
- No API changes
- HTML pages updated with new script paths

## Adding New JavaScript Files

**When creating new functionality:**

1. **Determine category:**
   - **Core** - Main dashboard logic
   - **Components** - Reusable UI elements
   - **Charts** - Visualization and interaction
   - **Features** - Standalone capabilities
   - **Utils** - Helper functions

2. **Create file in appropriate directory:**
   ```bash
   # Example: New table component
   touch web/assets/js/components/new-table.js
   ```

3. **Add script tag to HTML in dependency order:**
   ```html
   <!-- After other components, before core -->
   <script src="assets/js/components/new-table.js"></script>
   ```

4. **Export global functions with JSDoc:**
   ```javascript
   /* exported NewTable */
   /**
    * New table component
    */
   class NewTable {
     // ...
   }
   ```

5. **Update this README** with file description and dependencies

## Best Practices

**DO:**
- ✅ Place new files in appropriate category directory
- ✅ Document global functions with JSDoc
- ✅ Declare exports with `/* exported */` comment
- ✅ Follow existing code style (Prettier formatted)
- ✅ Update HTML script loading order

**DON'T:**
- ❌ Create new files in `web/assets/` root (use subdirectories)
- ❌ Mix concerns (e.g., chart logic in utils)
- ❌ Skip JSDoc comments on public functions
- ❌ Forget to update HTML script tags

## Documentation

See `/docs/DESIGN-SYSTEM.md` for component patterns and UI standards.
See `/docs/KNOWLEDGE-BASE.md` for complete system architecture.
