# Phase 9: CSR Call Reporting - Context

**Gathered:** 2026-03-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Display outgoing call counts per CSR on a dedicated report page accessible from the settings hub. Managers can see call volume by direction (placed/received/missed) per CSR with a weekly trend chart. The report covers a rolling 60-day window.

</domain>

<decisions>
## Implementation Decisions

### Settings page placement
- New active card added as the 5th card in the grid, after Backup & Restore, before the Coming Soon placeholders
- Card shows summary stats (total outgoing, per-CSR counts) loaded via API on settings page load
- Card links to a dedicated CSR report page (new PHP file)

### Report page content
- Summary table with columns: CSR Name | Outgoing | Received | Missed | Total — plus a totals row
- Chart.js stacked bar chart below the table showing weekly call volume per CSR, with placed/received/missed as stacked segments
- "Data as of" timestamp from the most recent `imported_at` in call_logs

### CSR name mapping
- PHP associative array in the API endpoint: `['BC' => 'Brittany Carroll', 'CW' => 'Chloe Welch']`
- Unmapped source_group codes display as "Unknown (XX)" — nothing silently dropped
- Signals to manager that the mapping needs updating if a new CSR line is added

### Data loading
- New API endpoint: `api/csr_report.php`
- Settings card calls `?summary=true` for totals only (no weekly breakdown)
- Report page calls without param to get both summary and weekly data
- Response shape: `{summary: [{name, group, placed, received, missed, total}], weekly: [{week, group, placed, received, missed}], last_updated: "..."}`
- Page loads skeleton, JS fetches data, renders table + chart

### Claude's Discretion
- Chart color scheme for placed/received/missed segments
- Skeleton loading design
- Exact page layout and spacing
- Card icon and color theme choice
- Whether to group weekly chart bars by week (CSRs side-by-side) or by CSR

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Database schema
- `database/migrations/014_add_call_logs_table.sql` -- call_logs table schema (call_direction, source_group, call_timestamp, imported_at columns)

### Existing call infrastructure
- `web/fetch_call_logs.php` -- Scraper with user config array showing BC/CW source groups, phone extensions, and BroadWorks keys

### Settings page
- `web/settings.php` -- Current card grid layout, Cache Management card as pattern for API-loaded stats on a card

### API patterns
- `web/api/legacy.php` -- Existing API endpoint patterns for the dashboard (PDO queries, JSON responses)
- `web/api/cache_management.php` -- Pattern for settings-page API endpoints with CSRF protection

### Design system
- `docs/DESIGN-SYSTEM.md` -- Component library and UI patterns

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `settings.php` Cache Management card: Pattern for inline API-loaded stats on a settings card (fetch on DOMContentLoaded, update DOM elements)
- Chart.js 4.4.0 (CDN): Already available on all pages, used extensively for trend charts and BU detail modals
- Tailwind CSS: Existing utility classes for tables, cards, spacing

### Established Patterns
- Settings cards: `settings-card` class with hover transform, `border-2 border-transparent hover:border-{color}-200`, icon in colored rounded-lg container
- API endpoints: PDO with prepared statements, JSON response with `success` boolean, CSRF token validation for mutations
- Page structure: `auth_check.php` require, header with back arrow to parent, `max-w-7xl mx-auto` content area, footer with version

### Integration Points
- `settings.php`: New card HTML inserted after the Backup & Restore card
- `api/csr_report.php`: New API endpoint file in the existing API directory
- New page file (e.g., `csr_report.php`) in `web/` directory with header linking back to settings

</code_context>

<specifics>
## Specific Ideas

- Chart should be stacked bar (not grouped) showing all three call directions per CSR per week
- Table includes all directions, not just outgoing — gives complete picture of CSR phone activity
- Summary on the settings card focuses on outgoing counts (the primary metric) with a link to the full report

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-csr-call-reporting*
*Context gathered: 2026-03-21*
