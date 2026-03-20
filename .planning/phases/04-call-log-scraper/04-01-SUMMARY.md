---
phase: 04-call-log-scraper
plan: 01
subsystem: scraper
tags: [broadworks, curl, php, web-scraping, call-logs]

# Dependency graph
requires:
  - phase: 03-data-foundation
    provides: call_logs table schema and phone_normalized column
provides:
  - MyCommPilotScraper PHP class for BroadWorks auth and call log HTML parsing
  - fetch_call_logs.php CLI runner with INSERT IGNORE dedup into call_logs table
  - .gitignore protection for .env.mycommpilot credentials
affects: [04-02-scheduling, 06-call-status-ui]

# Tech tracking
tech-stack:
  added: [cURL with cookie jar session management]
  patterns: [BroadWorks three-step auth flow, HTML regex parsing with broken-parsing detection]

key-files:
  created:
    - web/lib/MyCommPilotScraper.php
    - web/fetch_call_logs.php
  modified:
    - .gitignore

key-decisions:
  - 'Direct mail() for alerts instead of EmailNotifier (which requires ProcessResult object)'
  - 'JSON-encoded entry as raw_payload for debugging without dedicated name column'
  - 'Business-hours guard in PHP (8am-8pm ET) independent of scheduler'

patterns-established:
  - 'Scraper class pattern: namespace CirculationDashboard, cURL cookie jar, login/logout/getCallLogs'
  - 'CLI runner pattern: business-hours guard + lock file + .env credential loading'

requirements-completed: [CALL-01, CALL-04]

# Metrics
duration: 3min
completed: 2026-03-20
---

# Phase 4 Plan 1: Call Log Scraper Summary

**BroadWorks MyCommPilot scraper with cURL cookie-jar auth, HTML table regex parsing, and INSERT IGNORE dedup into call_logs for BC/CW lines**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-03-20T17:45:17Z
- **Completed:** 2026-03-20T17:47:59Z
- **Tasks:** 2
- **Files created:** 2
- **Files modified:** 1

## Accomplishments

- MyCommPilotScraper class with three-step BroadWorks auth, user context switching, and HTML table parsing with date validation
- fetch_call_logs.php CLI runner with business-hours guard, lock file, credential loading, login retry, INSERT IGNORE loop, and email alerts
- .gitignore updated to protect .env.mycommpilot from accidental commits

## Task Commits

Each task was committed atomically:

1. **Task 1: Create MyCommPilotScraper class** - `730314a` (feat)
2. **Task 2: Create fetch_call_logs.php CLI runner and update .gitignore** - `43b4975` (feat)

## Files Created/Modified

- `web/lib/MyCommPilotScraper.php` - BroadWorks auth, user context switching, HTML call log parsing, phone normalization, datetime parsing
- `web/fetch_call_logs.php` - CLI runner orchestrating scraper, DB inserts, error handling, email alerts
- `.gitignore` - Added .env.mycommpilot to credentials section

## Decisions Made

- Used direct `mail()` for scraper alerts rather than `EmailNotifier` class (which requires `ProcessResult` object not suitable for scraper context)
- JSON-encoded raw entry stored in `raw_payload` column for debugging (preserves caller name without dedicated column)
- Business-hours guard checks `date('G')` for 8-20 range in PHP, independent of launchd schedule

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - .env.mycommpilot credentials file must already exist on NAS at /volume1/web/circulation/.env.mycommpilot (documented in Phase 4 context).

## Next Phase Readiness

- Scraper class and CLI runner ready for scheduling (Plan 02: launchd plist and deployment)
- call_logs table must exist on production (migration 014 from Phase 3)
- .env.mycommpilot must be deployed to NAS before first run

---

_Phase: 04-call-log-scraper_
_Completed: 2026-03-20_
