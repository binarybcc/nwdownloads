# Phase 4: Call Log Scraper - Research

**Researched:** 2026-03-20
**Domain:** BroadWorks web scraping, PHP cURL, macOS launchd scheduling
**Confidence:** HIGH

## Summary

Phase 4 builds a PHP scraper that authenticates to the BroadWorks MyCommPilot web portal, fetches HTML call log pages for two staff members (BC and CW), parses the entries, normalizes phone numbers, and inserts them into the existing `call_logs` table using INSERT IGNORE for deduplication. The scraper runs hourly via macOS launchd with a PHP business-hours guard (8am-8pm ET).

The integration doc (`docs/MYCOMMPILOT-INTEGRATION.md`) provides a near-complete PHP class with proven auth flow and HTML parsing regex. The `call_logs` table DDL (migration 014) and the `normalizePhone()` method in `AllSubscriberImporter.php` already exist from Phase 3. The `EmailNotifier` class is ready for error alerting. This phase is primarily assembly of existing, proven components.

**Primary recommendation:** Adapt the integration doc's PHP class to project conventions (namespace, PDO, error handling, email alerts), write a CLI runner matching `auto_process.php` patterns, and create a launchd plist modeled on `com.circulation.auto-import.plist`.

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

- Login failure: log error AND send email alert via existing EmailNotifier class
- Zero call entries for a user/type: log as info and continue (normal outside business hours or on quiet days)
- HTML structure change (page has content but regex finds no matches): log as error + email alert
- Network/timeout errors: one retry after 30 seconds, then log error + email and exit
- All errors must be visible -- no silent empty runs
- Scraper class: `web/lib/MyCommPilotScraper.php` (follows existing importer pattern)
- CLI runner: `web/fetch_call_logs.php` (top-level in web/, same as auto_process.php)
- Business-hours guard in PHP (8am-8pm ET) -- script checks time and exits early
- Credentials loaded from `.env.mycommpilot` file (gitignored), read by CLI runner
- Internal extensions (4-digit): store in `remote_number`, `phone_normalized` = NULL
- Private callers: `remote_number` = 'Private', `phone_normalized` = NULL
- Caller names: store in `raw_payload` TEXT column only, no dedicated name column
- `source_group` column: store initials 'BC' or 'CW'
- BroadWorks datetime `M/D/YY h:mm AM/PM` parsed to MySQL DATETIME
- Phone normalization: same logic as AllSubscriberImporter
- Dedup: INSERT IGNORE on UNIQUE KEY `uq_call (call_timestamp, remote_number, local_extension, call_direction)`
- Launchd runs hourly, 7 days/week; PHP handles business-hours window
- Plist name: `com.circulation.call-scraper`
- Log file: `~/Library/Logs/circulation/call-scraper.log`
- Plist committed to `scripts/` for reference, copied to `~/Library/LaunchAgents/` manually

### Claude's Discretion

- Exact curl options (timeouts, SSL verification, user-agent string)
- raw_payload format (JSON blob vs concatenated string)
- Log message format and verbosity levels
- Email alert content (subject line, body format)
- Whether to log a summary line at end of successful run

### Deferred Ideas (OUT OF SCOPE)

None -- discussion stayed within phase scope.
</user_constraints>

<phase_requirements>

## Phase Requirements

| ID      | Description                                                                                               | Research Support                                                                                                                                                              |
| ------- | --------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CALL-01 | BroadWorks call logs (placed, received, missed) for BC and CW are scraped and stored in `call_logs` table | Integration doc provides complete auth flow, user keys, endpoint URLs, and HTML parsing regex. Migration 014 provides table DDL.                                              |
| CALL-03 | Scraper runs hourly 8am-8pm ET via launchd, with business-hours guard in PHP                              | Existing `com.circulation.auto-import.plist` provides template. `StartCalendarInterval` array supports hourly scheduling. PHP `date('G')` check handles business-hours guard. |
| CALL-04 | Scraper verifies login success and logs failures (no silent empty runs)                                   | Login verification via `str_contains($resp, 'folder_contents.jsp')` documented in integration doc. EmailNotifier class ready for alerts.                                      |

</phase_requirements>

## Standard Stack

### Core

| Library       | Version            | Purpose                       | Why Standard                                                    |
| ------------- | ------------------ | ----------------------------- | --------------------------------------------------------------- |
| PHP cURL      | Built-in (PHP 8.2) | HTTP requests with cookie jar | Already available on NAS; integration doc uses cURL exclusively |
| PDO (mysql)   | Built-in (PHP 8.2) | Database access               | Project standard -- all existing importers use PDO              |
| EmailNotifier | Existing class     | Error alerting                | Already built and tested for file processing failures           |

### Supporting

| Library                | Version  | Purpose                                     | When to Use                                                  |
| ---------------------- | -------- | ------------------------------------------- | ------------------------------------------------------------ |
| `html_entity_decode()` | Built-in | Decode BroadWorks HTML entities             | Parsing call log HTML (hex entities like `&#x20;`, `&#x2F;`) |
| `preg_match_all()`     | Built-in | Extract call log entries from HTML          | Regex pattern proven in integration doc                      |
| `DateTime`             | Built-in | Parse `M/D/YY h:mm AM/PM` to MySQL DATETIME | Date conversion for `call_timestamp` column                  |

### Alternatives Considered

| Instead of    | Could Use   | Tradeoff                                                                                   |
| ------------- | ----------- | ------------------------------------------------------------------------------------------ |
| cURL          | Guzzle HTTP | Guzzle is cleaner API but adds Composer dependency; cURL already proven in integration doc |
| Regex parsing | DOMDocument | DOM parser is fragile with BroadWorks malformed HTML; regex is proven                      |

**Installation:** No new packages needed. All functionality is built into PHP 8.2.

## Architecture Patterns

### Recommended Project Structure

```
web/
  lib/
    MyCommPilotScraper.php    # Scraper class (auth, fetch, parse)
  fetch_call_logs.php          # CLI runner (bootstrap, config, orchestrate)
  notifications/
    EmailNotifier.php          # Existing -- reused for alerts
scripts/
  com.circulation.call-scraper.plist  # Launchd template (committed for reference)
```

### Pattern 1: CLI Runner with Bootstrap

**What:** Top-level PHP script that guards CLI-only access, sets timezone, loads credentials, connects DB, and orchestrates the scraper class.
**When to use:** All automated scripts in this project follow this pattern.
**Example:**

```php
// Source: web/auto_process.php (existing pattern)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}
date_default_timezone_set('America/New_York');

// Business-hours guard (8am-8pm ET)
$hour = (int) date('G');
if ($hour < 8 || $hour >= 20) {
    echo "[" . date('Y-m-d H:i:s') . "] Outside business hours ($hour:00). Exiting.\n";
    exit(0);
}
```

### Pattern 2: Scraper Class with Single-Responsibility Methods

**What:** Class handles auth, user context switching, HTML fetching, and parsing. CLI runner handles DB inserts and error routing.
**When to use:** Keeps scraper testable and reusable; matches importer pattern where class does data extraction and caller does persistence.
**Example:**

```php
// Source: docs/MYCOMMPILOT-INTEGRATION.md (adapted)
namespace CirculationDashboard;

class MyCommPilotScraper {
    public function login(): bool { /* auth flow */ }
    public function getCallLogs(string $userKey, string $type): array { /* fetch+parse */ }
    public function logout(): void { /* cleanup */ }
}
```

### Pattern 3: INSERT IGNORE Dedup

**What:** Use INSERT IGNORE with the UNIQUE KEY `uq_call` to silently skip duplicate rows.
**When to use:** Every scrape run -- the 20-entry rolling window will overlap with previously scraped data.
**Example:**

```php
$stmt = $pdo->prepare("
    INSERT IGNORE INTO call_logs
        (call_direction, call_timestamp, remote_number, phone_normalized,
         local_extension, source_group, raw_payload)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
```

### Anti-Patterns to Avoid

- **Reusing sessions across runs:** BroadWorks sessions expire. Always do a fresh login per run (gotcha #7 in integration doc).
- **Treating zero results as error:** Outside business hours or on quiet days, zero calls is normal. Only flag as error when HTML has content but regex finds no matches (broken parsing).
- **Storing `local_extension` as the 4-digit number:** The `local_extension` column should store the BroadWorks extension (`8649736678EgP` suffix or similar), not the internal 4-digit number. The UNIQUE KEY uses this for dedup. Use the last 10 digits of the user key or a consistent identifier.

## Don't Hand-Roll

| Problem                          | Don't Build           | Use Instead                                        | Why                                                    |
| -------------------------------- | --------------------- | -------------------------------------------------- | ------------------------------------------------------ |
| Phone normalization              | Custom regex          | Copy `normalizePhone()` from AllSubscriberImporter | Must match exactly for subscriber JOIN to work         |
| Email alerting                   | Custom mail() calls   | EmailNotifier class                                | Already handles settings, recipients, headers          |
| Cookie management                | Manual header parsing | cURL CURLOPT_COOKIEFILE/COOKIEJAR                  | Handles HTTPS-to-HTTP cookie transfer automatically    |
| Date parsing (M/D/YY h:mm AM/PM) | String manipulation   | `DateTime::createFromFormat('n/j/y g:i A', $str)`  | Handles all edge cases (single-digit month/day, AM/PM) |

**Key insight:** The integration doc's PHP class is 90% of the scraper. The work is adapting it to project conventions, adding error handling/alerting, and wiring up the INSERT IGNORE persistence.

## Common Pitfalls

### Pitfall 1: HTTPS-to-HTTP Cookie Transfer

**What goes wrong:** Login happens over HTTPS but portal redirects to HTTP port 80. Cookies set on HTTPS may not transfer.
**Why it happens:** BroadWorks portal architecture; cURL cookie jar must handle both protocols.
**How to avoid:** Use `CURLOPT_COOKIEFILE` and `CURLOPT_COOKIEJAR` pointing to the same temp file. Set `CURLOPT_FOLLOWLOCATION => true`. The integration doc's class already handles this correctly.
**Warning signs:** Login appears successful but call log pages return login form HTML.

### Pitfall 2: Silent Empty Runs

**What goes wrong:** Scraper runs, finds zero entries, logs nothing, exits 0. No one notices data stopped flowing.
**Why it happens:** HTML structure changes or login silently fails.
**How to avoid:** Distinguish three cases: (1) genuinely empty results (log info, OK), (2) HTML has content but no regex matches (broken parsing -- log error + email), (3) login failure detected (log error + email). Check `str_contains($resp, 'folder_contents.jsp')` after login POST.
**Warning signs:** `call_logs` table stops growing for 24+ hours.

### Pitfall 3: `local_extension` Dedup Mismatch

**What goes wrong:** INSERT IGNORE dedup fails if `local_extension` value is inconsistent between runs.
**Why it happens:** The UNIQUE KEY includes `local_extension`. If one run stores '8649736678' and another stores '8649736678EgP', same call gets double-inserted.
**How to avoid:** Decide on a canonical value for `local_extension` per user and use it consistently. Recommendation: use the 10-digit phone number portion ('8649736678' for BC, '8649736689' for CW) since that is the actual extension.
**Warning signs:** Duplicate rows appearing with slightly different `local_extension` values.

### Pitfall 4: DateTime Parsing Edge Cases

**What goes wrong:** `M/D/YY h:mm AM/PM` format has single-digit months and days. Midnight shows as `12:00 AM`.
**Why it happens:** BroadWorks uses US locale datetime formatting.
**How to avoid:** Use `DateTime::createFromFormat('n/j/y g:i A', $dateStr)` which handles single-digit month (`n`), single-digit day (`j`), 2-digit year (`y`), and 12-hour time with AM/PM (`g:i A`).
**Warning signs:** NULL `call_timestamp` values in database, or parse errors in log.

### Pitfall 5: `.env.mycommpilot` Not Gitignored

**What goes wrong:** Credentials could be committed to the repository.
**Why it happens:** The `.gitignore` currently has `.env` and `.env.credentials` patterns but NOT `.env.mycommpilot` explicitly.
**How to avoid:** Add `.env.mycommpilot` to `.gitignore` as part of Phase 4 implementation. File is currently untracked but not protected.
**Warning signs:** `git status` showing `.env.mycommpilot` as untracked (it currently does).

### Pitfall 6: EmailNotifier Requires ProcessResult Object

**What goes wrong:** `EmailNotifier::sendFailure()` expects a `ProcessResult` object, not a plain string.
**Why it happens:** The notifier was built for file processing, not scraper errors.
**How to avoid:** Either (a) create a `ProcessResult` with the scraper error details, or (b) use PHP `mail()` directly for scraper-specific alerts bypassing the existing notifier, or (c) add a simpler `sendAlert(string $subject, string $body)` method. Option (c) is cleanest.
**Warning signs:** Type errors when trying to call `sendFailure()` with scraper context.

## Code Examples

### BroadWorks DateTime to MySQL DATETIME

```php
// Source: PHP DateTime::createFromFormat docs
$bwDatetime = '3/20/26 8:02 AM';
$dt = DateTime::createFromFormat('n/j/y g:i A', $bwDatetime);
if ($dt === false) {
    // Handle parse failure
    throw new Exception("Cannot parse BroadWorks datetime: $bwDatetime");
}
$mysqlDatetime = $dt->format('Y-m-d H:i:s');
// Result: '2026-03-20 08:02:00'
```

### Phone Normalization (from AllSubscriberImporter)

```php
// Source: web/lib/AllSubscriberImporter.php line 757-765
private function normalizePhone(string $phone): ?string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    return (strlen($digits) === 10) ? $digits : null;
}
```

### Launchd Hourly Schedule (Array of CalendarIntervals)

```xml
<!-- Source: Apple launchd.plist man page -->
<key>StartCalendarInterval</key>
<array>
    <!-- Run at minute 5 of every hour, every day -->
    <dict>
        <key>Minute</key>
        <integer>5</integer>
    </dict>
</array>
```

Note: A single `<dict>` with only `<key>Minute</key>` triggers once per hour at that minute, every hour, every day. The PHP business-hours guard handles the 8am-8pm window. No need for 12 separate dict entries.

### EmailNotifier Integration Consideration

```php
// Source: web/notifications/EmailNotifier.php
// Current interface requires ProcessResult object:
//   public function sendFailure(ProcessResult $result): void

// For scraper alerts, simplest approach: use mail() directly
// since scraper errors don't map to file processing results
function sendScraperAlert(string $subject, string $body): void
{
    // Load recipients from notification_settings table or hardcode
    $to = 'jcorbin@upstatetoday.com';
    $headers = "From: Circulation Dashboard <noreply@upstatetoday.com>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    mail($to, $subject, $body, $headers);
}
```

## State of the Art

| Old Approach     | Current Approach     | When Changed          | Impact                                                        |
| ---------------- | -------------------- | --------------------- | ------------------------------------------------------------- |
| XSI REST API     | Web scraping         | N/A (XSI unavailable) | Must parse HTML; no JSON/XML option                           |
| Python prototype | PHP production class | Phase 4               | Matches project stack; runs on NAS natively                   |
| cron on NAS      | launchd on Mac + SSH | Phase 4               | Synology Task Scheduler deletes jobs; Mac launchd is reliable |

**Deprecated/outdated:**

- Python `fetch_call_logs.py` prototype: Was proof-of-concept; PHP implementation replaces it
- Synology Task Scheduler: Deletes jobs; never use for this project

## Open Questions

1. **`folder_contents.jsp` Probe String Verification**
   - What we know: Login verification checks `str_contains($resp, 'folder_contents.jsp')` in the redirect response
   - What's unclear: STATE.md flags this needs verification against the live portal before production
   - Recommendation: Plan should include a manual verification step before deploying. Could also add a secondary check (e.g., verify the HTTP session page loads successfully after step 3 of auth flow).

2. **EmailNotifier vs Direct mail() for Scraper Alerts**
   - What we know: `EmailNotifier` requires `ProcessResult` object; scraper errors don't map to file processing context
   - What's unclear: Whether to extend EmailNotifier with a generic alert method or use mail() directly
   - Recommendation: Use mail() directly in the CLI runner for simplicity. The scraper is a separate concern from file processing. Can refactor later if more notification sources emerge.

3. **`local_extension` Column Value**
   - What we know: The UNIQUE KEY includes `local_extension`. CONTEXT.md says `source_group` stores 'BC'/'CW'. The migration has `local_extension VARCHAR(20) DEFAULT ''`.
   - What's unclear: What value to store in `local_extension` -- the 10-digit phone (8649736678), the BroadWorks user ID (8649736678EgP), or the initials (BC/CW)?
   - Recommendation: Store the 10-digit phone number (8649736678 for BC, 8649736689 for CW). It is stable, unique per line, and meaningful. `source_group` separately stores 'BC'/'CW' for display.

## Validation Architecture

### Test Framework

| Property           | Value                                                     |
| ------------------ | --------------------------------------------------------- |
| Framework          | PHPUnit (if installed) or manual verification             |
| Config file        | None detected -- no phpunit.xml in project                |
| Quick run command  | `php web/fetch_call_logs.php` (manual run on NAS via SSH) |
| Full suite command | Manual verification per success criteria                  |

### Phase Requirements to Test Map

| Req ID  | Behavior                                          | Test Type   | Automated Command                                                                                                                                                                                                                                                                               | File Exists? |
| ------- | ------------------------------------------------- | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| CALL-01 | Call logs for BC and CW stored in call_logs       | smoke       | `ssh nas "/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/fetch_call_logs.php" && ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p... circulation_dashboard -e 'SELECT source_group, call_direction, COUNT(*) FROM call_logs GROUP BY source_group, call_direction'"` | Wave 0       |
| CALL-03 | Hourly launchd schedule with business-hours guard | manual-only | Verify plist loaded: `launchctl list com.circulation.call-scraper`                                                                                                                                                                                                                              | Wave 0       |
| CALL-04 | Login failure logged, no silent empty runs        | smoke       | Test with wrong password, verify log output and email                                                                                                                                                                                                                                           | Wave 0       |

### Sampling Rate

- **Per task commit:** Manual SSH run of `fetch_call_logs.php` on NAS
- **Per wave merge:** Full verification of all 3 requirements
- **Phase gate:** All success criteria verified before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `.env.mycommpilot` must be added to `.gitignore`
- [ ] No automated test infrastructure for PHP in this project -- all verification is manual/smoke

## Sources

### Primary (HIGH confidence)

- `docs/MYCOMMPILOT-INTEGRATION.md` -- Complete auth flow, HTML parsing, PHP class, gotchas (project-local)
- `database/migrations/014_add_call_logs_table.sql` -- call_logs DDL (project-local)
- `web/auto_process.php` -- CLI runner pattern (project-local)
- `web/lib/AllSubscriberImporter.php` -- normalizePhone() method (project-local)
- `web/notifications/EmailNotifier.php` -- Email alert class (project-local)
- `~/Library/LaunchAgents/com.circulation.auto-import.plist` -- Existing launchd pattern (project-local)

### Secondary (MEDIUM confidence)

- PHP `DateTime::createFromFormat` -- built-in function, well-documented
- macOS `launchd.plist` StartCalendarInterval -- Apple developer documentation

### Tertiary (LOW confidence)

- None -- all findings verified from project-local sources

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH -- all built-in PHP, no external dependencies
- Architecture: HIGH -- following established project patterns exactly
- Pitfalls: HIGH -- integration doc explicitly lists gotchas; codebase inspection confirms EmailNotifier constraint and gitignore gap

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable -- BroadWorks portal unlikely to change)
