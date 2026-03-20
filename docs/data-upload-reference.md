# Data Upload Reference

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## Upload Interface

**CANONICAL URL:** `upload_unified.php`

- Production: https://cdash.upstatetoday.com/upload_unified.php
- Also accessible at: http://192.168.1.254:8081/upload_unified.php
- `upload.html` redirects here - do not use directly

## Weekly Upload Process

### 1. Export from Newzware

- Run "All Subscriber Report" in Ad-Hoc Query Builder
- Export as CSV: `AllSubscriberReportYYYYMMDDHHMMSS.csv`

### 2. Upload to Dashboard

1. Open upload_unified.php
2. Select file type (Subscribers or Vacations tab)
3. Choose CSV file
4. Click "Upload and Process Data"
5. Wait 10-30 seconds (~8,000 rows)
6. Review import summary
7. Click "View Dashboard"

### UPSERT System

- **New snapshots**: Inserted automatically
- **Existing snapshots**: Updated with latest counts
- **Date filter**: Only imports data from January 1, 2025 onwards
- **Safe**: Never deletes data

### Recommended Schedule

Every Saturday morning - aligns with Wed/Sat print days, captures full week.

## What Gets Imported

**From CSV columns:**
| Column | Values |
|--------|--------|
| Ed (Paper Code) | TJ, TA, TR, LJ, WRN, FN |
| DEL (Delivery Type) | MAIL, CARR, INTE |
| Zone (Vacation Status) | VAC indicators |

**Calculated metrics:**
| Metric | Definition |
|--------|-----------|
| `total_active` | Count of all subscribers |
| `mail_delivery` | MAIL delivery subscribers |
| `carrier_delivery` | CARR delivery subscribers |
| `digital_only` | INTE delivery subscribers |
| `on_vacation` | Subscribers with VAC in zone |
| `deliverable` | total_active minus on_vacation |

## Publications Tracked

| Code | Name               | BU           | Print             | Digital |
| ---- | ------------------ | ------------ | ----------------- | ------- |
| TJ   | The Journal        | Wyoming & SC | Wed/Sat           | Tue-Sat |
| TR   | The Ranger         | Wyoming      | Wed/Sat           | Wed/Sat |
| LJ   | The Lander Journal | Wyoming      | Wed/Sat           | Wed/Sat |
| WRN  | Wind River News    | Wyoming      | Thu               | Thu     |
| TA   | The Advertiser     | Michigan     | Wed               | None    |
| FN   | Former News        | N/A          | Sold/discontinued | N/A     |

## Database Schema

**daily_snapshots Table:**

```sql
PRIMARY KEY (snapshot_date, paper_code)  -- Enables UPSERT
- snapshot_date: DATE
- paper_code: VARCHAR(10)
- paper_name: VARCHAR(100)
- business_unit: VARCHAR(50)
- total_active, deliverable, mail_delivery, carrier_delivery, digital_only, on_vacation: INT
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

**Other tables:** `publication_schedule` - Print/digital publication days by paper

## Data Notes

- All pre-2025 data deleted (rate system changed Jan 2025)
- Current range: Jan 4, 2025 onwards
- 2026 will be first year with valid year-over-year comparisons

## Troubleshooting

| Error                                                | Solution                                                                                  |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| "CSV does not appear to be an All Subscriber Report" | Use "All Subscriber Report" query, not individual exports. Required columns: Ed, ISS, DEL |
| "No valid data found"                                | Check report includes active subscribers. Pre-2025 data filtered out                      |
| Import seems slow                                    | Normal: 10-30s for ~8K rows. Max file size: 10MB                                          |
| Numbers look wrong                                   | Compare upload summary to expected counts; check BU breakdowns                            |
