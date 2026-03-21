---
phase: 4
slug: call-log-scraper
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-20
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property               | Value                                                                                                    |
| ---------------------- | -------------------------------------------------------------------------------------------------------- |
| **Framework**          | Manual/smoke (no PHPUnit in project)                                                                     |
| **Config file**        | none                                                                                                     |
| **Quick run command**  | `ssh nas "/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/fetch_call_logs.php"` |
| **Full suite command** | Manual verification per success criteria (see Per-Task map)                                              |
| **Estimated runtime**  | ~30 seconds (SSH + scraper run + DB query)                                                               |

---

## Sampling Rate

- **After every task commit:** Run `ssh nas "/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/fetch_call_logs.php"`
- **After every plan wave:** Full verification of all 3 requirements
- **Before `/gsd:verify-work`:** All success criteria verified
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID  | Plan | Wave | Requirement | Test Type   | Automated Command                                                                                                                                                                             | File Exists | Status     |
| -------- | ---- | ---- | ----------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- | ---------- |
| 04-01-01 | 01   | 1    | CALL-01     | smoke       | `ssh nas "php82 /volume1/web/circulation/fetch_call_logs.php" && ssh nas "mysql ... -e 'SELECT source_group, call_direction, COUNT(*) FROM call_logs GROUP BY source_group, call_direction'"` | ❌ W0       | ⬜ pending |
| 04-01-02 | 01   | 1    | CALL-04     | smoke       | Run with wrong password in .env.mycommpilot, verify log error + email                                                                                                                         | ❌ W0       | ⬜ pending |
| 04-02-01 | 02   | 2    | CALL-03     | manual-only | `launchctl list com.circulation.call-scraper`                                                                                                                                                 | ❌ W0       | ⬜ pending |

_Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky_

---

## Wave 0 Requirements

- [ ] `.env.mycommpilot` added to `.gitignore` — prevent credential commit
- [ ] No automated test framework — all verification is manual SSH + DB queries

_Existing infrastructure covers DB queries and SSH execution. No new framework needed._

---

## Manual-Only Verifications

| Behavior                  | Requirement | Why Manual                                                  | Test Instructions                                                                                                                                  |
| ------------------------- | ----------- | ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Launchd hourly schedule   | CALL-03     | Requires macOS launchd loaded on dev machine                | 1. Copy plist to ~/Library/LaunchAgents/ 2. `launchctl load` 3. `launchctl list com.circulation.call-scraper` 4. Verify it fires at next hour mark |
| Login failure email alert | CALL-04     | Requires intentional bad credentials + email delivery check | 1. Set wrong password in .env.mycommpilot 2. Run scraper 3. Check log for error message 4. Check email inbox for alert                             |
| Dedup (INSERT IGNORE)     | CALL-01     | Requires running scraper twice and comparing row counts     | 1. Run scraper, note row count 2. Run immediately again 3. Verify row count unchanged                                                              |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
