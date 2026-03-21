# NWDownloads Circulation Dashboard

## What This Is

A newspaper circulation dashboard for tracking subscriber metrics across multiple business units (South Carolina, Wyoming, Michigan) and publications. Features interactive trend charts, subscription expiration tracking with 8-week view, renewal/churn analysis, new starts tracking, automated data import from Newzware, VOIP call log integration with contact status indicators, and color-coded XLSX export. Production runs on Synology NAS (native Apache/PHP/MariaDB).

## Core Value

Circulation managers can see subscriber health at a glance and take action on retention — knowing who's expiring, who's been contacted, and where trends are heading.

## Requirements

### Validated

- ✓ Company-wide 12-week trend chart (Total Active, Deliverable, On Vacation lines) — existing
- ✓ Business unit metric cards with donut charts and delivery breakdowns — existing
- ✓ API endpoint for trend data (`get_trend` action) — existing
- ✓ Chart.js 4.4.0 integration for data visualization — existing
- ✓ Business unit grouping logic in API and frontend — existing
- ✓ Compact 12-week trend chart in each business unit card — v1
- ✓ Interactive hover tooltips showing exact values per week — v1
- ✓ BU detail slide-out with subscription expiration chart — v1.x
- ✓ Right-click context menu on expiration chart for subscriber drill-down — v1.x
- ✓ BU trend detail modal with mixed chart (starts/stops/net) — v1.x (PRs #32-35)
- ✓ Automated weekly import of AllSubscriber, Vacation, Renewal CSVs — v1.x
- ✓ New starts importer with renewal cross-reference classification — v1.x
- ✓ Paid vs complimentary subscriber tracking — v1.x (PRs #43-44)
- ✓ New starts CSV import with dual date format support (M/D/YY + YYYY-MM-DD) — v2.1
- ✓ Phone normalization pipeline (10-digit CHAR matching across subscribers and call logs) — v2.1
- ✓ BroadWorks call log scraper with hourly NAS daemon automation — v2.1
- ✓ 8-week expiration chart with red-to-green color gradient — v2.1
- ✓ Call status overlay in subscriber table (phone icons, sort, tooltips, borders) — v2.1
- ✓ XLSX export with status-colored row fills and sync timestamp — v2.1

### Active

(None — start next milestone via `/gsd:new-milestone` to define requirements)

### Out of Scope

- Multiple lines (Deliverable, On Vacation) in card chart — keep simplified
- XSI REST API for call logs — not enabled by carrier (Segra), using web scraping
- Real-time call event push — BroadWorks XSI-Events not available
- Call duration tracking — Basic Call Logs don't include duration
- Inbound calls from non-subscribers in UI — only subscriber-matched calls shown
- Multiple staff attribution icons — single "contacted" status sufficient for MVP
- Synology Task Scheduler for scraper — deletes jobs, using NAS daemon instead
- PhpSpreadsheet server-side export — SheetJS client-side supports cell styling

## Context

Shipped v2.1 with ~35,966 LOC (PHP/JS/SQL). Tech stack: PHP 8.2, Chart.js 4.4.0 (CDN), vanilla JavaScript, Tailwind CSS, xlsx-js-style@1.2.0 (CDN). Production on Synology NAS (native Apache/PHP/MariaDB). No Docker — native stack only.

MyCommPilot (BroadWorks) VOIP integration at ws2.mycommpilot.com scrapes call logs for two circulation staff lines (Brittany Carroll, Chloe Welch) via NAS daemon running hourly 8am-8pm ET. Key limitation: only 20 entries per call type visible at any time.

Known tech debt: `fetch_call_logs.php` hardcodes DB password instead of loading from `.env.credentials` (low severity).

Future enhancement candidates from v2.1 REQUIREMENTS.md: manual call outcome annotation (CALL-F01), XSI API if carrier enables (CALL-F02), call duration tracking (CALL-F03), agent attribution display (CALL-F04), 90-day log retention policy (CALL-F05), visual de-emphasis of far-out weeks (DASH-F01), new starts count on BU cards (DASH-F02).

## Constraints

- **Tech Stack**: Must use existing Chart.js 4.4.0 — no new JS dependencies
- **VOIP API**: Web scraping only (no XSI API), 20-entry rolling window per call type
- **Scheduling**: NAS daemon (S99call_scraper.sh) for call scraping — not Synology Task Scheduler
- **Compatibility**: Native Synology NAS only (no Docker)
- **Credentials**: MyCommPilot creds in `.env.mycommpilot` (gitignored)
- **XLSX Export**: Must preserve row coloring for call status indicators (xlsx-js-style)

## Key Decisions

| Decision                                    | Rationale                                                      | Outcome |
| ------------------------------------------- | -------------------------------------------------------------- | ------- |
| Single line (Total Active only) in BU cards | Keep card charts clean and readable at small size              | ✓ Good  |
| Embed trends in overview response           | Single HTTP request, data always in sync                       | ✓ Good  |
| Web scraping for call logs (not XSI API)    | XSI not enabled by carrier                                     | ✓ Good  |
| Hourly scraping 8am-8pm ET                  | Covers business hours, captures 20-entry window before rolloff | ✓ Good  |
| Phone icon + row coloring for call status   | Option B from UI discussion — most info at a glance, sortable  | ✓ Good  |
| Row colors carry into XLSX export           | Staff need printable/shareable contact lists with status       | ✓ Good  |
| NAS daemon instead of macOS launchd         | Runs on NAS directly — no dependency on Mac being awake        | ✓ Good  |
| xlsx-js-style instead of community SheetJS  | Community edition strips .s cell styles needed for row fills   | ✓ Good  |
| COLLATE fix for cross-table JOIN            | Collation mismatch between call_logs and subscriber_snapshots  | ✓ Good  |
| Reusable $callLogSubquery PHP variable      | DRY SQL across 8 expiration bucket queries                     | ✓ Good  |
| parseDate() checks YYYY-MM-DD first         | Most specific pattern first, avoids M/D/YY ambiguity           | ✓ Good  |
| Phone normalization at ingest (not query)   | Indexed CHAR(10) column for fast JOINs                         | ✓ Good  |

---

_Last updated: 2026-03-21 after v2.1 milestone completion_
