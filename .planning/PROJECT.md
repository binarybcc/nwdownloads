# NWDownloads Circulation Dashboard

## What This Is

A newspaper circulation dashboard for tracking subscriber metrics across multiple business units (South Carolina, Wyoming, Michigan) and publications. Features interactive trend charts, subscription expiration tracking, renewal/churn analysis, new starts tracking, and automated data import from Newzware. Production runs on Synology NAS (native Apache/PHP/MariaDB).

## Core Value

Circulation managers can see subscriber health at a glance and take action on retention — knowing who's expiring, who's been contacted, and where trends are heading.

## Current Milestone: v2.1 Call Integration & Dashboard Enhancements

**Goal:** Integrate VOIP call logs with expiration data so circulation staff can see which expiring subscribers have been contacted, plus fix import bugs and expand the expiration view.

**Target features:**

- Fix new starts CSV import (date format compatibility)
- Expand subscription expiration chart from 4-week to 8-week view
- MyCommPilot call log scraper with hourly automated collection
- Call status overlay in expiration subscriber table with XLSX export

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

### Active

See REQUIREMENTS.md for v2.1 requirements.

### Out of Scope

- Multiple lines (Deliverable, On Vacation) in card chart — keep simplified
- XSI REST API for call logs — not enabled by carrier (Segra), using web scraping
- Real-time call event push — BroadWorks XSI-Events not available
- Call duration tracking — Basic Call Logs don't include duration

## Context

Tech stack: PHP 8.2, Chart.js 4.4.0 (CDN), vanilla JavaScript, Tailwind CSS. Production on Synology NAS (native Apache/PHP/MariaDB). No Docker — native stack only.

Since v1 milestone (2026-02-09), significant features shipped outside GSD: BU trend detail modal (PRs #32-35), stop analysis importer, new starts importer, paid/comp subscriber tracking (PRs #43-44). Current package.json version: v2.0.0.

MyCommPilot (BroadWorks) VOIP system at ws2.mycommpilot.com provides call logs for two circulation staff lines (Brittany Carroll, Chloe Welch). Integration doc at `docs/MYCOMMPILOT-INTEGRATION.md` has complete PHP scraper class and auth flow. Key limitation: only 20 entries per call type visible at any time — must scrape frequently.

## Constraints

- **Tech Stack**: Must use existing Chart.js 4.4.0 — no new JS dependencies
- **VOIP API**: Web scraping only (no XSI API), 20-entry rolling window per call type
- **Scheduling**: launchd on Mac (not Synology Task Scheduler — it deletes jobs)
- **Compatibility**: Native Synology NAS only (no Docker)
- **Credentials**: MyCommPilot creds in `.env.mycommpilot` (gitignored)
- **XLSX Export**: Must preserve row coloring for call status indicators

## Key Decisions

| Decision                                    | Rationale                                                      | Outcome   |
| ------------------------------------------- | -------------------------------------------------------------- | --------- |
| Single line (Total Active only) in BU cards | Keep card charts clean and readable at small size              | ✓ Good    |
| Embed trends in overview response           | Single HTTP request, data always in sync                       | ✓ Good    |
| Web scraping for call logs (not XSI API)    | XSI not enabled by carrier                                     | — Pending |
| Hourly scraping 8am-8pm ET                  | Covers business hours, captures 20-entry window before rolloff | — Pending |
| Phone icon + row coloring for call status   | Option B from UI discussion — most info at a glance, sortable  | — Pending |
| Row colors carry into XLSX export           | Staff need printable/shareable contact lists with status       | — Pending |
| Balanced model profile for GSD agents       | No novel architecture — extending established patterns         | — Pending |

---

_Last updated: 2026-03-20 after v2.1 milestone initialization_
