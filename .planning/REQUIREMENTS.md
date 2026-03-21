# Requirements: NWDownloads Circulation Dashboard

**Defined:** 2026-03-20
**Core Value:** Circulation managers can see subscriber health at a glance and take action on retention

## v2.2 Requirements

Requirements for milestone v2.2 -- Monthly Subscriber Handling & Dashboard Refinements.

### Monthly Subscriber Handling

- [x] **MONTH-01**: Monthly subscribers (last payment between -$0.01 and -$25.00) display with normal row styling (no red call status color) in subscriber list
- [x] **MONTH-02**: Monthly subscribers show no phone icon unless call log activity exists for that subscriber
- [x] **MONTH-03**: Monthly subscribers sort to the bottom of the subscriber list in default sort order
- [x] **MONTH-04**: Monthly subscribers export with no row coloring in XLSX unless call log activity exists

### Trend View

- [x] **TREND-01**: "Show trend over time" displays 13 weeks of data (expanded from current)

### Call Log Maintenance

- [x] **MAINT-01**: Call log import script purges records older than 90 days during each run

### CSR Reporting

- [x] **CSR-01**: Settings page displays outgoing call count per CSR (mapped from scraper phone lines) for the last 60 days

## Future Requirements

### Call Enhancements (deferred from v2.1)

- **CALL-F01**: Manual call outcome annotation
- **CALL-F02**: XSI API integration if carrier enables
- **CALL-F03**: Call duration tracking
- **CALL-F04**: Agent attribution display (multiple staff icons)

### Dashboard Enhancements (deferred from v2.1)

- **DASH-F01**: Visual de-emphasis of far-out expiration weeks
- **DASH-F02**: New starts count on BU cards

## Out of Scope

| Feature | Reason |
|---------|--------|
| Real-time call event push | BroadWorks XSI-Events not available |
| Inbound call count on settings | Only outgoing calls requested for CSR reporting |
| Date picker for CSR report | Fixed 60-day window sufficient for now |
| Monthly subscriber auto-detection from subscription type | Using payment amount heuristic (-$0.01 to -$25.00) per business rules |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| MONTH-01 | Phase 7 | Complete |
| MONTH-02 | Phase 7 | Complete |
| MONTH-03 | Phase 7 | Complete |
| MONTH-04 | Phase 7 | Complete |
| TREND-01 | Phase 8 | Complete |
| MAINT-01 | Phase 8 | Complete |
| CSR-01 | Phase 9 | Complete |

**Coverage:**
- v2.2 requirements: 7 total
- Mapped to phases: 7
- Unmapped: 0

---
*Requirements defined: 2026-03-20*
*Last updated: 2026-03-20 after roadmap creation*
