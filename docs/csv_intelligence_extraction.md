# All Subscriber Report CSV - Intelligence Extraction Guide

> **Data Source:** Newzware AllSubscriberReport CSV export  
> **Update Frequency:** On-demand (recommended: weekly Saturday snapshots)  
> **Current Use:** Source data for circulation dashboard

---

## 📊 Available Data Columns

```
SUB NUM      - Subscriber account number (unique ID)
Route        - Delivery route assignment
Name         - Subscriber name
Address      - Street address
CITY STATE POSTAL - Location
ABC          - ABC audit code
BEGIN        - Subscription start date
Paid Thru    - Expiration date
Ed           - Publication code (TJ, TA, TR, LJ, WRN)
ISS          - Issue frequency code
Zone         - Rate zone (DIGONLY, MAILDG, PRNTONLY, COMP, etc.)
LEN          - Subscription length (52 W, 12 M, 1 Y, etc.)
DEL          - Delivery type (INTE=Digital, MAIL, CARR=Carrier)
PAY          - Payment type (PAY, COMP)
LAST PAY     - Last payment amount (negative = paid)
Phone        - Phone number
Email        - Email address
DAILY RATE   - Daily rate charge
Login ID     - Digital account username
Last Login   - Last digital platform login date
```

---

## Part 1: Static Snapshot Analysis (Single CSV)

### 1. 🔍 **Immediate Expiration Risk**

**What to Extract:**
Count subscribers expiring in next 4, 8, and 12 weeks

**Business Value:**
```
If 400+ subscribers expire in next 4 weeks = URGENT renewal campaign needed
If 1,000+ expire in next 12 weeks = Plan quarterly revenue drop
```

**How to Calculate:**
Compare `Paid Thru` date to current date:
- 0-28 days out = Critical (need immediate contact)
- 29-56 days = Renewal window (start campaign)
- 57-84 days = Pre-renewal nurture

**Red Flag:**
If >15% of total base expires in any 30-day period, you have a revenue cliff problem.

---

### 2. 💰 **Revenue & Rate Analysis**

**What to Extract:**

**A. Current Monthly Revenue**
```
For each subscriber:
- 52 W (weeks) → LAST PAY ÷ 52 × 4.33 = monthly value
- 12 M (months) → LAST PAY ÷ 12 = monthly value
- 1 Y (year) → LAST PAY ÷ 12 = monthly value

Sum all = Total Monthly Recurring Revenue (MRR)
```

**B. Rate Distribution**
Count subscribers by `Zone` field:
- DIGONLY = Digital-only subscribers
- MAILDG = Mail delivery (print+digital bundle)
- PRNTONLY = Print-only subscribers
- COMP = Complimentary (no revenue)

**Business Insight:**
```
Example from your data:
- DIGONLY at $15.99/month (169.99/12)
- MAILDG at ~$14.16/month (169.99/12)
- PRNTONLY at ~$10.83/month (129.99/12)

If 70% are on PRNTONLY, your revenue/subscriber is 25% lower than it could be.
```

**C. Legacy vs. Current Pricing**
Group by `LAST PAY` amount to identify rate tiers:
- Find subscribers paying <$10/month → Legacy rates
- Compare to current published rates
- Calculate revenue gap: (Current rate - Legacy rate) × Legacy subscriber count

**Example Finding:**
```
500 subscribers at $8/month legacy rate
Current rate: $15/month
Monthly revenue gap: (15-8) × 500 = $3,500/month
Annual opportunity: $42,000/year
```

---

### 3. 📍 **Geographic Intelligence**

**What to Extract:**

**A. Concentration Risk**
Count subscribers by ZIP code (from `CITY STATE POSTAL`):
- Top 10 ZIPs = What % of total?
- Single ZIP >20% of base = Geographic concentration risk

**B. Out-of-Market Subscribers**
Identify subscribers outside primary service area:
- Look for states other than SC, WY, MI
- Count NY, NC, PA, VA, etc. subscribers

**Business Value:**
These are your **digital evangelists** or displaced locals:
- High loyalty (paying for hometown news from afar)
- Low cost to serve (digital delivery)
- Should be protected with special pricing

**Example from Data:**
I see subscribers in:
- New York, NC, Pennsylvania, Virginia, Maryland, DC, Delaware
- These are 100% digital-only or mail (no carrier option)

---

### 4. 📱 **Digital Engagement Analysis**

**What to Extract:**

**A. Digital Account Penetration**
```
Total subscribers with Login ID ÷ Total subscribers = Digital activation %
```

**Benchmark:** 60%+ is healthy for modern newspapers

**B. Active Digital Users**
```
Subscribers with Last Login in past 30 days ÷ Total with Login ID = Engagement rate
```

**Benchmark:** 40%+ monthly active users = engaged digital audience

**C. Dormant Digital Accounts**
Count subscribers with:
- Login ID exists (they signed up)
- Last Login >90 days ago OR blank
- Result = Inactive digital subscribers

**Business Value:**
```
If you have 2,000 digital accounts but only 800 logged in last month:
- 60% of digital base is dormant
- Re-engagement campaign opportunity
- Email reminder: "You're paying for access you're not using"
```

**D. Print-Only Subscribers WITHOUT Digital Access**
```
DEL = MAIL or CARR
Zone ≠ DIGONLY or MAILDG
No Login ID

= Print subscribers NOT using available digital
```

**Opportunity:** Upsell them to bundle (print + digital) for $2-3/month more.

---

### 5. 🚚 **Delivery Logistics Analysis**

**What to Extract:**

**A. Delivery Type Distribution**
```
DEL column:
- INTE (Internet/Digital) = Digital delivery
- MAIL = USPS delivery
- CARR = Carrier route delivery
```

**Calculate:**
- % Digital-only (cheap to serve)
- % Mail (moderate cost)
- % Carrier (high cost if routes thin out)

**B. Carrier Route Efficiency**
```
For DEL = CARR:
Count subscribers per Route value
```

**Example:**
```
Route C007: 45 subscribers
Route R003: 28 subscribers
Route B099: 12 subscribers ⚠️ (Unprofitable route)
```

**Critical Threshold:** Routes with <30 papers = losing money on delivery.

**C. Mail vs. Carrier Economics**
```
Carrier delivery cost: ~$0.75/paper (labor + gas + vehicle)
Mail delivery cost: ~$0.60/paper (postage)

If subscriber pays $0.65/day but carrier costs $0.75:
= Losing $0.10/paper × 52 weeks × 3 days/week = -$15.60/year per subscriber
```

**Strategic Decision:**
Convert low-density carrier routes to mail before they become unprofitable.

---

### 6. 💳 **Payment Health Indicators**

**What to Extract:**

**A. Payment Method Mix**
```
LAST PAY column:
- Negative number = Paid in advance (good)
- Zero = Complimentary
- Positive number or blank = Past due (BAD)
```

**B. Complimentary Subscribers**
```
PAY = COMP
Zone = COMP
```

Count these separately - they're revenue $0 but important for circulation count.

**C. Average Payment Size**
```
Filter: LAST PAY < 0 (exclude comps and past due)
Average of |LAST PAY| = Average subscription purchase value
```

**Strategic Insight:**
If average is $65, but you offer 52-week for $65 AND 12-month for $170:
- Subscribers choosing cheapest option (short-term thinking)
- Opportunity: Incentivize annual with discount ($159 vs $170)

---

### 7. 📧 **Email Database Quality**

**What to Extract:**

**A. Email Capture Rate**
```
Subscribers with Email address ÷ Total subscribers = Email %
```

**Benchmark:** 70%+ is good, 50% is acceptable, <40% is a problem.

**B. Email + Digital Account**
```
Has Email AND has Login ID = Digitally-engaged subscriber
Has Email but NO Login ID = Email marketing opportunity
```

**C. Phone vs. Email Contact**
```
Has Phone but no Email = Old-school subscriber (print-likely)
Has neither = Anonymous subscriber (risk)
```

---

## Part 2: Longitudinal Analysis (Weekly Tracking Over Time)

### 8. 📈 **Churn & Retention Tracking**

**How to Track:**
Save each week's CSV with date in filename:
```
AllSubscriberReport_2025-12-07.csv
AllSubscriberReport_2025-12-14.csv
AllSubscriberReport_2025-12-21.csv
```

**Analysis:**

**A. Weekly Churn Rate**
```
Week 1 SUB NUMs: [list of IDs]
Week 2 SUB NUMs: [list of IDs]

Missing from Week 2 = Churned subscribers
Count churned ÷ Week 1 total = Weekly churn %

Annualized churn = Weekly % × 52
```

**Healthy Benchmark:** <0.5% weekly churn (<26% annual)

**B. New Starts Tracking**
```
SUB NUMs in Week 2 NOT in Week 1 = New subscribers
```

**C. Net Subscriber Change**
```
New starts - Churned = Net change
```

**Strategic Value:**
```
Scenario A: -10 net = 50 new, 60 churned (Churn problem)
Scenario B: -10 net = 5 new, 15 churned (Acquisition problem)

Same result, completely different solution needed.
```

---

### 9. 🔄 **Rate Migration Patterns**

**How to Track:**
Compare same `SUB NUM` across weeks, check if:
- `Zone` changed (DIGONLY → MAILDG = upgraded)
- `LEN` changed (12 M → 52 W = downgraded)
- `LAST PAY` changed = renewed at different rate

**Key Migrations to Track:**

**A. Digital Upsells**
```
Week 1: Zone = PRNTONLY
Week 2: Same SUB NUM, Zone = MAILDG

= Successful upsell to print+digital bundle
```

**B. Downgrades**
```
Week 1: LEN = 1 Y ($170)
Week 2: Same SUB NUM, LEN = 12 M ($169.99 but expires sooner)

= Price-sensitive renewal (warning sign)
```

**C. Delivery Switches**
```
Week 1: DEL = CARR
Week 2: Same SUB NUM, DEL = MAIL

= Switched from carrier to mail (cost savings for you, but why?)
```

---

### 10. 📍 **Geographic Shifts**

**How to Track:**
Compare ZIP code distribution week-over-week:

**A. Growing vs. Declining Areas**
```
Week 1: ZIP 29630 has 450 subscribers
Week 4: ZIP 29630 has 435 subscribers (-15)

= Market saturation or competition issue in that ZIP
```

**B. Out-of-Market Growth**
```
Week 1: 120 out-of-state subscribers
Week 12: 145 out-of-state subscribers (+25)

= Digital product gaining traction beyond local market
```

---

### 11. 🎯 **Cohort Retention Analysis**

**How to Track:**
Tag all `SUB NUM` values with their `BEGIN` date month:
```
January 2025 cohort: Track what % still active after 6, 12, 24 months
February 2025 cohort: Same analysis
```

**Business Value:**
```
If January starters have 80% 12-month retention
but July starters have 60% 12-month retention:

Something changed in Q3 (product quality? delivery issues? competition?)
```

---

### 12. 📱 **Digital Adoption Trends**

**How to Track Weekly:**

**A. Digital Activation Rate**
```
Week 1: 5,200 subscribers, 3,100 with Login ID (59.6%)
Week 4: 5,180 subscribers, 3,200 with Login ID (61.8%)

= Digital activation improving (+2.2 percentage points)
```

**B. Last Login Recency**
```
Week 1: 800 logins in past 7 days
Week 2: 850 logins in past 7 days

= Digital engagement increasing
```

**C. Dormant Account Reactivation**
```
SUB NUM 12345:
  Week 1: Last Login = 03/15/2025 (6 months ago)
  Week 8: Last Login = 10/20/2025 (recent)

= Re-engaged dormant user (win!)
```

---

### 13. 💸 **Revenue Trajectory**

**How to Track:**

**A. Monthly Recurring Revenue (MRR)**
Calculate MRR each week:
```
Week 1 MRR: $82,000
Week 4 MRR: $81,200
Week 8 MRR: $80,500

Trend: Declining $375/week = -$19,500/year run rate
```

**B. Average Revenue Per User (ARPU)**
```
Week 1: $82,000 MRR ÷ 5,200 subs = $15.77/subscriber
Week 8: $80,500 MRR ÷ 5,100 subs = $15.78/subscriber

Insight: Revenue per subscriber stable, but losing total subscribers
```

---

### 14. 🚨 **Early Warning Indicators**

**Track These Weekly for Risk Signals:**

**A. Expiration Cliff Watch**
```
If "Paid Thru in next 30 days" jumps from 300 → 600 in one week:
= Renewal wave coming, prepare campaign
```

**B. Carrier Route Thinning**
```
Week 1: Route C007 has 48 subscribers
Week 12: Route C007 has 38 subscribers (-10)

= Route becoming unprofitable, consider mail conversion
```

**C. Payment Slowdown**
```
Week 1: Average "LAST PAY" for renewed subs = -$169
Week 8: Average "LAST PAY" for renewed subs = -$89

= Subscribers renewing for shorter terms (price sensitivity increasing)
```

---

## 🛠️ Recommended Weekly Workflow

### Every Saturday:

**1. Export AllSubscriberReport from Newzware**

**2. Save with dated filename:**
```bash
AllSubscriberReport_YYYY-MM-DD.csv
```

**3. Upload to dashboard** (current process)

**4. Run comparative analysis:**
```
- Compare to last week (churn, new starts)
- Compare to 4 weeks ago (monthly trend)
- Compare to 52 weeks ago (year-over-year)
```

**5. Generate weekly intelligence report:**
```
========================================
Weekly Circulation Intelligence Report
Week Ending: December 7, 2025
========================================

SUBSCRIBER MOVEMENT
-------------------
Total Active: 8,100 (-17 vs last week)
New Starts: 45
Stops: 62
Net Change: -17
Weekly Churn: 0.77%

REVENUE IMPACT
--------------
MRR: $97,610 (-$230 vs last week)
ARPU: $12.05
Lost MRR from churn: -$750/month
New MRR from starts: +$520/month

EXPIRATION RISK
---------------
Expiring 0-4 weeks: 387 (4.8% of base) 🚨
Expiring 5-12 weeks: 1,205 (14.9%)

DIGITAL ENGAGEMENT
------------------
Digital accounts: 4,860 (60% of base)
Logged in last 7 days: 1,240 (25.5%)
Dormant (>90 days): 1,820 (37.5%)

DELIVERY LOGISTICS
------------------
Carrier routes: 28 (avg 58 papers/route)
Routes below 40 papers: 3 ⚠️
Mail delivery: 72%
```

---

## 💡 Strategic Actions from This Data

### Immediate (This Week):
1. **Email everyone expiring in next 30 days** with easy renewal link
2. **Identify subscribers on legacy rates** <$10/month and plan migration
3. **Count dormant digital accounts** and send re-engagement email

### Short-term (This Month):
4. **Analyze carrier routes** below 40 subscribers, consider mail conversion
5. **Track print-only subscribers** without digital access, offer bundle upgrade
6. **Monitor out-of-state subscribers** for churn vs. in-market (likely more loyal)

### Long-term (This Quarter):
7. **Build cohort retention dashboard** to identify seasonal patterns
8. **Calculate customer lifetime value** by acquisition source
9. **Develop predictive churn model** using multi-week patterns

---

## 📊 Sample Analysis Queries

### Query 1: Expiration Risk Report
```
Filter: Paid Thru between TODAY and TODAY + 28 days
Sort by: Paid Thru (ascending)
Columns: SUB NUM, Name, Email, Paid Thru, LAST PAY

Output: List of 387 subscribers expiring in next 4 weeks
Action: Load into email marketing platform for renewal campaign
```

### Query 2: Digital Engagement Audit
```
Filter: Login ID is not blank
Calculate: Days since Last Login
Group by: 0-7 days, 8-30 days, 31-90 days, 90+ days

Output:
- Active (0-7 days): 1,240 subscribers
- Casual (8-30 days): 1,450 subscribers
- At-risk (31-90 days): 1,350 subscribers
- Dormant (90+ days): 1,820 subscribers
```

### Query 3: Revenue Opportunity from Rate Updates
```
Filter: LAST PAY between -$100 and -$50 (legacy pricing)
Count: Number of subscribers
Calculate: Gap = $15 - (|LAST PAY| ÷ 12)
Sum: Total monthly opportunity

Output: "650 subscribers on legacy rates, $4,200/month potential increase"
```

---

**Bottom Line:**
You have ALL the data needed for world-class circulation management. The CSV contains the "who, what, when, where, and how much" - you just need to ask the right questions and track trends over time.

Want me to create specific Excel formulas or SQL queries for any of these analyses?
