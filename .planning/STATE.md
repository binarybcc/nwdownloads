# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Circulation managers can see subscriber health at a glance and take action on retention
**Current focus:** Phase 3 — Data Foundation (v2.1 milestone start)

## Current Position

Phase: 3 of 6 (Data Foundation — v2.1 start)
Plan: 0 of 2 in current phase
Status: Ready to plan
Last activity: 2026-03-20 — v2.1 roadmap created, phases 3-6 defined

Progress: [░░░░░░░░░░] 0% (v2.1 phases; v1 phases 1-2 complete)

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

### Pending Todos

None.

### Blockers/Concerns

- Phase 6: SheetJS `xlsx-latest` CDN — verify `.s.fill` cell style support before building XLSX export
- Phase 4: BroadWorks `folder_contents.jsp` probe string — verify against live portal before production
- Phase 3: MariaDB minor version on NAS — confirm REGEXP_REPLACE availability (PHP normalization-at-import preferred to avoid this)

## Session Continuity

Last session: 2026-03-20
Stopped at: Roadmap created for v2.1, ready to plan Phase 3 (Data Foundation)
Resume file: None
Next step: /gsd:plan-phase 3
