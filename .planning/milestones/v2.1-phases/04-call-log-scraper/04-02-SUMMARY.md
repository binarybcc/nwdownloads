---
phase: 04-call-log-scraper
plan: 02
subsystem: automation
tags: [launchd, scheduling, macos, ssh]

# Dependency graph
requires:
  - phase: 04-call-log-scraper
    plan: 01
    provides: fetch_call_logs.php CLI runner

key-files:
  created:
    - scripts/com.circulation.call-scraper.plist
  modified: []

decisions:
  - decision: Run plist 24/7 hourly, let PHP business-hours guard handle 8am-8pm filtering
    reason: Simpler plist (one Minute key vs 12 Hour entries), single responsibility in PHP
  - decision: Minute=5 for hourly schedule
    reason: Avoids contention with other cron/launchd jobs that run on the hour

self-check: PASSED
---

## What Was Built

Launchd plist for hourly call log scraping via SSH to NAS. The plist runs at :05 past every hour, executing `fetch_call_logs.php` on the NAS via the `ssh nas` shortcut. PHP business-hours guard (8am-8pm ET) handles time filtering.

## Verification Results

| Test                 | Result                             |
| -------------------- | ---------------------------------- |
| Manual scraper run   | ✓ 120 scraped, 119 new             |
| Dedup (second run)   | ✓ 120 scraped, 0 new               |
| DB data check        | ✓ BC + CW × placed/received/missed |
| Login verification   | ✓ Implicit — login succeeded       |
| Launchd plist loaded | ✓ LastExitStatus = 0               |

## Installation Notes

Plist committed to `scripts/` for version control. Manually copied to `~/Library/LaunchAgents/` and loaded via `launchctl load`. Requires Mac to be on/awake (runs on wake if missed).
