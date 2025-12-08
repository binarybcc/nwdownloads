# Circulation Dashboard Enhancements Proposal

**Date:** 2025-12-01
**Purpose:** Add historical data navigation and improve chart readability

---

## 📅 Date Picker & Time Period Selector

### UI Components to Add:

**1. Time Period Selector (Tabs)**
```
[  Week  ] [ Month ] [ Quarter ] [  Year  ] [ Custom ]
    ↑ Active
```

**2. Navigation Controls**
```
[  ← Previous  ] [  This Week ▼  ] [  Next →  ]
```

**3. Date Range Display**
```
📅 Viewing: December 1-7, 2025 (Week 49)
```

### Time Period Definitions:

| Period | Definition | Use Case |
|--------|------------|----------|
| **Week** | Sunday-Saturday | Daily operations, delivery planning |
| **Month** | 4-5 complete weeks | Management reports, trends |
| **Quarter** | 13 weeks (Q1-Q4) | Financial reporting, board meetings |
| **Year** | 52 weeks | Annual reports, YoY comparisons |
| **Custom** | User-selected range | Special analysis, custom reports |

### Week Numbering Standard:
- **ISO 8601 Week Date** system
- Week 1 = first week with Thursday in January
- Weeks run Sunday-Saturday (newspaper industry standard)
- Display as "Week 49, 2025"

---

## 📊 Chart Y-Axis Improvements

###Current Problem:
Auto-scaling can make small fluctuations look dramatic. Example:
- Data range: 3,000-3,050 subscriptions
- Auto-scale shows: 0-4,000 (makes 50-sub change invisible)
- OR: 3,000-3,050 (makes 50-sub change look huge)

### Solution: Smart Scaling Algorithm

**Rules:**
1. **Calculate data range:** `max - min`
2. **Add padding:** Extend range by 10% on each side
3. **Round to clean numbers:**
   - If range < 100: round to 10s
   - If range < 1,000: round to 100s
   - If range > 1,000: round to 500s or 1,000s
4. **Never start at zero** (unless data includes zero)
5. **Show gridlines** at clean intervals

**Example:**
```
Data: 7,450 to 7,580 (range: 130)
Padding: 7,437 to 7,593 (10% each side)
Rounded: 7,400 to 7,600 (clean 100s)
Result: Y-axis shows 7,400-7,600 with gridlines every 50
```

### Percentage Change View Option

Add toggle button:
```
[ Absolute ] / [ Percentage ]
```

**Percentage view:**
- Baseline = first data point in range
- Show: +2.5%, -1.2%, etc.
- Good for comparing trends across different-sized papers

---

## 📈 Industry-Standard Views

### 1. Weekly View (Default)
**Display:**
- Current week (Sun-Sat) main metrics
- Comparison to last week
- Comparison to same week last year

**Chart:**
- 12-week trend (rolling 3 months)
- Daily breakdown if data available

**Example:**
```
Week of Dec 1-7, 2025 (Week 49)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Active: 7,508
  vs Last Week: +42 (↑ 0.6%)
  vs Year Ago: -125 (↓ 1.6%)
```

### 2. Monthly View
**Display:**
- All weeks in selected month
- Month-over-month comparison
- Same month last year comparison

**Chart:**
- 12-month trend
- Weekly breakdown within month

**Aggregation:**
- Use **last Saturday of month** as month's value
- OR average of all weeks in month

### 3. Quarterly View
**Display:**
- 13-week summary
- Quarter-over-quarter comparison
- Year-over-year comparison

**Chart:**
- 4-quarter (1 year) trend
- Weekly breakdown within quarter

**Standard Quarters:**
```
Q1: Weeks 1-13  (Jan-Mar)
Q2: Weeks 14-26 (Apr-Jun)
Q3: Weeks 27-39 (Jul-Sep)
Q4: Weeks 40-52 (Oct-Dec)
```

### 4. Year-over-Year View (Most Important!)
**Display:**
- Side-by-side comparison
- Percentage change
- Seasonal pattern visualization

**Chart:**
- Two lines: This year vs last year
- Same week numbers aligned
- Highlight current week

**Example:**
```
Week 49 Comparison
━━━━━━━━━━━━━━━━━━━━
2025: 7,508 subs
2024: 7,633 subs
Change: -125 (↓ 1.6%)
```

### 5. Rolling 52-Week View
**Display:**
- Trailing 52 weeks from selected date
- Smoothed trend (13-week moving average)
- Year-over-year overlay

**Chart:**
- Full year on one chart
- Shows seasonal patterns
- Removes week-to-week noise

---

## 🎨 UI/UX Enhancements

### Date Picker Component
Use **Flatpickr** (lightweight, no jQuery):
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
```

**Features:**
- Week picker mode (select Sunday, auto-fills through Saturday)
- Month picker mode
- Year picker mode
- Range picker for custom periods
- Disable future dates (can't view data that doesn't exist yet)
- Show available data range (May 2009 - present)

### Navigation Enhancements
```
Time Period: [  Week ▼  ]

[ ⟨ Previous Week ] [ This Week ] [ Next Week ⟩ ]

Jump to date: [📅 Select Date]

Viewing: Week 49, 2025 (Dec 1-7)
Available data: May 2009 - Dec 2025
```

### Comparison Tools
```
Compare to:
○ Previous period (week, month, quarter, year)
○ Same period last year ★ Recommended
○ Custom date range
○ No comparison
```

### Chart Enhancements
1. **Zoom controls** - Allow user to zoom in/out on date range
2. **Pan controls** - Click and drag to move through time
3. **Hover tooltips** - Show exact values on hover
4. **Legend toggle** - Click legend items to hide/show lines
5. **Export buttons** - Download chart as PNG or data as CSV

---

## 🔧 Technical Implementation

### API Endpoint Changes

**Current:** `api.php?action=overview`
**Enhanced:** `api.php?action=overview&period=week&date=2025-12-01&compare=yoy`

**Parameters:**
- `period`: week | month | quarter | year | custom
- `date`: YYYY-MM-DD (any date within desired period)
- `start_date`: YYYY-MM-DD (for custom ranges)
- `end_date`: YYYY-MM-DD (for custom ranges)
- `compare`: none | previous | yoy (year-over-year) | custom
- `compare_date`: YYYY-MM-DD (for custom comparisons)

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "period": {
      "type": "week",
      "start_date": "2025-12-01",
      "end_date": "2025-12-07",
      "label": "Week 49, 2025"
    },
    "current": {
      "total_active": 7508,
      "on_vacation": 2,
      "deliverable": 7506,
      "by_paper": { ... },
      "by_unit": { ... }
    },
    "comparison": {
      "type": "yoy",
      "period": {
        "start_date": "2024-12-01",
        "end_date": "2024-12-07",
        "label": "Week 49, 2024"
      },
      "data": {
        "total_active": 7633,
        "change": -125,
        "change_percent": -1.6
      }
    },
    "trend": [
      {
        "date": "2025-11-29",
        "total_active": 7508,
        ...
      }
    ],
    "available_range": {
      "min_date": "2009-05-16",
      "max_date": "2025-11-29"
    }
  }
}
```

### Database Query Updates

**Week Query:**
```sql
SELECT * FROM daily_snapshots
WHERE snapshot_date >= ?
  AND snapshot_date <= ?
  AND DAYOFWEEK(snapshot_date) = 7  -- Saturday only
ORDER BY snapshot_date
```

**Month Query:**
```sql
SELECT * FROM daily_snapshots
WHERE YEAR(snapshot_date) = ?
  AND MONTH(snapshot_date) = ?
  AND DAYOFWEEK(snapshot_date) = 7  -- Saturdays
ORDER BY snapshot_date
```

**Year-over-Year:**
```sql
SELECT
  WEEK(snapshot_date) as week_num,
  YEAR(snapshot_date) as year,
  SUM(total_active) as total
FROM daily_snapshots
WHERE YEAR(snapshot_date) IN (?, ?)
  AND DAYOFWEEK(snapshot_date) = 7
GROUP BY week_num, year
ORDER BY week_num, year
```

---

## 🎯 Phased Implementation Plan

### Phase 1: Date Navigation (Week View)
1. Add date picker (week selector)
2. Add Previous/Next week buttons
3. Update API to accept date parameter
4. Test with historical data

### Phase 2: Chart Improvements
1. Implement smart Y-axis scaling
2. Add zoom/pan controls
3. Improve tooltips
4. Add legend toggle

### Phase 3: Additional Period Views
1. Add Month view
2. Add Quarter view
3. Add Year view
4. Add Custom range

### Phase 4: Comparison Features
1. Year-over-year comparison
2. Previous period comparison
3. Side-by-side charts
4. Percentage change views

### Phase 5: Advanced Features
1. Export to CSV/PDF
2. Saved views/bookmarks
3. Email reports
4. Alerts for significant changes

---

## 📐 Design Mockup

```
┌─────────────────────────────────────────────────────────────┐
│ 📊 Circulation Dashboard                        👤 Admin ▼  │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  View: [ Week ▼] [ Month ] [ Quarter ] [ Year ] [ Custom ]  │
│                                                               │
│  [ ⟨ Prev ] [ Week 49, 2025 ▼ ] [ Next ⟩ ]  Compare: [YoY▼]│
│                                                               │
│  📅 Dec 1-7, 2025  vs  📅 Dec 1-7, 2024                     │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │ Total Active│ │ On Vacation │ │ Deliverable │           │
│  │   7,508     │ │      2      │ │   7,506     │           │
│  │  vs YoY     │ │  vs YoY     │ │  vs YoY     │           │
│  │   ↓ 1.6%    │ │   ↑ 0.0%    │ │   ↓ 1.6%    │           │
│  └─────────────┘ └─────────────┘ └─────────────┘           │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│  12-Week Trend                                                │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ 7,600 ┼                                    ●         │   │
│  │       │                              ●   ●   ●       │   │
│  │ 7,550 ┼                        ●   ●               │   │
│  │       │                  ●   ●                       │   │
│  │ 7,500 ┼            ●   ●                             │   │
│  │       │      ●   ●                                   │   │
│  │ 7,450 ┼────────────────────────────────────────────│   │
│  │       Sep  Oct  Nov  Dec                            │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ Recommended Approach

**Start with Phase 1 & 2:**
1. Week-based navigation (matches your data structure)
2. Fixed Y-axis scaling (immediate readability improvement)
3. Year-over-year comparison (most valuable for newspapers)

**Then expand to:**
4. Month/Quarter/Year aggregations
5. Advanced analytics and exports

This gives you immediate value while building toward comprehensive analytics.

---

## 📚 Industry References

**Newspaper Circulation Standards:**
- Alliance for Audited Media (AAM) - Week-based reporting
- News Media Alliance - YoY comparisons standard
- Audit Bureau of Circulations (ABC) - Weekly snapshots

**Dashboard Best Practices:**
- Stephen Few: "Information Dashboard Design"
- Edward Tufte: "The Visual Display of Quantitative Information"
- Cole Nussbaumer Knaflic: "Storytelling with Data"

---

**Questions to Consider:**

1. Do you want to see daily breakdowns within a week? (if you start collecting daily data)
2. Should "This Week" default to current incomplete week, or last complete week?
3. Do you want alerts when circulation drops below thresholds?
4. Export formats needed: PDF, Excel, CSV?

Let me know your priorities and I'll start implementing!
