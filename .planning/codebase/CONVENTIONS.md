# Coding Conventions

**Analysis Date:** 2026-02-09

## Naming Patterns

**Files:**

- PHP files: kebab-case for frontend files (`upload_unified.php`), snake_case for backend utilities
- JavaScript files: kebab-case for feature/utility modules (`vacation-display.js`, `state-icons.js`)
- API/core files: descriptive names following function (`api.php`, `auth_check.php`)
- Namespaced PHP classes: PascalCase within `CirculationDashboard\` namespace (e.g., `AllSubscriberProcessor`, `EmailNotifier`)

**Functions:**

- PHP: snake_case for utility functions (`getWeekBoundaries()`, `connectDB()`, `getMostRecentCompleteSaturday()`)
- JavaScript: camelCase for all functions (`loadDashboardData()`, `formatNumber()`, `calculateTrendDirection()`)
- Async functions use `async` keyword in JavaScript
- Private/internal functions don't have prefix convention (rely on naming clarity)

**Variables:**

- PHP: snake_case for all variables (`$snapshot_date`, `$total_active`, `$db_config`)
- JavaScript: camelCase for all variables (`dashboardData`, `currentDate`, `businessUnitCharts`)
- Constants: UPPER_SNAKE_CASE in both languages (`API_BASE = './api.php'`, `RENEWAL_RATE_THRESHOLDS = {...}`)
- Avoid unused variables (parameters ignored use `_` prefix: `function handleEvent(...$_unused)`)

**Types:**

- PHP: Full type declarations in docblocks with `@param` and `@return` (e.g., `@return array<string, mixed>`)
- JavaScript: JSDoc comments for complex functions with `@param` and `@return` tags
- Database/data structures documented in class-level docblocks

## Code Style

**Formatting:**

- Tool: Prettier for JavaScript (configured in `.prettierrc`)
- Tool: PHP CS Fixer for PHP (configured in `.php-cs-fixer.php`)

**JavaScript Settings (Prettier):**

- 2-space indentation
- Single quotes (with escape avoidance)
- Semicolons required
- Max line width: 100 characters
- Trailing commas: ES5 style (arrays/objects, not function args)
- Arrow function parentheses: avoid when possible
- Bracket spacing: true

**PHP Settings (CS Fixer):**

- PSR-12 coding standard (base)
- 4-space indentation (PSR-12 default)
- Single quotes for strings
- Short array syntax: `[]`
- Blank line after namespace and before statements (return, try, throw)
- Single blank line between class elements (methods and properties)
- Ordered imports: alphabetical
- PHPDoc alignment: left
- No leading namespace backslashes
- No trailing commas in single-line arrays

## Linting

**JavaScript:**

- Tool: ESLint (configuration in `eslint.config.js`)
- Base: ESLint recommended rules
- Key rules enforced:
  - `no-var`: error (use const/let only)
  - `prefer-const`: warn (const over let when possible)
  - `no-unused-vars`: warn (except parameters prefixed with `_`)
  - `no-console`: off (console methods allowed for debugging)
  - `eqeqeq`: error (strict equality `===` required)
  - `curly`: error (braces required on all blocks)
  - `brace-style`: error (1TBS style: `} else {` on same line)
  - `no-undef`: error (all globals must be declared)
  - `no-redeclare`: error

**PHP:**

- No explicit linter tool configured, relies on CS Fixer for standards compliance
- Error reporting enabled during development: `error_reporting(E_ALL)`

## Import Organization

**JavaScript:**

- Comments indicate load order (e.g., "LOAD ORDER: 1 of 11") in file headers
- Modules loaded via script tags in HTML in specific order (not ES6 imports)
- Global namespace registration: `const CircDashboard = window.CircDashboard || {};`
- Exported functions declared with `/* exported functionName */` comment

**PHP:**

- Namespaced imports at top: `use CirculationDashboard\Processors\AllSubscriberProcessor;`
- Autoloading via Composer: `require_once __DIR__ . '/../vendor/autoload.php';`
- File-level includes: `require_once 'api/legacy.php';` for local includes

**Path Aliases:**

- No path aliases configured for JavaScript (uses relative paths)
- PHP uses full namespace paths via Composer autoloader

## Error Handling

**PHP Patterns:**

- Try-catch blocks for database operations and external APIs
  - Database exceptions: catch `PDOException`
  - Custom exceptions: catch `Exception`
- Error responses sent via `sendError()` function (returns JSON)
- Error reporting disabled in production: `ini_set('display_errors', 0);`
- Database connection handles both TCP and Unix socket fallback

**JavaScript Patterns:**

- Async-await with try-catch-finally blocks
- Error messages displayed via `alert()` or UI elements
- Console logging for debugging (info/warn/error levels)
- Fallback comparisons when data unavailable (empty state handling)

**Database-Specific:**

- PDO used for all database operations with prepared statements
- Configuration loaded from environment variables via `getenv()`
- Connection includes charset declaration: `charset=utf8mb4`

## Logging

**Framework:** `console` object in JavaScript (not a logging library)

**Patterns:**

- `console.log()` for info messages (with emoji prefixes in some modules)
- `console.error()` for errors
- `console.warn()` for warnings
- Commented-out debug logs left in code (e.g., `// console.log('===== APP.JS FILE LOADED =====');`)
- Loading indicators shown during async operations (showLoading/hideLoading pattern)

**PHP:**

- Structured error messages in try-catch blocks
- Database error details logged before sending to client
- No centralized logging library (errors sent via API response)

## Comments

**When to Comment:**

- File headers: Always include description of module purpose and load order
- Function headers: Always include JSDoc for functions with parameters/returns
- Inline comments: Only for non-obvious logic or workarounds
- Avoid: Over-commenting obvious code; comments should explain "why" not "what"

**JSDoc/TSDoc:**

- Function docblocks include `@param {type}`, `@return {type}`, descriptions
- Parameters documented with type and brief description
- Complex return types documented: `@return {array<string, mixed>}`
- Some functions marked as `/* exported functionName */` to suppress unused warnings

**PHP Docblocks:**

- Function docblocks follow PHPDoc standard
- Parameter types documented: `@param array<string, mixed> $config`
- Return types documented: `@return PDO`, `@return array{start: string, end: string, week_num: int, year: int}`
- Class comments describe purpose and responsibility

## Function Design

**Size:**

- No explicit maximum line length for functions
- Utility functions tend to be 15-40 lines
- Async operations use Promise.all() for parallel execution

**Parameters:**

- Functions accept explicit parameters (no implicit globals except window/document)
- Database connection passed as parameter: `function connectDB(array $config): PDO`
- Callback functions use arrow functions in JavaScript: `array_filter($data, fn($item) => $item > 0)`

**Return Values:**

- Functions return data structures directly (arrays, objects)
- Errors thrown as exceptions rather than error codes
- Null used for missing/not-found conditions
- Empty arrays `[]` returned for "no results" case

## Module Design

**Exports:**

- JavaScript: Functions declared in global scope, made available via `/* exported functionName */`
- PHP: Classes use namespace and Composer autoload; functions included via `require_once`
- API endpoints return JSON via `header('Content-Type: application/json')`

**Barrel Files:**

- Not used in this codebase
- Each file has specific responsibility

## Global State Management

**JavaScript:**

- Centralized state object: `CircDashboard.state` for dashboard
- Module-specific state: `CircDashboard.churnState` for churn dashboard
- No Redux/Vuex style store (simple global objects)
- State updated directly by event handlers

**PHP:**

- Session variables: `$_SESSION['user']`, `$_SESSION['csrf_token']`
- Database as single source of truth
- Configuration loaded from environment and passed as parameters

## Authentication & CSRF

**PHP:**

- File-level auth check: `require_once 'auth_check.php';` at top of protected pages
- Session-based authentication (Newzware LDAP integration)
- CSRF token: Generated per session, validated on form submission
- Token displayed in forms: `value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"`

**HTML Forms:**

- Inline JavaScript event handlers: `onclick="switchTab('subscribers')"`
- Form submission via JavaScript with fetch/AJAX
- Success/error messages shown inline

## Data Validation

**JavaScript:**

- Client-side validation: required attributes, type checking
- File upload validation: File type checking before submission
- Data type coercion handled implicitly

**PHP:**

- Input sanitization: `htmlspecialchars()` for output escaping
- Database parameters: Prepared statements with bound values
- CSV parsing with column mapping and type conversion
- Date validation: `DateTime` class for date parsing

## Type Safety

**JavaScript:**

- No TypeScript (uses JSDoc for type documentation)
- Type checking via ESLint rules
- Assumes browser APIs are available globally

**PHP:**

- Full type declarations in function signatures: `function getWeekBoundaries(string $date): array`
- Nullable types where applicable: `?string`, `?int`
- Type hints for return arrays: `array<string, mixed>`

---

_Convention analysis: 2026-02-09_
