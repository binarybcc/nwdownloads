# Phase 3: Data Foundation - Research

**Researched:** 2026-03-20
**Domain:** PHP date parsing, MariaDB schema migrations, phone number normalization
**Confidence:** HIGH

---

<phase_requirements>

## Phase Requirements

| ID        | Description                                                                                  | Research Support                                                                                                                                                       |
| --------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| IMPORT-01 | New starts CSV imports successfully with both M/D/YY and YYYY-MM-DD date formats             | parseDate() fix in NewStartsImporter — add YYYY-MM-DD branch                                                                                                           |
| IMPORT-02 | Two failed CSVs (Mar 9, Mar 16) are reprocessed from NAS failed/ directory                   | Files confirmed in `/volume1/homes/newzware/failed/`; fix importer, move back to inbox                                                                                 |
| CALL-02   | Phone numbers are normalized to bare 10-digit at ingest (both subscriber and call log sides) | SQL migration: add `phone_normalized` to `subscriber_snapshots`; update AllSubscriberImporter; SQL migration: `call_logs` table with `phone_normalized` indexed column |

</phase_requirements>

---

## Summary

Phase 3 has two independent workstreams: fixing a CSV import bug in `NewStartsImporter` and creating the database foundations needed for call log matching.

**Workstream A (IMPORT-01, IMPORT-02):** The `NewStartsImporter::parseDate()` method only handles `M/D/YY` format. The auto-export from Newzware (run by launchd at 08:02 AM on Mondays) produces `YYYY-MM-DD` format, while manual exports produce `M/D/YY`. The two failed CSVs on the NAS (`NewSubscriptionStarts20260309021018.csv` and `NewSubscriptionStarts20260316021038.csv`) confirm this — their `STARTED` column contains dates like `2025-12-19`, not `2/24/26`. The fix is a one-line addition to `parseDate()`: detect and pass through `YYYY-MM-DD` before the existing regex. After fixing, move the two failed files from `/volume1/homes/newzware/failed/` back to `/volume1/homes/newzware/inbox/` and re-trigger `auto_process.php`.

**Workstream B (CALL-02):** The `call_logs` table does not exist yet. The `subscriber_snapshots` table does not have a `phone_normalized` column yet. Both need SQL migrations (following the project's numbered `.sql` file pattern — next would be `014_...`). Phone data in `subscriber_snapshots` is already mostly clean 10-digit bare numbers (7,107 of 7,119 unique phones are exactly 10 digits). The `STARTED` column header in the CSV has a leading space (`" STARTED"`) which is trimmed by `findHeader()` in the importer, so this is not a bug.

**Primary recommendation:** Fix `parseDate()` to handle both date formats (5-line change), then write two SQL migration files for `call_logs` and `phone_normalized`.

---

## Standard Stack

### Core

| Library    | Version  | Purpose           | Why Standard                                           |
| ---------- | -------- | ----------------- | ------------------------------------------------------ |
| PHP 8.2    | 8.2      | Server-side logic | Production NAS Web Station + Docker container          |
| MariaDB 10 | 10.x     | Database          | Production via Unix socket `/run/mysqld/mysqld10.sock` |
| PDO        | built-in | Database access   | Established pattern in all importers                   |

### Migration Pattern

| Approach           | Version           | Purpose        | Why Standard                                                      |
| ------------------ | ----------------- | -------------- | ----------------------------------------------------------------- |
| Numbered SQL files | e.g., `014_*.sql` | Schema changes | Established project convention — no Phinx in use (phinxlog empty) |

**No new packages needed for this phase.** All changes are pure PHP + SQL.

---

## Architecture Patterns

### Recommended Project Structure

```
web/lib/
└── NewStartsImporter.php     # fix parseDate() here

database/migrations/
├── 014_add_call_logs_table.sql           # new
└── 015_add_phone_normalized_to_snapshots.sql  # new
```

### Pattern 1: parseDate() Fix in NewStartsImporter

**What:** Add YYYY-MM-DD detection before the existing M/D/YY regex
**When to use:** Whenever Newzware auto-export (launchd 08:02 AM) produces `YYYY-MM-DD` vs. manual exports producing `M/D/YY`
**Example:**

```php
private function parseDate(string $dateStr): ?string
{
    if (empty($dateStr)) {
        return null;
    }

    // Handle YYYY-MM-DD (auto-export format from launchd/Newzware)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        // Already in correct DB format — validate it
        [$y, $m, $d] = explode('-', $dateStr);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return $dateStr;
        }
        return null;
    }

    // Handle M/D/YY (manual export format)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $dateStr, $matches)) {
        $month = (int)$matches[1];
        $day   = (int)$matches[2];
        $year  = (int)$matches[3];
        if ($year < 100) {
            $year += ($year < 50) ? 2000 : 1900;
        }
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    return null;
}
```

### Pattern 2: Reprocessing Failed Files

**What:** Move files from `failed/` back to `inbox/` and re-run `auto_process.php`
**When to use:** After fixing the importer that caused the failure
**Example:**

```bash
# On NAS via SSH
mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260309021018.csv \
   /volume1/homes/newzware/inbox/
mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260316021038.csv \
   /volume1/homes/newzware/inbox/

/var/packages/PHP8.2/target/usr/local/bin/php82 \
  /volume1/web/circulation/auto_process.php
```

### Pattern 3: SQL Migration for call_logs

**What:** Numbered SQL file following existing convention (next is 014)
**When to use:** New table creation

```sql
-- 014_add_call_logs_table.sql
CREATE TABLE IF NOT EXISTS call_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_direction  ENUM('placed','received','missed') NOT NULL,
    call_timestamp  DATETIME NOT NULL,
    duration_sec    INT UNSIGNED DEFAULT 0,
    remote_number   VARCHAR(50) NOT NULL COMMENT 'Raw number from BroadWorks',
    phone_normalized CHAR(10) DEFAULT NULL COMMENT 'Bare 10-digit, no punctuation',
    local_extension VARCHAR(20) DEFAULT NULL COMMENT 'BC or CW extension',
    source_group    VARCHAR(20) DEFAULT NULL COMMENT 'BC or CW (BroadWorks group)',
    raw_payload     TEXT DEFAULT NULL COMMENT 'Optional: raw row for debugging',
    imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_call_timestamp    (call_timestamp),
    INDEX idx_phone_normalized  (phone_normalized),
    INDEX idx_direction         (call_direction),
    INDEX idx_source_group      (source_group),
    UNIQUE KEY uq_call (call_timestamp, remote_number, local_extension, call_direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='BroadWorks call log entries, normalized for subscriber matching';
```

### Pattern 4: SQL Migration for phone_normalized on subscriber_snapshots

**What:** ALTER TABLE to add indexed column + UPDATE to backfill it

```sql
-- 015_add_phone_normalized_to_subscriber_snapshots.sql

-- Step 1: Add column
ALTER TABLE subscriber_snapshots
    ADD COLUMN phone_normalized CHAR(10) DEFAULT NULL
        COMMENT 'Bare 10-digit phone, no punctuation, for call log matching'
    AFTER phone,
    ADD INDEX idx_phone_normalized (phone_normalized);

-- Step 2: Backfill existing rows
-- Production data is already mostly 10-digit bare (7107 of 7119 unique phones).
-- Strip any remaining non-digit chars and take rightmost 10 digits.
UPDATE subscriber_snapshots
SET phone_normalized = RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10)
WHERE phone IS NOT NULL
  AND phone != ''
  AND REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^[0-9]{10,}$';
```

**Note on REGEXP_REPLACE availability:** MariaDB 10.0+ supports REGEXP_REPLACE. The NAS runs MariaDB 10 (confirmed via socket path `/run/mysqld/mysqld10.sock`). This is HIGH confidence safe to use.

### Pattern 5: Populating phone_normalized in AllSubscriberImporter

**What:** Normalize phone at ingest time so new imports are self-populating
**Where:** `web/lib/AllSubscriberImporter.php` — in the `$subscriber_records[]` array construction

```php
// After: $phone = isset($col_map['Phone']) ? trim($row[$col_map['Phone']] ?? '') : '';
$phone_normalized = $this->normalizePhone($phone);

// In subscriber_records array:
'phone' => $phone,
'phone_normalized' => $phone_normalized,
```

```php
/**
 * Normalize phone to bare 10-digit string
 */
private function normalizePhone(string $phone): ?string
{
    $digits = preg_replace('/\D/', '', $phone);
    // Strip leading country code '1' from 11-digit numbers
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    return (strlen($digits) === 10) ? $digits : null;
}
```

The INSERT in `AllSubscriberImporter` must also include `phone_normalized` in the column list and bind `:phone_normalized`.

### Anti-Patterns to Avoid

- **Running REGEXP_REPLACE in PHP instead of SQL for the backfill:** PHP-side normalization is fine for new ingest, but for the one-time backfill of ~108k existing rows, a single UPDATE SQL statement is far more efficient.
- **Moving failed files while auto_process.php could be running:** Check that no lock file exists at `/tmp/circulation_auto_process.lock` before moving files back to inbox.
- **Using `YYYY-MM-DD` date string detection by catching Exception from `DateTime`:** The regex approach is simpler and avoids ambiguity.

---

## Don't Hand-Roll

| Problem                     | Don't Build                | Use Instead                           | Why                                                            |
| --------------------------- | -------------------------- | ------------------------------------- | -------------------------------------------------------------- |
| Phone digit stripping       | Custom string manipulation | `preg_replace('/\D/', '', $phone)`    | Single-line, handles all punctuation variants                  |
| SQL migration tracking      | Custom migration table     | Existing numbered SQL file convention | Project already uses this; Phinx not configured for production |
| Date validation after parse | Manual range checks        | `checkdate($m, $d, $y)`               | PHP built-in, handles leap years and month lengths             |

---

## Common Pitfalls

### Pitfall 1: STARTED Column Header Has Leading Space

**What goes wrong:** CSV header row contains `" STARTED"` (note leading space). `array_flip($header)` after `array_map('trim', $row)` strips this — `findHeader()` calls `array_map('trim', $row)` before returning. The `colMap` will have `'STARTED'` not `' STARTED'`. This is already handled correctly in the current code.
**How to avoid:** Confirm by checking `$colMap['STARTED']` resolves — it does because `findHeader()` trims all cells.
**Warning signs:** `parseRow()` returning null for all rows despite valid data.

### Pitfall 2: Failed Files Were Processed by a NEWER CSV (Cumulative Report)

**What goes wrong:** Both failed CSVs (Mar 9, Mar 16) appear to be cumulative — they include all starts from 12/15/2025 to report date. The Mar 16 file contains the same records as Mar 9 plus new ones. After reprocessing both, the UPSERT constraint `uq_sub_paper_date (sub_num, paper_code, event_date)` handles deduplication automatically. Processing both is safe.
**How to avoid:** Process in chronological order (Mar 9 first, then Mar 16). Result will be identical either way due to UPSERT.

### Pitfall 3: Backfilling phone_normalized — the 0006476048 Junk Value

**What goes wrong:** The value `0006476048` appears 108,322 times (every row for some subscriber). This is a Newzware default/placeholder, not a real number. The backfill UPDATE should set `phone_normalized = NULL` for this, not `0006476048`.
**Why it happens:** Some subscribers have no phone and Newzware exports this placeholder.
**How to avoid:** The normalization logic `RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10)` will set `phone_normalized = '0006476048'` for this value, making it look like a valid 10-digit number. Add an exclusion: `AND phone NOT LIKE '000%'` OR let the call log matching logic handle it (call logs won't contain `0006476048`). Best approach: do NOT exclude it in the migration — the call matching query in Phase 6 will simply never find a matching call log for that number, which is correct behavior.
**Warning signs:** Unexpected matches in Phase 6 call log join.

### Pitfall 4: AlSubscriberImporter INSERT Missing phone_normalized Column

**What goes wrong:** After adding `phone_normalized` to `subscriber_snapshots`, the existing INSERT in `AllSubscriberImporter` will NOT fail (MariaDB accepts the omission, column defaults to NULL). But then new uploads won't populate `phone_normalized`.
**How to avoid:** Must update both the column list AND the VALUES bind in the prepared statement AND the `$sub_stmt->execute($sub)` binding array.

### Pitfall 5: Docker Dev DB Missing the New Columns

**What goes wrong:** After running migrations in production, the Docker dev database won't have `phone_normalized` or `call_logs`. Tests/development will fail.
**How to avoid:** Run the SQL migration files against the Docker container too:

```bash
docker exec -i circulation_db mariadb -uroot -pMojave48ice circulation_dashboard \
  < database/migrations/014_add_call_logs_table.sql
docker exec -i circulation_db mariadb -uroot -pMojave48ice circulation_dashboard \
  < database/migrations/015_add_phone_normalized_to_subscriber_snapshots.sql
```

---

## Code Examples

### Applying SQL Migration to Production (NAS)

```bash
# Source credentials first (per project protocol)
source .env.credentials

ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p'P@ta675N0id' \
  -S /run/mysqld/mysqld10.sock circulation_dashboard" \
  < database/migrations/014_add_call_logs_table.sql
```

### Verifying Migration Applied

```bash
ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p'P@ta675N0id' \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e 'SHOW COLUMNS FROM subscriber_snapshots LIKE \"phone_normalized\";'"
```

### Checking Backfill Results

```bash
ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p'P@ta675N0id' \
  -S /run/mysqld/mysqld10.sock circulation_dashboard \
  -e 'SELECT COUNT(*) as total, COUNT(phone_normalized) as normalized FROM subscriber_snapshots WHERE phone IS NOT NULL AND phone != \"\";'"
```

### Reprocessing Failed Files

```bash
ssh nas "mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260309021018.csv \
           /volume1/homes/newzware/inbox/ && \
         mv /volume1/homes/newzware/failed/NewSubscriptionStarts20260316021038.csv \
           /volume1/homes/newzware/inbox/ && \
         /var/packages/PHP8.2/target/usr/local/bin/php82 \
           /volume1/web/circulation/auto_process.php"
```

---

## State of the Art

| Old Approach               | Current Approach                      | When Changed | Impact                                       |
| -------------------------- | ------------------------------------- | ------------ | -------------------------------------------- |
| M/D/YY only in parseDate() | Both YYYY-MM-DD and M/D/YY            | Phase 3 fix  | Auto-exports work, manual exports still work |
| phone stored as raw        | phone + phone_normalized              | Phase 3      | Enables call log matching in Phase 6         |
| call_logs absent           | call_logs with phone_normalized index | Phase 3      | Prerequisite for Phase 4 scraper             |

**Deprecated/outdated:**

- The handoff.md refers to "Phase 1" through "Phase 6" as implementation phases within the new-starts feature. This was pre-roadmap terminology. The GSD roadmap now uses Phase 3 for the work described in plans 03-01 and 03-02.

---

## Open Questions

1. **Does `new_start_events` table exist in production already?**
   - What we know: Migration `011_create_new_starts_tables.sql` was created 2026-03-02
   - What's unclear: Whether it was applied to production (only SSH confirmed `call_logs` absent; `new_start_events` was confirmed present in `SHOW TABLES`)
   - Recommendation: Confirmed present — no action needed for `new_start_events`

2. **Should the `call_logs` UNIQUE KEY include `duration_sec`?**
   - What we know: BroadWorks shows 20-entry rolling window; duplicates possible if scraper runs while window shifts
   - What's unclear: Whether two calls to same number at same time with different durations are possible
   - Recommendation: Exclude `duration_sec` from unique key; use timestamp + remote_number + local_extension + direction as natural dedup

---

## Validation Architecture

### Test Framework

| Property           | Value                                                             |
| ------------------ | ----------------------------------------------------------------- |
| Framework          | None detected (no pytest.ini, jest.config.\*, or test/ directory) |
| Config file        | None                                                              |
| Quick run command  | Manual — see below                                                |
| Full suite command | Manual — see below                                                |

### Phase Requirements → Test Map

| Req ID    | Behavior                                               | Test Type | Automated Command                                                                             | File Exists?   |
| --------- | ------------------------------------------------------ | --------- | --------------------------------------------------------------------------------------------- | -------------- |
| IMPORT-01 | M/D/YY date parses correctly                           | manual    | Upload `NewSubscriptionStarts20260302160619.csv` via http://localhost:8081/upload_unified.php | ❌ manual only |
| IMPORT-01 | YYYY-MM-DD date parses correctly                       | manual    | Upload a failed CSV via dev UI and verify no parse errors                                     | ❌ manual only |
| IMPORT-02 | Mar 9 + Mar 16 CSVs reprocess successfully             | manual    | Check `auto_process.log` on NAS after reprocessing                                            | ❌ manual only |
| CALL-02   | `call_logs` table exists with `phone_normalized` index | SQL query | `SHOW CREATE TABLE call_logs`                                                                 | ❌ Wave 0      |
| CALL-02   | `subscriber_snapshots.phone_normalized` populated      | SQL query | `SELECT COUNT(*) FROM subscriber_snapshots WHERE phone_normalized IS NOT NULL`                | ❌ Wave 0      |

### Sampling Rate

- **Per task commit:** Manual smoke test in Docker dev (upload a CSV, query the table)
- **Per wave merge:** Full SQL verification queries on production
- **Phase gate:** All 4 success criteria TRUE before `/gsd:verify-work`

### Wave 0 Gaps

- No automated test framework exists — all verification is manual SQL queries and UI upload tests
- None — no test infrastructure to create, verification is SQL-query based

---

## Sources

### Primary (HIGH confidence)

- Direct inspection of `web/lib/NewStartsImporter.php` — `parseDate()` method confirmed, only handles M/D/YY
- Direct inspection of failed CSV files on NAS — confirmed `YYYY-MM-DD` format in STARTED column
- `ssh nas SHOW TABLES` — confirmed `call_logs` absent, `new_start_events` present
- `SHOW COLUMNS FROM subscriber_snapshots LIKE 'phone%'` — confirmed no `phone_normalized` column
- Phone length analysis — `SELECT LENGTH(phone), COUNT(*)` — 99.8% already 10-digit bare numbers

### Secondary (MEDIUM confidence)

- MariaDB 10 REGEXP_REPLACE support — confirmed by MariaDB 10.0 release notes (available since 10.0.5)

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — all from direct code/DB inspection
- Architecture: HIGH — patterns copied from existing importers and migrations
- Pitfalls: HIGH — identified from direct data analysis and code review
- Phone normalization scope: HIGH — 7,107/7,119 unique phones already 10-digit bare

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable schema; only changes if Newzware changes CSV format again)
