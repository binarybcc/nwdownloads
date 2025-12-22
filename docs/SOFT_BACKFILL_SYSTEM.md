# SoftBackfill System Documentation

> **Implemented:** December 8, 2025
> **Version:** 1.0.0
> **Status:** Production Ready

---

## Overview

The SoftBackfill system is an intelligent data backfilling algorithm that automatically fills historical weeks with subscriber data based on CSV upload dates.

### Key Principles

**"Each upload owns its week and backfills backward until hitting REAL data"**

- Uploads **ONLY backfill backward** (never forward)
- **REAL data (is_backfilled = 0):** Protected - stops backfilling
- **BACKFILLED data (is_backfilled = 1):** Placeholder - can be replaced by real uploads
- Upload order **doesn't matter** - real data always wins
- System **self-organizes** - you can upload files in any order

### Quick Reference

| Scenario | Behavior |
|----------|----------|
| Upload file for empty week | Creates REAL data, backfills empty weeks backward |
| Upload file for week with BACKFILLED data | **Replaces** backfilled data with REAL data |
| Upload file for week with REAL data | Only replaces if new file is newer |
| Backfilling encounters REAL data | **Stops** - real data is protected |
| Backfilling encounters BACKFILLED data | **Continues** - backfilled data can be replaced |

---

## How It Works

### Core Algorithm: Backward-Only Backfill

**Key Rule:** Uploads **ONLY backfill backward** until hitting existing data. There is **NO forward fill**.

When you upload a CSV file (e.g., `AllSubscriberReport20251208120000.csv`):

1. **Extract date from filename** → December 8, 2025
2. **Calculate ISO week number** → Week 50, 2025
3. **Start at upload week, work backward only:**
   - **Week 50** (upload week):
     - If empty → Fill with Dec 8 data (real data, 0 weeks backfilled)
     - If has older data → Replace with Dec 8 data
     - If has newer data → STOP (reject entire upload)
   - **Week 49** (1 week back):
     - If empty → Fill with Dec 8 data (backfilled, 1 week)
     - If has ANY data → **STOP backfilling** (existing data wins)
   - **Week 48, 47, 46...** (continue backward):
     - If empty → Fill with Dec 8 data (backfilled, 2, 3, 4... weeks)
     - If has ANY data → **STOP backfilling**
     - If reached **Oct 1, 2025** → STOP (minimum backfill date)

4. **Track metadata** for every snapshot:
   - `source_filename` - Which CSV created this data
   - `source_date` - Date from that CSV filename
   - `is_backfilled` - Boolean flag (0 = real data, 1 = backfilled)
   - `backfill_weeks` - How many weeks back (0 = upload week)

### Critical Behavior: Distinguish Real vs Backfilled Data

**⚠️ IMPORTANT CLARIFICATION:**

**When backfilling backward, the algorithm distinguishes between REAL data and BACKFILLED data:**

- **REAL data (is_backfilled = 0):** Stops backfilling - real data is protected
- **BACKFILLED data (is_backfilled = 1):** Can be REPLACED by real uploads - backfill is placeholder data

**This ensures:**
- Real data is always respected and never overwritten by backfill
- Backfilled data can be replaced when you upload the actual historical files
- Upload order doesn't matter - real data always wins over backfilled data
- You can upload files in any order, and the system self-organizes correctly

**Example:**
```
Upload Dec 22 (Week 51):
Week 48: Dec 22 (backfilled) ← Can be replaced
Week 49: Dec 22 (backfilled) ← Can be replaced
Week 50: Dec 22 (backfilled) ← Can be replaced
Week 51: Dec 22 (REAL)       ← Protected

Upload Nov 24 (Week 48):
Week 48: Nov 24 (REAL)       ← Replaced backfill with real data ✓
Week 49: Dec 22 (backfilled) ← Still backfilled
Week 50: Dec 22 (backfilled) ← Still backfilled
Week 51: Dec 22 (REAL)       ← Still protected

Upload Dec 1 (Week 49):
Week 48: Nov 24 (REAL)       ← Protected, stops backfill
Week 49: Dec 1 (REAL)        ← Replaced backfill with real data ✓
Week 50: Dec 22 (backfilled) ← Still backfilled
Week 51: Dec 22 (REAL)       ← Still protected
```

### Example Scenario

```
State 0 - Empty Database:
Week 40  Week 41  Week 42  Week 43  Week 44  Week 45  Week 46  Week 47  Week 48  Week 49  Week 50  Week 51
[empty   empty    empty    empty    empty    empty    empty    empty    empty    empty    empty    empty]

📁 Upload: AllSubscriberReport20251201.csv (Week 48, Dec 1)
Algorithm:
- Week 48 (upload): Empty → Fill with Dec 1 (real data, 0 weeks backfilled)
- Week 47: Would backfill, but Nov 24 is minimum date → STOP

Result:
Week 40  Week 41  Week 42  Week 43  Week 44  Week 45  Week 46  Week 47  Week 48  Week 49  Week 50  Week 51
[N/A     N/A      N/A      N/A      N/A      N/A      N/A      N/A      12-1     empty    empty    empty]
                                                                         ↑ Data world starts here

📁 Upload: AllSubscriberReport20251208.csv (Week 50, Dec 8)
Algorithm:
- Week 50 (upload): Empty → Fill with Dec 8 (real data, 0 weeks backfilled)
- Week 49: Empty → Fill with Dec 8 (backfilled 1 week)
- Week 48: HAS DATA (Dec 1) → **STOP** (existing data wins)

Result:
Week 40  Week 41  Week 42  Week 43  Week 44  Week 45  Week 46  Week 47  Week 48  Week 49  Week 50  Week 51
[N/A     N/A      N/A      N/A      N/A      N/A      N/A      N/A      12-1     12-8     12-8     empty]
                                                                  Dec 1  ←Dec 8→  (real)

📁 Upload: AllSubscriberReport20251215.csv (Week 51, Dec 15)
Algorithm:
- Week 51 (upload): Empty → Fill with Dec 15 (real data, 0 weeks backfilled)
- Week 50: HAS DATA (Dec 8) → **STOP** (existing data wins)

Result:
Week 40  Week 41  Week 42  Week 43  Week 44  Week 45  Week 46  Week 47  Week 48  Week 49  Week 50  Week 51
[N/A     N/A      N/A      N/A      N/A      N/A      N/A      N/A      12-1     12-8     12-8     12-15]
                                                                  Dec 1  ←Dec 8→  Dec 15
```

**Result:** Each upload "owns" its week and backfills as far backward as possible until hitting existing data.

---

## Visual Indicators

### Dashboard Badges

**Backfilled Data (<2 weeks):**
- Small amber badge next to week label
- Shows number of weeks backfilled
- Tooltip with source date

**Backfilled Data (>2 weeks):**
- Large amber warning banner at top of dashboard
- Explains what backfilled data means
- Shows source file and date
- Warns users data may not be accurate

---

## Admin Tools

### Data Provenance Audit

**URL:** `http://localhost:8081/admin_audit.php` (Development)
**URL:** `http://192.168.1.254:8081/admin_audit.php` (Production)

**Features:**
- View all upload sources
- See which file owns which weeks
- Identify backfilled vs. real data
- Understand data freshness

**Use Cases:**
- Debugging upload issues
- Verifying data accuracy
- Understanding data gaps
- Planning which CSVs to upload

---

## API Changes

### Overview Endpoint Response

**New field:** `backfill` object

```json
{
  "has_data": true,
  "week": {...},
  "current": {...},
  "backfill": {
    "is_backfilled": true,
    "backfill_weeks": 3,
    "source_date": "2025-12-07",
    "source_filename": "AllSubscriberReport20251207120000.csv"
  },
  "comparison": {...}
}
```

---

## Database Schema

### New Columns (both `daily_snapshots` and `subscriber_snapshots`)

| Column | Type | Description |
|--------|------|-------------|
| `source_filename` | VARCHAR(255) | Original CSV filename |
| `source_date` | DATE | Date extracted from filename |
| `is_backfilled` | TINYINT(1) | 1 if backfilled, 0 if real |
| `backfill_weeks` | INT | Weeks backfilled (0 = real) |

**Indexes:**
- `idx_source_date` - For querying by source
- `idx_backfilled` - For filtering backfilled data

---

## Benefits

### Over Previous System (Day-of-Week Precedence)

**Old System:**
- Complex rules (Saturday > Friday > Thursday...)
- Upload order mattered
- Could reject valid data
- Hard to understand which data was current

**SoftBackfill:**
- ✅ Simple rule: "latest date wins"
- ✅ Upload order doesn't matter
- ✅ Never rejects data
- ✅ Clear provenance tracking
- ✅ Automatic gap filling
- ✅ Easy to audit

### Flexibility

- **Upload out of order:** No problem - system self-organizes
- **Re-upload corrected data:** Just upload with the correct date
- **Fill historical gaps:** Upload old CSVs anytime
- **Data validation:** Admin audit shows exactly what you have

---

## Edge Cases Handled

### Scenario: Upload Very Old Data

**Problem:** Uploading Dec 28 file when database is empty since Oct 1
**Result:** Backfills 13 weeks with Dec 28 data
**Warning:** Dashboard shows "⚠️ Backfilled 13 weeks" - user knows data is approximate

### Scenario: Upload Same Week Twice

**Problem:** Re-upload same week with corrected data
**Result:** Replaces existing data, updates source_filename
**Behavior:** Latest upload wins (by filename date, not upload time)

### Scenario: Upload in Any Order

**Example:** Upload Week 51, then Week 48, then Week 49 (out of order)

```
Upload Week 51 (Dec 15):
[empty ... empty   empty   empty   12-15]  ← Week 51 + backfill to Oct 1

Upload Week 48 (Dec 1):
[empty ... 12-1    12-1    empty   12-15]  ← Week 48 stops at Week 49 (has Dec 15 backfill)

Upload Week 49 (Dec 8):
[empty ... 12-1    12-8    empty   12-15]  ← Week 49 stops at Week 48 (has Dec 1)
```

**Result:** Upload order doesn't matter - each week owns its data and backfills until hitting existing data

---

## Configuration

### Backfill Minimum Date

**Current:** November 17, 2025 (start of Week 47)
**Location:** `AllSubscriberImporter.php` line 354

```php
$min_backfill_date = '2025-11-17';  // Start of Week 47
```

**Why Nov 17:** Files subtract 7 days for "data represents previous week" logic (line 179). The first real CSV upload (`AllSubscriberReport20251124161207.csv` dated Nov 24) becomes Nov 17 after the -7 day adjustment, which is Week 47. Setting the minimum to Nov 17 allows all historical uploads starting from Nov 24 to work correctly.

**To change:** Edit this value in `AllSubscriberImporter.php` and redeploy

### Warning Threshold

**Current:** 2 weeks
**Location:** `assets/backfill-indicator.js` line 41

```javascript
if (backfillData.backfill_weeks > 2) {
    this.showWarning(backfillData);
}
```

---

## Testing

### Test Scenarios

1. **Empty database** → Upload Dec 1 CSV (Week 48) → Verify only Week 48 created (no backfill before Nov 17/Week 47)
2. **After Test 1** → Upload Dec 8 CSV (Week 50) → Verify Weeks 49-50 filled, Week 49 backfilled from Dec 8
3. **Empty database** → Upload Dec 8 (Week 50) → Upload Dec 1 (Week 48) → Verify Week 49 has Dec 8 data, Week 48 stops Dec 8 backfill
4. **Upload Dec 15 (Week 51)** → Verify Week 51 created, stops at Week 50 (has Dec 8 data)
5. **Re-upload Dec 8** → Verify Week 50 updated with new subscriber counts, Week 49 remains backfilled

### Verification Commands

```bash
# Check backfill status
docker exec circulation_web php -r "
\$pdo = new PDO('mysql:host=database;dbname=circulation_dashboard', 'circ_dash', 'Barnaby358@Jones!');
\$stmt = \$pdo->query('SELECT week_num, source_date, is_backfilled, backfill_weeks FROM daily_snapshots GROUP BY week_num ORDER BY week_num');
while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo \"Week {\$row['week_num']}: from {\$row['source_date']} \" . (\$row['is_backfilled'] ? \"(backfilled {\$row['backfill_weeks']} weeks)\" : \"(real data)\") . \"\n\";
}
"
```

---

## Troubleshooting

### Issue: Data Not Backfilling

**Check:**
1. Columns exist: `SHOW COLUMNS FROM daily_snapshots LIKE 'source%'`
2. Upload succeeds without errors
3. `source_filename` is populated after upload

### Issue: Wrong Week Ownership

**Solution:**
1. Visit `/admin_audit.php`
2. Check which file owns which week
3. Re-upload with correct filename date

### Issue: Too Much Backfill Warning

**Adjust threshold** in `backfill-indicator.js`
**Or upload actual historical CSVs**

---

## Migration Notes

**From day-of-week system:**
- Removed Monday adjustment
- Removed day-of-week precedence logic
- Added source tracking columns
- Kept ISO week number calculation

**Backward compatibility:**
- Existing data remains unchanged
- New uploads get source tracking
- Old data shows as `source_filename = NULL`

---

## Future Enhancements

**Potential additions:**
- Maximum backfill limit (e.g., "don't backfill more than 8 weeks")
- Email notifications for large backfills
- Automatic suggestions for missing CSVs
- Batch upload with date override
- Data quality scoring based on backfill age

---

**Last Updated:** December 22, 2025 - Added critical clarification about real vs backfilled data replacement
**Authors:** Claude Code
**Version:** 1.1.0
