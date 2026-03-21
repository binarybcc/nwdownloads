# Phase 9: CSR Call Reporting - Research

**Researched:** 2026-03-21
**Domain:** PHP API + Chart.js reporting page within existing Circulation Dashboard
**Confidence:** HIGH

## Summary

This phase adds a CSR (Customer Service Representative) call reporting feature consisting of three components: (1) a summary card on the existing settings page, (2) a new API endpoint that queries the existing `call_logs` table, and (3) a dedicated report page with a data table and stacked bar chart. All infrastructure already exists -- the `call_logs` table has the required `source_group`, `call_direction`, and `call_timestamp` columns, Chart.js 4.4.0 is already loaded via CDN, and the settings page has an established card pattern. The CSR name mapping is a simple PHP associative array since there are currently only two CSRs (BC and CW).

This is a read-only reporting feature with no database mutations, no new dependencies, and no schema changes. The implementation follows established patterns in the codebase for API endpoints, page structure, and chart rendering.

**Primary recommendation:** Build the API endpoint first (`api/csr_report.php`), then the report page (`csr_report.php`), then add the settings card -- each layer depends on the previous one.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- New active card added as the 5th card in the grid, after Backup & Restore, before the Coming Soon placeholders
- Card shows summary stats (total outgoing, per-CSR counts) loaded via API on settings page load
- Card links to a dedicated CSR report page (new PHP file)
- Summary table with columns: CSR Name | Outgoing | Received | Missed | Total -- plus a totals row
- Chart.js stacked bar chart below the table showing weekly call volume per CSR, with placed/received/missed as stacked segments
- "Data as of" timestamp from the most recent `imported_at` in call_logs
- PHP associative array in the API endpoint: `['BC' => 'Brittany Carroll', 'CW' => 'Chloe Welch']`
- Unmapped source_group codes display as "Unknown (XX)" -- nothing silently dropped
- New API endpoint: `api/csr_report.php`
- Settings card calls `?summary=true` for totals only (no weekly breakdown)
- Report page calls without param to get both summary and weekly data
- Response shape: `{summary: [{name, group, placed, received, missed, total}], weekly: [{week, group, placed, received, missed}], last_updated: "..."}`
- Page loads skeleton, JS fetches data, renders table + chart
- Chart should be stacked bar (not grouped) showing all three call directions per CSR per week
- Table includes all directions, not just outgoing -- gives complete picture of CSR phone activity
- Summary on the settings card focuses on outgoing counts (the primary metric) with a link to the full report

### Claude's Discretion
- Chart color scheme for placed/received/missed segments
- Skeleton loading design
- Exact page layout and spacing
- Card icon and color theme choice
- Whether to group weekly chart bars by week (CSRs side-by-side) or by CSR

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CSR-01 | Settings page displays outgoing call count per CSR (mapped from scraper phone lines) for the last 60 days | Existing `call_logs` table has `source_group` (BC/CW), `call_direction` (placed/received/missed), `call_timestamp` columns. SQL GROUP BY query with 60-day WHERE clause. CSR name mapping array in API endpoint. Settings card with inline stats pattern from Cache Management card. |
</phase_requirements>

## Standard Stack

### Core (Already in Project)
| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| PHP | 8.2 | API endpoint, page rendering | Already deployed on Synology |
| MariaDB | 10 | `call_logs` table queries | Already has required schema |
| Chart.js | 4.4.0 | Stacked bar chart for weekly trends | Already loaded via CDN on all pages |
| Tailwind CSS | (compiled) | Page layout, table styling, card components | Already in `assets/output.css` |
| PDO | (built-in) | Database access with prepared statements | Standard project pattern |

### No New Dependencies
This phase requires zero new libraries. Everything is built on the existing stack.

## Architecture Patterns

### Recommended File Structure
```
web/
  csr_report.php              # New report page
  settings.php                # Modified: add CSR card (5th position)
  api/
    csr_report.php            # New API endpoint
```

### Pattern 1: API Endpoint (from `api/cache_management.php`)
**What:** Read-only JSON API with auth check, PDO queries, structured response
**When to use:** All API endpoints in this project

```php
<?php
require_once __DIR__ . '/../auth_check.php';
header('Content-Type: application/json');

// DB connection (from legacy.php pattern)
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD'),
    'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];

// CSR name mapping
$csrNames = [
    'BC' => 'Brittany Carroll',
    'CW' => 'Chloe Welch',
];
```

### Pattern 2: Settings Card with API-Loaded Stats (from Cache Management card)
**What:** Card that fetches data on DOMContentLoaded and populates DOM elements
**When to use:** The CSR summary card on settings.php

Key elements from the existing Cache Management card:
- `div` (not `a`) with `settings-card` class when card has inline interactive content
- Use `a` tag wrapping the card when it links to another page (like Rate Management, Backup & Restore)
- Stats displayed in `text-xs text-gray-500` rows with `flex justify-between`
- Status badge in top-right corner
- Fetch call on `DOMContentLoaded`

### Pattern 3: Report Page Structure (from existing pages)
**What:** Standalone page with auth, header with back-arrow, max-w-7xl content, footer
**When to use:** The CSR report page

```php
<?php
require_once 'auth_check.php';
require_once 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head><!-- meta, title, tailwind css, chart.js CDN --></head>
<body class="bg-gray-50">
    <header><!-- back arrow to settings.php, page title --></header>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- skeleton loading states -->
        <!-- table container -->
        <!-- chart container -->
    </main>
    <footer><!-- version string --></footer>
    <script><!-- fetch API, render table, render chart --></script>
</body>
</html>
```

### Pattern 4: Stacked Bar Chart (from existing Chart.js usage)
**What:** Chart.js bar chart with `stacked: true` on both axes
**When to use:** Weekly CSR call volume chart

```javascript
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: weekLabels,  // e.g. ["Mar 3", "Mar 10", ...]
        datasets: [
            { label: 'Placed', data: [...], backgroundColor: '...', stack: 'BC' },
            { label: 'Received', data: [...], backgroundColor: '...', stack: 'BC' },
            { label: 'Missed', data: [...], backgroundColor: '...', stack: 'BC' },
            // repeat for CW with different stack group
        ]
    },
    options: {
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});
```

### Anti-Patterns to Avoid
- **Raw phone numbers in UI:** Always map source_group codes to names. Never display "BC" or "CW" directly.
- **Unbounded date queries:** Always include the 60-day WHERE clause. Never query all call_logs.
- **String concatenation in SQL:** Use PDO prepared statements even for simple queries.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Chart rendering | Custom SVG/canvas drawing | Chart.js (already loaded) | Stacked bar with tooltips, legends, responsive sizing built in |
| CSS framework | Custom styles | Tailwind (already compiled) | Consistent with rest of dashboard |
| Date formatting | Manual string parsing | PHP `DATE_FORMAT()` in SQL, JS `toLocaleDateString()` | Handles edge cases around week boundaries |
| Authentication | Custom session checks | `require_once 'auth_check.php'` | Already handles session, timeout, user type validation |

## Common Pitfalls

### Pitfall 1: Wrong Week Grouping in SQL
**What goes wrong:** Using `WEEK()` with default mode gives inconsistent week boundaries across years
**Why it happens:** MySQL `WEEK()` has 8 different modes with different start-day and year-boundary behavior
**How to avoid:** Use `DATE(DATE_SUB(call_timestamp, INTERVAL WEEKDAY(call_timestamp) DAY))` to get the Monday of each week, or use `YEARWEEK(call_timestamp, 3)` for ISO weeks (consistent with existing dashboard code that uses `format('W')` and `format('o')`)
**Warning signs:** Calls appearing in wrong week, missing weeks in chart

### Pitfall 2: Missing source_group Values
**What goes wrong:** Some call_logs rows might have NULL or empty source_group, causing them to be silently excluded from GROUP BY results
**Why it happens:** Edge cases in scraper, or future CSR lines added without mapping
**How to avoid:** Use `COALESCE(source_group, 'UNKNOWN')` in the query, and handle unmapped codes as "Unknown (XX)" per user decision
**Warning signs:** Total call count in report not matching raw table count

### Pitfall 3: Empty Data State
**What goes wrong:** Page crashes or shows broken chart when no call_logs data exists
**Why it happens:** Fresh install, or all data older than 60 days (purged by Phase 8 retention)
**How to avoid:** Check for empty result sets before rendering chart; show "No call data available" message. Return empty arrays in API response, not null.
**Warning signs:** JavaScript errors in console, blank chart area

### Pitfall 4: Settings Card as Link vs Interactive Element
**What goes wrong:** Using `<a>` tag for the card means the entire card is a link, but if it also has interactive stats that update, click behavior conflicts
**Why it happens:** Mixing navigation and display concerns
**How to avoid:** Use `<a>` tag wrapping the entire card (like Rate Management and Backup & Restore cards). The card stats are display-only and load on page init. Clicking anywhere on the card navigates to the report page.
**Warning signs:** Card not navigating, or stats not displaying

## Code Examples

### SQL: Summary Query (60-day window)
```sql
SELECT
    COALESCE(source_group, 'UNKNOWN') AS source_group,
    SUM(CASE WHEN call_direction = 'placed' THEN 1 ELSE 0 END) AS placed,
    SUM(CASE WHEN call_direction = 'received' THEN 1 ELSE 0 END) AS received,
    SUM(CASE WHEN call_direction = 'missed' THEN 1 ELSE 0 END) AS missed,
    COUNT(*) AS total
FROM call_logs
WHERE call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
GROUP BY COALESCE(source_group, 'UNKNOWN')
ORDER BY source_group
```

### SQL: Weekly Breakdown Query
```sql
SELECT
    YEARWEEK(call_timestamp, 3) AS yw,
    DATE_FORMAT(
        DATE_SUB(call_timestamp, INTERVAL WEEKDAY(call_timestamp) DAY),
        '%Y-%m-%d'
    ) AS week_start,
    COALESCE(source_group, 'UNKNOWN') AS source_group,
    SUM(CASE WHEN call_direction = 'placed' THEN 1 ELSE 0 END) AS placed,
    SUM(CASE WHEN call_direction = 'received' THEN 1 ELSE 0 END) AS received,
    SUM(CASE WHEN call_direction = 'missed' THEN 1 ELSE 0 END) AS missed
FROM call_logs
WHERE call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
GROUP BY yw, week_start, COALESCE(source_group, 'UNKNOWN')
ORDER BY yw, source_group
```

### SQL: Last Updated Timestamp
```sql
SELECT MAX(imported_at) AS last_updated FROM call_logs
```

### PHP: CSR Name Mapping with Unknown Fallback
```php
$csrNames = [
    'BC' => 'Brittany Carroll',
    'CW' => 'Chloe Welch',
];

function mapCsrName(string $group, array $csrNames): string {
    return $csrNames[$group] ?? "Unknown ({$group})";
}
```

### JS: Skeleton Loading Pattern
```html
<!-- Skeleton table rows -->
<div class="animate-pulse space-y-3">
    <div class="h-4 bg-gray-200 rounded w-full"></div>
    <div class="h-4 bg-gray-200 rounded w-full"></div>
    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
</div>
```

## Discretion Recommendations

### Chart Color Scheme
**Recommendation:** Use semantic colors consistent with call direction meaning:
- **Placed (outgoing):** Blue (`rgba(59, 130, 246, 0.7)`) -- proactive action, primary metric
- **Received:** Green (`rgba(34, 197, 94, 0.7)`) -- positive/incoming
- **Missed:** Red/amber (`rgba(239, 68, 68, 0.5)`) -- attention-worthy, lighter opacity

These align with the existing dashboard color language (green = positive, red = concern, blue = neutral/primary).

### Chart Grouping
**Recommendation:** Group by week with CSRs side-by-side within each week. This makes week-over-week trends visible at a glance, which is the primary use case (managers want to see "are we making enough calls this week?"). Each CSR gets a stacked bar (placed/received/missed segments) within each week cluster.

### Card Theme
**Recommendation:** Teal/cyan theme (`bg-teal-100`, `text-teal-600`, `hover:border-teal-200`) -- distinct from existing blue (rates), purple (files), green (cache), orange (backup). Phone/headset icon.

### Skeleton Design
**Recommendation:** Standard Tailwind `animate-pulse` with gray bars matching table row heights. Simple and consistent with modern loading patterns.

## State of the Art

| Aspect | Current Project State | Notes |
|--------|----------------------|-------|
| Chart.js | v4.4.0 via CDN | Current stable; no need to update |
| PHP | 8.2 on Synology | Supports all needed features (enums, named args, etc.) |
| call_logs table | Has all needed columns | `source_group`, `call_direction`, `call_timestamp`, `imported_at` |
| Existing CSR lines | 2 (BC, CW) | Hardcoded mapping is fine for this scale |

## Open Questions

1. **Will more CSR lines be added?**
   - What we know: Currently only BC and CW exist in the scraper config
   - What's unclear: Whether new staff would get new BroadWorks lines
   - Recommendation: The "Unknown (XX)" fallback handles this gracefully. When a new line appears, the manager sees "Unknown (XX)" and knows to request a mapping update. No code change needed for detection.

## Sources

### Primary (HIGH confidence)
- `database/migrations/014_add_call_logs_table.sql` -- confirmed column names and types
- `web/fetch_call_logs.php` -- confirmed source_group values (BC, CW) and scraper config
- `web/settings.php` -- confirmed card grid layout, Cache Management pattern for API-loaded stats
- `web/api/cache_management.php` -- confirmed API endpoint pattern (auth, JSON, CSRF for mutations)
- `web/api/legacy.php` -- confirmed DB connection pattern and PDO usage
- `web/assets/js/features/bu-trend-detail.js` -- confirmed Chart.js bar chart patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all components already exist in the project, no new dependencies
- Architecture: HIGH -- following established patterns from settings.php, cache_management.php, and existing Chart.js usage
- Pitfalls: HIGH -- based on direct inspection of schema, scraper config, and existing code patterns

**Research date:** 2026-03-21
**Valid until:** 2026-04-21 (stable -- no external dependencies changing)
