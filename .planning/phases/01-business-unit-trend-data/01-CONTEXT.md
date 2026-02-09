# Phase 1: Business Unit Trend Data - Context

**Gathered:** 2026-02-09
**Status:** Ready for planning

<domain>
## Phase Boundary

API serves 12-week trend data filtered per business unit. Each business unit card on the circulation dashboard can fetch its own 12-week trend history. This phase delivers the data layer only — chart rendering and card integration are Phase 2.

</domain>

<decisions>
## Implementation Decisions

### API response shape

- Week labels use sequential format: W1 (oldest) through W12 (most recent)
- Each data point includes: week label, Total Active count, and week-over-week change amount
- W1 change value is null (no prior week to compare)
- Response shape optimized for card chart use case — does not need to mirror existing company-wide trend format

### Data edge cases

- If fewer than 12 weeks exist, pad to 12 entries with null values for missing early weeks
- Partial weeks: sum whatever paper data exists for that week (no carry-forward of missing papers)
- Multiple snapshots in the same calendar week: latest snapshot date wins
- Weeks determined by ISO calendar week boundaries (Monday–Sunday)

### Filtering & parameters

- Always returns most recent 12 weeks — no configurable week count parameter
- Business unit identifier, fetch strategy (batch vs per-card), load timing (eager vs lazy), and invalid BU error handling are all Claude's discretion based on existing codebase patterns

### Data aggregation

- Weekly total = sum of Total Active across all papers belonging to that business unit
- Metric is Total Active only (not Deliverable or other metrics)
- Same aggregation logic for all business units regardless of paper count (Michigan with 1 paper uses same path as Wyoming with 3)
- Paper-to-business-unit mapping follows existing database assignments (Wyoming = TR, LJ, WRN; South Carolina = TJ; Michigan = TA)

### Claude's Discretion

- Response metadata (whether to include summary fields like unit name, total weeks, trend direction)
- Business unit identifier format (name string vs ID)
- Fetch strategy (one call per card vs batch all units)
- Load timing (with initial page load vs lazy after render)
- Error response for invalid business unit
- Cross-paper timing within same calendar week
- What to return when a business unit has zero data (empty array vs 12 nulls)

</decisions>

<specifics>
## Specific Ideas

- Correction noted: Wyoming papers are TR, LJ, WRN (not TJ). TJ belongs to South Carolina.
- The existing `get_trend` API action should be extended with a business unit parameter rather than creating a new endpoint (per roadmap decision).

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

_Phase: 01-business-unit-trend-data_
_Context gathered: 2026-02-09_
