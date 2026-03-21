# Phase 6: Call Status UI and Export - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Circulation staff can see call status for every expiring subscriber in the table, sort by contact status, and export a color-coded spreadsheet. This phase wires the call_logs data (Phase 3-4) into the existing subscriber table UI and XLSX export. Adding new call log capabilities, manual annotations, or retention policies are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Call status color definitions

- Green = staff placed a call TO the subscriber (outbound effort) within last 30 days
- Yellow-Orange = subscriber called US (inbound — received or missed) within last 30 days
- Red = no call record at all in the 30-day lookback window
- Direction-based, not outcome-based — what matters is who initiated the call
- When multiple calls exist in the 30-day window, the MOST RECENT call determines the color (not "best status wins")
- Retention stays at 90-day per CALL-F05 (future phase) — 30-day lookback is a separate display concern

### Row styling

- Subtle 4px colored left border on each row (green/yellow-orange/red) — visible at a glance
- Row background stays white/alternating (existing teal pattern preserved)
- Does NOT use full row tint in the browser — keeps existing table aesthetic clean

### Phone icon column

- Unicode phone emoji (📞) colored via CSS background circle or span styling
- Column position: FIRST column (leftmost, before Account ID)
- Pairs with the colored left border for immediate visual scanning
- Column header labeled "Status"

### Tooltip

- Hover over phone icon shows last call only: direction (Placed/Received/Missed), date/time, staff initials (BC/CW)
- Format: "Placed • Mar 18, 2:15 PM • BC"
- No contact shows: "No contact recorded"
- Staff identified by initials only (BC/CW) — circulation team knows who they are

### Default sort and filtering

- Default sort: not-contacted first (red on top), then yellow-orange, then green
- Sub-sort within each status group: expiration date soonest first
- No filter toggles — sorting is sufficient since red rows are already at top
- Status column header is clickable to toggle sort direction (▼ not-contacted first / ▲ contacted first)
- Sort arrow indicator shown in header

### XLSX export

- Three new columns added at the end: "Call Status" (text: Placed/Received/None), "Last Contact" (date/time), "Agent" (BC/CW)
- Entire row gets light fill tint matching status color:
  - Green rows: #DCFCE7 (light green)
  - Yellow rows: #FEF9C3 (light yellow)
  - Red rows: #FEE2E2 (light red)
- Row fill replaces the existing alternating teal pattern in the export (status color takes priority)
- "Call data as of [timestamp]" as a merged header row (Row 1), column headers on Row 2, data starts Row 3
- Same sync timestamp shown in the browser table header area (small text above table)
- Export sort order matches browser display (not-contacted first, sub-sorted by expiration date)
- Auto-filter and frozen headers preserved (existing export-utils.js behavior)

### Claude's Discretion

- Exact CSS implementation for the colored left border (border-left vs pseudo-element)
- How to color the Unicode phone emoji (CSS filter, colored span wrapper, or background circle approach)
- Exact tooltip positioning and animation
- SQL query optimization for the LEFT JOIN on call_logs
- How to determine the "last sync" timestamp (MAX(call_timestamp) from call_logs, or a separate metadata record)
- Sort arrow styling and toggle interaction details

</decisions>

<canonical_refs>

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Call log data layer

- `database/migrations/014_add_call_logs_table.sql` — call_logs table DDL with UNIQUE KEY, column types (call_direction, phone_normalized, call_timestamp, source_group)
- `web/api/legacy.php` — `getExpirationSubscribers()` function (line ~1424) that returns subscriber rows; needs LEFT JOIN on call_logs

### Existing subscriber table UI

- `web/assets/js/components/subscriber-table-panel.js` — `SubscriberTablePanel` class with `buildTableHTML()` that renders the column layout and row styling
- `web/assets/js/utils/export-utils.js` — `exportToExcel()` with SheetJS `.s.fill` cell styling, `formatSubscriberDataForExport()` column mapping, `exportSubscriberList()` high-level wrapper

### Phone normalization

- `web/lib/AllSubscriberImporter.php` — `normalizePhone()` method for 10-digit matching logic (call_logs.phone_normalized must match subscriber_snapshots.phone_normalized)

### Design system

- `docs/DESIGN-SYSTEM.md` — Component library and UI patterns for the dashboard

### Phase 4 context (call log structure)

- `.planning/phases/04-call-log-scraper/04-CONTEXT.md` — Data mapping decisions: source_group stores 'BC'/'CW', call_direction values, phone_normalized rules

</canonical_refs>

<code_context>

## Existing Code Insights

### Reusable Assets

- `SubscriberTablePanel` class: Full table rendering with backdrop, animations, export buttons, and pagination — extend with new Status column and sort behavior
- `exportToExcel()`: Already supports `.s.fill` for cell background colors, frozen headers, auto-filter — extend with call status row fills and header timestamp row
- `formatSubscriberDataForExport()`: Column mapping function — add Call Status, Last Contact, Agent columns
- `getExpirationSubscribers()`: SQL query returning subscriber rows with phone column — add LEFT JOIN on call_logs matching phone_normalized

### Established Patterns

- Table columns defined in `buildTableHTML()` as a `headers` array (line 229) — insert "Status" at index 0
- Row rendering uses inline styles with alternating `#F0FDFA`/white backgrounds — add conditional left-border color
- Export uses `XLSX.utils.json_to_sheet()` then iterates cells for styling — same pattern for status-based row fills
- Global function registration: `window.exportSubscriberList = exportSubscriberList` pattern

### Integration Points

- `legacy.php` `getExpirationSubscribers()` → needs to return `call_status`, `last_call_datetime`, `call_agent` fields
- `subscriber-table-panel.js` `buildTableHTML()` → needs Status column rendering with icon, tooltip, and left border
- `export-utils.js` `formatSubscriberDataForExport()` → needs 3 new columns mapped
- `export-utils.js` `exportToExcel()` → needs status-based row fill logic and header timestamp row
- API response for call sync timestamp → new field in the subscriber list response or separate query

</code_context>

<specifics>
## Specific Ideas

- The 30-day lookback window is a display concern — the actual retention (90 days, CALL-F05) is separate and handled in a future phase
- "Most recent call wins" for color determination means the SQL needs `ORDER BY call_timestamp DESC LIMIT 1` per subscriber in the JOIN or subquery
- Staff initials (BC/CW) match the `source_group` column stored in call_logs — no lookup mapping needed
- The XLSX row fills (#DCFCE7, #FEF9C3, #FEE2E2) were chosen to print well in both color and B&W (distinct gray levels)
- STATE.md flags a concern: "SheetJS xlsx-latest CDN — verify .s.fill cell style support before building XLSX export" — the existing export-utils.js already uses `.s.fill` successfully, so this is likely resolved but should be confirmed

</specifics>

<deferred>
## Deferred Ideas

- CALL-F05: 90-day retention policy with automatic pruning — future phase
- Status filter toggles (dropdown or checkbox to show only not-contacted) — not needed for MVP, sorting is sufficient
- Full staff name display instead of initials — future enhancement if non-circulation staff need the data
- Call history tooltip showing last 3 calls — future enhancement if single-call tooltip proves insufficient

</deferred>

---

_Phase: 06-call-status-ui-and-export_
_Context gathered: 2026-03-20_
