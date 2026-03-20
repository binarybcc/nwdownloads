# Project Research Summary

**Project:** NWDownloads v2.1 — Call Log Integration & Dashboard Enhancements
**Domain:** Newspaper circulation retention dashboard with VOIP call log overlay
**Researched:** 2026-03-20
**Confidence:** HIGH

## Executive Summary

This milestone adds four capabilities to an existing, stable circulation dashboard: a BroadWorks call log scraper (tracks which expiring subscribers have been called by Brittany or Chloe), call status display in the expiration subscriber table, an expansion of the expiration chart from 4 to 8 weeks, and a date format bug fix in the NewStarts importer. The existing PHP/MariaDB/Chart.js stack handles all four without any new JavaScript dependencies. The only new production library is PhpSpreadsheet (^5.5) for server-side styled XLSX export if the call status coloring is done server-side — and even that may be deferred if client-side SheetJS handles it.

The recommended approach is to build in strict dependency order: schema first, then the scraper that populates it, then the API JOIN that reads call status, then the UI that displays it, and finally the XLSX export that carries it through. The expiration chart expansion (SQL + JS labels) is independent of the call log work and can be done in parallel or as its own phase. The critical architectural decision — storing a `phone_normalized` column on both tables and indexing it — must happen before anything else, because every downstream feature depends on reliable phone matching.

The primary risks are all silent-failure modes: the BroadWorks scraper can return zero rows without throwing an error (stale session, cookie protocol mismatch, or phone normalization mismatch), and the expiration chart can appear to work while displaying wrong data (SQL and JS week counts out of sync). Both risks are preventable with a small amount of defensive coding and a specific validation checklist. Research confidence is HIGH across all areas because findings are grounded in direct codebase inspection and a working, tested integration document.

---

## Key Findings

### Recommended Stack

The existing stack (PHP 8.2, MariaDB 10, Chart.js 4.4.0, SheetJS Pro, Tailwind CSS 3.4, Vanilla JS ES2020+) handles every feature in this milestone. No new JavaScript dependencies are permitted or needed. The one addition is `phpoffice/phpspreadsheet ^5.5` for server-side XLSX generation with call status row colors — the only PHP library with full cell fill styling on PHP 8.2. BroadWorks integration uses PHP cURL with file-based cookie jars, which is already documented in a working, tested class in `docs/MYCOMMPILOT-INTEGRATION.md`.

**Core technologies:**

- PHP cURL (built-in): BroadWorks JSP scraping — file-based cookie jar required for HTTPS-to-HTTP session bridging
- PhpSpreadsheet ^5.5: server-side XLSX with call status row coloring — only mature option with fill styling on PHP 8.2
- Phinx ^0.13: database migration for `call_logs` table + `phone_normalized` column additions
- macOS launchd: hourly call log scraping via SSH to NAS — same pattern as existing auto-import job; Synology Task Scheduler must not be used
- Chart.js 4.4.0 (existing): 8-bar expiration chart requires only config adjustments (`barPercentage`, `ticks.maxRotation`) — no plugins

### Expected Features

**Must have (table stakes):**

- Call status indicator per subscriber row (phone icon + row background color: green = called ≤7 days, yellow = called 8-14 days, white = not called)
- Last called timestamp in subscriber table (relative time + exact datetime tooltip)
- Agent attribution (BC vs CW) visible per row — data available from `line_label` in call_logs
- Call status carry-through to XLSX export with matching fill colors
- Default sort: not-called subscribers first, then ascending by expiration date
- 8-week expiration chart (extended from current 4-week/21-day window)
- NewStarts CSV date format fix (M/D/YY compatibility in importer)

**Should have (differentiators):**

- Per-agent call activity summary (count of calls by BC vs CW this week) — add after initial validation
- Visual urgency de-emphasis on weeks 5-8 vs weeks 1-2 — easy win once 8-week chart is live

**Defer (v2.x / v3+):**

- Manual call outcome annotation (reached / voicemail / wrong number) — requires UI for staff to update
- Chart bar stacked call-status overlay — high Chart.js complexity, only if summary view proves insufficient
- XSI REST API migration — only if Segra enables it; would replace scraper entirely

### Architecture Approach

The architecture adds two new components (a scraper class and a CLI runner) to an existing layered system, modifies two existing API functions, and extends one existing frontend component. Call logs are stored in a separate table and joined at query time — never merged into subscriber_snapshots, which is rebuilt wholesale each week from Newzware CSVs. The critical cross-cutting concern is phone number normalization: both the call_logs table (at insert time) and subscriber_snapshots (via AllSubscriberImporter update) must store a `phone_normalized` column with consistent 10-digit stripping, indexed, so the JOIN is sargable.

**Major components:**

1. `web/lib/MyCommPilotScraper.php` — session-based cURL scraper for BroadWorks call logs (already specified in integration doc)
2. `web/fetch_call_logs.php` — CLI runner invoked by launchd; loops users/types, INSERT IGNORE into call_logs; parallel to auto_process.php
3. `call_logs` MariaDB table — stores all scraped calls with phone_normalized, UNIQUE dedup key, indexed for JOIN performance
4. `legacy.php` modifications — extend expiration query to 8 weeks; add LEFT JOIN on call_logs to getExpirationSubscribers()
5. macOS launchd plist — hourly trigger 8am-8pm M-F via SSH to NAS; business-hours guard in PHP script, not 65-dict plist
6. `export-utils.js` modification — pass call_status as data field; set SheetJS `.s.fill` explicitly (DOM row colors do not transfer to XLSX)

### Critical Pitfalls

1. **Stale BroadWorks session returning 0 rows silently** — add a post-login probe (fetch folder_contents.jsp, assert known UI string) before fetching call logs; add `register_shutdown_function()` to clean temp cookie files on fatal exit
2. **Phone number format mismatch producing zero JOIN matches** — normalize at ingest for both tables; store as `phone_normalized VARCHAR(10)` indexed column; JOIN only on this column; never REGEXP_REPLACE in a JOIN condition
3. **SQL and JS week counts going out of sync** — update SQL CASE clauses, interval filter, ORDER BY, JS color array, and context menu labels in one coordinated commit; the SQL hard-codes `INTERVAL 21 DAY` in at least three places
4. **XLSX export missing call status colors** — `json_to_sheet` reads data objects only; DOM row colors never transfer to XLSX; must set `ws[cellRef].s.fill` from the `call_status` field in the data object explicitly
5. **SheetJS `xlsx-latest` floating CDN tag** — verify current loaded version supports `.s` cell styles before building color export; if not, switch to `xlsx-js-style` (drop-in fork with guaranteed cell style support)

---

## Implications for Roadmap

Based on the dependency chain identified in research, a 4-phase structure is recommended. The call log features have hard sequential dependencies (schema → scraper → API JOIN → UI → export), while the expiration chart expansion is independent and can proceed as a dedicated phase.

### Phase 1: Data Foundation

**Rationale:** Everything downstream depends on the schema existing and phone normalization working. This is the highest-risk phase for silent failures — getting the data model right here prevents debugging pain in all later phases.
**Delivers:** `call_logs` table migration, `phone_normalized` column on `subscriber_snapshots`, updated AllSubscriberImporter to populate phone_normalized, one-time backfill of existing subscriber data, NewStarts date format bug fix
**Addresses:** Import date fix (P1), call_logs schema (P1)
**Avoids:** Phone mismatch pitfall (Pitfall 3), JOIN performance pitfall (Pitfall 5), DateTime parsing regression (Pitfall 8)

### Phase 2: Call Log Scraper

**Rationale:** The scraper must be running and populating call_logs before any UI can display call status. This phase has the most BroadWorks-specific gotchas and deserves isolation for testing.
**Delivers:** `MyCommPilotScraper.php`, `fetch_call_logs.php` CLI runner, macOS launchd hourly plist, INSERT IGNORE dedup, business-hours guard in PHP
**Uses:** PHP cURL with file-based cookie jar, launchd (existing pattern), MariaDB PDO (existing pattern)
**Avoids:** Stale session pitfall (Pitfall 1), HTTPS-to-HTTP cookie pitfall (Pitfall 2), cookie jar orphan leak
**Verification:** Run scraper twice in one minute — row count must not double. Simulate login failure — must log error, not stack trace.

### Phase 3: Expiration Chart Expansion

**Rationale:** Independent of call logs; can ship before or after Phase 2. Expands the subscriber table that call status will overlay, so shipping it early gives call status a wider canvas. Low regression risk if done as a focused change.
**Delivers:** 8-week SQL CASE in legacy.php, extended week labels in JS, updated Chart.js bar config (barPercentage, maxRotation), index-based color scheme replacing hard-coded label matching
**Avoids:** Color scheme breakage pitfall (Pitfall 6), SQL/JS mismatch pitfall (Pitfall 7)
**Verification:** Chart renders 8 bars with distinct colors; right-click context menu works on all 8 bars.

### Phase 4: Call Status Overlay and Export

**Rationale:** Depends on Phase 1 (phone_normalized populated) and Phase 2 (call_logs has data). This is the feature staff will interact with daily — the visible payoff of the first three phases.
**Delivers:** getExpirationSubscribers() LEFT JOIN on call_logs, call_status + last_call_datetime + agent fields in JSON response, row background coloring in subscriber table, phone icon with agent initials, default sort (not-called first), XLSX export with matching fill colors
**Uses:** PhpSpreadsheet if server-side export needed; SheetJS `.s.fill` in export-utils.js if client-side
**Avoids:** XLSX missing colors pitfall (UX pitfall), JOIN performance pitfall (Pitfall 5), "Private" caller false positives
**Verification:** Open exported .xlsx in Excel — call status rows must have correct fill color. EXPLAIN on the subscriber query must show index usage on call_logs.phone_normalized.

### Phase Ordering Rationale

- Schema and normalization must precede all other phases — a phone JOIN without `phone_normalized` indexes will fail silently or perform unacceptably
- Scraper must run before call status UI — you cannot test the overlay without real data
- Expiration chart expansion is deliberately isolated — it touches different code paths and can be validated cleanly without call log dependencies
- Call status overlay is last because it integrates all prior work; building it last means each dependency is testable before it is depended upon

### Research Flags

Phases where standard patterns apply (no additional research needed):

- **Phase 1 (Data Foundation):** Phinx migrations and AllSubscriberImporter modifications follow existing project patterns. MariaDB generated column for phone normalization is well-documented.
- **Phase 3 (Expiration Chart):** Chart.js 4.4 bar chart configuration is well-documented; no new patterns needed.

Phases that may benefit from brief research during planning:

- **Phase 2 (Call Log Scraper):** The BroadWorks session behavior is documented in MYCOMMPILOT-INTEGRATION.md with high confidence, but verify the `folder_contents.jsp` probe string against the live portal before relying on it as a session health check.
- **Phase 4 (Call Status Overlay):** SheetJS `xlsx-latest` version should be confirmed to support `.s.fill` before building the export feature. If it does not, switching to `xlsx-js-style` should be researched briefly.

---

## Confidence Assessment

| Area         | Confidence | Notes                                                                                                                                                                        |
| ------------ | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Stack        | HIGH       | Existing stack verified from codebase; PhpSpreadsheet version confirmed on Packagist; BroadWorks cURL pattern verified against live portal                                   |
| Features     | MEDIUM     | Core patterns grounded in CRM/retention industry norms; call status UX patterns extrapolated from general data table literature; newspaper-specific timing MEDIUM confidence |
| Architecture | HIGH       | All findings from direct codebase inspection; no guesses; build order derived from hard dependency chain                                                                     |
| Pitfalls     | HIGH       | Pitfalls identified from direct code inspection and live portal testing; not theoretical                                                                                     |

**Overall confidence:** HIGH

### Gaps to Address

- **SheetJS version and cell style support:** The CDN uses `xlsx-latest` (floating tag). Verify the currently loaded version supports `.s.fill` cell styling before committing to the client-side XLSX export path. If it does not, the server-side PhpSpreadsheet path is the fallback and should be scoped in Phase 4.
- **MariaDB REGEXP_REPLACE availability:** The Synology NAS runs MariaDB 10.x. Confirm the exact minor version supports `REGEXP_REPLACE` in generated column definitions before using it in the subscriber_snapshots phone_digits virtual column approach. The PHP-normalization-at-import approach (Option A in ARCHITECTURE.md) avoids this entirely and is recommended.
- **BroadWorks `folder_contents.jsp` probe string:** The post-login session health check relies on a known UI string in the response. Verify the exact string against the live portal before the scraper goes to production to avoid false "session broken" errors.
- **`call_logs` table growth and cleanup:** Research found the table will accumulate ~1,560 rows/day. A 90-day cleanup DELETE should be added to the scraper script; confirm this is acceptable data retention with stakeholders before shipping.

---

## Sources

### Primary (HIGH confidence)

- `docs/MYCOMMPILOT-INTEGRATION.md` — Working PHP scraper class, BroadWorks auth flow, JSP gotchas, session behavior (first-hand reverse engineering of live portal)
- `web/api/legacy.php` — Expiration query structure, getExpirationSubscribers() implementation, phone field definitions
- `web/lib/NewStartsImporter.php` — parseDate() M/D/YY implementation
- `web/assets/js/utils/export-utils.js` — SheetJS json_to_sheet usage, existing cell styling approach
- `web/assets/js/components/detail_panel.js` — renderExpirationChart() 4-category color logic
- `web/auto_process.php` — Existing importer/processor pattern (model for fetch_call_logs.php)
- `~/Library/LaunchAgents/com.circulation.auto-import.plist` — Existing launchd plist pattern

### Secondary (MEDIUM confidence)

- [Packagist: phpoffice/phpspreadsheet](https://packagist.org/packages/phpoffice/phpspreadsheet) — version 5.5.0, PHP ^8.1 requirement
- [PhpSpreadsheet docs: Cell fill styling](https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/) — applyFromArray fill pattern
- [Chart.js bar chart docs](https://www.chartjs.org/docs/latest/charts/bar.html) — barPercentage, categoryPercentage, ticks.maxRotation options
- [Recurly: 17 Strategies to Reduce Subscriber Churn](https://recurly.com/blog/reduce-churn/) — subscription retention timing norms
- [Pencil & Paper: Data Table Design UX Patterns](https://www.pencilandpaper.io/articles/ux-pattern-analysis-enterprise-data-tables) — call status table UX norms

### Tertiary (LOW confidence — needs validation)

- SheetJS Pro cell style support in `xlsx-latest` CDN version — verify against live loaded version before shipping XLSX export feature

---

_Research completed: 2026-03-20_
_Ready for roadmap: yes_
