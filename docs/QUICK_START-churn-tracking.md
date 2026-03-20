# Churn Tracking - Quick Start Implementation

## Files Created

### Database Schema

- `005_create_churn_tables.sql` - Creates `renewal_events` and `churn_daily_summary` tables

### PHP Backend

- `upload_renewals.php` - CSV import handler for renewal churn data

### Frontend

- `upload_renewals.html` - Upload interface for churn CSV files

## Installation Steps

### 1. Create Database Tables (2 minutes)

**Option A: Using MySQL command line**

```bash
mysql -u circ_dash -p circulation_dashboard < 005_create_churn_tables.sql
```

**Option B: On NAS via SSH**

```bash
ssh nas
/usr/local/mariadb10/bin/mysql -u circ_dash -p -S /run/mysqld/mysqld10.sock circulation_dashboard < 005_create_churn_tables.sql
```

**Option C: Using PhpMyAdmin**

1. Open PhpMyAdmin
2. Select `circulation_dashboard` database
3. Go to SQL tab
4. Paste contents of `005_create_churn_tables.sql`
5. Click "Go"

**Verify tables were created:**

```sql
SHOW TABLES LIKE '%renewal%';
-- Should show: renewal_events, churn_daily_summary
```

### 2. Deploy PHP Upload Script (1 minute)

Copy `upload_renewals.php` to your web directory:

```bash
# Deploy to production (via deploy script or manual copy)
ssh nas
cp upload_renewals.php /volume1/web/circulation/
```

**Test the endpoint:**

```bash
curl -X POST https://cdash.upstatetoday.com/upload_renewals.php
# Should return: {"success":false,"error":"Method not allowed..."}
# (This is correct - it means the file is accessible)
```

### 3. Deploy Upload HTML Page (1 minute)

Copy `upload_renewals.html` to the production web directory:

```bash
ssh nas
cp upload_renewals.html /volume1/web/circulation/
```

**Test the page:**
Open browser: `https://cdash.upstatetoday.com/upload_renewals.html`

### 4. Upload Your First Churn File (30 seconds)

1. Open `https://cdash.upstatetoday.com/upload_renewals.html`
2. Click or drag your churn CSV file
3. Click "Upload and Process Data"
4. Wait for success message

**Expected result:**

```
✅ Import Successful
Processing Time: 0.15 seconds
Date Range: 1/2/24
Events Imported: 98
Duplicates Skipped: 0
Regular Subs: 36
Monthly Subs: 60
```

## Testing the Data

### Verify Data Import

```sql
-- Check total events imported
SELECT COUNT(*) as total_events FROM renewal_events;

-- Check by publication
SELECT
    paper_code,
    COUNT(*) as events,
    COUNT(DISTINCT sub_num) as unique_subscribers,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewals,
    SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) as expires
FROM renewal_events
GROUP BY paper_code;

-- Check by subscription type
SELECT
    subscription_type,
    COUNT(*) as events,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewals,
    SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) as expires,
    ROUND(SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as renewal_rate
FROM renewal_events
GROUP BY subscription_type;
```

### Sample Queries

**Daily renewal rates:**

```sql
SELECT
    event_date,
    paper_code,
    COUNT(*) as expiring,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewed,
    ROUND(SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as renewal_rate
FROM renewal_events
GROUP BY event_date, paper_code
ORDER BY event_date DESC;
```

**Subscribers appearing multiple times (monthly recycling detection):**

```sql
SELECT
    sub_num,
    paper_code,
    COUNT(*) as appearances,
    MIN(event_date) as first_event,
    MAX(event_date) as last_event,
    GROUP_CONCAT(DISTINCT subscription_type) as types
FROM renewal_events
GROUP BY sub_num, paper_code
HAVING appearances > 1
ORDER BY appearances DESC
LIMIT 20;
```

**Compare Regular vs Monthly renewal rates:**

```sql
SELECT
    subscription_type,
    COUNT(*) as total_expirations,
    SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) as renewed,
    SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) as stopped,
    ROUND(SUM(CASE WHEN status = 'RENEW' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as renewal_rate,
    ROUND(SUM(CASE WHEN status = 'EXPIRE' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as churn_rate
FROM renewal_events
WHERE subscription_type IN ('REGULAR', 'MONTHLY')
GROUP BY subscription_type;
```

## Troubleshooting

### File upload fails

**Error:** "Could not find header row"

- **Solution:** Check CSV has "Sub ID" column in header

**Error:** "CSV missing required columns"

- **Solution:** Verify CSV has: Sub ID, Stat, Ed., Issue Date

**Error:** "File too large"

- **Solution:** Increase PHP upload limits in php.ini:
  ```ini
  upload_max_filesize = 50M
  post_max_size = 50M
  ```

### No events imported (duplicates_skipped = all rows)

**Cause:** You've already uploaded this exact data

- **Solution:** This is normal! The system prevents duplicate imports
- **Check:** Run `SELECT * FROM renewal_events LIMIT 10` to see existing data

### Database connection error

**Error:** "Access denied for user 'circ_dash'"

- **Solution:** Check database credentials in `upload_renewals.php` line 53
- **Verify:** Credentials match your `.env.credentials` file

## Next Steps

Once you have the full churn CSV file uploaded, you can:

1. **Analyze Monthly Recycling Pattern**
   - Run the "monthly recycling detection" query above
   - See if TJ subscribers appear 12+ times (monthly billing)

2. **Build the Churn Dashboard** (Next phase)
   - API endpoints for churn metrics
   - Charts showing renewal trends
   - Red flag detection

3. **Generate Daily Summaries**
   - Populate `churn_daily_summary` table
   - Pre-calculate renewal rates for faster queries

## Current Status

✅ Database schema created
✅ Upload handler working
✅ Upload interface deployed
⏳ Waiting for full churn CSV file
⏳ Dashboard UI (next phase)
⏳ Analysis API (next phase)

## Questions?

Common issues and solutions in the troubleshooting section above. If you encounter other problems, check:

1. PHP error logs: `/var/log/apache2/error.log`
2. Browser console for JavaScript errors
3. Database logs for connection issues
