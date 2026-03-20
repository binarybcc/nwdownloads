# NWDownloads Circulation Dashboard

A newspaper circulation tracking system for monitoring subscriber metrics across multiple business units and publications. Runs natively on Synology NAS with Apache, PHP 8.2, and MariaDB 10.

## Overview

This dashboard tracks daily circulation metrics for newspaper publications across Wyoming, Michigan, and South Carolina. It processes weekly CSV exports from Newzware's circulation management system and provides real-time metrics, trends, and year-over-year comparisons.

**Key Features:**

- Real-time subscriber counts by publication and business unit
- Weekly data import via CSV upload (UPSERT system)
- Year-over-year trend analysis
- Delivery type breakdown (Mail, Carrier, Digital)
- Vacation tracking

## ✨ Code Quality (December 2025)

**Professional code quality achieved through comprehensive 3-phase cleanup:**

**Phase 1: Critical File Organization**

- ✅ 8 backup/test/debug files moved from production to archive/tests
- ✅ Zero backup files in production directories
- ✅ Proper .gitignore configuration for development artifacts

**Phase 2: Code Standards & Analysis**

- ✅ **PHP**: PHPStan level 5 with **0 errors** (13,786 lines analyzed)
- ✅ **JavaScript**: ESLint with **0 errors**, 18 warnings (8,110 lines analyzed)
- ✅ **Python**: Black + isort formatting (2,304 lines formatted)
- ✅ PSR-12 compliance for all PHP code
- ✅ Installed tools: PHPStan, PHPCS, PHP-CS-Fixer, ESLint, Prettier, Black, isort, mypy, Pylint

**Phase 3: Structural Improvements**

- ✅ **JavaScript modularized** into 5 logical categories (core, components, charts, features, utils)
- ✅ **API foundation established** with shared modules (database, response, utils)
- ✅ 17 JavaScript files reorganized with clear dependency hierarchy
- ✅ Backward compatibility maintained (all global functions preserved)

**Metrics:**

- **Lines of Code**: PHP 13,786 | JavaScript 8,110 | Python 2,304
- **Code Quality**: PHPStan 0 errors | ESLint 0 errors
- **Documentation**: 115+ README files across project

See `/caudit/CODE_QUALITY_AUDIT_AND_CLEANUP_PLAN.md` for complete audit plan and execution details.

## 🗂️ Publications Tracked

### Wyoming Business Unit

- **TJ** (The Journal) - Print: Wed/Sat, Digital: Tue-Sat
- **TR** (The Ranger) - Print: Wed/Sat, Digital: Wed/Sat
- **LJ** (The Lander Journal) - Print: Wed/Sat, Digital: Wed/Sat
- **WRN** (Wind River News) - Print: Thu, Digital: Thu

### Michigan Business Unit

- **TA** (The Advertiser) - Print: Wed only

### South Carolina Business Unit

- **TJ** (The Journal) - Print: Wed/Sat, Digital: Tue-Sat

## Quick Start

### Prerequisites

- SSH access to Synology NAS (`ssh nas`)
- GitHub repo access

### Setup

1. **Clone the repository on NAS:**

```bash
ssh nas
cd /volume1/homes/it
git clone https://github.com/binarybcc/nwdownloads.git circulation-deploy
```

2. **Run deploy script:**

```bash
~/deploy-circulation.sh
```

3. **Access the dashboard:**

```
https://cdash.upstatetoday.com
```

4. **Upload data:**

- Navigate to: https://cdash.upstatetoday.com/upload_unified.php
- Upload your Newzware "All Subscriber Report" CSV
- View updated metrics on the dashboard

## Tech Stack

- **Backend:** PHP 8.2
- **Database:** MariaDB 10 (native on Synology NAS)
- **Web Server:** Apache (Synology Web Station)
- **Frontend:** Vanilla JavaScript (no frameworks)

## 📁 Project Structure

```
nwdownloads/
├── web/                              # PHP application
│   ├── api/                         # API endpoints (modular structure)
│   │   ├── legacy.php              # Monolithic API (migration in progress)
│   │   └── shared/                 # Shared modules
│   │       ├── database.php        # Database connection
│   │       ├── response.php        # JSON response helpers
│   │       └── utils.php           # Utility functions
│   ├── api.php                      # API router (routes to api/legacy.php)
│   ├── upload.php                   # CSV upload handler
│   ├── index.php                    # Dashboard UI
│   └── assets/
│       └── js/                      # Modular JavaScript structure
│           ├── core/                # Dashboard initialization
│           ├── components/          # Reusable UI components
│           ├── charts/              # Chart visualization
│           ├── features/            # Feature modules
│           └── utils/               # Utility functions
├── database/                         # Database management
│   ├── init/                        # Initialization scripts
│   ├── migrations/                  # Schema migrations (SQL + PHP)
│   └── seeds/                       # Seed data
├── docs/                            # Comprehensive documentation
│   ├── KNOWLEDGE-BASE.md            # Complete system reference
│   ├── TROUBLESHOOTING.md           # Decision tree diagnostics
│   └── DESIGN-SYSTEM.md             # Component patterns
├── tests/                           # Testing infrastructure
│   ├── Legacy/                      # Archived test scripts
│   └── Debug/                       # Debug utilities
├── archive/                         # Historical files
│   └── web-backups/                # Timestamped backups
├── caudit/                          # Code quality audit
│   ├── CODE_QUALITY_AUDIT_AND_CLEANUP_PLAN.md
│   ├── cleanup-phase1.sh           # Automated cleanup script
│   ├── .eslintrc.json              # ESLint configuration
│   ├── .php-cs-fixer.php           # PHP CS Fixer config
│   └── .prettierrc                 # Prettier configuration
├── eslint.config.js                 # ESLint v9 flat config
└── .env.credentials.example         # Environment template
```

## Weekly Data Upload Process

### Step 1: Export from Newzware

Run the "All Subscriber Report" query and export as CSV.

### Step 2: Upload to Dashboard

1. Open https://cdash.upstatetoday.com/upload_unified.php
2. Select the CSV file
3. Click "Upload and Process Data"
4. Wait 10-30 seconds for processing

### Step 3: Review Results

The system will show:

- New records added
- Existing records updated
- Total subscribers by business unit
- Processing time

**UPSERT System:**

- New snapshots are automatically inserted
- Existing snapshots are updated with latest counts
- Data from 2025+ only (pre-2025 data excluded due to rate system change)

## 🗄️ Database Schema

### `daily_snapshots` Table

Primary table storing daily circulation metrics:

| Column             | Type         | Description                       |
| ------------------ | ------------ | --------------------------------- |
| `snapshot_date`    | DATE         | Date of snapshot                  |
| `paper_code`       | VARCHAR(10)  | TJ, TA, TR, LJ, WRN, FN           |
| `paper_name`       | VARCHAR(100) | Full publication name             |
| `business_unit`    | VARCHAR(50)  | Wyoming, Michigan, South Carolina |
| `total_active`     | INT          | Total active subscribers          |
| `deliverable`      | INT          | Subscribers not on vacation       |
| `mail_delivery`    | INT          | Mail delivery count               |
| `carrier_delivery` | INT          | Carrier delivery count            |
| `digital_only`     | INT          | Digital-only subscribers          |
| `on_vacation`      | INT          | Subscribers on vacation           |

**Primary Key:** `(snapshot_date, paper_code)` - Enables UPSERT operations

## Production Deployment (Synology NAS)

### Prerequisites

- Synology NAS with SSH enabled
- PHP 8.2 + Apache via Web Station
- MariaDB 10 package installed

### Deployment Steps

1. **SSH into Synology NAS:**

```bash
ssh nas
```

2. **Run deploy script:**

```bash
~/deploy-circulation.sh
```

3. **Access production dashboard:**

```
https://cdash.upstatetoday.com
```

### Synology-Specific Notes

- Web files at `/volume1/web/circulation/`
- DB via Unix socket: `/run/mysqld/mysqld10.sock`
- DB binary: `/usr/local/mariadb10/bin/mysql`
- PHP CLI: `/var/packages/PHP8.2/target/usr/local/bin/php82`

## Common Tasks

### View Logs

```bash
ssh nas
tail -f /volume1/web/circulation/error.log
```

### Database Access

```bash
ssh nas
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard
```

### Check Latest Data

```bash
ssh nas
/usr/local/mariadb10/bin/mysql -uroot -p -S /run/mysqld/mysqld10.sock circulation_dashboard -e "
  SELECT snapshot_date, paper_code, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC
  LIMIT 10;
"
```

### Redeploy

```bash
ssh nas
~/deploy-circulation.sh
```

## 📊 Data Notes

### 2025 Data Reset

- **All pre-2025 data was deleted** (December 2, 2025)
- Reason: Rate system changed January 2025, making 2024 data incomplete
- Current data range: January 4, 2025 onwards
- **2026 will be the first year** with valid year-over-year comparisons
- See: `/docs/data-cleanup-2025-12-02.md` for details

### Recommended Upload Schedule

- **Weekly uploads every Saturday morning**
- Aligns with publication schedules (Wed/Sat print days)
- Provides consistent week-over-week trends

## Security

**Sensitive files (excluded from git):**

- `.env.credentials` - Environment credentials
- `.env.*.credentials` - Environment-specific credentials
- `.passwords` - Password storage
- `AllSubscriberReport*.csv` - Subscriber data

**Always:**

- Change default database passwords in production
- Use strong passwords (min 16 characters)
- Restrict database access to localhost
- Keep `.env` files outside web root

## Contributing

This is a private circulation tracking system. For internal modifications:

1. Make changes locally
2. Test thoroughly
3. Push to GitHub and deploy to NAS via `~/deploy-circulation.sh`
4. Never make changes directly in production

## Environment

- **Production** = Synology NAS (`https://cdash.upstatetoday.com` / `192.168.1.254:8081`)

## 📚 Documentation

**Comprehensive Documentation:**

**Primary References:**

- `/docs/KNOWLEDGE-BASE.md` - Complete system reference (architecture, database, API, deployment)
- `/docs/TROUBLESHOOTING.md` - Decision tree diagnostics for 9 common issue categories
- `/docs/DESIGN-SYSTEM.md` - Component library and UI patterns
- `/docs/cost_analysis.md` - Real-world development cost analysis

**Code Organization:**

- `/database/migrations/README.md` - Database migration system (SQL + PHP)
- `/database/init/README.md` - Database initialization scripts
- `/web/assets/js/README.md` - JavaScript modular architecture
- `/web/api/README.md` - API endpoints and shared modules
- `/tests/README.md` - Testing structure and guidelines
- `/archive/README.md` - Archive policy and historical files

**Code Quality:**

- `/caudit/CODE_QUALITY_AUDIT_AND_CLEANUP_PLAN.md` - Complete audit plan and results

**Historical Documentation:**

- `/docs/ARCHIVE/` - 33+ archived markdown files
- `/docs/DEPLOYMENT-2025-12-07.md` - Recent deployment notes

## 📄 License

Internal use only - proprietary newspaper circulation tracking system.

## Acknowledgments

Built for tracking circulation metrics across multiple newspaper publications, deployed natively on Synology NAS.

---

**Last Updated:** December 16, 2025
**Repository:** https://github.com/binarybcc/nwdownloads
**Code Quality:** ✅ PHPStan Level 5 (0 errors) | ✅ ESLint (0 errors) | ✅ Black Formatted
