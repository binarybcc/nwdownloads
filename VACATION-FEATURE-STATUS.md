# Vacation Information Feature - Implementation Status

## Overview
Adding comprehensive vacation tracking and display to the Circulation Dashboard, showing vacation counts and longest vacations with contextual menu options.

## What's Implemented ✅

### UI Components

1. **Key Metrics Vacation Card** (`web/index.php` lines 635-656)
   - Shows total subscribers on vacation count
   - Displays percentage of total
   - Section for "Longest 3 Vacations" (placeholder ready)
   - Right-click context menu enabled

2. **Business Unit Vacation Sections** (`web/assets/app.js` lines 805-817)
   - Shows vacation count per business unit
   - Displays percentage within unit
   - Section for "Longest 3 Vacations per unit" (placeholder ready)
   - Right-click context menu enabled

3. **Vacation Display Module** (`web/assets/vacation-display.js`)
   - `displayLongestVacationsOverall()` - Renders top 3 vacations in Key Metrics
   - `displayLongestVacationsForUnit()` - Renders top 3 per business unit
   - `formatVacationDuration()` - Smart formatting (weeks vs months)
   - Context menu system with options:
     - View Vacation Trends
     - View All On Vacation
     - Export to CSV
   - Individual vacation subscriber menu:
     - View Full Details
     - Vacation History

### Context Menu System
- Right-click on vacation metrics opens contextual menu
- Right-click on individual vacation opens subscriber menu
- Clean, modern UI with hover states
- Auto-closes on outside click

## What's Needed 🚧

### 1. Database Schema for Vacation Dates

**Current state:**
- `subscriber_snapshots.on_vacation` (TINYINT) - Boolean flag only
- No vacation start/end dates

**Needed:**
```sql
ALTER TABLE subscriber_snapshots
ADD COLUMN vacation_start DATE NULL COMMENT 'Vacation start date',
ADD COLUMN vacation_end DATE NULL COMMENT 'Vacation return date';
```

### 2. CSV Import with Vacation Dates

**User will provide:**
- New CSV export from Newzware with vacation date columns
- Need to identify column names for start/end dates

**Update required:**
- `web/upload.php` - Add vacation date extraction
- Map CSV columns to database fields
- Validate date formats

### 3. API Endpoint for Longest Vacations

**New endpoint needed:** `api.php?action=get_longest_vacations`

**Parameters:**
- `snapshot_date` - The current week
- `business_unit` (optional) - Filter by unit, or "overall" for all

**Response format:**
```json
{
  "overall": [
    {
      "sub_num": "12345",
      "subscriber_name": "John Doe",
      "paper_code": "TJ",
      "business_unit": "South Carolina",
      "vacation_start": "2025-10-15",
      "vacation_end": "2025-12-31",
      "weeks_on_vacation": 11
    }
  ],
  "by_unit": {
    "South Carolina": [...],
    "Wyoming": [...],
    "Michigan": [...]
  }
}
```

**Calculation logic:**
```sql
SELECT
    sub_num,
    name as subscriber_name,
    paper_code,
    business_unit,
    vacation_start,
    vacation_end,
    DATEDIFF(vacation_end, vacation_start) / 7 as weeks_on_vacation
FROM subscriber_snapshots
WHERE snapshot_date = ?
  AND on_vacation = 1
  AND vacation_start IS NOT NULL
  AND vacation_end IS NOT NULL
ORDER BY weeks_on_vacation DESC
LIMIT 3
```

### 4. Integration with Existing Code

**In `web/assets/app.js`:**

Add after line 520 (in `renderDashboard()` function):
```javascript
// Load longest vacations
fetch(`api.php?action=get_longest_vacations&snapshot_date=${snapshotDate}`)
    .then(response => response.json())
    .then(data => {
        // Display overall longest vacations
        displayLongestVacationsOverall(data.overall);

        // Display per-unit longest vacations
        Object.entries(data.by_unit).forEach(([unit, vacations]) => {
            displayLongestVacationsForUnit(unit, vacations);
        });
    })
    .catch(error => {
        console.error('Error loading vacation data:', error);
    });
```

### 5. Implement Context Menu Actions

**Currently stubbed functions in `vacation-display.js`:**
- `showVacationTrends()` - Open trend modal with vacation history chart
- `showVacationSubscriberList()` - Open modal with full list of subscribers on vacation
- `exportVacationList()` - Generate CSV export
- `showVacationDetails()` - Open subscriber detail modal
- `showVacationHistory()` - Show vacation history for one subscriber

## Testing Plan

1. **With sample data:**
   - Add vacation dates to database manually
   - Test display of longest vacations
   - Verify context menus work
   - Check formatting (1 week, 2 weeks, 1mo 2wk, etc.)

2. **With real CSV:**
   - Import CSV with vacation dates
   - Verify dates are extracted correctly
   - Confirm longest vacations are calculated correctly
   - Test across all business units

3. **Edge cases:**
   - No vacations (show "No active vacations")
   - Only 1-2 vacations (show fewer than 3)
   - Very long vacations (6+ months)
   - Vacation ending this week
   - Multiple subscribers with same vacation length

## Files Modified

- `web/index.php` - Added vacation display sections
- `web/assets/app.js` - Added vacation sections to business unit cards
- `web/assets/vacation-display.js` - New file (vacation display logic)

## Files To Modify

- `web/api.php` - Add `get_longest_vacations` endpoint
- `web/upload.php` - Extract vacation dates from CSV
- `sql/` - Migration for vacation date columns (optional, can use ALTER TABLE)

## PR Checklist

- [x] UI framework for vacation display
- [x] Context menu system
- [x] Display functions ready
- [ ] Database schema updated with vacation dates
- [ ] API endpoint implemented
- [ ] CSV import handles vacation dates
- [ ] Integration code added to app.js
- [ ] Context menu actions implemented
- [ ] Tested with real data
- [ ] Documentation updated

## Next Steps

1. **User provides vacation dates CSV**
   - Identify column names
   - Provide sample row

2. **Add database columns**
   ```bash
   mysql> ALTER TABLE subscriber_snapshots
          ADD COLUMN vacation_start DATE NULL,
          ADD COLUMN vacation_end DATE NULL;
   ```

3. **Implement API endpoint**
   - Add to api.php
   - Test with Postman/curl

4. **Update CSV import**
   - Map vacation date columns
   - Calculate weeks on vacation

5. **Wire up display**
   - Call API after dashboard renders
   - Populate vacation lists

6. **Test & refine**
   - Verify all features work
   - Polish UI/UX
   - Create PR

## Timeline

- Framework (UI + context menus): ✅ Complete
- Database schema: ⏳ Awaiting vacation CSV from user
- API endpoint: ⏳ 30 minutes
- CSV import update: ⏳ 30 minutes
- Integration: ⏳ 15 minutes
- Testing & refinement: ⏳ 1 hour

**Total remaining: ~2.5 hours after receiving vacation dates**
