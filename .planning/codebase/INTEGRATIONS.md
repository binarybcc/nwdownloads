# External Integrations

**Analysis Date:** 2026-02-09

## APIs & External Services

**Newzware Authentication:**

- Service: Newzware centralized authentication system
- What it's used for: User login and access control
- Endpoint: `https://seneca.newzware.com/authentication/auth70_xml.jsp`
- SDK/Client: cURL (native PHP)
- Auth method: Query parameters (site, login_id, password)
- Response format: XML with authenticated status and usertype
- User type filter: Only `usertype="NW"` granted access
- Implementation: `web/login.php` (lines 49-58)
  - Makes HTTPS cURL request with query parameters
  - Parses XML response with SimpleXMLElement
  - Validates authentication status and user type
  - Creates session with user info from response

**Newzware SFTP Server:**

- Service: Publication data downloads (planned integration)
- Purpose: Retrieve circulation data files
- Hosts configured in `.env.credentials.example`: `SFTP_TJ_HOST`, `SFTP_TJ_USER`, `SFTP_TJ_PASSWORD`
- Status: Configuration prepared, implementation pending

## Data Storage

**Primary Database:**

**MariaDB 10 (Development):**

- Connection: Docker container service `database`
- Host: `database` (Docker DNS)
- Port: 3306 (TCP)
- Database: `circulation_dashboard`
- User: `circ_dash` (from env var DB_USER)
- Password: From env var DB_PASSWORD
- Client: PDO (PHP Data Objects)
- Connection: `web/includes/database.php` - singleton pattern
- DSN format: `mysql:host=database;port=3306;dbname=circulation_dashboard`

**MariaDB 10 (Production):**

- Connection: Native Synology service
- Socket: `/run/mysqld/mysqld10.sock` (Unix domain socket)
- Database: `circulation_dashboard`
- User: `root`
- Password: From `.env.credentials` (PROD_DB_PASSWORD)
- Client: PDO
- DSN format: `mysql:unix_socket=/run/mysqld/mysqld10.sock;dbname=circulation_dashboard`

**File Storage:**

- Local filesystem only (no external object storage)
- Development: Docker volume mount at `./web:/var/www/html`
- Production: Native filesystem at `/volume1/web/circulation/`
- Upload directory: Configurable via `DATA_DIR` environment variable
- Raw CSV uploads: Stored in `raw_uploads` table as backup

**Caching:**

- Custom implementation: `web/SimpleCache.php`
- In-process PHP memory cache (no external service)
- Used by: `web/api/cache_management.php`
- Cache lifecycle: Per-request unless explicitly persisted
- No Redis, Memcached, or external cache service

## Authentication & Identity

**Auth Provider:**

- Service: Newzware (centralized authentication system for Edwards Group Holdings)
- Implementation: Custom
  - Location: `web/login.php` (user login interface)
  - Flow: Form → cURL to Newzware API → XML response → Session
  - Session storage: PHP native sessions (filesystem or Redis depending on config)
  - Session validation: `web/auth_check.php` (required at top of protected pages)
  - Timeout: 2 hours (SESSION_TIMEOUT in `web/config.php`)

**Login Process:**

1. User submits form with login_id and password
2. Brute force check: `web/brute_force_protection.php` limits attempts
3. CSRF token validation
4. cURL request to Newzware API with site, login_id, password
5. Parse XML response for authenticated status and usertype
6. Grant access only if `authenticated="Yes"` AND `usertype="NW"`
7. Create session with $\_SESSION['user'], $\_SESSION['user_type'], $\_SESSION['logged_in']
8. Regenerate session ID to prevent fixation attacks

**Session Security:**

- HTTPOnly: Yes (prevents XSS token theft)
- Secure flag: Yes in production only (HTTPS enforcement)
- SameSite: Strict (CSRF protection)
- Regeneration: On every login
- Timeout: 2 hours of inactivity

**Authorization:**

- Role-based: All authenticated users with usertype="NW" have full access
- No internal role system (all users are equivalent)

## Monitoring & Observability

**Error Tracking:**

- None implemented
- Errors logged to Apache error logs (default)
- Application logs: Custom file-based logging in `web/upload-debug.log`
- Error reporting: set to off in production, on in development

**Logs:**

- File-based logging:
  - `web/upload-debug.log` - CSV upload debugging
  - `web/file_processing.log` - File processing history (database-backed)
  - `web/error.log` - PHP errors (Docker/Synology standard location)
- Log format: Plain text, human-readable
- Log rotation: Not configured
- Log retention: Manual cleanup via `web/purge_all_data.php`

**Monitoring:**

- Health checks: Docker HEALTHCHECK in Dockerfile and docker-compose.yml
  - Development: HTTP GET to `/` checks web container
  - Database: MariaDB healthcheck.sh script
- No external monitoring service (Datadog, New Relic, Sentry, etc.)
- Dashboard includes "Data Range Display" showing available data in UI

## CI/CD & Deployment

**Hosting:**

- Development: Local Docker Compose (OrbStack on macOS)
- Production: Synology NAS running native services

**CI Pipeline:**

- Pre-commit hooks (Husky): ESLint, Prettier, Commitlint validation
- No automated testing pipeline (no Jest, Mocha, PHPUnit)
- No GitHub Actions workflows
- Manual deployment via shell script

**Deployment Process:**

1. Merge changes to master branch on GitHub (via PR)
2. SSH into NAS: `sshpass -p '$SSH_PASSWORD' ssh $SSH_USER@$SSH_HOST`
3. Run deployment script: `~/deploy-circulation.sh`
   - Pulls latest code from GitHub master
   - Syncs `web/` directory to `/volume1/web/circulation/` via rsync
   - Fixes file permissions automatically
   - Preserves production-specific files (`.htaccess`, `.build_number`)
4. Verify at `https://cdash.upstatetoday.com`

**Image Registry:**

- Docker Hub: `binarybcc/nwdownloads-circ` (production image)
- Built and pushed by: `build-and-push.sh` (not automated)
- Used by: Production deployment script pulls latest image

**Database Migrations:**

- Tool: Phinx (`robmorgan/phinx`)
- Config: `phinx.php` (defines development and production environments)
- Location: Migrations in `db/migrations/*.sql`
- Execution:
  - Development: `docker exec circulation_db phinx migrate`
  - Production: Via deployment script before starting services
- Tracking: `phinxlog` table maintains migration history

## Environment Configuration

**Required Environment Variables (Development):**

```
DB_HOST=database
DB_PORT=3306
DB_NAME=circulation_dashboard
DB_USER=circ_dash
DB_PASSWORD=<password>
DB_ROOT_PASSWORD=<root_password>
```

**Required Environment Variables (Production):**

```
DB_SOCKET=/run/mysqld/mysqld10.sock
DB_NAME=circulation_dashboard
DB_USER=root
DB_PASSWORD=<password>
```

**Optional Configuration:**

```
TIMEZONE=America/New_York
EXCLUDE_PAPERS=FN
DASHBOARD_TITLE=Circulation Dashboard
```

**Secrets Location:**

- Development: `.env` file (committed template as `.env.example`)
- Production: `.env.credentials` file (never committed, stored locally)
- Credentials template: `.env.credentials.example` (maps environment variables)

**Build Environment:**

- Node version: `.nvmrc` not present (assumes Node 18+)
- PHP version: Dockerfile pins PHP 8.2-apache
- Composer autoloader: Optimized for production (`optimize-autoloader` in `composer.json`)

## Webhooks & Callbacks

**Incoming Webhooks:**

- None implemented
- File upload endpoints are form-based, not webhook-driven

**Outgoing Webhooks:**

- Email notifications (optional feature, not configured)
  - Location: `web/notifications/EmailNotifier.php`
  - Trigger: File processing success/failure
  - Configuration: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD in `.env`
  - Status: Code exists but not integrated into production workflow

**File Processing Pipeline:**

- Hotfolder monitoring: `process-inbox.php` checks `/var/www/hotfolder/` for CSV files
- Trigger: Manual cron job or webhook (not configured)
- Processing: AllSubscriberImporter, VacationImporter, RenewalImporter classes
- Output: Database updates, email notifications (optional)

## Data Import/Export

**CSV Import:**

- Manual upload via web UI: `web/upload_unified.php`
- Formats supported:
  - Newzware All Subscriber Report (main data source)
  - Vacation data (separate format)
  - Renewal/churn data (separate format)
- Parser: `web/lib/AllSubscriberImporter.php` (handles CSV parsing and validation)
- Date extraction: Filename-based (YYYYMMDDHHMMSS format)
- Backfill logic: Soft backfill (fill missing weeks backward until hitting existing data)
- Validation: Required columns detection, data type checking
- Transaction safety: Database rollback on errors
- Audit trail: Raw CSV stored in `raw_uploads` table

**Excel Export:**

- JavaScript implementation: `web/assets/js/utils/export-utils.js`
- Library: SheetJS (XLSX) via CDN
- Formats: Excel (.xlsx), CSV, PDF
- Trigger: Export button in dashboard header
- Data source: Current dashboard state + API responses

**PDF Export:**

- JavaScript implementation: `web/assets/js/utils/export-utils.js`
- Libraries: jsPDF + html2canvas (both via CDN)
- Rendering: Client-side HTML-to-PDF conversion
- Content: Dashboard charts and tables as visible

---

_Integration audit: 2026-02-09_
