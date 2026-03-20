# Session Handoff — New Subscription Starts Import & Dashboard Integration

**Date:** 2026-03-02
**Status:** Research complete, ready to implement
**Branch:** `feature/new-starts-tracking`

---

## Background & Business Context

The trend detail modal (PRs #32–35, now deployed) shows Total Subscribers, Starts (renewals), Stops (expirations), and Net change. However, **renewals outnumber stops yet net is still negative**. The missing piece: **brand-new first-time subscribers** aren't tracked separately.

John found a Newzware report called "New Subscription Starts" (`newstarts` macro) that captures new subscriber events. He will provide a CSV covering Dec 16, 2025 through present, for **all editions**.

### Key Finding: Overlap with Renewal Data

We tested the 8 new starts from a sample TJ-only report against `renewal_events`:

- **5 of 8** also appeared in the renewal report (as RENEW or had prior EXPIRE/RENEW history)
- **Only 1** (sub 53001) was genuinely new — zero prior history
- "PAYMENT AUTO RESTART" entries slip through even with `Include Restarts: N`

**This means:** The import must cross-reference each `SUB NUM` against `renewal_events` to classify:

- **Truly new** = no prior history in `renewal_events`
- **Restart/overlap** = has prior RENEW or EXPIRE events

---

## Report Format (Newzware "New Subscription Starts")

**Filename pattern:** `NewSubscriptionStarts*.csv`
**Sample file (TJ only, 1 week):** `queries/NewSubscriptionStarts20260302160619.csv`

### CSV Structure

```
Rows 1-10:  Report header (company info, date range, filter criteria)
Row 11:     Column headers: SUB NUM,DIST,Route,Name,Address,CITY  STATE  ZIPCODE, STARTED,Remark,PROMOTION ,Ed,ISS,DEL ,PAY ,Copy,SUBMIT,Type
Row 12:     Separator dashes
Row 13:     Blank
Rows 14+:   Data rows (one per new subscriber)
Row N:      "New,,Starts  , Summary By ,Edition" — marks start of summary sections (STOP HERE)
```

### Key Columns for Import

| Column    | Example                               | Use                                                          |
| --------- | ------------------------------------- | ------------------------------------------------------------ |
| `SUB NUM` | 2338                                  | Unique subscriber ID — used for dedup against renewal_events |
| `STARTED` | 2/24/26                               | Start date — **M/D/YY format** (needs parsing)               |
| `Ed`      | TJ                                    | Paper code — maps to business unit                           |
| `ISS`     | 5D, WA                                | Issue code (frequency)                                       |
| `DEL`     | MAIL, CARR                            | Delivery type                                                |
| `Remark`  | WEB - NEW START, PAYMENT AUTO RESTART | Acquisition source                                           |
| `SUBMIT`  | CUSTSERV, WEB                         | Submission channel                                           |
| `Type`    | Start (New)                           | Transaction type — should always be "Start (New)"            |

### Report Settings (as John will run it)

- **Editions:** ALL (not just TJ)
- **Date range:** Dec 16, 2025 → current (aligned with renewal_events earliest date)
- **Include Restarts:** N
- **Include Vacation Restarts:** N
- **Transaction Types:** S (starts only)

---

## Existing Data Timeline

| Source                | Table                              | Earliest         | Latest       |
| --------------------- | ---------------------------------- | ---------------- | ------------ |
| AllSubscriber Report  | `daily_snapshots`                  | Nov 17, 2025     | Feb 23, 2026 |
| Renewal Churn Report  | `renewal_events`                   | Dec 16, 2025     | Feb 28, 2026 |
| Renewal Churn Summary | `churn_daily_summary`              | Dec 16, 2025     | Feb 28, 2026 |
| **New Starts (new)**  | **`new_start_events` (to create)** | **Dec 16, 2025** | **present**  |

**Alignment rule:** Only process new starts data from Dec 16, 2025 onward (when renewal data starts), so deduplication cross-reference works.

---

## Implementation Plan

### Phase 1: Database

**Create table `new_start_events`:**

```sql
CREATE TABLE new_start_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_filename VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    sub_num VARCHAR(50) NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    issue_code VARCHAR(10) DEFAULT NULL,
    delivery_type VARCHAR(10) DEFAULT NULL,
    remark_code VARCHAR(100) DEFAULT NULL,
    submit_code VARCHAR(50) DEFAULT NULL,
    is_truly_new TINYINT(1) NOT NULL DEFAULT 1,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    INDEX idx_sub_num (sub_num),
    INDEX idx_paper_code (paper_code),
    UNIQUE KEY uq_sub_paper_date (sub_num, paper_code, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Key design decisions:

- `is_truly_new` — set during import by checking `renewal_events` for prior history of this `sub_num`
- `UNIQUE KEY` on (sub_num, paper_code, event_date) — prevents duplicate imports (UPSERT safe)
- Stores remark/submit codes for future acquisition channel analysis

### Phase 2: Importer

**Create `web/lib/NewStartsImporter.php`**

Follow the established importer pattern (see `RenewalImporter.php` and `AllSubscriberImporter.php`):

1. Parse CSV — find header row containing "SUB NUM", skip decorative rows
2. Extract data rows until summary section ("New,,Starts") is reached
3. Parse `STARTED` date from `M/D/YY` format → `Y-m-d`
4. For each row, query `renewal_events` to check if `sub_num` has any prior history:
   ```sql
   SELECT COUNT(*) FROM renewal_events
   WHERE sub_num = ? AND event_date < ?
   ```
   If count > 0 → `is_truly_new = 0` (restart/overlap)
   If count = 0 → `is_truly_new = 1` (genuinely new)
5. UPSERT into `new_start_events`
6. Return stats: total imported, truly new count, restart count, by paper

**Filename detection pattern:** `NewSubscription*` or `NewStart*` or `newstart*`

### Phase 3: Create Processor + Wire into Upload

**Create `web/processors/NewStartsProcessor.php`**

- Follows `RenewalProcessor.php` pattern
- Called by `upload_unified.php` and `auto_process.php`

**Update `web/upload_unified.php`**

- Add "New Starts" as a file type option in the upload interface

**Update `web/auto_process.php`**

- Add filename pattern for auto-detection: `NewSubscription*` → NewStartsProcessor

### Phase 4: Create Summary Table (for chart integration)

**Create table `new_starts_daily_summary`:**

```sql
CREATE TABLE new_starts_daily_summary (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    total_new_starts INT NOT NULL DEFAULT 0,
    truly_new_count INT NOT NULL DEFAULT 0,
    restart_count INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_date_paper (snapshot_date, paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Populated during import — aggregates per paper per day.

### Phase 5: API + Chart Integration

**Update `web/api/get_bu_trend_detail.php`:**

- Add a third query joining `new_starts_daily_summary` by the same week-Sunday grouping
- Return new fields: `new_starts`, `truly_new`, `restarts`

**Update `web/assets/js/features/bu-trend-detail.js`:**

- Add "New Starts" dataset to the chart (new color, perhaps purple or teal bar)
- Add columns to the data table: "New" and optionally "Restarts"
- Update the explanatory note to describe New Starts

**Update `web/assets/js/core/app.js`** (optional/future):

- Could add new starts count to the BU card mini-view

### Phase 6: Tailwind Rebuild

Any new Tailwind classes used in the JS → run `npm run build:css` and cache-bust the version tag in `index.php`.

---

## Production Environment

- **Production URL:** `http://192.168.1.254:8081` (also `https://cdash.upstatetoday.com`)
- **NAS SSH:** `ssh nas` (passwordless key auth)
- **DB access:** `ssh nas` then `/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard`
- **Web dir:** `/volume1/web/circulation/`

---

## Cross-Reference Logic (Critical)

For each new start `sub_num`, check `renewal_events`:

```sql
-- If this returns rows, the subscriber has prior history → is_truly_new = 0
SELECT 1 FROM renewal_events
WHERE sub_num = :sub_num
  AND event_date < :start_date
LIMIT 1
```

This catches:

- People who expired and came back ("PAYMENT AUTO RESTART")
- People who show as both new start AND renewal on same day
- Recurring churners (subscribe/expire/subscribe cycles)

Only subscribers with **zero** prior `renewal_events` history are classified as truly new.

---

## File Locations Summary

| What                       | Path                                                               |
| -------------------------- | ------------------------------------------------------------------ |
| Sample CSV                 | `queries/NewSubscriptionStarts20260302160619.csv`                  |
| Importer (create)          | `web/lib/NewStartsImporter.php`                                    |
| Processor (create)         | `web/processors/NewStartsProcessor.php`                            |
| Upload UI (modify)         | `web/upload_unified.php`                                           |
| Auto processor (modify)    | `web/auto_process.php`                                             |
| API (modify)               | `web/api/get_bu_trend_detail.php`                                  |
| Chart JS (modify)          | `web/assets/js/features/bu-trend-detail.js`                        |
| Migration (create)         | `database/migrations/` or inline in setup                          |
| Existing importer patterns | `web/lib/RenewalImporter.php`, `web/lib/AllSubscriberImporter.php` |

---

## What John Will Provide

A single CSV file: `NewSubscriptionStarts*.csv` covering:

- **All editions** (TJ, TR, LJ, WRN, TA)
- **Date range:** ~Dec 16, 2025 through Mar 2, 2026
- **Settings:** Include Restarts: N, Include Vacation Restarts: N, Transaction Types: S
- **Location:** Will be placed somewhere accessible (likely `queries/` or uploaded directly)

---

## Session Completed Today (for context)

PRs #32–35 shipped the BU Trend Detail Modal:

- Mixed Chart.js chart (total line + starts/stops bars + net dashed line)
- Smart Y-axis scaling, print button, responsive layout
- Batch query optimization (2 queries, not N+1)
- Tailwind CSS rebuild (added .php to content scan)
- PHPCS CI fixes
- All deployed to production
