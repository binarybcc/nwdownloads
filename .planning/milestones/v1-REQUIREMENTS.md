# Requirements Archive: v1 Business Unit 12-Week Trend Charts

**Archived:** 2026-02-09
**Status:** SHIPPED

This is the archived requirements specification for v1.
For current requirements, see `.planning/REQUIREMENTS.md` (created for next milestone).

---

## v1 Requirements

**Defined:** 2026-02-09
**Core Value:** Each business unit card tells its own trend story at a glance

### Data

- [x] **DATA-01**: Each business unit card gets its own 12-week trend data (not the whole company)

### Chart

- [x] **CHART-01**: Small line chart with blue area fill (same style as the company-wide chart)
- [x] **CHART-02**: Shows week labels along the bottom (W1, W2, etc.)
- [x] **CHART-03**: Hovering over a point shows the exact subscriber count for that week
- [x] **CHART-04**: Y-axis scales to that business unit's numbers (not the company total)

### Card Layout

- [x] **CARD-01**: Chart sits between the comparison bar and the donut chart
- [x] **CARD-02**: Chart fits the card width on any screen size

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
| CHART-01    | Phase 2 | Complete |
| CHART-02    | Phase 2 | Complete |
| CHART-03    | Phase 2 | Complete |
| CHART-04    | Phase 2 | Complete |
| CARD-01     | Phase 2 | Complete |
| CARD-02     | Phase 2 | Complete |

**Coverage:**

- v1 requirements: 7 total
- Shipped: 7
- Adjusted: 0
- Dropped: 0

---

## Milestone Summary

**Shipped:** 7 of 7 v1 requirements
**Adjusted:** None — all requirements implemented as originally specified
**Dropped:** None

---

_Archived: 2026-02-09 as part of v1 milestone completion_
