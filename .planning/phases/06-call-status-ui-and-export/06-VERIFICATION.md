---
phase: 06-call-status-ui-and-export
verified: 2026-03-20T23:00:00Z
status: passed
score: 9/9 must-haves verified
re_verification: false
---

# Phase 06: Call Status UI and Export Verification Report

**Phase Goal:** Circulation staff can see call status for every expiring subscriber in the table, sort by contact status, and export a color-coded spreadsheet
**Verified:** 2026-03-20T23:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #   | Truth                                                                                                                      | Status   | Evidence                                                                                                                                                                                                                                                |
| --- | -------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | API returns call_status, last_call_datetime, and call_agent fields for each subscriber in the expiration list              | VERIFIED | `cl.call_direction as call_status`, `cl.call_timestamp as last_call_datetime`, `cl.source_group as call_agent` appear in all 8 bucket branches of `getExpirationSubscribers()` in `web/api/legacy.php`                                                  |
| 2   | API returns call_data_as_of timestamp showing when call logs were last synced                                              | VERIFIED | `$result['call_data_as_of'] = $callDataAsOf` at line 1421 of `legacy.php`; `call_data_as_of` captured and forwarded in `chart-context-integration.js` lines 276, 293, 309, 316                                                                          |
| 3   | Subscribers with no matching call log get null for all three call fields                                                   | VERIFIED | LEFT JOIN (not INNER JOIN) used in all 8 branches — unmatched rows produce NULL for `cl.*` columns                                                                                                                                                      |
| 4   | When multiple calls exist for a phone, only the most recent within 30 days is returned                                     | VERIFIED | `ROW_NUMBER() OVER (PARTITION BY phone_normalized ORDER BY call_timestamp DESC) as rn` with `AND cl.rn = 1` and `WHERE call_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)` at lines 1441-1447                                                           |
| 5   | Each row in the subscriber table shows a phone icon colored green, orange, or red based on call status                     | VERIFIED | `getCallStatusColor()` returns `border`/`iconBg` based on `call_status`; `&#x1F4DE;` rendered in a circular span with `background: ${statusColor.iconBg}` in `subscriber-table-panel.js` line 447                                                       |
| 6   | Rows are sorted by status priority (red first) with expiration date sub-sort, and the Status header toggles sort direction | VERIFIED | `sortSubscribers()` uses `getStatusSortPriority()` (null=0, received/missed=1, placed=2) with expiration date secondary sort; `handleSortToggle()` flips `this.sortAscending` and re-renders; sort arrow `\u25BC`/`\u25B2` on `data-sort-status` header |
| 7   | Hovering the phone icon shows tooltip with direction, date/time, and agent initials                                        | VERIFIED | `buildCallTooltip()` returns "No contact recorded" for null or "Direction • Date, Time • Agent" format; value HTML-escaped by `escapeHtml()` and set as `title` attribute                                                                               |
| 8   | XLSX export rows have light fill tint matching status color, merged timestamp row, and 3 new columns                       | VERIFIED | `getExportStatusFill()` returns DCFCE7/FEF9C3/FEE2E2; `ws['!merges']` for merged row 1; "Call data as of" string in cell A1; `formatSubscriberDataForExport()` maps 'Call Status', 'Last Contact', 'Agent' columns                                      |
| 9   | Browser shows sync timestamp text above the subscriber table                                                               | VERIFIED | `callDataAsOf` extracted from `data?.call_data_as_of` in `buildPanelHTML()`; rendered as `<p style="font-size: 0.69rem; font-style: italic ...">Call data as of ${syncDateStr} ${syncTimeStr}</p>`                                                      |

**Score:** 9/9 truths verified

---

### Required Artifacts

| Artifact                                             | Expected                                                                               | Status   | Details                                                                                                                                                                                                                                                     |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `web/api/legacy.php`                                 | LEFT JOIN call_logs in getExpirationSubscribers(), call_data_as_of in getSubscribers() | VERIFIED | 8x `cl.call_direction as call_status`, 1x `ROW_NUMBER()`, 1x `COLLATE utf8mb4_general_ci` in JOIN, 1x `call_data_as_of`; PHP lint passes                                                                                                                    |
| `web/assets/js/components/subscriber-table-panel.js` | Status column, left border, tooltip, sort toggle, sync timestamp display               | VERIFIED | Contains `getCallStatusColor()`, `buildCallTooltip()`, `getStatusSortPriority()`, `sortSubscribers()`, `handleSortToggle()`, `attachSortHandler()`, `escapeHtml()`; `border-left: 4px solid`; `data-sort-status`; `data-table-container`; `call_data_as_of` |
| `web/assets/js/utils/export-utils.js`                | Status-based row fills, merged timestamp row, 3 new export columns                     | VERIFIED | `getExportStatusFill()` with DCFCE7/FEF9C3/FEE2E2; "Call data as of" string; `ws['!merges']`; `ws['!views']` (freeze fix); `rowOffset` pattern; `'Call Status'`, `'Last Contact'`, `'Agent'` columns; `window.exportSubscriberList` assigned                |
| `web/assets/js/charts/chart-context-integration.js`  | call_data_as_of captured from API and forwarded to panel and export payload            | VERIFIED | Lines 260-316: `callDataAsOf` variable initialized, captured from both single and aggregated queries, passed into both `data.call_data_as_of` and `exportData.call_data_as_of`                                                                              |
| `web/index.php`                                      | xlsx-js-style@1.2.0 CDN (not xlsx-latest)                                              | VERIFIED | Line 28: `https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js`                                                                                                                                                                             |

---

### Key Link Verification

| From                                            | To                                       | Via                                                            | Status   | Details                                                                                                                                                            |
| ----------------------------------------------- | ---------------------------------------- | -------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --- | -------------------------------------------- | --- | --------------------------------------------------------------------------------------------- |
| `legacy.php getExpirationSubscribers()`         | `call_logs` table                        | LEFT JOIN on phone_normalized with ROW_NUMBER() subquery       | VERIFIED | `ROW_NUMBER() OVER (PARTITION BY phone_normalized ORDER BY call_timestamp DESC)` present; COLLATE fix applied to resolve utf8mb4_unicode_ci vs general_ci mismatch |
| `legacy.php getSubscribers()`                   | `call_logs` table                        | MAX(call_timestamp) for sync timestamp                         | VERIFIED | `$result['call_data_as_of'] = $callDataAsOf` in getSubscribers()                                                                                                   |
| `chart-context-integration.js`                  | `subscriber-table-panel.js`              | `call_data_as_of` passed in `.show()` data and exportData      | VERIFIED | Lines 309 and 316 both include `call_data_as_of: callDataAsOf`                                                                                                     |
| `subscriber-table-panel.js buildTableHTML()`    | API response call_status field           | `sub.call_status` used for color mapping and icon rendering    | VERIFIED | `this.getCallStatusColor(sub.call_status)` at line 427; `this.buildCallTooltip(sub)` at line 428                                                                   |
| `subscriber-table-panel.js buildPanelHTML()`    | API response call_data_as_of             | Sync timestamp displayed above table                           | VERIFIED | `const callDataAsOf = data?.call_data_as_of                                                                                                                        |     | null` at line 214; rendered at lines 233-234 |
| `subscriber-table-panel.js handleExportExcel()` | `export-utils.js exportSubscriberList()` | `syncTimestamp` passed as third argument                       | VERIFIED | `syncTimestamp = this.data.data?.call_data_as_of                                                                                                                   |     | exportPayload?.call_data_as_of               |     | null`at lines 527-528; passed to`exportSubscriberList(exportPayload, 'excel', syncTimestamp)` |
| `export-utils.js exportToExcel()`               | merged timestamp row                     | `options.syncTimestamp` with cell-shifting and `ws['!merges']` | VERIFIED | `hasSyncTimestamp` branch shifts cells down, inserts A1 with "Call data as of", sets `ws['!merges']`                                                               |
| `export-utils.js` freeze pane                   | xlsx-js-style API                        | `ws['!views']` array (not `ws['!freeze']`)                     | VERIFIED | Line 143: `if (!ws['!views']) ws['!views'] = []` — correct API for xlsx-js-style                                                                                   |

---

### Requirements Coverage

| Requirement | Source Plan  | Description                                                                             | Status    | Evidence                                                                                                                             |
| ----------- | ------------ | --------------------------------------------------------------------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| UI-01       | 06-01, 06-02 | Expiration subscriber table shows phone icon column with call status (green/orange/red) | SATISFIED | `getCallStatusColor()` maps placed→green, received/missed→orange, null→red; phone icon rendered with `statusColor.iconBg` background |
| UI-02       | 06-02        | Row background color matches call status for visual scanning                            | SATISFIED | `border-left: 4px solid ${statusColor.border}` on every `<tr>`; XLSX rows filled with DCFCE7/FEF9C3/FEE2E2                           |
| UI-03       | 06-02        | Call status column is sortable (not-contacted first by default)                         | SATISFIED | `this.sortAscending = true` default; null priority=0 sorts first; `data-sort-status` header with click toggle                        |
| UI-04       | 06-02        | Tooltip on phone icon shows last contact date and type                                  | SATISFIED | `buildCallTooltip()` produces "Direction • Date, Time • Agent" or "No contact recorded"; set as `title` attribute                    |
| UI-05       | 06-02        | XLSX export preserves row coloring and call status data                                 | SATISFIED | `getExportStatusFill()` applies fills; 'Call Status', 'Last Contact', 'Agent' columns added to `formatSubscriberDataForExport()`     |
| UI-06       | 06-01, 06-02 | Subscriber list and XLSX export show timestamp of last call log sync                    | SATISFIED | Sync text in panel header (lines 233-234); "Call data as of" merged row 1 in XLSX                                                    |

All 6 requirements satisfied. No orphaned requirements.

---

### Post-Deployment Fix Verification

The following fixes were applied after initial plan execution. All verified present in codebase:

| Fix                                                         | File                                                | Evidence                                                                          |
| ----------------------------------------------------------- | --------------------------------------------------- | --------------------------------------------------------------------------------- |
| COLLATE utf8mb4_general_ci on LEFT JOIN                     | `web/api/legacy.php`                                | Line 1447: `cl.phone_normalized COLLATE utf8mb4_general_ci = ss.phone_normalized` |
| SheetJS library swap to xlsx-js-style@1.2.0                 | `web/index.php`                                     | Line 28: CDN URL references `xlsx-js-style@1.2.0`                                 |
| Freeze pane via ws['!views'] not ws['!freeze']              | `web/assets/js/utils/export-utils.js`               | Lines 143-144: `ws['!views'] = []`                                                |
| call_data_as_of passed through chart-context-integration.js | `web/assets/js/charts/chart-context-integration.js` | Lines 276, 293, 309, 316                                                          |

---

### Anti-Patterns Found

None found. No TODOs, FIXMEs, stub returns, or placeholder implementations detected in the modified files.

---

### Human Verification Required

The following items require a browser session to confirm (automated checks cannot cover visual rendering and interactive behavior):

#### 1. Status column visual rendering

**Test:** Open the dashboard, click any expiration chart bar to open the subscriber table
**Expected:** Status column is leftmost; phone icons are colored (green/orange/red circles); rows have visible 4px left border in matching color
**Why human:** CSS rendering and visual color accuracy cannot be verified by grep

#### 2. Sort toggle interaction

**Test:** Click the "Status" header; observe order change; click again
**Expected:** Default = red (no contact) first. First click = green (placed) first with up-arrow. Second click = red first again with down-arrow.
**Why human:** DOM interaction and re-render cannot be verified statically

#### 3. Tooltip content

**Test:** Hover over a phone icon on a row that has a call record
**Expected:** Native browser tooltip shows "Placed • Mar 18, 2:15 PM • BC" format (or similar)
**Why human:** Tooltip display requires actual mouse hover in browser

#### 4. XLSX export end-to-end

**Test:** Click "Export to Excel" from an open subscriber panel
**Expected:** Row 1 = italic gray "Call data as of YYYY-MM-DD HH:MM" merged across all columns; Row 2 = teal header row; data rows = light green/yellow/red tints per status; last 3 columns = Call Status, Last Contact, Agent; autofilter on Row 2
**Why human:** XLSX file content requires opening the downloaded file in Excel/Numbers

---

### Gaps Summary

No gaps. All automated checks pass. The phase goal is achieved: the API layer delivers call status data for all 8 expiration buckets with collation-safe JOINs; the subscriber table renders the Status column with color-coded icons, 4px borders, sort toggle, tooltip, and sync timestamp; and the XLSX export uses xlsx-js-style for styled output with status-colored rows, a merged timestamp row, and 3 new call data columns. All 5 documented commits verified present in git history.

---

_Verified: 2026-03-20T23:00:00Z_
_Verifier: Claude (gsd-verifier)_
