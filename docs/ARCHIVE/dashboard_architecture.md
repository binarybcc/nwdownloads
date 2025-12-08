# Multi-Level Circulation Dashboard Architecture

## Overview
Web-based dashboard showing real-time and historical subscription metrics across all active newspapers with drill-down capability and vacation hold tracking.

## Core Metrics (What We Track)

### Primary Counts
1. **Total Active Subscriptions** - Status 'A', excluding FN
2. **Currently on Vacation** - Active subs with vacation hold covering today
3. **Deliverable Today** - Active minus on vacation
4. **Void Subscriptions** - Status 'V' (for reference/reporting)

### Business Unit Groupings
- **South Carolina**: TJ (The Journal)
- **Michigan**: TA (The Advertiser)
- **Wyoming**: TR (The Ranger) + LJ (Lander Journal) + WRN (Wind River News)

### Excluded Data
- **FN (Fayette News)** - Sold publication, excluded from all active views
- Can be shown in "Historical/Archive" view if needed

## Dashboard Levels

### Level 1: Overall Dashboard (Landing Page)

```
┌─────────────────────────────────────────────────────────────────┐
│ CIRCULATION DASHBOARD - ALL PAPERS                    [Date]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  📊 OVERALL METRICS                                             │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │ Total Active │ On Vacation  │ Deliverable  │ Void         │ │
│  │    8,256     │     ???      │    ???       │    ???       │ │
│  │   (100%)     │    (?%)      │    (?%)      │              │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
│                                                                  │
│  📍 BY BUSINESS UNIT                      [Click to drill down] │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ South Carolina (TJ)                                   3,129 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 37.9%                 ││
│  │ On Vacation: ??? | Deliverable: ???                         ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Michigan (TA)                                         2,911 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 35.2%                   ││
│  │ On Vacation: ??? | Deliverable: ???                         ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Wyoming (TR + LJ + WRN)                               2,216 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 26.8%                            ││
│  │ On Vacation: ??? | Deliverable: ???                         ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  📈 TREND (Last 30 Days)                                        │
│  [Line chart showing active vs deliverable over time]           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Level 2: Business Unit Dashboard (Click on SC, MI, or WY)

**Example: Wyoming Business Unit**

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to All Papers     WYOMING BUSINESS UNIT         [Date]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  📊 WYOMING TOTALS                                              │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │ Total Active │ On Vacation  │ Deliverable  │ Growth       │ │
│  │    2,216     │     ???      │    ???       │   +/- ???    │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
│                                                                  │
│  📰 BY PUBLICATION                        [Click to drill down] │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ The Ranger (TR)                                       1,326 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 59.8%    ││
│  │ On Vacation: ??? | Deliverable: ??? | Type: Mail           ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Lander Journal (LJ)                                     771 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 34.8%                        ││
│  │ On Vacation: ??? | Deliverable: ??? | Type: Mail           ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Wind River News (WRN)                                   119 ││
│  │ ▓▓▓▓▓ 5.4%                                                  ││
│  │ On Vacation: ??? | Deliverable: ??? | Type: Mail           ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  📈 TRENDS                                                      │
│  [Combined trend chart for WY papers]                           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Level 3: Individual Publication Dashboard

**Example: The Journal (TJ)**

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to SC Unit    THE JOURNAL (TJ) - SENECA, SC    [Date]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  📊 SUBSCRIPTION METRICS                                        │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │ Total Active │ On Vacation  │ Deliverable  │ 30-Day Chg   │ │
│  │    3,129     │     ???      │    ???       │   +/- ???    │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
│                                                                  │
│  📦 DELIVERY BREAKDOWN                                          │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Digital + 2-Day Mail         1,640 (52.4%)                  ││
│  │ Wed & Sat Only                 449 (14.3%)                  ││
│  │ Digital Only                   243 (7.8%)                   ││
│  │ Other Packages                 797 (25.5%)                  ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  🏖️ VACATION HOLDS                                              │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Currently On Hold: ???                                      ││
│  │ Returning Today: ???                                        ││
│  │ Starting Today: ???                                         ││
│  │ Scheduled Future: ???                                       ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  📍 DELIVERY TYPE BREAKDOWN                                     │
│  ┌──────────────┬──────────────┬──────────────────────────────┐│
│  │ Mail (USPS)  │ Carrier      │ Digital Only                 ││
│  │   ???        │    ???       │     243                      ││
│  └──────────────┴──────────────┴──────────────────────────────┘│
│                                                                  │
│  📈 90-DAY TREND                                                │
│  [Detailed trend showing Active, On Vacation, Deliverable]      │
│                                                                  │
│  🎯 TOP RATE PLANS (By Subscriber Count)                        │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Rate 844 - 5-Day Digital + 2-Day Mail         957 subs  ││
│  │ 2. Rate 841 - 5-Day Digital + 2-Day Mail         458 subs  ││
│  │ 3. Rate 902 - Wed & Sat Only                      254 subs  ││
│  │ 4. Rate 843 - 5-Day Digital + 2-Day Mail         225 subs  ││
│  │ 5. Rate 967 - 5-Day Digital + 2-Day Delivery     150 subs  ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Data Requirements

### From Newzware - Daily Exports

#### 1. Active Subscriptions Query
```sql
SELECT
    sp_num,           -- Subscription number
    sp_rate_id,       -- Rate ID
    sp_stat,          -- Status (A, V, I, P)
    sp_beg,           -- Start date
    sp_paid_thru,     -- Paid through date
    sp_route          -- Route (MAIL, INTERNET, route number)
FROM subscrip
WHERE sp_stat = 'A'
```

#### 2. Vacation Holds Query (NEED TO IDENTIFY TABLE NAME)
```sql
-- Need to find vacation table name from ERD or NW
SELECT
    vacation_id,
    subscription_id,
    begin_date,
    restart_date,
    type,
    days
FROM [vacation_table_name]
WHERE restart_date >= CURRENT_DATE  -- Active or future holds
```

#### 3. Rate/Edition Mapping Query
```sql
SELECT
    [Rate.db Rowid],
    [Sub Rate Id],
    [Edition],
    [Description]
FROM retail_rate
```

### Calculated Fields

#### Vacation Status (Per Subscription)
```python
def is_on_vacation(subscription, date, vacation_holds):
    """Check if subscription is on vacation hold for given date"""
    for hold in vacation_holds:
        if hold.subscription_id == subscription.id:
            if hold.begin_date <= date < hold.restart_date:
                return True
    return False
```

#### Daily Counts
```python
total_active = count(subscriptions where status='A' and edition!='FN')
on_vacation_today = count(subscriptions where is_on_vacation(sub, today))
deliverable_today = total_active - on_vacation_today
```

## Database Schema (Historical Storage)

### Tables Needed

#### 1. daily_snapshots
```sql
CREATE TABLE daily_snapshots (
    id INTEGER PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,  -- TJ, TA, TR, LJ, WRN
    total_active INTEGER,
    on_vacation INTEGER,
    deliverable INTEGER,
    void_count INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(snapshot_date, paper_code)
);
```

#### 2. subscription_details (Daily cache)
```sql
CREATE TABLE subscription_details (
    id INTEGER PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    sp_num VARCHAR(50),
    paper_code VARCHAR(10),
    rate_id INTEGER,
    rate_description TEXT,
    status VARCHAR(2),
    delivery_type VARCHAR(50),  -- Mail, Carrier, Digital
    is_on_vacation BOOLEAN,
    vacation_return_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(snapshot_date, paper_code)
);
```

#### 3. vacation_holds (Daily cache)
```sql
CREATE TABLE vacation_holds (
    id INTEGER PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    sp_num VARCHAR(50),
    paper_code VARCHAR(10),
    begin_date DATE,
    restart_date DATE,
    days INTEGER,
    type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(snapshot_date, paper_code)
);
```

#### 4. rate_packages (Reference data)
```sql
CREATE TABLE rate_packages (
    rate_id INTEGER PRIMARY KEY,
    paper_code VARCHAR(10),
    description TEXT,
    delivery_type VARCHAR(50),
    last_updated TIMESTAMP
);
```

## Technology Stack

### Backend
- **Python Flask/FastAPI** - REST API
- **PostgreSQL or SQLite** - Data storage
- **Pandas** - Data processing from CSV exports
- **APScheduler** - Scheduled daily imports

### Frontend
- **React** - UI framework
- **Chart.js or Recharts** - Visualizations
- **Tailwind CSS** - Styling
- **React Router** - Multi-level navigation

### Deployment
- **Vercel or Netlify** - Frontend hosting
- **Railway or Render** - Backend hosting
- **Daily cron job** - Automated NW data export & import

## Daily Data Flow

```
1. Automated NW Query Export (6am daily)
   ├─> Export subscriptions CSV
   ├─> Export vacation holds CSV
   └─> Export rates CSV (weekly)

2. Python ETL Process
   ├─> Read CSV files
   ├─> Match rates to editions
   ├─> Calculate vacation status
   ├─> Store in database
   └─> Generate daily snapshot

3. Dashboard Display
   ├─> Query database for metrics
   ├─> Compare to historical data
   ├─> Calculate trends
   └─> Render visualizations
```

## Next Steps

1. ✅ Identify vacation hold table/fields in Newzware
2. ✅ Create NW queries for subscriptions, vacation holds, rates
3. ✅ Set up PostgreSQL/SQLite database
4. ✅ Build Python ETL script
5. ✅ Build React dashboard frontend
6. ✅ Set up hosting and automation

## Questions to Answer

1. **What is the vacation table name in Newzware?**
   - Check ERD for vacation/hold table
   - May be in subscrip table as fields?

2. **What vacation types exist?**
   - Temporary hold
   - Seasonal
   - Medical
   - Other?

3. **How far back should historical data go?**
   - 90 days? 1 year? All time?

4. **What's the update frequency?**
   - Daily at 6am?
   - Multiple times per day?
   - Real-time?

5. **Who needs access?**
   - Management only?
   - Office staff?
   - Public/read-only?
