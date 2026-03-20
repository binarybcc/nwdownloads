# Pitfalls Research

**Domain:** Adding VOIP call log scraping and call-status overlay to an existing newspaper circulation dashboard (PHP 8.2, MariaDB, Chart.js 4.4.0, SheetJS)
**Researched:** 2026-03-20
**Confidence:** HIGH — based on direct inspection of existing codebase, the MyCommPilot integration doc (docs/MYCOMMPILOT-INTEGRATION.md), and known BroadWorks/JSP portal behavior

---

## Critical Pitfalls

### Pitfall 1: Stale Cookie Jar Accumulating Across Scrape Runs

**What goes wrong:**
The scraper uses `tempnam()` to create a fresh cookie file per `MyCommPilotScraper` instance. If the scraper crashes after `tempnam()` but before `__destruct()` is called — which happens when PHP runs out of memory, hits a fatal error, or when the launchd job is killed — the temp file is orphaned. Over weeks of hourly runs, `/tmp` fills with `mcp_*` files. More dangerous: if an operator tries to reuse a single scraper instance across multiple cron invocations (e.g., by making it a persistent daemon), the stale JSESSIONID from a previous BroadWorks session causes all requests to silently return the login page HTML instead of call log HTML, and `parseCallLogs()` returns an empty array with no error.

**Why it happens:**
BroadWorks session tokens expire after inactivity but the PHP cookie jar keeps serving them. `CURLOPT_COOKIEFILE` + `CURLOPT_COOKIEJAR` pointing to the same file means old cookies persist across curl operations within the same instance. The login check in `login()` only verifies the response body contains `folder_contents.jsp` — it does not verify subsequent call log fetches are returning real data.

**How to avoid:**

- Always instantiate a fresh `MyCommPilotScraper` object per scrape run (never reuse across cron invocations — the doc already recommends this, but the scheduler must enforce it).
- Add a fallback `@unlink()` in a `register_shutdown_function()` at the top of the scraper CLI script to clean up the temp file on fatal errors.
- After login, add a probe: fetch `/Common/folder_contents.jsp?menuId=1` and assert the response contains a known UI string (e.g., `"Edwards_Group"` or `"Group"`) before proceeding to call log pages. If the probe fails, treat the session as broken and abort with a logged error rather than inserting zero records silently.

**Warning signs:**

- Scrape runs complete with zero new rows inserted but no error logged.
- `/tmp` contains many `mcp_*` files older than a day.
- Call log pages return HTML that contains `"Login"` or `"servlet/Login"` — indicates redirect to login page.

**Phase to address:** Call Log Scraper phase (whichever phase builds `web/lib/MyCommPilotScraper.php`). Add the probe and shutdown handler before the first cron test run.

---

### Pitfall 2: HTTPS-to-HTTP Cookie Sharing Blocked by cURL Defaults

**What goes wrong:**
The BroadWorks auth flow logs in over HTTPS (`https://ws2.mycommpilot.com`) then redirects all subsequent traffic to plain HTTP (`http://ws2.mycommpilot.com:80`). By default, cURL does NOT send cookies from an HTTPS domain to an HTTP URL for the same domain — this is a security feature. The result: the HTTP call log requests have no session cookies, the server returns the login page, `parseCallLogs()` finds no matching `<td>` pattern, and returns an empty array. No PHP error is thrown.

**Why it happens:**
RFC-correct behavior: cookies set with `Secure` flag should not travel over HTTP. BroadWorks JSP portals set `JSESSIONID` during HTTPS login. Without explicitly allowing cross-protocol cookies, cURL drops them on the protocol transition. The working example in `docs/MYCOMMPILOT-INTEGRATION.md` handles this correctly — but only because `CURLOPT_FOLLOWLOCATION => true` is set and the redirect from the login POST naturally carries the session. The problem surfaces if someone adds `CURLOPT_SSL_VERIFYPEER => false` without `CURLOPT_COOKIESESSION => false` (the defaults differ between PHP environments).

**How to avoid:**
Do not add `CURLOPT_COOKIESESSION` to the scraper options — it would reset cookies between requests. The existing pattern (shared `$this->cookieFile` with both `CURLOPT_COOKIEFILE` and `CURLOPT_COOKIEJAR`) is correct. The trap is in modifications. Any future change that adds `CURLOPT_COOKIESESSION => true` or replaces the single-file approach with separate read/write files will break protocol bridging.

Add a code comment at the cURL initialization block: `// WARNING: COOKIEFILE and COOKIEJAR must be the same file path to carry cookies across HTTPS→HTTP redirect.`

**Warning signs:**

- Call logs fetch 0 records immediately after a code change to the cURL setup.
- Adding `curl_getinfo()` logging shows HTTP 200 responses but HTML content length < 500 bytes (login page redirect).

**Phase to address:** Call Log Scraper phase, during initial implementation. Flag this in code review.

---

### Pitfall 3: Phone Number Format Mismatch Silently Produces Zero Matches

**What goes wrong:**
The `subscriber_snapshots.phone` column is `VARCHAR(50)` and stores whatever Newzware exports — raw from the `AllSubscriberImporter` with no normalization. Newzware exports phone numbers in formats like `(864) 973-7731`, `864-973-7731`, or `8649737731`. MyCommPilot call logs return bare 10-digit numbers (`8649737731`) or 4-digit internal extensions (`6706`). A naive string equality match between subscriber phone and call log phone will produce zero matches even when the same phone number exists in both systems. Since call status shows as "no contact" rather than throwing an error, this failure mode is invisible until someone notices that nobody in the table ever shows a call icon.

**Why it happens:**
Phone numbers are stored as-entered with no normalization step. The existing `AllSubscriberImporter.php` reads the `Phone` column directly (`trim($row[$col_map['Phone']] ?? '')`) with no stripping. The call log scraper captures the phone exactly as BroadWorks presents it (10 digits, no formatting). These two formats will never match without a normalization layer.

**How to avoid:**
Create a canonical phone normalization function used in both directions — normalize subscriber phones at query time (strip all non-digits, keep last 10) AND normalize call log phones before insert (same rule). Do not normalize at import time for subscriber_snapshots — that would require re-importing all historical data. Use a computed/stripped comparison at JOIN time:

```sql
REGEXP_REPLACE(ss.phone, '[^0-9]', '') = cl.phone_normalized
```

Where `phone_normalized` is stored pre-computed in `call_logs` (digits only, 10 chars). Index `call_logs.phone_normalized`.

For 4-digit extensions: extensions will never match a 10-digit subscriber phone. Filter them out of the matching query entirely using `WHERE LENGTH(cl.phone_normalized) = 10`. Internal extension calls are not subscriber outreach anyway.

**Warning signs:**

- Zero rows returned when joining `call_logs` to `subscriber_snapshots` in development testing.
- Checking actual data: `SELECT phone FROM subscriber_snapshots LIMIT 5` shows formatted numbers while `call_logs.phone` shows bare digits.

**Phase to address:** Call Log Scraper phase (normalize on insert into `call_logs`) AND Call Status Overlay phase (normalize at JOIN time in the subscriber list query).

---

### Pitfall 4: `INSERT IGNORE` Dedup Key Silently Loses Data When Calls Happen at the Same Time

**What goes wrong:**
The suggested `call_logs` schema uses `UNIQUE KEY dedup (line_label, call_type, phone, call_datetime)`. Two calls from the same subscriber phone number within the same minute (or any two calls logged with the same `call_datetime` string from BroadWorks) will deduplicate to one record. BroadWorks stores datetimes as strings in `M/D/YY h:mm AM/PM` format — minute-level precision, no seconds. If a staff member calls a subscriber, gets voicemail, and calls back within the same minute, only one record is stored.

**Why it happens:**
BroadWorks Basic Call Logs don't include seconds in the timestamp. The dedup key is the only available identity for a call record since there is no call UUID. This is a fundamental limitation of the data source, but the schema must acknowledge it rather than pretend records are fully unique.

**How to avoid:**
Accept the limitation: document it as a known constraint. The call status overlay only needs to know "was this subscriber called?" not "how many times exactly?" — so losing duplicate-minute calls does not affect the primary use case. Do not attempt to work around it by removing the dedup constraint (which would cause runaway duplicate inserts on each scrape run as the 20-entry rolling window re-scrapes the same calls).

Add a comment to the schema: `-- Dedup at minute precision; calls within the same minute by same line to same number are collapsed. Known BroadWorks limitation.`

**Warning signs:**

- Call count for a subscriber seems low; checking portal manually shows more calls.
- This is expected behavior — only escalate if it causes business logic problems.

**Phase to address:** Call Log Scraper phase, during schema design. No code fix needed; documentation fix only.

---

### Pitfall 5: Call Status JOIN Slowing Down the Expiration Subscriber Query

**What goes wrong:**
The expiration subscriber list query in `legacy.php` already selects all subscriber fields from `subscriber_snapshots` filtered by BU and expiration window. Adding a LEFT JOIN to `call_logs` to determine call status can cause the query to go from fast (indexed `snapshot_date + paper_code` lookup) to slow, because `call_logs` will grow continuously (hourly scrapes, 6 call types per 2 users = up to 240 new rows/day). Without proper indexing on `call_logs`, the JOIN will table-scan `call_logs` for every subscriber row.

The expiration panel is already opened in a UI context where the user is waiting for data — a 3-second query feels broken on the Synology's modest MariaDB.

**Why it happens:**
The subscriber list query currently does a straightforward WHERE-filtered SELECT. A new LEFT JOIN on `call_logs` with a phone normalization expression (`REGEXP_REPLACE(ss.phone, '[^0-9]', '')`) in the JOIN condition will make the join non-sargable — MariaDB cannot use an index on an expression. If `phone_normalized` is not a stored computed column with an index, the query planner resorts to full scans.

**How to avoid:**
Store `phone_normalized` as a real column in `call_logs` (populated at insert time, 10 digits only). Index it:

```sql
ALTER TABLE call_logs ADD INDEX idx_phone_normalized (phone_normalized);
```

For the subscriber side: create a companion indexed view or add a `phone_digits` generated column to `subscriber_snapshots`. MariaDB supports generated/virtual columns:

```sql
ALTER TABLE subscriber_snapshots
ADD COLUMN phone_digits VARCHAR(10) AS (REGEXP_REPLACE(phone, '[^0-9]', '')) VIRTUAL,
ADD INDEX idx_phone_digits (phone_digits);
```

Alternatively, use a subquery that pre-aggregates "which phone numbers were called in the last N days" into a set, then JOIN to that small set. This keeps the outer query working against the indexed subscriber data.

**Warning signs:**

- Expiration panel takes >1 second to load after adding the call status JOIN in development.
- `EXPLAIN` shows `type: ALL` on `call_logs` or `subscriber_snapshots` for the JOIN.

**Phase to address:** Call Status Overlay phase. Run EXPLAIN before and after adding the JOIN. Do not ship without verifying query plan.

---

### Pitfall 6: Extending Expiration Chart from 4 to 8 Weeks Breaks the Color Scheme Logic

**What goes wrong:**
`renderExpirationChart()` in `detail_panel.js` assigns colors by label name using hard-coded `if/else` branches: `'Past Due'` → red, `'This Week'` → orange, `'Next Week'` → amber, `'Week +2'` → yellow, everything else → gray. When weeks 3-7 are added, all new labels fall into the gray (`else`) branch and look identical. Users cannot distinguish urgency across the extended range.

Additionally, the current chart canvas has no explicit height constraint; with 8 bars the bars may become very narrow on the mobile-width panel (the subscriber table panel is 75% viewport width). Chart.js `maintainAspectRatio: false` is set, so the canvas will maintain whatever pixel height the container CSS provides. If the container height isn't updated for 8 bars, they'll be too thin to click for the right-click context menu.

**Why it happens:**
The color assignment code was written for exactly 4 categories and uses label-name matching rather than an index-based or value-based gradient. The canvas container height is likely set in inline CSS or Tailwind utilities tuned for 4 bars.

**How to avoid:**
Replace the label-name color logic with an index-based approach: the 0th element is always most urgent (Past Due), the last element is least urgent. Map index to a color scale from red through amber to gray. This is resilient to adding new week labels:

```javascript
const urgencyColors = [
  'rgba(239, 68, 68, 0.8)', // Past Due - red
  'rgba(251, 146, 60, 0.8)', // This Week - orange
  'rgba(251, 191, 36, 0.8)', // Week +1 - amber
  'rgba(253, 224, 71, 0.8)', // Week +2 - yellow
  'rgba(253, 230, 138, 0.8)', // Week +3 - pale yellow
  'rgba(209, 213, 219, 0.8)', // Week +4 - light gray
  'rgba(209, 213, 219, 0.8)', // Week +5 - light gray
  'rgba(209, 213, 219, 0.8)', // Week +6 - light gray
];
const backgroundColors = labels.map((_, i) => urgencyColors[i] || 'rgba(156, 163, 175, 0.8)');
```

Check the canvas container height in `detail_panel.js` or the panel HTML and increase it if it was sized for 4 bars. With `maintainAspectRatio: false` and a `responsive: true` chart, the height comes from CSS — verify the container's explicit height accommodates 8 bars.

**Warning signs:**

- All bars beyond week 2 render in the same gray color.
- Bars are too narrow to right-click comfortably on the normal panel width.

**Phase to address:** Expiration Chart Expansion phase (this is a standalone phase — low risk of regression if done before the call status work).

---

### Pitfall 7: The SQL Expiration Query Hard-Codes a 21-Day Window

**What goes wrong:**
The expiration chart query in `legacy.php` hard-codes `DATE_ADD(?, INTERVAL 21 DAY)` (3 weeks out). Extending the chart to 8 weeks requires expanding this window to 56 days. If the PHP query is not updated in sync with the JS label changes, the API returns data for only 4 buckets, the JavaScript renders only 4 bars, and the new week labels are silently absent with no error.

**Why it happens:**
The 21-day value appears in at least three places in the SQL: the WHEN clause for `'Week +2'`, the final `AND paid_thru <= DATE_ADD(?, INTERVAL 21 DAY)` filter, and the ORDER BY CASE. Adding weeks 3-7 requires adding new WHEN clauses, extending the filter, and extending the ORDER BY — all of which must stay in sync with the JavaScript `renderExpirationChart()` label assignments.

**How to avoid:**
Make the number of weeks a PHP constant (`EXPIRATION_WEEKS = 8`) and build the SQL CASE statement dynamically from it. This keeps the SQL and the data contract self-consistent. Alternatively, document the three locations clearly and add a comment: `// EXTEND HERE: add WHEN clause, update interval filter, update ORDER BY`.

The right-click context menu (`context-menu.js`) also builds week bucket labels for the drill-down query. Verify it uses the same label strings as the chart, or it will send the wrong `week_bucket` parameter to the API.

**Warning signs:**

- Chart renders only 4 bars after the SQL is "updated" but JS now expects 8.
- Context menu drill-down returns wrong subscriber list for weeks beyond +2.

**Phase to address:** Expiration Chart Expansion phase. Update SQL, JS labels, ORDER BY, and context menu in one coordinated change.

---

### Pitfall 8: `parseDate()` in NewStartsImporter Only Handles `M/D/YY` — MyCommPilot Uses Same Format but Different Context

**What goes wrong:**
`NewStartsImporter::parseDate()` already handles `M/D/YY` format correctly (the 2-digit year ambiguity rule: `< 50` → 2000s, `>= 50` → 1900s). The call log scraper will need to parse the same date format from BroadWorks (`3/20/26 8:02 AM` includes a time component). The risk is not in the date parser itself — it is in copy-pasting the date-only regex into the call log scraper without accounting for the time component, producing a null return from `parseDate()` for every call log row.

The secondary risk is in the AllSubscriberImporter: the `Phone` column in Newzware CSVs has never been normalized, and there is no guarantee it stays stable across Newzware version changes. A Newzware update that changes the phone format (e.g., from `(864) 973-7731` to `864.973.7731`) would not break the import but would break phone matching silently.

**Why it happens:**
Date parsing was built for one known format and works. When the same format appears in a new context with extra data (timestamp suffix), the regex either needs to be extended or the time portion needs to be stripped first. The `M/D/YY h:mm AM/PM` format from BroadWorks needs to be split on space and the date portion extracted before applying the existing `M/D/YY` regex.

**How to avoid:**
For the call log scraper: strip the time portion before parsing date, then apply the existing M/D/YY logic. Store the full `call_datetime` string as VARCHAR and separately store `call_timestamp` as a proper DATETIME column (the schema suggestion in the integration doc includes this). Parse to DATETIME using PHP's `DateTime::createFromFormat('n/j/y g:i A', $datetimeStr)` — this handles both date and time in one call and is unambiguous.

For phone format resilience: document in `AllSubscriberImporter.php` that the `Phone` column format is assumed to be Newzware's current output. If phone matching breaks, the first diagnostic step is to check the raw phone format in a new CSV.

**Warning signs:**

- `call_timestamp` is NULL for all rows after import.
- `parseCallLogs()` returns entries but `call_timestamp` conversion fails silently.

**Phase to address:** Call Log Scraper phase. Use `DateTime::createFromFormat()` from the start — do not port the M/D/YY-only parser.

---

## Technical Debt Patterns

| Shortcut                                                                            | Immediate Benefit                 | Long-term Cost                                                                                                                   | When Acceptable                                                             |
| ----------------------------------------------------------------------------------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Store call log `call_datetime` as VARCHAR only, skip DATETIME column                | Simpler insert, no parsing needed | Cannot query "calls in last 7 days" without ugly string comparisons; prevents date-range filtering                               | Never — parse to DATETIME at insert time                                    |
| Normalize phone numbers at query time with REGEXP_REPLACE in JOIN                   | No schema change needed           | Non-sargable JOIN causes full table scans; gets worse as call_logs grows                                                         | Only during initial spike/prototype; must fix before shipping               |
| Copy `renderExpirationChart()` logic for call status chart rather than extending it | Fast to implement                 | Two parallel charts with duplicate color/config code; both need updating on future changes                                       | Acceptable only if the charts are truly separate UI components              |
| Skip the "Private" caller filter in the scraper                                     | Fewer lines of code               | Private entries in call_logs create false noise; a subscriber phone of "Private" will never match                                | Never — filter at import, it's one line                                     |
| Re-use existing `exportToExcel()` without modification for call status export       | No new code                       | Call status color coding exists only in DOM inline styles, not in data objects; SheetJS `json_to_sheet` does not read DOM colors | Never — must pass status as a data field and color from the export function |

---

## Integration Gotchas

| Integration                    | Common Mistake                                                                        | Correct Approach                                                                                                                                                                                                                                                                                                         |
| ------------------------------ | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| BroadWorks/JSP login           | Treating HTTP 200 from login POST as success                                          | Check response body for `folder_contents.jsp` string (portal returns 200 even on bad password — just serves the login page again)                                                                                                                                                                                        |
| BroadWorks call logs           | Fetching all 3 call types while still "inside" a previous user context                | Must call `getCallLogs()` (which sets user context via `/Group/Members/Modify/`) per user per scrape; never assume context persists across calls to the same URL                                                                                                                                                         |
| SheetJS row coloring           | Using `exportToExcel()` with `json_to_sheet` and expecting DOM row colors to transfer | SheetJS builds from data objects only; DOM rendering has no connection to XLSX output. Must set `ws[cellRef].s.fill` explicitly in the export function based on a `call_status` field in the data array                                                                                                                  |
| SheetJS on `xlsx-latest` CDN   | Assuming `xlsx-latest` resolves to a stable version                                   | SheetJS CDN `xlsx-latest` is a floating tag — the API can change. The `.s` (style) property requires SheetJS Pro or the community fork `xlsx-js-style`. Verify the loaded version supports cell styles before building the color export feature. If it doesn't, use `xlsx-js-style` loaded from npm or a pinned CDN URL. |
| MariaDB REGEXP_REPLACE in JOIN | Using in WHERE or JOIN ON without testing the query plan                              | REGEXP_REPLACE in a JOIN condition is never indexed. Always pre-compute normalized values as stored columns.                                                                                                                                                                                                             |
| launchd + PHP scraper on macOS | Running the scraper as a web request (HTTP) instead of CLI                            | The scraper must run via CLI (`php scraper.php`) via launchd SSH, same as `auto_process.php`. Web-triggered scrapes are blocked by Apache timeouts and expose credentials in URL parameters.                                                                                                                             |

---

## Performance Traps

| Trap                                                                       | Symptoms                                         | Prevention                                                                                                                                                                                                                                         | When It Breaks                                                                       |
| -------------------------------------------------------------------------- | ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Non-indexed phone JOIN between `subscriber_snapshots` and `call_logs`      | Expiration panel takes 3-10 seconds to load      | Add `phone_normalized` stored column + index on `call_logs`; add virtual column + index on `subscriber_snapshots`                                                                                                                                  | As soon as `call_logs` exceeds ~5,000 rows (~3 weeks of hourly scrapes)              |
| Fetching full call log history for every subscriber in the expiration view | Query returns far more rows than needed          | Limit call log lookup to a relevant time window (e.g., calls in last 30 days) and aggregate to a single "was called" boolean per phone number before joining                                                                                       | Immediately — every expiration page load would join against all historical call data |
| Rebuilding call status for all subscribers on every dashboard load         | Page load slows proportional to subscriber count | Do not recalculate call status server-side on every request; cache the "was called" set at the API level (same SimpleCache.php already used for other endpoints)                                                                                   | After ~500 expiring subscribers, noticeable on Synology                              |
| Chart.js re-rendering with `destroy()` + `new Chart()` on every panel open | Short flash/blank canvas on fast re-opens        | Existing pattern already handles this correctly; do not change the destroy-before-create flow for the expiration chart. Pitfall is adding a second `new Chart()` call elsewhere (e.g., in the call status click handler) without destroying first. | On every panel re-open if a second instance is created                               |

---

## Security Mistakes

| Mistake                                                                   | Risk                                                                       | Prevention                                                                                                                                                 |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Logging MyCommPilot credentials in the scraper error log                  | Credentials exposed in `~/Library/Logs/circulation/auto-import.log`        | Log only "authentication failed" — never log the password string, even in debug mode. The log file path is not gitignored.                                 |
| Storing `.env.mycommpilot` as a web-accessible file                       | Credentials browsable at `https://cdash.upstatetoday.com/.env.mycommpilot` | Apache on Synology needs `.htaccess` or `DirectoryIndex` rules to block `.env*` files. Verify this is blocked — other `.env` files have the same exposure. |
| Running the scraper with `error_display = On` in the web request path     | Full stack trace with file paths visible to browser                        | Scraper runs CLI only (launchd), not via web. This is a constraint, not a vulnerability, as long as no web trigger endpoint is added.                      |
| Call log data contains real subscriber phone numbers in `call_logs` table | Cross-table PII aggregation                                                | Already present in `subscriber_snapshots`; no new risk class. Follow existing pattern: no unauthenticated API access to subscriber data.                   |

---

## UX Pitfalls

| Pitfall                                                                 | User Impact                                                                                        | Better Approach                                                                                                                                                                                                                                        |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Showing call status for placed calls only                               | Staff see calls Brittany/Chloe made but not calls subscribers returned                             | Show status from all 3 call types (placed + received + missed) in the overlay. A received call from a subscriber number is as meaningful as a placed call to them.                                                                                     |
| "Private" caller indicator in the overlay                               | One or more rows may show a phone icon erroneously if "Private" phone values slip through matching | Filter at DB insert time (`WHERE phone != 'Private'` in scraper); verify via spot-check on overlay                                                                                                                                                     |
| Row color for call status clashing with existing alternating row colors | Users cannot distinguish "called" from "uncalled" rows                                             | Use a distinct indicator: a phone icon in a new column rather than full row background color. If using row background, the "called" color must contrast against both white (odd rows) and `#F0FDFA` (even rows — the existing teal alternating color). |
| Changing row color in HTML but not propagating it to XLSX               | Staff exports appear without call status coloring                                                  | The XLSX export in `export-utils.js` uses `json_to_sheet` from data objects — DOM row colors do not transfer. Add a `call_status` field to the subscriber data object and set `ws[cellRef].s.fill` in the export loop explicitly.                      |

---

## "Looks Done But Isn't" Checklist

- [ ] **Scraper:** Runs in development and logs output — verify it also handles login failure gracefully (no stack trace, just a logged error and clean exit) before deploying to production launchd.
- [ ] **Phone matching:** Call log join returns results in development — verify with real subscriber data that at least one known subscriber phone matches a known call log entry end-to-end.
- [ ] **XLSX export with call status:** Export button produces a file — open the file in Excel/Numbers and verify the call status color appears on rows, not just in the HTML table.
- [ ] **8-week chart:** Chart renders 8 bars — right-click each bar and verify the context menu drill-down returns the correct subscriber list for weeks beyond +2.
- [ ] **Scraper dedup:** Run the scraper twice within one minute — verify `call_logs` row count does not double (INSERT IGNORE is working).
- [ ] **"Private" callers:** Insert a mock `Private` phone call log entry — verify it does not match any subscriber and does not appear as a false positive call status indicator.
- [ ] **Newstarts date fix:** After the `DateTime::createFromFormat()` change, re-import a historical NewStarts CSV — verify row counts match pre-fix import exactly (no regression in parsing valid dates).
- [ ] **Cache invalidation:** After scraper inserts new call log data, verify the expiration subscriber table shows updated call status on next page load (SimpleCache TTL must be shorter than scrape interval, or cache must be cleared by the scraper).

---

## Recovery Strategies

| Pitfall                                                         | Recovery Cost | Recovery Steps                                                                                                                                                              |
| --------------------------------------------------------------- | ------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Stale session producing 0-row inserts for multiple hours        | LOW           | Check log for zero-insert runs. Restart scraper manually to get fresh session. No data loss (BroadWorks still has the 20-entry window; next successful scrape catches up).  |
| Cookie jar file leak filling /tmp                               | LOW           | `rm /tmp/mcp_*` clears orphaned files. Add shutdown handler to prevent recurrence.                                                                                          |
| Phone normalization mismatch producing zero call status matches | MEDIUM        | Add `phone_normalized` column to `call_logs`, populate with `UPDATE call_logs SET phone_normalized = REGEXP_REPLACE(phone, '[^0-9]', '')`, rebuild index. No data loss.     |
| XLSX export missing call status colors                          | LOW           | Add `call_status` field to export data object and explicit fill logic in `exportToExcel()`. Re-export.                                                                      |
| Expiration chart showing only 4 bars after SQL/JS mismatch      | LOW           | The fix is coordinated: update SQL CASE clauses + JS color array + ORDER BY + context menu labels in one commit.                                                            |
| `call_logs` table growing without bound                         | LOW           | Add a scheduled cleanup: `DELETE FROM call_logs WHERE call_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)`. Run as part of the scraper script.                                |
| SheetJS `xlsx-latest` API change breaking cell styles           | MEDIUM        | Pin to a specific SheetJS version in `index.php`. Switch to `xlsx-js-style` (drop-in fork with guaranteed cell style support) if the community version drops style support. |

---

## Pitfall-to-Phase Mapping

| Pitfall                            | Prevention Phase                                                              | Verification                                                                                            |
| ---------------------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Stale session / empty insert       | Call Log Scraper                                                              | Run scraper twice, verify dedup; simulate login failure, verify logged error                            |
| HTTPS-to-HTTP cookie bridging      | Call Log Scraper                                                              | Integration test: confirm call logs are fetched after fresh login; check HTML response length           |
| Phone number format mismatch       | Call Log Scraper (normalize on insert) + Call Status Overlay (normalize JOIN) | SELECT count of matched phones between call_logs and subscriber_snapshots > 0                           |
| Dedup key losing same-minute calls | Call Log Scraper                                                              | Documented constraint — no code verification needed                                                     |
| JOIN performance on call_logs      | Call Status Overlay                                                           | EXPLAIN query must show index usage on call_logs and subscriber_snapshots phone columns                 |
| 8-week chart color scheme          | Expiration Chart Expansion                                                    | Visual QA: all 8 bars have distinct/expected colors; no two adjacent bars are same gray                 |
| SQL/JS week count mismatch         | Expiration Chart Expansion                                                    | API returns exactly 8 buckets; chart renders 8 bars; right-click context menu works on all bars         |
| DateTime parsing regression        | Date Fix phase                                                                | Re-import historical NewStarts CSV; row count matches prior import                                      |
| XLSX missing call status colors    | Call Status Overlay                                                           | Open exported .xlsx in Excel; call status column rows have expected fill color                          |
| Credentials in logs                | Call Log Scraper                                                              | Review log output after a failed login attempt — must not contain password string                       |
| SheetJS style support              | Call Status Overlay                                                           | Load index.php in browser; in DevTools confirm SheetJS version; test cell style render in exported file |

---

## Sources

- `docs/MYCOMMPILOT-INTEGRATION.md` — BroadWorks auth flow, JSP gotchas, PHP scraper class, known limitations (20-entry limit, "Private" callers, session timeout, HTTPS-to-HTTP redirect) — HIGH confidence (first-hand reverse engineering of the actual portal)
- `web/lib/NewStartsImporter.php` — existing `parseDate()` implementation and M/D/YY format handling — HIGH confidence (codebase direct inspection)
- `web/assets/js/utils/export-utils.js` — SheetJS `json_to_sheet` usage, existing row coloring via data objects — HIGH confidence (codebase direct inspection)
- `web/assets/js/components/detail_panel.js` — `renderExpirationChart()`, 4-category color logic, `maintainAspectRatio: false` setting — HIGH confidence (codebase direct inspection)
- `web/assets/js/components/subscriber-table-panel.js` — existing alternating row colors (`#F0FDFA`) — HIGH confidence (codebase direct inspection)
- `web/api/legacy.php` — expiration SQL with hard-coded 21-day window, 4 CASE buckets, phone field usage — HIGH confidence (codebase direct inspection)
- `sql/05_add_contact_fields.sql` + `sql/06_partition_subscriber_snapshots.sql` — phone column definition (`VARCHAR(50)`, no normalization) — HIGH confidence (codebase direct inspection)
- `web/index.php` — SheetJS loaded from `cdn.sheetjs.com/xlsx-latest` (floating version tag) — HIGH confidence (codebase direct inspection)
- MariaDB documentation on generated/virtual columns and REGEXP_REPLACE — MEDIUM confidence (known behavior; verify REGEXP_REPLACE is available in the MariaDB 10.x version on the Synology)

---

_Pitfalls research for: NWDownloads v2.1 — VOIP call log scraping and call-status overlay_
_Researched: 2026-03-20_
