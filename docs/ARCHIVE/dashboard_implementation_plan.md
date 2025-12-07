# Circulation Dashboard - Implementation Plan

## Overview
Multi-layer, interactive dashboard with modern design, charts, trends, and historical data storage.

---

## Architecture Options

### Option A: Nexcess Hosted (Recommended for External Access)

**Stack:**
- **Frontend:** React + Tailwind CSS + Recharts
- **Backend:** PHP (Nexcess standard) or Node.js
- **Database:** MariaDB (already on Nexcess)
- **Auth:** Simple password or WordPress integration

**Pros:**
- ✅ Professional domain (yourpaper.com/dashboard)
- ✅ SSL certificate included
- ✅ Fast external access
- ✅ MariaDB already available
- ✅ PHP expertise common

**Cons:**
- Monthly hosting cost (already paying)
- Depends on Nexcess uptime

**Best For:** Management, remote staff, multi-location teams

---

### Option B: Synology NAS Hosted (Recommended for Internal Access)

**Stack:**
- **Frontend:** React + Tailwind CSS + Recharts
- **Backend:** Node.js or Python (via Docker)
- **Database:** MariaDB on Synology
- **Auth:** Synology SSO or custom

**Pros:**
- ✅ Free hosting (NAS already owned)
- ✅ Full control over data
- ✅ Can run 24/7
- ✅ Docker support for easy deployment
- ✅ Built-in backup system

**Cons:**
- Requires port forwarding for remote access
- Synology must stay powered on
- Slower if accessing externally

**Best For:** Office-only access, data security, cost savings

---

### Option C: Hybrid Approach (Best of Both Worlds)

**Setup:**
- **Data Processing:** Synology NAS (runs daily Python script)
- **Database:** MariaDB on Synology
- **Dashboard:** Static React app on Nexcess
- **API:** Lightweight Node.js on Nexcess reading from Synology DB

**Pros:**
- ✅ Data stored securely on-premise
- ✅ Fast dashboard access via Nexcess
- ✅ Leverage both infrastructures
- ✅ Easy to maintain

**Cons:**
- More complex setup
- Synology must expose MariaDB port

---

## Recommended Solution: Option C (Hybrid)

### Why This Works Best:

1. **Synology NAS = Data Hub**
   - Runs daily Python script (6 AM)
   - Stores historical data in MariaDB
   - NW exports saved to Synology shared folder
   - Backup and redundancy built-in

2. **Nexcess = Dashboard Frontend**
   - Fast, professional access
   - Simple authentication
   - Charts and visualizations
   - Reads data from Synology via API

3. **Separation of Concerns**
   - Data processing happens on NAS
   - Dashboard is lightweight and fast
   - Easy to update either independently

---

## Modern Dashboard Design

### Design Principles:
- **Clean, minimal interface** - Focus on data, not decoration
- **Mobile-responsive** - Works on phones, tablets, desktops
- **Dark mode option** - Easier on eyes for daily use
- **Fast load times** - <2 seconds initial load
- **Intuitive navigation** - Drill-down in 1-2 clicks

### Color Palette:
```
Primary: #2563eb (Blue - professional, trustworthy)
Secondary: #10b981 (Green - growth, positive)
Accent: #f59e0b (Amber - alerts, highlights)
Neutral: #6b7280 (Gray - text, borders)
Background: #f9fafb (Light) / #1f2937 (Dark mode)
```

### Typography:
- **Headings:** Inter or Poppins (modern, clean)
- **Body:** System fonts (fast, native)
- **Numbers:** Tabular numerals (aligned)

---

## Dashboard Structure

### Level 1: Overview Dashboard

```
┌────────────────────────────────────────────────────────────────────┐
│ 📊 CIRCULATION DASHBOARD              [Date Selector] [⚙️ Settings]│
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📈 KEY METRICS                                   vs Yesterday     │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐   │
│  │ Total Active │ On Vacation  │ Deliverable  │ 30-Day Chg   │   │
│  │   8,151      │      18      │    8,133     │    +24       │   │
│  │              │   (0.22%)    │              │   (+0.3%)    │   │
│  └──────────────┴──────────────┴──────────────┴──────────────┘   │
│                                                                     │
│  📍 BY BUSINESS UNIT                              [View Details ›] │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ South Carolina (TJ)                                     3,111 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 38.2%                   ││
│  │ Deliverable: 3,105 | On Vacation: 6                           ││
│  │ Mail: 78% • Carrier: 12% • Digital: 10%                       ││
│  └────────────────────────────────────────────────────────────────┘│
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ Michigan (TA)                                           2,909 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 35.7%                     ││
│  │ Deliverable: 2,901 | On Vacation: 8                           ││
│  │ Mail: 98% • Carrier: 0% • Digital: 2%                         ││
│  └────────────────────────────────────────────────────────────────┘│
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ Wyoming (TR + LJ + WRN)                                 2,131 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 26.1%                              ││
│  │ Deliverable: 2,127 | On Vacation: 4                           ││
│  │ Mail: 98% • Carrier: 1% • Digital: 1%                         ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  📊 90-DAY TREND                                                   │
│  [Interactive line chart showing Active vs Deliverable over time]  │
│                                                                     │
│  🔄 RECENT CHANGES                                                 │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ • +12 new subscriptions (last 7 days)                         ││
│  │ • -8 cancellations (last 7 days)                              ││
│  │ • 24 vacation holds scheduled (next 30 days)                  ││
│  │ • Net growth: +4 (+0.05%)                                     ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

### Level 2: Business Unit Detail (Click on SC, MI, or WY)

```
┌────────────────────────────────────────────────────────────────────┐
│ ← Back to Overview       WYOMING PUBLICATIONS           Nov 25     │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📊 WYOMING TOTALS                                                 │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐   │
│  │ Total Active │ On Vacation  │ Deliverable  │ Growth Rate  │   │
│  │   2,131      │       4      │    2,127     │   +1.2%      │   │
│  └──────────────┴──────────────┴──────────────┴──────────────┘   │
│                                                                     │
│  📰 BY PUBLICATION                                [Compare All ›]  │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ The Ranger (TR)                                         1,265 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 59.4%              ││
│  │ On Vacation: 2 | Deliverable: 1,263                           ││
│  │ [Mini trend sparkline]                                         ││
│  │ [View Details ›]                                               ││
│  └────────────────────────────────────────────────────────────────┘│
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ Lander Journal (LJ)                                       748 ││
│  │ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 35.1%                                  ││
│  │ On Vacation: 1 | Deliverable: 747                             ││
│  │ [Mini trend sparkline]                                         ││
│  │ [View Details ›]                                               ││
│  └────────────────────────────────────────────────────────────────┘│
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ Wind River News (WRN)                                     118 ││
│  │ ▓▓▓▓▓ 5.5%                                                    ││
│  │ On Vacation: 1 | Deliverable: 117                             ││
│  │ [Mini trend sparkline]                                         ││
│  │ [View Details ›]                                               ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  📈 WYOMING COMBINED TREND (90 Days)                               │
│  [Stacked area chart showing all 3 papers over time]              │
│                                                                     │
│  📦 DELIVERY TYPE BREAKDOWN                                        │
│  [Pie chart: Mail 98%, Carrier 1%, Digital 1%]                    │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

### Level 3: Individual Paper Detail (Click on TR, LJ, or WRN)

```
┌────────────────────────────────────────────────────────────────────┐
│ ← Back to Wyoming       THE RANGER (TR)                  Nov 25   │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📊 CURRENT STATUS                                                 │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐   │
│  │ Total Active │ On Vacation  │ Deliverable  │ 30-Day Δ     │   │
│  │   1,265      │       2      │    1,263     │    +5        │   │
│  └──────────────┴──────────────┴──────────────┴──────────────┘   │
│                                                                     │
│  📈 6-MONTH TREND                                                  │
│  [Detailed line chart with annotations for major events]           │
│  - Showing: Total, Deliverable, On Vacation                       │
│  - Hover for daily details                                        │
│  - Click to zoom date range                                       │
│                                                                     │
│  🏖️ VACATION ANALYSIS                                              │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ Currently On Hold:        2                                    ││
│  │ Returning This Week:      5                                    ││
│  │ Starting This Week:       3                                    ││
│  │ Scheduled (Next 30d):    18                                    ││
│  │                                                                ││
│  │ [Calendar heatmap showing vacation density]                   ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  📦 DELIVERY BREAKDOWN                                             │
│  ┌─────────────────────┬─────────────────────┬──────────────────┐ │
│  │ Mail (USPS)         │ Digital Only        │ Other            │ │
│  │ 1,250 (98.8%)       │ 15 (1.2%)           │ 0 (0%)           │ │
│  │ [7-day trend]       │ [7-day trend]       │                  │ │
│  └─────────────────────┴─────────────────────┴──────────────────┘ │
│                                                                     │
│  🎯 TOP SUBSCRIPTION PACKAGES                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │ 1. Rate 313 - 1 Year Mail                          1,055 (83%)││
│  │ 2. Rate 312 - 6 Month Mail                           201 (16%)││
│  │ 3. Rate 355 - Complimentary 1 Year                    27 (2%) ││
│  │ 4. Rate 915 - 25% OFF Promo                           21 (2%) ││
│  │ 5. Rate 434 - Employee Comp Digital                   12 (1%) ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  📊 COMPARATIVE METRICS                                            │
│  [Bar chart comparing TR to LJ and WRN across key metrics]        │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

---

## Chart Types & Visualizations

### 1. **Line Charts** (Trends over time)
- Total active subscribers
- Deliverable count
- On vacation count
- Growth rate

**Library:** Recharts (React) - responsive, interactive

### 2. **Bar Charts** (Comparisons)
- Paper-to-paper comparison
- Business unit comparison
- Delivery type comparison

### 3. **Stacked Area Charts** (Multiple series)
- Combined business unit trends
- Delivery type breakdown over time

### 4. **Pie/Donut Charts** (Proportions)
- Delivery type distribution
- Paper distribution by business unit

### 5. **Sparklines** (Mini trends)
- Quick visual indicators in lists
- 7-day trends for each paper

### 6. **Heat Maps** (Calendar view)
- Vacation density over time
- Busiest days for starts/stops

### 7. **KPI Cards** (Big numbers)
- Total active
- Deliverable today
- On vacation
- Growth metrics

---

## Database Schema (MariaDB)

### Table: `daily_snapshots`
```sql
CREATE TABLE daily_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    business_unit VARCHAR(50),
    total_active INT NOT NULL,
    on_vacation INT NOT NULL,
    deliverable INT NOT NULL,
    mail_delivery INT NOT NULL,
    carrier_delivery INT NOT NULL,
    digital_only INT NOT NULL,
    new_starts INT DEFAULT 0,
    cancellations INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `vacation_snapshots`
```sql
CREATE TABLE vacation_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    active_vacations INT NOT NULL,
    scheduled_next_7days INT NOT NULL,
    scheduled_next_30days INT NOT NULL,
    returning_next_7days INT NOT NULL,
    average_vacation_days DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_vac (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `rate_distribution`
```sql
CREATE TABLE rate_distribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    rate_id INT NOT NULL,
    rate_description TEXT,
    subscriber_count INT NOT NULL,
    percentage DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_paper (snapshot_date, paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Indexes for Performance:
- Date-based queries (trends)
- Paper-specific queries (drill-downs)
- Business unit rollups

---

## Technology Stack Recommendation

### Frontend:
```
React 18 (UI framework)
├── Vite (build tool - super fast)
├── Tailwind CSS (styling)
├── Recharts (charts)
├── React Router (navigation)
├── Axios (API calls)
└── date-fns (date formatting)
```

### Backend API (Nexcess):
```
Node.js + Express
├── mysql2 (MariaDB connector)
├── bcrypt (password hashing)
├── jsonwebtoken (auth tokens)
└── cors (cross-origin requests)
```

**Alternative:** PHP + Laravel (if more familiar)

### Data Processing (Synology):
```
Python 3.11+
├── pandas (data analysis)
├── mysql-connector-python (database)
├── schedule (job scheduling)
└── python-dotenv (config)
```

### Deployment:
```
Synology NAS:
├── Docker container (Python + MariaDB)
├── Scheduled task (6 AM daily)
└── Shared folder (NW exports)

Nexcess:
├── Node.js app (API)
├── Static React build (dashboard)
└── SSL certificate
```

---

## Authentication Options

### Option 1: Simple Password (Easiest)
```javascript
// Single shared password
// Best for: Small team, internal use
Username: circulation
Password: [your-secure-password]
```

### Option 2: Multi-User Auth
```javascript
// Different passwords per user
// Best for: Multiple departments, audit trail
Users:
  - admin (full access)
  - manager (view + export)
  - staff (view only)
```

### Option 3: WordPress Integration
```javascript
// Use existing WP user accounts
// Best for: Already using WordPress
// Single sign-on experience
```

### Option 4: Synology SSO
```javascript
// Use Synology user accounts
// Best for: NAS-hosted version
// No additional auth system needed
```

**Recommendation:** Start with Option 1, upgrade to Option 2 later if needed

---

## Implementation Timeline

### Week 1: Setup & Infrastructure
- [ ] Set up MariaDB on Synology
- [ ] Create database tables
- [ ] Configure Docker container
- [ ] Test Python script → MariaDB pipeline

### Week 2: Backend Development
- [ ] Build Node.js API
- [ ] Create API endpoints (read metrics, get trends)
- [ ] Implement authentication
- [ ] Test API with Postman

### Week 3-4: Frontend Development
- [ ] Set up React project with Vite
- [ ] Build Level 1 (Overview) dashboard
- [ ] Build Level 2 (Business Unit) views
- [ ] Build Level 3 (Paper Detail) views
- [ ] Add charts and visualizations

### Week 5: Integration & Polish
- [ ] Connect frontend to API
- [ ] Add loading states, error handling
- [ ] Mobile responsive design
- [ ] Dark mode implementation
- [ ] Performance optimization

### Week 6: Testing & Deployment
- [ ] User acceptance testing
- [ ] Fix bugs and issues
- [ ] Deploy to Nexcess
- [ ] Train staff on usage
- [ ] Document maintenance procedures

**Total: 6 weeks part-time or 3 weeks full-time**

---

## Estimated Costs

### Development (One-Time):
- **Your time (DIY):** $0 (40-60 hours)
- **Freelancer:** $3,000-5,000
- **Agency:** $15,000-25,000

### Hosting (Monthly):
- **Nexcess:** $0 (already have)
- **Synology:** $0 (already own)
- **Total additional cost:** $0

### Maintenance (Monthly):
- **Your time:** 1-2 hours
- **Freelancer retainer:** $100-200

**Total Project Cost (DIY):** $0 + your time

---

## Feature Roadmap

### Phase 1: Core Dashboard (Weeks 1-6)
- Multi-level navigation
- Key metrics
- Basic charts
- Historical trends
- Simple auth

### Phase 2: Enhanced Analytics (Months 2-3)
- Vacation calendar view
- Rate package analysis
- Growth projections
- Export to Excel/PDF
- Email alerts

### Phase 3: Advanced Features (Months 4-6)
- Predictive analytics (churn risk)
- Seasonal pattern detection
- Comparison tools
- Custom date ranges
- Mobile app

### Phase 4: Integration (Months 7-12)
- Sage 100 integration
- Automated reporting
- API for other systems
- Real-time updates

---

## Quick Start: MVP in 1 Week

If you need something faster, we can build a minimal version:

**Week 1 MVP Features:**
- Single page dashboard
- Key metrics cards
- One trend chart (90-day active subs)
- Basic auth (single password)
- Manual data upload (no automation)

**What you sacrifice:**
- Multi-level drill-downs
- Multiple chart types
- Automated daily updates
- Historical database

**Good for:** Proving concept, getting stakeholder buy-in

---

## Next Steps

### Decision Points:

1. **Hosting choice:**
   - [ ] Nexcess only
   - [ ] Synology only
   - [✓] Hybrid (recommended)

2. **Timeline:**
   - [ ] 1 week MVP
   - [ ] 6 weeks full build
   - [ ] 12 weeks with advanced features

3. **Who builds it:**
   - [ ] You (with my guidance)
   - [ ] Freelancer
   - [ ] Agency

4. **Budget:**
   - [ ] $0 (DIY)
   - [ ] $3K-5K (freelancer)
   - [ ] $15K+ (agency)

### Immediate Actions:

1. **Check infrastructure:**
   - Can Synology run Docker? (likely yes)
   - Does Nexcess allow Node.js? (check plan)
   - MariaDB access credentials?

2. **Get credentials:**
   - Synology admin access
   - Nexcess cPanel/SSH access
   - MariaDB root password

3. **Approve design:**
   - Review dashboard mockups above
   - Suggest any changes
   - Confirm color scheme

**Once you confirm the approach, I can start building immediately!**

---

**Questions?**
- Which hosting option appeals most?
- Timeline preference?
- Any must-have features I missed?
- Ready to start building?
