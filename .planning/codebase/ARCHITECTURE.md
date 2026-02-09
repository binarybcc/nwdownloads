# Architecture

**Analysis Date:** 2026-02-09

## Pattern Overview

**Overall:** Three-tier monolithic with layered separation (Presentation → Application → Data)

**Key Characteristics:**

- Vanilla PHP backend with no framework (procedural with functions)
- Single entry point API router (`api.php` → `api/legacy.php` with 2,583 lines)
- RESTful JSON API driven by action parameter
- Vanilla JavaScript ES6+ frontend with Chart.js visualization
- Legacy monolithic API undergoing gradual modularization
- Plugin-style file processing architecture (Strategy pattern)

## Layers

**Presentation Layer:**

- Purpose: Render HTML pages and handle client-side interactions
- Location: `web/index.php` (58KB), `web/upload_unified.php`, `web/login.php`, `web/settings.php`
- Contains: HTML structure, embedded PHP for server-side rendering, Tailwind CSS styling, Chart.js integration
- Depends on: `config.php` (authentication/session config), `auth_check.php` (auth guard)
- Used by: Browser clients after authentication

**Application Layer (API):**

- Purpose: Route requests and execute business logic for data retrieval, analysis, and processing
- Location: `web/api.php` (router), `web/api/legacy.php` (monolithic implementation)
- Contains: Data fetching functions (25+), comparison logic, trend analysis, forecasting, anomaly detection
- Depends on: Database connection, business logic functions
- Used by: Frontend JavaScript via fetch() calls with action parameters
- Entry point: `api.php?action=<action>&<params>`

**Application Support (Processors & Importers):**

- Purpose: Handle file processing and data import workflows
- Location: `web/processors/` (strategy pattern implementations), `web/lib/` (shared importers)
- Contains: `IFileProcessor` interface, `AllSubscriberProcessor.php`, `VacationProcessor.php`, `RenewalProcessor.php`, `RatesProcessor.php`
- Uses: `AllSubscriberImporter.php`, `VacationImporter.php`, `RenewalImporter.php`, `RatesImporter.php`
- Pattern: Plugin-style auto-discovery via `process-inbox.php` orchestrator

**Data Layer:**

- Purpose: Store and retrieve circulation metrics, subscriber data, and operational records
- Location: MariaDB 10.11 database (native Synology or Docker containerized)
- Contains: `daily_snapshots` (aggregated metrics), `subscriber_snapshots` (detailed subscriber records), supporting tables
- Indexes: On snapshot_date, paper_code, business_unit for fast queries
- Connected via: PDO with Unix socket (production) or TCP (development)

## Data Flow

**Dashboard Load (Browser View):**

1. User navigates to `index.php`
2. `auth_check.php` verifies session via Newzware authentication
3. PHP renders 58KB HTML with embedded initial state
4. JavaScript: `app.js` loads, initializes state object
5. `loadDashboardData()` fetches `api.php?action=overview`
6. API: `legacy.php` calls `getOverviewEnhanced()` which:
   - Gets current week via `getWeekBoundaries()`
   - Queries `daily_snapshots` for all active papers
   - Groups by business_unit and calculates metrics
   - Performs YoY or previous-week comparison
   - Detects anomalies via `detectAnomalies()`
7. Returns JSON with `{success: true, data: {by_business_unit, trend, data_range}}`
8. JavaScript: `renderDashboard()` draws charts via Chart.js, renders metric cards

**Data Import (CSV Upload):**

1. User uploads CSV via `upload_unified.php`
2. Form handler determines file type (subscribers, vacations, renewals)
3. File moves to hotfolder/ directory
4. Browser polls or waits for processing
5. Background: `process-inbox.php` (cron or manual trigger) discovers processors
6. Correct processor validates then processes file:
   - AllSubscriberProcessor: parses CSV, extracts snapshot date, performs soft backfill
   - Creates transactions, inserts to `daily_snapshots` via UPSERT
   - Updates `subscriber_snapshots` with individual subscriber records
7. Processor returns `ProcessResult` with statistics
8. Dashboard updated with new metrics on next refresh

**Drill-Down Detail Panel:**

1. User clicks metric card on dashboard
2. `detail_panel.js` triggers context menu or trend slider
3. JavaScript calls `api.php?action=detail_panel&business_unit=<unit>&snapshot_date=<date>`
4. API: `getDetailPanelData()` aggregates for single business unit:
   - Subscriber count breakdown (delivery type, rate, subscription length)
   - Expiration chart (who's expiring in 7/14/30 days)
   - Rate distribution (most common rate plans)
   - Subscription length breakdown
5. Returns detailed metrics for visualization and drill-down tables
6. JavaScript: `detail_panel.js` renders expandable tables with individual subscriber data

**State Management:**

- Frontend: `CircDashboard.state` object (centralized, non-reactive)
  - `currentDate`: selected date or null for latest
  - `compareMode`: 'yoy', 'previous', or 'none'
  - `charts`: Chart.js instances by type
  - `dashboardData`: last API response
- Backend: Stateless (each request independent)
- Database: UPSERT logic (later snapshot_date wins if same week)

## Key Abstractions

**Week Boundaries:**

- Purpose: Standardize week calculation across upload and API
- Implementation: `getWeekBoundaries()` in `legacy.php` (line 56)
- Uses ISO week numbering (W format in PHP datetime)
- Returns: `{start: Sunday, end: Saturday, week_num, year}`
- Used by: Snapshot comparison, historical trending, YoY logic

**Business Unit Grouping:**

- Purpose: Aggregate metrics across papers by business unit
- Examples: `BUSINESS_UNITS` constant in `app.js`, `getBusinessUnitDetail()` in `legacy.php`
- Pattern: Loop through papers array, sum metrics, recalculate rates
- Used by: Dashboard overview, detailed comparisons, forecasting

**File Processor Interface:**

- Purpose: Plugin-style extensibility for new file types
- Location: `web/processors/IFileProcessor.php`
- Pattern: Strategy pattern with auto-discovery
- Methods: `getName()`, `getDefaultPatterns()`, `getFileType()`, `validate()`, `process()`
- Used by: `process-inbox.php` orchestrator to handle different file types consistently

**Comparison Types:**

- Purpose: Support different comparison modes (year-over-year, previous-week, none)
- Implementation: Logic in `getBusinessUnitComparison()` with week_num matching or date arithmetic
- Calculation: `(current - baseline) / baseline * 100` for percent change
- Trend detection: 4-week moving averages to classify as growing/declining/stable

## Entry Points

**Web Entry Points:**

**`index.php` (Main Dashboard):**

- Location: `web/index.php`
- Triggers: User navigates to https://cdash.upstatetoday.com or http://localhost:8081
- Responsibilities: Render dashboard HTML, load CSS/JS, display metric cards and charts
- Auth required: Yes (via `auth_check.php`)
- Output: 58KB HTML with Chart.js and Flatpickr libraries

**`api.php` (API Router):**

- Location: `web/api.php`
- Triggers: JavaScript calls `fetch('./api.php?action=X&params')`
- Responsibilities: Route to appropriate handler based on action parameter
- Auth required: Yes (via `auth_check.php` in legacy.php)
- Actions: overview, business_unit_detail, paper, data_range, detail_panel, get_subscribers, get_trend, get_longest_vacations, vacation_subscribers, get_churn_overview, etc. (15+ actions)
- Output: JSON `{success: boolean, data: object, error?: string}`

**`upload_unified.php` (Data Upload):**

- Location: `web/upload_unified.php`
- Triggers: User navigates to upload page and selects file
- Responsibilities: Provide tabbed interface for Subscribers/Vacations/Renewals uploads
- Auth required: Yes
- Output: HTML form with file input, then JSON response with import summary

**`settings.php` (Configuration):**

- Location: `web/settings.php`
- Triggers: User accesses settings from menu
- Responsibilities: Manage file processing patterns, notification settings, data retention
- Auth required: Yes

**Background/Batch Entry Points:**

**`process-inbox.php` (File Processing Orchestrator):**

- Location: `web/process-inbox.php`
- Triggers: Cron job or manual invocation
- Responsibilities: Scan hotfolder, auto-discover processors, validate and process files
- Moves files: hotfolder/ → processing/ → archive/ or failed/
- Notifications: Sends email/dashboard alerts on completion
- Logging: Records to `processing_history` table

## Error Handling

**Strategy:** Try-catch blocks at function level, JSON error responses with HTTP status codes

**Patterns:**

**Database Errors:**

```php
// In connectDB() and queries
try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    sendError('Database connection failed: ' . $e->getMessage());
    exit;
}
```

**API Response Errors:**

```php
// In legacy.php
function sendError(string $message): void
{
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}
```

**File Processing Errors:**

```php
// In processors
try {
    $this->validate($filepath);
    $result = $this->process($filepath);
} catch (Exception $e) {
    return ProcessResult::failure($e->getMessage(), []);
}
```

**Frontend Error Handling:**

```javascript
// In app.js
catch (error) {
    console.error('Error loading dashboard:', error);
    showError('Failed to connect to API');
}
```

## Cross-Cutting Concerns

**Logging:**

- Application level: File writes to `upload-debug.log` during import
- Database level: Schema migrations tracked in `schema_migrations` table
- Processing level: `processing_history` table records file processing events
- No centralized logging framework (manual file I/O)

**Validation:**

- CSV import: Header detection, column count verification, data type validation
- API parameters: Presence checks (`?? ''`), enum validation for action/business_unit
- Database: PDO with prepared statements (no SQL injection risk)

**Authentication:**

- Entry point: `auth_check.php` required on all user-facing pages
- Session: PHP native session with Newzware API fallback
- Source: Newzware XML API at `https://seneca.newzware.com/authentication/auth70_xml.jsp`
- Session security: HTTPOnly cookies, SameSite=Strict, optional Secure flag for HTTPS

**Performance Optimization:**

- Query optimization: Indexes on `(snapshot_date, paper_code)`, `(snapshot_date, business_unit)`
- Caching: `SimpleCache.php` for repetitive queries (not actively used)
- Frontend: 21KB optimized Tailwind, CDN dependencies (Chart.js, Flatpickr)
- Database: Week-based precedence (single snapshot per week) reduces data volume

---

_Architecture analysis: 2026-02-09_
