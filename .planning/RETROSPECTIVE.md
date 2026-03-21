# Project Retrospective

_A living document updated after each milestone. Lessons feed forward into future planning._

## Milestone: v2.1 — Call Integration & Dashboard Enhancements

**Shipped:** 2026-03-21
**Phases:** 4 | **Plans:** 7 | **Sessions:** ~3

### What Was Built

- BroadWorks VOIP call log scraper with NAS daemon automation
- Call status overlay in expiration subscriber table (icons, sort, tooltips, XLSX export)
- 8-week expiration chart with red-to-green gradient
- Dual-format date parsing fix for new starts CSV imports
- Phone normalization pipeline across subscribers and call logs

### What Worked

- **Single-day execution** — all 4 phases planned and executed in one session (2026-03-20)
- **Reusable SQL patterns** — $callLogSubquery variable made 8 branch queries DRY and maintainable
- **Phone normalization at ingest** — indexed CHAR(10) column eliminated runtime normalization overhead
- **NAS daemon over macOS launchd** — eliminated dependency on Mac being awake for hourly scraping
- **Audit before completion** — caught CALL-03 gap (scraper scheduling) before marking done

### What Was Inefficient

- **Post-deployment fixes needed for Phase 6** — collation mismatch, SheetJS library swap, and data passthrough all required fixes after initial deploy. Better pre-deploy testing would have caught these.
- **SheetJS blocker was known but not pre-verified** — STATE.md flagged SheetJS styling as a concern, but we didn't test CDN compatibility until production
- **ROADMAP progress table had formatting bugs** — v2.1 phases had misaligned columns that persisted through execution

### Patterns Established

- NAS daemon pattern (S99call_scraper.sh + PHP business-hours guard) for scheduled tasks
- Phone normalization: 10-digit CHAR(10), strip non-digits, handle 11-digit with leading 1
- Reusable SQL subquery as PHP variable for DRY multi-branch queries
- Status color mapping helpers: getCallStatusColor() for UI, getExportStatusFill() for XLSX

### Key Lessons

1. **Test CDN library capabilities before planning features around them** — the xlsx-latest → xlsx-js-style swap was avoidable
2. **Collation mismatches are inevitable across tables created at different times** — always specify COLLATE explicitly in cross-table JOINs
3. **NAS daemon >> macOS launchd for server tasks** — daemon runs on the server itself, no remote dependency
4. **Phone normalization at ingest is the right pattern** — normalize once on write, fast JOINs forever

### Cost Observations

- Model mix: ~80% opus, ~20% sonnet (quality profile)
- Sessions: ~3 (research/planning, execution, completion)
- Notable: Single-day execution for 4 phases is exceptional velocity for a VOIP integration feature

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change                                          |
| --------- | -------- | ------ | --------------------------------------------------- |
| v1        | 1        | 2      | First GSD project — established basic patterns      |
| v2.1      | ~3       | 4      | Added research phase, UI-SPEC contracts, NAS daemon |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
| --------- | ----- | -------- | ------------------ |
| v1        | 0     | —        | 0                  |
| v2.1      | 0     | —        | 1 (xlsx-js-style)  |

### Top Lessons (Verified Across Milestones)

1. Single-day execution is achievable when research/planning is thorough — both v1 and v2.1 shipped same-day
2. Embed data in existing API responses when possible — reduces round-trips and keeps data in sync (v1 trends, v2.1 call status)
