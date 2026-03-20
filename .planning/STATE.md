---
gsd_state_version: 1.0
milestone: v2.1
milestone_name: Call Integration & Dashboard Enhancements
status: unknown
stopped_at: Completed 06-01-PLAN.md
last_updated: '2026-03-20T22:23:54.170Z'
progress:
  total_phases: 4
  completed_phases: 3
  total_plans: 7
  completed_plans: 6
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Circulation managers can see subscriber health at a glance and take action on retention
**Current focus:** Phase 06 — call-status-ui-and-export

## Current Position

Phase: 06 (call-status-ui-and-export) — EXECUTING
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
| 5. Expiration Chart | 1     | ~16 min  | ~16 min  |

_Updated after each plan completion_
| Phase 06 P01 | 3min | 1 tasks | 1 files |

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
- [Phase 05]: array_fill() for PDO parameter arrays instead of explicit entries; This Week color changed to amber-500 for smoother 8-stop gradient
- [Phase 06]: Reusable $callLogSubquery PHP variable for DRY SQL across 8 expiration bucket queries

### Pending Todos

None.

### Blockers/Concerns

- Phase 6: SheetJS `xlsx-latest` CDN — verify `.s.fill` cell style support before building XLSX export
- Phase 4: BroadWorks `folder_contents.jsp` probe string — verify against live portal before production
- Phase 3: MariaDB minor version on NAS — confirm REGEXP_REPLACE availability (PHP normalization-at-import preferred to avoid this)

## Session Continuity

Last session: 2026-03-20T22:23:54.168Z
Stopped at: Completed 06-01-PLAN.md
Resume file: None
Next step: Execute 04-02-PLAN.md (scheduling and deployment)
