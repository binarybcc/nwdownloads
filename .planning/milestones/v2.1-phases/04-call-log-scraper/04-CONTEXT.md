# Phase 4: Call Log Scraper - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

BroadWorks call logs for both circulation staff lines (Brittany Carroll, Chloe Welch) are collected hourly during business hours and stored in the `call_logs` table. The scraper handles authentication, HTML parsing, phone normalization, dedup via INSERT IGNORE, and error visibility. The `call_logs` table and `phone_normalized` column already exist from Phase 3. Displaying call status in the UI is Phase 6.

</domain>

<decisions>
## Implementation Decisions

### Error handling & alerting

- Login failure: log error AND send email alert via existing EmailNotifier class
- Zero call entries for a user/type: log as info and continue (normal outside business hours or on quiet days)
- HTML structure change (page has content but regex finds no matches): log as error + email alert — distinguishes broken parsing from genuinely empty results
- Network/timeout errors: one retry after 30 seconds, then log error + email and exit
- All errors must be visible — no silent empty runs (success criteria #4)

### Scraper file & class placement

- Scraper class: `web/lib/MyCommPilotScraper.php` — follows existing importer pattern in web/lib/
- CLI runner: `web/fetch_call_logs.php` — top-level in web/, same pattern as auto_process.php
- Business-hours guard in PHP (8am-8pm ET) — script checks current time and exits early if outside window, regardless of launchd schedule
- Credentials loaded from `.env.mycommpilot` file (gitignored), read by the CLI runner

### Data mapping to call_logs schema

- Internal extensions (4-digit numbers like '6706'): store in `remote_number`, set `phone_normalized` to NULL — preserves the record but won't pollute subscriber matching
- Private callers ('Private' for name and phone): store with `remote_number` = 'Private', `phone_normalized` = NULL — records that a call happened for volume stats
- Caller names (e.g., 'Jeremy Power', 'CONDREY,GLENDA'): store in `raw_payload` TEXT column only, no dedicated name column — subscriber matching uses phone numbers, not names
- `source_group` column: store initials 'BC' or 'CW' (not full names)
- BroadWorks datetime format `M/D/YY h:mm AM/PM` must be parsed to MySQL DATETIME for `call_timestamp`
- Phone normalization: strip to bare 10-digit using same logic as AllSubscriberImporter (Phase 3)
- Dedup: INSERT IGNORE on existing UNIQUE KEY `uq_call (call_timestamp, remote_number, local_extension, call_direction)`

### Launchd schedule

- Runs hourly, 7 days a week (PHP business-hours guard handles the 8am-8pm window)
- Plist name: `com.circulation.call-scraper`
- Log file: `~/Library/Logs/circulation/call-scraper.log` (same directory as existing auto-import.log)
- Plist template committed to `scripts/` in the repo for reference — must be copied to `~/Library/LaunchAgents/` and loaded manually

### Claude's Discretion

- Exact curl options (timeouts, SSL verification, user-agent string)
- raw_payload format (JSON blob vs concatenated string)
- Log message format and verbosity levels within the decided framework
- How to structure the email alert content (subject line, body format)
- Whether to log a summary line at end of successful run (e.g., "Scraped 42 entries, 18 new")

</decisions>

<canonical_refs>

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### MyCommPilot integration

- `docs/MYCOMMPILOT-INTEGRATION.md` — Complete auth flow, HTML parsing regex, user keys, endpoint URLs, PHP class example, and all BroadWorks gotchas (HTTPS-to-HTTP redirect, 20-entry limit, session context)

### Database schema

- `database/migrations/014_add_call_logs_table.sql` — call_logs table DDL with UNIQUE KEY dedup constraint, column types, and indexes

### Existing automation pattern

- `web/auto_process.php` — CLI runner model (CLI-only guard, bootstrap, config constants, error handling pattern)
- `~/Library/LaunchAgents/com.circulation.auto-import.plist` — Existing launchd plist pattern (SSH to NAS, log path, calendar interval)

### Notification system

- `web/notifications/EmailNotifier.php` — Existing email notification class for error alerts
- `web/notifications/INotifier.php` — Notifier interface

### Phone normalization

- `web/lib/AllSubscriberImporter.php` — Contains `normalizePhone()` method used at subscriber ingest; call log normalization should match this logic

</canonical_refs>

<code_context>

## Existing Code Insights

### Reusable Assets

- `web/lib/AllSubscriberImporter.php::normalizePhone()` — 10-digit phone normalization; reuse or extract for call log phone normalization
- `web/notifications/EmailNotifier.php` — Ready-to-use email alerting for scraper failures
- `web/SimpleCache.php` — Cache clearing utility (may be useful to invalidate dashboard cache after scrape)

### Established Patterns

- CLI scripts use `php_sapi_name() !== 'cli'` guard (auto_process.php line 26)
- Timezone set explicitly: `date_default_timezone_set('America/New_York')`
- Composer autoloader: `require_once __DIR__ . '/vendor/autoload.php'` or direct requires
- Namespaced classes under `CirculationDashboard\` namespace
- PDO with prepared statements for all database operations
- Database connection via environment variables (`getenv()`)

### Integration Points

- `call_logs` table (Phase 3) — INSERT IGNORE target
- `.env.mycommpilot` — credential source (already exists, gitignored)
- `~/Library/LaunchAgents/` — launchd plist installation directory
- NAS PHP binary: `/var/packages/PHP8.2/target/usr/local/bin/php82`
- NAS web directory: `/volume1/web/circulation/`

</code_context>

<specifics>
## Specific Ideas

- The integration doc (`docs/MYCOMMPILOT-INTEGRATION.md`) has a near-complete PHP class — use it as the foundation, not as a copy-paste. Adapt it to match project conventions (namespace, error handling, logging).
- STATE.md flags a concern: "BroadWorks folder_contents.jsp probe string — verify against live portal before production". The login verification check (`str_contains($resp, 'folder_contents.jsp')`) should be validated.
- The scraper should always do a fresh login per run (sessions expire — gotcha #7 in the integration doc).

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

_Phase: 04-call-log-scraper_
_Context gathered: 2026-03-20_
