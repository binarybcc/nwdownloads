# Business Unit 12-Week Trend Charts

## What This Is

A feature addition to the NWDownloads Circulation Dashboard that adds compact, interactive 12-week trend charts to each business unit card. Each card shows Total Active subscribers over time as a blue area-fill line chart with hover tooltips, positioned between the comparison bar and the donut chart.

## Core Value

Each business unit card tells its own trend story at a glance — a compact sparkline-style chart showing 12 weeks of Total Active subscriber counts with hover tooltips for exact values.

## Requirements

### Validated

- ✓ Company-wide 12-week trend chart (Total Active, Deliverable, On Vacation lines) — existing
- ✓ Business unit metric cards with donut charts and delivery breakdowns — existing
- ✓ API endpoint for trend data (`get_trend` action) — existing
- ✓ Chart.js 4.4.0 integration for data visualization — existing
- ✓ Business unit grouping logic in API and frontend — existing
- ✓ Compact 12-week trend chart in each business unit card — v1
- ✓ Single Total Active line with area fill (simplified from company-wide chart) — v1
- ✓ Interactive hover tooltips showing exact values per week — v1
- ✓ Chart positioned between comparison bar and donut chart in card layout — v1
- ✓ Data filtered to specific business unit (aggregate of all papers in that BU) — v1

### Active

(None — planning next milestone)

### Out of Scope

- Multiple lines (Deliverable, On Vacation) in card chart — keep simplified to single Total Active line
- Legend in card chart — not needed for single-line chart
- Replacing the existing company-wide 12-week trend chart — it stays as-is
- Configurable week range (8, 12, 24 weeks) — 12 weeks is the standard

## Context

Shipped v1 with 2 code files modified: `web/api/legacy.php` (BU trend API functions) and `web/assets/js/core/app.js` (chart rendering and card integration).

Tech stack: PHP 8.2, Chart.js 4.4.0 (CDN), vanilla JavaScript. Production on Synology NAS (native Apache/PHP/MariaDB).

All 3 business units (South Carolina, Wyoming, Michigan) display interactive trend charts on dashboard load. Charts handle edge cases: no data, null-padded weeks, single data points.

## Constraints

- **Tech Stack**: Must use existing Chart.js 4.4.0 — no new dependencies
- **Card Size**: Chart must fit compactly within existing card width without making cards excessively tall
- **Performance**: Must not add significant API calls — batch or reuse existing trend data fetch
- **Compatibility**: Must work on both development (Docker) and production (Synology native) environments

## Key Decisions

| Decision                                           | Rationale                                                  | Outcome |
| -------------------------------------------------- | ---------------------------------------------------------- | ------- |
| Single line (Total Active only)                    | Keep card charts clean and readable at small size          | ✓ Good  |
| Interactive hover tooltips                         | Users need exact values, not just visual trend shape       | ✓ Good  |
| Reuse existing trend API                           | Minimize backend changes, data already available           | ✓ Good  |
| Embed trends in overview response                  | Single HTTP request, data always in sync with current week | ✓ Good  |
| MAX(snapshot_date) subquery for dedup              | Handles multiple CSV uploads in same week                  | ✓ Good  |
| Sequential W1-W12 labels                           | Simpler for chart X-axis, matches spec                     | ✓ Good  |
| data-bu-trend DOM attribute                        | Flexible access pattern for chart rendering                | ✓ Good  |
| bu-trend- canvas ID prefix                         | Avoids collision with drill-down panel trend- IDs          | ✓ Good  |
| Nulls with spanGaps instead of zeros               | Prevents misleading "dip to zero" visual for missing weeks | ✓ Good  |
| Default animation instead of progressive line draw | Safer with fill:true, avoids rendering bugs                | ✓ Good  |
| Tooltip reads pre-computed change via closure      | No duplicate calculation, consistent with API data         | ✓ Good  |

---

_Last updated: 2026-02-09 after v1 milestone_
