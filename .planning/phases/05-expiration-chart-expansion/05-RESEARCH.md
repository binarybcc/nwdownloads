# Phase 5: Expiration Chart Expansion - Research

**Researched:** 2026-03-20
**Domain:** PHP SQL CASE expansion + Chart.js bar chart colors + context menu labels
**Confidence:** HIGH

## Summary

This phase expands the existing 4-bucket expiration chart (Past Due, This Week, Next Week, Week +2) to an 8-bucket view by adding Week +3 through Week +6. The work touches exactly three files in a tightly coupled change: the SQL CASE statement in `legacy.php`, the color array in `detail_panel.js`, and the subscriber drill-down in `getExpirationSubscribers()` plus `getMetricCount()`.

The current implementation is clean and well-structured. Each bucket is a named CASE branch in SQL, a label-matched color in JS, and a switch case in PHP subscriber queries. Extending from 4 to 8 buckets follows the exact same pattern -- no new libraries, no new architecture, just replicating the existing pattern 4 more times with adjusted date intervals and colors.

**Primary recommendation:** Add 4 new CASE branches in the SQL query (7-day intervals for weeks +3 through +6), add 4 new colors in the JS `backgroundColors` map, and add 4 new switch cases in `getExpirationSubscribers()` and `getMetricCount()`. All in one coordinated commit.

<phase_requirements>

## Phase Requirements

| ID       | Description                                                                                  | Research Support                                                                                                                                                                    |
| -------- | -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CHART-01 | Expiration chart shows 8-week view (Past Due, This Week, Next Week, Week +2 through Week +6) | SQL CASE expansion pattern identified -- add 4 WHEN branches for days 22-28, 29-35, 36-42, 43-49                                                                                    |
| CHART-02 | Right-click context menu works on all 8 week buckets                                         | Context menu reads labels from chart data dynamically -- no menu code changes needed; subscriber drill-down (`getExpirationSubscribers`, `getMetricCount`) needs 4 new switch cases |
| CHART-03 | Color gradient extends naturally across 8 bars (red -> orange -> yellow -> green)            | JS `backgroundColors` map needs 4 new entries; 8-stop gradient from red through green documented below                                                                              |

</phase_requirements>

## Standard Stack

No new libraries required. This phase uses only what is already in the project.

### Core (already in project)

| Library    | Version                   | Purpose                                              | Why Standard                               |
| ---------- | ------------------------- | ---------------------------------------------------- | ------------------------------------------ |
| Chart.js   | CDN (loaded in index.php) | Bar chart rendering                                  | Already renders the 4-bar expiration chart |
| PHP 8.2    | 8.2.x                     | Server-side SQL + subscriber queries                 | Production NAS runtime                     |
| MariaDB 10 | 10.x                      | `subscriber_snapshots` table with `paid_thru` column | Production database                        |

### Supporting

No additional libraries needed.

### Alternatives Considered

None -- this is a pure extension of existing patterns.

## Architecture Patterns

### Current Expiration Chart Data Flow

```
1. PHP: getOverviewEnhanced() in legacy.php
   -> SQL CASE on paid_thru with date intervals
   -> Returns array of {week_bucket, count}

2. JS: renderExpirationChart() in detail_panel.js
   -> Maps labels to colors via label-matching
   -> Creates Chart.js bar chart

3. Context Menu: addExpirationChartContextMenu() in chart-context-integration.js
   -> Right-click reads chart.data.labels[index] dynamically
   -> Passes label as metric to showSubscriberList()

4. Subscriber Drill-down: getExpirationSubscribers() in legacy.php
   -> switch($bucket) with date arithmetic per case
   -> Returns subscriber rows for that week
```

### Files to Modify

```
web/api/legacy.php           # 3 functions: getOverviewEnhanced(), getExpirationSubscribers(), getMetricCount()
web/assets/js/components/detail_panel.js  # 1 function: renderExpirationChart()
web/assets/js/charts/chart-layout-manager.js  # 1 metadata entry: title change "4-Week" -> "8-Week"
```

### Pattern: SQL CASE Extension

Current SQL (lines 1248-1270 of legacy.php):

```sql
CASE
    WHEN paid_thru < ? THEN 'Past Due'
    WHEN paid_thru BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) THEN 'This Week'
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 8 DAY) AND DATE_ADD(?, INTERVAL 14 DAY) THEN 'Next Week'
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 15 DAY) AND DATE_ADD(?, INTERVAL 21 DAY) THEN 'Week +2'
END as week_bucket
```

Extend with:

```sql
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 22 DAY) AND DATE_ADD(?, INTERVAL 28 DAY) THEN 'Week +3'
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 29 DAY) AND DATE_ADD(?, INTERVAL 35 DAY) THEN 'Week +4'
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 36 DAY) AND DATE_ADD(?, INTERVAL 42 DAY) THEN 'Week +5'
    WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 43 DAY) AND DATE_ADD(?, INTERVAL 49 DAY) THEN 'Week +6'
```

The WHERE clause filter must also expand: `paid_thru <= DATE_ADD(?, INTERVAL 49 DAY)` (was 21 DAY).

The ORDER BY CASE must add entries 5-8 for Week +3 through Week +6.

The parameter count increases: currently 9 `?` placeholders (7 CASE + 1 WHERE snapshot + 1 WHERE date filter). Each new WHEN adds 2 `?` placeholders (8 new), plus the final filter stays at 1. New total: 17 `?` for CASE + 1 WHERE snapshot + 1 WHERE filter = 19 total snapshot_date params before paper_codes.

### Pattern: JS Color Gradient (8 stops, red to green)

Current 4 colors + fallback gray:

```javascript
if (label === 'Past Due') return 'rgba(239, 68, 68, 0.8)'; // red-500
if (label === 'This Week') return 'rgba(251, 146, 60, 0.8)'; // orange-400
if (label === 'Next Week') return 'rgba(251, 191, 36, 0.8)'; // amber-400
if (label === 'Week +2') return 'rgba(253, 224, 71, 0.8)'; // yellow-300
return 'rgba(156, 163, 175, 0.8)'; // gray (fallback)
```

Recommended 8-color gradient (red -> orange -> amber -> yellow -> lime -> green):

```javascript
if (label === 'Past Due') return 'rgba(239, 68, 68, 0.8)'; // red-500
if (label === 'This Week') return 'rgba(245, 158, 11, 0.8)'; // amber-500
if (label === 'Next Week') return 'rgba(251, 191, 36, 0.8)'; // amber-400
if (label === 'Week +2') return 'rgba(253, 224, 71, 0.8)'; // yellow-300
if (label === 'Week +3') return 'rgba(250, 240, 137, 0.8)'; // yellow-200
if (label === 'Week +4') return 'rgba(190, 242, 100, 0.8)'; // lime-300
if (label === 'Week +5') return 'rgba(134, 239, 128, 0.8)'; // green-300
if (label === 'Week +6') return 'rgba(74, 222, 128, 0.8)'; // green-400
```

Note: The existing "This Week" color (rgba(251,146,60)) was orange-400. With 8 bars, the gradient needs more granular steps. Adjust colors so the transition feels natural across 8 stops.

### Pattern: Subscriber Drill-down Switch Cases

`getExpirationSubscribers()` (line 1416) uses a switch statement with manual date arithmetic per bucket. Each new Week +N case follows the exact same pattern as Week +2:

```php
case 'Week +3':
    $weekStart->modify('+21 days');
    $weekEnd->modify('+27 days');
    // ... same SELECT as Week +2
    break;
```

Day offsets per bucket:
| Bucket | Start offset | End offset |
|--------|-------------|------------|
| Past Due | n/a (< snapshot) | n/a |
| This Week | +0 | +6 |
| Next Week | +7 | +13 |
| Week +2 | +14 | +20 |
| Week +3 | +21 | +27 |
| Week +4 | +28 | +34 |
| Week +5 | +35 | +41 |
| Week +6 | +42 | +48 |

`getMetricCount()` (line 1781) also needs the same 4 new elseif branches, following the `Week +2` pattern with adjusted week offsets.

### Anti-Patterns to Avoid

- **Copy-pasting the full SELECT for each case in getExpirationSubscribers():** The current code duplicates the entire SELECT for each bucket. While refactoring to a single query with parameters would be ideal, do NOT refactor in this phase -- maintain the existing pattern for consistency. Phase scope is extension, not refactoring.
- **Hardcoding interval math inconsistently between SQL CASE and PHP switch:** The SQL uses `DATE_ADD(?, INTERVAL N DAY)` while PHP uses `$weekStart->modify('+N days')`. Both must use the SAME day offsets. Cross-check the table above.

## Don't Hand-Roll

| Problem                   | Don't Build              | Use Instead                                | Why                                                                                        |
| ------------------------- | ------------------------ | ------------------------------------------ | ------------------------------------------------------------------------------------------ |
| Color gradient            | Manual RGB interpolation | Pre-selected Tailwind palette stops        | Tailwind colors are tested for visual harmony                                              |
| Context menu for new bars | New event listeners      | Existing `addExpirationChartContextMenu()` | It already reads labels dynamically from `chart.data.labels[index]` -- zero changes needed |

**Key insight:** The context menu integration (`chart-context-integration.js`) reads bar labels dynamically from the chart data. No changes are needed in the context menu code itself -- it will automatically work with 8 bars once the chart data has 8 labels. The only context-menu-adjacent change is adding switch cases in the PHP subscriber drill-down functions.

## Common Pitfalls

### Pitfall 1: Parameter Count Mismatch in SQL

**What goes wrong:** The `$expiration_stmt->execute()` call passes an array of `$snapshotDate` values that must match the number of `?` placeholders. Adding 4 new WHEN clauses (2 `?` each) adds 8 new parameters.
**Why it happens:** The current code uses positional `?` placeholders (not named `:params`), so counting must be exact.
**How to avoid:** Count placeholders carefully. Current: 9 snapshot dates before paper_codes + 1 after. New: 17 before + 1 after. Build the array programmatically or count explicitly.
**Warning signs:** "Invalid parameter number" or "Number of bound variables does not match" PDO error.

### Pitfall 2: WHERE Clause Date Filter Not Updated

**What goes wrong:** The WHERE clause currently limits to `paid_thru <= DATE_ADD(?, INTERVAL 21 DAY)`. If not updated to 49 days, weeks 3-6 return zero counts.
**Why it happens:** Easy to add CASE branches but forget the outer WHERE filter.
**How to avoid:** Update the WHERE to `INTERVAL 49 DAY` to match the last CASE branch (week +6 ends at day 49).
**Warning signs:** New week bars showing 0 despite subscribers existing in those date ranges.

### Pitfall 3: getMetricCount() "Later" Bucket Shift

**What goes wrong:** The `getMetricCount()` function has a "Later" fallback (`else` clause at line 1816) that captures anything beyond Week +2. After expansion, "Later" should mean beyond Week +6.
**Why it happens:** The else clause uses `modify('+2 weeks')` from next week start, which means 3 weeks out. Needs to be `modify('+6 weeks')` (or equivalent) for the new cutoff.
**How to avoid:** Update the `else` fallback in `getMetricCount()` to start at day 50 (after Week +6 ends at day 49).
**Warning signs:** Historical trend data for "Later" bucket includes subscribers that should now appear in Weeks +3 through +6.

### Pitfall 4: Chart Title Not Updated

**What goes wrong:** The chart layout manager metadata says "4-Week Expiration View" (line 192 of chart-layout-manager.js). Users see "4-Week" label despite 8 bars.
**Why it happens:** Metadata is a separate file from chart rendering.
**How to avoid:** Update the title string to "8-Week Expiration View" and description to match.

### Pitfall 5: Tailwind CSS Rebuild

**What goes wrong:** If new Tailwind utility classes are introduced in JS/PHP, they won't appear without rebuilding CSS.
**Why it happens:** Tailwind purges unused classes.
**How to avoid:** This phase uses inline rgba() colors in Chart.js, not Tailwind classes. No CSS rebuild needed. But verify no new Tailwind classes are accidentally introduced.

## Code Examples

### SQL CASE Extension (complete replacement block)

```sql
-- Source: Current legacy.php lines 1248-1270, extended
SELECT
    CASE
        WHEN paid_thru < ? THEN 'Past Due'
        WHEN paid_thru BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) THEN 'This Week'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 8 DAY) AND DATE_ADD(?, INTERVAL 14 DAY) THEN 'Next Week'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 15 DAY) AND DATE_ADD(?, INTERVAL 21 DAY) THEN 'Week +2'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 22 DAY) AND DATE_ADD(?, INTERVAL 28 DAY) THEN 'Week +3'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 29 DAY) AND DATE_ADD(?, INTERVAL 35 DAY) THEN 'Week +4'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 36 DAY) AND DATE_ADD(?, INTERVAL 42 DAY) THEN 'Week +5'
        WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 43 DAY) AND DATE_ADD(?, INTERVAL 49 DAY) THEN 'Week +6'
    END as week_bucket,
    COUNT(*) as count
FROM subscriber_snapshots
WHERE snapshot_date = ?
    AND paper_code IN ($placeholders)
    AND paid_thru IS NOT NULL
    AND paid_thru <= DATE_ADD(?, INTERVAL 49 DAY)
GROUP BY week_bucket
ORDER BY
    CASE week_bucket
        WHEN 'Past Due' THEN 1
        WHEN 'This Week' THEN 2
        WHEN 'Next Week' THEN 3
        WHEN 'Week +2' THEN 4
        WHEN 'Week +3' THEN 5
        WHEN 'Week +4' THEN 6
        WHEN 'Week +5' THEN 7
        WHEN 'Week +6' THEN 8
    END
```

Parameter count: 17 `?` in CASE (1 for Past Due + 2 each for 7 remaining = 15, total 1+2\*7=15... let me recount).

Exact count:

- Past Due: 1 `?`
- This Week: 2 `?`
- Next Week: 2 `?`
- Week +2: 2 `?`
- Week +3: 2 `?`
- Week +4: 2 `?`
- Week +5: 2 `?`
- Week +6: 2 `?`
- WHERE snapshot_date: 1 `?`
- WHERE paid_thru filter: 1 `?`

Total: 1 + (7 \* 2) + 1 + 1 = 17 `$snapshotDate` values before `$paper_codes`, plus 1 after.

### JS Color Map (complete replacement)

```javascript
// Source: detail_panel.js renderExpirationChart()
const backgroundColors = labels.map(label => {
  if (label === 'Past Due') return 'rgba(239, 68, 68, 0.8)'; // red-500
  if (label === 'This Week') return 'rgba(245, 158, 11, 0.8)'; // amber-500
  if (label === 'Next Week') return 'rgba(251, 191, 36, 0.8)'; // amber-400
  if (label === 'Week +2') return 'rgba(253, 224, 71, 0.8)'; // yellow-300
  if (label === 'Week +3') return 'rgba(250, 240, 137, 0.8)'; // yellow-200
  if (label === 'Week +4') return 'rgba(190, 242, 100, 0.8)'; // lime-300
  if (label === 'Week +5') return 'rgba(134, 239, 128, 0.8)'; // green-300
  if (label === 'Week +6') return 'rgba(74, 222, 128, 0.8)'; // green-400
  return 'rgba(156, 163, 175, 0.8)'; // gray fallback
});
```

### PHP Subscriber Drill-down (new cases to add)

```php
// Source: legacy.php getExpirationSubscribers(), after the Week +2 case
case 'Week +3':
    $weekStart->modify('+21 days');
    $weekEnd->modify('+27 days');
    // Same SELECT as Week +2, different date params
    break;
case 'Week +4':
    $weekStart->modify('+28 days');
    $weekEnd->modify('+34 days');
    break;
case 'Week +5':
    $weekStart->modify('+35 days');
    $weekEnd->modify('+41 days');
    break;
case 'Week +6':
    $weekStart->modify('+42 days');
    $weekEnd->modify('+48 days');
    break;
```

## State of the Art

| Old Approach                             | Current Approach                         | When Changed | Impact                                                                |
| ---------------------------------------- | ---------------------------------------- | ------------ | --------------------------------------------------------------------- |
| 4-bucket view (Past Due through Week +2) | 8-bucket view (Past Due through Week +6) | This phase   | Staff see 6 more weeks of upcoming expirations for proactive outreach |

## Open Questions

1. **Color gradient tuning**
   - What we know: 8 stops from red to green using Tailwind palette values
   - What's unclear: Whether the exact RGB values produce a visually pleasing gradient on screen
   - Recommendation: Implement the proposed colors; adjustments can be made in a follow-up if needed. The colors are all from the Tailwind palette so they should harmonize well.

2. **Legacy getSubscribers() mock data function**
   - What we know: There is a legacy mock data function (around line 1710) that generates fake subscriber data with a switch for expiration buckets. It only handles 4 buckets.
   - What's unclear: Whether this function is still called anywhere
   - Recommendation: Add the 4 new cases to the mock function for completeness, but it is low priority since the app uses real data via `getExpirationSubscribers()`.

## Validation Architecture

### Test Framework

| Property           | Value                                                                 |
| ------------------ | --------------------------------------------------------------------- |
| Framework          | Manual browser testing (no automated test framework in project)       |
| Config file        | none                                                                  |
| Quick run command  | Open http://localhost:8081, click a BU card, inspect expiration chart |
| Full suite command | Manual: verify all 3 BUs show 8 bars, right-click each bar            |

### Phase Requirements to Test Map

| Req ID   | Behavior                                                   | Test Type | Automated Command                   | File Exists? |
| -------- | ---------------------------------------------------------- | --------- | ----------------------------------- | ------------ |
| CHART-01 | 8 bars render in expiration chart                          | manual    | Browser: click BU card, count bars  | n/a          |
| CHART-02 | Right-click each bar opens context menu with correct label | manual    | Browser: right-click each of 8 bars | n/a          |
| CHART-03 | Color gradient red to green across 8 bars                  | manual    | Browser: visual inspection          | n/a          |

### Sampling Rate

- **Per task commit:** Open dev dashboard, click a BU card, verify chart renders 8 bars
- **Per wave merge:** Test all 3 BUs, right-click all 8 bars on each
- **Phase gate:** All 3 requirements visually verified before marking complete

### Wave 0 Gaps

None -- no test infrastructure needed. This is a UI-only visual change verified by inspection.

## Sources

### Primary (HIGH confidence)

- `web/api/legacy.php` lines 1246-1280 -- current SQL CASE for 4-bucket expiration
- `web/api/legacy.php` lines 1416-1567 -- `getExpirationSubscribers()` switch statement
- `web/api/legacy.php` lines 1781-1842 -- `getMetricCount()` expiration bucket handling
- `web/assets/js/components/detail_panel.js` lines 502-567 -- `renderExpirationChart()` with color map
- `web/assets/js/charts/chart-context-integration.js` lines 50-85 -- dynamic label reading (no changes needed)
- `web/assets/js/charts/chart-layout-manager.js` line 192 -- chart title metadata

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH - no new libraries, pure extension of existing code
- Architecture: HIGH - all three touchpoints read directly from source code
- Pitfalls: HIGH - parameter counting and WHERE clause identified from actual SQL

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable -- internal project, no external dependencies changing)
