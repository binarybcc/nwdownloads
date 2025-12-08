# Shared Import Helper Functions

This directory contains reusable functions extracted from the CSV import implementations.

## Files

### `import-helpers.php`

Core functions for CSV processing, date handling, and database operations.

**Functions:**

| Function | Purpose | Pattern Usage |
|----------|---------|---------------|
| `extractDateFromFilename()` | Parse date from Newzware CSV filename | All patterns |
| `calculateWeekNumber()` | Get ISO week number and year from date | Pattern 1 |
| `getWeekStartDate()` | Get Monday of a specific ISO week | Pattern 1 |
| `determineBackfillRange()` | Calculate which weeks to backfill | Pattern 1 only |
| `executeUpsert()` | Perform UPSERT database operation | Pattern 1 only |
| `validateCsvHeader()` | Check CSV has required columns | All patterns |
| `findCsvHeader()` | Find header row in CSV by key column | All patterns |
| `skipDecoratorRows()` | Skip separator rows after header | All patterns |
| `formatImportStats()` | Format statistics for logging | All patterns |

**Usage Example:**

```php
<?php
require_once 'includes/import-helpers.php';

// Extract date from filename
$date = extractDateFromFilename('AllSubscriberReport20251208120000.csv');
// Returns: '2025-12-08'

// Calculate week number
$week_data = calculateWeekNumber('2025-12-08');
// Returns: ['week' => 50, 'year' => 2025]

// Find CSV header
$handle = fopen('report.csv', 'r');
$header = findCsvHeader($handle, 'SUB NUM');
// Returns: ['SUB NUM', 'Name', 'Address', ...]

// Validate required columns
$missing = validateCsvHeader($header, ['SUB NUM', 'Ed', 'DEL']);
// Returns: [] if all present, or ['Ed', 'DEL'] if missing
```

## When to Use These Functions

**Pattern 1 (Snapshot with Backfill):**
- Use ALL functions as needed
- `determineBackfillRange()` and `executeUpsert()` are Pattern 1 specific

**Pattern 2 (Event/Append-Only):**
- Use: `extractDateFromFilename()`, `validateCsvHeader()`, `findCsvHeader()`, `skipDecoratorRows()`
- Skip: `determineBackfillRange()`, `executeUpsert()`, week calculation functions

**Pattern 3 (Dimension/Full Replace):**
- Use: `validateCsvHeader()`, `findCsvHeader()`, `skipDecoratorRows()`
- Skip: All date/week functions, backfill functions

## Adding New Helpers

When you find yourself copying the same code across multiple imports:

1. Extract the function to this file
2. Add PHPDoc comments
3. Add to the table above
4. Add usage example
5. Update `/docs/IMPORT_IMPLEMENTATION_GUIDE.md`

**Guidelines:**
- Keep functions small and focused
- No side effects (pure functions when possible)
- Document parameters and return values
- Include usage examples in comments
- Handle edge cases gracefully

---

**Last Updated:** December 8, 2025
