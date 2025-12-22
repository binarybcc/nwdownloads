# Churn Tracking System - Complete Business Logic Documentation

**Version:** 1.0
**Created:** 2025-12-16
**Purpose:** Document churn tracking architecture, business logic, and integration opportunities for business intelligence and growth strategy

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Business Context](#business-context)
3. [Data Architecture](#data-architecture)
4. [Business Metrics Definitions](#business-metrics-definitions)
5. [Integration with Existing Systems](#integration-with-existing-systems)
6. [Business Intelligence Opportunities](#business-intelligence-opportunities)
7. [Calculation Methodologies](#calculation-methodologies)
8. [Data Flow and Processing](#data-flow-and-processing)
9. [Future Enhancement Opportunities](#future-enhancement-opportunities)
10. [Glossary](#glossary)

---

## Executive Summary

The churn tracking system monitors subscriber renewal and expiration behavior across all publications, providing actionable intelligence for retention strategies and revenue forecasting.

**Core Value Proposition:**
- **Predictive Intelligence**: Identify at-risk subscriber segments before they churn
- **Performance Benchmarking**: Compare renewal rates across publications, subscription types, and time periods
- **Revenue Impact**: Quantify the financial impact of renewal/churn trends
- **Strategic Planning**: Data-driven decisions for retention campaigns and pricing strategies

**Key Metrics:**
- Renewal Rate: % of expiring subscriptions that renewed
- Churn Rate: % of expiring subscriptions that stopped
- Net Change: Renewals minus expirations (growth indicator)
- Cohort Performance: Renewal rates by subscription type and publication

**Data Sources:**
- **renewal_events**: Individual renewal/expiration events from Newzware churn reports
- **churn_daily_summary**: Pre-aggregated daily metrics for fast querying

**Time Horizon:**
- Historical data: September 2024 - Present (~28,000 events)
- Reporting periods: 4 weeks (28 days) or 12 weeks (84 days)
- Granularity: Daily snapshots

---

## Business Context

### Publications Tracked

**Wyoming (3 publications)**
- The Journal (TJ) - Flagship publication, largest subscriber base
- The Ranger (TR) - Community newspaper
- The Lander Journal (LJ) - Regional publication
- Wind River News (WRN) - Local news

**Michigan (1 publication)**
- The Advertiser (TA) - Print-only weekly

**South Carolina (1 publication)**
- The Journal (TJ) - Same brand as Wyoming, different market

### Subscription Types

**1. REGULAR Subscriptions**
- **Characteristics**: Annual billing cycle, traditional subscription model
- **Business Importance**: Core revenue stream, highest lifetime value
- **Typical Renewal Rate**: 85-90% (industry benchmark)
- **Churn Risk Factors**: Price sensitivity, digital migration, demographic changes

**2. MONTHLY Subscriptions**
- **Characteristics**: Month-to-month billing, flexible commitment
- **Business Importance**: Lower barrier to entry, attracts younger demographics
- **Typical Renewal Rate**: 90-95% (higher than regular due to auto-renewal)
- **Churn Risk Factors**: Payment failures, card expirations, voluntary cancellations

**3. COMPLIMENTARY Subscriptions**
- **Characteristics**: Free subscriptions for VIPs, advertisers, staff, promotional
- **Business Importance**: Maintains relationships, inflates circulation numbers
- **Typical Renewal Rate**: 75-85% (manual renewal process, less priority)
- **Churn Risk Factors**: Administrative oversight, budget cuts, relationship changes

### Business Unit Structure

**Wyoming**: 3 publications (TJ, TR, LJ, WRN)
**Michigan**: 1 publication (TA)
**South Carolina**: 1 publication (TJ)

**Cross-Unit Considerations:**
- Same brand name (TJ) in different markets = independent subscriber bases
- Different market dynamics (Wyoming = rural, Michigan = small town, SC = suburban)
- Shared operational costs but separate revenue streams

---

## Data Architecture

### Database Schema

#### Table: `renewal_events`

**Purpose:** Granular event log of every subscription expiration and renewal decision

**Schema:**
```sql
CREATE TABLE renewal_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Source tracking
    upload_id BIGINT,                    -- Future: Link to raw_uploads table
    source_filename VARCHAR(255),         -- Original Newzware CSV filename

    -- Event identification
    event_date DATE NOT NULL,             -- Issue date when subscription expired/renewed
    sub_num VARCHAR(50) NOT NULL,         -- Subscriber number (unique identifier)
    paper_code VARCHAR(10) NOT NULL,      -- Publication (TJ, TA, TR, LJ, WRN)

    -- Renewal decision
    status ENUM('RENEW', 'EXPIRE') NOT NULL,

    -- Subscription characteristics
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY') NOT NULL,

    -- Metadata
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_event_date (event_date),
    INDEX idx_sub_paper (sub_num, paper_code),
    INDEX idx_paper_date (paper_code, event_date),
    INDEX idx_status (status),
    INDEX idx_type (subscription_type),
    INDEX idx_paper_type_date (paper_code, subscription_type, event_date),

    -- Prevent duplicates
    UNIQUE KEY unique_renewal_event (sub_num, paper_code, event_date)
);
```

**Record Count:** ~28,046 events (Sept 2024 - Dec 2025)

**Data Characteristics:**
- **Immutable**: Events never updated once imported
- **Append-only**: New events added, old events preserved
- **Complete history**: Every expiration decision recorded
- **Subscriber-level granularity**: Individual subscriber tracking

**Business Value:**
- **Cohort analysis**: Track specific subscriber segments over time
- **Churn prediction**: Identify patterns in churned subscribers
- **Drill-down capability**: Investigate specific renewal events
- **Audit trail**: Complete record of all renewal decisions

#### Table: `churn_daily_summary`

**Purpose:** Pre-aggregated daily metrics for fast dashboard queries

**Schema:**
```sql
CREATE TABLE churn_daily_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Dimensions
    snapshot_date DATE NOT NULL,          -- Date of churn calculation
    paper_code VARCHAR(10) NOT NULL,      -- Publication
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY', 'ALL') NOT NULL,

    -- Counts
    expiring_count INT NOT NULL DEFAULT 0,  -- Subscriptions expiring this date
    renewed_count INT NOT NULL DEFAULT 0,   -- Number that renewed
    stopped_count INT NOT NULL DEFAULT 0,   -- Number that stopped

    -- Calculated rates (percentages)
    renewal_rate DECIMAL(5,2),              -- % that renewed (0-100)
    churn_rate DECIMAL(5,2),                -- % that stopped (0-100)

    -- Metadata
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_daily_churn (snapshot_date, paper_code, subscription_type),
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code),
    INDEX idx_type (subscription_type),
    INDEX idx_paper_date (paper_code, snapshot_date)
);
```

**Record Count:** ~1,180 daily summaries

**Data Characteristics:**
- **Aggregated**: Pre-calculated metrics for speed
- **Daily granularity**: One record per date/publication/type combination
- **Rollup capability**: 'ALL' subscription_type = total across all types
- **Fast queries**: Dashboard loads in <200ms

**Business Value:**
- **Real-time dashboards**: Instant metric access
- **Trend analysis**: Time-series data for charts
- **Comparative analysis**: Cross-publication/type comparisons
- **Performance optimization**: No need to aggregate 28k events on every query

### Data Relationships

```
renewal_events (28,046 records)
    ├─ Aggregates to → churn_daily_summary (1,180 records)
    ├─ Groups by → paper_code (5 publications)
    ├─ Groups by → subscription_type (3 types)
    └─ Groups by → event_date (daily)

churn_daily_summary
    ├─ Links to → daily_snapshots (by snapshot_date, paper_code)
    │   └─ Provides: Subscriber counts, delivery metrics
    │
    ├─ Links to → publication_schedule (by paper_code)
    │   └─ Provides: Print/digital publication days
    │
    └─ Potential links (future):
        ├─ revenue_data (by date, paper) → Financial impact
        ├─ campaign_data (by date, paper) → Marketing effectiveness
        └─ subscriber_demographics (by sub_num) → Churn risk profiling
```

### Integration with Existing Tables

#### `daily_snapshots` (Circulation Dashboard)

**Relationship:** Both tables share `snapshot_date` and `paper_code`

**Integration Opportunity:**
```sql
-- Combined view: Churn rate alongside active subscriber counts
SELECT
    ds.snapshot_date,
    ds.paper_code,
    ds.total_active,              -- From daily_snapshots
    ds.deliverable,               -- From daily_snapshots
    cds.renewal_rate,             -- From churn_daily_summary
    cds.renewed_count,            -- From churn_daily_summary
    cds.stopped_count             -- From churn_daily_summary
FROM daily_snapshots ds
LEFT JOIN churn_daily_summary cds
    ON ds.snapshot_date = cds.snapshot_date
    AND ds.paper_code = cds.paper_code
    AND cds.subscription_type = 'ALL'
WHERE ds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK);
```

**Business Intelligence Value:**
- **Growth vs Retention**: Are we growing due to new subscribers or retention?
- **Churn Impact**: How many subscribers did we lose vs how many are active?
- **Net Change Analysis**: Active subscribers + renewals - churn = projected growth

#### `publication_schedule` (Publication Days)

**Relationship:** Both tables reference `paper_code`

**Integration Opportunity:**
```sql
-- Churn patterns by publication day
SELECT
    ps.paper_code,
    ps.print_days,
    ps.digital_days,
    AVG(cds.renewal_rate) as avg_renewal_rate
FROM publication_schedule ps
JOIN churn_daily_summary cds ON ps.paper_code = cds.paper_code
WHERE cds.subscription_type = 'ALL'
GROUP BY ps.paper_code, ps.print_days, ps.digital_days;
```

**Business Intelligence Value:**
- **Publication Frequency Impact**: Do more frequent publications have higher retention?
- **Digital Adoption**: Are digital-only days correlated with churn?

---

## Business Metrics Definitions

### Primary Metrics

#### 1. Renewal Rate

**Definition:** Percentage of expiring subscriptions that chose to renew

**Formula:**
```
Renewal Rate = (Renewed Count / Expiring Count) × 100
```

**Example:**
- 100 subscriptions expired on 2025-12-01
- 89 renewed, 11 stopped
- Renewal Rate = (89 / 100) × 100 = 89%

**Business Interpretation:**
- **85-100%**: Excellent retention, healthy business
- **70-84%**: Good retention, room for improvement
- **60-69%**: Warning level, investigate causes
- **Below 60%**: Critical, immediate action required

**Factors Affecting Renewal Rate:**
- **Product quality**: Content relevance, local news coverage
- **Customer service**: Responsiveness, problem resolution
- **Pricing**: Competitive rates, perceived value
- **Demographics**: Age, income, education level
- **Competition**: Alternative news sources, digital alternatives
- **Seasonality**: Holiday periods, summer months

#### 2. Churn Rate

**Definition:** Percentage of expiring subscriptions that did not renew

**Formula:**
```
Churn Rate = (Stopped Count / Expiring Count) × 100
```

**Relationship:**
```
Churn Rate = 100 - Renewal Rate
```

**Example:**
- 100 subscriptions expired on 2025-12-01
- 89 renewed, 11 stopped
- Churn Rate = (11 / 100) × 100 = 11%

**Business Interpretation:**
- **0-15%**: Industry standard, acceptable loss
- **15-30%**: Elevated churn, investigate causes
- **30-40%**: High churn, urgent intervention needed
- **Above 40%**: Crisis level, fundamental business issues

#### 3. Net Change

**Definition:** Net subscriber movement from renewals and expirations

**Formula:**
```
Net Change = Renewed Count - Stopped Count
```

**Example:**
- 89 renewed, 11 stopped
- Net Change = 89 - 11 = +78 subscribers retained

**Business Interpretation:**
- **Positive**: Growth through retention (good)
- **Zero**: Breakeven, need new subscriber acquisition
- **Negative**: Shrinking subscriber base (concerning)

#### 4. Expiring Count

**Definition:** Number of subscriptions coming up for renewal on a given date

**Business Importance:**
- **Volume indicator**: How many renewal decisions occur
- **Revenue at risk**: Total potential lost revenue
- **Campaign targeting**: Who to contact for retention

**Patterns:**
- **Seasonal spikes**: End of year, post-holiday
- **Monthly billing cycles**: Predictable monthly peaks
- **Promotional expirations**: Batch expirations from campaigns

### Secondary Metrics (Calculated)

#### 5. Renewal Count Trend

**Definition:** Change in renewal count over time periods

**Formula:**
```
Trend = ((Current Period Renewals - Previous Period Renewals) / Previous Period Renewals) × 100
```

**Business Use:**
- **Growth trajectory**: Are renewals increasing or decreasing?
- **Campaign effectiveness**: Did retention campaigns work?
- **Seasonal adjustments**: Account for predictable fluctuations

#### 6. Churn Velocity

**Definition:** Rate of acceleration in churn

**Formula:**
```
Churn Velocity = (Current Period Churn Rate - Previous Period Churn Rate) / Time
```

**Business Use:**
- **Early warning system**: Detect sudden increases in churn
- **Crisis detection**: Identify rapid deterioration
- **Intervention timing**: When to launch retention campaigns

#### 7. Lifetime Churn Rate

**Definition:** Cumulative churn rate since data collection began

**Formula:**
```
Lifetime Churn Rate = (Total Stopped / Total Expiring) × 100
```

**Business Use:**
- **Historical baseline**: What's normal for our business?
- **Long-term trends**: Are we improving or declining?
- **Benchmark comparison**: How do we compare to industry?

---

## Calculation Methodologies

### Time Range Calculations

#### 4-Week View (28 days)

**Start Date:** `end_date - 28 days`
**End Date:** `end_date` (default: today)

**SQL Logic:**
```sql
SET @end_date = CURDATE();
SET @start_date = DATE_SUB(@end_date, INTERVAL 28 DAY);

SELECT
    SUM(renewed_count) as total_renewed,
    SUM(stopped_count) as total_stopped,
    SUM(expiring_count) as total_expiring,
    (SUM(renewed_count) / SUM(expiring_count)) * 100 as renewal_rate
FROM churn_daily_summary
WHERE snapshot_date BETWEEN @start_date AND @end_date
  AND subscription_type = 'ALL';
```

**Business Justification:**
- **28 days = 4 weeks**: Aligns with print publication cycles (weekly publications)
- **Recent trends**: Short enough to detect emerging patterns
- **Actionable**: Time frame for immediate retention campaigns

#### 12-Week View (84 days)

**Start Date:** `end_date - 84 days`
**End Date:** `end_date` (default: today)

**Business Justification:**
- **84 days = 12 weeks = 3 months**: Standard quarterly view
- **Seasonal patterns**: Long enough to smooth out weekly fluctuations
- **Strategic planning**: Time frame for strategic decisions
- **Comparative analysis**: Compare quarters year-over-year (future)

### Week Navigation Logic

**Previous Week:** `end_date - 7 days`
**Next Week:** `end_date + 7 days`
**This Week:** `today`

**Date Boundaries:**
- **Earliest:** 2024-09-01 (first churn data available)
- **Latest:** `today` (cannot view future)

**Business Use:**
- **Week-over-week comparison**: Did this week perform better than last?
- **Weekly reporting**: Standard cadence for management updates
- **Recent trends**: Focus on most recent performance

### Aggregation Methods

#### By Subscription Type

**Goal:** Compare renewal performance across subscription types

**SQL:**
```sql
SELECT
    subscription_type,
    SUM(renewed_count) as renewed,
    SUM(stopped_count) as stopped,
    SUM(expiring_count) as expiring,
    (SUM(renewed_count) / SUM(expiring_count)) * 100 as renewal_rate
FROM churn_daily_summary
WHERE snapshot_date BETWEEN @start_date AND @end_date
  AND subscription_type IN ('REGULAR', 'MONTHLY', 'COMPLIMENTARY')
GROUP BY subscription_type;
```

**Business Questions Answered:**
- Which subscription type has highest retention?
- Should we shift focus to higher-performing types?
- Are monthly subscriptions more stable than annual?

#### By Publication

**Goal:** Compare renewal performance across publications

**SQL:**
```sql
SELECT
    paper_code,
    SUM(renewed_count) as renewed,
    SUM(stopped_count) as stopped,
    (SUM(renewed_count) / SUM(expiring_count)) * 100 as renewal_rate
FROM churn_daily_summary
WHERE snapshot_date BETWEEN @start_date AND @end_date
  AND subscription_type = 'ALL'
GROUP BY paper_code;
```

**Business Questions Answered:**
- Which publication has strongest subscriber loyalty?
- Should we invest more in underperforming publications?
- Are geographic markets different in retention?

#### Trend Data (Time Series)

**Goal:** Plot historical renewal rates over time

**SQL:**
```sql
SELECT
    snapshot_date,
    SUM(renewed_count) as renewed,
    SUM(stopped_count) as stopped,
    (SUM(renewed_count) / SUM(expiring_count)) * 100 as renewal_rate
FROM churn_daily_summary
WHERE snapshot_date BETWEEN @start_date AND @end_date
  AND subscription_type = 'ALL'
  AND paper_code = 'TJ'  -- Optional: filter by publication
GROUP BY snapshot_date
ORDER BY snapshot_date ASC;
```

**Business Questions Answered:**
- Is our retention improving or declining?
- Are there seasonal patterns in renewals?
- Did our retention campaign have an impact?

---

## Integration with Existing Systems

### Current Integration Points

#### 1. Circulation Dashboard (`daily_snapshots`)

**Data Overlap:**
- Both track: `snapshot_date`, `paper_code`
- Both measure: Subscriber counts (active vs expiring)

**Combined Analysis Opportunity:**

```sql
-- Daily Snapshot: Active subscriber health + Renewal performance
SELECT
    ds.snapshot_date,
    ds.paper_code,
    ds.paper_name,
    ds.business_unit,

    -- Active subscriber metrics (from daily_snapshots)
    ds.total_active,
    ds.deliverable,
    ds.on_vacation,
    ds.mail_delivery,
    ds.carrier_delivery,
    ds.digital_only,

    -- Renewal performance (from churn_daily_summary)
    cds.expiring_count,
    cds.renewed_count,
    cds.stopped_count,
    cds.renewal_rate,

    -- Calculated: Net subscriber change
    (ds.total_active + cds.renewed_count - cds.stopped_count) as projected_next_week,

    -- Calculated: Churn as % of active base
    (cds.stopped_count / ds.total_active * 100) as churn_pct_of_active

FROM daily_snapshots ds
LEFT JOIN churn_daily_summary cds
    ON ds.snapshot_date = cds.snapshot_date
    AND ds.paper_code = cds.paper_code
    AND cds.subscription_type = 'ALL'
WHERE ds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
ORDER BY ds.snapshot_date DESC, ds.paper_code;
```

**Business Intelligence Value:**

1. **Growth Rate Analysis:**
   - Active subscribers + renewals - churn = net growth
   - Is growth driven by new subscribers or retention?

2. **Churn Impact on Active Base:**
   - If 100 stop and 3,000 active = 3.3% base erosion
   - High churn % of active = serious concern

3. **Deliverable vs Renewal Rate:**
   - Are high vacation rates correlated with lower renewals?
   - Do subscribers who stop delivery eventually churn?

4. **Delivery Type vs Retention:**
   - Do digital-only subscribers renew at different rates?
   - Is mail delivery more loyal than carrier delivery?

#### 2. Weekly Upload Process (`upload.html`)

**Current Workflow:**
1. Export "All Subscriber Report" from Newzware (weekly)
2. Upload CSV to dashboard (`upload.html`)
3. UPSERT into `daily_snapshots` table

**Churn Tracking Upload (Separate Process):**
1. Export "Churn Report" from Newzware (separate report)
2. Upload CSV to churn tracking (`upload_renewals.php`)
3. INSERT into `renewal_events` table
4. Auto-calculate and INSERT into `churn_daily_summary`

**Integration Opportunity:**

**Option A: Unified Upload Interface**
```
Single upload page with two sections:
- Section 1: Circulation Data (All Subscriber Report)
- Section 2: Churn Data (Renewal/Expiration Report)
```

**Option B: Automated Scheduled Import**
```
- Configure automatic SFTP fetch from Newzware
- Nightly job pulls both reports
- Validates and imports automatically
- Email alert if import fails
```

**Business Value:**
- Reduced manual work (save ~10 min/week)
- Consistency (never forget to upload churn data)
- Real-time insights (data always up to date)

### Future Integration Opportunities

#### 3. Revenue Data (Not Yet Implemented)

**Goal:** Connect churn to financial impact

**Proposed Schema:**
```sql
CREATE TABLE revenue_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY'),

    -- Revenue metrics
    subscription_revenue DECIMAL(10,2),     -- Total revenue from subscriptions
    avg_subscription_price DECIMAL(6,2),    -- Average subscription price
    new_subscriber_revenue DECIMAL(10,2),   -- Revenue from new subscribers
    renewal_revenue DECIMAL(10,2),          -- Revenue from renewals

    -- Cost metrics
    acquisition_cost DECIMAL(10,2),         -- Cost to acquire new subscribers
    retention_campaign_cost DECIMAL(10,2),  -- Cost of retention campaigns

    UNIQUE KEY (snapshot_date, paper_code, subscription_type)
);
```

**Integration Query:**
```sql
-- Financial impact of churn
SELECT
    cds.snapshot_date,
    cds.paper_code,
    cds.stopped_count,
    rd.avg_subscription_price,

    -- Revenue lost to churn
    (cds.stopped_count * rd.avg_subscription_price) as revenue_lost,

    -- Revenue retained
    (cds.renewed_count * rd.avg_subscription_price) as revenue_retained,

    -- ROI of retention campaigns
    ((cds.renewed_count * rd.avg_subscription_price) - rd.retention_campaign_cost) as retention_roi

FROM churn_daily_summary cds
JOIN revenue_data rd
    ON cds.snapshot_date = rd.snapshot_date
    AND cds.paper_code = rd.paper_code
    AND cds.subscription_type = rd.subscription_type
WHERE cds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK);
```

**Business Questions Answered:**
- How much revenue are we losing to churn each month?
- What's the ROI of our retention campaigns?
- Should we spend more on retention or acquisition?
- Which subscription type generates most revenue per renewal?

#### 4. Subscriber Demographics (Future Enhancement)

**Goal:** Identify at-risk subscriber segments

**Proposed Schema:**
```sql
CREATE TABLE subscriber_demographics (
    sub_num VARCHAR(50) PRIMARY KEY,
    paper_code VARCHAR(10),

    -- Demographics
    age_range ENUM('18-34', '35-54', '55-64', '65+'),
    income_level ENUM('Low', 'Medium', 'High'),
    education ENUM('HS', 'Some College', 'Bachelor', 'Graduate'),
    household_size INT,

    -- Behavioral data
    first_subscription_date DATE,
    total_renewals INT,
    total_stops INT,
    avg_subscription_length_days INT,

    -- Engagement metrics
    last_login_date DATE,                   -- Digital subscribers only
    avg_logins_per_week DECIMAL(4,2),       -- Digital engagement
    email_open_rate DECIMAL(5,2),           -- Email engagement

    FOREIGN KEY (sub_num, paper_code)
        REFERENCES renewal_events(sub_num, paper_code)
);
```

**Integration Query:**
```sql
-- Churn risk profiling by demographic segment
SELECT
    sd.age_range,
    sd.income_level,
    COUNT(DISTINCT re.sub_num) as subscribers,

    -- Renewal behavior
    AVG(CASE WHEN re.status = 'RENEW' THEN 1 ELSE 0 END) * 100 as renewal_rate,

    -- Engagement metrics
    AVG(sd.avg_logins_per_week) as avg_engagement,
    AVG(sd.total_renewals) as avg_renewals,

    -- Churn risk score (calculated)
    CASE
        WHEN AVG(CASE WHEN re.status = 'RENEW' THEN 1 ELSE 0 END) < 0.70
        THEN 'High Risk'
        WHEN AVG(CASE WHEN re.status = 'RENEW' THEN 1 ELSE 0 END) < 0.85
        THEN 'Medium Risk'
        ELSE 'Low Risk'
    END as risk_category

FROM subscriber_demographics sd
JOIN renewal_events re ON sd.sub_num = re.sub_num AND sd.paper_code = re.paper_code
WHERE re.event_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
GROUP BY sd.age_range, sd.income_level
ORDER BY renewal_rate ASC;
```

**Business Questions Answered:**
- Which demographic segments have highest churn risk?
- Are younger subscribers more likely to cancel?
- Does engagement correlate with renewal rates?
- Should we target retention campaigns by age/income?

#### 5. Campaign Tracking (Marketing Integration)

**Goal:** Measure effectiveness of retention campaigns

**Proposed Schema:**
```sql
CREATE TABLE retention_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(100),
    paper_code VARCHAR(10),

    -- Campaign details
    start_date DATE,
    end_date DATE,
    campaign_type ENUM('Email', 'Direct Mail', 'Phone', 'Digital Ad', 'Discount Offer'),

    -- Targeting
    target_subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY', 'ALL'),
    target_segment VARCHAR(50),             -- e.g., "High Risk", "First Renewal", etc.

    -- Metrics
    subscribers_targeted INT,
    cost DECIMAL(10,2),

    INDEX idx_dates (start_date, end_date),
    INDEX idx_paper (paper_code)
);

CREATE TABLE campaign_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    sub_num VARCHAR(50),
    paper_code VARCHAR(10),

    -- Response details
    response_date DATE,
    action_taken ENUM('Renewed', 'Stopped', 'No Action'),

    FOREIGN KEY (campaign_id) REFERENCES retention_campaigns(id),
    FOREIGN KEY (sub_num, paper_code) REFERENCES renewal_events(sub_num, paper_code)
);
```

**Integration Query:**
```sql
-- Campaign effectiveness analysis
SELECT
    rc.campaign_name,
    rc.campaign_type,
    rc.paper_code,
    rc.start_date,
    rc.end_date,

    -- Campaign metrics
    rc.subscribers_targeted,
    rc.cost,
    COUNT(cr.id) as responses,

    -- Renewal results
    SUM(CASE WHEN cr.action_taken = 'Renewed' THEN 1 ELSE 0 END) as renewals,
    SUM(CASE WHEN cr.action_taken = 'Stopped' THEN 1 ELSE 0 END) as stops,

    -- Effectiveness
    (SUM(CASE WHEN cr.action_taken = 'Renewed' THEN 1 ELSE 0 END) / rc.subscribers_targeted) * 100 as conversion_rate,
    (rc.cost / NULLIF(SUM(CASE WHEN cr.action_taken = 'Renewed' THEN 1 ELSE 0 END), 0)) as cost_per_renewal,

    -- Compare to baseline (subscribers not targeted)
    (SELECT AVG(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100
     FROM renewal_events
     WHERE paper_code = rc.paper_code
       AND event_date BETWEEN rc.start_date AND rc.end_date
       AND sub_num NOT IN (SELECT sub_num FROM campaign_responses WHERE campaign_id = rc.id)
    ) as baseline_renewal_rate,

    -- Lift from campaign
    ((SUM(CASE WHEN cr.action_taken = 'Renewed' THEN 1 ELSE 0 END) / rc.subscribers_targeted) * 100)
    -
    (SELECT AVG(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100
     FROM renewal_events
     WHERE paper_code = rc.paper_code
       AND event_date BETWEEN rc.start_date AND rc.end_date
       AND sub_num NOT IN (SELECT sub_num FROM campaign_responses WHERE campaign_id = rc.id)
    ) as campaign_lift

FROM retention_campaigns rc
LEFT JOIN campaign_responses cr ON rc.id = cr.campaign_id
GROUP BY rc.id
ORDER BY conversion_rate DESC;
```

**Business Questions Answered:**
- Which types of retention campaigns work best?
- What's the ROI of our marketing spend on retention?
- Should we invest more in email vs direct mail?
- Does targeting high-risk segments improve conversion?
- What's the incremental lift from campaigns vs baseline?

---

## Business Intelligence Opportunities

### Current Dashboard Capabilities

**1. Performance Monitoring**
- Real-time renewal rate tracking
- Heat map visualization of performance (green = good, red = bad)
- Comparison across subscription types and publications

**2. Trend Analysis**
- 4-week and 12-week historical views
- Week-over-week navigation
- Chart.js visualizations (line charts, stacked bar charts)

**3. Drill-Down Investigation**
- Context menu on any metric card
- View individual renewal/expiration events (up to 1000 records)
- Filter by status, publication, subscription type, date range

**4. Comparative Analysis**
- Side-by-side comparison of publications
- Subscription type performance comparison
- Time period comparison (4 weeks vs 12 weeks)

### Immediate Enhancement Opportunities

#### 1. Cohort Analysis

**Goal:** Track specific subscriber cohorts over time

**Use Case:**
- Subscribers who started in Q1 2025: What's their 6-month renewal rate?
- First-time renewals vs long-term subscribers: Who's more loyal?
- Subscribers acquired through specific campaigns: Do they renew?

**Implementation:**
```sql
-- Define cohorts by first subscription date
WITH subscriber_cohorts AS (
    SELECT
        sub_num,
        paper_code,
        MIN(event_date) as cohort_start_date,
        DATE_FORMAT(MIN(event_date), '%Y-%m') as cohort_month
    FROM renewal_events
    GROUP BY sub_num, paper_code
)

-- Analyze renewal behavior by cohort
SELECT
    sc.cohort_month,
    COUNT(DISTINCT re.sub_num) as cohort_size,

    -- Renewal behavior
    AVG(CASE WHEN re.status = 'RENEW' THEN 1 ELSE 0 END) * 100 as renewal_rate,

    -- Lifecycle metrics
    AVG(DATEDIFF(re.event_date, sc.cohort_start_date)) as avg_days_since_start,
    COUNT(re.id) / COUNT(DISTINCT re.sub_num) as avg_renewals_per_subscriber

FROM subscriber_cohorts sc
JOIN renewal_events re ON sc.sub_num = re.sub_num AND sc.paper_code = re.paper_code
WHERE re.event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY sc.cohort_month
ORDER BY sc.cohort_month DESC;
```

**Business Value:**
- **Acquisition Quality**: Are recent acquisitions renewing well?
- **Lifetime Value**: How long do different cohorts stay subscribed?
- **Retention Strategies**: Should we focus on new subscribers or long-term?

#### 2. Seasonal Pattern Detection

**Goal:** Identify predictable renewal patterns by season/month

**Use Case:**
- Do renewals drop in summer (vacation season)?
- Are December renewals higher (holiday gifts)?
- Should we adjust retention campaigns by season?

**Implementation:**
```sql
-- Seasonal renewal patterns
SELECT
    MONTH(event_date) as month_num,
    MONTHNAME(event_date) as month_name,
    QUARTER(event_date) as quarter,

    -- Overall metrics
    COUNT(*) as total_events,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewals,
    AVG(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100 as renewal_rate,

    -- By subscription type
    AVG(CASE WHEN subscription_type = 'REGULAR' AND status = 'RENEW' THEN 1 ELSE 0 END) * 100 as regular_renewal_rate,
    AVG(CASE WHEN subscription_type = 'MONTHLY' AND status = 'RENEW' THEN 1 ELSE 0 END) * 100 as monthly_renewal_rate

FROM renewal_events
WHERE YEAR(event_date) >= 2024
GROUP BY MONTH(event_date), QUARTER(event_date)
ORDER BY month_num;
```

**Business Value:**
- **Budget Planning**: Allocate retention budget to high-risk months
- **Campaign Timing**: Launch campaigns before predictable churn periods
- **Forecasting**: More accurate revenue projections with seasonal adjustments

#### 3. At-Risk Subscriber Identification

**Goal:** Proactively identify subscribers likely to churn

**Use Case:**
- Subscribers with upcoming expirations: Who's at risk?
- Early warning system: Flag subscribers before they expire
- Targeted retention: Focus efforts on highest-risk subscribers

**Implementation:**
```sql
-- At-risk subscriber scoring (requires subscriber_demographics table)
SELECT
    sd.sub_num,
    sd.paper_code,

    -- Risk factors
    DATEDIFF(CURDATE(), sd.last_login_date) as days_since_login,
    sd.avg_logins_per_week,
    sd.email_open_rate,
    sd.total_stops as previous_cancellations,

    -- Calculate risk score (0-100, higher = more risk)
    (
        (DATEDIFF(CURDATE(), sd.last_login_date) / 30 * 20) +  -- 20 points for inactivity
        ((1 - sd.avg_logins_per_week) * 20) +                  -- 20 points for low engagement
        ((1 - sd.email_open_rate) * 20) +                      -- 20 points for low email engagement
        (sd.total_stops * 20) +                                 -- 20 points per previous stop
        (CASE WHEN sd.age_range IN ('18-34') THEN 20 ELSE 0 END) -- 20 points for high-churn demographics
    ) as churn_risk_score,

    -- Next expiration date (would need to calculate from subscription data)
    DATE_ADD(sd.first_subscription_date, INTERVAL sd.avg_subscription_length_days DAY) as projected_expiration

FROM subscriber_demographics sd
WHERE sd.sub_num NOT IN (
    SELECT sub_num FROM renewal_events
    WHERE event_date > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
)
HAVING churn_risk_score > 50
ORDER BY churn_risk_score DESC
LIMIT 100;
```

**Business Value:**
- **Proactive Retention**: Contact at-risk subscribers before they expire
- **Resource Optimization**: Focus retention efforts on highest-value/highest-risk
- **Churn Prevention**: Reduce churn through early intervention

#### 4. Competitive Benchmarking

**Goal:** Compare performance to industry standards and competitors

**Use Case:**
- How do our renewal rates compare to industry benchmarks?
- Are we improving relative to the market?
- Which publications are above/below industry average?

**Industry Benchmarks (Newspaper Industry):**
- **Print subscriptions**: 75-85% renewal rate (industry average)
- **Digital subscriptions**: 60-70% renewal rate (industry average)
- **Monthly subscriptions**: 80-90% retention rate (auto-renewal)

**Implementation:**
```sql
-- Compare to industry benchmarks
SELECT
    paper_code,
    subscription_type,

    -- Actual performance
    AVG(renewal_rate) as our_renewal_rate,

    -- Industry benchmark
    CASE subscription_type
        WHEN 'REGULAR' THEN 80.0
        WHEN 'MONTHLY' THEN 85.0
        WHEN 'COMPLIMENTARY' THEN 75.0
    END as industry_benchmark,

    -- Performance vs benchmark
    (AVG(renewal_rate) -
        CASE subscription_type
            WHEN 'REGULAR' THEN 80.0
            WHEN 'MONTHLY' THEN 85.0
            WHEN 'COMPLIMENTARY' THEN 75.0
        END
    ) as vs_benchmark,

    -- Interpretation
    CASE
        WHEN AVG(renewal_rate) >= CASE subscription_type
            WHEN 'REGULAR' THEN 80.0
            WHEN 'MONTHLY' THEN 85.0
            WHEN 'COMPLIMENTARY' THEN 75.0
        END + 5 THEN 'Exceeds Industry'
        WHEN AVG(renewal_rate) >= CASE subscription_type
            WHEN 'REGULAR' THEN 80.0
            WHEN 'MONTHLY' THEN 85.0
            WHEN 'COMPLIMENTARY' THEN 75.0
        END - 5 THEN 'Meets Industry'
        ELSE 'Below Industry'
    END as benchmark_status

FROM churn_daily_summary
WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
  AND subscription_type != 'ALL'
GROUP BY paper_code, subscription_type
ORDER BY vs_benchmark DESC;
```

**Business Value:**
- **Performance Context**: Understand if issues are internal or industry-wide
- **Best Practices**: Learn from high-performing publications
- **Strategic Positioning**: Differentiate from competitors

### Advanced Analytics Opportunities

#### 5. Predictive Churn Modeling (Machine Learning)

**Goal:** Use ML to predict which subscribers will churn

**Data Inputs:**
- Historical renewal behavior (renewal_events)
- Subscriber demographics (age, income, location)
- Engagement metrics (logins, email opens, article reads)
- Subscription characteristics (type, length, price)
- Seasonality features (month, quarter, holiday periods)

**ML Model Options:**
1. **Logistic Regression**: Simple, interpretable, good baseline
2. **Random Forest**: Handles non-linear relationships, feature importance
3. **Gradient Boosting (XGBoost)**: High accuracy, production-ready
4. **Neural Networks**: Best for large datasets with complex patterns

**Implementation Steps:**
1. **Feature Engineering**: Create predictive variables from raw data
2. **Training Data**: Historical events with known outcomes (RENEW/EXPIRE)
3. **Model Training**: Train on 70% of data, validate on 30%
4. **Prediction**: Score all active subscribers for churn probability
5. **Action**: Export high-risk subscribers for retention campaigns

**Expected Accuracy:** 70-85% (based on industry benchmarks for churn prediction)

**Business Value:**
- **Revenue Protection**: Prevent $X thousands in lost subscription revenue
- **ROI on Retention**: Target campaigns to subscribers most likely to respond
- **Efficiency**: Automate identification of at-risk subscribers

#### 6. Customer Lifetime Value (CLV) Analysis

**Goal:** Calculate expected revenue from each subscriber over their lifetime

**Formula:**
```
CLV = (Average Subscription Price) × (Average Number of Renewals) × (Renewal Rate)
```

**Example:**
- Average subscription: $50/year
- Average renewals: 5 years
- Renewal rate: 85%
- CLV = $50 × 5 × 0.85 = $212.50

**Segmented CLV:**
```sql
-- CLV by subscription type
SELECT
    subscription_type,

    -- Average metrics
    AVG(subscription_price) as avg_price,
    AVG(renewals) as avg_renewals,
    AVG(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewal_rate,

    -- CLV calculation
    (AVG(subscription_price) * AVG(renewals) * AVG(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END)) as customer_lifetime_value

FROM (
    -- Subscriber-level aggregation
    SELECT
        re.sub_num,
        re.subscription_type,
        rd.avg_subscription_price as subscription_price,
        COUNT(*) as renewals,
        re.status
    FROM renewal_events re
    JOIN revenue_data rd ON re.paper_code = rd.paper_code AND re.subscription_type = rd.subscription_type
    GROUP BY re.sub_num, re.subscription_type, rd.avg_subscription_price, re.status
) subscriber_metrics
GROUP BY subscription_type;
```

**Business Value:**
- **Acquisition Budget**: How much can we spend to acquire a subscriber?
- **Retention ROI**: Is it cheaper to retain than acquire?
- **Segment Prioritization**: Focus on high-CLV segments

#### 7. Churn Reason Analysis (Text Analytics)

**Goal:** Understand WHY subscribers are churning

**Data Source:**
- Customer service notes (cancellation reasons)
- Exit surveys (why are you cancelling?)
- Support tickets (complaints before cancellation)

**Text Analytics Approach:**
1. **Text Classification**: Categorize reasons (price, content, delivery, technical)
2. **Sentiment Analysis**: Detect frustrated customers before churn
3. **Topic Modeling**: Discover common themes in cancellation reasons

**Example Categories:**
- **Price**: "Too expensive", "Found cheaper alternative"
- **Content**: "Not enough local news", "Prefer other sources"
- **Delivery**: "Late delivery", "Missing issues"
- **Technical**: "Website doesn't work", "Can't log in"
- **Life Changes**: "Moving", "Retired", "No longer need"

**Business Value:**
- **Root Cause Analysis**: Fix underlying issues causing churn
- **Product Improvement**: Address content/delivery complaints
- **Retention Messaging**: Tailor campaigns to address specific concerns

---

## Data Flow and Processing

### Upload and Import Process

#### Step 1: Export from Newzware

**Newzware Report:** "Renewal and Expiration Report" (or similar)

**Export Format:** CSV file

**Required Columns:**
- `SubNum`: Subscriber number
- `PaperCode`: Publication (TJ, TA, TR, LJ, WRN)
- `IssueDate`: Date of expiration/renewal event
- `Status`: RENEW or EXPIRE
- `SubscriptionType`: REGULAR, MONTHLY, or COMPLIMENTARY

**File Naming:** `RenewalReport_YYYYMMDD.csv`

#### Step 2: Upload to Dashboard

**Upload Page:** `/web/upload_renewals.php`

**Process:**
1. User selects CSV file
2. File validated (size <10MB, correct format)
3. CSV parsed and validated (required columns present)
4. Data inserted into `renewal_events` table (UPSERT by sub_num + paper_code + event_date)
5. Aggregation triggered to update `churn_daily_summary`
6. Success/error message displayed

**Error Handling:**
- Missing columns: "CSV does not contain required columns"
- Invalid data: "Invalid status value on row 42"
- Duplicate events: Skip (UNIQUE constraint)
- File too large: "File exceeds 10MB limit"

#### Step 3: Data Aggregation

**Trigger:** Automatically after upload completes

**Process:**
```sql
-- Calculate daily summary for newly imported dates
INSERT INTO churn_daily_summary (
    snapshot_date,
    paper_code,
    subscription_type,
    expiring_count,
    renewed_count,
    stopped_count,
    renewal_rate,
    churn_rate
)
SELECT
    event_date as snapshot_date,
    paper_code,
    subscription_type,

    -- Counts
    COUNT(*) as expiring_count,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewed_count,
    SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) as stopped_count,

    -- Rates
    (SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as renewal_rate,
    (SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as churn_rate

FROM renewal_events
WHERE event_date IN (SELECT DISTINCT event_date FROM renewal_events WHERE imported_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))
GROUP BY event_date, paper_code, subscription_type

ON DUPLICATE KEY UPDATE
    expiring_count = VALUES(expiring_count),
    renewed_count = VALUES(renewed_count),
    stopped_count = VALUES(stopped_count),
    renewal_rate = VALUES(renewal_rate),
    churn_rate = VALUES(churn_rate),
    calculated_at = CURRENT_TIMESTAMP;

-- Also insert 'ALL' rollup
INSERT INTO churn_daily_summary (
    snapshot_date,
    paper_code,
    subscription_type,
    expiring_count,
    renewed_count,
    stopped_count,
    renewal_rate,
    churn_rate
)
SELECT
    event_date as snapshot_date,
    paper_code,
    'ALL' as subscription_type,

    COUNT(*) as expiring_count,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewed_count,
    SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) as stopped_count,
    (SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as renewal_rate,
    (SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as churn_rate

FROM renewal_events
WHERE event_date IN (SELECT DISTINCT event_date FROM renewal_events WHERE imported_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))
GROUP BY event_date, paper_code

ON DUPLICATE KEY UPDATE
    expiring_count = VALUES(expiring_count),
    renewed_count = VALUES(renewed_count),
    stopped_count = VALUES(stopped_count),
    renewal_rate = VALUES(renewal_rate),
    churn_rate = VALUES(churn_rate),
    calculated_at = CURRENT_TIMESTAMP;
```

**Performance:**
- ~28,000 events → ~1,180 summary records
- Aggregation time: <5 seconds

#### Step 4: Dashboard Refresh

**Automatic:** Dashboard queries `churn_daily_summary` table (fast)

**No manual refresh needed:** Summary table updated by upload process

### API Data Flow

**Frontend (JavaScript) → Backend (PHP) → Database (MariaDB)**

```
User Action (Click, Navigate, Filter)
    ↓
JavaScript Event Handler (churn_dashboard.js)
    ↓
API Call (fetch to /api.php?action=get_churn_*)
    ↓
PHP Endpoint (api.php - case 'get_churn_*')
    ↓
SQL Query (PDO prepared statement)
    ↓
Database (MariaDB - churn_daily_summary table)
    ↓
Result Set (PDO fetchAll)
    ↓
JSON Response (sendResponse helper)
    ↓
JavaScript Promise Resolution (.then)
    ↓
DOM Update (Render cards, charts, tables)
    ↓
User sees updated data
```

**Caching:** Currently none (all queries <200ms, no caching needed)

**Future:** Add SimpleCache for 7-day TTL on expensive aggregations

---

## Future Enhancement Opportunities

### Short-Term (0-3 months)

#### 1. Export Functionality

**Goal:** Export churn data to Excel/CSV for offline analysis

**Implementation:**
- Add "Export to CSV" button to context menu
- Generate CSV from current view (filtered data)
- Include metadata (date range, filters applied)

**Business Value:**
- Share data with stakeholders who don't have dashboard access
- Perform custom analysis in Excel
- Archive snapshots for historical comparison

#### 2. Email Alerts

**Goal:** Proactive notifications when metrics cross thresholds

**Triggers:**
- Renewal rate drops below 80% (warning)
- Renewal rate drops below 70% (critical)
- Churn rate increases by >5% week-over-week (alert)
- New weekly data uploaded (confirmation)

**Implementation:**
- Daily cron job checks thresholds
- Email sent to configured recipients
- Include link to dashboard with filters pre-applied

**Business Value:**
- Early warning system for declining performance
- No need to manually check dashboard daily
- Faster response to emerging issues

#### 3. Comparison View

**Goal:** Side-by-side comparison of time periods

**UI:**
- Split-screen: Left = 4 weeks ago, Right = This week
- Color-coded differences (green = improved, red = declined)
- Percentage change indicators

**Business Value:**
- Easily see week-over-week/month-over-month trends
- Identify which publications improved vs declined
- Visual impact of retention campaigns

### Medium-Term (3-6 months)

#### 4. Mobile App

**Goal:** Access churn metrics on mobile devices

**Platform:** Progressive Web App (PWA) or native iOS/Android

**Key Features:**
- Dashboard overview (key metrics)
- Push notifications for alerts
- Quick filters (by publication, type)
- Offline mode (cached data)

**Business Value:**
- Check metrics on the go
- Faster decision-making
- Improved executive visibility

#### 5. Custom Reporting

**Goal:** User-defined reports for specific business questions

**Features:**
- Report builder interface (drag-and-drop)
- Save/share custom reports
- Schedule automated delivery (email PDF)
- Custom date ranges and filters

**Business Value:**
- Tailor analytics to specific roles (exec vs operations)
- Reduce ad-hoc data requests
- Self-service business intelligence

#### 6. Integration with CRM

**Goal:** Sync churn data with customer relationship management system

**Benefits:**
- Customer service reps see churn risk scores
- Trigger retention campaigns automatically
- Track customer interactions alongside renewal behavior

**Technical:**
- API integration with CRM (e.g., Salesforce, HubSpot)
- Bidirectional data sync (churn → CRM, interactions → churn)

### Long-Term (6-12 months)

#### 7. Predictive Analytics

**Goal:** Machine learning models for churn prediction

**Approach:**
- Train models on historical renewal_events
- Score active subscribers for churn probability
- Integrate predictions into dashboard

**Business Value:**
- Proactive retention (contact before churn occurs)
- Optimize retention budget (target high-risk/high-value)
- Forecasting (predict future churn rates)

#### 8. Subscriber Journey Mapping

**Goal:** Visualize subscriber lifecycle from acquisition to churn

**Visualization:**
- Sankey diagram showing subscriber flow
- Stages: Acquisition → First Renewal → Long-term → Churn/Retention
- Drop-off rates at each stage

**Business Value:**
- Identify where most churn occurs (first renewal vs long-term)
- Optimize onboarding for new subscribers
- Tailor retention strategies by lifecycle stage

#### 9. A/B Testing Framework

**Goal:** Test retention strategies scientifically

**Experiments:**
- Test A: Email campaign with 10% discount
- Test B: Email campaign with free month
- Control: No campaign

**Metrics:**
- Renewal rate lift
- Cost per retained subscriber
- Statistical significance

**Business Value:**
- Data-driven retention strategies
- Stop wasting money on ineffective campaigns
- Continuous improvement culture

---

## Glossary

**Churn:** The loss of a subscriber who does not renew their subscription.

**Churn Rate:** Percentage of expiring subscriptions that did not renew.

**Cohort:** A group of subscribers who share a common characteristic (e.g., start date, acquisition source).

**Customer Lifetime Value (CLV):** Total expected revenue from a subscriber over their entire relationship with the publication.

**Expiring Count:** Number of subscriptions coming up for renewal on a given date.

**Heat Map:** Visual color-coding of metrics (green = good, red = bad) for quick performance assessment.

**Net Change:** Difference between renewals and expirations (renewals - stopped).

**Renewal Rate:** Percentage of expiring subscriptions that chose to renew.

**Renewed Count:** Number of subscriptions that renewed on a given date.

**Stopped Count:** Number of subscriptions that expired and did not renew on a given date.

**Subscription Type:**
- **REGULAR**: Annual subscriptions with yearly billing
- **MONTHLY**: Month-to-month subscriptions with monthly billing
- **COMPLIMENTARY**: Free subscriptions (VIPs, advertisers, staff, promotional)

**Time Range:**
- **4 weeks**: 28-day rolling window for recent performance
- **12 weeks**: 84-day (3-month) rolling window for strategic analysis

---

## Document Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-12-16 | Initial comprehensive documentation of churn tracking system |

---

**End of Document**
