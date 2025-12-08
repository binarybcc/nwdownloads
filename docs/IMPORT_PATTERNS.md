# CSV Import Patterns & Strategies

> **Purpose:** Guide for implementing new CSV imports into the circulation dashboard
> **Audience:** Developers adding new data sources
> **Last Updated:** December 8, 2025

---

## Overview

The circulation dashboard supports multiple CSV import patterns optimized for different data types. Choosing the right pattern is critical for data integrity, performance, and maintainability.

---

## Pattern 1: Snapshot Data (SoftBackfill)

### When to Use
- **Time-series data** with weekly/daily snapshots
- **Aggregated metrics** that change over time
- **Subscriber counts**, revenue totals, engagement metrics
- Data that represents "state at a point in time"

### Characteristics
- Week-based or day-based snapshots
- UPSERT logic (update existing, insert new)
- Backward-only backfill to fill historical gaps
- Source tracking (which CSV created this data)
- Metadata: `is_backfilled`, `backfill_weeks`, `source_date`, `source_filename`

### Examples
✅ **AllSubscriberReport** - Weekly subscriber counts by paper/business unit
✅ **Revenue Mix Report** - Weekly MRR by product type
✅ **Delivery Distribution** - Weekly carrier/mail/digital breakdown
✅ **Digital Engagement** - Weekly login activity metrics
✅ **Geographic Penetration** - Weekly subscriber counts by ZIP code

### Database Schema Pattern
```sql
CREATE TABLE snapshot_name (
    snapshot_date DATE NOT NULL,
    primary_key VARCHAR(50) NOT NULL,
    -- Data columns
    metric_1 INT,
    metric_2 DECIMAL(10,2),
    -- Source tracking columns
    source_filename VARCHAR(255),
    source_date DATE,
    is_backfilled TINYINT(1) DEFAULT 0,
    backfill_weeks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (snapshot_date, primary_key),
    INDEX idx_source_date (source_date),
    INDEX idx_backfilled (is_backfilled)
);
```

### Implementation Reference
- **File:** `/web/upload.php`
- **Tables:** `daily_snapshots`, `subscriber_snapshots`
- **Algorithm:** SoftBackfill (backward-only fill until hitting existing data)
- **Documentation:** `/docs/SOFT_BACKFILL_SYSTEM.md`

### Key Algorithm Points
1. Extract date from CSV filename (when report was run)
2. Adjust date if needed (e.g., subtract 7 days for weekly data representing previous week)
3. Calculate ISO week number and year
4. Determine backfill range (upload week backward to minimum date)
5. Stop backfilling when hitting ANY existing data
6. Track source metadata for every snapshot

### Data World Starting Point
- **Current:** November 24, 2025 (Week 48) - First real data week
- **No backfill** before this date (prevents synthetic historical data)
- Keeps analytics accurate with real data only

---

## Pattern 2: Event Data (Append-Only)

### When to Use
- **Transaction logs** with exact timestamps
- **One-time events** that never change
- **Historical records** you never update
- Data that represents "something that happened"

### Characteristics
- Append-only (never update existing records)
- No backfill logic needed
- Exact date/time preservation
- Simple INSERT operations
- Optional deduplication by unique transaction ID

### Examples
✅ **Payment Transactions** - Individual payment records with exact timestamps
✅ **Stop Reasons** - When/why subscribers canceled
✅ **Complaint Logs** - Service issues reported by date
✅ **Vacation Holds** - Start/end dates for seasonal stops
✅ **Rate Changes** - Historical pricing adjustments
✅ **Delivery Misses** - Individual missed delivery incidents

### Database Schema Pattern
```sql
CREATE TABLE event_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    event_timestamp DATETIME,
    transaction_id VARCHAR(100) UNIQUE, -- Optional dedup key
    -- Event-specific columns
    subscriber_id VARCHAR(50),
    event_type VARCHAR(50),
    event_details TEXT,
    -- Import tracking
    source_filename VARCHAR(255),
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_event_type (event_type)
);
```

### Implementation Pattern
```php
// Parse CSV
foreach ($csv_rows as $row) {
    // Map CSV columns to database columns
    $event = [
        'event_date' => $row['Date'],
        'subscriber_id' => $row['SubNum'],
        'event_type' => $row['Type'],
        'transaction_id' => $row['TransID'], // For dedup
        'source_filename' => $original_filename
    ];

    // Simple INSERT IGNORE (skip duplicates) or INSERT ON DUPLICATE KEY UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO events (event_date, subscriber_id, event_type, transaction_id, source_filename)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE event_date = event_date  -- No-op, just skip
    ");
    $stmt->execute([$event['event_date'], $event['subscriber_id'],
                    $event['event_type'], $event['transaction_id'],
                    $event['source_filename']]);
}
```

### Key Points
- **Never backfill** - Events happened when they happened
- **Preserve exact dates** - Don't adjust or aggregate
- **Deduplication** - Use transaction IDs to prevent double-import
- **Fast imports** - Simple INSERT, no complex logic

---

## Pattern 3: Dimension Data (Full Replace)

### When to Use
- **Reference tables** that change infrequently
- **Small datasets** (<1,000 rows typically)
- **Master lists** that define valid values
- Data that represents "current truth"

### Characteristics
- Full table replacement on each import
- TRUNCATE + INSERT pattern
- No versioning or history
- Always reflects current state

### Examples
✅ **Rate Master** - Current subscription pricing by type
✅ **Publication List** - Active papers and their codes
✅ **Carrier Routes** - Current route assignments
✅ **Geographic Zones** - Delivery zones and boundaries
✅ **Payment Types** - Valid payment method codes
✅ **Stop Reason Codes** - Valid cancellation reasons

### Database Schema Pattern
```sql
CREATE TABLE dimension_name (
    code VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT,
    active TINYINT(1) DEFAULT 1,
    -- Tracking
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Implementation Pattern
```php
// Start transaction
$pdo->beginTransaction();

try {
    // Clear existing data
    $pdo->exec("TRUNCATE TABLE dimension_name");

    // Prepare insert statement
    $stmt = $pdo->prepare("
        INSERT INTO dimension_name (code, name, description, display_order, active)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Insert all rows from CSV
    foreach ($csv_rows as $row) {
        $stmt->execute([
            $row['Code'],
            $row['Name'],
            $row['Description'],
            $row['DisplayOrder'],
            $row['Active']
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Key Points
- **Atomic replacement** - Use transactions to prevent partial updates
- **No history** - If you need history, use Pattern 2 (Event) instead
- **Small files only** - Not suitable for large datasets
- **Fast and simple** - Entire import in seconds

---

## Decision Tree: Which Pattern Should I Use?

### Question 1: Does this data represent a point-in-time snapshot?
- **YES** → Use Pattern 1 (Snapshot/SoftBackfill)
- **NO** → Go to Question 2

### Question 2: Does this data represent individual events/transactions?
- **YES** → Use Pattern 2 (Event/Append-Only)
- **NO** → Go to Question 3

### Question 3: Is this a small reference table that changes infrequently?
- **YES** → Use Pattern 3 (Dimension/Full Replace)
- **NO** → Consider a hybrid approach or custom pattern

---

## Common CSV Formats & Filename Patterns

### Newzware Standard Exports

**AllSubscriberReport:**
- Filename: `AllSubscriberReport20251208120000.csv`
- Pattern: `AllSubscriberReport[YYYYMMDDHHMMSS].csv`
- Contains: Full subscriber list snapshot
- Use: Pattern 1 (Snapshot)

**PaymentHistory:**
- Filename: `PaymentHistory_2025-12-01_to_2025-12-07.csv`
- Pattern: `PaymentHistory_[START]_to_[END].csv`
- Contains: Individual payment transactions
- Use: Pattern 2 (Event)

**RateMaster:**
- Filename: `RateMaster_Current.csv`
- Pattern: `RateMaster_[DATE].csv`
- Contains: Current valid rates
- Use: Pattern 3 (Dimension)

---

## File Size Considerations

### Tiny CSVs (<500 rows)
- **Pattern 2 or 3** typically
- Can process client-side if needed
- Import time: <1 second

### Medium CSVs (500-5,000 rows)
- **Any pattern** works
- Server-side processing recommended
- Import time: 1-5 seconds

### Large CSVs (5,000-20,000+ rows)
- **Pattern 1 or 2** typically
- Batch processing recommended
- Import time: 10-30 seconds
- Consider chunked uploads for >10MB files

### Very Large CSVs (50,000+ rows)
- **Pattern 2** most likely
- Background processing recommended
- Progress indicators required
- Consider streaming parser (not load entire file)

---

## Error Handling Best Practices

### All Patterns Should:
1. **Validate file format** before processing
2. **Use transactions** to prevent partial imports
3. **Log errors** with context (filename, row number, specific error)
4. **Return detailed results** (rows processed, errors, warnings)
5. **Preserve source files** for audit trail

### Validation Checklist:
- ✅ File extension matches expected type (.csv)
- ✅ Required columns present (case-insensitive check)
- ✅ Date formats parseable
- ✅ Foreign key references valid (if applicable)
- ✅ Data types match schema expectations
- ✅ File size within acceptable limits

---

## Performance Guidelines

### Pattern 1 (Snapshot) - Moderate Speed
- **Bottleneck:** Week-by-week backfill loop with existence checks
- **Optimization:** Batch UPSERTs, index on (snapshot_date, primary_key)
- **Typical Speed:** 8,000 rows in 10-30 seconds

### Pattern 2 (Event) - Fastest
- **Bottleneck:** Usually none (simple INSERT)
- **Optimization:** Batch inserts (500-1,000 rows at a time)
- **Typical Speed:** 10,000 rows in 5-15 seconds

### Pattern 3 (Dimension) - Very Fast
- **Bottleneck:** TRUNCATE locks table briefly
- **Optimization:** Keep tables small, use transactions
- **Typical Speed:** 1,000 rows in <2 seconds

---

## Testing New Imports

### Phase 1: Development Testing
1. Create test table with "_test" suffix
2. Import small sample CSV (10-50 rows)
3. Verify data accuracy manually
4. Test with edge cases (empty fields, special characters, duplicates)

### Phase 2: Staging Testing
1. Import full production-size CSV
2. Measure import time
3. Verify all rows processed
4. Check error handling (malformed CSV, missing columns)

### Phase 3: Production Deployment
1. Back up existing data
2. Run import with production CSV
3. Verify results match expectations
4. Monitor performance and errors
5. Document any issues or quirks

---

## Future Enhancements

### Potential Additions:
- **Import scheduling** - Automatic weekly CSV processing
- **Email notifications** - Alert on import success/failure
- **Data quality reports** - Flag suspicious changes (e.g., 50% subscriber drop)
- **Version history** - Track when dimension tables change
- **Audit logging** - Who imported what, when
- **Batch uploads** - Process multiple CSVs in one operation

---

## Related Documentation

- `/docs/SOFT_BACKFILL_SYSTEM.md` - Detailed SoftBackfill algorithm documentation
- `/docs/KNOWLEDGE-BASE.md` - Complete system architecture and database schemas
- `/docs/strategic_consulting_report.md` - Business intelligence use cases
- `/docs/csv_intelligence_extraction.md` - Analysis patterns for AllSubscriberReport

---

**Last Updated:** December 8, 2025
**Maintainer:** Development Team
**Version:** 1.0.0
