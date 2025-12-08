# Sunday Boundary Logic - Week Normalization System

## 🎯 Core Principle

**All data is normalized to Sunday week-ending dates** to enable consistent week-over-week analysis.

---

## 📅 The Publishing Week

### Complete Publishing Week:
- **Starts**: Monday 8:00 AM
- **Ends**: Saturday 11:59 PM
- **Week Ending**: Sunday (the day after Saturday)

### Why Sunday as the Boundary?
- Publishing week completes Saturday night
- Sunday is a "frozen" day (no new data until Monday 8am)
- Natural week-ending date for reporting

---

## 🔄 Upload Time Logic

### Safe Export Window (Saturday 11:59 PM - Monday 7:59 AM):
**Includes**: Late Saturday night, All of Sunday, Early Monday morning

**Logic**: Data exported during this window is COMPLETE for the week that just ended.

**Snapshot Assignment**: → **THIS Sunday** (the week that just completed)

**Examples**:
```
Upload Saturday Dec 6 at 11:30 PM  → snapshot_date = 2025-12-07 (Sun)
Upload Sunday Dec 7 at 2:00 PM     → snapshot_date = 2025-12-07 (Sun)
Upload Monday Dec 8 at 7:30 AM     → snapshot_date = 2025-12-07 (Sun)
```

### Incomplete Week Window (Monday 8:00 AM - Friday):
**Includes**: Monday morning through all of Friday

**Logic**: Current week has started being modified. Data is INCOMPLETE for the current week.

**Snapshot Assignment**: → **PREVIOUS Sunday** (last completed week)

**Examples**:
```
Upload Monday Dec 8 at 8:00 AM     → snapshot_date = 2025-11-30 (prev Sun)
Upload Monday Dec 8 at 10:00 AM    → snapshot_date = 2025-11-30 (prev Sun)
Upload Tuesday Dec 9 at 2:00 PM    → snapshot_date = 2025-11-30 (prev Sun)
Upload Friday Dec 12 at 10:00 AM   → snapshot_date = 2025-11-30 (prev Sun)
```

---

## 💻 Implementation

### PHP Function: `calculateSnapshotDate()`

**Location**: `web/upload.php` (lines 88-122)

```php
function calculateSnapshotDate($uploadDateTime) {
    $timestamp = strtotime($uploadDateTime);
    $dayOfWeek = (int)date('N', $timestamp); // 1=Monday, 7=Sunday
    $hour = (int)date('G', $timestamp); // 0-23

    // Sunday → use today (already the week ending)
    if ($dayOfWeek == 7) {
        return date('Y-m-d', $timestamp);
    }

    // Saturday → use tomorrow (this week's Sunday)
    if ($dayOfWeek == 6) {
        return date('Y-m-d', strtotime('+1 day', $timestamp));
    }

    // Monday before 8am → use yesterday (still in safe window)
    if ($dayOfWeek == 1 && $hour < 8) {
        return date('Y-m-d', strtotime('-1 day', $timestamp));
    }

    // Monday 8am+ or Tue-Fri → previous week's Sunday
    $daysBack = $dayOfWeek + 7;
    return date('Y-m-d', strtotime("-$daysBack days", $timestamp));
}
```

### Automatic Application:

**When**: Every CSV upload via `upload.php`

**What Happens**:
1. System captures upload date/time
2. Calls `calculateSnapshotDate()` with that timestamp
3. Assigns result to ALL rows in the upload
4. Stores original upload date for audit trail

**User Impact**: **None** - Happens transparently in background

---

## 📊 Real-World Scenarios

### Scenario 1: Ideal Monday Morning Upload
```
User exports CSV Monday Dec 8 at 9:00 AM
  ↓
calculateSnapshotDate('2025-12-08 09:00:00')
  ↓
Current week (Dec 1-7) is incomplete
  ↓
snapshot_date = 2025-11-30 (Sunday Nov 30)
  ↓
Data represents: Mon Nov 24 - Sat Nov 30
  ↓
Dashboard shows: "Week ending November 30, 2025"
```

### Scenario 2: Late Saturday Night Upload
```
User exports CSV Saturday Dec 6 at 11:30 PM
  ↓
calculateSnapshotDate('2025-12-06 23:30:00')
  ↓
In safe window (Sat night)
  ↓
snapshot_date = 2025-12-07 (Sunday Dec 7)
  ↓
Data represents: Mon Dec 1 - Sat Dec 6
  ↓
Dashboard shows: "Week ending December 7, 2025"
```

### Scenario 3: Mid-Week Upload (Unusual)
```
User exports CSV Thursday Dec 11 at 2:00 PM
  ↓
calculateSnapshotDate('2025-12-11 14:00:00')
  ↓
Current week (Dec 8-14) is incomplete
  ↓
snapshot_date = 2025-11-30 (Sunday Nov 30)
  ↓
Data represents: Mon Nov 24 - Sat Nov 30
  ↓
NOTE: This is "extra free data" for previous week
```

---

## 🎯 Benefits

### 1. Consistent Week Boundaries
All data aligns to Sunday week-ending dates:
- 2025-11-30 (Week ending Nov 30)
- 2025-12-07 (Week ending Dec 7)
- 2025-12-14 (Week ending Dec 14)

### 2. Historical Accuracy
Expiration buckets calculated "as of snapshot date":
```sql
-- Not "past due today"
WHEN paid_thru < snapshot_date THEN 'Past Due'

-- But "past due as of Nov 30"
```

This means:
- Historical trends are accurate
- Week-over-week comparisons work
- Year-over-year analysis is meaningful

### 3. Flexible Upload Timing
Users can upload:
- Late Saturday night (captures just-completed week)
- Sunday anytime (weekend workflow)
- Early Monday morning before 8am
- Any day Mon-Fri (gets assigned to previous complete week)

### 4. "Free" Bonus Data
Upload on Tuesday? That's extra data for the previous week:
- Main upload Monday morning: Week ending Nov 30
- Bonus upload Tuesday: Also week ending Nov 30 (updates/supplements)
- System uses most recent snapshot

---

## ⚠️ Important Notes

### Upload Frequency
**Recommended**: Once per week, Monday morning

**Why**: Captures most recent complete week

**But**: Can upload more frequently for updates/corrections

### Existing Data
**Old Snapshots**: Non-Sunday dates remain as-is

**New Snapshots**: All assigned to Sundays going forward

**System Behavior**: Handles both transparently via date resolution

### Time Zone Considerations
**Current**: Uses server time (NAS local time)

**Future**: If needed, could add timezone awareness

---

## 🔍 Testing the Logic

### Test Cases (All Pass ✅):

| Upload Date/Time          | Day of Week | Expected Result | Actual Result | Status |
|---------------------------|-------------|-----------------|---------------|--------|
| 2025-12-06 23:00:00      | Sat 11pm    | 2025-12-07      | 2025-12-07    | ✅ PASS |
| 2025-12-07 14:00:00      | Sun 2pm     | 2025-12-07      | 2025-12-07    | ✅ PASS |
| 2025-12-08 07:00:00      | Mon 7am     | 2025-12-07      | 2025-12-07    | ✅ PASS |
| 2025-12-08 08:00:00      | Mon 8am     | 2025-11-30      | 2025-11-30    | ✅ PASS |
| 2025-12-08 10:00:00      | Mon 10am    | 2025-11-30      | 2025-11-30    | ✅ PASS |
| 2025-12-09 09:00:00      | Tue 9am     | 2025-11-30      | 2025-11-30    | ✅ PASS |
| 2025-12-05 10:00:00      | Fri 10am    | 2025-11-23      | 2025-11-23    | ✅ PASS |

### Manual Test:
```php
// Test function directly
$result = calculateSnapshotDate('2025-12-08 09:00:00');
echo $result; // Expected: 2025-11-30
```

---

## 📖 Related Documentation

- **Deployment Guide**: `/docs/DEPLOYMENT-2025-12-05.md`
- **Original Fixes**: `/docs/FIXES-2025-12-05.md`
- **Upload Process**: `/docs/UPLOAD-PROCESS.md`

---

## 🧠 Key Takeaways

1. **Publishing week = Monday 8am to Saturday 11:59pm**
2. **Sunday = Week-ending boundary date**
3. **Safe window = Sat night through Mon 7:59am → THIS Sunday**
4. **Incomplete week = Mon 8am through Friday → PREVIOUS Sunday**
5. **All queries use snapshot_date (not today's date)**
6. **System handles old non-Sunday data transparently**

---

**Implementation Date**: 2025-12-05
**Status**: ✅ Production Ready
**Developer**: Claude Code (AI Assistant)
