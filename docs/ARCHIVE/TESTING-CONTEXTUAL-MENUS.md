# Testing Guide: Contextual Chart Menus

**Date:** 2025-12-05
**Feature:** Interactive context menus for chart drill-down
**Status:** Ready for Testing

---

## Overview

This document provides step-by-step testing instructions for the new contextual chart menu system that enables users to:
- View historical trends for specific metrics
- Export subscriber lists with detailed contact information
- Drill down into chart data with right-click interactions

---

## Prerequisites

- **Development Environment:** Docker containers running (http://localhost:8081)
- **Production Environment:** Synology NAS deployment (http://192.168.1.254:8081)
- **Browser:** Chrome, Firefox, or Safari (latest versions)
- **Data:** At least one week of circulation data uploaded

---

## Test Scenarios

### Test 1: Basic Context Menu Interaction

**Objective:** Verify context menu appears and responds to user interaction

**Steps:**
1. Open dashboard at http://localhost:8081
2. Click on any business unit card (Wyoming, Michigan, or South Carolina)
3. Wait for detail panel to slide in from right
4. Scroll to "Subscription Expirations (4-Week View)" chart
5. **Right-click** on the "Past Due" bar
6. Context menu should appear with two options:
   - 📈 Show trend over time
   - 👥 View subscribers

**Expected Result:**
- Context menu appears at cursor position
- Menu has white background with shadow
- Icons and labels are clearly visible
- Hover over items shows highlight effect

**Pass/Fail:** ___________

---

### Test 2: Historical Trend View

**Objective:** Verify trend chart displays and animates correctly

**Steps:**
1. Right-click on "Past Due" bar in Expiration chart
2. Select "📈 Show trend over time"
3. Wait for animation to complete

**Expected Result:**
- Bar chart slides LEFT off screen (400ms animation)
- Line chart slides IN from RIGHT (400ms animation)
- Breadcrumb navigation appears:
  `← Back | Expirations › Past Due › Trend`
- Time range selector shows 4 options: 4 Weeks, 12 Weeks, 26 Weeks, 52 Weeks
- 12 Weeks is selected by default
- Line chart shows historical data with blue line
- Hover over data points shows tooltips with count and change

**Pass/Fail:** ___________

---

### Test 3: Back Navigation from Trend

**Objective:** Verify return to original chart view

**Steps:**
1. From trend view (Test 2), click "← Back" button
2. Wait for animation

**Expected Result:**
- Line chart slides RIGHT off screen
- Original bar chart slides IN from LEFT
- Chart returns to exact previous state
- Context menu is still functional (right-click works)

**Pass/Fail:** ___________

---

### Test 4: Subscriber List View

**Objective:** Verify subscriber table panel displays correctly

**Steps:**
1. Right-click on "This Week" bar in Expiration chart
2. Select "👥 View subscribers"
3. Wait for panel to slide in

**Expected Result:**
- Slide-out panel appears from RIGHT (75% width)
- Panel has **TEAL/CYAN color scheme** (different from main blue theme)
- Header shows:
  - Title: "This Week Subscribers - [Business Unit]"
  - Subtitle: "[Count] subscribers • Snapshot: [Date]"
  - Two export buttons: "📗 Export to Excel" and "📊 Export to CSV"
  - Total count badge
- Table displays with:
  - Teal header row
  - Alternating white/light teal rows
  - 12 columns: Account ID, Name, Phone, Email, Address, Paper, Rate, Rate Amount, Last Payment, Payment Method, Expiration, Delivery Type
  - Rows highlight on hover (brighter teal)

**Pass/Fail:** ___________

---

### Test 5: Excel Export from Subscriber Table

**Objective:** Verify formatted Excel export works

**Steps:**
1. From subscriber table panel (Test 4)
2. Click "📗 Export to Excel" button
3. Wait for download

**Expected Result:**
- File downloads immediately: `[BusinessUnit]_[Metric]_[Date]_[Timestamp].xlsx`
- Open file in Excel/Numbers:
  - Header row has **TEAL background** with white bold text
  - Headers are frozen (scroll down, headers stay visible)
  - Auto-filter enabled on headers (dropdown arrows visible)
  - Alternating row colors: white and light teal
  - Columns auto-sized to content
  - Phone numbers preserved as text (no scientific notation)
  - Currency columns formatted with $ symbol

**Pass/Fail:** ___________

---

### Test 6: CSV Export from Subscriber Table

**Objective:** Verify CSV export works

**Steps:**
1. From subscriber table panel
2. Click "📊 Export to CSV" button

**Expected Result:**
- File downloads: `[BusinessUnit]_[Metric]_[Date]_[Timestamp].csv`
- Open in Excel/Numbers:
  - All data visible with proper encoding (no weird characters)
  - Commas in addresses handled correctly (quoted)
  - Opens cleanly without import wizard

**Pass/Fail:** ___________

---

### Test 7: Close Subscriber Panel

**Objective:** Verify panel closes properly

**Steps:**
1. From subscriber table panel
2. Click the white X button in top-right
3. Alternatively: Press ESC key

**Expected Result:**
- Panel slides RIGHT off screen (350ms animation)
- Backdrop fades out
- Detail panel remains open and functional
- Memory is cleaned up (no performance issues)

**Pass/Fail:** ___________

---

### Test 8: Multiple Chart Types

**Objective:** Verify context menus work on all three charts

**Steps:**
1. Test Rate Distribution Chart:
   - Right-click on any rate bar
   - Select "View subscribers"
   - Verify subscriber list shows correct rate filter

2. Test Subscription Length Chart:
   - Right-click on "6-month" bar
   - Select "Show trend over time"
   - Verify trend displays correctly

**Expected Result:**
- Context menus work identically on all three charts
- Data filtering is correct for each chart type
- Animations are smooth and consistent

**Pass/Fail:** ___________

---

### Test 9: API Endpoints (Backend Testing)

**Objective:** Verify API returns correct data

**Steps:**
1. Open browser developer console (F12)
2. Open Network tab
3. Right-click on "Past Due" bar
4. Select "View subscribers"
5. Check Network tab for API call

**Expected Result:**
- Request URL: `api.php?action=get_subscribers&business_unit=...&metric_type=expiration&metric_value=Past%20Due&snapshot_date=...`
- Response status: 200 OK
- Response JSON structure:
  ```json
  {
    "success": true,
    "data": {
      "metric_type": "expiration",
      "metric": "Past Due",
      "count": 45,
      "snapshot_date": "2025-12-05",
      "business_unit": "Wyoming",
      "subscribers": [...]
    }
  }
  ```
- Subscribers array has 12 fields per record

**Pass/Fail:** ___________

---

### Test 10: Error Handling

**Objective:** Verify graceful error handling

**Steps:**
1. Disconnect from network (airplane mode)
2. Try to open subscriber list
3. Reconnect
4. Try again

**Expected Result:**
- Shows user-friendly error message: "Failed to load subscriber data. Please try again."
- Console logs error details (for debugging)
- Can retry after reconnecting
- No JavaScript crashes or blank screens

**Pass/Fail:** ___________

---

## Cross-Browser Testing

Test the following scenarios in each browser:

| Feature | Chrome | Firefox | Safari |
|---------|--------|---------|--------|
| Context menu appears | ☐ | ☐ | ☐ |
| Trend animation smooth | ☐ | ☐ | ☐ |
| Subscriber panel opens | ☐ | ☐ | ☐ |
| Excel export works | ☐ | ☐ | ☐ |
| CSV export works | ☐ | ☐ | ☐ |
| ESC key closes panel | ☐ | ☐ | ☐ |
| Right-click prevented (no browser menu) | ☐ | ☐ | ☐ |

---

## Performance Testing

**Objective:** Verify no performance degradation

**Metrics to Check:**
- Dashboard initial load time: < 2 seconds
- Detail panel open time: < 1 second
- Context menu response time: < 200ms
- Subscriber list load time: < 3 seconds (for 1000 records)
- Excel export time: < 2 seconds (for 1000 records)

**Tools:**
- Browser DevTools Performance tab
- Network tab (check payload sizes)

**Pass/Fail:** ___________

---

## Known Limitations (Phase 1)

These are intentional limitations that will be addressed in Phase 2:

1. **Mock Data:** Subscriber contact information is generated (not real Newzware data)
2. **Limited to 1000 records:** Large subscriber lists are capped for performance
3. **No real-time filtering:** Table doesn't have search/filter functionality yet
4. **Fixed time ranges:** Trend charts use preset time ranges (no custom date picker)
5. **Chart-specific actions disabled:** Future options (like "Compare to last year") show as disabled

---

## Bug Reporting Template

If you find issues, report them with this format:

```
**Bug Title:** [Short description]

**Severity:** [Critical | High | Medium | Low]

**Steps to Reproduce:**
1. [First step]
2. [Second step]
3. [etc.]

**Expected Behavior:**
[What should happen]

**Actual Behavior:**
[What actually happened]

**Browser/Environment:**
- Browser: [Chrome 120, Firefox 121, etc.]
- Environment: [Development | Production]
- Date/Time: [When it occurred]

**Screenshots/Console Errors:**
[Attach if available]
```

---

## Testing Checklist Summary

- [ ] Test 1: Basic Context Menu Interaction
- [ ] Test 2: Historical Trend View
- [ ] Test 3: Back Navigation from Trend
- [ ] Test 4: Subscriber List View
- [ ] Test 5: Excel Export
- [ ] Test 6: CSV Export
- [ ] Test 7: Close Subscriber Panel
- [ ] Test 8: Multiple Chart Types
- [ ] Test 9: API Endpoints
- [ ] Test 10: Error Handling
- [ ] Cross-Browser Testing (Chrome, Firefox, Safari)
- [ ] Performance Testing

---

## Approval Sign-off

**Tested By:** ___________________
**Date:** ___________________
**Status:** [ ] Approved for Production  [ ] Needs Fixes

**Notes:**
_______________________________________________
_______________________________________________
_______________________________________________

---

**Next Steps After Testing:**
1. Address any critical/high severity bugs
2. Deploy to Production (Synology NAS)
3. User training/documentation
4. Monitor for issues in first week
5. Plan Phase 2 enhancements
