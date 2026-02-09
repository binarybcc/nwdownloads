# Requirements: Business Unit 12-Week Trend Charts

**Defined:** 2026-02-09
**Core Value:** Each business unit card tells its own trend story at a glance

## v1 Requirements

### Data

- [x] **DATA-01**: Each business unit card gets its own 12-week trend data (not the whole company)

### Chart

- [ ] **CHART-01**: Small line chart with blue area fill (same style as the company-wide chart)
- [ ] **CHART-02**: Shows week labels along the bottom (W1, W2, etc.)
- [ ] **CHART-03**: Hovering over a point shows the exact subscriber count for that week
- [ ] **CHART-04**: Y-axis scales to that business unit's numbers (not the company total)

### Card Layout

- [ ] **CARD-01**: Chart sits between the comparison bar and the donut chart
- [ ] **CARD-02**: Chart fits the card width on any screen size

## v2 Requirements

None -- this is a focused feature addition.

## Out of Scope

| Feature                                                 | Reason                                                               |
| ------------------------------------------------------- | -------------------------------------------------------------------- |
| Multiple lines (Deliverable, On Vacation) in card chart | Keep simplified -- single Total Active line is cleaner at small size |
| Legend in card chart                                    | Not needed for a single-line chart                                   |
| Collapse/expand toggle for the chart                    | Adds complexity, not requested                                       |
| Configurable week range (8, 12, 24 weeks)               | 12 weeks is the standard, matches company chart                      |
| Replacing the company-wide 12-week trend chart          | It stays as-is below the cards                                       |

## Traceability

| Requirement | Phase   | Status   |
| ----------- | ------- | -------- |
| DATA-01     | Phase 1 | Complete |
| CHART-01    | Phase 2 | Pending  |
| CHART-02    | Phase 2 | Pending  |
| CHART-03    | Phase 2 | Pending  |
| CHART-04    | Phase 2 | Pending  |
| CARD-01     | Phase 2 | Pending  |
| CARD-02     | Phase 2 | Pending  |

**Coverage:**

- v1 requirements: 7 total
- Mapped to phases: 7
- Unmapped: 0

---

_Requirements defined: 2026-02-09_
_Last updated: 2026-02-09 after Phase 1 completion_
