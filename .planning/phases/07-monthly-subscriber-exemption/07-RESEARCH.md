# Phase 7: Monthly Subscriber Exemption - Research

**Researched:** 2026-03-20
**Domain:** PHP/SQL conditional logic, JavaScript UI rendering, XLSX export styling
**Confidence:** HIGH

## Summary

Phase 7 adds conditional logic to distinguish monthly subscribers (last payment between -$0.01 and -$25.00) from annual subscribers. The core change is an `is_monthly` boolean flag computed server-side via SQL CASE expression, then consumed by three frontend systems: table row rendering, sort ordering, and XLSX export coloring.

This is a pure logic-branching task with no new libraries, no schema changes, and no new UI components. All changes are conditional wrappers around existing code paths in three files: `legacy.php` (SQL + API), `subscriber-table-panel.js` (UI rendering + sorting), and `export-utils.js` (XLSX fills). The risk is low because the change is additive -- existing behavior is preserved for non-monthly subscribers.

**Primary recommendation:** Implement server-side `is_monthly` flag first, then modify the three frontend consumption points (rendering, sorting, export) in sequence.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Monthly subscribers with NO call activity: plain row, no colored left border, no phone icon
- Monthly subscribers WITH call activity: same treatment as annual subs (green/orange borders and icons)
- No badge, label, or extra indicator -- absence of call status styling IS the distinction
- No phone icon when monthly sub has no call activity (MONTH-02)
- Standard phone icon with appropriate color when call activity exists
- Monthly subs with no call activity in XLSX: no row fill color (plain white cells)
- Monthly subs WITH call activity in XLSX: same status coloring as annual subs
- No additional "Subscriber Type" column in export
- Monthly subs sort as lowest priority group (below all annual subs in default ascending sort)
- Within monthly group: sorted by expiration date ascending
- Sort toggle reverses monthly position (ascending = bottom, descending = top)
- Server-side: PHP API returns `is_monthly` boolean flag on each subscriber object
- SQL CASE expression: `WHEN last_payment_amount BETWEEN -25.00 AND -0.01 THEN 1 ELSE 0`
- Threshold constants: `MONTHLY_PAYMENT_MIN = -25.00`, `MONTHLY_PAYMENT_MAX = -0.01`
- Added to SELECT clause in each bucket query following existing `$callLogSubquery` reuse pattern
- Frontend checks `sub.is_monthly` -- single source of truth in PHP

### Claude's Discretion
- Exact PHP constant placement (top of legacy.php vs separate constants file)
- How to structure the reusable `is_monthly` SQL fragment across 8 bucket queries
- Sort priority number assignment for monthly group in JS `getStatusSortPriority()`
- Edge cases around null/zero payment amounts

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MONTH-01 | Monthly subscribers display with normal row styling (no red call status color) | `buildTableHTML()` row rendering loop at line 424-474 checks `getCallStatusColor()` -- add `is_monthly` guard to skip border and icon when no call activity |
| MONTH-02 | Monthly subscribers show no phone icon unless call log activity exists | Same `buildTableHTML()` loop -- conditionally omit the phone icon span when `sub.is_monthly && !sub.call_status` |
| MONTH-03 | Monthly subscribers sort to bottom in default sort order | `sortSubscribers()` at line 80-88 and `getStatusSortPriority()` at line 69-73 -- add monthly-aware priority level (e.g., -1) that sorts below all call status priorities |
| MONTH-04 | Monthly subscribers export with no row coloring unless call activity exists | `getExportStatusFill()` at line 24-28 -- add `is_monthly` check to return empty fill `{}` when no call activity |
</phase_requirements>

## Standard Stack

No new libraries needed. All changes use existing infrastructure.

### Core (Existing)
| Library | Version | Purpose | Role in Phase 7 |
|---------|---------|---------|-----------------|
| PHP | 8.2 | Server-side API | Compute `is_monthly` flag in SQL, return in JSON |
| MariaDB | 10 | Database | CASE expression on `last_payment_amount` |
| SheetJS (xlsx-js-style) | CDN | XLSX export | Conditional fill colors via `getExportStatusFill()` |

### No New Dependencies
This phase requires zero new packages. All changes are conditional logic added to existing functions.

## Architecture Patterns

### Pattern 1: Server-Side Flag with Client Consumption
**What:** Compute `is_monthly` as a SQL CASE expression, return it as a boolean in JSON, consume it in JS.
**When to use:** When business logic (payment threshold) should not be duplicated across backend and frontend.
**Why:** Single source of truth. If the threshold changes, only one place to update (PHP constants).

```php
// In legacy.php - reusable SQL fragment (like $callLogSubquery pattern)
$isMonthlyExpression = "
    CASE
        WHEN ss.last_payment_amount BETWEEN " . MONTHLY_PAYMENT_MIN . " AND " . MONTHLY_PAYMENT_MAX . " THEN 1
        ELSE 0
    END as is_monthly
";
```

### Pattern 2: Conditional Rendering Guard
**What:** In `buildTableHTML()`, wrap border/icon rendering in an `is_monthly` check.
**When to use:** When existing rendering must be selectively suppressed.

```javascript
// Pseudocode for row rendering
const isMonthlyNoActivity = sub.is_monthly && !sub.call_status;
const borderStyle = isMonthlyNoActivity ? 'none' : `4px solid ${statusColor.border}`;
const showIcon = !isMonthlyNoActivity;
```

### Pattern 3: Sort Priority Extension
**What:** Extend `getStatusSortPriority()` to return a value that places monthly-no-activity subscribers at the bottom (or top when reversed).
**When to use:** When adding a new sort group to an existing priority system.

```javascript
// Current: 0=no contact, 1=received/missed, 2=placed
// Extended: -1=monthly-no-activity (sorts below 0 in ascending)
getStatusSortPriority(callStatus, isMonthly) {
    if (isMonthly && !callStatus) return -1;
    if (!callStatus) return 0;
    if (callStatus === 'placed') return 2;
    return 1;
}
```

### Anti-Patterns to Avoid
- **Duplicating threshold logic in JS:** Never compute `is_monthly` client-side from payment amount. The PHP API is the single source of truth.
- **Adding a visual "Monthly" badge/label:** The user explicitly decided against this. The absence of call status styling IS the distinction.
- **Modifying the SQL WHERE clause:** Monthly subscribers should still appear in results. Only their rendering changes.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Monthly detection | Client-side payment parsing | Server-side `is_monthly` flag | Single source of truth, threshold may change |
| Sort group separation | Custom sort algorithm | Extend existing priority number system | Consistent with Phase 6 pattern |

## Common Pitfalls

### Pitfall 1: SQL Fragment Repetition Across 8 Bucket Queries
**What goes wrong:** Copy-pasting `is_monthly` CASE into 8 separate SELECT statements creates maintenance burden.
**Why it happens:** Each bucket (Past Due, This Week, Next Week, Week +2 through +6) has its own query.
**How to avoid:** Define `$isMonthlyExpression` as a PHP string variable (same pattern as `$callLogSubquery` on line 1440-1448) and interpolate it into each query's SELECT clause.
**Warning signs:** If you find yourself editing the CASE expression in more than one place.

### Pitfall 2: Forgetting the Sort Order SQL
**What goes wrong:** The SQL ORDER BY in each bucket query also uses call status priority. Monthly subscribers with no activity currently get `WHEN cl.call_direction IS NULL THEN 0` which puts them at the top in ascending order -- the opposite of what's wanted.
**Why it happens:** The server-side sort and client-side sort both need updating.
**How to avoid:** Add `is_monthly` awareness to the SQL ORDER BY CASE as well, OR rely entirely on client-side sorting (the JS `sortSubscribers()` already re-sorts). Given the client already sorts, the simpler approach is to let the SQL return data in any order and let JS handle sort priority.
**Recommendation:** Keep SQL ORDER BY as-is (it's a reasonable default), and let the JS `sortSubscribers()` handle the monthly-aware priority. The JS sort runs on every render and on toggle, so it's the authoritative sort.

### Pitfall 3: NULL and Zero Payment Amounts
**What goes wrong:** `last_payment_amount` could be NULL or 0.00, which should NOT match as monthly.
**Why it happens:** Missing data, free subscriptions, or data import issues.
**How to avoid:** The BETWEEN -25.00 AND -0.01 range naturally excludes NULL (SQL BETWEEN with NULL returns NULL/false) and excludes 0.00 (outside the range). No special handling needed, but document this edge case.

### Pitfall 4: Export Data Pipeline
**What goes wrong:** `is_monthly` flag doesn't make it to `getExportStatusFill()`.
**Why it happens:** The export pipeline goes: `handleExportExcel()` -> `exportSubscriberList()` -> `exportToExcel()` which passes raw `subscribers` array as `options.subscribers`. The `getExportStatusFill(sub)` receives the original subscriber object, so `sub.is_monthly` will be available IF the API returns it.
**How to avoid:** Verify the data flow: API returns `is_monthly` -> JS stores in subscriber array -> export passes raw subscribers -> `getExportStatusFill()` can check `sub.is_monthly`.

### Pitfall 5: Border Style Removal
**What goes wrong:** Setting `border-left: none` may cause a visual shift (4px difference) compared to rows with borders.
**Why it happens:** The 4px left border on non-monthly rows takes up space.
**How to avoid:** Use `border-left: 4px solid transparent` instead of removing the border entirely. This preserves alignment.

## Code Examples

### PHP: Constants and SQL Fragment (legacy.php)

```php
// Constants at top of getExpirationSubscribers() or at file/function scope
const MONTHLY_PAYMENT_MIN = -25.00;
const MONTHLY_PAYMENT_MAX = -0.01;

// Reusable SQL fragment (same pattern as $callLogSubquery)
$isMonthlyCase = "
    CASE
        WHEN ss.last_payment_amount BETWEEN " . MONTHLY_PAYMENT_MIN . " AND " . MONTHLY_PAYMENT_MAX . " THEN 1
        ELSE 0
    END as is_monthly
";

// Add to each bucket query's SELECT clause after call_agent:
// cl.source_group as call_agent,
// {$isMonthlyCase}
```

### JS: Modified getCallStatusColor (subscriber-table-panel.js)

```javascript
// Option A: Modify getCallStatusColor to accept isMonthly
getCallStatusColor(callStatus, isMonthly = false) {
    if (isMonthly && !callStatus) return { border: 'transparent', iconBg: 'transparent', label: 'none', hideIcon: true };
    if (!callStatus) return { border: '#EF4444', iconBg: '#EF444420', label: 'red' };
    if (callStatus === 'placed') return { border: '#22C55E', iconBg: '#22C55E20', label: 'green' };
    return { border: '#F59E0B', iconBg: '#F59E0B20', label: 'orange' };
}
```

### JS: Modified sortSubscribers (subscriber-table-panel.js)

```javascript
getStatusSortPriority(callStatus, isMonthly = false) {
    if (isMonthly && !callStatus) return -1; // Below all annual subs
    if (!callStatus) return 0;
    if (callStatus === 'placed') return 2;
    return 1;
}

sortSubscribers(subscribers) {
    return [...subscribers].sort((a, b) => {
        const priorityA = this.getStatusSortPriority(a.call_status, a.is_monthly);
        const priorityB = this.getStatusSortPriority(b.call_status, b.is_monthly);
        const priorityDiff = priorityA - priorityB;
        const statusSort = this.sortAscending ? priorityDiff : -priorityDiff;
        if (statusSort !== 0) return statusSort;
        return new Date(a.expiration_date) - new Date(b.expiration_date);
    });
}
```

### JS: Modified getExportStatusFill (export-utils.js)

```javascript
function getExportStatusFill(sub) {
    if (!sub) return {};
    // Monthly with no call activity: no fill
    if (sub.is_monthly && !sub.call_status) return {};
    // Standard logic
    if (!sub.call_status) return { fgColor: { rgb: 'FEE2E2' } }; // red
    if (sub.call_status === 'placed') return { fgColor: { rgb: 'DCFCE7' } }; // green
    return { fgColor: { rgb: 'FEF9C3' } }; // yellow
}
```

### JS: Modified buildTableHTML Row (subscriber-table-panel.js)

```javascript
// Inside the forEach loop:
const isMonthlyNoActivity = sub.is_monthly && !sub.call_status;
const statusColor = this.getCallStatusColor(sub.call_status, sub.is_monthly);
const tooltipText = isMonthlyNoActivity ? '' : this.escapeHtml(this.buildCallTooltip(sub));
const borderStyle = isMonthlyNoActivity ? '4px solid transparent' : `4px solid ${statusColor.border}`;

// In the <tr> tag:
// border-left: ${borderStyle}

// In the status <td>:
// Conditionally render the phone icon span only when !isMonthlyNoActivity
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| All subscribers get red "no contact" indicator | Monthly subs exempted from urgency styling | Phase 7 (this phase) | Reduces false urgency for subscribers who don't need retention calls |

## Integration Point Summary

| File | Line(s) | What Changes | Scope |
|------|---------|-------------|-------|
| `web/api/legacy.php` | ~1440 (constants), 1459-1489 + 7 more bucket queries | Add `$isMonthlyCase` variable, add to each SELECT | 8 bucket queries + 1 variable |
| `web/assets/js/components/subscriber-table-panel.js` | 43-47, 69-73, 80-88, 424-474 | Modify `getCallStatusColor()`, `getStatusSortPriority()`, `sortSubscribers()`, `buildTableHTML()` | 4 methods |
| `web/assets/js/utils/export-utils.js` | 24-28 | Modify `getExportStatusFill()` | 1 function |

**Total touch points:** 3 files, ~13 edit locations (8 SQL queries + 5 JS functions/methods).

## Open Questions

1. **Exact constant placement in PHP**
   - What we know: PHP `define()` or `const` at function scope vs file scope
   - Recommendation: Define as variables at the top of `getExpirationSubscribers()` function since they're only used there. Keeps scope tight. `$monthlyPaymentMin = -25.00; $monthlyPaymentMax = -0.01;`

2. **SQL fragment reuse pattern**
   - What we know: `$callLogSubquery` is defined once and interpolated into 8 queries. Same pattern works for `$isMonthlyCase`.
   - Recommendation: Define `$isMonthlyCase` right after `$callLogSubquery` (line ~1448), interpolate into each SELECT clause.

3. **Sort priority number for monthly**
   - What we know: Current priorities are 0, 1, 2. Need monthly-no-activity below all.
   - Recommendation: Use -1 for monthly-no-activity. The sort comparison `priorityA - priorityB` handles negative values correctly. In ascending sort, -1 sorts after 2 because the sort is `priorityDiff` which means lower numbers = higher urgency = top of list. Wait -- re-examining: ascending sort means 0 (no contact/red) at top, 2 (placed/green) at bottom. So -1 would sort ABOVE 0, not below.
   - **Correction:** Need a value HIGHER than 2, like 3, so monthly-no-activity sorts BELOW placed (green) in ascending order. In ascending: 0 (red) -> 1 (orange) -> 2 (green) -> 3 (monthly). In descending: 3 (monthly) -> 2 (green) -> 1 (orange) -> 0 (red).
   - **Final recommendation:** Use priority 3 for monthly-no-activity subscribers.

## Sources

### Primary (HIGH confidence)
- Direct code inspection of `subscriber-table-panel.js` (lines 43-484) -- rendering, sorting, status color logic
- Direct code inspection of `export-utils.js` (lines 24-28, 293-343) -- export fill and data pipeline
- Direct code inspection of `legacy.php` (lines 1437-1679+) -- SQL queries, `$callLogSubquery` pattern, 8 bucket cases
- CONTEXT.md -- locked user decisions on all behavior

### Secondary (MEDIUM confidence)
- None needed -- all research based on direct code inspection

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new dependencies, all existing code
- Architecture: HIGH - direct code inspection of all integration points
- Pitfalls: HIGH - identified from reading actual code paths and data flow

**Research date:** 2026-03-20
**Valid until:** No expiry -- based on project source code, not external dependencies
