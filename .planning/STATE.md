---
gsd_state_version: 1.0
milestone: v2.1
milestone_name: Call Integration & Dashboard Enhancements
status: unknown
stopped_at: Completed 03-02-PLAN.md (call_logs table + phone_normalized migrations + AllSubscriberImporter normalization)
last_updated: '2026-03-20T17:10:42.745Z'
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 2
  completed_plans: 2
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Circulation managers can see subscriber health at a glance and take action on retention
**Current focus:** Phase 03 — data-foundation

## Current Position

Phase: 03 (data-foundation) — EXECUTING
Plan: 2 of 2 (COMPLETE)

## Performance Metrics

**Velocity (v1):**

- Total plans completed: 2
- Average duration: ~3.5 min
- Total execution time: ~7 min

**By Phase:**

| Phase              | Plans | Total    | Avg/Plan |
| ------------------ | ----- | -------- | -------- |
| 1. BU Trend Data   | 1     | ~3.5 min | ~3.5 min |
| 2. Chart Rendering | 1     | ~3.5 min | ~3.5 min |

_Updated after each plan completion_

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.1:

- Web scraping only (no XSI API) — Segra/BroadWorks carrier does not enable XSI REST
- Phone icon + row coloring for call status (Option B) — most info at a glance, sortable
- Row colors carry into XLSX export — staff need printable contact lists with status
- launchd on Mac for scraper scheduling — Synology Task Scheduler deletes jobs
- Hourly scraping 8am-8pm ET — captures 20-entry rolling window before rolloff
- New starts import bug: parseDate() needs M/D/YY format support; two failed CSVs on NAS
- [Phase 03-data-foundation]: parseDate() checks YYYY-MM-DD before M/D/YY — most specific pattern first, avoids ambiguity, returns dateStr unchanged for YYYY-MM-DD
- [Phase 03-02]: REGEXP_REPLACE backfill uses RIGHT(x,10) to take rightmost 10 digits — matches PHP normalizePhone() leading-1-strip logic; phone_normalized populated at ingest via AllSubscriberImporter

### Pending Todos

None.

### Blockers/Concerns

- Phase 6: SheetJS `xlsx-latest` CDN — verify `.s.fill` cell style support before building XLSX export
- Phase 4: BroadWorks `folder_contents.jsp` probe string — verify against live portal before production
- Phase 3: MariaDB minor version on NAS — confirm REGEXP_REPLACE availability (PHP normalization-at-import preferred to avoid this)

## Session Continuity

Last session: 2026-03-20
Stopped at: Completed 03-02-PLAN.md (call_logs table + phone_normalized migrations + AllSubscriberImporter normalization)
Resume file: None
Next step: Phase 03 complete — all plans done; proceed to Phase 04 (call log scraper)
