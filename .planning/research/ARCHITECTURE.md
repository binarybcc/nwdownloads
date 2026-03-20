# Architecture Research

**Domain:** VOIP call log integration with newspaper circulation dashboard
**Researched:** 2026-03-20
**Confidence:** HIGH — all findings derived from direct codebase inspection and verified integration documentation

## Standard Architecture

### System Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                     macOS launchd (Scheduling)                    │
│  ┌────────────────────────────────┐  ┌─────────────────────────┐ │
│  │  com.circulation.auto-import   │  │  com.circulation.call-  │ │
│  │  (Monday 08:02 AM)             │  │  logs (hourly, 8am-8pm) │ │
│  └──────────────┬─────────────────┘  └────────────┬────────────┘ │
│                 │ ssh nas + php82                  │ ssh nas      │
└─────────────────┼──────────────────────────────────┼─────────────┘
                  │                                  │
┌─────────────────▼──────────────────────────────────▼─────────────┐
│                   NAS PHP CLI Layer                               │
│  ┌──────────────────────────┐   ┌───────────────────────────┐    │
│  │  auto_process.php        │   │  fetch_call_logs.php       │    │
│  │  (CSV file router)       │   │  (NEW — scraper runner)    │    │
│  └────────────┬─────────────┘   └──────────────┬────────────┘    │
│               │                                │                  │
│  ┌────────────▼─────────────┐   ┌──────────────▼────────────┐    │
│  │  *Importer.php classes   │   │  MyCommPilotScraper.php    │    │
│  │  (web/lib/)              │   │  (NEW — web/lib/)          │    │
│  └────────────┬─────────────┘   └──────────────┬────────────┘    │
└───────────────┼─────────────────────────────────┼────────────────┘
                │                                 │
┌───────────────▼─────────────────────────────────▼────────────────┐
│                     MariaDB (circulation_dashboard)               │
│  ┌─────────────────────┐        ┌──────────────────────────────┐  │
│  │  subscriber_        │        │  call_logs                   │  │
│  │  snapshots          │        │  (NEW — separate table)      │  │
│  │  (existing)         │        │                              │  │
│  └────────────┬────────┘        └──────────────┬───────────────┘  │
└───────────────┼─────────────────────────────────┼────────────────┘
                │                                 │
┌───────────────▼─────────────────────────────────▼────────────────┐
│                     PHP API Layer (web/api/)                      │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  legacy.php                                                  │  │
│  │  - getOverviewEnhanced() — expiration_chart (MODIFY: 8-wk)  │  │
│  │  - getExpirationSubscribers() — (MODIFY: + call_status JOIN) │  │
│  └──────────────────────────────┬──────────────────────────────┘  │
└──────────────────────────────────┼─────────────────────────────────┘
                                   │
┌──────────────────────────────────▼─────────────────────────────────┐
│                     Browser (vanilla JS + Chart.js)                │
│  ┌────────────────────┐  ┌──────────────────┐  ┌────────────────┐  │
│  │  chart-context-    │  │  expiration       │  │  export-utils  │  │
│  │  integration.js    │  │  subscriber table │  │  .js (MODIFY)  │  │
│  │  (existing)        │  │  (MODIFY: status) │  └────────────────┘  │
│  └────────────────────┘  └──────────────────┘                       │
└────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component                                 | Responsibility                                                                                         | Status |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------ | ------ |
| `web/lib/MyCommPilotScraper.php`          | Session login, HTML scrape, call log parse for all 3 call types across 2 lines                         | NEW    |
| `web/fetch_call_logs.php`                 | CLI runner: instantiate scraper, loop users/types, INSERT IGNORE into call_logs, log results           | NEW    |
| `call_logs` table                         | Persist all call history; normalized phone; dedup key on (line, type, normalized_phone, call_datetime) | NEW    |
| `legacy.php` — expiration query           | Extend 4-bucket CASE/WHEN to 8 weekly buckets                                                          | MODIFY |
| `legacy.php` — getExpirationSubscribers() | Add LEFT JOIN call_logs on normalized phone + date window; return call_status field                    | MODIFY |
| `export-utils.js`                         | Carry row color coding (call status) into XLSX cell fills                                              | MODIFY |
| macOS launchd plist (call logs)           | Trigger fetch_call_logs.php hourly 8am–8pm ET, weekdays                                                | NEW    |

## Recommended Project Structure

```
web/
├── lib/
│   ├── MyCommPilotScraper.php   # NEW: scraper class (already specified in integration doc)
│   ├── AllSubscriberImporter.php
│   ├── NewStartsImporter.php
│   └── ...                      # existing importers unchanged
├── processors/
│   └── ...                      # existing processors — no call log processor needed
│                                # (scraper writes directly; no file-based workflow)
├── api/
│   └── legacy.php               # MODIFY: 8-week expiration, call_status JOIN
├── fetch_call_logs.php          # NEW: CLI runner script (parallel to auto_process.php)
└── auto_process.php             # EXISTING: unchanged
```

```
sql/
└── migrations/
    └── 003_call_logs.sql        # NEW: call_logs table + phone normalization index
```

```
~/Library/LaunchAgents/
├── com.circulation.auto-import.plist       # EXISTING: Monday 08:02
└── com.circulation.call-logs.plist         # NEW: hourly 8am-8pm weekdays
```

### Structure Rationale

- **`fetch_call_logs.php` at web root (not lib):** Parallel to `auto_process.php` — both are CLI-only runner scripts, not library classes. Same pattern, same location.
- **`MyCommPilotScraper.php` in `web/lib/`:** Library class consumed by the runner, consistent with all other importer classes.
- **No CallLogsProcessor needed:** The importer/processor pattern exists to handle file routing (inbox → processing → completed/failed). Call logs are scraped directly from a live HTTP source, not file-dropped CSVs. A processor adds complexity with no benefit here.
- **Separate `call_logs` table (not denormalized into subscriber_snapshots):** See Data Model section below.

## Architectural Patterns

### Pattern 1: Separate Table, Join at Query Time

**What:** `call_logs` lives as its own table. `getExpirationSubscribers()` performs a LEFT JOIN at query time to attach call status to each subscriber row.

**When to use:** When the two data sources have different update cadences (weekly vs hourly), different schemas, and the cross-reference is a read-only overlay — not a persistent merge.

**Why not denormalize into subscriber_snapshots:** Subscriber snapshots are rebuilt wholesale each week from Newzware CSVs. Denormalizing call data into them would either (a) be overwritten on next import, or (b) require the AllSubscriberImporter to know about call logs — a cross-cutting concern it should not own. The data sources are independent; keep them independent.

**Trade-offs:** JOIN at query time adds a small amount of query complexity. At the scale of this dashboard (hundreds of subscribers per BU, not millions), this cost is negligible.

**Example:**

```sql
-- getExpirationSubscribers() modification:
SELECT
    s.sub_num as account_id,
    s.name as subscriber_name,
    s.phone,
    CASE
        WHEN cl.most_recent_call IS NOT NULL THEN 'contacted'
        ELSE 'not_contacted'
    END as call_status,
    cl.most_recent_call as last_call_datetime,
    cl.call_type as last_call_type,
    ...
FROM subscriber_snapshots s
LEFT JOIN (
    SELECT
        normalized_phone,
        MAX(call_timestamp) as most_recent_call,
        -- call_type from the most recent call
        SUBSTRING_INDEX(GROUP_CONCAT(call_type ORDER BY call_timestamp DESC), ',', 1) as call_type
    FROM call_logs
    WHERE call_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY normalized_phone
) cl ON s.phone_normalized = cl.normalized_phone
WHERE s.business_unit = :business_unit
  AND s.snapshot_date = :snapshot_date
  AND ...
```

### Pattern 2: Phone Number Normalization at Ingest

**What:** Both sides normalize phone numbers to 10-digit strings at the point of data entry — the scraper normalizes before INSERT, and a computed column or separate field in subscriber_snapshots holds the normalized form.

**When to use:** Whenever two datasets that share phone numbers are ingested from different sources with different formatting conventions.

**The problem this solves:**

The MyCommPilot call logs produce:

- 10-digit numbers: `8649737731`
- 4-digit internal extensions: `6706`
- 7-digit local numbers: `7365588` (missing area code)
- "Private": no usable data

Newzware subscriber data may include:

- `(864) 973-7731` — formatted with punctuation
- `864-973-7731` — hyphen-separated
- `8649737731` — raw 10-digit
- `7365588` — 7-digit without area code

**Normalization function (PHP):**

```php
function normalizePhone(string $raw): ?string {
    // Strip everything except digits
    $digits = preg_replace('/\D/', '', $raw);

    // Discard extensions (4-digit internal), "Private", empty
    if (strlen($digits) < 7) return null;

    // Strip leading "1" from 11-digit US numbers
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }

    // Accept 10-digit results only
    if (strlen($digits) === 10) return $digits;

    // 7-digit: cannot reliably match without knowing area code — store as-is
    // but flag as low-confidence match
    if (strlen($digits) === 7) return $digits; // partial match possible
    return null;
}
```

**Where normalization happens:**

1. `MyCommPilotScraper::parseCallLogs()` — normalize before returning entries (or in the runner before INSERT)
2. `call_logs` table — store both `phone` (raw) and `phone_normalized` (computed)
3. `subscriber_snapshots` — add `phone_normalized` as a generated/stored column or populated during AllSubscriberImporter

**Trade-offs:** Adds one field to subscriber_snapshots, but eliminates all format-mismatch false negatives at JOIN time. The alternative (normalizing in SQL at JOIN time) works but makes the query harder to index.

### Pattern 3: INSERT IGNORE Deduplication

**What:** The unique key on `call_logs` (`line_label`, `call_type`, `phone_normalized`, `call_datetime`) combined with `INSERT IGNORE` means re-scraping already-seen calls is safe and idempotent. Each hourly run can safely re-fetch the full 20-entry window without creating duplicates.

**When to use:** Any time a rolling-window source is polled repeatedly. This is the same pattern the existing importers use for daily snapshots.

**Trade-offs:** Requires the unique key to be tightly defined. The dedup tuple of (line, type, normalized_phone, datetime) should be unique because BroadWorks timestamps are per-minute and the same subscriber would not call twice in the same minute on the same call type. Internal extension calls (4-digit phones) will not match external subscriber numbers and are safely ignored or stored but never joined.

## Data Model

### call_logs Table

```sql
CREATE TABLE call_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    line_label      VARCHAR(10)  NOT NULL,           -- 'BC' or 'CW'
    call_type       ENUM('placed','received','missed') NOT NULL,
    caller_name     VARCHAR(100),                    -- raw name from BroadWorks
    phone_raw       VARCHAR(30),                     -- original as scraped
    phone_normalized VARCHAR(10),                    -- 7 or 10 digits, NULL if unparseable
    call_datetime   VARCHAR(30)  NOT NULL,           -- raw: '3/20/26 8:02 AM'
    call_timestamp  DATETIME,                        -- parsed, stored as DATETIME
    is_extension    BOOLEAN DEFAULT FALSE,           -- TRUE when phone is 4 digits
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY dedup (line_label, call_type, phone_normalized, call_datetime),
    INDEX idx_phone_normalized (phone_normalized),
    INDEX idx_call_timestamp (call_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Notes on schema decisions:**

- `call_datetime` (raw VARCHAR) is kept alongside `call_timestamp` (parsed DATETIME) because the raw format `M/D/YY h:mm AM/PM` is the dedup key from the source. If parsing logic ever changes, the raw value is preserved.
- `is_extension` flag lets the JOIN query skip internal extension numbers without pattern matching on every row.
- `phone_normalized` is indexed because it is the JOIN column.

### subscriber_snapshots — Required Addition

A `phone_normalized` column must be added to allow an indexed JOIN. Options:

**Option A (recommended): Computed column populated by AllSubscriberImporter**

```sql
ALTER TABLE subscriber_snapshots ADD COLUMN phone_normalized VARCHAR(10) AFTER phone;
```

AllSubscriberImporter already processes phone on every import — add normalization there. This is a one-time schema migration + importer change. Re-upload the current AllSubscriber CSV to populate it.

**Option B: Generated column (MariaDB)**

```sql
ALTER TABLE subscriber_snapshots ADD COLUMN phone_normalized VARCHAR(10)
    GENERATED ALWAYS AS (REGEXP_REPLACE(REGEXP_REPLACE(phone, '[^0-9]', ''), '^1([0-9]{10})$', '\\1'))
    STORED;
```

Option B is appealing but MariaDB's REGEXP_REPLACE in generated columns has limitations with complex patterns. Option A is more reliable and keeps normalization logic in PHP where it can be tested.

## Data Flow

### Call Log Collection Flow (Hourly)

```
macOS launchd (hourly, 8am-8pm ET weekdays)
    |
    | ssh nas
    v
fetch_call_logs.php (CLI)
    |
    | new MyCommPilotScraper(username, password)
    |   login() → HTTPS then HTTP session
    v
    | for each user [BC, CW]:
    |   for each type [placed, received, missed]:
    |     getCallLogs(userKey, type) → array of raw entries
    |       normalizePhone() on each entry
    |       INSERT IGNORE into call_logs
    v
call_logs table
    |
    | (no cache clear needed — call data is live-joined at query time)
    v
Available immediately for next dashboard request
```

### Expiration Subscriber Request Flow (Modified)

```
User right-clicks expiration chart bar
    |
    v
chart-context-integration.js
    | fetch /api/legacy.php?action=get_expiration_subscribers&bu=X&bucket=Y
    v
legacy.php → getExpirationSubscribers(pdo, bu, date, bucket)
    |
    | SELECT subscriber_snapshots s
    | LEFT JOIN (
    |   SELECT phone_normalized, MAX(call_timestamp), call_type
    |   FROM call_logs
    |   WHERE call_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    |   GROUP BY phone_normalized
    | ) cl ON s.phone_normalized = cl.normalized_phone
    v
JSON response with call_status, last_call_datetime, last_call_type per row
    |
    v
Frontend subscriber table
    | Row coloring: green (contacted), yellow (missed/no answer), none (not contacted)
    | Phone icon with tooltip showing last call info
    v
export-utils.js → XLSX with cell fill colors matching row status
```

### Weekly Import Flow (Unchanged)

```
macOS launchd (Monday 08:02 AM)
    | ssh nas → auto_process.php
    v
AllSubscriberImporter → subscriber_snapshots (+ phone_normalized populated)
VacationImporter, RenewalImporter, etc.
    | SimpleCache::clear()
    v
Dashboard reflects new snapshot data
```

## Scheduling Architecture

### Coexistence of Weekly + Hourly Schedules

The two launchd jobs are fully independent and do not conflict:

| Plist                         | Trigger            | Action                | Lock file                            |
| ----------------------------- | ------------------ | --------------------- | ------------------------------------ |
| `com.circulation.auto-import` | Monday 08:02 AM    | `auto_process.php`    | `/tmp/circulation_auto_process.lock` |
| `com.circulation.call-logs`   | Hourly 8am–8pm M–F | `fetch_call_logs.php` | `/tmp/circulation_call_logs.lock`    |

Both use separate lock files. The call logs job runs every hour during business hours; the auto-import runs once weekly. There is no shared state between them.

### New Plist Template

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
    "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.circulation.call-logs</string>

    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/ssh</string>
        <string>nas</string>
        <string>/var/packages/PHP8.2/target/usr/local/bin/php82</string>
        <string>/volume1/web/circulation/fetch_call_logs.php</string>
    </array>

    <!-- Run every hour on the hour, Monday-Friday, 8am-8pm -->
    <key>StartCalendarInterval</key>
    <array>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>8</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>9</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>10</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>11</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>12</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>13</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>14</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>15</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>16</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>18</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>19</integer><key>Minute</key><integer>0</integer></dict>
        <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>20</integer><key>Minute</key><integer>0</integer></dict>
        <!-- Repeat the above dict blocks for Weekday 2 (Tue) through 5 (Fri) -->
    </array>

    <key>StandardOutPath</key>
    <string>/Users/johncorbin/Library/Logs/circulation/call-logs.log</string>
    <key>StandardErrorPath</key>
    <string>/Users/johncorbin/Library/Logs/circulation/call-logs.log</string>
</dict>
</plist>
```

**Note on StartCalendarInterval:** macOS launchd does not support a cron-style "every hour between X and Y" in a single dict. You must list each hour/weekday combination as a separate dict in an array. For M–F, 8am–8pm (13 hours × 5 days = 65 dicts), this is verbose but correct and reliable. An alternative is a single `StartInterval` of 3600 (every hour, 24/7) with a time-of-day guard inside `fetch_call_logs.php` — this is simpler and the recommended approach (see Anti-Patterns below).

## Integration Points

### External Services

| Service                  | Integration Pattern                | Credential                         | Notes                                                                                  |
| ------------------------ | ---------------------------------- | ---------------------------------- | -------------------------------------------------------------------------------------- |
| MyCommPilot (BroadWorks) | Session-based HTTP scrape via curl | `.env.mycommpilot`                 | HTTPS login → HTTP session; fresh login per run; 20-entry rolling window per call type |
| MariaDB (NAS)            | Unix socket PDO                    | `auto_process.php` hardcoded creds | `fetch_call_logs.php` uses same pattern                                                |

### Internal Boundaries

| Boundary                                     | Communication                                                      | Notes                                                                             |
| -------------------------------------------- | ------------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| `fetch_call_logs.php` → `MyCommPilotScraper` | Direct instantiation                                               | Same pattern as auto_process.php → Importers                                      |
| `legacy.php` → `call_logs` table             | SQL LEFT JOIN inside getExpirationSubscribers()                    | No new API endpoint needed; call status is an overlay on existing subscriber data |
| Frontend → call status                       | JSON field `call_status` added to existing subscriber row response | No new endpoint; existing right-click + table render path reused                  |
| `AllSubscriberImporter` → `phone_normalized` | Normalize phone during existing import loop                        | Minimal change: add one field population to existing UPSERT                       |

## Build Order

Build in this sequence because of hard dependencies:

1. **Schema migration** (`call_logs` table + `phone_normalized` on `subscriber_snapshots`) — everything else depends on the schema existing.
2. **`AllSubscriberImporter` phone normalization** — populate `phone_normalized` on existing rows. Run a one-time re-import or migration script to backfill.
3. **`MyCommPilotScraper.php`** — library class, no dependencies except the `call_logs` table.
4. **`fetch_call_logs.php`** — CLI runner, depends on scraper class and call_logs table.
5. **launchd plist** — depends on `fetch_call_logs.php` existing on NAS (deploy first).
6. **`legacy.php` expiration query expansion** (4-week → 8-week) — independent of call logs, can be done in parallel with steps 3–5.
7. **`legacy.php` getExpirationSubscribers() JOIN** — depends on `phone_normalized` being populated (step 2) and `call_logs` table existing (step 1).
8. **Frontend call status display** — depends on step 7 returning `call_status` in API response.
9. **XLSX export with row colors** — depends on step 8 (needs the status field to be present in the table rows before export logic can reference it).

## Anti-Patterns

### Anti-Pattern 1: Using a CallLogsProcessor in the Importer/Processor Pattern

**What people do:** Create `web/lib/CallLogsImporter.php` + `web/processors/CallLogsProcessor.php` and route through `auto_process.php`.

**Why it's wrong:** The importer/processor pattern is designed for file-drop workflows (Newzware SFTP → inbox → processing → completed). Call logs come from a live HTTP source, not a CSV file. Forcing this into the file-based pattern would require either generating temporary CSV files or inventing fake filenames — both are unnecessary complexity that adds no value.

**Do this instead:** A standalone `fetch_call_logs.php` CLI script, directly parallel to `auto_process.php`. Same DB connection pattern, same logging pattern, different trigger mechanism.

### Anti-Pattern 2: 65-Dict StartCalendarInterval Plist

**What people do:** List every hour/weekday combination (65 dicts) in the launchd plist to implement "hourly between 8am and 8pm on weekdays."

**Why it's wrong:** Verbose, hard to maintain, easy to make a typo, and breaks if you want to adjust hours. The plist becomes difficult to read.

**Do this instead:** Use `StartInterval` of `3600` (run every hour) and add a business hours guard at the top of `fetch_call_logs.php`:

```php
// Guard: only run during business hours (8am-8pm ET, M-F)
$now = new DateTime('now', new DateTimeZone('America/New_York'));
$hour = (int)$now->format('G');      // 0-23
$dow  = (int)$now->format('N');      // 1=Mon, 7=Sun
if ($dow > 5 || $hour < 8 || $hour >= 20) {
    exit(0); // outside business hours, exit silently
}
```

This keeps the plist minimal (same shape as the existing `auto-import` plist) and puts business-hours logic where it is easy to find, test, and modify.

### Anti-Pattern 3: Normalizing Phone at JOIN Time in SQL

**What people do:** Write the normalization logic inline in the JOIN condition: `ON REGEXP_REPLACE(s.phone, '[^0-9]', '') = REGEXP_REPLACE(cl.phone_raw, '[^0-9]', '')`.

**Why it's wrong:** Cannot be indexed. Every row in both tables gets the function applied on every query. At small scale this works but is architecturally wrong — functions on JOIN columns defeat indexes.

**Do this instead:** Normalize at ingest, store normalized form, index the normalized column, JOIN on the indexed column.

### Anti-Pattern 4: Joining call_logs Directly on Raw Phone

**What people do:** `JOIN call_logs cl ON s.phone = cl.phone_raw`.

**Why it's wrong:** Format mismatches between `(864) 973-7731` (subscriber) and `8649737731` (call log) will silently produce zero matches. No error — just missing call status for every subscriber.

**Do this instead:** Normalize both sides at ingest (pattern 2 above). Test the JOIN on known-contacted subscribers immediately after first scrape run to verify matches are occurring.

## Scaling Considerations

This dashboard serves a small internal team (2-3 circulation staff). Scaling is not a concern. The architecture choices here are driven by maintainability and correctness, not performance.

| Concern                  | At current scale (hundreds of subscribers/BU)                                                   | Notes                                                           |
| ------------------------ | ----------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| JOIN performance         | Trivial — indexed phone_normalized, small table                                                 | No pagination or caching needed                                 |
| call_logs table growth   | ~120 rows/hour max (2 users × 3 types × 20 entries) = ~1,560 rows/day. 30-day window ≈ 47K rows | Stays small indefinitely at this scale                          |
| BroadWorks rate limiting | Unknown — no documented limit. Fresh session per run, logout after.                             | If issues arise, add 1–2 second sleep between user/type fetches |

## Sources

- `docs/MYCOMMPILOT-INTEGRATION.md` — Complete scraper class, auth flow, schema suggestion (HIGH confidence — direct codebase inspection)
- `web/auto_process.php` — Existing importer/processor pattern (HIGH confidence — direct codebase inspection)
- `web/api/legacy.php` lines 1246–1280 — Expiration query structure (HIGH confidence — direct codebase inspection)
- `web/api/legacy.php` lines 1407–1567 — getExpirationSubscribers() current implementation (HIGH confidence — direct codebase inspection)
- `.planning/PROJECT.md` — Constraints and key decisions (HIGH confidence — direct codebase inspection)
- macOS launchd documentation — StartCalendarInterval vs StartInterval behavior (HIGH confidence — production plist in `~/Library/LaunchAgents/com.circulation.auto-import.plist` as reference)

---

_Architecture research for: NWDownloads v2.1 — VOIP call log integration_
_Researched: 2026-03-20_
