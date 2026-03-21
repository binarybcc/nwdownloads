---
phase: 8
slug: trend-expansion-log-retention
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-21
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/phpunit --testsuite Unit -x` |
| **Full suite command** | `./vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit --testsuite Unit -x`
- **After every plan wave:** Run `./vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | TREND-01 | unit | `./vendor/bin/phpunit tests/Unit/TrendExpansionTest.php -x` | ❌ W0 | ⬜ pending |
| 08-01-02 | 01 | 1 | TREND-01 | unit | `./vendor/bin/phpunit tests/Unit/TrendExpansionTest.php -x` | ❌ W0 | ⬜ pending |
| 08-01-03 | 01 | 1 | TREND-01 | manual-only | Visual check: open BU card, verify 13 data points in mini-chart | N/A | ⬜ pending |
| 08-02-01 | 02 | 1 | MAINT-01 | unit | `./vendor/bin/phpunit tests/Unit/CallLogPurgeTest.php -x` | ❌ W0 | ⬜ pending |
| 08-02-02 | 02 | 1 | MAINT-01 | unit | `./vendor/bin/phpunit tests/Unit/CallLogPurgeTest.php -x` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/TrendExpansionTest.php` — stubs for TREND-01 (verify 13-week iteration count, $weeksMap includes 13weeks entry)
- [ ] `tests/Unit/CallLogPurgeTest.php` — stubs for MAINT-01 (verify purge SQL targets call_timestamp, logging via log_msg)

*Note: PHP backend functions are tightly coupled to PDO database calls. Integration tests with mocked PDO or code-path verification may be more practical than full database integration tests.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| BU card mini-charts show 13 data points | TREND-01 | Frontend rendering requires browser | Open overview page, inspect a BU card mini-chart, count data points (should be 13) |
| Trend slider defaults to 13 weeks | TREND-01 | JS default requires browser | Click a bar chart on BU detail page, verify weeks input defaults to 13 |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
