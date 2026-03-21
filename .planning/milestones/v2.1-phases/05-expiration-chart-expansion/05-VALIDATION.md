---
phase: 5
slug: expiration-chart-expansion
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-20
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property               | Value                                                                                               |
| ---------------------- | --------------------------------------------------------------------------------------------------- |
| **Framework**          | Manual verification + grep-based checks (no test framework for PHP/JS in this project)              |
| **Config file**        | none                                                                                                |
| **Quick run command**  | `php -l web/api/legacy.php`                                                                         |
| **Full suite command** | `php -l web/api/legacy.php && php -l web/assets/js/features/detail_panel.js 2>/dev/null; echo done` |
| **Estimated runtime**  | ~2 seconds                                                                                          |

---

## Sampling Rate

- **After every task commit:** Run `php -l` on modified PHP files
- **After every plan wave:** Verify all 8 bars render via API response check
- **Before `/gsd:verify-work`:** Full manual chart inspection
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID  | Plan | Wave | Requirement        | Test Type | Automated Command                                                           | File Exists | Status     |
| -------- | ---- | ---- | ------------------ | --------- | --------------------------------------------------------------------------- | ----------- | ---------- |
| 05-01-01 | 01   | 1    | CHART-01           | grep+lint | `grep -c 'INTERVAL 49 DAY' web/api/legacy.php && php -l web/api/legacy.php` | N/A         | ⬜ pending |
| 05-01-02 | 01   | 1    | CHART-02, CHART-03 | grep+lint | `grep -c 'Week +6' web/assets/js/features/detail_panel.js`                  | N/A         | ⬜ pending |

_Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky_

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test framework installation needed — verification is via PHP lint, grep for expected strings, and manual chart inspection.

---

## Manual-Only Verifications

| Behavior                          | Requirement | Why Manual                                | Test Instructions                                                             |
| --------------------------------- | ----------- | ----------------------------------------- | ----------------------------------------------------------------------------- |
| 8 bars render with correct colors | CHART-03    | Visual color gradient requires human eyes | Load dashboard, inspect expiration chart for red→orange→yellow→green gradient |
| Context menu opens on each bar    | CHART-02    | Browser right-click interaction           | Right-click each of 8 bars, verify correct week label in context menu         |
| Bar labels are correct            | CHART-01    | Visual label check                        | Verify: Past Due, This Week, Next Week, Week +2 through Week +6               |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
