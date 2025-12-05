# NWDownloads Circulation Dashboard

A Docker-based newspaper circulation tracking system for monitoring subscriber metrics across multiple business units and publications.

## 📊 Overview

This dashboard tracks daily circulation metrics for newspaper publications across Wyoming, Michigan, and South Carolina. It processes weekly CSV exports from Newzware's circulation management system and provides real-time metrics, trends, and year-over-year comparisons.

**Key Features:**
- 📈 Real-time subscriber counts by publication and business unit
- 📅 Weekly data import via CSV upload (UPSERT system)
- 🔄 Year-over-year trend analysis
- 📱 Delivery type breakdown (Mail, Carrier, Digital)
- 🏖️ Vacation tracking
- 🐳 Fully containerized with Docker

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

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- 2GB free disk space
- Port 8081 available

### Local Development Setup

1. **Clone the repository:**
```bash
git clone https://github.com/binarybcc/nwdownloads.git
cd nwdownloads
```

2. **Create environment file:**
```bash
cp .env.example .env
# Edit .env with your database credentials
```

3. **Start the containers:**
```bash
docker compose up -d
```

4. **Access the dashboard:**
```
http://localhost:8081
```

5. **Upload data:**
- Navigate to: `http://localhost:8081/upload.html`
- Upload your Newzware "All Subscriber Report" CSV
- View updated metrics on the dashboard

## 🐳 Docker Architecture

**Containers:**
- `circulation_web` - PHP 8.2 + Apache web server
- `circulation_db` - MariaDB 10.11 database

**Volumes:**
- `db_data` - Persistent database storage

**Network:**
- `circulation_network` - Internal bridge network

## 📦 Tech Stack

- **Backend:** PHP 8.2
- **Database:** MariaDB 10.11
- **Web Server:** Apache 2.4
- **Frontend:** Vanilla JavaScript (no frameworks)
- **Containerization:** Docker & Docker Compose

## 📁 Project Structure

```
nwdownloads/
├── web/                    # PHP application
│   ├── api.php            # REST API endpoints
│   ├── upload.php         # CSV upload handler
│   ├── index.html         # Dashboard UI
│   └── assets/            # JavaScript and CSS
├── sql/                   # Database schemas
│   ├── 00_create_daily_snapshots.sql
│   ├── 01_create_publication_schedule.sql
│   └── 02_seed_publication_schedule.sql
├── docs/                  # Documentation
├── docker-compose.yml     # Container orchestration
├── Dockerfile            # Web container build
└── .env.example          # Environment template
```

## 📤 Weekly Data Upload Process

### Step 1: Export from Newzware
Run the "All Subscriber Report" query and export as CSV.

### Step 2: Upload to Dashboard
1. Open `http://localhost:8081/upload.html`
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

| Column | Type | Description |
|--------|------|-------------|
| `snapshot_date` | DATE | Date of snapshot |
| `paper_code` | VARCHAR(10) | TJ, TA, TR, LJ, WRN, FN |
| `paper_name` | VARCHAR(100) | Full publication name |
| `business_unit` | VARCHAR(50) | Wyoming, Michigan, South Carolina |
| `total_active` | INT | Total active subscribers |
| `deliverable` | INT | Subscribers not on vacation |
| `mail_delivery` | INT | Mail delivery count |
| `carrier_delivery` | INT | Carrier delivery count |
| `digital_only` | INT | Digital-only subscribers |
| `on_vacation` | INT | Subscribers on vacation |

**Primary Key:** `(snapshot_date, paper_code)` - Enables UPSERT operations

## 🚢 Production Deployment (Synology NAS)

### Prerequisites
- Synology NAS with Docker installed
- SSH access enabled
- Port 8081 available

### Deployment Steps

1. **SSH into Synology NAS:**
```bash
ssh admin@192.168.1.254
```

2. **Create project directory:**
```bash
sudo mkdir -p /volume1/docker/nwdownloads
cd /volume1/docker/nwdownloads
```

3. **Copy files via SCP:**
```bash
# From local machine
scp -r web sql docker-compose.yml Dockerfile .env admin@192.168.1.254:/volume1/docker/nwdownloads/
```

4. **Start containers:**
```bash
sudo /usr/local/bin/docker compose up -d
```

5. **Access production dashboard:**
```
http://192.168.1.254:8081
```

### Synology-Specific Notes
- Use IP addresses instead of hostnames in database connections
- Docker DNS resolution doesn't work reliably on Synology
- Always use `sudo` for Docker commands
- Database container IP may change on restart (check with `docker inspect`)

## 🔧 Common Tasks

### View Container Logs
```bash
docker compose logs -f circulation_web
docker compose logs -f circulation_db
```

### Restart Containers
```bash
docker compose restart
```

### Database Access
```bash
docker exec -it circulation_db mariadb -ucirc_dash -p -D circulation_dashboard
```

### Check Latest Data
```bash
docker exec circulation_db mariadb -ucirc_dash -p'YourPassword' -D circulation_dashboard -e "
  SELECT snapshot_date, paper_code, total_active, deliverable
  FROM daily_snapshots
  ORDER BY snapshot_date DESC
  LIMIT 10;
"
```

### Rebuild Containers
```bash
docker compose down
docker compose up -d --build --force-recreate
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

## 🛡️ Security

**Sensitive files (excluded from git):**
- `.env` - Database credentials
- `.env.*.credentials` - Environment-specific credentials
- `.passwords` - Password storage
- `AllSubscriberReport*.csv` - Subscriber data

**Always:**
- Change default database passwords in production
- Use strong passwords (min 16 characters)
- Restrict database access to localhost
- Keep `.env` files outside web root

## 🤝 Contributing

This is a private circulation tracking system. For internal modifications:

1. Make changes in **Development environment** (local)
2. Test thoroughly
3. Deploy to **Production environment** (Synology NAS)
4. Never make changes directly in production

## 📝 Environment Naming

- **Development** = Local OrbStack deployment (`localhost:8081`)
- **Production** = Synology NAS deployment (`192.168.1.254:8081`)

## 📚 Documentation

See `/docs` directory for:
- `dashboard_architecture.md` - System architecture
- `data-cleanup-2025-12-02.md` - Data cleanup notes
- `PHASE_1_REQUIREMENTS.md` - Original requirements
- `DOCKER_SETUP.md` - Detailed Docker setup guide

## 📄 License

Internal use only - proprietary newspaper circulation tracking system.

## 🙏 Acknowledgments

Built for tracking circulation metrics across multiple newspaper publications with Docker containerization for consistent deployment across development and production environments.

---

**Last Updated:** December 5, 2025
**Repository:** https://github.com/binarybcc/nwdownloads
