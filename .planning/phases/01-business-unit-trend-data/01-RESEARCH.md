# Phase 1: Business Unit Trend Data - Research

**Researched:** 2026-02-09
**Domain:** PHP API extension + JavaScript data integration (existing codebase)
**Confidence:** HIGH

## Summary

This phase extends the existing `get_trend` API action in `web/api/legacy.php` to support per-business-unit filtering, returning 12 weeks of Total Active subscriber counts. The work is entirely within the existing PHP/JavaScript/MariaDB stack with no new dependencies.

The codebase already has two patterns that do nearly identical work:

1. **Company-wide 12-week trend** (lines 773-831 of `legacy.php`) -- iterates ISO weeks using `week_num`/`year` columns, sums across all papers, handles missing weeks with null padding. This is the primary pattern to follow.
2. **`getBusinessUnitDetail` function** (lines 293-377) -- fetches a 12-week trend for a specific business unit, but uses `snapshot_date` ranges and `DAYOFWEEK(snapshot_date) = 7` filtering instead of the modern `week_num`/`year` approach.

The recommendation is to follow the company-wide trend pattern (pattern #1) since it uses the modern `week_num`/`year` columns and already handles missing-week padding with null values. Add a `business_unit` filter to the existing `get_trend` action's case in the router, bypassing the `getHistoricalTrend` function (which queries `subscriber_snapshots` for metric-specific counts, not `daily_snapshots` for totals).

**Primary recommendation:** Add a new PHP function (e.g., `getBusinessUnitTrend`) that mirrors the company-wide trend query loop (lines 773-831) but adds a `WHERE business_unit = ?` filter and sums only Total Active. Wire it into the existing `get_trend` case or a dedicated case in the API router. On the frontend, fetch this data during `loadDashboardData()` and attach it to each business unit card's data.

## Standard Stack

### Core

| Library    | Version  | Purpose         | Why Standard                                            |
| ---------- | -------- | --------------- | ------------------------------------------------------- |
| PHP        | 8.2      | API backend     | Already in production (Synology native)                 |
| MariaDB    | 10       | Database        | Already in production (Synology native)                 |
| PDO        | built-in | Database access | Already used throughout `legacy.php`                    |
| Chart.js   | 4.4.0    | Chart rendering | Already loaded via CDN in `index.php` (Phase 2 concern) |
| Vanilla JS | ES6+     | Frontend        | Already used, no framework                              |

### Supporting

No additional libraries needed. Everything required is already in the stack.

### Alternatives Considered

| Instead of                      | Could Use                        | Tradeoff                                                                                                                                                                                                                                                                                                                  |
| ------------------------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| New PHP function                | Extend `getHistoricalTrend`      | `getHistoricalTrend` queries `subscriber_snapshots` for metric-specific counts -- wrong table and wrong aggregation for Total Active from `daily_snapshots`. New function is cleaner.                                                                                                                                     |
| New API endpoint                | Extend `get_trend` action        | Decision from CONTEXT.md: extend `get_trend` with business unit parameter. But `get_trend` currently routes to `getHistoricalTrend` which requires `metric_type` and `metric_value`. Two options: add a new conditional branch in the `get_trend` case, or add a new case like `get_bu_trend`. See Architecture Patterns. |
| Batch fetch (one call, all BUs) | Per-card fetch (one call per BU) | Batch is fewer HTTP requests, but per-card is simpler and matches existing pattern (`business_unit_detail` is per-unit). Decision is Claude's discretion per CONTEXT.md.                                                                                                                                                  |

**Installation:** None required -- no new dependencies.

## Architecture Patterns

### Relevant Existing File Structure

```
web/
  api.php                    # Router -- delegates to api/legacy.php
  api/legacy.php             # Monolithic API (all handlers here)
  assets/js/core/app.js      # Main dashboard JS (loadDashboardData, renderBusinessUnits)
```

### Pattern 1: Week-Number Iteration (Company-Wide Trend)

**What:** The existing company-wide 12-week trend query (lines 773-831 of `legacy.php`) iterates through 12 ISO week numbers, querying `daily_snapshots` for each week by `week_num` and `year`, and padding missing weeks with null values.

**When to use:** This is the pattern to follow for per-business-unit trend data.

**Key characteristics:**

- Uses `week_num` and `year` columns (added Dec 2025 migration)
- Loops 12 times, one query per week
- Handles year boundary (week_num going below 1 or above 52)
- Returns null values for missing weeks (not skipped)
- Groups by `snapshot_date, week_num, year`
- Excludes `paper_code != 'FN'` (sold publications)

**Example (adapted from lines 773-831):**

```php
// Source: web/api/legacy.php lines 773-831
// Calculate starting week (12 weeks back from current)
$startWeekNum = $week_num - 11;
$startYear = $year;
if ($startWeekNum < 1) {
    $weeksNeeded = abs($startWeekNum) + 1;
    $startYear--;
    $startWeekNum = 52 - $weeksNeeded + 1;
}

for ($i = 0; $i < 12; $i++) {
    $currentWeekNum = $startWeekNum + $i;
    $currentYear = $startYear;
    if ($currentWeekNum > 52) {
        $currentWeekNum = $currentWeekNum - 52;
        $currentYear++;
    }
    // Query with business_unit filter added:
    $stmt = $pdo->prepare("
        SELECT snapshot_date, week_num, year,
               SUM(total_active) as total_active
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND week_num = ? AND year = ?
        GROUP BY snapshot_date, week_num, year
    ");
    $stmt->execute([$businessUnit, $currentWeekNum, $currentYear]);
    $weekData = $stmt->fetch(PDO::FETCH_ASSOC);
    // ... null padding for missing weeks
}
```

### Pattern 2: API Response Shape (From CONTEXT.md Decisions)

**What:** The response must use sequential week labels (W1-W12), include Total Active count and week-over-week change, with W1 change as null.

**Response shape:**

```json
{
  "business_unit": "Wyoming",
  "weeks": 12,
  "data": [
    { "label": "W1", "total_active": null, "change": null },
    { "label": "W2", "total_active": null, "change": null },
    { "label": "W3", "total_active": 1580, "change": null },
    { "label": "W4", "total_active": 1595, "change": 15 },
    ...
    { "label": "W12", "total_active": 1610, "change": -5 }
  ]
}
```

Notes:

- If fewer than 12 weeks exist, early entries are padded with null `total_active` and null `change`
- W1's `change` is always null (no prior week to compare)
- First non-null entry's `change` is also null (no prior value)
- `total_active` = SUM across all papers for that business unit in that week
- Multiple snapshots in same calendar week: latest `snapshot_date` wins (handled by `GROUP BY` picking the max date, or explicit `MAX(snapshot_date)`)

### Pattern 3: Fetch Strategy Options (Claude's Discretion)

**Option A: Batch fetch (recommended)**

Fetch all business unit trends in a single API call. The PHP function iterates over the known business units and returns a map.

- Pros: 1 HTTP request instead of 3, simpler frontend code
- Cons: Slightly larger response, all-or-nothing loading

**Option B: Per-card fetch**

Each business unit card makes its own API call.

- Pros: Cards can load independently, matches `business_unit_detail` pattern
- Cons: 3 HTTP requests, more frontend coordination

**Recommendation: Option A (batch).** The data is small (12 entries x 3 units = 36 data points), the business units are known up front, and it avoids the complexity of managing 3 separate fetch states. Can be included in the existing `overview` response or as a separate action.

### Pattern 4: Integration Point (Dashboard Data Flow)

**What:** The existing `loadDashboardData()` function fetches the `overview` action and stores the result in `dashboardData`. The `renderBusinessUnits()` function reads from `dashboardData.by_business_unit`.

**Two integration approaches:**

1. **Embed in overview response** -- Add a `business_unit_trends` key to the existing overview response. Frontend reads it alongside other data. Zero additional fetch calls.
   - Downside: Makes the overview response slightly heavier (adds ~36 data points)
   - Upside: One request, data always in sync with current week

2. **Separate action, parallel fetch** -- Create a `get_bu_trends` action. Frontend fetches it in parallel with `overview` during `loadDashboardData()`.
   - Downside: Second HTTP request
   - Upside: Separation of concerns, can be cached independently

**Recommendation: Embed in overview response.** The additional data is tiny (~2KB JSON), avoids race conditions, and guarantees the trend data matches the same week being displayed.

### Anti-Patterns to Avoid

- **Re-using `getHistoricalTrend` for this:** That function queries `subscriber_snapshots` and requires `metric_type`/`metric_value` parameters. Business unit Total Active comes from `daily_snapshots` with a simple SUM. Different table, different aggregation.
- **Using `DAYOFWEEK(snapshot_date) = 7`:** The old `getBusinessUnitDetail` function uses this to filter Saturdays only. The modern approach uses `week_num`/`year` columns which handle non-Saturday snapshots correctly. The old pattern would miss data uploaded on non-Saturday dates.
- **Querying all 12 weeks in one SQL with GROUP BY week_num:** This would skip missing weeks instead of returning null entries. The loop-per-week approach is necessary to pad missing weeks.
- **Computing week labels from dates:** CONTEXT.md specifies sequential W1-W12 labels, not date-based labels. The loop index directly provides the label.

## Don't Hand-Roll

| Problem                     | Don't Build           | Use Instead                                               | Why                                                                                                                                                                                     |
| --------------------------- | --------------------- | --------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ISO week calculation        | Custom week math      | PHP `DateTime::format('W')` and `format('o')`             | Already used in `getWeekBoundaries()` (line 71-72). Handles year boundaries, leap years.                                                                                                |
| Year boundary crossing      | Manual year/week math | Existing pattern from lines 778-792                       | The `startWeekNum < 1` and `currentWeekNum > 52` guards are already tested and working in production. Copy them.                                                                        |
| Business unit paper mapping | Hardcoded paper list  | `WHERE business_unit = ?` on `daily_snapshots`            | The database already has the `business_unit` column. No need to maintain a separate mapping in PHP.                                                                                     |
| Multiple snapshots per week | Custom deduplication  | `GROUP BY snapshot_date, week_num, year` then take latest | SQL handles this. If multiple snapshot_dates exist in the same week_num/year, the GROUP BY with SUM aggregates correctly per snapshot_date. Pick the one with the latest snapshot_date. |

**Key insight:** The company-wide trend code (lines 773-831) already solves every data problem this phase needs. The only change is adding `AND business_unit = ?` to the query and running it per unit.

## Common Pitfalls

### Pitfall 1: Week 53 / Year Boundary Issues

**What goes wrong:** ISO 8601 allows week 53 in some years. The existing code uses `52` as the max week number. If data spans a year with 53 weeks, the 53rd week data could be missed or the week-number math could produce incorrect results.

**Why it happens:** The existing company-wide trend code uses hardcoded `52` for year boundary calculations.

**How to avoid:** The production data starts from January 2025. Year 2025 has 52 ISO weeks. Year 2026 also has 53 ISO weeks (week 53 starts Dec 28, 2026). This could become an issue in late 2026. For now, the existing pattern works. Flag for future fix but don't block on it.

**Warning signs:** Data gaps in late December / early January weeks.

### Pitfall 2: Multiple Snapshots in Same Calendar Week

**What goes wrong:** If a CSV is uploaded twice in the same week (e.g., Monday and Saturday), there could be two snapshot_dates with the same week_num/year. The current GROUP BY produces one row per snapshot_date -- if there are two, the query returns multiple rows for one week.

**Why it happens:** The query groups by `snapshot_date, week_num, year`, not just `week_num, year`.

**How to avoid:** Per CONTEXT.md decision: "latest snapshot date wins." Use a subquery or `HAVING snapshot_date = MAX(snapshot_date)` to pick only the latest snapshot within each week. Or group only by `week_num, year` and use `MAX(snapshot_date)` as the representative date.

**Warning signs:** Duplicate week entries in the trend array, more than 12 data points returned.

### Pitfall 3: Change Calculation When First Non-Null Follows Nulls

**What goes wrong:** If weeks W1-W5 are null and W6 is the first week with data, W6's change should be null (no prior week to compare), not calculated against a null value.

**Why it happens:** Naive change calculation: `current - previous` where previous is null gives NaN or 0.

**How to avoid:** Track the "last non-null value" separately. When computing change, only calculate if both current and previous non-null values exist. The first non-null entry always has `change: null`.

**Warning signs:** Change values of 0 for the first data point when there should be null.

### Pitfall 4: Business Unit Name Mismatch

**What goes wrong:** Frontend sends "South Carolina" but the database has a different casing or extra whitespace.

**Why it happens:** Business unit names are strings, not IDs.

**How to avoid:** The existing codebase consistently uses exact string matching (`South Carolina`, `Wyoming`, `Michigan`). The `BUSINESS_UNITS` constant in `app.js` and the database `business_unit` column are in sync. Validate the business unit parameter against a whitelist in PHP.

**Warning signs:** Empty result sets for valid business units.

### Pitfall 5: N+1 Query Problem (12 Queries Per Business Unit)

**What goes wrong:** The week-iteration pattern runs 12 separate queries per business unit. With 3 units embedded in overview, that's 36 additional queries per page load.

**Why it happens:** The pattern requires null-padding for missing weeks, which is hard to do in a single query.

**How to avoid:** This is an acceptable tradeoff for this data size. Each query is simple (indexed on `week_num, year, business_unit`), returns at most 1 row, and the total additional load is ~36 trivial queries. For context, the existing overview already runs 12 queries for the company-wide trend. The index `idx_week ON daily_snapshots(week_num, year)` exists. Adding `business_unit` to the WHERE clause is still covered by the `idx_business_unit` index.

**Alternative:** A single query fetching all weeks for a unit, then padding in PHP. This reduces 12 queries to 1 per unit (3 total), at the cost of slightly more complex PHP code. Both approaches are valid; the loop approach is proven in the codebase.

## Code Examples

### Business Unit Trend Query (per week, adapted from existing pattern)

```php
// Source: Adapted from web/api/legacy.php lines 773-831
// Add business_unit filter to the existing week-iteration pattern

function getBusinessUnitTrendData(PDO $pdo, string $businessUnit, int $weekNum, int $year): array
{
    $trend = [];
    $startWeekNum = $weekNum - 11;
    $startYear = $year;

    if ($startWeekNum < 1) {
        $weeksNeeded = abs($startWeekNum) + 1;
        $startYear--;
        $startWeekNum = 52 - $weeksNeeded + 1;
    }

    $lastNonNullValue = null;

    for ($i = 0; $i < 12; $i++) {
        $currentWeekNum = $startWeekNum + $i;
        $currentYear = $startYear;
        if ($currentWeekNum > 52) {
            $currentWeekNum -= 52;
            $currentYear++;
        }

        $stmt = $pdo->prepare("
            SELECT
                MAX(snapshot_date) as snapshot_date,
                SUM(total_active) as total_active
            FROM daily_snapshots
            WHERE business_unit = ?
              AND paper_code != 'FN'
              AND week_num = ?
              AND year = ?
        ");
        $stmt->execute([$businessUnit, $currentWeekNum, $currentYear]);
        $weekData = $stmt->fetch(PDO::FETCH_ASSOC);

        $label = 'W' . ($i + 1);

        if ($weekData && $weekData['total_active'] !== null) {
            $totalActive = (int)$weekData['total_active'];
            $change = ($lastNonNullValue !== null) ? $totalActive - $lastNonNullValue : null;
            $lastNonNullValue = $totalActive;

            $trend[] = [
                'label' => $label,
                'total_active' => $totalActive,
                'change' => $change
            ];
        } else {
            $trend[] = [
                'label' => $label,
                'total_active' => null,
                'change' => null
            ];
        }
    }

    return $trend;
}
```

### Batch Fetch for All Business Units

```php
// Fetch trend for all known business units in one function call
function getAllBusinessUnitTrends(PDO $pdo, int $weekNum, int $year): array
{
    $units = ['South Carolina', 'Wyoming', 'Michigan'];
    $result = [];

    foreach ($units as $unit) {
        $result[$unit] = getBusinessUnitTrendData($pdo, $unit, $weekNum, $year);
    }

    return $result;
}
```

### Integration into Overview Response

```php
// Inside getOverviewEnhanced(), after existing business unit processing:
// Add around line 898 (before $dataRange = getDataRange($pdo))

$business_unit_trends = getAllBusinessUnitTrends($pdo, $week_num, $year);

// Then include in the return array:
return [
    // ... existing keys ...
    'business_unit_trends' => $business_unit_trends,
    // ... rest of return ...
];
```

### Frontend Consumption (in renderBusinessUnits)

```javascript
// Inside renderBusinessUnits(), access the trend data:
const trends = dashboardData.business_unit_trends || {};

for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
  const data = byUnit[unitName];
  if (!data) continue;

  const trendData = trends[unitName] || [];
  // trendData is array of { label: "W1", total_active: 1580, change: 15 }
  // Available for chart rendering in Phase 2
}
```

### Optimized Single-Query Alternative

```php
// Instead of 12 queries per unit, fetch all available weeks in one query
// Then pad missing weeks in PHP

function getBusinessUnitTrendOptimized(PDO $pdo, string $businessUnit, int $weekNum, int $year): array
{
    $startWeekNum = $weekNum - 11;
    $startYear = $year;
    if ($startWeekNum < 1) {
        $weeksNeeded = abs($startWeekNum) + 1;
        $startYear--;
        $startWeekNum = 52 - $weeksNeeded + 1;
    }

    // Single query: get all existing data in the 12-week window
    $stmt = $pdo->prepare("
        SELECT
            week_num,
            year,
            MAX(snapshot_date) as snapshot_date,
            SUM(total_active) as total_active
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND ((year = ? AND week_num >= ?) OR (year = ? AND week_num <= ?))
        GROUP BY week_num, year
        ORDER BY year ASC, week_num ASC
    ");
    // This query handles same-year range; cross-year needs adjustment
    $stmt->execute([$businessUnit, $startYear, $startWeekNum, $year, $weekNum]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Index by "year-weeknum" for fast lookup
    $dataByWeek = [];
    foreach ($rows as $row) {
        $key = $row['year'] . '-' . $row['week_num'];
        $dataByWeek[$key] = (int)$row['total_active'];
    }

    // Build padded 12-week array
    $trend = [];
    $lastNonNullValue = null;

    for ($i = 0; $i < 12; $i++) {
        $currentWeekNum = $startWeekNum + $i;
        $currentYear = $startYear;
        if ($currentWeekNum > 52) {
            $currentWeekNum -= 52;
            $currentYear++;
        }

        $key = $currentYear . '-' . $currentWeekNum;
        $label = 'W' . ($i + 1);

        if (isset($dataByWeek[$key])) {
            $totalActive = $dataByWeek[$key];
            $change = ($lastNonNullValue !== null) ? $totalActive - $lastNonNullValue : null;
            $lastNonNullValue = $totalActive;
            $trend[] = ['label' => $label, 'total_active' => $totalActive, 'change' => $change];
        } else {
            $trend[] = ['label' => $label, 'total_active' => null, 'change' => null];
        }
    }

    return $trend;
}
```

## State of the Art

| Old Approach                             | Current Approach                 | When Changed            | Impact                                                            |
| ---------------------------------------- | -------------------------------- | ----------------------- | ----------------------------------------------------------------- |
| `DAYOFWEEK(snapshot_date) = 7` filtering | `week_num`/`year` column queries | Dec 2025 (migration 03) | Handles non-Saturday snapshots correctly                          |
| `snapshot_date` range queries            | ISO week number queries          | Dec 2025                | Consistent week boundaries regardless of upload day               |
| Monolithic API file                      | Router + legacy.php              | Current structure       | New code goes in `legacy.php` for now (no endpoint migration yet) |

**Deprecated/outdated:**

- `DAYOFWEEK(snapshot_date) = 7`: Used in `getBusinessUnitDetail` and `getBusinessUnitComparison`. These older functions should not be used as a pattern for new code. Use `week_num`/`year` instead.
- `getSaturdayForWeek()`: Not needed when using `week_num`/`year` queries.

## Open Questions

1. **Multiple snapshot_dates per week_num/year**
   - What we know: The `GROUP BY snapshot_date, week_num, year` pattern in the existing code produces one row per unique snapshot_date. CONTEXT.md says "latest snapshot date wins."
   - What's unclear: Whether `GROUP BY week_num, year` with `MAX(snapshot_date)` gives correct SUM(total_active). If two uploads exist in the same week with different paper sets, summing across both would double-count.
   - Recommendation: Use `MAX(snapshot_date)` as a subquery filter: first find the latest snapshot_date for the week, then SUM only rows with that date. This matches the "latest snapshot date wins" decision exactly.

2. **Performance of 36 additional queries in overview**
   - What we know: Each query hits indexed columns (`week_num`, `year`, `business_unit`). The `daily_snapshots` table has ~250 records (small).
   - What's unclear: Exact query time under production load.
   - Recommendation: Acceptable for current data size. The optimized single-query approach reduces this to 3 queries total. Implement whichever the planner prefers; both are valid.

3. **Zero-data business units**
   - What we know: CONTEXT.md marks this as Claude's discretion.
   - Recommendation: Return 12 entries all with null `total_active` and null `change`. This is consistent with the existing missing-week pattern and simplifies frontend handling.

## Sources

### Primary (HIGH confidence)

- `web/api/legacy.php` -- Existing company-wide trend query (lines 773-831), `getBusinessUnitDetail` (lines 293-377), `getHistoricalTrend` (lines 1753-1820), API router (lines 2447-2583)
- `web/assets/js/core/app.js` -- Frontend dashboard data flow, `BUSINESS_UNITS` config, `renderBusinessUnits()`, `renderTrendChart()`
- `database/init/03_add_week_columns.sql` -- `week_num`/`year` column migration
- `sql/00_create_daily_snapshots.sql` -- Table schema with indexes
- `.planning/phases/01-business-unit-trend-data/01-CONTEXT.md` -- Locked decisions on response shape, edge cases, filtering

### Secondary (MEDIUM confidence)

- `web/assets/js/components/trend-slider.js` -- Existing `get_trend` API consumer pattern (for understanding current usage)
- `web/includes/database.php` -- Database connection singleton pattern

### Tertiary (LOW confidence)

None -- all findings are from direct codebase analysis.

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH -- No new libraries, all existing tech
- Architecture: HIGH -- Extending existing proven patterns with minimal changes
- Pitfalls: HIGH -- Identified from direct code analysis and CONTEXT.md edge case decisions

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (stable codebase, no external dependency changes expected)
