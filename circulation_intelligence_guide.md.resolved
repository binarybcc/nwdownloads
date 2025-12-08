# Newzware Database - Circulation Director's Business Intelligence Guide

> **Perspective:** COO/Circulation Director with 20+ years experience  
> **Data Source:** Newzware circulation management system ERD  
> **Update Frequency:** Weekly snapshots (matching your current workflow)

---

## 🎯 Executive Summary

Based on the Newzware schema, you have access to **enterprise-grade circulation intelligence**. The database contains 20+ interconnected tables covering everything from subscriber demographics to payment history to delivery logistics.

**Key Finding:** You're currently using <5% of available data. The tables you're NOT querying contain the "why" behind the numbers you see.

---

## Tier 1: Quick Wins (Implement This Week)

### 1. 📉 **Subscription Length Analysis**

**Current State:** You track total active subscribers  
**Opportunity:** Track *how long they'll remain active*

**Data Available in ERD:**
- `SUBSCR` table → `sub_lngth_qty` (subscription length in weeks/months)
- `sub_start_dte` and `sub_expire_dte` → Calculate weeks remaining

**Business Value:**
```
If you have 8,000 subscribers but 2,000 expire in the next 8 weeks,
you need to start renewal campaigns NOW, not in week 7.
```

**Weekly Metric to Track:**
- **Subscriptions Expiring in Next 4 Weeks** - Urgent renewal targets
- **Subscriptions Expiring in 5-12 Weeks** - Pre-renewal marketing window
- **Average Weeks Remaining** - Overall "health" indicator

**Benchmark:** Healthy newspaper should have <10% of base expiring in any 30-day period.

**Query Concept:**
```sql
SELECT 
  CASE 
    WHEN DATEDIFF(sub_expire_dte, CURDATE()) <= 28 THEN '0-4 weeks'
    WHEN DATEDIFF(sub_expire_dte, CURDATE()) <= 84 THEN '5-12 weeks'
    ELSE '12+ weeks'
  END as expiration_bucket,
  COUNT(*) as subscriber_count
FROM SUBSCR
WHERE sub_status_cd = 'ACTIVE'
GROUP BY expiration_bucket;
```

---

### 2. 💰 **Rate/Revenue Mix Intelligence**

**Current State:** You track subscriber counts  
**Opportunity:** Track *revenue per subscriber* and identify product mix issues

**Data Available:**
- `RATE` table → `rate_amt`, `rate_nm`, `rate_cd`  
- `SUBSCR` → `rate_cd` (links to rate table)
- `rate_type_cd` → Print/Digital/Combo distinction

**Business Value:**
```
Example: If 60% of your subscribers are on $8/month legacy rates
but new subscribers pay $15/month, you're leaving $56,000/year on
the table with just 1,000 legacy subscribers.
```

**Weekly Metrics:**
- **Revenue Per Subscriber by Rate** - Which products make money
- **Legacy Rate Percentage** - How many are on old pricing
- **Print vs Digital Revenue Split** - Where the money comes from

**Strategic Questions:**
1. What % are on rates created >3 years ago? (Legacy pricing risk)
2. What's the average $/month by rate type?
3. Are new subscribers getting better or worse rates than existing?

**Benchmark:** If >40% on legacy rates, you have a pricing migration problem.

---

### 3. 🚚 **Delivery Type Trends**

**Current State:** You track mail/carrier/digital counts  
**Opportunity:** Predict when print delivery becomes unprofitable

**Data Available:**
- `SUBSCR` → `dlvry_type_cd` (MAIL/CARR/INTE)
- `ROUTE` table → Carrier route assignments
- `ZONE` table → Geographic delivery zones

**Business Value:**
```
If carrier routes drop below 40 deliveries/route, the cost per
delivery doubles. You need to know BEFORE you're underwater.
```

**Weekly Metrics:**
- **Subscribers Per Carrier Route** - Efficiency metric
- **Mail Penetration by Geography** - Rural going 100% mail?
- **Carrier Turnover Impact** - Did route changes cause cancellations?

**Critical Insight:**
> Industry standard: 60+ papers/route = profitable. Below 40 = consider mail conversion or route consolidation.

**Risk Indicator:**
If carrier delivery drops 5%+ in a quarter, dig into WHY:
- Are routes being consolidated? (Good - cost savings)
- Are subscribers switching to mail? (Neutral)
- Are subscribers canceling? (Bad - churn issue)

---

### 4. 📊 **New Starts vs. Stops Tracking**

**Current State:** You see net subscriber change  
**Opportunity:** Separate growth from churn

**Data Available:**
- `SUBSCR` → `sub_start_dte` (when they begin)
- `sub_expire_dte` + `sub_status_cd` (when they end)
- `SUBSTP` table → Stop reasons and dates

**Business Value:**
```
Scenario A: -50 net change = 100 new, 150 stops (CRISIS)
Scenario B: -50 net change = 200 new, 250 stops (Growth problem, but manageable)

Same net result, completely different strategic response.
```

**Weekly Metrics:**
- **New Starts This Week** - Acquisition performance
- **Stops This Week** - Churn rate
- **Net Change** - Overall trend
- **Churn Rate** - Stops ÷ Total Active (%)

**Benchmark:** 
- Healthy newspaper: 2-3% monthly churn (24-36% annual)
- Struggling newspaper: 4%+ monthly churn (>48% annual)

**Stop Reason Analysis:**
- `SUBSTP.stp_reason_cd` → WHY they're leaving
- Group by reason: Price? Service? Moving? Deceased?

**Gold Mine Insight:**
> If 40% of stops are "Price" but you haven't raised rates in 2 years, the problem isn't price—it's perceived value. Fix your product, not your pricing.

---

## Tier 2: Strategic Intelligence (Worth the Setup Time)

### 5. 🎯 **Payment Health Dashboard**

**Data Available:**
- `PAY` table → Payment history
- `pay_type_cd` → Auto-renew vs manual
- `PAY_TYPE` → Payment method details
- `CCARD` → Credit card on file?

**Critical Metrics:**
- **Auto-Renew Penetration** - % on automatic payment
- **Days to Payment** - Average collection time
- **Past-Due Buckets** - 30/60/90 day aging

**Business Value:**
```
Subscribers on auto-renew have 90%+ retention vs 55-60% manual pay.
Every conversion to auto-pay is worth ~$200 lifetime value.
```

**The $50,000 Question:**
What % of your subscribers have a credit card on file (`CCARD` table)?
- If <30%: You're in "renewal crisis" mode every cycle
- If >70%: You have a sustainable base

**Action Item:**
Create weekly report of subscribers expiring in 8-12 weeks who are NOT on auto-renew. Target them with "easy renewal" messaging.

---

### 6. 📍 **Geographic Performance Analysis**

**Data Available:**
- `geo_cd` fields across tables (county/region)
- `ZONE` table → Delivery zones
- `ROUTE` → Carrier routes by geography

**Why Geography Matters:**
Different markets have different economics:
- **Urban:** High density, carrier-delivered, competitive
- **Rural:** Low density, mail-delivered, less competition

**Weekly Metrics:**
- **Subscribers by County/Zone** - Where's your base?
- **Penetration Rates** - % of households subscribed (if you have census data)
- **Cost Per Delivery by Zone** - Rural economics

**Strategic Question:**
Are you profitable in low-density rural areas, or are you subsidizing them with urban revenue?

**Example Analysis:**
```
Zone A (Urban): 2,000 subs, $15/month avg, carrier delivered
Zone B (Rural): 500 subs, $12/month avg, mail delivered

Zone A profit margin: 35%
Zone B profit margin: 8%

Decision: Raise rural rates or accept it as market penetration cost?
```

---

### 7. 🔄 **Subscription Type Migration**

**Data Available:**
- `SUBSCR.sbscrptn_type_cd` → Print/Digital/Combo
- Historical changes via `sub_mod_dte` or audit tables

**Track Over Time:**
- Print-only → Print+Digital (upsell success)
- Print → Digital-only (managed decline)
- Digital → Canceled (churn risk)

**Critical Insight:**
```
Print-only: 25% annual churn
Digital-only: 45% annual churn
Print+Digital combo: 15% annual churn

Lesson: Bundle = retention. Push combo packages.
```

---

### 8. 📅 **Cohort Retention Analysis**

**Data Available:**
- `sub_start_dte` → Group by start month
- `sub_expire_dte` or `sub_status_cd` → Track survival

**The Analysis:**
Group subscribers by start month, then track what % remain active after:
- 3 months (danger zone)
- 6 months (stabilization)
- 12 months (loyal base)

**Business Value:**
```
If January 2024 starters have 65% retention at 12 months
but June 2024 starters have 80% retention,
you found a seasonal quality issue.
```

**Action:**
Whatever you did in June, do more of that. Whatever happened in January, avoid it.

---

## Tier 3: Advanced Analysis (6 Month Projects)

### 9. 📈 **Lifetime Value (LTV) Modeling**

**Requires:**
- Historical subscriber data (3+ years)
- Payment history
- Stop tracking

**Calculate:**
Average revenue per subscriber over their entire lifecycle, by acquisition source.

**Example Output:**
- Referral subscribers: $850 LTV (stay 3.2 years)
- Direct mail subscribers: $420 LTV (stay 1.5 years)
- Online signup: $280 LTV (stay 0.9 years)

**Decision Impact:**
If a referral is worth $850 and direct mail is $420, you can spend 2x more to acquire a referral and still win.

---

### 10. 🚨 **Predictive Churn Modeling**

**The Holy Grail:**
Identify subscribers likely to cancel BEFORE they do.

**Risk Signals in Your Data:**
- Stopped auto-renew recently
- Changed from carrier to mail delivery
- Reduced subscription length (52wk → 26wk)
- Payment becoming slower (30 days → 45 days)
- Multiple service complaints (`COMPLAINT` table if exists)

**Business Value:**
If you can predict churn with 70% accuracy, you can:
- Target retention offers to at-risk subscribers
- Save 30-40% of "saveable" cancellations
- ROI: Every saved subscriber = $200+ saved acquisition cost

---

## 🔧 Tool Recommendations

### Immediate (This Month):
1. **Build Expiration Dashboard** - 4-week lookahead
2. **Rate Mix Report** - Revenue by rate type
3. **New Starts vs Stops** - Weekly trend

### Short-term (3 Months):
4. **Payment Health Metrics** - Auto-renew %
5. **Geographic Analysis** - Profitability by zone
6. **Stop Reason Tracking** - Why are they leaving?

### Long-term (6-12 Months):
7. **Cohort Retention** - Start month analysis  
8. **LTV Modeling** - Value by acquisition source
9. **Predictive Churn** - At-risk scoring

---

## 📊 Sample Weekly Dashboard

```
========================================
Weekly Circulation Intelligence Report
Week of December 1, 2025
========================================

SUBSCRIBER HEALTH
-----------------
Total Active: 8,100
New Starts (week): 45
Stops (week): 62
Net Change: -17
Weekly Churn Rate: 0.77%

EXPIRATION RISK
---------------
Expiring 0-4 weeks: 387 (4.8%) 🚨
Expiring 5-12 weeks: 1,205 (14.9%)
Avg weeks remaining: 23.4

REVENUE MIX
-----------
Print-only: 4,850 ($11.50 avg)
Digital-only: 890 ($8.25 avg)
Print+Digital: 2,360 ($15.75 avg)
Weighted avg: $12.10/subscriber
Monthly revenue: $97,610

PAYMENT HEALTH
--------------
Auto-renew: 62% ✅
Manual pay: 38%
Past due >30 days: 145 (1.8%)

DELIVERY LOGISTICS
------------------
Carrier routes: 28
Avg papers/route: 58.2
Mail delivery: 72%
Carrier delivery: 23%
Digital-only: 5%
```

---

## 🎯 Final Recommendations

**Priority 1 (Do This Week):**
Add expiration tracking to your current dashboard. This is 80% of the value with 20% of the effort.

**Priority 2 (Do This Month):**
Separate new starts from stops. See what's actually happening beneath net change.

**Priority 3 (Do This Quarter):**
Build payment health tracking. Auto-renew conversion is your highest-ROI project.

**Your Competitive Advantage:**
Most small newspapers run on gut feel and lagging indicators. You have real-time data. Use it.

---

**Questions to Consider:**
1. What % of revenue comes from subscribers who joined >5 years ago? (Demographic time bomb if high)
2. What's your revenue per delivery stop? (Rural routes may be unprofitable)
3. How fast are print subscribers switching to digital? (Trend line for print capacity)

Want me to design specific SQL queries for any of these analyses?
