# Backfill Indicator Integration Guide

## Quick Integration

Add backfill indicators to the dashboard by including the JavaScript module and hooking into the data fetch.

### Step 1: Include the Script

Add this line to `index.php` in the `<head>` section (around line 30):

```html
<!-- Backfill Indicator Module -->
<script src="assets/backfill-indicator.js?v=20251208"></script>
```

### Step 2: Hook Into Data Fetch

Find where the dashboard data is fetched and rendered (search for `fetch('api.php'` or similar).

Add this code after the data is received:

```javascript
// Example integration
fetch('api.php?action=overview&date=' + selectedDate)
    .then(response => response.json())
    .then(data => {
        // Existing dashboard render code...
        renderDashboard(data);

        // NEW: Update backfill indicators
        if (window.backfillIndicator && data.backfill) {
            window.backfillIndicator.update(data.backfill);
        }
    });
```

### Step 3: Test

1. Open dashboard: `http://localhost:8081/`
2. Upload a CSV file that will backfill data
3. Look for:
   - Amber "Backfilled" badge next to header
   - Warning banner if >2 weeks backfilled

### Step 4: Access Admin Audit

Visit: `http://localhost:8081/admin_audit.php`

---

## Alternative: Manual Integration

If you want to add the indicators manually without the JavaScript module:

### Badge HTML (add near week label)

```html
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200"
      title="Data backfilled X weeks from DATE upload">
    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
    </svg>
    Backfilled (X weeks)
</span>
```

### Warning Banner HTML (add at top of main content)

```html
<div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg shadow-sm mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-amber-800">
                ⚠️ Backfilled Data Warning
            </h3>
            <div class="mt-2 text-sm text-amber-700">
                <p>This week's data was backfilled X weeks from a DATE upload.</p>
            </div>
        </div>
    </div>
</div>
```

---

## Testing Checklist

- [ ] Script loads without errors
- [ ] Badge appears when data is backfilled
- [ ] Warning appears when backfill >2 weeks
- [ ] Tooltips show correct information
- [ ] Admin audit page loads and shows data
- [ ] No indicators show when data is real (not backfilled)

---

**Last Updated:** December 8, 2025
