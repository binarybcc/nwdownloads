# Contextual Chart Menus - Interactive Data Drill-Down System

**Date:** 2025-12-05
**Version:** 1.0
**Status:** Implementation Phase

## Executive Summary

Adding interactive contextual menus to dashboard charts to enable rapid subscriber data access and historical trend analysis. This eliminates the "10-minute report nightmare" in Newzware by providing instant access to subscriber lists and trend data.

### Problem Statement
Users need to extract subscriber lists for specific metrics (e.g., "Past Due subscribers", "Subscribers on Rate X") which currently requires complex manual reports in Newzware. This dashboard should provide instant access to this data with export capabilities.

### Solution Overview
Progressive disclosure context menu system with three interaction layers:
1. **Layer 1:** Dashboard charts with hover indicators
2. **Layer 2:** Context menu with core actions
3. **Layer 3a:** Historical trend chart view (inline transition)
3. **Layer 3b:** Subscriber table slide-out panel (distinct visual theme)

---

## User Workflow & Use Cases

### Primary Use Case: Renewal Campaign Planning

**Scenario:** User needs to contact all subscribers whose subscriptions expire this week.

**Workflow:**
1. Open Business Unit detail panel (existing feature)
2. Navigate to "Subscription Expirations (4-Week View)" chart
3. Hover over "This Week" bar → icon appears
4. Right-click or click icon → context menu appears
5. Select "👥 View subscribers"
6. Slide-out panel appears with subscriber table showing:
   - NW Account Number
   - Subscriber Name
   - Phone, Email, Mailing Address
   - Current Rate, Last Payment Amount
   - Payment Method, Expiration Date
7. Click "Export to Excel" → formatted spreadsheet downloads
8. User switches to Newzware with account numbers to process renewals

**Time Saved:** 10 minutes of Newzware report generation → 30 seconds

### Secondary Use Case: Trend Analysis

**Scenario:** User notices high "Past Due" count and wants to see if it's seasonal or trending up.

**Workflow:**
1. Right-click "Past Due" bar → context menu
2. Select "📈 Show trend over time"
3. Chart smoothly transitions to line chart showing past 12 weeks
4. User sees trend is increasing → investigates further
5. Click "Back" button → returns to bar chart

---

## Architecture Design

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                      Dashboard View                         │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         Expiration Chart (Bar Chart)                   │  │
│  │  [Past Due] [This Week] [Next Week] [Week +2]         │  │
│  │       ↑ Hover shows icon, right-click opens menu      │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    ┌─────────────────┐
                    │  Context Menu   │
                    │  ┌────────────┐ │
                    │  │ 📈 Trend   │ │ ← Core actions
                    │  │ 👥 Subs    │ │
                    │  ├────────────┤ │
                    │  │ [Future]   │ │ ← Chart-specific
                    │  └────────────┘ │
                    └─────────────────┘
                    ↙               ↘
         ┌──────────────────┐    ┌──────────────────────┐
         │  Trend View      │    │  Subscriber Panel    │
         │  (Inline)        │    │  (Slide-out)         │
         │                  │    │                      │
         │  ← Bar slides L  │    │  Different color     │
         │  → Line slides R │    │  Export buttons      │
         │  [Breadcrumb]    │    │  Data table          │
         └──────────────────┘    └──────────────────────┘
```

### Technology Stack

**Frontend:**
- **Context Menu:** Custom JavaScript class (avoid library dependencies)
- **Chart Transitions:** CSS transforms + requestAnimationFrame
- **Slide-out Panel:** CSS transitions (reuse existing pattern)
- **Excel Export:** SheetJS (already loaded)
- **CSV Export:** Native Blob + download

**Backend:**
- **Language:** PHP 8.2
- **Database:** MariaDB 10.11
- **API Pattern:** RESTful JSON endpoints in existing `api.php`

**Deployment:**
- **Development:** OrbStack (localhost:8081)
- **Production:** Synology NAS Docker (192.168.1.254:8081)
- **Container:** PHP 8.2-apache + MariaDB

---

## Database Schema Considerations

### Existing Tables
We'll query from existing `daily_snapshots` table plus need to join with Newzware subscriber data.

**Challenge:** Dashboard may not have full subscriber details (phone, email, etc.).

**Options:**
1. **Option A:** Import subscriber contact info during weekly upload
2. **Option B:** Query Newzware database directly (if accessible)
3. **Option C:** User provides supplemental contact CSV

**Recommendation:** Start with Option C (simplest, no Newzware integration), evolve to Option A later.

### New Table: `subscriber_contacts` (Optional Enhancement)

```sql
CREATE TABLE subscriber_contacts (
    account_id VARCHAR(50) PRIMARY KEY,
    subscriber_name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    mailing_address TEXT,
    last_payment_amount DECIMAL(10,2),
    payment_method VARCHAR(50),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** For Phase 1, we'll work with available data from `daily_snapshots` and mock contact fields.

---

## API Endpoints Design

### 1. Get Subscriber List

**Endpoint:** `GET /api.php?action=get_subscribers`

**Parameters:**
- `business_unit` (string, required): "Wyoming", "Michigan", "South Carolina"
- `snapshot_date` (date, required): "2025-12-05"
- `metric_type` (string, required): "expiration", "rate", "subscription_length"
- `metric_value` (string, required): "Past Due", "Rate_42", "6-month", etc.

**Response:**
```json
{
  "success": true,
  "data": {
    "metric": "Past Due",
    "count": 45,
    "snapshot_date": "2025-12-05",
    "business_unit": "Wyoming",
    "subscribers": [
      {
        "account_id": "WY-12345",
        "subscriber_name": "John Doe",
        "phone": "307-555-1234",
        "email": "john@example.com",
        "mailing_address": "123 Main St, Lander, WY 82520",
        "paper_code": "TJ",
        "paper_name": "The Journal",
        "current_rate": "Senior 6mo",
        "rate_amount": 42.00,
        "last_payment_amount": 42.00,
        "payment_method": "Check",
        "expiration_date": "2025-11-28",
        "delivery_type": "MAIL"
      },
      // ... more subscribers
    ]
  }
}
```

### 2. Get Historical Trend

**Endpoint:** `GET /api.php?action=get_trend`

**Parameters:**
- `business_unit` (string, required)
- `metric_type` (string, required): "expiration", "rate", "subscription_length"
- `metric_value` (string, required): "Past Due", "Rate_42", etc.
- `time_range` (string, required): "4weeks", "12weeks", "26weeks", "52weeks"
- `end_date` (date, required): "2025-12-05" (defaults to latest)

**Response:**
```json
{
  "success": true,
  "data": {
    "metric": "Past Due",
    "time_range": "12weeks",
    "business_unit": "Wyoming",
    "data_points": [
      {"snapshot_date": "2025-09-14", "count": 38},
      {"snapshot_date": "2025-09-21", "count": 42},
      {"snapshot_date": "2025-09-28", "count": 45},
      // ... 12 weeks of data
      {"snapshot_date": "2025-12-05", "count": 45}
    ]
  }
}
```

---

## Frontend Component Design

### 1. ContextMenu Class

**File:** `web/assets/context-menu.js`

**Responsibilities:**
- Render context menu at cursor position
- Handle click-outside-to-close
- Emit events for action selection
- Position menu intelligently (avoid screen edges)

**API:**
```javascript
const menu = new ContextMenu({
  items: [
    { id: 'trend', icon: '📈', label: 'Show trend over time' },
    { id: 'subscribers', icon: '👥', label: 'View subscribers' },
    { type: 'divider' },
    { id: 'future1', label: 'Future action', disabled: true }
  ],
  onSelect: (itemId, context) => {
    // Handle action
  }
});

menu.show(x, y, context);
```

### 2. ChartTransitionManager Class

**File:** `web/assets/chart-transition-manager.js`

**Responsibilities:**
- Animate chart transitions (slide left/right)
- Manage breadcrumb navigation
- Store navigation history stack
- Render historical trend charts

**API:**
```javascript
const transitionMgr = new ChartTransitionManager(chartContainerId);

// Transition to trend view
transitionMgr.showTrend({
  chartType: 'expiration',
  metric: 'Past Due',
  timeRange: '12weeks',
  data: [...],
  onBack: () => { /* restore original chart */ }
});
```

### 3. SubscriberTablePanel Class

**File:** `web/assets/subscriber-table-panel.js`

**Responsibilities:**
- Render slide-out panel with distinct color scheme
- Display subscriber data table
- Handle Excel/CSV export
- Manage panel open/close animations

**API:**
```javascript
const tablePanel = new SubscriberTablePanel({
  colorScheme: 'teal', // Different from main dashboard
  onClose: () => { /* cleanup */ }
});

tablePanel.show({
  title: 'Past Due Subscribers - Wyoming',
  subtitle: 'Snapshot: 2025-12-05 (45 subscribers)',
  columns: [...],
  data: [...]
});
```

### 4. ChartHoverIndicator

**Responsibilities:**
- Add hover indicators to chart bars
- Position icons correctly
- Handle hover state management

**Integration:**
```javascript
// Add to existing chart rendering
chartInstance.options.plugins.push({
  id: 'hoverIndicator',
  afterDraw: (chart) => {
    // Draw icon on hover
  }
});
```

---

## Visual Design Specifications

### Color Schemes

**Main Dashboard:**
- Primary: `#0369A1` (Professional Blue)
- Background: `#F8FAFC` (Off-white)
- Text: `#0F172A` (Navy)

**Subscriber Table Panel (Distinct):**
- Primary: `#0891B2` (Cyan/Teal) - clearly different but professional
- Background: `#F0FDFA` (Teal-tinted white)
- Accent: `#14B8A6` (Teal for buttons)
- Header: `#134E4A` (Dark teal)

**Trend View (In-place):**
- Keep main dashboard colors
- Add breadcrumb with blue accent
- Back button: outlined style

### Context Menu Styling

```css
.context-menu {
  background: white;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15);
  min-width: 220px;
  padding: 6px;
  z-index: 9999;
}

.context-menu-item {
  padding: 10px 14px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: background 150ms;
}

.context-menu-item:hover {
  background: #F1F5F9;
}

.context-menu-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.context-menu-divider {
  height: 1px;
  background: #E2E8F0;
  margin: 6px 0;
}
```

### Hover Icon Indicator

```css
.chart-hover-icon {
  position: absolute;
  width: 24px;
  height: 24px;
  background: white;
  border: 2px solid #0369A1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  opacity: 0;
  transition: opacity 200ms;
  pointer-events: none;
}

.chart-container:hover .chart-hover-icon {
  opacity: 1;
  pointer-events: all;
}
```

---

## Excel Export Formatting Specification

Using SheetJS (already loaded in project).

### Formatting Applied:

1. **Header Row (Row 1):**
   - Background: `#0891B2` (Teal)
   - Font: White, Bold, 12pt
   - Alignment: Center
   - Frozen (stays visible during scroll)

2. **Data Rows:**
   - Alternating row colors: White, `#F0FDFA` (light teal)
   - Font: 11pt
   - Borders: Light gray

3. **Column Widths:**
   - Auto-sized based on content
   - Minimum 12 characters, maximum 50

4. **Auto-Filter:**
   - Enabled on header row

5. **Number Formatting:**
   - Currency columns: `$#,##0.00`
   - Date columns: `YYYY-MM-DD`
   - Phone: Text format (preserve leading zeros)

### Implementation:
```javascript
function exportToExcel(data, filename) {
  const ws = XLSX.utils.json_to_sheet(data);

  // Apply header styling
  const headerRange = XLSX.utils.decode_range(ws['!ref']);
  for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
    const cellRef = XLSX.utils.encode_cell({r: 0, c: col});
    if (!ws[cellRef]) continue;

    ws[cellRef].s = {
      fill: { fgColor: { rgb: "0891B2" } },
      font: { bold: true, color: { rgb: "FFFFFF" }, sz: 12 },
      alignment: { horizontal: "center", vertical: "center" }
    };
  }

  // Freeze header row
  ws['!freeze'] = { xSplit: 0, ySplit: 1 };

  // Auto-filter
  ws['!autofilter'] = { ref: ws['!ref'] };

  // Column widths
  ws['!cols'] = calculateColumnWidths(data);

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Subscribers");
  XLSX.writeFile(wb, filename);
}
```

---

## CSV Export Specification

Simple, clean CSV for maximum compatibility.

### Format:
- UTF-8 encoding (with BOM for Excel compatibility)
- Comma-separated
- Double-quotes around fields containing commas/newlines
- Header row included
- No special formatting (just raw data)

### Implementation:
```javascript
function exportToCSV(data, filename) {
  const headers = Object.keys(data[0]);
  const csvRows = [];

  // Header row
  csvRows.push(headers.map(h => `"${h}"`).join(','));

  // Data rows
  for (const row of data) {
    const values = headers.map(h => {
      const val = row[h] ?? '';
      return `"${String(val).replace(/"/g, '""')}"`;
    });
    csvRows.push(values.join(','));
  }

  const csvString = '\uFEFF' + csvRows.join('\n'); // BOM for Excel
  const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });

  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
}
```

---

## Animation Specifications

### Chart Transition Animation

**Duration:** 400ms
**Easing:** `cubic-bezier(0.4, 0, 0.2, 1)` (ease-in-out)

**Sequence:**
1. **Fade & Slide Out (200ms):**
   - Current chart opacity: 1 → 0
   - Transform: `translateX(0) → translateX(-100%)`

2. **Brief Pause (0ms):** No pause, immediate transition

3. **Fade & Slide In (200ms):**
   - New chart opacity: 0 → 1
   - Transform: `translateX(100%) → translateX(0)`

**Back Animation:**
- Reverse the sequence (right to left)

### Subscriber Panel Animation

**Duration:** 350ms
**Easing:** `cubic-bezier(0.4, 0, 0.2, 1)`

**Open:**
- Initial: `right: -100%`
- Final: `right: 0`
- Backdrop fade in: opacity 0 → 0.4

**Close:**
- Reverse animation

---

## Implementation Phases

### Phase 1: Core Infrastructure (Days 1-2)
- [x] Design document
- [ ] Database schema (if needed)
- [ ] PHP API endpoints (get_subscribers, get_trend)
- [ ] ContextMenu JavaScript class
- [ ] Basic integration with one chart (expiration chart)

### Phase 2: Chart Transitions (Day 3)
- [ ] ChartTransitionManager class
- [ ] Breadcrumb navigation
- [ ] Historical trend rendering
- [ ] Back button functionality

### Phase 3: Subscriber Table Panel (Days 4-5)
- [ ] SubscriberTablePanel class
- [ ] Distinct color scheme implementation
- [ ] Data table rendering
- [ ] Excel export with formatting
- [ ] CSV export

### Phase 4: Integration & Polish (Day 6)
- [ ] Integrate with all three chart types
- [ ] Chart-specific action placeholders
- [ ] Hover indicator system
- [ ] Right-click support
- [ ] Keyboard navigation (ESC to close, etc.)

### Phase 5: Testing & Deployment (Day 7)
- [ ] Test in Development (OrbStack)
- [ ] Cross-browser testing (Chrome, Firefox, Safari)
- [ ] Accessibility review
- [ ] Deploy to Production (Synology NAS)
- [ ] User training/documentation

---

## Future Enhancements (Phase 2)

### Chart-Specific Actions

**Expiration Chart:**
- 📊 "Compare to same period last year"
- 📅 "View all expiring next 8 weeks"

**Rate Distribution Chart:**
- 💰 "Show revenue by rate"
- 📈 "Compare top 5 rates over time"

**Subscription Length Chart:**
- 🔄 "Show renewal rates by length"
- 💡 "Calculate average subscription value"

### Advanced Features:
- Multi-metric comparison charts
- Custom date range picker (beyond presets)
- Email list export (subscriber emails only)
- Print-optimized table view
- Save/bookmark specific queries
- Scheduled exports (weekly reports)

---

## Security Considerations

### SQL Injection Prevention
- All database queries use prepared statements
- Parameter validation and sanitization
- Type checking on all inputs

### Access Control
- Dashboard is internal-only (no public access)
- No authentication required (trusted network)
- Rate limiting on API endpoints (prevent abuse)

### Data Privacy
- Subscriber contact info is sensitive
- No logging of personal data
- HTTPS enforced (if available)

### Export Security
- File downloads use secure Blob URLs
- No server-side file storage (client-side export only)
- Filenames sanitized to prevent path traversal

---

## Performance Considerations

### Database Optimization
- Index on `snapshot_date`, `business_unit`, `paper_code`
- Limit query results to 10,000 rows (pagination if needed)
- Cache trend data for 5 minutes (reduce DB load)

### Frontend Optimization
- Lazy-load chart transition animations
- Debounce hover events (100ms delay)
- Virtual scrolling for large tables (>1000 rows)
- Reuse Chart.js instances where possible

### Deployment Constraints (Synology NAS)
- Limited CPU/memory (optimize for efficiency)
- No CDN (all assets served locally)
- Potential slow disk I/O (minimize file operations)

---

## Testing Strategy

### Unit Tests
- ContextMenu: positioning, click handling, keyboard nav
- Export functions: data formatting, encoding
- API endpoints: parameter validation, error handling

### Integration Tests
- Chart → Context Menu → Action flow
- Transition animations (visual review)
- Slide-out panel open/close

### User Acceptance Tests
1. Extract "Past Due" subscribers and export to Excel
2. View 12-week trend for specific rate
3. Navigate back from trend view
4. Export multiple subscriber lists
5. Test on different screen sizes (1920x1080, 1366x768)

### Browser Compatibility
- Chrome 120+ (primary)
- Firefox 121+ (secondary)
- Safari 17+ (macOS)

---

## Documentation Deliverables

1. **User Guide:** How to use context menus (screenshots)
2. **API Documentation:** Endpoint specs for future devs
3. **Code Comments:** Inline documentation for all classes
4. **Deployment Guide:** Docker build and deployment steps

---

## Success Metrics

### Quantitative:
- **Time to extract subscriber list:** < 30 seconds (vs 10 minutes)
- **Chart interaction response:** < 200ms
- **Excel export generation:** < 2 seconds for 1000 rows
- **Page load impact:** < 100ms additional load time

### Qualitative:
- User feedback: "This is a game-changer"
- Reduced Newzware report generation
- Increased dashboard usage frequency

---

## Appendix A: Data Structures

### Subscriber Data Format
```javascript
{
  account_id: "WY-12345",
  subscriber_name: "John Doe",
  phone: "307-555-1234",
  email: "john@example.com",
  mailing_address: "123 Main St, Lander, WY 82520",
  paper_code: "TJ",
  paper_name: "The Journal",
  current_rate: "Senior 6mo",
  rate_amount: 42.00,
  last_payment_amount: 42.00,
  payment_method: "Check",
  expiration_date: "2025-11-28",
  delivery_type: "MAIL"
}
```

### Trend Data Format
```javascript
{
  snapshot_date: "2025-12-05",
  count: 45,
  change_from_previous: +3,
  change_percent: +7.1
}
```

---

## Appendix B: File Structure

```
web/
├── assets/
│   ├── context-menu.js (new)
│   ├── chart-transition-manager.js (new)
│   ├── subscriber-table-panel.js (new)
│   ├── chart-hover-indicators.js (new)
│   ├── export-utils.js (new)
│   └── detail_panel.js (modify - integrate context menus)
├── api.php (modify - add endpoints)
└── index.html (modify - load new scripts)

docs/
└── plans/
    └── 2025-12-05-contextual-chart-menus-design.md (this file)
```

---

**End of Design Document**
