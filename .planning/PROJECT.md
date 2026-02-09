# Business Unit 12-Week Trend Charts

## What This Is

A feature addition to the NWDownloads Circulation Dashboard that adds a compact, interactive 12-week trend chart to each business unit card. The chart shows Total Active subscribers over time as a single line with area fill, positioned between the comparison bar and the donut chart within each card.

## Core Value

Each business unit card should tell its own trend story at a glance — a compact sparkline-style chart showing 12 weeks of Total Active subscriber counts with hover tooltips for exact values.

## Requirements

### Validated

- ✓ Company-wide 12-week trend chart (Total Active, Deliverable, On Vacation lines) — existing
- ✓ Business unit metric cards with donut charts and delivery breakdowns — existing
- ✓ API endpoint for trend data (`get_trend` action) — existing
- ✓ Chart.js 4.4.0 integration for data visualization — existing
- ✓ Business unit grouping logic in API and frontend — existing

### Active

- [ ] Compact 12-week trend chart in each business unit card
- [ ] Single Total Active line with area fill (simplified from company-wide chart)
- [ ] Interactive hover tooltips showing exact values per week
- [ ] Chart positioned between comparison bar and donut chart in card layout
- [ ] Data filtered to specific business unit (aggregate of all papers in that BU)

### Out of Scope

- Multiple lines (Deliverable, On Vacation) in card chart — keep simplified to single Total Active line
- Legend in card chart — not needed for single-line chart
- Replacing the existing company-wide 12-week trend chart — it stays as-is
- New API endpoints — reuse/adapt existing `get_trend` action with BU parameter

## Context

- The existing company-wide 12-week trend chart already fetches and renders trend data using Chart.js
- The API already supports business unit filtering in various endpoints
- Business unit cards are rendered in `index.php` with Chart.js donut charts already present
- The frontend state management uses `CircDashboard.state` object
- Production serves from Synology NAS (native Apache/PHP, no Docker)
- Chart.js 4.4.0 loaded via CDN (jsDelivr)

## Constraints

- **Tech Stack**: Must use existing Chart.js 4.4.0 — no new dependencies
- **Card Size**: Chart must fit compactly within existing card width without making cards excessively tall
- **Performance**: Must not add significant API calls — batch or reuse existing trend data fetch
- **Compatibility**: Must work on both development (Docker) and production (Synology native) environments

## Key Decisions

| Decision                        | Rationale                                            | Outcome   |
| ------------------------------- | ---------------------------------------------------- | --------- |
| Single line (Total Active only) | Keep card charts clean and readable at small size    | — Pending |
| Interactive hover tooltips      | Users need exact values, not just visual trend shape | — Pending |
| Reuse existing trend API        | Minimize backend changes, data already available     | — Pending |

---

_Last updated: 2026-02-09 after initialization_
