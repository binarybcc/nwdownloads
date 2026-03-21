# Phase 8: Trend Expansion & Log Retention - Context

**Gathered:** 2026-03-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Expand the "trend over time" views to show a full quarter by default (13 weeks) with user-adjustable range, and add automated 90-day call log retention to the import script. No new UI pages or features -- modifies existing trend views and adds cleanup logic to existing scraper.

</domain>

<decisions>
## Implementation Decisions

### Trend scope (TREND-01)
- The "Click on any bar to see trend over time" views on BU detail pages get a user-adjustable weeks input (like the existing trend detail modal's 4-52 week selector)
- Default to 13 weeks (a full quarter) when trend view opens
- Reuse the existing adjustable pattern from `bu-trend-detail.js` rather than writing specialized code
- BU card mini-charts on the overview page also change from 12 to 13 weeks for consistency

### Purge timing (MAINT-01)
- Purge runs AFTER import completes within `fetch_call_logs.php` -- new data inserted first, then old records cleaned
- This ensures no data gap if import fails partway through

### Purge criteria
- Delete based on `call_timestamp` (when the call happened), not `imported_at`
- Business-meaningful: "keep 90 days of call history"
- Simple `DELETE FROM call_logs WHERE call_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)`

### Purge logging
- Log purged row count to existing scraper log: "Purged N call_logs records older than 90 days"
- No detailed date range logging needed
- Matches existing `log_msg()` pattern in `fetch_call_logs.php`

### Purge safety
- No guard or abort threshold -- simple DELETE WHERE older than 90 days
- Call logs are scraped copies of BroadWorks data, not critical records
- 90-day window is generous for the business use case (subscriber contact status only looks back 30 days)

### Claude's Discretion
- Whether to add `13weeks` to the legacy API `$weeksMap` or just change the default
- Exact placement of weeks input in the trend views (follow existing modal pattern)
- Whether purge should be wrapped in a try/catch or just log on failure

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Trend implementation
- `web/api/legacy.php` lines 2211-2278 -- `getHistoricalTrend()` function, `$weeksMap` at lines 2225-2230, default `12weeks` at line 3047
- `web/api/get_bu_trend_detail.php` -- Newer BU-specific trend endpoint used by modal
- `web/assets/js/features/bu-trend-detail.js` -- Trend detail modal with weeks input (lines 48-57), chart rendering, API call pattern at lines 369-373

### Call log import
- `web/fetch_call_logs.php` -- Full import script (238 lines), CLI-only guard, business hours guard, lock file, `INSERT IGNORE` dedup, `log_msg()` logging pattern
- `database/migrations/014_add_call_logs_table.sql` -- `call_logs` schema with `call_timestamp` and `imported_at` columns, indexes

### Requirements
- `.planning/REQUIREMENTS.md` -- TREND-01 and MAINT-01 define acceptance criteria

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `bu-trend-detail.js` weeks input pattern (lines 48-57): number input with min/max/default that triggers API refetch -- reuse this for the bar-click trend views
- `log_msg()` in `fetch_call_logs.php`: existing logging function that writes to `/volume1/web/circulation/logs/call_scraper.log`
- `$weeksMap` in `getHistoricalTrend()`: already supports 4, 12, 26, 52 weeks -- add 13 or change default

### Established Patterns
- Trend data uses Saturday-based snapshots, goes back N x 7 days from end date
- API returns `snapshot_date`, `count`, `change_from_previous`, `change_percent` per data point
- Import script uses lock file + business hours guard + CLI-only guard before any operations

### Integration Points
- BU detail page bar charts ("Subscribers by Zone", "Rate Distribution", "Subscription Length Distribution") -- their click handlers open trend views that need the weeks selector
- `fetch_call_logs.php` line ~200 area (after INSERT loop) -- add DELETE statement for purge
- Legacy API `$weeksMap` -- add `13weeks` entry or change default

</code_context>

<specifics>
## Specific Ideas

- Reuse the existing adjustable weeks pattern from the trend detail modal rather than writing specialized 13-week code -- "save writing specialized code" was the explicit preference
- The bar-click trend views should get the same kind of weeks input the modal already has

</specifics>

<deferred>
## Deferred Ideas

None -- discussion stayed within phase scope.

</deferred>

---

*Phase: 08-trend-expansion-log-retention*
*Context gathered: 2026-03-21*
