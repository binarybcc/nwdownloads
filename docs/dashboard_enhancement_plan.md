# Dashboard Enhancement Recommendations
## C-Suite & CSR Feature Additions

> **Goal:** Eliminate need to run separate reports by surfacing actionable intelligence in the dashboard  
> **Target Audiences:** Executives (COO, CFO, Publisher) & Customer Service Reps  
> **Current Capabilities:** Subscriber counts, delivery types, business unit breakdowns, 12-week trends

---

## Executive Summary

**Current Dashboard Shows:** What happened (subscriber counts, delivery splits)  
**Missing:** Why it's happening, what to do about it, and what comes next

**Opportunity:** Add 8 C-suite features + 6 CSR tools that answer the questions users actually ask.

---

## Part 1: C-Suite / Executive Features

### 1. 🚨 **Expiration Risk Dashboard** (Priority: CRITICAL)

**Problem Solved:**  
Executives don't know revenue at risk until it's too late.

**What to Add:**

**A. Expiration Risk Widget** (Top of dashboard)
```
┌─────────────────────────────────────┐
│ 🚨 REVENUE AT RISK                  │
├─────────────────────────────────────┤
│ Expiring Next 4 Weeks:     387 subs │
│ Est. Revenue Impact:    $5,805/month │
│                                       │
│ ⚠️  15% of base exp. in Q1 2026     │
│ [View Details →]                     │
└─────────────────────────────────────┘
```

**B. Expiration Timeline Chart**
```
Bar chart showing:
- 0-4 weeks: 387 (red)
- 5-8 weeks: 450 (orange)
- 9-12 weeks: 368 (yellow)
- 12+ weeks: 6,895 (green)
```

**Behind the Scenes:**
- Calculate from `Paid Thru` dates in CSV
- Estimate revenue: Count × Average LAST PAY amount
- Benchmark: Flag if >10% expiring in any 30-day period

**Business Value:**
> "If the COO sees 400 subs expiring next month worth $6K MRR, they can authorize a retention campaign TODAY instead of seeing -$6K on next month's P&L."

**Technical Complexity:** ⭐⭐ Medium (new SQL query + widget)

---

### 2. 💰 **Revenue Intelligence** (Priority: HIGH)

**Problem Solved:**  
Execs see subscriber counts but not revenue per subscriber or pricing trends.

**What to Add:**

**A. Revenue Metrics Cards**
```
┌──────────────────────┬──────────────────────┐
│ Monthly Revenue      │ Revenue Per Sub      │
│ $97,610              │ $12.05/month         │
│ ↓ -$230 vs last week │ ↑ +$0.15 vs last wk  │
└──────────────────────┴──────────────────────┘
```

**B. Rate Mix Breakdown**
```
Digital-Only:    890 @ $8.25/mo  = $7,343
Print+Digital: 2,360 @ $15.75/mo = $37,170
Print-Only:    4,850 @ $11.50/mo = $55,775
──────────────────────────────────────────
Total MRR: $97,610
```

**C. Legacy Pricing Alert**
```
⚠️  650 subscribers on legacy rates (<$10/mo)
💡 Opportunity: $4,200/month if migrated to current pricing
```

**Behind the Scenes:**
- Parse `LAST PAY` and `LEN` columns to calculate monthly revenue
- Group by `Zone` field for rate mix
- Compare `LAST PAY` amounts to current published rates

**Business Value:**
> "Publisher sees that print-only subscribers generate 25% less revenue than bundles. Immediate strategic decision: Push bundle pricing in next campaign."

**Technical Complexity:** ⭐⭐⭐ Medium-High (revenue calculations + comparison logic)

---

### 3. 📉 **Churn Analytics** (Priority: HIGH)

**Problem Solved:**  
Dashboard shows net change but not whether it's a growth problem or churn problem.

**What to Add:**

**A. Weekly Movement Widget**
```
┌────────────────────────────────────┐
│ THIS WEEK'S MOVEMENT               │
├────────────────────────────────────┤
│ New Starts:        +45             │
│ Stops (Churned):   -62             │
│ ────────────────────────           │
│ Net Change:        -17             │
│                                     │
│ Weekly Churn: 0.77% (🔴 High)     │
│ Annualized:   40% (Industry: 30%)  │
└────────────────────────────────────┘
```

**B. Churn Trend Chart** (12-week history)
```
Line chart:
- New starts per week (green line)
- Stops per week (red line)
- Net change (blue bars)
```

**Behind the Scenes:**
- Compare SUB NUM lists week-over-week
- Missing from current week = churned
- Present in current week only = new start

**Business Value:**
> "-17 net could be 5 new/22 stops (acquisition problem) or 100 new/117 stops (churn crisis). Completely different strategies required."

**Technical Complexity:** ⭐⭐⭐⭐ High (requires storing historical snapshots + comparison logic)

---

### 4. 📱 **Digital Engagement Dashboard** (Priority: MEDIUM)

**Problem Solved:**  
Digital strategy decisions made blindly without engagement data.

**What to Add:**

**A. Digital Health Scorecard**
```
┌────────────────────────────────────┐
│ DIGITAL ENGAGEMENT                 │
├────────────────────────────────────┤
│ Digital Accounts:  4,860 (60%)     │
│ Active (7 days):   1,240 (26%)     │
│ Dormant (90+ days): 1,820 (37%)    │
│                                     │
│ 📊 Trend: ↑ +2% activation vs. Q3  │
└────────────────────────────────────┘
```

**B. Digital Funnel Visualization**
```
Total Subscribers: 8,100
  ├─ Has Login ID: 4,860 (60%)
  │   ├─ Logged in last 7 days: 1,240 (26%)
  │   ├─ Logged in 8-30 days: 1,450 (30%)
  │   └─ Dormant (90+ days): 1,820 (37%)
  └─ No digital access: 3,240 (40%) 💡
```

**Behind the Scenes:**
- Count subscribers with `Login ID` populated
- Parse `Last Login` dates for recency buckets
- Flag subscribers with print delivery but no digital account

**Business Value:**
> "VP Product sees 1,820 dormant accounts paying for digital they don't use. Re-engagement email campaign recovers 20% = $4,368/month MRR saved."

**Technical Complexity:** ⭐⭐ Medium (date parsing + bucket logic)

---

### 5. 🗺️ **Geographic Performance Map** (Priority: MEDIUM)

**Problem Solved:**  
No visibility into which markets are growing vs declining.

**What to Add:**

**A. Top Markets Table**
```
┌──────────────┬───────┬────────┬─────────┐
│ ZIP / County │ Subs  │ Change │ Trend   │
├──────────────┼───────┼────────┼─────────┤
│ 29630        │  450  │  -15   │ 📉 -3.3%│
│ 29631        │  380  │   +8   │ 📈 +2.1%│
│ 29625        │  290  │   -5   │ 📉 -1.7%│
│ Out-of-state │  145  │  +12   │ 📈 +9.0%│
└──────────────┴───────┴────────┴─────────┘
```

**B. Market Alert**
```
⚠️ Central, SC (29630): -15 subs this month
   Investigate: Competition? Delivery issues?
```

**Behind the Scenes:**
- Parse `CITY STATE POSTAL` for ZIP codes
- Track ZIP count week-over-week
- Flag ZIPs with >5% decline

**Business Value:**
> "Publisher sees one ZIP losing subs while adjacent ZIP gains. Data points to competitor launch or carrier service issue."

**Technical Complexity:** ⭐⭐⭐ Medium-High (ZIP parsing + historical tracking)

---

### 6. 💳 **Payment Health Monitor** (Priority: MEDIUM)

**Problem Solved:**  
No early warning for cash flow issues or payment problems.

**What to Add:**

**A. Payment Health Widget**
```
┌────────────────────────────────────┐
│ PAYMENT HEALTH                     │
├────────────────────────────────────┤
│ Paid in Advance: 7,200 (89%) ✅    │
│ Past Due:          145 (1.8%) ⚠️   │
│ Complimentary:     755 (9.3%)      │
│                                     │
│ Avg Payment Size: $89.50           │
│ (↓ -$12 vs last quarter)           │
└────────────────────────────────────┘
```

**B. Auto-Renew Opportunity**
```
💡 3,100 subscribers on manual pay
   If converted to auto-renew: +30% retention
   = 930 fewer cancellations/year
```

**Behind the Scenes:**
- Parse `LAST PAY` column (negative = paid, positive/zero = due)
- Count `PAY = COMP` for complimentary subs
- Flag declining average payment (subscribers choosing shorter terms)

**Business Value:**
> "CFO sees average payment dropping from $101 to $89. Signal: Price sensitivity increasing or customers choosing shorter terms."

**Technical Complexity:** ⭐⭐ Low-Medium (simple parsing + averages)

---

### 7. 🚚 **Delivery Economics Dashboard** (Priority: LOW)

**Problem Solved:**  
Operations doesn't know which routes are profitable.

**What to Add:**

```
┌────────────────────────────────────────────┐
│ DELIVERY ROUTE EFFICIENCY                  │
├────────────────────────────────────────────┤
│ Total Carrier Routes:   28                 │
│ Avg Papers/Route:       58 ✅              │
│                                             │
│ Routes Below 40 (Unprofitable):            │
│  • Route B099: 12 papers ($15.60 loss/wk)  │
│  • Route R028: 28 papers ($5.20 loss/wk)   │
│  • Route C024: 35 papers ($0.80 loss/wk)   │
│                                             │
│ 💡 Recommend: Convert 3 routes to mail     │
│    Savings: ~$1,100/month                  │
└────────────────────────────────────────────┘
```

**Behind the Scenes:**
- Count subscribers per `Route` value (where DEL = CARR)
- Calculate cost: <40 papers = unprofitable
- Estimate savings: Carrier ($0.75/paper) vs Mail ($0.60/paper)

**Business Value:**
> "Operations Manager sees 3 routes losing money. Converts to mail = $13K/year savings without losing subscribers."

**Technical Complexity:** ⭐⭐ Medium (grouping + cost calculations)

---

### 8. 📊 **Executive KPI Snapshot** (Priority: HIGH)

**Problem Solved:**  
CEO/COO needs "at-a-glance" health on mobile during travel.

**What to Add:**

**Mobile-Optimized Executive View** (toggle at top)
```
┌──────────────────────────────────────┐
│ CIRCULATION HEALTH SCORE: 76/100 🟡  │
├──────────────────────────────────────┤
│ Total Subscribers:  8,100 📊         │
│   Week Change:      -17 (🔴 -0.2%)  │
│   Month Change:     -45 (🔴 -0.6%)  │
│                                       │
│ Revenue (MRR):      $97,610 💰       │
│   Week Change:      -$230 (🔴)       │
│                                       │
│ Churn Rate:         40% annual 🔴    │
│   (Industry avg: 30%)                │
│                                       │
│ Expiration Risk:    387 (4.8%) ⚠️    │
│   Action Needed: Renewal campaign    │
│                                       │
│ Digital Engagement: 26% active ✅    │
│   Trend: Improving                   │
└──────────────────────────────────────┘
```

**Behind the Scenes:**
- Calculate health score: Weighted average of key metrics
- Green (80-100), Yellow (60-79), Red (<60)
- Mobile-first design, minimal scrolling

**Business Value:**
> "Publisher checks phone during board meeting. Sees 'Health Score: 62/100 🟡' with top 3 issues listed. Can discuss strategy in real-time."

**Technical Complexity:** ⭐⭐⭐ Medium (scoring algorithm + mobile UX)

---

## Part 2: CSR / Customer Service Features

### 9. 🔍 **Subscriber Quick Lookup** (Priority: CRITICAL)

**Problem Solved:**  
CSR has to open Newzware to answer basic customer questions.

**What to Add:**

**A. Search Bar** (prominent in header)
```
┌────────────────────────────────────────────┐
│ 🔍 Find Subscriber: [Name, Phone, Email]  │
└────────────────────────────────────────────┘
```

**B. Instant Results Panel**
```
┌────────────────────────────────────────────┐
│ SUBSCRIBER: John Smith (#90166)            │
├────────────────────────────────────────────┤
│ 📧 webservices@upstatetoday.com            │
│ 📞 864-882-2375                            │
│                                             │
│ Subscription:                              │
│  • Publication: The Journal (TJ)           │
│  • Type: Digital-Only (COMP)               │
│  • Expires: 2026-11-12                     │
│  • Days Remaining: 340 days ✅             │
│                                             │
│ Payment:                                    │
│  • Status: Complimentary                   │
│  • Last Payment: $0.00                     │
│                                             │
│ Account:                                    │
│  • Login ID: webservices@upstatetoday.com  │
│  • Last Login: 12/04/2025 ✅               │
│  • Account Since: 11/15/2018 (7 years)     │
│                                             │
│ [📝 Add Note] [📧 Send Email] [🔄 Renew]  │
└────────────────────────────────────────────┘
```

**Behind the Scenes:**
- Search CSV data by Name, Phone, Email, SUB NUM
- Display all relevant fields in clean card
- Quick actions launch pre-filled emails or renewal flows

**Business Value:**
> "Customer calls: 'When do I expire?' CSR types name, sees answer in 2 seconds vs. 45 seconds logging into Newzware."

**Technical Complexity:** ⭐⭐⭐ Medium-High (search index + UI)

---

### 10. ⚠️ **At-Risk Subscriber List** (Priority: HIGH)

**Problem Solved:**  
CSR doesn't know who to proactively contact before they cancel.

**What to Add:**

**A. At-Risk Dashboard Tab**
```
┌──────────────────────────────────────────────┐
│ AT-RISK SUBSCRIBERS (387)                    │
├──────────────────────────────────────────────┤
│ Filters: [Expiring <30 days] [Dormant] [...]│
└──────────────────────────────────────────────┘

┌─────────────┬────────────┬─────────┬─────────┐
│ Name        │ Expires    │ Risk    │ Action  │
├─────────────┼────────────┼─────────┼─────────┤
│ Smith, J.   │ 12/18/2025 │ 🔴 High │ [Call]  │
│ Doe, M.     │ 12/22/2025 │ 🟡 Med  │ [Email] │
│ Johnson, B. │ 01/05/2026 │ 🟠 Med  │ [Email] │
└─────────────┴────────────┴─────────┴─────────┘
```

**B. Risk Scoring**
```
🔴 High Risk:
  - Expires in <14 days
  - No digital login in 90+ days
  - Switched to shorter subscription length

🟡 Medium Risk:
  - Expires in 15-30 days
  - Payment declined recently

🟢 Low Risk:
  - Expires in 31-60 days
  - Active digital user
```

**Behind the Scenes:**
- Calculate days until `Paid Thru`
- Check `Last Login` recency
- Compare current `LEN` to previous renewal

**Business Value:**
> "CSR proactively calls 20 high-risk subscribers/day. Saves 30% = 6 subscribers × $15/mo × 12 = $1,080/year revenue PER CSR."

**Technical Complexity:** ⭐⭐⭐⭐ High (risk scoring + list management)

---

### 11. 📧 **Bulk Email Templates** (Priority: MEDIUM)

**Problem Solved:**  
CSR can't easily send renewal reminders to filtered lists.

**What to Add:**

**A. Email Builder**
```
┌────────────────────────────────────────────┐
│ SEND EMAIL CAMPAIGN                        │
├────────────────────────────────────────────┤
│ Recipients: Expiring in 7-14 days (45)     │
│ Template: [Renewal Reminder]               │
│                                             │
│ Subject: Your subscription expires soon    │
│ ───────────────────────────────────────    │
│ Hi {NAME},                                 │
│                                             │
│ We noticed your subscription to {PAPER}    │
│ expires on {EXPIRE_DATE}.                  │
│                                             │
│ Renew now: {RENEWAL_LINK}                  │
│ ───────────────────────────────────────    │
│                                             │
│ [Preview] [Send Now] [Schedule]            │
└────────────────────────────────────────────┘
```

**B. Pre-built Templates**
- Renewal reminder (7 days out)
- Final notice (3 days out)
- Digital reactivation (dormant users)
- Thank you for renewal
- Upgrade offer (print-only → bundle)

**Behind the Scenes:**
- Filter subscribers by criteria
- Merge tags from CSV data
- Log sent emails for tracking

**Business Value:**
> "CSR sends personalized renewal emails to 45 people in 30 seconds vs. 2 hours of manual work."

**Technical Complexity:** ⭐⭐⭐ Medium (email integration + templates)

---

### 12. 📞 **Call List Generator** (Priority: MEDIUM)

**Problem Solved:**  
CSR doesn't have prioritized contact list for outreach.

**What to Add:**

**A. Daily Call List**
```
┌────────────────────────────────────────────┐
│ TODAY'S CALL LIST (15 contacts)            │
├────────────────────────────────────────────┤
│ 1. ☐ SMITH, JOHN - 864-882-2375           │
│      Expires: 12/15/2025 (8 days)          │
│      Profile: 7-year subscriber, $170/year │
│      Talking points: Thank + early renew   │
│      [📞 Call] [✓ Reached] [✗ No Answer]   │
│                                             │
│ 2. ☐ DOE, MARY - 864-555-1234             │
│      Expires: 12/18/2025 (11 days)         │
│      Profile: Digital-only, dormant        │
│      Talking points: Re-engage digital     │
│      [📞 Call] [✓ Reached] [✗ No Answer]   │
└────────────────────────────────────────────┘
```

**B. Auto-Prioritization**
```
Priority Rules:
1. High-value subscribers ($150+ annual)
2. Long-term customers (3+ years)
3. Expiring soonest
4. Has phone number on file
```

**Behind the Scenes:**
- Score subscribers by value + tenure + urgency
- Filter for phone numbers only
- Track call outcomes

**Business Value:**
> "CSR makes 15 targeted calls/day instead of random outreach. Conversion rate: 40% vs. 15% for generic calls."

**Technical Complexity:** ⭐⭐ Medium (scoring + UI)

---

### 13. 📊 **CSR Performance Dashboard** (Priority: LOW)

**Problem Solved:**  
CSRs don't know their retention performance.

**What to Add:**

```
┌────────────────────────────────────────────┐
│ MY RETENTION PERFORMANCE (This Month)      │
├────────────────────────────────────────────┤
│ At-Risk Contacted:    127                  │
│ Renewals Secured:      48 (38%)            │
│ Revenue Saved:         $7,200              │
│                                             │
│ Leaderboard:                               │
│  1. Sarah J. - 52 renewals (41%)           │
│  2. You - 48 renewals (38%) 🎯             │
│  3. Mike L. - 44 renewals (35%)            │
│                                             │
│ This Week's Goal: 12 renewals              │
│ Progress: 8/12 ████████░░░░ 67%            │
└────────────────────────────────────────────┘
```

**Behind the Scenes:**
- Track CSR actions (calls, emails)
- Match to actual renewals
- Gamify with leaderboards

**Business Value:**
> "CSRs compete to save subscribers. Average retention improves 15% = $145K annual revenue saved."

**Technical Complexity:** ⭐⭐⭐⭐ High (action tracking + attribution)

---

### 14. 🎯 **Quick Actions Menu** (Priority: MEDIUM)

**Problem Solved:**  
Common tasks require multiple clicks in Newzware.

**What to Add:**

**Global Quick Actions** (accessible from anywhere)
```
[⚡ Quick Actions ▼]
  ├─ 🔍 Find Subscriber
  ├─ 📧 Send Renewal Email
  ├─ 📞 Today's Call List
  ├─ ⚠️ View At-Risk (387)
  ├─ 💳 Process Payment
  └─ 📝 Add Note
```

**Behind the Scenes:**
- Modal overlays for quick tasks
- Pre-fill with current context
- Keyboard shortcuts (Alt+F = Find, Alt+E = Email, etc.)

**Business Value:**
> "CSR completes task in 3 clicks vs. 12. Handles 30% more calls/day."

**Technical Complexity:** ⭐⭐ Low-Medium (UI patterns)

---

## Implementation Priority Matrix

### Phase 1: Must-Have (Next 2-4 Weeks)
1. ✅ **Expiration Risk Dashboard** - Prevents revenue loss
2. ✅ **Subscriber Quick Lookup** - CSR time savings
3. ✅ **Revenue Intelligence** - Executive visibility
4. ✅ **At-Risk Subscriber List** - Proactive retention

### Phase 2: High-Value (Next 1-2 Months)
5. **Churn Analytics** - Strategic insights
6. **Executive KPI Snapshot** - Mobile access
7. **Bulk Email Templates** - CSR efficiency
8. **Call List Generator** - Focused outreach

### Phase 3: Nice-to-Have (Ongoing)
9. **Digital Engagement Dashboard** - Product strategy
10. **Geographic Performance** - Market intelligence
11. **Payment Health Monitor** - Financial early warning
12. **Delivery Economics** - Operations optimization
13. **CSR Performance Dashboard** - Retention gamification
14. **Quick Actions Menu** - UI polish

---

## Technical Implementation Notes

### Data Source Strategy
- **Current:** Daily snapshots in `daily_snapshots` table
- **New Needed:**
  - Weekly CSV archive (for churn tracking)
  - Subscriber detail table (for CSR lookup)
  - Action tracking table (for CSR performance)

### API Endpoints to Add
```php
// New endpoints needed:
/api.php?action=expiration_risk
/api.php?action=revenue_metrics
/api.php?action=subscriber_search&q={query}
/api.php?action=at_risk_list&filter={criteria}
/api.php?action=churn_analysis&weeks={n}
```

### UI Components
- New dashboard widgets (collapsible sections)
- Search/lookup modal
- Data table with filters
- Email template builder
- Mobile-responsive executive view

### Performance Considerations
- Cache expensive calculations (churn, revenue)
- Index subscriber search fields
- Lazy-load CSR features
- Paginate large lists

---

## ROI Estimates

**Executive Features:**
- Expiration alerts prevent 10% revenue loss = **$120K/year**
- Better pricing strategy (rate migration) = **$50K/year**
- Reduced churn (5% improvement) = **$58K/year**

**CSR Features:**
- Time savings (30 min/day × 3 CSRs × $20/hr) = **$23K/year**
- Improved retention (15% better save rate) = **$145K/year**
- Reduced Newzware lookups = Less training, faster onboarding

**Total Estimated Value:** $396K/year  
**Development Cost:** ~$30K (based on previous cost analysis)  
**ROI:** 13x first year

---

## Recommendations Summary

**Start With:** Expiration Risk + Subscriber Lookup (biggest bang for buck)  
**Measure:** Track CSR time savings and renewal conversion rates  
**Iterate:** Add features based on actual usage data

**Bottom Line:** You have the data. Surface it where people actually look.
