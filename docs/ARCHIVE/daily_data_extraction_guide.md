# Daily Data Extraction Guide for Circulation Dashboard

## Overview
Run these 3 queries daily in Newzware Ad-Hoc Query Builder to generate data for the circulation dashboard.

**Recommended Schedule:** 6:00 AM daily (after overnight processing)

---

## Query 1: Active Subscriptions Export

**Purpose:** Get all active subscriptions with vacation linkage and rate information

**Query Name:** `daily_subscriber_export`

### Settings:
```
Database Selection: NEWZWARE
Column List: sp_num, sp_stat, sp_rate_id, sp_route, sp_vac_ind
Table List: subscrip
Where Clause: sp_stat = 'A'
Order By List: sp_num

☑ Suppress Page Breaks / Headings
☑ Show First Row Headings
Column Delimiter: COMMA
Subsystem Selection: Circulation
```

### Export File Name:
```
subscriptions_YYYYMMDD.csv
Example: subscriptions_20251125.csv
```

### What Each Column Means:
- `sp_num` - Subscription number
- `sp_stat` - Status (A=Active)
- `sp_rate_id` - Links to rate table for edition
- `sp_route` - MAIL, INTERNET, or route number
- `sp_vac_ind` - Links to vacation table (0 = no vacation)

### Expected Results:
- ~8,500 rows (active subscriptions)
- File size: ~200-300 KB
- Includes ALL editions (TJ, TA, TR, LJ, WRN, FN)

---

## Query 2: Vacation Holds Export

**Purpose:** Get all active and upcoming vacation holds

**Query Name:** `daily_vacation_export`

### Settings:
```
Database Selection: NEWZWARE
Column List: vd_sp_id, vd_beg_date, vd_end_date, vd_type, vd_vac_days
Table List: vac_detl
Where Clause: (leave blank to get all vacations)
Order By List: vd_beg_date DESC

☑ Suppress Page Breaks / Headings
☑ Show First Row Headings
Column Delimiter: COMMA
Subsystem Selection: Circulation
```

### Export File Name:
```
vacations_YYYYMMDD.csv
Example: vacations_20251125.csv
```

### What Each Column Means:
- `vd_sp_id` - Links to subscription sp_vac_ind
- `vd_beg_date` - Vacation start date
- `vd_end_date` - Vacation end date (restart delivery)
- `vd_type` - Vacation type code
- `vd_vac_days` - Number of days

### Expected Results:
- ~30-50 rows (typical vacation holds)
- File size: <10 KB
- Includes past, current, and future vacations

---

## Query 3: Rate-to-Edition Mapping (Weekly Only)

**Purpose:** Map rate IDs to edition codes (TJ, TA, TR, LJ, WRN, FN)

**Query Name:** `weekly_rates_export`

**Run Frequency:** Weekly (or when rates change)

### Settings:
```
Database Selection: NEWZWARE
Table List: retail_rate
Column List: * (all columns)
Order By List: (blank)

☑ Suppress Page Breaks / Headings
☑ Show First Row Headings
Column Delimiter: COMMA
Subsystem Selection: Circulation
```

### Export File Name:
```
rates_YYYYMMDD.csv
Example: rates_20251125.csv
```

### Key Columns Used:
- Column 1 (`Rate.db Rowid`) - Internal rate ID
- Column 4 (`Description`) - Rate description
- Column 6 (`Edition`) - TJ, TA, TR, LJ, WRN, FN
- Column 56 (`Sub Rate Id`) - Links to subscription sp_rate_id

### Expected Results:
- ~400 rows (all rate definitions)
- File size: ~30-50 KB
- Relatively stable (changes infrequently)

---

## Daily Processing Workflow

### Step 1: Export Data (5 minutes)
1. Open Newzware Ad-Hoc Query Builder
2. Run Query 1: Active Subscriptions
3. Save to `/queries/subscriptions_YYYYMMDD.csv`
4. Run Query 2: Vacation Holds
5. Save to `/queries/vacations_YYYYMMDD.csv`
6. (Weekly) Run Query 3: Rates
7. (Weekly) Save to `/queries/rates_YYYYMMDD.csv`

### Step 2: Run Analysis Script (30 seconds)
```bash
python3 final_complete_analysis.py
```

This generates:
- Console output showing daily metrics
- `daily_snapshot.json` with all dashboard data

### Step 3: View Dashboard (Optional)
- Open dashboard web app
- Refreshes automatically from `daily_snapshot.json`
- View trends, drill-downs, comparisons

---

## File Organization

### Directory Structure:
```
/Users/johncorbin/Desktop/projs/nwdownloads/
├── queries/                          # Daily exports
│   ├── subscriptions_20251125.csv
│   ├── vacations_20251125.csv
│   └── rates_20251125.csv
├── final_complete_analysis.py        # Processing script
├── daily_snapshot.json               # Dashboard data
└── docs/                             # Documentation
    └── daily_data_extraction_guide.md
```

### Retention Policy:
- **Daily exports:** Keep 90 days
- **daily_snapshot.json:** Store in database for long-term trends
- **rates.csv:** Keep current + last 2 versions

---

## Quick Reference: Column Mappings

### Subscription Status Codes:
- `A` = Active
- `I` = Inactive
- `P` = Pending
- `V` = Void (deleted)

### Delivery Types (sp_route):
- `MAIL` = USPS mail delivery
- `INTERNET` = Digital-only subscription
- `CARRIER` or route numbers = Carrier delivery
- `MOTOR` = Motor route

### Vacation Types (vd_type):
Common types observed:
- `1` = Standard vacation
- `2` = Extended hold
- `3` = Seasonal
- `8` = Other/Special

(Note: Check NW documentation for official type definitions)

---

## Troubleshooting

### Issue: Query returns 0 rows
**Solution:** Check WHERE clause - ensure date format matches NW syntax

### Issue: Column not found error
**Solution:** Column names may have trailing spaces - check exact spelling

### Issue: Vacation linkage not working
**Solution:** Ensure sp_vac_ind in subscriptions matches vd_sp_id in vacations

### Issue: Wrong edition counts
**Solution:** Re-export rates.csv - rate definitions may have changed

---

## Data Quality Checks

### Daily Validation:
- [ ] Total subscriptions ~8,500 (±100)
- [ ] TJ ~3,100 (largest paper)
- [ ] TA ~2,900 (second largest)
- [ ] Mail delivery ~90% of total
- [ ] Vacation rate <5% typically
- [ ] No negative numbers
- [ ] Deliverable = Total - On Vacation

### Weekly Validation:
- [ ] Compare week-over-week trends
- [ ] Flag unusual spikes/drops (>5% change)
- [ ] Verify FN (sold paper) excluded from active counts
- [ ] Check for orphaned vacation records

---

## Automation (Future Enhancement)

### Option 1: Scheduled Script
```bash
# Add to crontab
0 6 * * * cd /path/to/project && python3 final_complete_analysis.py
```

### Option 2: Windows Task Scheduler
- Create task to run Python script daily at 6 AM
- Email results to management

### Option 3: Full Integration
- Export NW data to shared drive/database
- Dashboard auto-refreshes from new data
- Alerts for unusual trends

---

## Support Contacts

**Newzware Issues:**
- Support: support@newzware.com
- Your account rep: [name]

**Dashboard Issues:**
- Developer: [contact]
- Documentation: This guide

**Data Questions:**
- South Carolina (TJ): [contact]
- Michigan (TA): [contact]
- Wyoming (TR/LJ/WRN): [contact]

---

**Last Updated:** November 25, 2025
**Version:** 1.0
**Next Review:** Monthly
