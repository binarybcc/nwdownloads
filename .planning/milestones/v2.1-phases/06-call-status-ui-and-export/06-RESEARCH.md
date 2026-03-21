# Phase 6: Call Status UI and Export - Research

**Researched:** 2026-03-20
**Domain:** Frontend table rendering, SQL JOINs, SheetJS XLSX export styling
**Confidence:** HIGH

## Summary

Phase 6 wires existing call_logs data (Phase 3-4) into the expiration subscriber table UI and XLSX export. The work spans three layers: (1) a PHP SQL modification to LEFT JOIN call_logs onto subscriber queries, (2) JavaScript table rendering changes to add a Status column with colored icons and sortable headers, and (3) XLSX export modifications to add call status columns with row-level fill colors.

The codebase is well-prepared for this phase. Both `subscriber_snapshots.phone_normalized` and `call_logs.phone_normalized` are indexed CHAR(10) columns with matching normalization logic. The existing `export-utils.js` already uses SheetJS `.s.fill` for cell styling (alternating teal rows), confirming the CDN-served xlsx v0.20.3 supports the fill styling needed for status-colored rows. The `SubscriberTablePanel` class uses inline styles throughout (no CSS framework for the table itself), so the new Status column and left-border styling follow the same inline pattern.

**Primary recommendation:** Modify `getExpirationSubscribers()` in legacy.php to LEFT JOIN call_logs with a correlated subquery for the most recent call per subscriber, then extend `SubscriberTablePanel.buildTableHTML()` with the Status column and `exportToExcel()` with status-based row fills.

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

- Green = staff placed a call TO the subscriber (outbound effort) within last 30 days
- Yellow-Orange = subscriber called US (inbound -- received or missed) within last 30 days
- Red = no call record at all in the 30-day lookback window
- Direction-based, not outcome-based -- what matters is who initiated the call
- When multiple calls exist in the 30-day window, the MOST RECENT call determines the color (not "best status wins")
- Retention stays at 90-day per CALL-F05 (future phase) -- 30-day lookback is a separate display concern
- Subtle 4px colored left border on each row (green/yellow-orange/red) -- visible at a glance
- Row background stays white/alternating (existing teal pattern preserved)
- Does NOT use full row tint in the browser -- keeps existing table aesthetic clean
- Unicode phone emoji colored via CSS background circle or span styling
- Column position: FIRST column (leftmost, before Account ID)
- Column header labeled "Status"
- Tooltip hover shows last call only: direction (Placed/Received/Missed), date/time, staff initials (BC/CW)
- Format: "Placed . Mar 18, 2:15 PM . BC"
- No contact shows: "No contact recorded"
- Default sort: not-contacted first (red on top), then yellow-orange, then green
- Sub-sort within each status group: expiration date soonest first
- No filter toggles -- sorting is sufficient
- Status column header is clickable to toggle sort direction
- XLSX: Three new columns at end: "Call Status", "Last Contact", "Agent"
- XLSX: Entire row gets light fill tint (#DCFCE7 green, #FEF9C3 yellow, #FEE2E2 red)
- XLSX: Row fill replaces existing alternating teal pattern
- XLSX: "Call data as of [timestamp]" as merged header row (Row 1)
- XLSX: Export sort order matches browser display
- Same sync timestamp shown in browser table header area

### Claude's Discretion

- Exact CSS implementation for the colored left border (border-left vs pseudo-element)
- How to color the Unicode phone emoji (CSS filter, colored span wrapper, or background circle approach)
- Exact tooltip positioning and animation
- SQL query optimization for the LEFT JOIN on call_logs
- How to determine the "last sync" timestamp (MAX(call_timestamp) from call_logs, or a separate metadata record)
- Sort arrow styling and toggle interaction details

### Deferred Ideas (OUT OF SCOPE)

- CALL-F05: 90-day retention policy with automatic pruning -- future phase
- Status filter toggles (dropdown or checkbox to show only not-contacted) -- not needed for MVP
- Full staff name display instead of initials -- future enhancement
- Call history tooltip showing last 3 calls -- future enhancement

</user_constraints>

<phase_requirements>

## Phase Requirements

| ID    | Description                                                                                                           | Research Support                                                                                               |
| ----- | --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| UI-01 | Expiration subscriber table shows phone icon column with call status (green=contacted, orange=missed, red=no contact) | SQL LEFT JOIN on call_logs returns call_direction + call_timestamp; JS renders colored icon in Status column   |
| UI-02 | Row background color matches call status for visual scanning                                                          | 4px colored left border on each `<tr>` via inline `border-left` style (user decision: NOT full row tint)       |
| UI-03 | Call status column is sortable (not-contacted first by default)                                                       | JS sort function with priority mapping: red=0, orange=1, green=2; sub-sort by expiration_date                  |
| UI-04 | Tooltip on phone icon shows last contact date and type                                                                | CSS/JS tooltip on hover showing "Direction . Date . Agent" format                                              |
| UI-05 | XLSX export preserves row coloring and call status data                                                               | SheetJS `.s.fill` with fgColor per row based on status; 3 new columns added to formatSubscriberDataForExport() |
| UI-06 | Subscriber list and XLSX export show timestamp of last call log sync                                                  | `MAX(call_timestamp)` query; displayed above table and as merged Row 1 in XLSX                                 |

</phase_requirements>

## Standard Stack

### Core (already in project)

| Library        | Version    | Purpose                                | Why Standard                                                                |
| -------------- | ---------- | -------------------------------------- | --------------------------------------------------------------------------- |
| SheetJS (xlsx) | 0.20.3     | XLSX export with cell styling          | Already loaded via cdn.sheetjs.com/xlsx-latest; `.s.fill` confirmed working |
| Chart.js       | (existing) | Expiration chart rendering             | Already used for bar charts; no changes needed                              |
| PHP 8.2 + PDO  | 8.2        | SQL queries with prepared statements   | Already used throughout legacy.php                                          |
| MariaDB 10     | 10.x       | Database with indexed phone_normalized | call_logs and subscriber_snapshots tables ready                             |

### No New Libraries Needed

This phase extends existing code only. No new dependencies required.

## Architecture Patterns

### Modified Files

```
web/api/legacy.php                                    # LEFT JOIN call_logs in getExpirationSubscribers()
web/assets/js/components/subscriber-table-panel.js    # Status column, left border, tooltip, sorting
web/assets/js/utils/export-utils.js                   # Status-based row fills, header timestamp row, 3 new columns
```

### Pattern 1: SQL LEFT JOIN with Correlated Subquery

**What:** Get the most recent call within 30 days for each subscriber by phone_normalized
**When to use:** Every call to getExpirationSubscribers()
**Recommended approach:**

```sql
SELECT
    ss.sub_num as account_id,
    ss.name as subscriber_name,
    ss.phone,
    ss.email,
    CONCAT(COALESCE(ss.address, ''), ', ', COALESCE(ss.city_state_postal, '')) as mailing_address,
    ss.paper_code,
    ss.paper_name,
    ss.rate_name as current_rate,
    ss.last_payment_amount as rate_amount,
    ss.last_payment_amount,
    ss.payment_status as payment_method,
    ss.paid_thru as expiration_date,
    ss.delivery_type,
    cl.call_direction as call_status,
    cl.call_timestamp as last_call_datetime,
    cl.source_group as call_agent
FROM subscriber_snapshots ss
LEFT JOIN (
    SELECT phone_normalized, call_direction, call_timestamp, source_group,
           ROW_NUMBER() OVER (PARTITION BY phone_normalized ORDER BY call_timestamp DESC) as rn
    FROM call_logs
    WHERE call_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND phone_normalized IS NOT NULL
) cl ON cl.phone_normalized = ss.phone_normalized AND cl.rn = 1
WHERE ss.business_unit = :business_unit
AND ss.snapshot_date = :snapshot_date
AND ss.paid_thru < :snapshot_date
ORDER BY
    CASE
        WHEN cl.call_direction IS NULL THEN 0
        WHEN cl.call_direction IN ('received', 'missed') THEN 1
        WHEN cl.call_direction = 'placed' THEN 2
    END ASC,
    ss.paid_thru ASC
LIMIT 1000
```

**Why ROW_NUMBER() subquery:** MariaDB 10.x supports window functions. A correlated subquery with LIMIT 1 would run per-row; the window function approach materializes once and JOINs efficiently. Both `phone_normalized` columns are indexed (CHAR(10), idx_phone_normalized).

**Alternative if ROW_NUMBER() is unavailable:** Use a derived table with GROUP BY + MAX(call_timestamp), then self-join back to get the full row.

### Pattern 2: Status Column Rendering in buildTableHTML()

**What:** Insert "Status" as first column with colored phone icon and left-border on each row
**Existing pattern:** Headers array at line 229, row rendering at line 279-311

```javascript
// Status color mapping
function getCallStatusColor(callStatus) {
  if (!callStatus) return { border: '#EF4444', icon: '#EF4444', label: 'red' }; // Red - no contact
  if (callStatus === 'placed') return { border: '#22C55E', icon: '#22C55E', label: 'green' }; // Green - outbound
  return { border: '#F59E0B', icon: '#F59E0B', label: 'orange' }; // Orange - received/missed (inbound)
}

// Phone icon with colored circle background
// Recommendation: Use a span with background-color circle wrapping the emoji
`<td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; text-align: center;">
    <span style="
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: ${statusColor.icon}20;
        cursor: help;
    " title="${tooltipText}">
        <span style="filter: none; font-size: 14px;">&#x1F4DE;</span>
    </span>
</td>`
// Left border on row
`<tr style="background: ${bgColor}; border-left: 4px solid ${statusColor.border}; transition: background 150ms;">`;
```

### Pattern 3: Client-Side Sorting with Status Priority

**What:** Sort subscribers by call status (red first), sub-sort by expiration date
**Approach:** Sort the `subscribers` array in JS before rendering, with a toggle state

```javascript
// Sort priority: red=0 (no contact), orange=1 (inbound), green=2 (placed)
function getStatusSortPriority(sub) {
  if (!sub.call_status) return 0; // red
  if (sub.call_status === 'placed') return 2; // green
  return 1; // orange (received or missed)
}

// Sort function
subscribers.sort((a, b) => {
  const statusDiff = getStatusSortPriority(a) - getStatusSortPriority(b);
  if (statusDiff !== 0) return sortAscending ? statusDiff : -statusDiff;
  // Sub-sort by expiration date (soonest first always)
  return new Date(a.expiration_date) - new Date(b.expiration_date);
});
```

### Pattern 4: XLSX Export with Status Row Fills

**What:** Replace alternating teal with status-based fill colors, add merged header row
**Existing pattern:** `exportToExcel()` iterates cells for styling at lines 59-77

```javascript
// Status fill colors (hex without #)
const STATUS_FILLS = {
  green: { fgColor: { rgb: 'DCFCE7' } }, // #DCFCE7
  orange: { fgColor: { rgb: 'FEF9C3' } }, // #FEF9C3
  red: { fgColor: { rgb: 'FEE2E2' } }, // #FEE2E2
};

// Merged header row approach:
// 1. Build data array with header timestamp as first row
// 2. Use XLSX.utils.aoa_to_sheet() instead of json_to_sheet() for more control
// 3. Or: use json_to_sheet() then insert row via sheet_add_aoa() and adjust range
```

### Pattern 5: Sync Timestamp

**What:** Show when call logs were last synced
**Recommended approach:** `MAX(call_timestamp) FROM call_logs` as a simple, reliable method

```sql
SELECT MAX(call_timestamp) as last_sync FROM call_logs
```

Return this alongside the subscriber data in the API response. No separate metadata table needed -- the most recent call_timestamp IS the last sync indicator.

### Anti-Patterns to Avoid

- **Full row background tint in browser:** User explicitly decided against this. Use 4px left border only.
- **"Best status wins" logic:** When multiple calls exist, use MOST RECENT call only (ORDER BY call_timestamp DESC LIMIT 1 via ROW_NUMBER).
- **Sorting in SQL for all entry points:** The expiration chart context menu and revenue intelligence both open the subscriber table. SQL sorting handles the default case, but JS sorting is needed for the toggle.
- **Modifying the subscriber_snapshots table:** No schema changes needed. Call data comes from LEFT JOIN only.

## Don't Hand-Roll

| Problem               | Don't Build                | Use Instead                                        | Why                                                                                        |
| --------------------- | -------------------------- | -------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Phone number matching | Custom string comparison   | Indexed `phone_normalized` CHAR(10) JOIN           | Both tables already have normalized, indexed columns                                       |
| XLSX cell styling     | Server-side PhpSpreadsheet | SheetJS `.s.fill` client-side                      | Already proven working in export-utils.js                                                  |
| Tooltip rendering     | Custom tooltip library     | Native `title` attribute or simple CSS tooltip     | Staff use desktop browsers; native title is sufficient for "Placed . Mar 18, 2:15 PM . BC" |
| Date formatting       | Custom date parser         | JS `toLocaleDateString()` / `toLocaleTimeString()` | Standard browser API handles "Mar 18, 2:15 PM" format                                      |

## Common Pitfalls

### Pitfall 1: Multiple Subscribers Sharing Same Phone Number

**What goes wrong:** Two subscribers with the same phone_normalized both get call status from the same call_logs row. This is actually correct behavior (the phone was called), but could create duplicate join rows if not handled.
**Why it happens:** Family members on the same phone number.
**How to avoid:** The ROW_NUMBER() subquery partitions by phone_normalized, so the JOIN returns one call_logs row per phone number. Multiple subscribers sharing that phone get the same status -- which is correct.
**Warning signs:** None -- this is expected behavior.

### Pitfall 2: NULL phone_normalized Causing Missed JOINs

**What goes wrong:** Subscribers with no phone or non-10-digit phones have `phone_normalized = NULL`. LEFT JOIN with NULL never matches.
**Why it happens:** ~0.05% of subscriber_snapshots have NULL phone_normalized (per Phase 3 verification: 94,943/94,991).
**How to avoid:** These subscribers correctly show as "red" (no contact) because the LEFT JOIN returns NULL for call columns. No special handling needed -- the NULL case IS the "no contact" case.
**Warning signs:** If a subscriber has a valid phone but shows red, check phone_normalized is populated.

### Pitfall 3: SheetJS Merged Cell Handling for Header Row

**What goes wrong:** Inserting a "Call data as of..." merged header row shifts all cell references, breaking styling, autofilter, and frozen headers.
**Why it happens:** `XLSX.utils.json_to_sheet()` assumes row 0 is headers. Adding a row above requires adjusting the entire range.
**How to avoid:** Use `XLSX.utils.sheet_add_aoa()` to prepend the timestamp row, then update `ws['!ref']`, `ws['!merges']`, `ws['!autofilter']`, and `ws['!freeze']` to account for the offset. The merge needs `{ s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } }`.
**Warning signs:** Column headers appearing in row 1 instead of row 2, or autofilter on wrong row.

### Pitfall 4: Sort State Not Persisting Across Re-renders

**What goes wrong:** Clicking the sort toggle works, but opening a different expiration bucket resets the sort.
**Why it happens:** `SubscriberTablePanel` creates a new instance each time via `new SubscriberTablePanel()`.
**How to avoid:** Store sort state on the instance, apply default sort (not-contacted first) on initial render. The sort toggle only needs to persist within one panel session.
**Warning signs:** Sort arrow indicator not matching actual sort order.

### Pitfall 5: XLSX Row Color Applied to Wrong Rows Due to Header Offset

**What goes wrong:** With the merged timestamp row, data starts at row 3 (0-indexed: row 2). The styling loop must account for this offset.
**Why it happens:** Original `exportToExcel()` assumes data starts at row 1 (after headers at row 0). With the timestamp row, headers are at row 1 and data at row 2+.
**How to avoid:** Track the row offset explicitly. When iterating cells for status-based fills, map each data row back to its source subscriber record to get the correct status color.
**Warning signs:** First data row having wrong color, or timestamp row getting a fill color.

### Pitfall 6: SQL Performance with Large call_logs Table

**What goes wrong:** As call_logs grows (hourly scrapes, 90-day retention), the ROW_NUMBER() subquery scans more rows.
**Why it happens:** Each scrape adds ~20 rows per staff member. At hourly 8am-8pm (12 scrapes/day, 2 staff), that is ~480 rows/day, ~14,400 rows/month.
**How to avoid:** The 30-day lookback window in the WHERE clause (`call_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)`) combined with `idx_call_timestamp` index limits the scan. At 14,400 rows/month, this is well within MariaDB's capability.
**Warning signs:** Subscriber panel load time exceeding 2 seconds.

## Code Examples

### Tooltip Text Construction

```javascript
// Source: CONTEXT.md tooltip format decision
function buildCallTooltip(sub) {
  if (!sub.call_status) return 'No contact recorded';

  const direction = sub.call_status.charAt(0).toUpperCase() + sub.call_status.slice(1);
  const dt = new Date(sub.last_call_datetime);
  const dateStr = dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const timeStr = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  const agent = sub.call_agent || '';

  return `${direction} \u2022 ${dateStr}, ${timeStr} \u2022 ${agent}`;
  // Example: "Placed . Mar 18, 2:15 PM . BC"
}
```

### XLSX Merged Header Row

```javascript
// Source: SheetJS documentation for sheet_add_aoa and merges
// After creating worksheet from data:
const lastCol = XLSX.utils.decode_range(ws['!ref']).e.c;

// Insert timestamp row at top
XLSX.utils.sheet_add_aoa(ws, [[`Call data as of ${syncTimestamp}`]], { origin: 'A1' });

// Merge across all columns
ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } }];

// Style the merged header
ws['A1'].s = {
  font: { italic: true, sz: 10, color: { rgb: '666666' } },
  alignment: { horizontal: 'left' },
};

// Adjust autofilter to row 2 (column headers)
ws['!autofilter'] = {
  ref: XLSX.utils.encode_range({ s: { r: 1, c: 0 }, e: { r: range.e.r + 1, c: lastCol } }),
};

// Freeze from row 3 (after timestamp + headers)
ws['!freeze'] = {
  xSplit: 0,
  ySplit: 2,
  topLeftCell: 'A3',
  activePane: 'bottomLeft',
  state: 'frozen',
};
```

### API Response Structure

```php
// Return from getExpirationSubscribers() or wrapper
[
    'metric_type' => 'expiration',
    'metric' => $bucket,
    'count' => count($subscribers),
    'snapshot_date' => $actualDate,
    'business_unit' => $businessUnit,
    'call_data_as_of' => $lastSyncTimestamp,  // NEW: "2026-03-20 14:02:00"
    'subscribers' => [
        [
            'account_id' => '12345',
            'subscriber_name' => 'John Smith',
            'phone' => '(864) 555-1234',
            'call_status' => 'placed',          // NEW: placed/received/missed/null
            'last_call_datetime' => '2026-03-18 14:15:00',  // NEW
            'call_agent' => 'BC',               // NEW
            // ... existing fields
        ]
    ]
]
```

## State of the Art

| Old Approach                     | Current Approach                         | When Changed         | Impact                                                 |
| -------------------------------- | ---------------------------------------- | -------------------- | ------------------------------------------------------ |
| No call data in subscriber table | LEFT JOIN call_logs with 30-day lookback | Phase 6 (this phase) | Circulation staff see contact status at a glance       |
| Alternating teal rows in XLSX    | Status-based fill colors in XLSX         | Phase 6 (this phase) | Printed call lists show who needs to be contacted      |
| No sort on subscriber table      | Status-priority sort with toggle         | Phase 6 (this phase) | Not-contacted subscribers surface to top automatically |

## Open Questions

1. **ROW_NUMBER() availability on NAS MariaDB**
   - What we know: MariaDB 10.x generally supports window functions (added in 10.2). The NAS runs MariaDB 10.
   - What's unclear: Exact minor version on the NAS. MariaDB 10.0/10.1 do NOT support ROW_NUMBER().
   - Recommendation: Test `SELECT ROW_NUMBER() OVER (ORDER BY 1)` on the NAS. If unavailable, use the GROUP BY + self-join fallback pattern. Phase 3 research noted MariaDB version concern; if REGEXP_REPLACE worked (requires 10.0.5+), ROW_NUMBER() likely works too (requires 10.2+). **Verify before implementation.**

2. **Tooltip implementation: native title vs CSS tooltip**
   - What we know: Native `title` attribute is simplest but has a delay and no styling control. CSS tooltips require more markup but appear instantly.
   - What's unclear: Whether the "Placed . Mar 18, 2:15 PM . BC" format renders well in native title.
   - Recommendation: Start with native `title` attribute (zero dependencies, no positioning bugs). If styling is needed later, upgrade to a CSS pseudo-element tooltip. The user said "exact tooltip positioning and animation" is Claude's discretion.

3. **Multiple entry points for subscriber table**
   - What we know: The subscriber table opens from at least 3 places: (a) expiration chart context menu via `getSubscribers()` in legacy.php, (b) revenue intelligence via `handleSubscriberListRequest()` in revenue_intelligence.php, (c) direct bar click.
   - What's unclear: Whether ALL entry points need call status, or only the expiration chart drill-down.
   - Recommendation: Add call status to `getExpirationSubscribers()` which is shared by both legacy.php and revenue_intelligence.php. This covers all expiration-related entry points. Rate and subscription-length drill-downs do not need call status (different use case).

## Sources

### Primary (HIGH confidence)

- `web/assets/js/components/subscriber-table-panel.js` -- Full table rendering code, inline styles pattern, headers array structure
- `web/assets/js/utils/export-utils.js` -- Existing `.s.fill` usage confirming SheetJS styling works, export column mapping
- `web/api/legacy.php` lines 1424-1718 -- `getExpirationSubscribers()` with all 8 bucket cases, SQL structure
- `database/migrations/014_add_call_logs_table.sql` -- call_logs schema with phone_normalized CHAR(10), indexes
- `database/migrations/015_add_phone_normalized_to_subscriber_snapshots.sql` -- subscriber_snapshots phone_normalized column
- `cdn.sheetjs.com/xlsx-latest/package/package.json` -- Confirmed xlsx v0.20.3 (supports .s.fill)

### Secondary (MEDIUM confidence)

- SheetJS documentation for `sheet_add_aoa()` and `!merges` -- pattern for inserting merged header rows
- MariaDB 10.2+ window function support -- ROW_NUMBER() availability

### Tertiary (LOW confidence)

- Exact MariaDB minor version on NAS -- needs runtime verification for ROW_NUMBER() support

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH -- no new libraries, all existing tools confirmed working
- Architecture: HIGH -- clear integration points, existing patterns to follow, SQL JOIN strategy well-understood
- Pitfalls: HIGH -- phone normalization verified, SheetJS styling confirmed, edge cases documented

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable -- no external dependency changes expected)
