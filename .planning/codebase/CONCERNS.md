# Codebase Concerns

**Analysis Date:** 2026-02-09

## Security Concerns

### Hardcoded Database Credentials

**Issue:** Default database credentials are hardcoded as fallback values in multiple files.

**Files:**

- `web/upload.php:60` - Default password: `Barnaby358@Jones!`
- `web/phase1_setup.php:18` - Default password: `Barnaby358@Jones!`
- `web/api/revenue_intelligence.php:60` - Default password: `Barnaby358@Jones!`
- `web/api/legacy.php:24` - No default, but password can be null

**Impact:**

- If environment variables are not set, system falls back to hardcoded credentials
- Credentials exposed in version control history
- Development credentials same format as production creds (risky if accidentally deployed)

**Fix approach:**

- Remove hardcoded defaults entirely
- Require environment variables to be set (fail fast if missing)
- Use distinct development credentials separate from production
- Implement credential validation at startup

### CORS Headers Too Permissive

**Issue:** API endpoints allow cross-origin requests from any origin.

**Files:**

- `web/api/legacy.php:16` - `Access-Control-Allow-Origin: *`
- `web/api/revenue_intelligence.php:23-24` - Allows all origins, all methods

**Impact:**

- Browser-based CSRF attacks possible (if authentication can be bypassed)
- API data accessible from any website
- No protection for sensitive subscriber/circulation data

**Fix approach:**

- Restrict CORS to trusted domains only: `cdash.upstatetoday.com`, `localhost:8081`
- Consider removing CORS headers if not needed for external consumption
- Add CSRF token validation to all state-changing operations

### Unvalidated Direct Query Execution

**Issue:** Some database queries use `pdo->query()` without parameterization (though input comes from controlled sources).

**Files:**

- `web/process-inbox.php:261` - Direct query (but no user input)
- `web/cleanup_bad_data.php:59` - Direct query (but no user input)
- `web/rates.php:127, 150` - Direct queries (no user input)

**Impact:**

- Low risk because user input is not concatenated
- But violates defensive programming principles
- Future modifications could introduce SQL injection

**Fix approach:**

- Convert all queries to prepared statements with placeholders
- Use parameterized queries even for constant values
- Add static analysis to catch string concatenation in SQL

## Tech Debt

### Monolithic API Files

**Issue:** Large, single-file API implementations with many functions.

**Files:**

- `web/api/legacy.php` - 2,583 lines, 30+ functions in one file
- `web/api/revenue_intelligence.php` - 1,596 lines, multiple query handlers

**Impact:**

- Difficult to test individual functions
- High cognitive load when navigating code
- Tight coupling between concerns
- Difficult to reuse functions across endpoints

**Fix approach:**

- Split into separate files by concern (weeklyComparison.php, detailPanel.php, etc.)
- Create a QueryBuilder or DataService abstraction
- Extract common patterns into utilities
- Implement proper class-based architecture

### Large HTML/PHP Files

**Issue:** UI rendering mixed directly with PHP in large files.

**Files:**

- `web/index.php` - 1,288 lines (HTML + PHP + JS + CSS all in one file)
- `web/header-redesign-test.php` - 1,276 lines (duplicate of index.php for testing)

**Impact:**

- Difficult to modify UI without affecting PHP logic
- HTML is unseparated from business logic
- CSS/JS mixed in same file (no separate assets)
- Testing is difficult
- Code duplication (test file vs. main file)

**Fix approach:**

- Extract template into separate views/dashboard.php
- Move inline CSS to assets/css/dashboard.css
- Move dashboard logic to assets/js/dashboard.js
- Remove header-redesign-test.php once testing complete
- Implement template engine if more complexity

### Duplicate Code Across Import Classes

**Issue:** AllSubscriberImporter, RenewalImporter, VacationImporter, RatesImporter all have similar patterns.

**Files:**

- `web/lib/AllSubscriberImporter.php` - 760 lines
- `web/lib/RenewalImporter.php` - 476 lines
- `web/lib/VacationImporter.php` - 475 lines
- `web/lib/RatesImporter.php` - 449 lines

**Impact:**

- Bug fixes must be made in 4 places
- Inconsistent error handling between importers
- CSV parsing logic duplicated
- Summary HTML building duplicated
- Difficult to add new importer types

**Fix approach:**

- Create abstract BaseImporter class with shared patterns
- Extract CSV parsing to CSVReader utility
- Implement common import workflow
- Each importer specializes only the unique parts

### Missing Error Context Returns

**Issue:** Some methods return null or empty results without indicating the reason.

**Files:**

- `web/lib/AllSubscriberImporter.php:222, 716, 741` - Returns false/null without context
- `web/lib/RenewalImporter.php:166, 404, 422` - Returns null without error message
- `web/lib/VacationImporter.php:356, 366, 380, 387` - Returns null

**Impact:**

- Calling code can't distinguish between "no data" and "error occurred"
- No way to log specific failure reasons
- User interface can't provide meaningful error messages
- Difficult to debug issues

**Fix approach:**

- Return structured error objects: `['success' => false, 'error' => 'reason', 'code' => 'ERROR_CODE']`
- Use exceptions for truly exceptional conditions
- Implement Result<T> type pattern or similar

## Performance Bottlenecks

### N+1 Query Pattern in Detail Views

**Issue:** Detail panels may load related data inefficiently.

**Files:**

- `web/api/legacy.php` - Multiple functions fetch data in loops
- `web/api/revenue_intelligence.php` - Subscriber list generation (line 80+)

**Impact:**

- Each subscriber drill-down may trigger separate queries
- Performance degrades with data volume
- Database connection overhead

**Fix approach:**

- Use JOINs instead of separate queries
- Implement query batching for related data
- Add database indexes on frequently filtered columns (paper_code, snapshot_date)
- Consider caching expensive calculations

### Missing Database Indexes

**Issue:** Query performance not optimized at database level.

**Files:**

- Database schema in `database/init/` - No evidence of strategic indexes
- Common queries: snapshot_date, paper_code, business_unit combinations

**Impact:**

- Table scans on large daily_snapshots table
- Slow detail panel loads
- Slow API responses on historical data queries

**Fix approach:**

- Add composite index on (snapshot_date, paper_code)
- Add index on business_unit for filtering
- Analyze query plans with EXPLAIN
- Profile slowest endpoints and index accordingly

### File-Based Caching Without Expiration Checks

**Issue:** SimpleCache checks TTL but re-reads file on every access.

**Files:**

- `web/SimpleCache.php:42-112` - File I/O on every cache get

**Impact:**

- Disk I/O overhead (especially on NAS in production)
- No in-memory caching layer
- Expensive for frequently accessed data

**Fix approach:**

- Implement in-memory cache layer (APCu or Redis)
- Keep file cache as persistent backup
- Add cache warming for predictable data
- Profile cache hit rates

### No Data Pagination in CSV Imports

**Issue:** Large CSV files loaded entirely into memory.

**Files:**

- `web/lib/AllSubscriberImporter.php` - Loads full CSV with file_get_contents()
- All import classes use memory-intensive array operations

**Impact:**

- Large uploads consume significant memory
- Production NAS may have memory constraints
- Risk of timeout on very large files

**Fix approach:**

- Process CSV in chunks using fgetcsv() in a loop
- Stream processing instead of loading full file
- Add progress tracking for long uploads
- Implement early termination on validation failure

## Fragile Areas

### Soft Backfill Logic Complexity

**Issue:** AllSubscriberImporter has complex soft backfill algorithm with many edge cases.

**Files:**

- `web/lib/AllSubscriberImporter.php:35` - MIN_BACKFILL_DATE constant
- Lines 400-500+ - Backfill algorithm with multiple conditions

**Impact:**

- Difficult to understand or modify safely
- No comprehensive test coverage visible
- Produces inconsistent data if algorithm changes
- User confusion about what "backfill" means

**Safe modification:**

- Add unit tests for each backfill scenario (all scenarios documented in code)
- Create separate BackfillStrategy class
- Add detailed comments explaining date logic
- Implement comprehensive test fixtures

**Test coverage gaps:**

- Edge cases around year boundaries (Dec/Jan week crossing)
- Scenarios where data exists for some papers but not others
- Date boundary conditions

### Date/Week Calculation Inconsistencies

**Issue:** Week numbering uses multiple approaches (ISO week vs. Sunday-Saturday).

**Files:**

- `web/api/legacy.php:56-79` - getWeekBoundaries() uses ISO week calculation
- Comments indicate reconciliation with upload.php logic (line 69-70)
- Multiple timezone and date format variations across files

**Impact:**

- Off-by-one errors in comparisons possible
- User confusion about which week is displayed
- Historical data may use different week definitions
- Year boundary handling can be wrong

**Safe modification:**

- Standardize on single week definition (document decision)
- Add comprehensive date boundary tests
- Create DateRangeHelper utility with centralized logic
- Add logging of week calculations for debugging

### CSV Header Detection Fragile

**Issue:** CSV parsing skips decorative rows and expects specific column order.

**Files:**

- `web/includes/import-helpers.php:337` - Regex to detect separator rows
- All importers depend on column order assumptions
- Header trimming (line auto-trims whitespace)

**Impact:**

- New CSV format from Newzware could break imports
- Whitespace variations could cause column mismatches
- Regex `/^[-=_]+$/` matches unintended rows
- No validation that all required columns present

**Safe modification:**

- Use column name mapping instead of positional access
- Add explicit column validation after header read
- Create TestableCSVParser with fixture support
- Log actual CSV headers for debugging failed imports

## Scaling Limits

### File-Based Cache on NAS Bottleneck

**Issue:** Caching uses file system on Synology NAS, which may be slower than local SSD.

**Files:**

- `web/SimpleCache.php` - File-based caching
- Cache operations on `/var/www/html/cache/` or similar

**Impact:**

- NAS disk I/O is shared resource
- Cache miss hits network latency
- No atomic operations for concurrent access
- Not suitable for high-frequency reads

**Scaling path:**

- Implement Redis cache layer for production
- Keep file cache for persistent/offline access
- Add APCu for in-memory caching
- Measure cache hit rates to justify complexity

### Database Connection Pooling Missing

**Issue:** Each request creates new PDO connection.

**Files:**

- `web/api/legacy.php:33-49` - Creates new PDO per request
- Most PHP files create their own connections

**Impact:**

- Connection overhead on every request
- NAS database connection limit could be exceeded under load
- No reuse of prepared statements across requests
- Memory waste from connection churn

**Scaling path:**

- Implement connection pooling (ProxySQL, PgBouncer, or custom)
- Use persistent PDO connections in PHP-FPM
- Prepare common queries at startup
- Monitor connection pool metrics

### Single-Threaded PHP Processing

**Issue:** Import processing runs synchronously in single PHP process.

**Files:**

- `web/process-inbox.php` - Sequential file processing
- `web/upload_unified.php` - Synchronous upload handling

**Impact:**

- Large imports block other requests
- No parallel processing of multiple files
- User must wait for completion
- One slow import delays all others

**Scaling path:**

- Implement job queue (AWS SQS, RabbitMQ, or database-backed)
- Add background worker process
- Return job ID immediately, let user check status
- Process multiple imports in parallel

## Missing Critical Features

### No Soft Delete or Audit Trail

**Issue:** Data changes don't track who made them or when.

**Files:**

- Database schema - No created_by, updated_by columns in most tables
- No audit table or change log
- No soft delete (records just disappear)

**Impact:**

- Cannot recover accidentally deleted data
- No accountability for data changes
- Cannot trace data quality issues to source
- Compliance issues if audit needed

**Priority:** Medium (depends on regulatory requirements)

**Fix approach:**

- Add audit columns (created_by, updated_by, created_at, updated_at)
- Create audit_log table to track significant changes
- Implement trigger-based audit on key tables
- Add soft delete support where appropriate

### No Rate Limiting on API Endpoints

**Issue:** API endpoints can be called unlimited times without throttling.

**Files:**

- `web/api/legacy.php` - No rate limit checks
- `web/api/revenue_intelligence.php` - No rate limit checks

**Impact:**

- DoS vulnerability (can hammer API)
- Malicious scraping of data possible
- No protection for expensive queries
- Could overwhelm NAS database

**Priority:** High (before production exposure)

**Fix approach:**

- Implement per-user rate limiting
- Use Redis or database for request counting
- Return 429 Too Many Requests when exceeded
- Add configurable limits per endpoint

### No Backup/Restore Management UI

**Issue:** Backups managed via manual commands or scripts only.

**Files:**

- `web/backup.php` - Exists but no visible way to initiate or monitor
- No backup schedule management
- No restore UI

**Impact:**

- Requires SSH access to perform backups
- No visibility into backup status
- Recovery process not user-friendly
- Risk of data loss if backups not running

**Priority:** Medium (depends on RPO/RTO requirements)

**Fix approach:**

- Create admin backup management page
- Show backup history and schedule
- Allow manual backup trigger
- Implement restore wizard

## Known Issues

### CSS/JS Version Caching in HTML

**Issue:** CSS/JS files use version query strings but may not update reliably.

**Files:**

- `web/index.php:18` - `output.css?v=20251206`
- Multiple script tags with version numbers

**Impact:**

- Version string must be manually updated
- Old versions cached in browsers despite version change
- Inconsistent cache invalidation

**Workaround:** Cache-busting requires manual version bump; consider content hash instead.

### Duplicate Test/Development Files

**Issue:** Test and example files committed to main codebase.

**Files:**

- `web/header-redesign-test.php` - Duplicate of index.php
- `web/test-process-inbox.php` - Test file in web root
- `web/upload_dimensions_example.php` - Example template
- `web/upload_events_example.php` - Example template
- `hotfolder/dashboard_v2/` - Old version files

**Impact:**

- Confuses developers about which is canonical
- Test files accidentally serve to users
- Example files take up space and suggest incomplete work
- Increased maintenance burden

**Fix approach:**

- Move test files to `/tests/` directory
- Move examples to `/examples/` directory
- Keep only one version of each page
- Document which files are active vs. archived

## Code Quality Issues

### Inconsistent Error Handling Patterns

**Issue:** Different files use different error handling approaches.

**Files:**

- Some use try/catch with exceptions
- Some use error_log() and continue
- Some use die() or exit()
- Some return error arrays in JSON

**Impact:**

- Difficult to debug (don't know which error format to expect)
- Inconsistent user experience
- Some errors silently logged, others shown to user
- Makes adding global error handler difficult

**Fix approach:**

- Define standard error handling interface
- Create ErrorHandler utility class
- Use exceptions consistently
- Transform exceptions to user-friendly responses

### Missing Input Validation Documentation

**Issue:** Which inputs are validated vs. assumed safe is not documented.

**Files:**

- CSV imports assume correct format but check headers
- API parameters some validated, some not
- Database input uses prepared statements (good), but validation missing

**Impact:**

- New developers don't know what to validate
- Security assumptions implicit in code
- Difficult to refactor input handling

**Fix approach:**

- Create InputValidator utility with clear rules
- Document validation for each input type
- Add type hints to show expected input types
- Create test fixtures for invalid inputs

### Weak Type Hints

**Issue:** Many functions lack type hints, relying on documentation.

**Files:**

- `web/api/legacy.php` - Some functions have `@param` docs but no PHP type hints
- PHPStan/Psalm would catch many issues

**Impact:**

- IDE autocomplete limited
- Type errors only visible at runtime
- Refactoring tools less effective
- Higher cognitive load

**Fix approach:**

- Add strict_types=1 declaration to all files
- Add return type declarations to all functions
- Use union types (PHP 8.0+) for nullable values
- Run PHPStan in CI/CD pipeline

---

_Concerns audit: 2026-02-09_
