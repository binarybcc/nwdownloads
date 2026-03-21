# Roadmap: NWDownloads Circulation Dashboard

## Milestones

- ✅ **v1 Business Unit Trend Charts** - Phases 1-2 (shipped 2026-02-09)
- 🚧 **v2.1 Call Integration & Dashboard Enhancements** - Phases 3-6 (in progress)

## Phases

<details>
<summary>✅ v1 Business Unit Trend Charts (Phases 1-2) - SHIPPED 2026-02-09</summary>

### Phase 1: Business Unit Trend Data

**Goal**: Per-BU trend data is available via API
**Plans**: 1 plan

Plans:

- [x] 01-01: Add getBusinessUnitTrends() to legacy.php, embed in overview response

### Phase 2: Chart Rendering and Card Integration

**Goal**: Trend mini-charts render inside each BU card with hover tooltips
**Plans**: 1 plan

Plans:

- [x] 02-01: Render Chart.js mini-charts in BU cards with tooltip and lifecycle management

</details>

### 🚧 v2.1 Call Integration & Dashboard Enhancements (In Progress)

**Milestone Goal:** Circulation staff can see which expiring subscribers have been contacted by phone, plus an expanded 8-week expiration view and a date format bug fix in the new starts importer.

---

### Phase 3: Data Foundation

**Goal**: The database schema and phone normalization are ready for call log matching — the prerequisite every downstream phase depends on
**Depends on**: Phase 2 (v1)
**Requirements**: IMPORT-01, IMPORT-02, CALL-02
**Success Criteria** (what must be TRUE):

1. Uploading a new starts CSV with M/D/YY dates (e.g., 2/24/26) succeeds without an error
2. The two previously failed CSVs (Mar 9, Mar 16) are reprocessed and appear in new starts data
3. The `call_logs` table exists in the database with a `phone_normalized` indexed column
4. Subscriber records in `subscriber_snapshots` have a populated `phone_normalized` column

**Plans**: 2 plans

Plans:

- [ ] 03-01-PLAN.md — Fix NewStartsImporter parseDate() for YYYY-MM-DD format and reprocess failed CSVs from NAS
- [ ] 03-02-PLAN.md — SQL migrations for call_logs table + phone_normalized column; update AllSubscriberImporter to normalize at ingest

---

### Phase 4: Call Log Scraper

**Goal**: BroadWorks call logs for both circulation staff lines are collected hourly and stored reliably — any failure is visible, no silent empty runs
**Depends on**: Phase 3
**Requirements**: CALL-01, CALL-03, CALL-04
**Success Criteria** (what must be TRUE):

1. Running the scraper twice in one minute does not double the row count (INSERT IGNORE dedup works)
2. The `call_logs` table contains placed, received, and missed entries for both Brittany Carroll and Chloe Welch
3. The scraper runs automatically on an hourly schedule during business hours (8am-8pm ET) via macOS launchd
4. When login to MyCommPilot fails, an error is written to the log and the script exits cleanly — no silent empty run

**Plans**: 2 plans

Plans:

- [ ] 04-01-PLAN.md — Build MyCommPilotScraper.php class and fetch_call_logs.php CLI runner with INSERT IGNORE dedup, login verification, and error logging
- [ ] 04-02-PLAN.md — Create macOS launchd plist for hourly scraping; human-verify scraper on NAS and install schedule

---

### Phase 5: Expiration Chart Expansion

**Goal**: The subscription expiration chart shows 8 weeks of data with correct colors and working context menu on every bar — independent of call log work
**Depends on**: Phase 2 (v1)
**Requirements**: CHART-01, CHART-02, CHART-03
**Success Criteria** (what must be TRUE):

1. The expiration chart renders 8 bars: Past Due, This Week, Next Week, and Week +2 through Week +6
2. Right-clicking any of the 8 bars opens the subscriber drill-down context menu with the correct week label
3. Bar colors transition naturally from red (past due) through orange and yellow to green (furthest out)

**Plans**: 1 plan

Plans:

- [ ] 05-01-PLAN.md — Extend SQL CASE/interval, JS color array, context menu drill-down, and chart title from 4 to 8 weeks

---

### Phase 6: Call Status UI and Export

**Goal**: Circulation staff can see call status for every expiring subscriber in the table, sort by contact status, and export a color-coded spreadsheet
**Depends on**: Phase 3, Phase 4
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06
**Success Criteria** (what must be TRUE):

1. Each row in the expiration subscriber table shows a phone icon colored green, orange, or red based on call status
2. Rows with no call contact appear at the top of the table by default (not-contacted first sort)
3. Hovering the phone icon shows the last contact date, time, and call type (placed/received/missed)
4. Downloading the XLSX export produces a file where each row's background fill color matches its call status color in the browser
5. The subscriber table and XLSX export display a "Call data as of [timestamp]" line showing when call logs were last synced

**Plans**: 2 plans

Plans:

- [x] 06-01-PLAN.md — LEFT JOIN call_logs in getExpirationSubscribers(); add call_status, last_call_datetime, call_agent to API response
- [x] 06-02-PLAN.md — Status column with colored icons, sort toggle, tooltips, left border; XLSX export with status fills and timestamp row

---

## Progress

**Execution Order:** 3 → 4 → 5 → 6 (Phase 5 is independent; can run in parallel with 4 if desired)

| Phase                                   | Milestone | Plans Complete | Status      | Completed  |
| --------------------------------------- | --------- | -------------- | ----------- | ---------- |
| 1. Business Unit Trend Data             | v1        | 1/1            | Complete    | 2026-02-09 |
| 2. Chart Rendering and Card Integration | v1        | 1/1            | Complete    | 2026-02-09 |
| 3. Data Foundation                      | 2/2       | Complete       | 2026-03-20  | -          |
| 4. Call Log Scraper                     | 1/2       | In Progress    |             | -          |
| 5. Expiration Chart Expansion           | v2.1      | 0/1            | Not started | -          |
| 6. Call Status UI and Export            | v2.1      | 2/2            | Complete    | 2026-03-20 |
