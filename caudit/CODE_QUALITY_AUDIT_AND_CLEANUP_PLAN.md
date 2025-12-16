# Professional Code Quality Audit & Cleanup Plan
## Circulation Intelligence Dashboard Project

**Date:** December 16, 2025  
**Project Type:** Multi-language web application (PHP, JavaScript, Python, SQL)  
**Purpose:** Pre-refactoring code quality assessment and cleanup roadmap

---

## Executive Summary

Your project shows signs of **organic growth** with good documentation practices but needs systematic cleanup before refactoring. Key findings:

✅ **Strengths:**
- Comprehensive documentation (docs/ folder)
- Archive strategy in place
- Docker containerization
- Migration system exists
- Active maintenance (recent files)

⚠️**Critical Issues:**
- Duplicate/redundant files (multiple index.php, api.php versions)
- Inconsistent file organization
- Mixed concerns in web/ directory
- Test files scattered across multiple locations
- No clear separation between dev/production code

---

## 1. FILE STRUCTURE & ORGANIZATION AUDIT

### 1.1 Current Architecture Issues

#### **Problem: Web Root Pollution**
```
web/
├── index.php                    # Production
├── index.php.old-20251212      # Should be in archive
├── index.html.backup           # Should be in archive
├── api.php                     # Production
├── api.php.backup              # Should be in archive
├── test_*.php (8 files)        # Should be in tests/
├── debug_*.php (4 files)       # Should be in tests/ or removed
├── cleanup_*.php               # Should be in scripts/
├── upload_*.php (5 files)      # Should be organized
```

**Impact:** Production directory cluttered with development artifacts, making it hard to distinguish active code from legacy files.

**Recommendation:**
- Move all `.backup`, `.old-*` files to `archive/web-backups/`
- Create `web/admin/` for admin tools (cleanup_*, purge_*)
- Create `web/dev/` for development utilities
- Consolidate upload handlers into single module

---

#### **Problem: Inconsistent API Organization**
```
web/
├── api.php                     # Main API
├── api/ (directory)
│   ├── cache_management.php
│   └── revenue_intelligence.php
```

**Issue:** APIs split between root file and subdirectory with no clear pattern.

**Recommendation:**
- Move all API endpoints to `web/api/`
- Rename `api.php` → `web/api/index.php` (main router)
- Create clear endpoint structure:
  ```
  web/api/
  ├── index.php              (router/main controller)
  ├── snapshots.php          (daily data endpoints)
  ├── revenue.php            (revenue intelligence)
  ├── cache.php              (cache management)
  ├── imports.php            (data import endpoints)
  └── vacations.php          (vacation tracking)
  ```

---

#### **Problem: Test Files Scattered**
```
tests/                          # Official test directory
archive/old-test-scripts/      # Legacy tests
web/test_*.php                  # Ad-hoc tests in production
web/debug_*.php                 # Debug scripts in production
```

**Recommendation:**
- Consolidate ALL tests under `tests/`
- Structure:
  ```
  tests/
  ├── Unit/              (existing - good)
  ├── Integration/       (existing - good)
  ├── Api/               (new - for API endpoint tests)
  ├── Import/            (new - for data import validation)
  └── Legacy/            (new - for archived test scripts)
  ```

---

### 1.2 JavaScript Organization Issues

#### **Problem: Flat Asset Structure**
```
web/assets/
├── app.js                           # Main app
├── detail_panel.js                  # Component
├── revenue-intelligence.js          # Feature module
├── chart-context-integration.js     # Feature
├── context-menu.js                  # UI component
├── export-utils.js                  # Utility
├── state-icons.js                   # Data
├── (15+ more mixed files)
```

**Issue:** No modular organization - all JS files flat in assets folder.

**Recommendation:**
```
web/assets/
├── js/
│   ├── app.js                    (main entry point)
│   ├── components/               (reusable UI components)
│   │   ├── context-menu.js
│   │   ├── detail-panel.js
│   │   └── subscriber-table.js
│   ├── features/                 (feature modules)
│   │   ├── revenue-intelligence.js
│   │   ├── backfill-indicator.js
│   │   └── vacation-display.js
│   ├── charts/                   (chart-specific code)
│   │   ├── chart-layout-manager.js
│   │   ├── chart-context-integration.js
│   │   └── donut-to-state-animation.js
│   ├── utils/                    (utility functions)
│   │   ├── export-utils.js
│   │   └── state-icons.js
│   └── config/                   (configuration/constants)
├── css/
│   ├── output.css               (generated - Tailwind)
│   └── input.css                (source)
└── images/
    ├── logos/
    └── states/
```

---

### 1.3 Python Script Organization

#### **Problem: Scripts Scattered**
```
hotfolder/
├── hotfolder_watcher.py
├── process_allsubs_historical.py
├── upload_historical_data.py
├── upload_historical_sql.py
scripts/
├── import_to_database.py
├── parse_rates.py
archive/old-analysis-scripts/
├── (8 analysis scripts)
```

**Recommendation:**
```
scripts/
├── imports/
│   ├── hotfolder_watcher.py       (from hotfolder/)
│   ├── import_to_database.py
│   ├── process_allsubs.py
│   └── parse_rates.py
├── maintenance/
│   ├── database/
│   │   ├── dump-db.sh
│   │   ├── restore-db.sh
│   │   └── migrate.sh
│   └── deployment/
│       ├── deploy-production.sh
│       └── update-version.sh
├── analysis/                      (archived analysis tools)
└── testing/                       (test scripts)
```

---

## 2. CODE QUALITY STANDARDS AUDIT

### 2.1 PHP Code Issues

#### **Critical: Inconsistent Error Handling**
Many PHP files likely lack:
- Try-catch blocks for database operations
- Input validation
- Proper HTTP status codes
- Standardized error responses

**Action Items:**
1. Create `web/includes/ErrorHandler.php` class
2. Standardize error response format:
   ```php
   {
       "success": false,
       "error": "User-friendly message",
       "code": "ERROR_CODE",
       "details": [...] // Only in development
   }
   ```

#### **Missing: PHP Standards**
- [ ] PSR-12 coding standards compliance
- [ ] Namespaces (likely missing)
- [ ] Autoloading (composer.json exists but may not be used)
- [ ] Type hints on function parameters
- [ ] Return type declarations

**Tools Needed:**
```bash
# Install code quality tools
composer require --dev phpstan/phpstan
composer require --dev squizlabs/php_codesniffer
composer require --dev friendsofphp/php-cs-fixer
```

---

### 2.2 JavaScript Code Issues

#### **Likely Issues:**
- No module system (ES6 modules vs global scope)
- Inconsistent variable declarations (var vs let/const)
- Missing JSDoc comments
- No linting configuration
- jQuery mixed with vanilla JS (based on dashboard patterns)

**Action Items:**
1. Add `.eslintrc.json`:
   ```json
   {
     "extends": "eslint:recommended",
     "parserOptions": {
       "ecmaVersion": 2022,
       "sourceType": "module"
     },
     "env": {
       "browser": true,
       "es6": true
     },
     "rules": {
       "no-var": "error",
       "prefer-const": "error",
       "no-unused-vars": "warn"
     }
   }
   ```

2. Add `.prettierrc`:
   ```json
   {
     "semi": true,
     "singleQuote": true,
     "tabWidth": 2,
     "trailingComma": "es5"
   }
   ```

---

### 2.3 Python Code Issues

#### **Likely Issues:**
- Inconsistent import organization
- Missing type hints (Python 3.7+)
- No docstrings
- Hardcoded configuration values
- No virtual environment management

**Action Items:**
1. Add `pyproject.toml`:
   ```toml
   [tool.black]
   line-length = 100
   target-version = ['py312']

   [tool.isort]
   profile = "black"
   line_length = 100

   [tool.pylint]
   max-line-length = 100
   ```

2. Create `requirements.txt` for Python dependencies
3. Add `.pylintrc` for code quality checks

---

### 2.4 SQL Code Issues

#### **Problem: SQL Files Scattered**
```
sql/ (20 files)
database/init/ (4 files)
database/migrations/ (5 files)
synology_setup/ (1 SQL file)
```

**Issues:**
- Duplicate migration systems
- Inconsistent naming
- No version tracking clarity

**Recommendation:**
```
database/
├── migrations/           (Phinx migrations only)
│   ├── YYYYMMDDHHMMSS_*.php
├── seeds/               (seed data)
│   └── ProductionSeeds.php
├── legacy/              (move old SQL files here)
│   ├── manual-migrations/
│   └── archived-schemas/
└── README.md            (migration guide)
```

---

## 3. SECURITY AUDIT

### 3.1 Critical Security Checks

#### **Files to Audit:**
- [ ] `web/login.php` - Session management
- [ ] `web/auth_check.php` - Authentication logic
- [ ] `web/brute_force_protection.php` - Rate limiting
- [ ] `web/config.php` - Credential storage
- [ ] `web/api.php` - Input validation

#### **Common Vulnerabilities to Check:**

1. **SQL Injection**
   - Search for: `$_GET`, `$_POST` used directly in queries
   - Ensure: PDO prepared statements everywhere

2. **XSS (Cross-Site Scripting)**
   - Check: All user input is escaped before output
   - Use: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`

3. **CSRF (Cross-Site Request Forgery)**
   - Missing: CSRF tokens on forms
   - Need: Token generation/validation

4. **Authentication Issues**
   - Check: Password hashing (bcrypt/argon2)
   - Verify: Session regeneration after login
   - Ensure: Secure session configuration

5. **File Upload Vulnerabilities**
   - Files: `upload_*.php` (multiple)
   - Check: File type validation
   - Verify: File size limits
   - Ensure: Files stored outside web root

---

### 3.2 Configuration Security

#### **Problem: Exposed Credentials**
```
web/config.php           # Likely contains DB credentials
nwauth/config.php        # Authentication config
```

**Action Items:**
1. Move credentials to `.env` file
2. Add `.env.example` template
3. Ensure `.env` in `.gitignore`
4. Use environment variables:
   ```php
   $db_host = getenv('DB_HOST') ?: 'localhost';
   $db_name = getenv('DB_NAME') ?: 'circulation';
   $db_user = getenv('DB_USER') ?: 'root';
   $db_pass = getenv('DB_PASS') ?: '';
   ```

---

## 4. DOCUMENTATION AUDIT

### 4.1 Current Documentation Assessment

✅ **Excellent:** Comprehensive docs/ folder with 50+ markdown files

⚠️ **Issues:**
1. **Overwhelming Volume** - Too many docs for newcomers
2. **No Clear Entry Point** - Which doc to read first?
3. **Archive Confusion** - `docs/ARCHIVE/` mixed with active docs
4. **Duplicate Concepts** - Multiple deployment guides

### 4.2 Recommended Documentation Structure

```
docs/
├── README.md                          # Start here
├── GETTING-STARTED.md                 # Quick setup guide
├── ARCHITECTURE.md                    # System overview
│
├── guides/                            # How-to guides
│   ├── deployment/
│   │   ├── DEPLOYMENT-GUIDE.md       (primary)
│   │   └── DEPLOYMENT-CHECKLIST.md
│   ├── development/
│   │   ├── COMPOSER-WORKFLOW.md
│   │   ├── MIGRATIONS-GUIDE.md
│   │   └── TESTING-GUIDE.md
│   └── operations/
│       ├── IMPORT-PATTERNS.md
│       └── TROUBLESHOOTING.md
│
├── reference/                         # Technical reference
│   ├── API-REFERENCE.md
│   ├── DATABASE-SCHEMA.md
│   └── DESIGN-SYSTEM.md
│
├── decisions/                         # Architecture decisions
│   ├── WEEK-BASED-ARCHITECTURE.md
│   └── SOFT-BACKFILL-SYSTEM.md
│
└── archive/                           # Historical docs
    ├── completed-work/
    ├── deprecated-features/
    └── old-deployment-notes/
```

---

## 5. TESTING INFRASTRUCTURE

### 5.1 Current Testing Status

**PHP Testing:**
- ✅ PHPUnit configured (`phpunit.xml`)
- ✅ Test structure exists (`tests/Unit/`, `tests/Integration/`)
- ⚠️ Limited test coverage (2 unit tests, 1 integration test)

**JavaScript Testing:**
- ✅ Vitest configured (`vitest.config.js`)
- ⚠️ Only 1 test file (`tests/app.test.js`)

**Python Testing:**
- ❌ No testing framework configured
- ❌ No test files found

### 5.2 Testing Recommendations

#### **Add Python Testing:**
```bash
# requirements-dev.txt
pytest>=7.4.0
pytest-cov>=4.1.0
pytest-mock>=3.11.0
```

#### **Test Coverage Goals:**
- [ ] API endpoints: 80%+ coverage
- [ ] Import scripts: 90%+ coverage (critical path)
- [ ] JavaScript components: 70%+ coverage
- [ ] Database migrations: 100% validation

#### **Test Organization:**
```
tests/
├── php/
│   ├── Unit/                 (isolated tests)
│   ├── Integration/          (API/database tests)
│   └── Feature/              (end-to-end scenarios)
├── javascript/
│   ├── components/           (UI component tests)
│   ├── utils/                (utility function tests)
│   └── integration/          (full workflow tests)
└── python/
    ├── unit/                 (function tests)
    ├── integration/          (script execution tests)
    └── fixtures/             (test data)
```

---

## 6. DEPENDENCY MANAGEMENT

### 6.1 PHP Dependencies (Composer)

**Current Status:**
- ✅ `composer.json` and `composer.lock` exist
- ⚠️ Need to audit dependencies for:
  - Outdated packages
  - Security vulnerabilities
  - Unused dependencies

**Action:**
```bash
composer outdated
composer audit
composer show --tree  # Check dependency conflicts
```

---

### 6.2 JavaScript Dependencies (NPM)

**Files Found:**
- `package.json`
- `package-lock.json`

**Action:**
```bash
npm outdated
npm audit
npm audit fix
```

---

### 6.3 Python Dependencies

**Issue:** No `requirements.txt` or `Pipfile` found

**Action Required:**
```bash
# Create from current environment
pip freeze > requirements.txt

# Or use pipenv for better dependency management
pipenv install
```

---

## 7. BUILD PROCESS & ASSETS

### 7.1 CSS Build (Tailwind)

**Current Setup:**
```
src/input.css → (build) → web/assets/output.css
```

**Issues to Check:**
- [ ] Build script documented?
- [ ] Production builds minified?
- [ ] Purge unused CSS?
- [ ] Source maps for debugging?

**Recommended `package.json` scripts:**
```json
{
  "scripts": {
    "css:dev": "tailwindcss -i ./src/input.css -o ./web/assets/output.css --watch",
    "css:build": "tailwindcss -i ./src/input.css -o ./web/assets/output.css --minify",
    "css:purge": "tailwindcss -i ./src/input.css -o ./web/assets/output.css --minify --purge"
  }
}
```

---

### 7.2 JavaScript Build Process

**Issue:** No JavaScript bundler detected

**Recommendation:**
- For small projects: Keep simple (no bundler)
- For growth: Add Vite or esbuild for:
  - Module bundling
  - Minification
  - Tree shaking
  - Dev server with HMR

---

## 8. CODE COMMENT AUDIT

### 8.1 Documentation Standards

**Required for ALL code files:**

#### **PHP Files:**
```php
<?php
/**
 * Brief file description
 *
 * Detailed explanation of file purpose, key functionality,
 * and any important notes about usage or dependencies.
 *
 * @package    CirculationIntelligence
 * @subpackage API
 * @author     Your Name
 * @since      1.0.0
 */

/**
 * Function description
 *
 * @param string $param1 Description
 * @param int    $param2 Description
 * @return array Description of return value
 * @throws Exception When something fails
 */
function myFunction($param1, $param2) {
    // Implementation
}
```

#### **JavaScript Files:**
```javascript
/**
 * Module description
 * @module ChartManager
 */

/**
 * Function description
 * @param {string} chartId - Chart identifier
 * @param {Object} options - Configuration options
 * @param {boolean} options.animate - Enable animations
 * @returns {Chart} Chart instance
 */
function createChart(chartId, options) {
    // Implementation
}
```

#### **Python Files:**
```python
"""
Module docstring explaining purpose and usage.

This module handles SFTP file imports from Newzware
and processes daily circulation data.
"""

def process_file(filepath: str, validate: bool = True) -> dict:
    """
    Process uploaded circulation file.
    
    Args:
        filepath: Path to CSV file
        validate: Whether to run validation checks
        
    Returns:
        Dictionary containing:
            - success: bool
            - records_processed: int
            - errors: list of error messages
            
    Raises:
        FileNotFoundError: If filepath doesn't exist
        ValueError: If file format is invalid
    """
    pass
```

---

### 8.2 Code Comment Guidelines

**When to Comment:**
- ✅ Complex business logic
- ✅ Non-obvious algorithms
- ✅ Workarounds for bugs
- ✅ Security-critical sections
- ✅ Performance optimizations

**When NOT to Comment:**
- ❌ Obvious code (`// increment counter`)
- ❌ Commented-out code (delete it)
- ❌ Redundant explanations

**Example of Good Comments:**
```php
// IMPORTANT: Newzware exports use Sunday as week boundary
// We must align our week calculations to match their system
// to ensure accurate YoY comparisons. See WEEK-BASED-ARCHITECTURE.md
$weekStart = $this->getSundayBoundary($date);
```

---

## 9. FILE SIZE AUDIT

### 9.1 Files Requiring Review

**Large files that may need splitting:**

**To Check:**
```bash
# Find PHP files over 500 lines
find web/ -name "*.php" -exec wc -l {} + | sort -rn | head -20

# Find JavaScript files over 400 lines
find web/assets/ -name "*.js" -exec wc -l {} + | sort -rn | head -20

# Find SQL files over 200 lines
find sql/ database/ -name "*.sql" -exec wc -l {} + | sort -rn
```

**Splitting Guidelines:**
- **PHP:** Classes > 400 lines should be split
- **JavaScript:** Files > 300 lines should be modularized
- **SQL:** Schema files > 500 lines should be split by table/feature

---

## 10. VERSION CONTROL CLEANUP

### 10.1 .gitignore Audit

**Should be ignored (verify):**
```
# Dependencies
/vendor/
/node_modules/

# Environment
.env
.env.local
*.local.php

# Build artifacts
/web/assets/output.css
/web/assets/*.min.*

# IDE
/.vscode/
/.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log
/logs/

# Temporary files
/web/test_*.php
/web/debug_*.php
*.backup
*.old

# Database dumps
*.sql.gz
*.sql.zip
/dumps/

# Uploads (sensitive data)
/queries/*.csv
/hotfolder/*.csv

# Cache
/cache/
*.cache
```

---

## 11. PERFORMANCE OPTIMIZATION CHECKLIST

### 11.1 Database Performance

**To Audit:**
- [ ] Indexes on frequently queried columns
- [ ] N+1 query problems
- [ ] Missing foreign keys
- [ ] Query complexity (EXPLAIN ANALYZE)
- [ ] Connection pooling configured

**Files to Check:**
- `web/api.php` - Main query patterns
- `web/includes/database.php` - Connection management

---

### 11.2 Caching Strategy

**Current Status:**
- ✅ `SimpleCache.php` exists
- ✅ `api/cache_management.php` endpoint

**To Verify:**
- [ ] Cache invalidation strategy
- [ ] Cache key naming conventions
- [ ] Cache expiry times appropriate
- [ ] Memory limits configured

---

### 11.3 Asset Optimization

**To Implement:**
- [ ] CSS minification (production)
- [ ] JavaScript minification
- [ ] Image optimization (WebP format used - good!)
- [ ] SVG optimization
- [ ] Gzip/Brotli compression enabled
- [ ] HTTP/2 server push for critical assets

---

## 12. DEPLOYMENT CHECKLIST

### 12.1 Pre-Deployment Requirements

**Before any deployment:**
- [ ] All tests passing
- [ ] No debug code in production files
- [ ] Environment variables configured
- [ ] Database migrations tested
- [ ] Backup created
- [ ] Rollback plan documented

---

### 12.2 Deployment Scripts Audit

**Scripts Found:**
```
scripts/deploy-production.sh
deploy.sh
build-and-push.sh
```

**To Verify:**
- [ ] Scripts have error handling
- [ ] Scripts log all actions
- [ ] Scripts support dry-run mode
- [ ] Scripts validate environment before changes
- [ ] Scripts include health checks

---

## 13. PRIORITY ACTION PLAN

### Phase 1: Critical Cleanup (Week 1)
**Priority: CRITICAL - Do this immediately**

1. **File Organization** (4 hours)
   ```bash
   # Move backup files
   mkdir -p archive/web-backups
   mv web/*.backup archive/web-backups/
   mv web/*.old* archive/web-backups/
   
   # Organize test files
   mv web/test_*.php tests/Legacy/
   mv web/debug_*.php tests/Debug/
   ```

2. **Security Audit** (2 hours)
   - [ ] Review `web/config.php` for exposed credentials
   - [ ] Check all `$_GET`/`$_POST` usage
   - [ ] Verify password hashing in `login.php`

3. **Documentation Cleanup** (2 hours)
   - [ ] Create `docs/README.md` (entry point)
   - [ ] Move obsolete docs to `archive/`
   - [ ] Update `DOCUMENTATION-INDEX.md`

---

### Phase 2: Code Standards (Week 2)
**Priority: HIGH - Foundation for refactoring**

1. **Install Quality Tools** (1 hour)
   ```bash
   # PHP
   composer require --dev phpstan/phpstan php-cs-fixer
   
   # JavaScript
   npm install --save-dev eslint prettier
   
   # Python
   pip install black pylint isort
   ```

2. **Configure Linters** (2 hours)
   - Create `.eslintrc.json`
   - Create `.php-cs-fixer.php`
   - Create `pyproject.toml`

3. **Initial Linting Pass** (4 hours)
   - Fix critical errors
   - Document suppressions for legacy code

---

### Phase 3: Structural Improvements (Week 3)
**Priority: MEDIUM - Better organization**

1. **Reorganize JavaScript** (4 hours)
   - Create `web/assets/js/` structure
   - Move files to appropriate subdirectories
   - Update HTML includes

2. **Consolidate APIs** (3 hours)
   - Move `api.php` content to `web/api/index.php`
   - Create endpoint modules
   - Update frontend API calls

3. **Python Script Consolidation** (2 hours)
   - Move hotfolder scripts to `scripts/imports/`
   - Update documentation

---

### Phase 4: Testing & Documentation (Week 4)
**Priority: MEDIUM - Quality assurance**

1. **Expand Test Coverage** (8 hours)
   - Write API endpoint tests
   - Test critical import functions
   - Add integration tests

2. **Code Documentation** (4 hours)
   - Add PHPDoc blocks to all classes
   - Add JSDoc to JavaScript modules
   - Add Python docstrings

3. **Performance Audit** (2 hours)
   - Review database queries
   - Check N+1 problems
   - Validate cache strategy

---

## 14. CODE QUALITY METRICS TO TRACK

### 14.1 Baseline Metrics (Measure Now)

Run these commands to establish baseline:

```bash
# PHP Lines of Code
find web/ -name "*.php" | xargs wc -l | tail -1

# JavaScript Lines of Code
find web/assets/ -name "*.js" | xargs wc -l | tail -1

# Python Lines of Code
find scripts/ hotfolder/ -name "*.py" | xargs wc -l | tail -1

# Number of TODO/FIXME comments
grep -r "TODO\|FIXME" web/ scripts/ | wc -l

# Number of backup/old files
find . -name "*.backup" -o -name "*.old*" | wc -l
```

### 14.2 Target Metrics (After Cleanup)

**File Organization:**
- Zero `.backup` or `.old` files in production directories
- Zero debug/test files in `web/` root
- All scripts in appropriate subdirectories

**Code Quality:**
- PHPStan level 5+ (no errors)
- ESLint: Zero errors, < 10 warnings
- Python: Pylint score > 8.0/10

**Test Coverage:**
- PHP: > 60%
- JavaScript: > 50%
- Python: > 70%

**Documentation:**
- 100% of public functions documented
- All modules have file-level documentation
- README exists in all major directories

---

## 15. REFACTORING READINESS CHECKLIST

**Before starting major refactoring, ensure:**

### Code Organization
- [ ] All production code in designated directories
- [ ] Test files separated from production code
- [ ] No backup/old files in working directories
- [ ] Clear separation of concerns (API, frontend, scripts)

### Code Quality
- [ ] Linters configured and passing
- [ ] Critical security issues resolved
- [ ] No exposed credentials in code
- [ ] Input validation on all endpoints

### Testing
- [ ] Test framework configured for all languages
- [ ] Critical path tests written and passing
- [ ] Test data fixtures available
- [ ] CI/CD pipeline ready (if applicable)

### Documentation
- [ ] Architecture documented
- [ ] API endpoints documented
- [ ] Database schema documented
- [ ] Deployment process documented

### Infrastructure
- [ ] Environment configuration standardized
- [ ] Database migration system working
- [ ] Backup/restore process tested
- [ ] Rollback plan documented

---

## 16. TOOLS & COMMANDS REFERENCE

### Quick Audit Commands

```bash
# Find large files
find . -type f -size +100k -exec ls -lh {} \; | sort -k5 -hr

# Find files without proper extensions
find web/ -type f ! -name "*.*"

# Find duplicate file names
find . -type f | rev | cut -d'/' -f1 | rev | sort | uniq -d

# Count comment density
# For PHP:
grep -r "^\s*//" web/*.php | wc -l

# For JavaScript:
grep -r "^\s*//" web/assets/*.js | wc -l

# Find TODO/FIXME/HACK comments
grep -r "TODO\|FIXME\|HACK\|XXX" --include="*.php" --include="*.js" --include="*.py" .

# Find potential SQL injection
grep -r "\$_GET\|\$_POST" --include="*.php" web/ | grep -v "htmlspecialchars\|mysqli_real_escape_string\|PDO"

# Find hardcoded credentials
grep -ri "password\s*=\|pwd\s*=\|passwd\s*=" --include="*.php" web/ | grep -v "placeholder"
```

---

## CONCLUSION

Your circulation intelligence dashboard is **well-structured at the architecture level** but needs systematic cleanup before refactoring. The project shows:

**Strengths:**
- Solid documentation culture
- Modern containerization
- Clear archive strategy
- Active development

**Key Improvements Needed:**
1. File organization (remove backups from production)
2. Standardize code quality (linters, formatters)
3. Security hardening (credential management, input validation)
4. Test coverage expansion
5. Code documentation (docblocks, comments)

**Estimated Cleanup Time:** 3-4 weeks part-time (60-80 hours total)

**Recommendation:** Follow the Phase 1-4 action plan sequentially. Each phase builds foundation for the next and prepares code for confident refactoring.

---

## NEXT STEPS

1. **Review this document** with your team
2. **Prioritize** which phases to tackle first
3. **Create branch** for cleanup work (`cleanup/code-quality-2025`)
4. **Start with Phase 1** (Critical Cleanup)
5. **Track metrics** throughout the process
6. **Document decisions** in `docs/decisions/`

**Questions to Answer:**
- What's your timeline for completing cleanup?
- Are there specific areas of most concern?
- What's your testing strategy during cleanup?
- Will cleanup happen in parallel with feature work?

