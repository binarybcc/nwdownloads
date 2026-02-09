# Codebase Structure

**Analysis Date:** 2026-02-09

## Directory Layout

```
/Users/johncorbin/Desktop/projs/nwdownloads/
├── web/                           # PHP application and frontend (deployed to production)
│   ├── api/                        # API endpoints (monolithic + modular transition)
│   │   ├── legacy.php              # Monolithic API (2,583 lines) - all endpoints until migration
│   │   ├── shared/                 # Shared modules
│   │   │   ├── database.php        # Database connection helper
│   │   │   ├── response.php        # JSON response utilities
│   │   │   └── utils.php           # General utilities
│   │   ├── endpoints/              # Future modular endpoints (currently empty)
│   │   ├── functions/              # Future extracted functions (currently empty)
│   │   ├── cache_management.php    # Cache utilities
│   │   ├── api_backup_restore.php  # Backup/restore endpoints
│   │   ├── revenue_intelligence.php # Revenue analysis endpoints
│   │   └── README.md               # API migration notes
│   ├── assets/                     # CSS, JS, images
│   │   ├── js/
│   │   │   ├── core/               # Core functionality
│   │   │   │   ├── app.js          # Main dashboard state & rendering
│   │   │   │   └── churn_dashboard.js  # Churn analysis view
│   │   │   ├── charts/             # Chart.js integrations
│   │   │   │   ├── chart-context-integration.js
│   │   │   │   ├── chart-layout-manager.js
│   │   │   │   └── donut-to-state-animation.js
│   │   │   ├── components/         # Reusable components
│   │   │   │   ├── detail_panel.js         # Drill-down detail view
│   │   │   │   ├── subscriber-table-panel.js  # Subscriber table display
│   │   │   │   ├── context-menu.js        # Right-click menu
│   │   │   │   ├── trend-slider.js        # Timeline slider
│   │   │   │   ├── publication-revenue-detail.js
│   │   │   │   └── revenue-opportunity-table.js
│   │   │   ├── features/           # Feature modules
│   │   │   │   ├── vacation-display.js
│   │   │   │   ├── revenue-intelligence.js
│   │   │   │   └── backfill-indicator.js
│   │   │   └── utils/              # Utilities
│   │   │       ├── export-utils.js # CSV/Excel/PDF export
│   │   │       ├── state-icons.js  # Status indicators
│   │   │       └── ui-enhancements.js
│   │   ├── css/
│   │   │   └── output.css          # Tailwind compiled (21KB optimized)
│   │   └── images/                 # Static images
│   ├── lib/                        # Shared importers (not OOP framework)
│   │   ├── AllSubscriberImporter.php    # All Subscriber Report parser & importer
│   │   ├── VacationImporter.php         # Vacation data importer
│   │   ├── RenewalImporter.php          # Churn/renewal tracking importer
│   │   └── RatesImporter.php            # Rate plan importer
│   ├── processors/                 # File processor plugins (Strategy pattern)
│   │   ├── IFileProcessor.php      # Interface for all processors
│   │   ├── ProcessResult.php       # Result object
│   │   ├── AllSubscriberProcessor.php   # Processes All Subscriber CSV
│   │   ├── VacationProcessor.php        # Processes vacation CSV
│   │   ├── RenewalProcessor.php         # Processes renewal data
│   │   └── RatesProcessor.php           # Processes rate data
│   ├── includes/                   # Legacy includes (being deprecated)
│   │   ├── database.php            # Database connection (getDatabase singleton)
│   │   └── import-helpers.php      # Import utilities
│   ├── notifications/              # Notification system
│   │   ├── INotifier.php           # Interface for notifiers
│   │   ├── DashboardNotifier.php   # In-app notification alerts
│   │   └── EmailNotifier.php       # Email notification handler
│   ├── index.php                   # Main dashboard (58KB, entry point)
│   ├── api.php                     # API router (delegates to legacy.php)
│   ├── auth_check.php              # Authentication guard (include on all protected pages)
│   ├── config.php                  # Configuration (timezone, session security, Newzware API)
│   ├── login.php                   # Login page (Newzware authentication)
│   ├── logout.php                  # Logout handler
│   ├── upload_unified.php          # Unified upload interface (Subscribers/Vacations/Renewals tabs)
│   ├── upload.php                  # Legacy upload (redirects to upload_unified.php)
│   ├── upload_vacations.php        # Legacy vacation upload
│   ├── upload_renewals.php         # Renewal/churn upload
│   ├── settings.php                # Settings/configuration page
│   ├── rates.php                   # Rate plan management
│   ├── churn_dashboard.php         # Churn analysis dashboard
│   ├── backup.php                  # Data backup/restore
│   ├── admin_audit.php             # Admin audit log viewer
│   ├── process-inbox.php           # File processing orchestrator (cron-triggered)
│   ├── file_processing.php         # File processing utilities
│   ├── SimpleCache.php             # Cache utilities
│   ├── version.php                 # Version information
│   └── .htaccess                   # Apache rewrite rules (production)
│
├── database/                       # Database initialization & migrations
│   ├── init/                       # Database initialization SQL
│   │   ├── 01_initial_data.sql     # Initial users, papers, business units
│   │   ├── 02_subscriber_snapshots.sql  # Subscriber snapshot table
│   │   ├── 03_add_week_columns.sql # Week number, year columns
│   │   └── 04_add_source_tracking_columns.sql  # Source tracking
│   ├── migrations/                 # Version-controlled migrations
│   │   ├── 000_create_migrations_table.sql
│   │   ├── 001_initial_schema.sql
│   │   └── ... (additional migrations)
│   └── seeds/                      # Test data seeds
│
├── docs/                           # Documentation
│   ├── KNOWLEDGE-BASE.md           # Comprehensive system reference
│   ├── DESIGN-SYSTEM.md            # Component library & UI patterns
│   ├── TROUBLESHOOTING.md          # Troubleshooting decision trees
│   ├── DEPLOYMENT-*.md             # Deployment guides
│   └── ARCHIVE/                    # Historical documentation (33+ files)
│
├── .planning/                      # GSD codebase analysis output
│   └── codebase/                   # Analysis documents
│       ├── ARCHITECTURE.md         # This file's companion
│       ├── STRUCTURE.md            # This file
│       ├── STACK.md                # Technology stack (if generated)
│       └── CONVENTIONS.md          # Coding conventions (if generated)
│
├── docker-compose.yml              # Development configuration (volume mounts)
├── docker-compose.prod.yml         # Production configuration
├── Dockerfile                      # Web container image
│
├── .env.example                    # Environment template
├── .env.credentials.example        # Credentials template
│
├── package.json                    # Node.js dependencies (for CLI tools)
├── composer.json                   # PHP dependencies
├── composer.lock                   # Locked PHP versions
│
├── .eslintrc.json                  # JavaScript linting rules
├── .prettierrc                     # Code formatting (JavaScript)
├── .php-cs-fixer.php              # PHP code style fixer
│
├── README.md                       # Project overview
├── QUICK_START.md                  # Quick start guide
├── CHANGELOG.md                    # Version history
│
├── hotfolder/                      # File upload staging area
│   └── dashboard_v2/               # V2 upload directory
│
├── tests/                          # Test suites
│   ├── Unit/                       # Unit tests
│   ├── Integration/                # Integration tests
│   ├── ui/                         # UI tests
│   └── Debug/                      # Debug test utilities
│
├── scripts/                        # Utility scripts
│   └── run-migrations.sh           # Database migration runner
│
└── .github/                        # GitHub workflows
    └── workflows/                  # CI/CD configurations
```

## Directory Purposes

**web/**

- **Purpose:** Entire deployed application (what gets served to users)
- **Contains:** PHP pages, API endpoints, JavaScript frontend, CSS assets
- **Key files:** `index.php` (entry), `api.php` (API router), `config.php` (configuration)
- **Deployment:** Entire directory synced to production `/volume1/web/circulation/`

**web/api/**

- **Purpose:** RESTful API implementation
- **Contains:** Monolithic `legacy.php` with 2,583 lines of endpoint handlers
- **In transition:** Gradual migration to modular `endpoints/` and `functions/` directories
- **Currently:** All action handlers in one file (backward compatible)
- **Future:** Individual files per endpoint with shared utilities

**web/assets/**

- **Purpose:** Client-side code and styles
- **Contains:** JavaScript modules (core, components, charts, features, utils) and compiled CSS
- **Load order:** Core modules first (app.js), then features (detail_panel.js, etc.)
- **CSS:** Tailwind compiled output (21KB, optimized)

**web/lib/**

- **Purpose:** Shared importers used by both manual upload and automated processing
- **Contains:** CSV parsers and database insertion logic (UPSERT pattern)
- **Reusability:** Used by both `upload_unified.php` and `AllSubscriberProcessor.php`
- **Pattern:** Not an OOP framework, just shared functions

**web/processors/**

- **Purpose:** Plugin-style file processing handlers
- **Pattern:** Implements `IFileProcessor` interface for auto-discovery
- **Usage:** `process-inbox.php` scans directory, instantiates processors, calls validate() then process()
- **Extensibility:** Add new processor, implement interface, system auto-discovers on next run

**web/includes/**

- **Purpose:** Legacy shared code (being phased out)
- **Contains:** Database connection helper, import utilities
- **Migration:** Being replaced by `api/shared/` and `lib/` modules

**web/notifications/**

- **Purpose:** Notification system abstraction
- **Pattern:** Strategy pattern with `INotifier` interface
- **Implementations:** `DashboardNotifier` (in-app), `EmailNotifier` (SMTP)

**database/init/**

- **Purpose:** Database initialization (runs on Docker startup)
- **Order:** Files execute alphabetically, migrations depend on prior tables
- **Idempotent:** `IF NOT EXISTS` clauses prevent errors on re-run

**database/migrations/**

- **Purpose:** Version-controlled schema changes
- **Usage:** Applied manually or via `scripts/run-migrations.sh`
- **Tracking:** `schema_migrations` table records applied migrations
- **Safety:** Only applied once per environment

**docs/**

- **Purpose:** Complete system documentation
- **Key files:** KNOWLEDGE-BASE.md (comprehensive), TROUBLESHOOTING.md (decision trees)
- **ARCHIVE/:** Historical files kept for reference (33+ markdown files)

**tests/**

- **Purpose:** Test suites organized by type
- **Contains:** Unit tests, integration tests, UI tests, debug utilities
- **Configuration:** Jest/Vitest config in project root

## Key File Locations

**Entry Points:**

- `web/index.php`: Main dashboard (58KB, all HTML/CSS/JS in one file currently)
- `web/api.php`: API router (simple 22-line delegator to legacy.php)
- `web/login.php`: Authentication entry point
- `web/upload_unified.php`: Data upload interface

**Configuration:**

- `web/config.php`: Timezone, session security, Newzware API URL, session timeout
- `.env`: Environment variables (DB_HOST, DB_USER, DB_PASSWORD, etc.)
- `.env.credentials`: Deployment credentials (gitignored, must be created manually)
- `docker-compose.yml`: Development environment configuration
- `docker-compose.prod.yml`: Production Docker image configuration

**Core Logic:**

- `web/api/legacy.php`: All API endpoints (2,583 lines of functions + main switch statement)
- `web/lib/AllSubscriberImporter.php`: CSV parsing and database insertion logic
- `web/assets/js/core/app.js`: Frontend state management and dashboard rendering
- `web/auth_check.php`: Authentication guard (required on protected pages)

**Testing:**

- `tests/Unit/`: Unit test files
- `tests/Integration/`: Integration test files
- `web/test-process-inbox.php`: Manual testing of file processing

**Database:**

- `database/init/01_initial_data.sql`: Base schema with tables
- `database/init/02_subscriber_snapshots.sql`: Subscriber-level snapshot table
- `database/migrations/`: Version-controlled schema changes

## Naming Conventions

**Files:**

- **PHP files:** `camelCase.php` or `kebab-case.php` for utilities
  - Entry points: `index.php`, `login.php`, `api.php` (lowercase)
  - Classes: `AllSubscriberImporter.php`, `ProcessResult.php` (PascalCase filename)
  - Utilities: `auth_check.php`, `file_processing.php` (snake_case)

- **JavaScript files:** `kebab-case.js` in feature-organized structure
  - Core modules: `core/app.js`, `core/churn_dashboard.js`
  - Components: `components/detail-panel.js`, `components/context-menu.js`
  - Utilities: `utils/export-utils.js`, `utils/state-icons.js`

- **CSS files:** `output.css` (Tailwind compiled output)

- **Database:** Snake_case for tables and columns
  - Tables: `daily_snapshots`, `subscriber_snapshots`, `schema_migrations`
  - Columns: `snapshot_date`, `paper_code`, `business_unit`, `total_active`

- **SQL migrations:** `[number]_description.sql`
  - Format: `000_create_migrations_table.sql`, `001_initial_schema.sql`
  - Auto-numbered to ensure execution order

**Directories:**

- **Functional grouping:** `api/`, `lib/`, `processors/`, `assets/`, `includes/`, `notifications/`
- **Feature organization (js):** `core/`, `components/`, `charts/`, `features/`, `utils/`
- **Database organization:** `init/`, `migrations/`, `seeds/`

## Where to Add New Code

**New Feature (Dashboard Metric):**

1. **Backend:**
   - Add function to `web/api/legacy.php` (e.g., `getNewMetric($pdo, $params)`)
   - Register action in main switch statement around line 2450
   - Example: `case 'new_metric': $data = getNewMetric($pdo, $params);`

2. **Frontend:**
   - Add chart rendering function to `web/assets/js/core/app.js` or new component file
   - Call API from `loadDashboardData()` with new action parameter
   - Render using Chart.js or HTML template

3. **Tests:**
   - Add test to `tests/Unit/` for backend function
   - Add test to `tests/ui/` for frontend rendering

**New Component/Module (e.g., Analytics Panel):**

1. **File location:** `web/assets/js/components/analytics-panel.js`
2. **Pattern:** IIFE with methods, exported to CircDashboard namespace
3. **Integration:** Include script tag in `index.php`, initialize in `app.js`
4. **Styling:** Add to `src/input.css`, rebuild Tailwind CSS

**New File Processor (e.g., Email Importer):**

1. **File location:** `web/processors/EmailProcessor.php`
2. **Implementation:**
   - Implement `IFileProcessor` interface
   - Methods: `getName()`, `getDefaultPatterns()`, `getFileType()`, `validate()`, `process()`
   - Return `ProcessResult` with statistics
3. **Auto-discovery:** `process-inbox.php` finds it automatically on next run
4. **Configuration:** User configures filename patterns in Settings page

**New API Endpoint (e.g., Advanced Analytics):**

1. **Current approach (short-term):** Add to `web/api/legacy.php`
   - Function: `function getAdvancedAnalytics($pdo, $params): array`
   - Handler: Add case in main switch (line 2450): `case 'advanced_analytics':`
   - Call: `api.php?action=advanced_analytics&params`

2. **Future approach (modular):** Create `web/api/endpoints/advanced-analytics.php`
   - File contains single action handler
   - Router delegates: `require 'api/endpoints/' . $action . '.php';`

**Utilities:**

- **Shared utilities:** `web/includes/database.php` (singleton connection)
- **Export utilities:** `web/assets/js/utils/export-utils.js` (CSV, PDF, Excel)
- **UI helpers:** `web/assets/js/utils/ui-enhancements.js` (formatting, display)

## Special Directories

**hotfolder/**

- **Purpose:** Staging area for file uploads
- **Generated:** Yes (by upload form)
- **Committed:** No (empty placeholder in git)
- **Lifecycle:** Files moved by `process-inbox.php` to `processing/`, then `archive/` or `failed/`

**archive/** (root)

- **Purpose:** Historical code and documentation backups
- **Generated:** No (manually maintained)
- **Committed:** Yes (for reference)
- **Contains:** Old deployments, old tests, old analysis scripts

**backup-\*/** (root)

- **Purpose:** Pre-migration backups (old structure)
- **Generated:** Yes (manual creation during schema changes)
- **Committed:** No (should be in separate backup location)
- **Cleanup:** Safe to delete after verification

**.planning/codebase/**

- **Purpose:** GSD codebase analysis documents
- **Generated:** Yes (by /gsd:map-codebase command)
- **Committed:** No (for orchestrator use only)
- **Contents:** ARCHITECTURE.md, STRUCTURE.md, STACK.md, CONVENTIONS.md, CONCERNS.md

**vendor/** and **node_modules/**

- **Purpose:** Dependency packages (Composer PHP, npm JavaScript)
- **Generated:** Yes (from lock files)
- **Committed:** No (lock files only: composer.lock, package-lock.json)

**tests/**

- **Purpose:** Test suites
- **Committed:** Yes
- **Organization:** By type (Unit, Integration, ui) and feature
- **Execution:** Via Jest/Vitest with config in project root

---

_Structure analysis: 2026-02-09_
