# Project Milestones: NWDownloads Circulation Dashboard

## v2.1 Call Integration & Dashboard Enhancements (Shipped: 2026-03-21)

**Delivered:** VOIP call log integration with expiration subscriber tracking — circulation staff can see which expiring subscribers have been contacted by phone, sort by contact status, and export color-coded XLSX spreadsheets. Also expanded expiration chart to 8 weeks and fixed new starts import date format bug.

**Phases completed:** 3-6 (4 phases, 7 plans, ~14 tasks)

**Key accomplishments:**

- Dual-format `parseDate()` for new starts CSV import (M/D/YY and YYYY-MM-DD) — reprocessed 842 failed records
- Phone normalization pipeline: `call_logs` table + `phone_normalized` on subscriber_snapshots with 99.95% backfill
- BroadWorks MyCommPilot scraper with 3-step auth, INSERT IGNORE dedup, hourly NAS daemon (S99call_scraper.sh)
- 8-week expiration chart with red-to-green gradient and subscriber drill-down on all buckets
- Call status overlay: colored phone icons, sort toggle, tooltips, 4px left borders in subscriber table
- XLSX export with status-colored row fills, merged timestamp header, and 3 new columns via xlsx-js-style

**Stats:**

- 44 files changed (code + planning docs)
- +3,834 / -2,604 lines
- 4 phases, 7 plans, ~14 tasks
- Single-day execution (2026-03-20)
- ~35,966 total LOC (PHP/JS/SQL)

**Git range:** `653596e` (fix: NewStartsImporter parseDate) → `a50e971` (docs: resolve CALL-03)

**Tech debt:** fetch_call_logs.php hardcodes DB password instead of loading from .env.credentials (low severity)

**What's next:** TBD — next milestone via `/gsd:new-milestone`

---

## v1 Business Unit 12-Week Trend Charts (Shipped: 2026-02-09)

**Delivered:** Interactive 12-week trend mini-charts in each business unit card showing Total Active subscriber history with hover tooltips and auto-scaled axes.

**Phases completed:** 1-2 (2 plans total)

**Key accomplishments:**

- Per-business-unit trend API embedded in existing overview response (zero new endpoints)
- Chart.js mini-charts with blue area fill matching company-wide chart style
- Color-coded hover tooltips with exact subscriber count and week-over-week change
- Auto-scaled Y-axis per business unit with clean null-gap handling
- Full lifecycle management with event isolation from existing card interactions

**Stats:**

- 2 code files modified (`legacy.php`, `app.js`)
- 17 total files changed (including planning docs)
- 3,871 insertions, 1,060 deletions
- 2 phases, 2 plans, 4 tasks
- Same-day execution (~2 hours from init to ship)

**Git range:** `bd2f750` (docs: initialize project) → `b65113a` (docs(02): complete phase)

**What's next:** TBD — next milestone via `/gsd:new-milestone`

---
