# Roadmap: NWDownloads Circulation Dashboard

## Milestones

- v1 Business Unit Trend Charts -- Phases 1-2 (shipped 2026-02-09)
- v2.1 Call Integration & Dashboard Enhancements -- Phases 3-6 (shipped 2026-03-20)
- v2.2 Monthly Subscriber Handling & Dashboard Refinements -- Phases 7-9 (in progress)

## Phases

<details>
<summary>v1 Business Unit Trend Charts (Phases 1-2) -- SHIPPED 2026-02-09</summary>

- [x] Phase 1: Business Unit Trend Data (1/1 plans) -- completed 2026-02-09
- [x] Phase 2: Chart Rendering and Card Integration (1/1 plans) -- completed 2026-02-09

</details>

<details>
<summary>v2.1 Call Integration & Dashboard Enhancements (Phases 3-6) -- SHIPPED 2026-03-20</summary>

- [x] Phase 3: Data Foundation (2/2 plans) -- completed 2026-03-20
- [x] Phase 4: Call Log Scraper (2/2 plans) -- completed 2026-03-20
- [x] Phase 5: Expiration Chart Expansion (1/1 plan) -- completed 2026-03-20
- [x] Phase 6: Call Status UI and Export (2/2 plans) -- completed 2026-03-20

</details>

### v2.2 Monthly Subscriber Handling & Dashboard Refinements

- [ ] **Phase 7: Monthly Subscriber Exemption** - Monthly subscribers excluded from call status urgency with correct styling, sorting, and export behavior
- [ ] **Phase 8: Trend Expansion & Log Retention** - 13-week trend view and automated 90-day call log purge
- [ ] **Phase 9: CSR Call Reporting** - Outgoing call counts per CSR displayed on settings page

## Phase Details

### Phase 7: Monthly Subscriber Exemption
**Goal**: Monthly subscribers are visually distinguished from annual subscribers and excluded from call-to-action urgency indicators
**Depends on**: Phase 6 (call status UI from v2.1)
**Requirements**: MONTH-01, MONTH-02, MONTH-03, MONTH-04
**Success Criteria** (what must be TRUE):
  1. A monthly subscriber (last payment between -$0.01 and -$25.00) in the subscriber list shows normal row styling -- no red call status coloring
  2. A monthly subscriber with no call log activity shows no phone icon; a monthly subscriber WITH call log activity shows the appropriate phone icon
  3. Monthly subscribers appear at the bottom of the subscriber list in default sort order
  4. XLSX export renders monthly subscriber rows with no status coloring unless call log activity exists for that subscriber
**Plans**: TBD

Plans:
- [ ] 07-01: TBD
- [ ] 07-02: TBD

### Phase 8: Trend Expansion & Log Retention
**Goal**: Trend charts show a full quarter of history and call log data is automatically maintained
**Depends on**: Nothing (independent of Phase 7)
**Requirements**: TREND-01, MAINT-01
**Success Criteria** (what must be TRUE):
  1. Clicking "Show trend over time" on a business unit displays 13 weeks of data in the trend modal
  2. After the call log import script runs, no call_logs records older than 90 days remain in the database
**Plans**: TBD

Plans:
- [ ] 08-01: TBD

### Phase 9: CSR Call Reporting
**Goal**: Managers can see how many outgoing calls each CSR has made
**Depends on**: Nothing (independent of Phases 7-8)
**Requirements**: CSR-01
**Success Criteria** (what must be TRUE):
  1. The settings page displays a table showing each CSR name with their outgoing call count for the last 60 days
  2. CSR names are mapped from the scraper phone lines (not raw phone numbers)
**Plans**: TBD

Plans:
- [ ] 09-01: TBD

## Progress

**Execution Order:**
Phases 7, 8, 9 can execute in any order (no inter-dependencies). Recommended: 7 first (largest scope), then 8 and 9.

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Business Unit Trend Data | v1 | 1/1 | Complete | 2026-02-09 |
| 2. Chart Rendering and Card Integration | v1 | 1/1 | Complete | 2026-02-09 |
| 3. Data Foundation | v2.1 | 2/2 | Complete | 2026-03-20 |
| 4. Call Log Scraper | v2.1 | 2/2 | Complete | 2026-03-20 |
| 5. Expiration Chart Expansion | v2.1 | 1/1 | Complete | 2026-03-20 |
| 6. Call Status UI and Export | v2.1 | 2/2 | Complete | 2026-03-20 |
| 7. Monthly Subscriber Exemption | v2.2 | 0/? | Not started | - |
| 8. Trend Expansion & Log Retention | v2.2 | 0/? | Not started | - |
| 9. CSR Call Reporting | v2.2 | 0/? | Not started | - |
