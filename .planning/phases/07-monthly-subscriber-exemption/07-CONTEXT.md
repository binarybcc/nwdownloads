# Phase 7: Monthly Subscriber Exemption - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Monthly subscribers (last payment between -$0.01 and -$25.00) are visually distinguished from annual subscribers and excluded from call-to-action urgency indicators. Affects subscriber table rendering, sort order, and XLSX export. Does not add new UI features or columns -- uses existing infrastructure with conditional logic.

</domain>

<decisions>
## Implementation Decisions

### Row styling (no call activity)
- Monthly subscribers with NO call activity: plain row, no colored left border, no phone icon
- They don't participate in the call status system when uncontacted
- No badge, label, or extra indicator -- the absence of call status styling IS the distinction
- Staff already recognize monthly subs by their small negative payment amounts (-$12.50 etc.)

### Row styling (with call activity)
- Monthly subscribers WITH call activity: same treatment as annual subs
- Green border + phone icon for placed calls, orange for received/missed
- The exemption specifically targets the "no contact = red urgency" indicator
- If someone actually called a monthly sub, that contact is displayed normally

### Phone icon behavior
- No phone icon when monthly sub has no call activity (MONTH-02)
- Standard phone icon with appropriate color when call activity exists
- Tooltip behavior unchanged when icon is present

### XLSX export
- Monthly subs with no call activity: no row fill color (plain white cells)
- Monthly subs WITH call activity: same status coloring as annual subs (green for placed, yellow for received/missed)
- No additional "Subscriber Type" column -- payment amount column is sufficient indicator
- Consistent with UI treatment: exemption is about "no contact = no urgency color"

### Sort order
- Monthly subs sort as lowest priority group (below all annual subs in default ascending sort)
- Within monthly group: sorted by expiration date ascending
- Sort toggle (ascending/descending) reverses monthly position: ascending = bottom, descending = top
- Monthly subs participate in sort direction -- they're the lowest priority group, not pinned to bottom

### Detection approach
- Server-side: PHP API returns `is_monthly` boolean flag on each subscriber object
- SQL CASE expression: `WHEN last_payment_amount BETWEEN -25.00 AND -0.01 THEN 1 ELSE 0`
- Threshold defined as PHP constants: `MONTHLY_PAYMENT_MIN = -25.00`, `MONTHLY_PAYMENT_MAX = -0.01`
- Added to SELECT clause in each expiration bucket query, following existing `$callLogSubquery` reuse pattern
- Frontend checks `sub.is_monthly` -- single source of truth in PHP

### Claude's Discretion
- Exact PHP constant placement (top of legacy.php vs separate constants file)
- How to structure the reusable `is_monthly` SQL fragment across 8 bucket queries
- Sort priority number assignment for monthly group in JS `getStatusSortPriority()`
- Any edge cases around null/zero payment amounts

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Call status implementation (Phase 6 code)
- `web/assets/js/components/subscriber-table-panel.js` -- `getCallStatusColor()`, `sortSubscribers()`, `buildTableHTML()` render call status borders, icons, and sort order
- `web/assets/js/utils/export-utils.js` -- `getExportStatusFill()` applies red/green/yellow row fills in XLSX export
- `web/api/legacy.php` lines 1437-1489 -- `getExpirationSubscribers()` with `$callLogSubquery` pattern, serves subscriber data with call status fields

### Requirements
- `.planning/REQUIREMENTS.md` -- MONTH-01 through MONTH-04 define acceptance criteria

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `$callLogSubquery` (legacy.php): Reusable SQL fragment for call log LEFT JOIN, already used across all 8 bucket queries -- `is_monthly` CASE can follow the same pattern
- `getCallStatusColor()` (subscriber-table-panel.js): Returns border/iconBg/label -- needs monthly-aware conditional
- `getStatusSortPriority()` (subscriber-table-panel.js): Returns 0/1/2 for sort grouping -- needs new priority level for monthly
- `getExportStatusFill()` (export-utils.js): Returns SheetJS fill object -- needs monthly-aware conditional

### Established Patterns
- Call status colors: red (#EF4444) = no contact, green (#22C55E) = placed, orange (#F59E0B) = received/missed
- Export fills: red (FEE2E2), green (DCFCE7), yellow (FEF9C3) -- monthly with no activity = no fill
- Sort order via priority numbers (0=no contact, 1=received/missed, 2=placed) -- monthly needs lowest/highest depending on direction
- Alternating row backgrounds: white / #F0FDFA -- monthly rows should use this same alternation

### Integration Points
- `getExpirationSubscribers()` SQL SELECT clause: add `is_monthly` CASE expression
- `buildTableHTML()` row rendering loop: check `is_monthly` to conditionally skip border/icon
- `sortSubscribers()`: add monthly detection to sort priority
- `getExportStatusFill()`: check `is_monthly` + `call_status` for fill logic
- `exportSubscriberList()` / `formatSubscriberDataForExport()`: may need to pass `is_monthly` through

</code_context>

<specifics>
## Specific Ideas

- The key insight: monthly exemption is specifically about the "no contact = red urgency" indicator. If a monthly sub actually has call activity, they're treated identically to annual subs.
- No visual separator between annual and monthly groups in the table -- the styling change (absence of borders/icons) IS the separator.
- Payment amount in the existing "Last Payment" column is sufficient to identify monthly subs -- no new columns needed.

</specifics>

<deferred>
## Deferred Ideas

None -- discussion stayed within phase scope.

</deferred>

---

*Phase: 07-monthly-subscriber-exemption*
*Context gathered: 2026-03-20*
