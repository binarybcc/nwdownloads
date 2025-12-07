# Week-Based Upload System - Implementation Summary

## Overview

The circulation dashboard now uses a **week-based data storage system** instead of date-based. This implements the "one snapshot per week" model with automatic Monday adjustment.

---

## Key Features

### 1. **One Snapshot Per Week**
- Each week can have only ONE dataset
- Latest upload for a week **replaces** previous uploads
- No more duplicate or conflicting weekly data

### 2. **Monday Snapshot = Previous Week**
- **Monday uploads** are automatically assigned to the **previous week**
- This matches the business workflow: "Monday morning report shows last week's results"
- Other days (Tue-Sun) are assigned to their natural week

### 3. **Flexible Snapshot Days**
- Accept uploads any day of the week
- Saturday/Sunday preferred, but not required
- System handles any day intelligently

---

## How It Works

### Week Assignment Logic:

```
IF snapshot day = Monday:
    week_assigned = week_calculated - 1 (previous week)
ELSE:
    week_assigned = week_calculated (current week)
```

### Example Scenarios:

**Scenario 1: Monday Upload**
- Upload CSV dated: **Monday, Dec 9, 2025**
- Dec 9 naturally falls in: **Week 50** (Dec 7-13)
- System assigns to: **Week 49** (Nov 30 - Dec 6)  ✓
- Display: "Week 49 (Nov 30 - Dec 6, snapshot: Dec 9)"

**Scenario 2: Replace Mid-Week**
- Upload CSV dated: **Tuesday, Dec 3** → Week 49
- Later upload CSV dated: **Saturday, Dec 7** → Week 49
- Saturday data **replaces** Tuesday data automatically

**Scenario 3: Saturday Upload**
- Upload CSV dated: **Saturday, Dec 6**
- Dec 6 naturally falls in: **Week 49** (Nov 30 - Dec 6)
- System assigns to: **Week 49** (same week) ✓
- Display: "Week 49 (Nov 30 - Dec 6, snapshot: Dec 6)"

---

## Database Schema Changes

### daily_snapshots Table:
```sql
Added columns:
- week_num INT (calculated from snapshot_date)
- year INT (year of the week)

Changed Primary Key:
- OLD: (snapshot_date, paper_code)
- NEW: (week_num, year, paper_code)

snapshot_date is now a non-key field (tracks actual snapshot date)
```

### subscriber_snapshots Table:
```sql
Added columns:
- week_num INT
- year INT

Changed Unique Key:
- OLD: (snapshot_date, sub_num, paper_code)
- NEW: (week_num, year, sub_num, paper_code)
```

---

## Upload Process

When you upload a CSV:

1. **Extract date** from filename: `AllSubscriberReport20251209120000.csv` → Dec 9
2. **Calculate week**: Dec 9 = Week 50
3. **Adjust if Monday**: Dec 9 is Monday → Week 49 (previous week)
4. **Delete old data**: Remove any existing Week 49 data
5. **Insert new data**: Add new Week 49 data from this CSV
6. **Result**: Week 49 has exactly ONE snapshot (from Dec 9)

---

## Benefits

✅ **Forgiving of mistakes** - Upload whenever, system handles it
✅ **No duplicate weeks** - Only one dataset per week
✅ **Latest upload wins** - Corrections/updates handled automatically
✅ **Clean comparisons** - Week-over-week always works
✅ **Monday workflow** - Aligns with business reporting pattern

---

## Usage Examples

### Regular Weekly Upload:
```
Monday, Dec 2  → Run AllSubs report → Upload to dashboard
System assigns to Week 48 (Nov 24-30)
Dashboard shows: "Week 48 (snapshot: Dec 2)"
```

### Correction/Re-Upload:
```
Monday, Dec 2  → Upload AllSubs (Week 48)
Later same day  → Notice error, re-run report, upload again
System automatically replaces Week 48 data with newer upload
```

### Multiple Uploads in One Week:
```
Tuesday, Dec 3  → Upload (Week 49)
Thursday, Dec 5 → Upload again (Week 49) - replaces Tuesday data
Saturday, Dec 7 → Upload again (Week 49) - replaces Thursday data
Final result: Week 49 has Saturday's data (most recent)
```

---

## Week Boundaries

**Week Structure: Sunday - Saturday**
- Week starts: **Sunday**
- Week ends: **Saturday**
- This is US standard week format

**Example - Week 49, 2025:**
- Start: Nov 30 (Sunday)
- End: Dec 6 (Saturday)
- Any CSV dated Nov 30 - Dec 6 → Assigned to Week 49
- CSV dated Monday, Dec 7 → Assigned to Week 49 (previous week logic)
- CSV dated Tuesday, Dec 8 → Assigned to Week 50 (current week)

---

## Testing

To test the system:

1. **Upload a CSV** (any day of the week)
2. **Check the dashboard** - Verify week assignment is correct
3. **Upload another CSV for same week** - Verify replacement works
4. **Upload Monday CSV** - Verify it goes to previous week

---

## Important Notes

⚠️ **Monday snapshots** are the only special case - they go to the previous week
⚠️ **Week number** is calculated using ISO 8601 week numbering
⚠️ **Replacements are permanent** - When you upload new data for a week, old data is deleted
⚠️ **Snapshots before 2025-01-01** are still filtered out and not imported

---

## Automation-Ready

This system is designed for future automation:
- **Every Monday morning** → Scheduled task runs AllSubs report
- Automatic upload to dashboard
- System assigns to previous week (the week that just ended)
- Clean, consistent weekly snapshots without manual intervention

---

**Implementation Date:** December 6, 2025
**Version:** 2.0 - Week-Based System
