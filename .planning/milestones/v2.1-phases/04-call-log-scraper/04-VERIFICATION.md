---
phase: 04-call-log-scraper
verified: 2026-03-20T18:30:00Z
status: human_needed
score: 8/8 must-haves verified
re_verification: false
human_verification:
  - test: 'Run the scraper on the NAS and confirm DB rows exist for BC and CW'
    expected: 'SELECT source_group, call_direction, COUNT(*) FROM call_logs GROUP BY source_group, call_direction returns rows for both BC and CW across placed/received/missed — matches 04-02-SUMMARY Test 3 result'
    why_human: 'Cannot SSH to NAS during verification to query call_logs table directly. 04-02-SUMMARY reports 120 entries scraped / 119 new but this was at SUMMARY write time; needs confirmation that production DB still contains those rows and did not get dropped by a schema migration or rollback.'
  - test: 'Run the scraper twice in one minute and confirm second run reports New: 0'
    expected: "Second run output shows 'New: 0' — INSERT IGNORE dedup works correctly"
    why_human: 'INSERT IGNORE dedup depends on the UNIQUE KEY on (call_timestamp, remote_number, local_extension, call_direction) existing in the live DB. Cannot verify the unique key constraint is in place without connecting to production MariaDB.'
  - test: 'Confirm login probe string is correct against live portal'
    expected: "Scraper login succeeds — STATE.md flags this as an open concern: 'BroadWorks folder_contents.jsp probe string — verify against live portal before production'"
    why_human: 'Cannot make HTTP requests to https://ws2.mycommpilot.com during verification. 04-02-SUMMARY reports login succeeded implicitly (120 entries were scraped), which is strong evidence — but the STATE.md concern is worth explicit confirmation.'
---

# Phase 4: Call Log Scraper Verification Report

**Phase Goal:** BroadWorks call logs for both circulation staff lines are collected hourly and stored reliably — any failure is visible, no silent empty runs
**Verified:** 2026-03-20T18:30:00Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #   | Truth                                                                                                              | Status     | Evidence                                                                                                                                                                                                                                                       |
| --- | ------------------------------------------------------------------------------------------------------------------ | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Running the scraper twice in one minute does not double the row count (INSERT IGNORE dedup)                        | ? HUMAN    | `INSERT IGNORE INTO call_logs` confirmed in fetch_call_logs.php:129. UNIQUE KEY exists in migration schema per PLAN interfaces. Dedup confirmed in 04-02-SUMMARY Test 2 ("120 scraped, 0 new"). Cannot verify unique key is live on NAS DB without SSH access. |
| 2   | call_logs table contains placed, received, and missed entries for both BC and CW                                   | ? HUMAN    | Both users configured in fetch_call_logs.php:92-95 with correct keys/groups/extensions. All three $callTypes iterated. 04-02-SUMMARY Test 3 confirms DB rows existed post-run. Cannot query live DB to confirm rows still present.                             |
| 3   | Scraper runs automatically on an hourly schedule during business hours (8am-8pm ET) via macOS launchd              | ✓ VERIFIED | plist loaded and LastExitStatus=0 confirmed via `launchctl list com.circulation.call-scraper`. Minute=5 with no Hour/Weekday key = runs every hour. Business-hours guard at fetch_call_logs.php:30 (`$hour < 8 \|\| $hour >= 20`).                             |
| 4   | When login to MyCommPilot fails, an error is written to the log and the script exits cleanly — no silent empty run | ✓ VERIFIED | fetch_call_logs.php:105-122: login failure triggers `log_msg()`, `send_alert()`, and `exit(1)`. Login retry with new scraper instance on first failure. Two-attempt logic confirmed in code.                                                                   |

**Score (automated checks):** 2/4 truths fully verified programmatically, 2/4 require human confirmation of live DB state (code correct, execution unverifiable without SSH)

### Required Artifacts

| Artifact                                     | Expected                                                                        | Status     | Details                                                                |
| -------------------------------------------- | ------------------------------------------------------------------------------- | ---------- | ---------------------------------------------------------------------- |
| `web/lib/MyCommPilotScraper.php`             | BroadWorks auth, user context switching, HTML fetching, call log parsing        | ✓ VERIFIED | Exists, 257 lines, all required methods present, PHP syntax clean      |
| `web/fetch_call_logs.php`                    | CLI runner with scraper orchestration, DB inserts, error handling, email alerts | ✓ VERIFIED | Exists, 239 lines, all required patterns present, PHP syntax clean     |
| `.gitignore`                                 | Prevents .env.mycommpilot from being committed                                  | ✓ VERIFIED | `.env.mycommpilot` on line 7 of .gitignore                             |
| `scripts/com.circulation.call-scraper.plist` | Launchd plist template for hourly call log scraping                             | ✓ VERIFIED | Exists, passes `plutil -lint`, loaded in launchd with LastExitStatus=0 |

### Key Link Verification

| From                                         | To                               | Via                                  | Status  | Details                                                                                                                           |
| -------------------------------------------- | -------------------------------- | ------------------------------------ | ------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `web/fetch_call_logs.php`                    | `web/lib/MyCommPilotScraper.php` | require_once and class instantiation | ✓ WIRED | `require_once __DIR__ . '/lib/MyCommPilotScraper.php'` at line 24; `new MyCommPilotScraper(` at lines 102 and 108                 |
| `web/fetch_call_logs.php`                    | `call_logs table`                | PDO INSERT IGNORE                    | ✓ WIRED | `INSERT IGNORE INTO call_logs` at line 129, all 7 columns bound, `$stmt->execute()` at line 166                                   |
| `web/lib/MyCommPilotScraper.php`             | `https://ws2.mycommpilot.com`    | cURL with cookie jar                 | ✓ WIRED | `CURLOPT_COOKIEJAR` and `CURLOPT_COOKIEFILE` set in constructor; `$this->baseUrl = 'https://ws2.mycommpilot.com'` used in login() |
| `scripts/com.circulation.call-scraper.plist` | `web/fetch_call_logs.php`        | SSH to NAS running PHP CLI           | ✓ WIRED | `fetch_call_logs.php` in ProgramArguments array; NAS SSH shortcut `nas` confirmed                                                 |

### Requirements Coverage

| Requirement | Source Plan   | Description                                                                      | Status      | Evidence                                                                                                                                                       |
| ----------- | ------------- | -------------------------------------------------------------------------------- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CALL-01     | 04-01-PLAN.md | BroadWorks call logs (placed, received, missed) for BC and CW scraped and stored | ✓ SATISFIED | Both users configured with correct keys, all 3 call types iterated, INSERT IGNORE stores to call_logs. REQUIREMENTS.md marks as complete.                      |
| CALL-03     | 04-02-PLAN.md | Scraper runs hourly 8am-8pm ET via launchd, business-hours guard in PHP          | ✓ SATISFIED | Plist loaded with Minute=5 (no Hour/Weekday = every hour). PHP guard at line 30 enforces 8-20 window. `launchctl list` shows job active with LastExitStatus=0. |
| CALL-04     | 04-01-PLAN.md | Scraper verifies login success and logs failures (no silent empty runs)          | ✓ SATISFIED | Login verified via `str_contains($loginResponse, 'folder_contents.jsp')`. Failure triggers log + email alert + exit(1). Broken parsing also triggers alert.    |

No orphaned requirements: all three requirement IDs (CALL-01, CALL-03, CALL-04) appear in plan frontmatter and are verified in code.

### Anti-Patterns Found

| File       | Line | Pattern | Severity | Impact                                                           |
| ---------- | ---- | ------- | -------- | ---------------------------------------------------------------- |
| None found | —    | —       | —        | No TODO, FIXME, placeholder, or stub patterns in either PHP file |

### Human Verification Required

#### 1. Live DB Row Confirmation

**Test:** SSH to NAS and run: `ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p'P@ta675N0id' circulation_dashboard -e \"SELECT source_group, call_direction, COUNT(*) as cnt FROM call_logs GROUP BY source_group, call_direction\""`
**Expected:** Rows for BC and CW with placed/received/missed counts — same result as 04-02-SUMMARY Test 3
**Why human:** Cannot SSH to NAS during automated verification. Code is correct; this confirms the data persisted.

#### 2. INSERT IGNORE Dedup Live Test

**Test:** Run the scraper twice in quick succession and confirm second run reports `New: 0`
**Expected:** Output line: `=== Done. Scraped: N, New: 0 ===`
**Why human:** Dedup depends on the UNIQUE KEY `uq_call` existing in the live schema (from migration 014). Cannot verify the constraint is in place on production without connecting to the DB.

#### 3. Login Probe String Against Live Portal

**Test:** Confirm a successful scraper run logs "Login successful." (04-02-SUMMARY already reports this was verified, but STATE.md flags it as a concern)
**Expected:** Log output includes `Login successful.` followed by entry counts
**Why human:** Cannot make HTTP requests to ws2.mycommpilot.com. The 04-02-SUMMARY verification (120 scraped, 119 new) is strong corroborating evidence that login succeeded.

### Gaps Summary

No gaps. All artifacts exist and are substantive (well beyond stub threshold). All key links are wired. All three requirement IDs are satisfied in code. The three human verification items are confirmation tests for live DB state — the code itself fully implements the goal.

The 04-02-SUMMARY already documents passing results for all three human verification tests (manual scraper run, dedup test, DB data check). These items are flagged `human_needed` because the verifier cannot independently query the production NAS DB, not because there is reason to doubt the implementation.

---

## Artifact Detail

### web/lib/MyCommPilotScraper.php (257 lines)

All required methods confirmed present:

- `public function login(): bool` — three-step auth flow, `str_contains` probe on `folder_contents.jsp`
- `public function logout(): void`
- `public function getCallLogs(string $userKey, string $type): array` — user context switch + HTML fetch + broken-parsing detection
- `private function parseCallLogs(string $html): array` — regex `/<\/td>\s*<td\s*>([^<\n]+)/`, html_entity_decode, date-validation grouping
- `public function normalizePhone(string $phone): ?string` — exact match to AllSubscriberImporter logic
- `public function parseBroadWorksDatetime(string $bwDatetime): ?string` — `DateTime::createFromFormat('n/j/y g:i A', ...)`
- `public function hasContent(string $html): bool` — `strlen($html) > 500`
- `private function get(string $url): string|false`
- `private function post(string $url, array $data): string|false`

cURL configured with: CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE (same tempnam file), CURLOPT_TIMEOUT=30, CURLOPT_CONNECTTIMEOUT=10, CURLOPT_USERAGENT.

### web/fetch_call_logs.php (239 lines)

All required patterns confirmed present:

- CLI guard (`php_sapi_name() !== 'cli'`)
- Timezone (`America/New_York`)
- Business-hours guard (line 30: `$hour < 8 || $hour >= 20`)
- Lock file (LOCK_FILE constant, stale-lock detection, `register_shutdown_function` cleanup)
- `.env.mycommpilot` credential loading with missing-file and missing-key alerts
- Both user records (BC: `8649736678`, CW: `8649736689`) with correct BroadWorks keys
- Login with retry (new scraper instance on first failure, `send_alert` + `exit(1)` on second failure)
- `INSERT IGNORE INTO call_logs` with all 7 columns bound via prepared statement
- `RuntimeException` catch → log + `send_alert` + `continue`
- `$scraper->parseBroadWorksDatetime()`, `$scraper->normalizePhone()`, `json_encode` for raw_payload
- `$stmt->rowCount()` tracking for `$totalInserted`
- `$scraper->logout()` before exit
- `connect_db()`, `log_msg()`, `send_alert()` helpers at bottom of file

No hardcoded credentials — username/password loaded exclusively from `.env.mycommpilot`.

### scripts/com.circulation.call-scraper.plist

- Label: `com.circulation.call-scraper`
- ProgramArguments: `/usr/bin/ssh nas /var/packages/PHP8.2/.../php82 /volume1/web/circulation/fetch_call_logs.php`
- StartCalendarInterval: `Minute=5` only (no Hour or Weekday keys — fires every hour at :05)
- StandardOutPath/StandardErrorPath: `/Users/johncorbin/Library/Logs/circulation/call-scraper.log`
- plutil validation: OK
- launchctl status: Loaded, LastExitStatus=0

---

_Verified: 2026-03-20T18:30:00Z_
_Verifier: Claude (gsd-verifier)_
