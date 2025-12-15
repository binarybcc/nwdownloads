# Dashboard Metrics Math Issues - Found 2025-12-06

## Summary

Testing revealed mathematical discrepancies in the circulation dashboard metrics, specifically for **The Advertiser (TA)** publication.

## Issues Found

### Issue #1: Missing Delivery Type Handler - 'EMAI'

**Symptom:**
- Mail + Carrier + Digital ≠ Total Active
- Overall: 8,224 delivery sum vs 8,226 total active (difference of 2)
- TA Paper: 2,901 delivery sum vs 2,903 total active (difference of 2)

**Root Cause:**
The upload.php script doesn't handle the 'EMAI' delivery type in its switch statement (lines 345-358).

**Current Code:**
```php
switch (strtoupper($delivery_type)) {
    case 'MAIL':
        $snapshots[$key]['mail_delivery']++;
        break;
    case 'CARR':
    case 'CARRIER':
        $snapshots[$key]['carrier_delivery']++;
        break;
    case 'INTE':
    case 'INTERNET':
    case 'DIGITAL':
        $snapshots[$key]['digital_only']++;
        break;
    // NO CASE FOR 'EMAI' !!!
}
```

**Data Evidence:**
```sql
-- From subscriber_snapshots table for TA paper (2025-12-06):
delivery_type | count
MAIL          | 2831
INTE          | 69
EMAI          | 2    <-- NOT BEING COUNTED!
```

**Impact:**
- total_active is incremented (line 342)
- But no delivery type counter is incremented
- Result: total_active > (mail + carrier + digital)

**Fix Required:**
Add 'EMAI' case to the switch statement, likely treating it as digital:

```php
switch (strtoupper($delivery_type)) {
    case 'MAIL':
        $snapshots[$key]['mail_delivery']++;
        break;
    case 'CARR':
    case 'CARRIER':
        $snapshots[$key]['carrier_delivery']++;
        break;
    case 'INTE':
    case 'INTERNET':
    case 'DIGITAL':
    case 'EMAI':          // ADD THIS
    case 'EMAIL':         // ADD THIS (variant)
        $snapshots[$key]['digital_only']++;
        break;
}
```

---

### Issue #2: Count Discrepancy Between Tables

**Symptom:**
- daily_snapshots shows 2,903 total_active for TA
- subscriber_snapshots only has 2,902 unique records for TA
- Difference of 1 subscriber

**Data Evidence:**
```sql
-- daily_snapshots:
paper_code | total_active | mail_delivery | carrier_delivery | digital_only
TA         | 2903         | 2831          | 0                | 70

-- subscriber_snapshots:
COUNT(*) = 2902
COUNT(DISTINCT sub_num) = 2902

-- Breakdown:
MAIL: 2831
INTE: 69
EMAI: 2
TOTAL: 2831 + 69 + 2 = 2902  (NOT 2903!)
```

**Root Cause:**
Unknown - needs further investigation. Possibilities:
1. Duplicate subscriber being counted during CSV processing
2. Off-by-one error in counting logic
3. Row with NULL/empty sub_num being counted as total_active but not inserted into subscriber_snapshots

**Fix Required:**
Investigation needed - check upload.php for:
- How `$snapshots[$key]['total_active']++` is being incremented
- Whether any rows are skipped in subscriber_snapshots but still count toward total_active
- Duplicate handling logic

---

## Test Results

### Overall Metrics (2025-12-06)
- Total Active: 8,226
- On Vacation: 0
- Deliverable: 8,226 ✓ (= Total - Vacation)
- Mail: 7,419
- Carrier: 406
- Digital: 399
- **Delivery Sum: 8,224 ✗ (SHOULD BE 8,226)**

### Per-Paper Breakdown

| Paper | Total | Mail | Carrier | Digital | Sum | Missing |
|-------|-------|------|---------|---------|-----|---------|
| TA    | 2,903 | 2,831 | 0       | 70      | 2,901 | **2** ✗ |
| TJ    | 3,109 | 2,428 | 376     | 305     | 3,109 | 0 ✓ |
| LJ    | 773   | 736   | 30      | 7       | 773   | 0 ✓ |
| TR    | 1,322 | 1,305 | 0       | 17      | 1,322 | 0 ✓ |
| WRN   | 119   | 119   | 0       | 0       | 119   | 0 ✓ |

### Business Unit Totals
All business unit sums match overall totals ✓

### Data Quality
- No negative values ✓
- No NULL values ✓
- On Vacation <= Total Active ✓

---

## Recommended Actions

### Immediate (Fix Issue #1)
1. Update upload.php to handle 'EMAI' delivery type
2. Re-upload the most recent CSV to recalculate daily_snapshots
3. Verify math is correct after fix

### Investigation Needed (Issue #2)
1. Review upload.php counting logic around lines 300-400
2. Check for edge cases where total_active is incremented but subscriber isn't inserted
3. Add validation in upload.php to ensure:
   ```php
   // After processing CSV, verify:
   total_active == (mail_delivery + carrier_delivery + digital_only + other_delivery_types)
   ```

### Long-term
1. Add delivery type validation with a default/unknown category
2. Add automated tests that run after each upload to verify math
3. Consider adding a "Unknown Delivery Type" counter to catch future edge cases

---

## Testing Script

A comprehensive test script has been created: `web/test_metrics_math.php`

**Usage:**
```bash
# Test most recent Saturday
docker exec circulation_web php /var/www/html/test_metrics_math.php

# Test specific date
docker exec circulation_web php /var/www/html/test_metrics_math.php 2025-12-06
```

The script verifies:
- Overall totals math (deliverable, delivery type sums)
- Business unit aggregations
- Per-paper calculations
- Year-over-year comparison data
- Data quality checks

---

## Next Steps

When you're ready to fix, please:

1. **Review the proposed fix** for upload.php (adding EMAI case)
2. **Decide on categorization** - Should EMAI be counted as digital_only or a separate category?
3. **Test with old CSV** - Upload an older AllSubscriberReport to verify the fix works
4. **Investigate Issue #2** - Determine why TA has 1 extra subscriber in daily_snapshots

**Note:** After fixing, the dashboard numbers should match exactly with:
- total_active = mail + carrier + digital (+ any other delivery types)
- deliverable = total_active - on_vacation
- business unit sums = overall totals
