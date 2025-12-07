# Phase 1 Requirements: Publication-Aware Weekly Dashboard

## Document Version
- **Version**: 1.0
- **Date**: 2025-12-01
- **Status**: Planning
- **Author**: Claude Code
- **Project**: Circulation Dashboard Enhancement

---

## Executive Summary

Transform the current daily snapshot dashboard into a publication-aware weekly summary dashboard that:
1. Shows weekly aggregated metrics for executive (COO) review
2. Allows drill-down to individual papers for detailed analysis
3. Only displays circulation data on print publication days
4. Supports future integration with Google Analytics for digital metrics
5. Enables week-over-week trend analysis

---

## Business Context

### Publication Schedule (CORRECTED)

| Paper | Code | Business Unit | Print Days | Digital Days | Notes |
|-------|------|---------------|------------|--------------|-------|
| The Journal | TJ | South Carolina | Wed, Sat | Tue-Sat | 2 print/week |
| The Advertiser | TA | Michigan | Wed | None | Print only, 1/week |
| The Register | TR | Wyoming | Wed, Sat | Print days only | 2 print/week |
| Lake Journal | LJ | Wyoming | Wed, Sat | Print days only | 2 print/week |
| Wyoming Review News | WRN | Wyoming | Thu | Print days only | 1 print/week |

**Total Print Editions per Week**: 9 editions across 5 papers

### Key Business Requirements

1. **Primary Users**: COO (weekly review), Circulation Management (operational)
2. **Primary Use Case**: Trend analysis and operational heartbeat monitoring
3. **Check Frequency**: Weekly basis, but accessible any day of the week
4. **Current State**: Multiple reports must be run and manually analyzed
5. **Goal**: Single dashboard replacing multiple report analysis

---

## Current System State

### What We Have Today (2025-12-01)

✅ **Working Components:**
- Docker container running on Synology NAS (192.168.1.254:8080)
- MariaDB database: `circulation_dashboard`
- Web-based CSV upload interface (upload.html)
- Daily snapshot storage in `daily_snapshots` table
- API endpoint returning current dashboard data (api.php)
- Frontend dashboard displaying real-time metrics (index.html + app.js)

✅ **Database Tables (Existing):**
```sql
daily_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    paper_name VARCHAR(100),
    business_unit VARCHAR(100),
    total_active INT DEFAULT 0,
    on_vacation INT DEFAULT 0,
    deliverable INT DEFAULT 0,
    mail_delivery INT DEFAULT 0,
    carrier_delivery INT DEFAULT 0,
    digital_only INT DEFAULT 0,
    UNIQUE KEY unique_snapshot (snapshot_date, paper_code)
)

import_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_date DATETIME NOT NULL,
    records_processed INT DEFAULT 0,
    status VARCHAR(50)
)
```

✅ **Current Data:**
- **Snapshot Date**: 2025-12-01 (TODAY ONLY)
- **Total Active**: 3,482 subscriptions
- **Papers**: TJ (3,025), TA (352), Wyoming papers (105)
- **Historical Data**: NONE - only today's upload exists

⚠️ **Critical Limitation:**
We have ZERO historical data. We must build up data over time OR backfill if possible.

---

## Phase 1 Objectives

### Goal
Create the database foundation for publication-aware weekly summaries.

### Deliverables

1. **New Database Table**: `publication_schedule`
   - Defines which papers publish on which days
   - Distinguishes print vs digital days
   - Reference data for all dashboard logic

2. **New Database View**: `weekly_summary`
   - Aggregates daily snapshots into weekly metrics
   - Calculates week-over-week changes
   - Filters to only relevant publication days

3. **Enhanced API Endpoint**: New endpoints in api.php
   - `?action=weekly_summary` - Returns weekly aggregated data
   - `?action=paper_detail&paper=XX&week=YYYY-MM-DD` - Returns daily details
   - `?action=publication_schedule` - Returns publication calendar

4. **Documentation**: Clear SQL and logic for future reference

5. **Testing**: Verify queries work with minimal data (1 week)

---

## Technical Specification

### 1. Database Schema: `publication_schedule` Table

**Purpose**: Define publication days for each paper (reference data)

```sql
CREATE TABLE publication_schedule (
    paper_code VARCHAR(10) NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ... 6=Saturday',
    has_print BOOLEAN DEFAULT FALSE COMMENT 'True if print edition publishes',
    has_digital BOOLEAN DEFAULT FALSE COMMENT 'True if digital content updates',
    PRIMARY KEY (paper_code, day_of_week),
    FOREIGN KEY (paper_code) REFERENCES daily_snapshots(paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Publication schedule for all papers';
```

**Seed Data**:
```sql
-- The Journal (TJ): Print Wed/Sat, Digital Tue-Sat
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('TJ', 2, FALSE, TRUE),   -- Tuesday: Digital only
('TJ', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('TJ', 4, FALSE, TRUE),   -- Thursday: Digital only
('TJ', 5, FALSE, TRUE),   -- Friday: Digital only
('TJ', 6, TRUE, TRUE),    -- Saturday: Print + Digital

-- The Advertiser (TA): Print Wed only, no digital
('TA', 3, TRUE, FALSE),   -- Wednesday: Print only

-- The Register (TR): Print Wed/Sat, Digital on print days
('TR', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('TR', 6, TRUE, TRUE),    -- Saturday: Print + Digital

-- Lake Journal (LJ): Print Wed/Sat, Digital on print days
('LJ', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('LJ', 6, TRUE, TRUE),    -- Saturday: Print + Digital

-- Wyoming Review News (WRN): Print Thu only, Digital on print days
('WRN', 4, TRUE, TRUE);   -- Thursday: Print + Digital
```

**Data Validation**:
- Total rows: 13 (TJ=5, TA=1, TR=2, LJ=2, WRN=1)
- Total print days per week: 9 editions
- Each paper must have at least 1 print day

---

### 2. Database View: `weekly_summary`

**Purpose**: Aggregate daily snapshots into weekly metrics

```sql
CREATE VIEW weekly_summary AS
SELECT
    -- Week identification (Monday = start of week)
    DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY) as week_start_date,
    CONCAT(
        DATE_FORMAT(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), '%b %d'),
        ' - ',
        DATE_FORMAT(DATE_ADD(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), INTERVAL 6 DAY), '%b %d, %Y')
    ) as week_label,

    -- Paper identification
    ds.paper_code,
    ds.paper_name,
    ds.business_unit,

    -- Weekly metrics (only from print days)
    COUNT(DISTINCT ds.snapshot_date) as print_days_reported,
    AVG(ds.total_active) as avg_total_active,
    AVG(ds.deliverable) as avg_deliverable,
    MAX(ds.total_active) as max_total_active,
    MIN(ds.total_active) as min_total_active,
    MAX(ds.total_active) - MIN(ds.total_active) as weekly_variation,

    -- Delivery method averages
    AVG(ds.mail_delivery) as avg_mail,
    AVG(ds.carrier_delivery) as avg_carrier,
    AVG(ds.digital_only) as avg_digital,
    AVG(ds.on_vacation) as avg_vacation,

    -- Data quality
    MAX(ds.snapshot_date) as latest_snapshot_in_week

FROM daily_snapshots ds
INNER JOIN publication_schedule ps
    ON ds.paper_code = ps.paper_code
    AND DAYOFWEEK(ds.snapshot_date) - 1 = ps.day_of_week  -- Match day of week
    AND ps.has_print = TRUE  -- Only include print publication days

WHERE ds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)  -- Last 90 days

GROUP BY
    week_start_date,
    week_label,
    ds.paper_code,
    ds.paper_name,
    ds.business_unit

ORDER BY
    week_start_date DESC,
    ds.business_unit,
    ds.paper_name;
```

**Key Logic**:
- Weeks start on Monday (industry standard)
- Only includes snapshots from print publication days
- Averages smooth out daily fluctuations
- Tracks variation within the week (max - min)
- Limited to 90 days of history (performance)

**Expected Output** (once we have data):
```
week_start_date | week_label        | paper_code | print_days_reported | avg_total_active | avg_deliverable
2025-11-25      | Nov 25 - Dec 1    | TJ         | 2                   | 3024.5          | 3022.5
2025-11-25      | Nov 25 - Dec 1    | TA         | 1                   | 352.0           | 352.0
2025-11-25      | Nov 25 - Dec 1    | TR         | 2                   | 45.0            | 45.0
...
```

---

### 3. API Enhancements: New Endpoints

**File**: `/volume1/circulation/web/api.php`

#### Endpoint 1: Weekly Summary

**Request**: `GET /api.php?action=weekly_summary&weeks=4`

**Parameters**:
- `weeks` (optional, default=4): Number of recent weeks to return

**Response**:
```json
{
  "success": true,
  "current_week": {
    "week_start": "2025-11-25",
    "week_label": "Nov 25 - Dec 1, 2025",
    "total_active": 3482,
    "total_deliverable": 3480,
    "print_editions": 9,
    "papers": [
      {
        "paper_code": "TJ",
        "paper_name": "The Journal",
        "business_unit": "South Carolina",
        "avg_active": 3024.5,
        "avg_deliverable": 3022.5,
        "print_days_reported": 2,
        "expected_print_days": 2,
        "data_complete": true
      },
      // ... more papers
    ]
  },
  "historical_weeks": [
    // Previous 3 weeks (if data exists)
  ]
}
```

#### Endpoint 2: Paper Detail

**Request**: `GET /api.php?action=paper_detail&paper=TJ&week=2025-11-25`

**Parameters**:
- `paper` (required): Paper code (TJ, TA, TR, LJ, WRN)
- `week` (optional, default=current week): Week start date (Monday)

**Response**:
```json
{
  "success": true,
  "paper_code": "TJ",
  "paper_name": "The Journal",
  "business_unit": "South Carolina",
  "week_start": "2025-11-25",
  "week_label": "Nov 25 - Dec 1, 2025",
  "print_days": [
    {
      "date": "2025-11-27",
      "day_name": "Wednesday",
      "total_active": 3023,
      "deliverable": 3021,
      "on_vacation": 2,
      "mail": 2392,
      "carrier": 305,
      "digital": 326
    },
    {
      "date": "2025-11-30",
      "day_name": "Saturday",
      "total_active": 3026,
      "deliverable": 3024,
      "on_vacation": 0,
      "mail": 2395,
      "carrier": 306,
      "digital": 325
    }
  ],
  "digital_days": [
    "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"
  ]
}
```

#### Endpoint 3: Publication Schedule

**Request**: `GET /api.php?action=publication_schedule`

**Response**:
```json
{
  "success": true,
  "papers": [
    {
      "paper_code": "TJ",
      "paper_name": "The Journal",
      "print_days": ["Wednesday", "Saturday"],
      "digital_days": ["Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
      "print_days_per_week": 2
    },
    // ... more papers
  ]
}
```

---

## Data Collection Strategy

### Critical Issue: We Have ZERO Historical Data

**Current State**: Only 2025-12-01 snapshot exists

**Problem**: Weekly summaries require multiple days of data

### Option 1: Build Up Data Naturally ⏰ (RECOMMENDED)

**Strategy**: Import daily snapshots going forward, wait for data accumulation

**Timeline**:
- **Day 1 (Today, Mon Dec 1)**: TJ digital, no print editions - import but won't show
- **Day 2 (Tue Dec 2)**: TJ digital only - import but won't show
- **Day 3 (Wed Dec 3)**: TJ, TA, TR, LJ print days - **FIRST MEANINGFUL DATA**
- **Day 4 (Thu Dec 4)**: WRN print day - more data
- **Day 5 (Fri Dec 5)**: TJ digital only
- **Day 6 (Sat Dec 6)**: TJ, TR, LJ print days - **FIRST COMPLETE WEEK**
- **Day 7 (Sun Dec 7)**: No publications

**First Useful Dashboard**: After Saturday, Dec 7 (6 days from now)

**Pros**:
- ✅ Accurate, real data
- ✅ No retroactive assumptions
- ✅ Clean data from start

**Cons**:
- ❌ Can't show dashboard for ~1 week
- ❌ No historical trends for 4-6 weeks

### Option 2: Backfill Historical Data 📊 (IF POSSIBLE)

**Strategy**: If Newzware has historical exports, import past weeks

**Requirements**:
- Can you export subscriptions/vacations/rates from past dates?
- Do the CSVs have a snapshot date field?
- How far back can you go? (suggest 4-12 weeks)

**Process** (if feasible):
1. Export CSVs for each past Wednesday/Thursday/Saturday for 4 weeks
2. Run upload.php for each date (modify to accept date parameter)
3. Populate database with historical snapshots
4. Dashboard immediately has trend data

**Pros**:
- ✅ Dashboard useful immediately
- ✅ 4-week trends visible from day 1
- ✅ Week-over-week comparisons work

**Cons**:
- ❌ Requires manual export for each date
- ❌ More complex upload process
- ❌ Assumes Newzware can produce historical exports

### Option 3: Hybrid Approach 🎯

**Strategy**:
1. Backfill last week (if possible) - get baseline
2. Collect going forward naturally
3. Dashboard partially functional in 1 week, fully functional in 2 weeks

---

## Implementation Checklist

### Pre-Implementation (DO FIRST)

- [ ] **Decide on data strategy**: Natural accumulation vs Backfill?
- [ ] **Test Newzware historical exports**: Can we export past dates?
- [ ] **Determine acceptable launch timeline**: Ok to wait 1 week?

### Phase 1A: Database Foundation (30-60 minutes)

- [ ] Create `publication_schedule` table
- [ ] Insert seed data (13 rows)
- [ ] Verify data: `SELECT * FROM publication_schedule ORDER BY paper_code, day_of_week;`
- [ ] Create `weekly_summary` view
- [ ] Test view (will return 0 rows until we have weekly data)
- [ ] Document table schema in project docs

### Phase 1B: API Development (2-3 hours)

- [ ] Add `weekly_summary` endpoint to api.php
- [ ] Add `paper_detail` endpoint to api.php
- [ ] Add `publication_schedule` endpoint to api.php
- [ ] Test all endpoints with Postman or curl
- [ ] Document API responses

### Phase 1C: Upload Process Enhancement (1-2 hours)

**IF doing backfill**:
- [ ] Modify upload.php to accept `snapshot_date` parameter
- [ ] Create batch upload script for historical dates
- [ ] Test with sample historical data

**IF natural accumulation**:
- [ ] Document daily upload schedule
- [ ] Set calendar reminders for print days
- [ ] Create upload checklist (Wed, Thu, Sat)

### Phase 1D: Testing & Validation (1 hour)

- [ ] Test queries with minimal data (1-2 days)
- [ ] Verify weekly calculations are correct
- [ ] Check publication schedule filtering works
- [ ] Confirm API returns expected JSON structure

---

## Success Criteria

### Phase 1 Complete When:

✅ **Database**:
- `publication_schedule` table exists with 13 rows
- `weekly_summary` view executes without errors
- Queries correctly filter to print days only

✅ **API**:
- All 3 new endpoints return valid JSON
- Error handling works (no weeks available, invalid paper code)
- Responses match documented schema

✅ **Documentation**:
- SQL scripts saved and annotated
- API endpoint documentation complete
- Data collection strategy documented

✅ **Testing**:
- Queries tested with sample data
- Edge cases handled (no data for week, missing paper)

---

## Known Risks & Mitigation

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| No historical data available | Dashboard empty for 1+ weeks | High | Accept natural accumulation, set expectations |
| Newzware can't export historical | Can't backfill | Medium | Proceed with natural accumulation |
| Weekly view incorrect on partial weeks | Wrong metrics displayed | Medium | Add "data incomplete" warning if < expected print days |
| Users expect immediate results | Disappointment | High | Clear communication about timeline |

---

## Future Phases (Not in Phase 1)

- **Phase 2**: Frontend redesign (weekly landing page, drill-down navigation)
- **Phase 3**: Google Analytics integration
- **Phase 4**: Export/reporting features
- **Phase 5**: Alerting and anomaly detection

---

## Questions to Resolve Before Starting

### Critical Questions:

1. **Can Newzware export subscriptions data for past dates?**
   - If yes: How far back? (4 weeks preferred)
   - If no: Accept 1-week delay for dashboard

2. **Who will run the daily uploads?**
   - Automated script?
   - Manual process?
   - Which days? (Wed, Thu, Sat minimum)

3. **Is 1 week delay acceptable for initial launch?**
   - Or must we have historical data immediately?

### Nice-to-Know Questions:

4. What's the earliest date you want trends from? (4 weeks? 12 weeks? 1 year?)
5. Should we archive old snapshots after X months?
6. Any specific week start preference? (Monday standard, but can change)

---

## Appendix: SQL Quick Reference

### Check Publication Schedule
```sql
SELECT
    paper_code,
    GROUP_CONCAT(CASE WHEN has_print THEN
        CASE day_of_week
            WHEN 0 THEN 'Sun'
            WHEN 1 THEN 'Mon'
            WHEN 2 THEN 'Tue'
            WHEN 3 THEN 'Wed'
            WHEN 4 THEN 'Thu'
            WHEN 5 THEN 'Fri'
            WHEN 6 THEN 'Sat'
        END
    END ORDER BY day_of_week SEPARATOR ', ') as print_days
FROM publication_schedule
WHERE has_print = TRUE
GROUP BY paper_code;
```

### Check Weekly Summary (once data exists)
```sql
SELECT * FROM weekly_summary
WHERE week_start_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
ORDER BY week_start_date DESC, paper_code;
```

### Verify Data Completeness
```sql
-- Expected print days per paper per week
SELECT
    ps.paper_code,
    COUNT(*) as expected_print_days_per_week
FROM publication_schedule ps
WHERE ps.has_print = TRUE
GROUP BY ps.paper_code;

-- Actual snapshots collected this week
SELECT
    paper_code,
    COUNT(*) as actual_snapshots_this_week
FROM daily_snapshots
WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
GROUP BY paper_code;
```

---

## Document History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2025-12-01 | Initial requirements | Claude Code |

---

## Sign-Off

**Technical Reviewer**: ___________________ Date: ___________

**Business Owner (COO)**: ___________________ Date: ___________

**Implementation Start Approved**: Yes / No
