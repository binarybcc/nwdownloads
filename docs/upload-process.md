# Weekly Data Upload Process

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## Overview

The dashboard uses an **UPSERT** (Update or Insert) system for importing weekly circulation data from Newzware's "All Subscriber Report".

**How UPSERT Works:**

- **New snapshots**: Automatically inserted into database
- **Existing snapshots**: Updated with latest subscriber counts
- **Date filter**: Only imports data from January 1, 2025 onwards
- **Safe operation**: Never deletes data, only adds or updates

## Step-by-Step Upload Process

### 1. Export Data from Newzware

- Run "All Subscriber Report" query in Newzware Ad-Hoc Query Builder
- Export results as CSV
- File saves as: `AllSubscriberReportYYYYMMDDHHMMSS.csv`

### 2. Upload to Dashboard

**Production:** `https://cdash.upstatetoday.com/upload_unified.php`
**Alternate:** `http://192.168.1.254:8081/upload_unified.php`

1. Select file type (Subscribers or Vacations tab)
2. Click "Choose File" and select the CSV
3. Click "Upload and Process Data"
4. Wait 10-30 seconds for processing (~8,000 rows)
5. Review import summary
6. Click "View Dashboard" to see updated data

### What Gets Imported

**From AllSubscriberReport CSV:**

- **Paper Code** (Ed column) - TJ, TA, TR, LJ, WRN, FN
- **Delivery Type** (DEL column) - MAIL, CARR, INTE
- **Vacation Status** (Zone column) - VAC indicators

**Calculated Metrics:**

| Metric             | Source                         |
| ------------------ | ------------------------------ |
| `total_active`     | Count of all subscribers       |
| `mail_delivery`    | Subscribers with MAIL delivery |
| `carrier_delivery` | Subscribers with CARR delivery |
| `digital_only`     | Subscribers with INTE delivery |
| `on_vacation`      | Subscribers with VAC in zone   |
| `deliverable`      | total_active minus on_vacation |

### Upload Results Example

```
Import Successful!
Date Range: 2025-12-02 to 2025-12-02
New Records Added: 5
Existing Records Updated: 0
Total Records Processed: 5
Processing Time: 2.3 seconds

Summary by Business Unit:
South Carolina: 3,106 subscribers (TJ)
Michigan: 2,909 subscribers (TA)
Wyoming: 1,610 subscribers (TR, LJ, WRN)
```

## Weekly Workflow

**Recommended Schedule: Every Saturday Morning**

1. Run All Subscriber Report in Newzware (captures current week)
2. Upload CSV to Production dashboard
3. Verify metrics look correct
4. Review weekly trends on dashboard

**Why Weekly?** Aligns with publication schedules (Wed/Sat print days), provides consistent week-over-week comparison, Saturday captures full week's data.

## Database Schema

**daily_snapshots Table:**

```sql
PRIMARY KEY (snapshot_date, paper_code)  -- Enables UPSERT
- snapshot_date: DATE
- paper_code: VARCHAR(10) (TJ, TA, TR, LJ, WRN, FN)
- paper_name: VARCHAR(100)
- business_unit: VARCHAR(50)
- total_active, deliverable, mail_delivery, carrier_delivery, digital_only, on_vacation: INT
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

## Troubleshooting

| Error                                                | Solution                                                                                  |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| "CSV does not appear to be an All Subscriber Report" | Ensure you're exporting the "All Subscriber Report" query. Required columns: Ed, ISS, DEL |
| "No valid data found"                                | Check report includes active subscribers. Date filter excludes pre-2025 data              |
| Import seems slow                                    | Normal: 10-30 seconds for ~8,000 rows. Files >10MB are rejected                           |
| Numbers look wrong                                   | Verify upload summary matches expected counts. Compare to previous week.                  |
