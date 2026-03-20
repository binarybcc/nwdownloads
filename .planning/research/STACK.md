# Stack Research

**Domain:** Newspaper circulation dashboard — call log integration, expiration chart expansion, import fixes
**Researched:** 2026-03-20
**Confidence:** HIGH (existing stack verified from codebase; new additions verified against Packagist and official docs)

---

## Existing Stack (Do Not Change)

These are already in production. Listed here to document integration points for new features.

| Technology   | Version           | Role                                                         |
| ------------ | ----------------- | ------------------------------------------------------------ |
| PHP          | 8.2               | Server-side logic, API endpoints, importers                  |
| MariaDB      | 10                | Database (production: Unix socket on NAS)                    |
| Chart.js     | 4.4.0 (CDN)       | All data visualization                                       |
| SheetJS Pro  | xlsx-latest (CDN) | Client-side XLSX export — already supports cell `.s` styling |
| Tailwind CSS | 3.4.x             | Utility-first CSS, built via `npm run build:css`             |
| Vanilla JS   | ES2020+           | No framework; modular files loaded in explicit order         |

---

## Recommended Stack Additions

### New Production Dependency: PhpSpreadsheet

| Technology               | Version | Purpose                                                                           | Why Recommended                                                                                                                         |
| ------------------------ | ------- | --------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| phpoffice/phpspreadsheet | ^5.5    | Server-side XLSX generation with row-level background color, call status coloring | Only mature PHP XLSX library with full cell fill/font/border styling support. Current latest is 5.5.0 (March 2026). PHP 8.2 compatible. |

**Install:**

```bash
composer require phpoffice/phpspreadsheet
```

**Why PhpSpreadsheet and not client-side SheetJS for this feature:**

The call status XLSX export (expiring subscriber table with color-coded call status rows) is triggered from a right-click context menu on chart columns. The subscriber data is already fetched server-side via API. Generating the styled XLSX on the server avoids passing large subscriber arrays back to the browser and keeps styling logic in PHP alongside the data logic — consistent with how all other importers and processors work in this project.

**PhpSpreadsheet cell fill pattern (HIGH confidence):**

```php
use PhpOffice\PhpSpreadsheet\Style\Fill;

$sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FEF3C7'],  // amber-100 — "called, no answer"
    ],
]);
```

Color palette for call status rows:

- Called and confirmed: `D1FAE5` (green-100)
- Called, no answer: `FEF3C7` (amber-100)
- Not yet contacted: no fill (default white)
- Do not contact: `FEE2E2` (red-100)

---

### No New JS Dependencies

**Constraint from PROJECT.md:** "Must use existing Chart.js 4.4.0 — no new JS dependencies."

The existing SheetJS Pro CDN (`cdn.sheetjs.com/xlsx-latest`) already handles styled XLSX in the browser. The `export-utils.js` already applies `.s` style objects to cells (header colors, alternating row fills). Extending this for call status row coloring requires only logic changes in `export-utils.js` — no new library.

---

## Supporting Libraries

| Library                      | Version              | Purpose                                      | When to Use                                                       |
| ---------------------------- | -------------------- | -------------------------------------------- | ----------------------------------------------------------------- |
| phpoffice/phpspreadsheet     | ^5.5                 | Server-side XLSX with call status row colors | For the "Export expiration table with call status" feature        |
| PHP cURL (built-in)          | bundled with PHP 8.2 | BroadWorks portal scraping                   | Already available on Synology PHP 8.2 package — no install needed |
| PHP PDO + MariaDB (built-in) | bundled              | Call logs table storage                      | Already in use project-wide                                       |

---

## Development Tools (No Changes)

The existing toolchain handles this milestone without modification:

| Tool                | Purpose             | Notes                                                                    |
| ------------------- | ------------------- | ------------------------------------------------------------------------ |
| `ssh nas`           | Production access   | Passwordless SSH to Synology NAS for deployment and DB operations        |
| Phinx `^0.13`       | Database migrations | Use for new `call_logs` table migration                                  |
| launchd plist       | Scheduled scraping  | Existing pattern from `auto_process.php`; new plist for call log scraper |
| `npm run build:css` | Tailwind rebuild    | Required after adding any new utility classes to JS/PHP                  |

---

## cURL / Session Management for BroadWorks JSP Scraping

The integration doc at `docs/MYCOMMPILOT-INTEGRATION.md` contains a working `MyCommPilotScraper` PHP class. Key implementation notes verified against the BroadWorks portal behavior:

**Session management pattern (HIGH confidence — tested against live portal):**

1. Single cURL handle reused across all requests in one scrape run (preserves session state)
2. `CURLOPT_COOKIEFILE` and `CURLOPT_COOKIEJAR` both point to the same temp file — this handles the HTTPS-to-HTTP cookie carryover that BroadWorks requires
3. `CURLOPT_FOLLOWLOCATION => true` must be set — the login response is a 200 with a JavaScript redirect, not an HTTP 302, so this handles the intermediate hop to `folder_contents.jsp`
4. After login over HTTPS, ALL subsequent requests use `http://ws2.mycommpilot.com:80` — the scraper must switch base URLs at Step 3
5. Always do a fresh login per scrape run — do NOT attempt to reuse sessions across cron invocations (sessions expire and the cookie jar is deleted after `__destruct`)

**Known gotcha — HTTPS-to-HTTP cross-protocol cookies:**

libcurl by default does not send cookies from an HTTPS origin to an HTTP endpoint. The `CURLOPT_COOKIEFILE`/`CURLOPT_COOKIEJAR` file-based cookie jar bypasses this because both endpoints write to and read from the same Netscape-format cookie file, regardless of protocol. This is why the working class uses a temp file rather than `CURLOPT_COOKIEJAR => ''` (in-memory only). Do not change this.

**Additional cURL options to add for production robustness:**

```php
CURLOPT_TIMEOUT => 30,
CURLOPT_CONNECTTIMEOUT => 10,
CURLOPT_SSL_VERIFYPEER => true,
CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; circulation-dashboard/2.1)',
```

---

## Chart.js: Expanding from 4 to 8 Week Expiration View

**No new plugins required.** Chart.js 4.4.0 handles 8 categories in a bar chart natively.

What changes are needed:

| Change                | Where                           | Notes                                                                       |
| --------------------- | ------------------------------- | --------------------------------------------------------------------------- |
| SQL CASE statement    | `legacy.php` `getBUDetail()`    | Extend from 21-day to 56-day window; add Week +3 through Week +7 buckets    |
| Chart label array     | `detail_panel.js` or equivalent | Add 4 more string labels; Chart.js scales automatically                     |
| Bar width             | Chart.js `barPercentage` option | May need to reduce from default 0.9 to ~0.7 with 8 bars to prevent crowding |
| X-axis label rotation | `ticks.maxRotation`             | Set to 45 degrees if labels truncate at narrower BU panel width             |

**Label overflow is not a concern at 8 categories** (Chart.js only starts struggling at hundreds of categories). The BU detail slide-out panel is ~400px wide — with 8 bars, using short labels ("Wk +3", "Wk +4") prevents overflow without any plugin.

**Chart.js 4.4.0 bar chart config for 8-category view (MEDIUM confidence — pattern from official docs):**

```javascript
options: {
  scales: {
    x: {
      ticks: {
        maxRotation: 45,
        font: { size: 11 }
      }
    }
  },
  barPercentage: 0.75,
  categoryPercentage: 0.85
}
```

---

## Alternatives Considered

| Recommended                    | Alternative                                  | When to Use Alternative                                                                                                                                                                                     |
| ------------------------------ | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PhpSpreadsheet (server-side)   | Extend SheetJS export-utils.js (client-side) | Use client-side if the subscriber data is already in the browser DOM at export time. For the expiration+call-status table, call status must be joined server-side anyway, so server-side export is cleaner. |
| PhpSpreadsheet `^5.5`          | `^1.x` or `^2.x`                             | Never — 5.x is current stable. Older versions have fill color bugs (GitHub issue #2528) and are unsupported on PHP 8.2.                                                                                     |
| Single reused cURL handle      | New handle per request                       | Use per-request handles only if scraping from multiple concurrent PHP processes. For the single scheduled scraper, one handle is correct.                                                                   |
| launchd (macOS) for scheduling | Synology Task Scheduler                      | Never use Synology Task Scheduler — it silently deletes jobs (documented in MEMORY.md).                                                                                                                     |

---

## What NOT to Use

| Avoid                                                | Why                                                                        | Use Instead                                            |
| ---------------------------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------ |
| `fopen()` wrapper for HTTP scraping                  | No cookie/session support; can't handle redirects                          | PHP cURL                                               |
| `league/csv` or similar for XLSX with colors         | CSV has no styling; "XLSX" libs without PhpSpreadsheet don't support fills | PhpSpreadsheet                                         |
| Chart.js plugins (`chartjs-plugin-datalabels`, etc.) | Project constraint: no new JS dependencies                                 | Native Chart.js 4.4 options (`ticks`, `barPercentage`) |
| XSI REST API                                         | Returns 401 — not enabled by Segra carrier                                 | Web scraping via cURL (documented approach)            |
| Synology Task Scheduler for hourly scraper           | Silently deletes jobs                                                      | macOS launchd (existing pattern)                       |
| `phpoffice/phpexcel`                                 | Abandoned in 2017, PHP 8 incompatible                                      | PhpSpreadsheet (the official successor)                |

---

## Stack Patterns by Feature

**Call log scraper (new `web/lib/MyCommPilotScraper.php`):**

- PHP cURL with file-based cookie jar (already designed in integration doc)
- Stores to new `call_logs` MariaDB table via PDO
- CLI invoked by launchd plist on Mac → SSH to NAS → runs `/var/packages/PHP8.2/target/usr/local/bin/php82 auto_scrape_calls.php`
- `INSERT IGNORE` deduplication on `(line_label, call_type, phone, call_datetime)` unique key

**Expiration chart (4 → 8 weeks):**

- SQL-only change in `legacy.php` plus chart label update in frontend JS
- No new dependencies

**XLSX export with call status row coloring:**

- If export is triggered from client-side table: extend `export-utils.js` — call status data is already in the table DOM, SheetJS Pro `.s` styling is already used in the file
- If export needs server-side join (call logs + subscriber data): add PhpSpreadsheet, create `web/api/export_expiration.php` endpoint

**Import date fix (new starts CSV):**

- Pure PHP string manipulation in `web/lib/NewStartsImporter.php`
- No new dependencies

---

## Version Compatibility

| Package                       | Compatible With          | Notes                                                                                                         |
| ----------------------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------- |
| phpoffice/phpspreadsheet ^5.5 | PHP ^8.1                 | PHP 8.2 is supported; requires `ext-zip`, `ext-xml`, `ext-mbstring` (all present in Synology PHP 8.2 package) |
| Chart.js 4.4.0                | Vanilla JS, no framework | No Chart.js plugins needed for 8-bar expansion                                                                |
| SheetJS Pro xlsx-latest       | Browser, CDN             | `.s` cell styling already works in project (see `export-utils.js` lines 52-76)                                |

---

## Installation

```bash
# Add PhpSpreadsheet for server-side styled XLSX export
composer require phpoffice/phpspreadsheet

# No npm changes needed
# No new CDN scripts needed
```

**Synology production note:** Composer is run locally, then vendor/ is synced to NAS via the existing `~/deploy-circulation.sh` script. The Synology PHP 8.2 package includes `zip`, `xml`, and `mbstring` extensions that PhpSpreadsheet requires.

---

## Sources

- [Packagist: phpoffice/phpspreadsheet](https://packagist.org/packages/phpoffice/phpspreadsheet) — latest version 5.5.0, PHP ^8.1 requirement confirmed
- [PhpSpreadsheet docs: Cell fill styling](https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/) — `applyFromArray` with fill.fillType and fill.startColor.rgb verified
- [SheetJS Pro](https://sheetjs.com/pro/) — "Cell, column, table and worksheet-level styling" confirmed in Pro Basic; `.s` property in use in project's `export-utils.js`
- [docs/MYCOMMPILOT-INTEGRATION.md](../MYCOMMPILOT-INTEGRATION.md) — Working PHP scraper class, auth flow, and cURL pattern verified against live portal (HIGH confidence)
- [Chart.js bar chart docs](https://www.chartjs.org/docs/latest/charts/bar.html) — `barPercentage`, `categoryPercentage`, `ticks.maxRotation` options verified
- [GitHub issue #2528 PHPOffice/PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet/issues/2528) — fill color bugs in older versions, reason to use 5.x

---

_Stack research for: NWDownloads v2.1 — Call Integration & Dashboard Enhancements_
_Researched: 2026-03-20_
