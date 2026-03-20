---
gsd_state_version: 1.0
milestone: v2.1
milestone_name: Call Integration & Dashboard Enhancements
status: unknown
stopped_at: Phase 4 context gathered
last_updated: '2026-03-20T17:48:37.972Z'
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 4
  completed_plans: 3
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Circulation managers can see subscriber health at a glance and take action on retention
**Current focus:** Phase 04 — call-log-scraper

## Current Position

Phase: 04 (call-log-scraper) — EXECUTING
Plan: 2 of 2

## Performance Metrics

**Velocity (v1):**

- Total plans completed: 3
- Average duration: ~3.3 min
- Total execution time: ~10 min

**By Phase:**

| Phase               | Plans | Total    | Avg/Plan |
| ------------------- | ----- | -------- | -------- |
| 1. BU Trend Data    | 1     | ~3.5 min | ~3.5 min |
| 2. Chart Rendering  | 1     | ~3.5 min | ~3.5 min |
| 4. Call Log Scraper | 1/2   | ~3 min   | ~3 min   |

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
- [Phase 04-01]: Direct mail() for scraper alerts (EmailNotifier requires ProcessResult); JSON raw_payload for debugging; business-hours guard in PHP independent of scheduler

### Pending Todos

None.

### Blockers/Concerns

- Phase 6: SheetJS `xlsx-latest` CDN — verify `.s.fill` cell style support before building XLSX export
- Phase 4: BroadWorks `folder_contents.jsp` probe string — verify against live portal before production
- Phase 3: MariaDB minor version on NAS — confirm REGEXP_REPLACE availability (PHP normalization-at-import preferred to avoid this)

## Session Continuity

Last session: 2026-03-20T17:48:00Z
Stopped at: Completed 04-01-PLAN.md
Resume file: .planning/phases/04-call-log-scraper/04-01-SUMMARY.md
Next step: Execute 04-02-PLAN.md (scheduling and deployment)
