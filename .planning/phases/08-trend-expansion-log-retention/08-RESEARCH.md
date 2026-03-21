# Phase 8: Trend Expansion & Log Retention - Research

**Researched:** 2026-03-21
**Domain:** PHP/JS trend chart expansion + SQL data retention
**Confidence:** HIGH

## Summary

This phase involves two independent changes: (1) expanding trend views from 12-week to 13-week defaults with a user-adjustable weeks input, and (2) adding a 90-day purge to the existing call log import script. Both changes modify existing, well-understood code with clear patterns to follow.

The trend expansion touches four areas: the `getBusinessUnitTrendData()` PHP function (BU card mini-charts), the `getHistoricalTrend()` PHP function (bar-click trend slider), the `$weeksMap` in `legacy.php`, and frontend JS defaults. The purge is a single `DELETE` statement added after the import loop in `fetch_call_logs.php`.

**Primary recommendation:** Add `'13weeks' => 13` to `$weeksMap`, change defaults from `12weeks` to `13weeks`, update `getBusinessUnitTrendData()` loop from 12 to 13 iterations, and add purge DELETE after line 189 of `fetch_call_logs.php`.

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions
- Trend views on BU detail pages get a user-adjustable weeks input (like existing trend detail modal's 4-52 week selector)
- Default to 13 weeks (a full quarter) when trend view opens
- Reuse the existing adjustable pattern from `bu-trend-detail.js` rather than writing specialized code
- BU card mini-charts on the overview page also change from 12 to 13 weeks for consistency
- Purge runs AFTER import completes within `fetch_call_logs.php` -- new data inserted first, then old records cleaned
- Delete based on `call_timestamp` (when the call happened), not `imported_at`
- Simple `DELETE FROM call_logs WHERE call_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)`
- Log purged row count to existing scraper log using `log_msg()` pattern
- No guard or abort threshold -- simple DELETE WHERE older than 90 days

### Claude's Discretion
- Whether to add `13weeks` to the legacy API `$weeksMap` or just change the default
- Exact placement of weeks input in the trend views (follow existing modal pattern)
- Whether purge should be wrapped in a try/catch or just log on failure

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.

</user_constraints>

<phase_requirements>

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TREND-01 | "Show trend over time" displays 13 weeks of data (expanded from current) | Change defaults in `$weeksMap`, `getBusinessUnitTrendData()` loop count, JS defaults, add `13weeks` option to trend-slider buttons |
| MAINT-01 | Call log import script purges records older than 90 days during each run | Add DELETE + log_msg() after import loop in `fetch_call_logs.php` line ~189 |

</phase_requirements>

## Standard Stack

No new libraries needed. All changes use existing PHP, SQL, and vanilla JS.

### Core (existing, no changes)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.2 | Server-side logic | Production runtime |
| MariaDB | 10 | Database | Production database |
| Chart.js | (existing) | Trend charts | Already loaded for all chart views |
| Vanilla JS | ES5+ | Frontend logic | Project standard (no framework) |

## Architecture Patterns

### Files to Modify

```
web/
  api/legacy.php                          # $weeksMap, getBusinessUnitTrendData(), get_trend default
  assets/js/core/app.js                   # BU card mini-chart label "12-Week Trend" -> "13-Week Trend"
  assets/js/components/trend-slider.js    # Add '13weeks' button, change default from '4weeks' to '13weeks'
  fetch_call_logs.php                     # Add purge DELETE after import loop
```

### Pattern 1: Trend Default Expansion (Backend)

**What:** Change `getBusinessUnitTrendData()` from 12 to 13 iterations and update all `12weeks` defaults to `13weeks`.

**Where the 12-week count lives (all locations):**

| File | Line | Current | Change to |
|------|------|---------|-----------|
| `legacy.php` `getBusinessUnitTrendData()` | 556 | `$weekNum - 11` | `$weekNum - 12` |
| `legacy.php` `getBusinessUnitTrendData()` | 568 | `for ($i = 0; $i < 12; $i++)` | `for ($i = 0; $i < 13; $i++)` |
| `legacy.php` `$weeksMap` | 2225-2230 | No `13weeks` entry | Add `'13weeks' => 13` |
| `legacy.php` `getHistoricalTrend()` | 2217 | `?? '12weeks'` | `?? '13weeks'` |
| `legacy.php` `$numWeeks` fallback | 2231 | `?? 12` | `?? 13` |
| `legacy.php` `get_trend` case | 3046 | `?? '12weeks'` | `?? '13weeks'` |

**Important:** `getBusinessUnitTrendData()` uses `$weekNum - 11` to go back 12 weeks (current + 11 previous). For 13 weeks, this becomes `$weekNum - 12`.

### Pattern 2: Trend Default Expansion (Frontend)

**Where JS defaults and labels live:**

| File | Line | Current | Change to |
|------|------|---------|-----------|
| `app.js` | 959 | `"12-Week Trend"` | `"13-Week Trend"` |
| `app.js` | 1325 | `'12-Week Trend'` (XLSX sheet name) | `'13-Week Trend'` |
| `app.js` | 1504 | `'anomalies in last 12 weeks'` | `'anomalies in last 12 weeks'` (keep -- anomaly detection is separate) |
| `app.js` | 1620 | `'12-Week Trend'` (detail panel heading) | `'13-Week Trend'` |
| `trend-slider.js` | 53 | `timeRange: context.timeRange \|\| '4weeks'` | `timeRange: context.timeRange \|\| '13weeks'` |
| `trend-slider.js` | 149-153 | No `'13weeks'` in labels | Add `'13weeks': '13 Weeks'` |
| `trend-slider.js` | 236 | `['4weeks', '12weeks', '26weeks', '52weeks']` | `['4weeks', '13weeks', '26weeks', '52weeks']` |
| `trend-slider.js` | 619 | No `'13weeks'` in labels | Add `'13weeks': '13 weeks'` |

**Decision (Claude's Discretion):** Add `13weeks` to the `$weeksMap` AND change the default. Removing `12weeks` would break any existing bookmarks or saved URLs that use `time_range=12weeks`, so keep `12weeks` in the map but change the default to `13weeks`. In the trend-slider button array, replace `12weeks` with `13weeks` since users select visually.

### Pattern 3: BU Trend Detail Modal (Already 26-week default)

The `bu-trend-detail.js` modal already has its own weeks input (lines 48-57) defaulting to 26 weeks. It uses `api/get_bu_trend_detail.php` (separate endpoint). This does NOT need modification for TREND-01 since it already supports arbitrary week counts and its default of 26 weeks exceeds 13.

### Pattern 4: Call Log Purge

**What:** Add DELETE statement after import loop completion.

**Placement:** After line 189 (`log_msg("=== Done. Scraped: ...")`), before `exit(0)` on line 190.

**Code pattern:**
```php
// Purge old call logs (90-day retention)
try {
    $purgeStmt = $pdo->exec("DELETE FROM call_logs WHERE call_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    log_msg("Purged {$purgeStmt} call_logs records older than 90 days");
} catch (\PDOException $e) {
    log_msg("WARNING: Call log purge failed: " . $e->getMessage());
}
```

**Decision (Claude's Discretion):** Wrap in try/catch. The purge is non-critical (scraped copies of BroadWorks data), so a purge failure should not prevent the script from reporting success. Logging the failure is sufficient.

### Anti-Patterns to Avoid
- **Don't add a separate purge script/cron:** The decision locks purge into `fetch_call_logs.php` after import.
- **Don't change `imported_at` to `call_timestamp`:** The decision explicitly uses `call_timestamp`.
- **Don't remove `12weeks` from `$weeksMap`:** Would break backward compatibility for any saved URLs.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Week input UI | Custom weeks picker | Copy existing pattern from `bu-trend-detail.js` lines 48-57 | Already tested, consistent UX |
| Date calculation | Custom PHP date math | `DATE_SUB(NOW(), INTERVAL 90 DAY)` | MySQL handles DST, leap years |

## Common Pitfalls

### Pitfall 1: Year Boundary in getBusinessUnitTrendData()
**What goes wrong:** Going from 13 weeks to `$weekNum - 12` could produce negative week numbers if current week is early January.
**Why it happens:** The existing code already handles this (lines 560-564), but changing from `-11` to `-12` shifts the boundary.
**How to avoid:** The existing year-boundary logic at lines 560-564 handles this correctly: `if ($startWeekNum < 1)` adjusts to previous year. Just change the subtraction value.
**Warning signs:** Test with week 1 of any year (e.g., week_num=1, year=2026).

### Pitfall 2: Missing 13weeks in Trend Slider Button Array
**What goes wrong:** If `13weeks` is added to `$weeksMap` but not to the trend-slider's button array, the default won't match any button (no button appears selected).
**How to avoid:** Update ALL locations listed in Pattern 2 table.

### Pitfall 3: PDO::exec() vs PDO::prepare() for Purge
**What goes wrong:** Using `$pdo->prepare()` then `$stmt->execute()` for a parameterless DELETE works but is unnecessary overhead.
**How to avoid:** Use `$pdo->exec()` which returns affected row count directly. No parameters needed since `INTERVAL 90 DAY` is a literal.

### Pitfall 4: Anomaly Detection Text
**What goes wrong:** Changing "12 weeks" text in anomaly detection strings (app.js line 1504) when that feature may use a different time window.
**How to avoid:** Only change trend-specific labels. The anomaly detection text refers to its own analysis window, not the mini-chart range. Verify before changing.

## Code Examples

### Verified: Existing weeks input pattern (bu-trend-detail.js lines 47-57)
```javascript
const weeksInput = document.createElement('input');
weeksInput.id = 'trend-detail-weeks';
weeksInput.type = 'number';
weeksInput.min = '4';
weeksInput.max = '52';
weeksInput.value = '26';
weeksInput.className =
  'mx-2 w-16 border border-gray-300 rounded px-2 py-1 text-center font-semibold focus:ring-2 focus:ring-blue-400 focus:outline-none';
```

### Verified: Existing log_msg() pattern (fetch_call_logs.php line 213)
```php
function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}
```

### Verified: $weeksMap structure (legacy.php lines 2225-2230)
```php
$weeksMap = [
    '4weeks' => 4,
    '12weeks' => 12,
    '26weeks' => 26,
    '52weeks' => 52
];
```

## State of the Art

No technology changes relevant. This phase modifies constants and adds a SQL DELETE -- purely business logic adjustments.

## Open Questions

1. **Anomaly detection time window**
   - What we know: `app.js` line 1504 says "anomalies in last 12 weeks"
   - What's unclear: Whether this is tied to the mini-chart data length or has its own analysis window
   - Recommendation: Leave anomaly text unchanged unless investigation shows it's derived from the mini-chart data length

2. **churn_dashboard.php 12weeks option**
   - What we know: `churn_dashboard.php` line 206 has `<option value="12weeks">Last 12 Weeks</option>`
   - What's unclear: Whether this dropdown should also add a 13-week option
   - Recommendation: Keep existing options in churn dashboard (separate feature), only change defaults for BU overview and bar-click trends per TREND-01 scope

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x |
| Config file | `phpunit.xml` |
| Quick run command | `./vendor/bin/phpunit --testsuite Unit -x` |
| Full suite command | `./vendor/bin/phpunit` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TREND-01 | `getBusinessUnitTrendData()` returns 13 data points | unit | `./vendor/bin/phpunit tests/Unit/TrendExpansionTest.php -x` | No - Wave 0 |
| TREND-01 | `$weeksMap` includes `13weeks` key | unit | `./vendor/bin/phpunit tests/Unit/TrendExpansionTest.php -x` | No - Wave 0 |
| TREND-01 | Frontend defaults show 13 weeks | manual-only | Visual check: open BU card, verify 13 data points in mini-chart | N/A |
| MAINT-01 | Purge deletes records older than 90 days | unit | `./vendor/bin/phpunit tests/Unit/CallLogPurgeTest.php -x` | No - Wave 0 |
| MAINT-01 | Purge logs row count via log_msg() | unit | `./vendor/bin/phpunit tests/Unit/CallLogPurgeTest.php -x` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit --testsuite Unit -x`
- **Per wave merge:** `./vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/TrendExpansionTest.php` -- covers TREND-01 (verify 13-week iteration, $weeksMap entry)
- [ ] `tests/Unit/CallLogPurgeTest.php` -- covers MAINT-01 (verify purge SQL, logging)

Note: PHP backend functions are tightly coupled to PDO database calls, making true unit tests require mocking. Integration tests against a test database may be more practical for the trend functions. The purge logic is simple enough that a code review may suffice over a mock-heavy unit test.

## Sources

### Primary (HIGH confidence)
- Direct code inspection of `web/api/legacy.php` lines 553-647, 2211-2278, 2225-2231, 3040-3052
- Direct code inspection of `web/assets/js/features/bu-trend-detail.js` lines 40-69, 360-380
- Direct code inspection of `web/assets/js/components/trend-slider.js` lines 40-60, 140-155, 230-250
- Direct code inspection of `web/assets/js/core/app.js` lines 771-870, 907-1080
- Direct code inspection of `web/fetch_call_logs.php` lines 140-238
- Direct code inspection of `database/migrations/014_add_call_logs_table.sql`

### Secondary (MEDIUM confidence)
- None needed -- all findings from direct code inspection

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new libraries, all existing code
- Architecture: HIGH - all modification points identified and verified by line number
- Pitfalls: HIGH - identified from actual code structure, year-boundary logic verified

**Research date:** 2026-03-21
**Valid until:** Indefinite (internal codebase, no external dependency changes)
