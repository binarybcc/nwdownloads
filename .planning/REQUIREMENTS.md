# Requirements: NWDownloads Circulation Dashboard

**Defined:** 2026-03-20
**Core Value:** Circulation managers can see subscriber health at a glance and take action on retention

## v2.1 Requirements

Requirements for milestone v2.1: Call Integration & Dashboard Enhancements.

### Import Fixes

- [x] **IMPORT-01**: New starts CSV imports successfully with both M/D/YY and YYYY-MM-DD date formats
- [x] **IMPORT-02**: Two failed CSVs (Mar 9, Mar 16) are reprocessed from NAS failed/ directory

### Expiration Chart

- [x] **CHART-01**: Subscription expiration chart shows 8-week view (Past Due, This Week, Next Week, Week +2 through Week +6)
- [x] **CHART-02**: Right-click context menu works on all 8 week buckets
- [x] **CHART-03**: Color gradient extends naturally across 8 bars (red -> orange -> yellow -> green)

### Call Log Scraper

- [x] **CALL-01**: BroadWorks call logs (placed, received, missed) for BC and CW are scraped and stored in `call_logs` table
- [x] **CALL-02**: Phone numbers are normalized to bare 10-digit at ingest (both subscriber and call log sides)
- [ ] **CALL-03**: Scraper runs hourly 8am-8pm ET via launchd, with business-hours guard in PHP
- [x] **CALL-04**: Scraper verifies login success and logs failures (no silent empty runs)

### Call Status UI

- [ ] **UI-01**: Expiration subscriber table shows phone icon column with call status (green=contacted, orange=missed call from subscriber, red=no contact)
- [ ] **UI-02**: Row background color matches call status for visual scanning
- [ ] **UI-03**: Call status column is sortable (not-contacted first by default)
- [ ] **UI-04**: Tooltip on phone icon shows last contact date and type
- [ ] **UI-05**: XLSX export preserves row coloring and call status data
- [ ] **UI-06**: Subscriber list and XLSX export show timestamp of last call log sync

## Future Requirements

### Call Enhancements

- **CALL-F01**: Manual outcome annotation (voicemail vs. live contact vs. no answer)
- **CALL-F02**: XSI REST API integration if Segra enables access (replaces web scraping)
- **CALL-F03**: Call duration tracking via Enhanced Call Logs (if BroadWorks enables)
- **CALL-F04**: Agent attribution (which staff member made/received the call) as separate display
- **CALL-F05**: 90-day retention policy with automatic pruning of old call_logs

### Dashboard Enhancements

- **DASH-F01**: Visual de-emphasis of weeks 5-8 in expiration chart (lighter colors or collapsible)
- **DASH-F02**: New starts count on BU cards
- **DASH-F03**: Server-side XLSX export via PhpSpreadsheet (if client-side SheetJS proves limiting)

## Out of Scope

| Feature                                  | Reason                                                     |
| ---------------------------------------- | ---------------------------------------------------------- |
| Real-time call event push                | BroadWorks XSI-Events not available from carrier           |
| Call duration in UI                      | Basic Call Logs don't include duration                     |
| Inbound calls from non-subscribers in UI | Only subscriber-matched calls shown; raw data still stored |
| Multiple staff attribution icons         | Deferred — single "contacted" status sufficient for MVP    |
| Synology Task Scheduler for scraper      | Deletes jobs — use macOS launchd instead                   |
| PhpSpreadsheet server-side export        | SheetJS client-side already supports cell styling          |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase   | Status   |
| ----------- | ------- | -------- |
| IMPORT-01   | Phase 3 | Complete |
| IMPORT-02   | Phase 3 | Complete |
| CALL-02     | Phase 3 | Complete |
| CALL-01     | Phase 4 | Complete |
| CALL-03     | Phase 4 | Pending  |
| CALL-04     | Phase 4 | Complete |
| CHART-01    | Phase 5 | Complete |
| CHART-02    | Phase 5 | Complete |
| CHART-03    | Phase 5 | Complete |
| UI-01       | Phase 6 | Pending  |
| UI-02       | Phase 6 | Pending  |
| UI-03       | Phase 6 | Pending  |
| UI-04       | Phase 6 | Pending  |
| UI-05       | Phase 6 | Pending  |
| UI-06       | Phase 6 | Pending  |

**Coverage:**

- v2.1 requirements: 16 total
- Mapped to phases: 16
- Unmapped: 0

---

_Requirements defined: 2026-03-20_
_Last updated: 2026-03-20 after Phase 3 completion (IMPORT-01, IMPORT-02, CALL-02 complete)_
