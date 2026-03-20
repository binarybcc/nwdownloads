---
phase: 3
slug: data-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-20
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property               | Value                                         |
| ---------------------- | --------------------------------------------- |
| **Framework**          | None — no automated test framework in project |
| **Config file**        | None                                          |
| **Quick run command**  | Manual SQL queries + dev UI upload            |
| **Full suite command** | Manual SQL queries + dev UI upload            |
| **Estimated runtime**  | ~60 seconds (manual)                          |

---

## Sampling Rate

- **After every task commit:** Manual smoke test in Docker dev (upload CSV, query table)
- **After every plan wave:** Full SQL verification queries on dev database
- **Before `/gsd:verify-work`:** All 4 success criteria TRUE
- **Max feedback latency:** ~60 seconds

---

## Per-Task Verification Map

| Task ID  | Plan | Wave | Requirement | Test Type | Automated Command                                                              | File Exists    | Status     |
| -------- | ---- | ---- | ----------- | --------- | ------------------------------------------------------------------------------ | -------------- | ---------- |
| 03-01-01 | 01   | 1    | IMPORT-01   | manual    | Upload M/D/YY CSV via dev UI                                                   | ❌ manual only | ⬜ pending |
| 03-01-02 | 01   | 1    | IMPORT-01   | manual    | Upload YYYY-MM-DD CSV via dev UI                                               | ❌ manual only | ⬜ pending |
| 03-01-03 | 01   | 1    | IMPORT-02   | manual    | Check `auto_process.log` on NAS after reprocessing                             | ❌ manual only | ⬜ pending |
| 03-02-01 | 02   | 2    | CALL-02     | SQL query | `SHOW CREATE TABLE call_logs`                                                  | ❌ Wave 0      | ⬜ pending |
| 03-02-02 | 02   | 2    | CALL-02     | SQL query | `SELECT COUNT(*) FROM subscriber_snapshots WHERE phone_normalized IS NOT NULL` | ❌ Wave 0      | ⬜ pending |

_Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky_

---

## Wave 0 Requirements

- Existing infrastructure covers all phase requirements. No test framework to install — verification is SQL-query and UI-upload based.

---

## Manual-Only Verifications

| Behavior                         | Requirement | Why Manual                            | Test Instructions                                                                                         |
| -------------------------------- | ----------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| M/D/YY date parses correctly     | IMPORT-01   | No test framework; upload is the test | Upload CSV with M/D/YY dates via dev upload_unified.php, verify no parse errors                           |
| YYYY-MM-DD date parses correctly | IMPORT-01   | No test framework; upload is the test | Upload a failed CSV (auto-export format) via dev UI, verify success                                       |
| Failed CSVs reprocess            | IMPORT-02   | Requires NAS file access              | SSH to NAS, trigger auto_process.php, verify files moved to completed/                                    |
| call_logs table exists           | CALL-02     | SQL schema check                      | `SHOW CREATE TABLE call_logs` — verify phone_normalized indexed column                                    |
| phone_normalized populated       | CALL-02     | SQL data check                        | `SELECT COUNT(*) FROM subscriber_snapshots WHERE phone_normalized IS NOT NULL AND phone_normalized != ''` |

---

## Validation Sign-Off

- [ ] All tasks have manual verify instructions
- [ ] Sampling continuity: manual verification after each task
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
