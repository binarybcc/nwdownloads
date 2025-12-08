# CSV Import Implementation Guide

> **Quick Reference:** How to add new CSV imports to the circulation dashboard
> **Last Updated:** December 8, 2025

---

## Getting Started

When you need to add a new CSV import, follow these steps:

### Step 1: Identify the Pattern

Ask yourself: **What kind of data is this?**

- **Snapshots** (weekly subscriber counts, revenue totals) → Use Pattern 1
- **Events** (payments, cancellations, complaints) → Use Pattern 2
- **Reference** (rates, publications, zones) → Use Pattern 3

📖 **Full pattern descriptions:** See `/docs/IMPORT_PATTERNS.md`

### Step 2: Use the Template

Copy the appropriate example file:

**Pattern 1 (Snapshot):**
- Reference: `/web/upload.php` (production implementation)
- Documentation: `/docs/SOFT_BACKFILL_SYSTEM.md`

**Pattern 2 (Event):**
- Template: `/web/upload_events_example.php`
- Customize table name, columns, validation

**Pattern 3 (Dimension):**
- Template: `/web/upload_dimensions_example.php`
- Customize table name, columns, required fields

### Step 3: Use Helper Functions

All templates use shared helper functions from:
```php
require_once 'includes/import-helpers.php';
```

**Available helpers:**
- `extractDateFromFilename($filename)` - Parse Newzware filename dates
- `calculateWeekNumber($date)` - Get ISO week number
- `getWeekStartDate($year, $week)` - Get Monday of a week
- `determineBackfillRange(...)` - Calculate backfill weeks (Pattern 1 only)
- `executeUpsert(...)` - UPSERT database operation (Pattern 1 only)
- `validateCsvHeader($header, $required)` - Check required columns
- `findCsvHeader($handle, $key_column)` - Find header row in CSV
- `skipDecoratorRows($handle)` - Skip separator rows
- `formatImportStats($stats)` - Format results for logging

📖 **Full API documentation:** See comments in `/web/includes/import-helpers.php`

---

## Implementation Checklist

### For Every New Import:

#### Phase 1: Planning
- [ ] Identify data pattern (snapshot/event/dimension)
- [ ] Design database table schema
- [ ] Create test CSV file with sample data
- [ ] Document business rules and edge cases

#### Phase 2: Development
- [ ] Copy appropriate template file
- [ ] Rename file (e.g., `upload_payments.php`)
- [ ] Update table names in code
- [ ] Update column mappings for your CSV format
- [ ] Update validation rules (required columns)
- [ ] Add business logic (calculations, transformations)
- [ ] Test with sample CSV

#### Phase 3: Database
- [ ] Create table with proper schema
- [ ] Add indexes for performance
- [ ] Add foreign keys if needed
- [ ] Test with large dataset (performance)

#### Phase 4: Frontend
- [ ] Add upload form to UI
- [ ] Add result display/feedback
- [ ] Add error handling
- [ ] Test user workflow

#### Phase 5: Documentation
- [ ] Document CSV format expected
- [ ] Document table schema
- [ ] Add to `/docs/KNOWLEDGE-BASE.md`
- [ ] Create example CSV file
- [ ] Document any gotchas or edge cases

---

## Quick Examples

### Example 1: Payment History Import (Pattern 2)

**CSV Format:**
```csv
Transaction ID,Date,Subscriber ID,Amount,Payment Type
TXN12345,2025-12-01,10001,-169.99,CREDIT_CARD
TXN12346,2025-12-01,10002,-129.99,CHECK
```

**Table Schema:**
```sql
CREATE TABLE payment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) UNIQUE,
    event_date DATE NOT NULL,
    subscriber_id VARCHAR(50),
    amount DECIMAL(10,2),
    payment_type VARCHAR(50),
    source_filename VARCHAR(255),
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (event_date),
    INDEX idx_subscriber (subscriber_id)
);
```

**Implementation:**
1. Copy `/web/upload_events_example.php` to `/web/upload_payments.php`
2. Update table name to `payment_events`
3. Update column mappings to match CSV
4. Update `findCsvHeader()` to look for 'Transaction ID'
5. Update `validateCsvHeader()` required columns
6. Test with sample CSV

**Upload Endpoint:** POST to `/upload_payments.php` with file field `event_csv`

---

### Example 2: Rate Master Import (Pattern 3)

**CSV Format:**
```csv
Rate Code,Rate Name,Amount,Type,Active
DIGONLY,Digital Only Annual,169.99,DIGITAL,1
MAILDG,Mail + Digital Bundle,169.99,COMBO,1
```

**Table Schema:**
```sql
CREATE TABLE rate_master (
    rate_code VARCHAR(50) PRIMARY KEY,
    rate_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    rate_type VARCHAR(50),
    active TINYINT(1) DEFAULT 1,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Implementation:**
1. Copy `/web/upload_dimensions_example.php` to `/web/upload_rates.php`
2. Update table name to `rate_master`
3. Update column mappings to match CSV
4. Update `findCsvHeader()` to look for 'Rate Code'
5. Update `validateCsvHeader()` required columns
6. Test with sample CSV

**Upload Endpoint:** POST to `/upload_rates.php` with file field `dimension_csv`

---

### Example 3: Revenue Snapshots (Pattern 1)

**CSV Format:** (hypothetical weekly revenue report)
```csv
Week,Year,Business Unit,MRR,ARPU,Subscribers
50,2025,Wyoming,45000.00,15.75,2856
50,2025,Michigan,28000.00,12.50,2240
```

**Table Schema:**
```sql
CREATE TABLE revenue_snapshots (
    snapshot_date DATE NOT NULL,
    business_unit VARCHAR(50) NOT NULL,
    week_num INT,
    year INT,
    mrr DECIMAL(10,2),
    arpu DECIMAL(10,2),
    subscriber_count INT,
    -- Source tracking
    source_filename VARCHAR(255),
    source_date DATE,
    is_backfilled TINYINT(1) DEFAULT 0,
    backfill_weeks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (snapshot_date, business_unit),
    INDEX idx_week (week_num, year),
    INDEX idx_source (source_date)
);
```

**Implementation:**
1. Study `/web/upload.php` (reference implementation)
2. Create new file `/web/upload_revenue.php`
3. Copy core structure from `upload.php`
4. Adapt CSV parsing for revenue report format
5. Use `determineBackfillRange()` for backfill logic
6. Use `executeUpsert()` for database operations
7. Test with sample CSV

**Key Differences from AllSubscriberReport:**
- Different CSV columns (week, MRR, ARPU)
- Different primary key (snapshot_date + business_unit)
- Same backfill algorithm
- Same source tracking metadata

---

## Testing Your Import

### Test Scenarios

#### All Patterns Should Test:
1. **Valid CSV** - Import succeeds, correct row count
2. **Invalid CSV** - Proper error message
3. **Missing columns** - Validation catches it
4. **Empty file** - Handles gracefully
5. **Malformed data** - Skips bad rows, logs errors
6. **Large file** - Performance acceptable
7. **Special characters** - Handles names with quotes, commas, etc.

#### Pattern 1 (Snapshot) Should Also Test:
8. **Empty database** - Backfills correctly
9. **Existing data** - Stops backfill at right point
10. **Out-of-order uploads** - Handles correctly
11. **Re-upload same week** - Updates existing data
12. **Before minimum date** - Doesn't backfill too far

#### Pattern 2 (Event) Should Also Test:
13. **Duplicate transactions** - Skips correctly
14. **Different date formats** - Parses correctly
15. **Multiple uploads** - Appends without duplicating

#### Pattern 3 (Dimension) Should Also Test:
16. **Full replacement** - Old data removed, new data loaded
17. **Transaction rollback** - Partial failure doesn't corrupt table
18. **Concurrent access** - Table lock doesn't block reads too long

### Test Commands

```bash
# Development environment
cd /Users/johncorbin/Desktop/projs/nwdownloads

# Check table structure
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' \
  -D circulation_dashboard -e "DESCRIBE your_table_name;"

# Check row count before/after import
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' \
  -D circulation_dashboard -e "SELECT COUNT(*) FROM your_table_name;"

# View sample rows
docker exec circulation_db mariadb -ucirc_dash -p'Barnaby358@Jones!' \
  -D circulation_dashboard -e "SELECT * FROM your_table_name LIMIT 10;"

# Check for errors in logs
docker logs circulation_web | grep -i error
```

---

## Performance Guidelines

### File Size Limits

**Pattern 1 (Snapshot):**
- Max recommended: 10MB
- ~8,000 rows in 10-30 seconds
- Bottleneck: Week-by-week backfill checks

**Pattern 2 (Event):**
- Max recommended: 20MB
- ~10,000 rows in 5-15 seconds
- Bottleneck: Usually none (simple INSERT)

**Pattern 3 (Dimension):**
- Max recommended: 5MB
- ~1,000 rows in <2 seconds
- Bottleneck: TRUNCATE table lock

### Optimization Tips

**For all patterns:**
- Batch inserts when possible
- Use prepared statements (already in templates)
- Add appropriate indexes
- Set realistic file size limits

**For Pattern 1:**
- Consider caching existence checks
- Batch UPSERT operations by week
- Index on (week_num, year)

**For Pattern 2:**
- Use `INSERT IGNORE` or `ON DUPLICATE KEY` for dedup
- Batch inserts (500-1,000 rows at a time)
- Index on transaction_id for dedup

**For Pattern 3:**
- Keep tables small (<10,000 rows)
- Use transactions for atomicity
- Consider read replicas if high concurrency

---

## Common Pitfalls

### ❌ Don't:
- Mix patterns (e.g., using backfill logic for event data)
- Forget to validate CSV format before processing
- Skip transaction handling for Pattern 3
- Ignore error logging
- Process entire large file in memory
- Forget to document the CSV format

### ✅ Do:
- Choose the right pattern for your data type
- Validate early and fail fast
- Use transactions where appropriate
- Log errors with context (row number, field name)
- Process CSVs in streaming fashion
- Document expected CSV format with examples
- Test with realistic data volumes
- Monitor performance in production

---

## Deployment Checklist

Before deploying to production:

- [ ] Tested with sample CSV in development
- [ ] Tested with production-size CSV
- [ ] Table indexes created
- [ ] Foreign keys validated (if applicable)
- [ ] Error handling tested
- [ ] File size limits configured
- [ ] Documentation updated
- [ ] Sample CSV file saved
- [ ] Monitoring/logging in place
- [ ] Rollback plan documented

---

## Getting Help

**Documentation:**
- `/docs/IMPORT_PATTERNS.md` - Detailed pattern descriptions
- `/docs/SOFT_BACKFILL_SYSTEM.md` - Backfill algorithm (Pattern 1)
- `/docs/KNOWLEDGE-BASE.md` - Complete system reference
- `/web/includes/import-helpers.php` - Helper function API docs

**Examples:**
- `/web/upload.php` - Production Pattern 1 implementation
- `/web/upload_events_example.php` - Pattern 2 template
- `/web/upload_dimensions_example.php` - Pattern 3 template

**Strategic Context:**
- `/docs/strategic_consulting_report.md` - Business intelligence use cases
- `/docs/csv_intelligence_extraction.md` - AllSubscriberReport analysis patterns
- `/docs/circulation_intelligence_guide.md` - Newzware database insights

---

**Questions?** Reference the documentation above or review the example implementations.

**Last Updated:** December 8, 2025
**Version:** 1.0.0
