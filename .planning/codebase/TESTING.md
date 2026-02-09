# Testing Patterns

**Analysis Date:** 2026-02-09

## Test Framework

**JavaScript Runner:**

- Framework: Vitest
- Version: Latest (configured in `vitest.config.js`)
- Config: `vitest.config.js`
- Environment: jsdom (browser simulation)

**PHP Runner:**

- Framework: PHPUnit
- Version: 10.5
- Config: `phpunit.xml`
- Bootstrap: `tests/bootstrap.php`

**Run Commands:**

```bash
# JavaScript tests (Vitest)
npm test                          # Run all Vitest tests
npm run test:watch               # Watch mode (not configured, would be added as needed)

# PHP tests (PHPUnit)
./vendor/bin/phpunit             # Run all tests
./vendor/bin/phpunit tests/Unit  # Run unit tests only
./vendor/bin/phpunit tests/Integration  # Run integration tests only

# Coverage
npm run test:coverage            # JavaScript coverage (not configured, would be: vitest --coverage)
./vendor/bin/phpunit --coverage-html coverage/  # PHP coverage report
```

**Assertion Libraries:**

- JavaScript: Vitest built-in expect() API
- PHP: PHPUnit assertions (TestCase methods)

## Test File Organization

**JavaScript:**

- Location: `tests/` directory
- Naming: `*.test.js` files
- Structure:
  ```
  tests/
  ├── app.test.js           # Main dashboard tests
  ├── setup.js              # Test environment setup
  └── integration/          # (future directory for integration tests)
  ```

**PHP:**

- Location: `tests/` directory with subdirectories
- Naming: `*Test.php` files (PascalCase)
- Structure:
  ```
  tests/
  ├── Unit/
  │   ├── SimpleTest.php              # Basic functionality verification
  │   ├── WeekBoundariesTest.php      # Week number calculation tests
  │   └── [FeatureTest.php]           # Additional unit tests
  ├── Integration/
  │   ├── ApiEndpointTest.php         # API endpoint tests
  │   └── [IntegrationTest.php]       # Additional integration tests
  ├── Legacy/                         # (deprecated manual test scripts)
  ├── Debug/                          # (debugging utilities, not tests)
  ├── bootstrap.php                   # PHPUnit setup
  └── [setup.js]                      # Vitest setup
  ```

## Test Structure

**JavaScript Test Suite Structure:**

```javascript
import { describe, it, expect, beforeEach } from 'vitest';

describe('Feature Name', () => {
  it('should handle specific behavior', () => {
    // Arrange
    const input = { /* ... */ };

    // Act
    const result = functionUnderTest(input);

    // Assert
    expect(result).toBe(expectedValue);
  });

  it('should handle edge case', () => {
    expect(value).toEqual({...});
  });
});
```

**Patterns Observed in `tests/app.test.js`:**

- Grouped by feature: "Dashboard Data Handling", "Week Label Formatting", "Metric Calculations"
- Data structures tested with sample snapshots and mock data
- Edge cases tested: null values, year boundaries, fallback comparisons
- Basic math calculations verified with known inputs
- No setUp/tearDown patterns (state isolated per test)

**PHP Test Suite Structure:**

```php
<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FeatureTest extends TestCase
{
    /**
     * Test that feature does X
     */
    public function testFeatureDoesX(): void
    {
        // Arrange
        require_once PROJECT_ROOT . '/web/api.php';

        // Act
        $result = functionUnderTest(...);

        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

**Patterns Observed in `tests/Unit/WeekBoundariesTest.php`:**

- Tests for specific date calculations
- Function loaded via `require_once` in test method
- Uses PHPUnit assertion methods: `assertEquals()`, `assertGreaterThanOrEqual()`
- Comments explain context (e.g., "Wednesday (should return Sunday before it and Saturday after)")
- Isolated test data (no shared fixtures)

## Mocking

**Framework:** No explicit mocking library configured

- JavaScript: Manual mock objects created in test data
- PHP: No mock objects, tests use real functions

**Patterns in Tests:**

_JavaScript mocking:_

```javascript
// Mock data structure
const mockData = {
  has_data: false,
  week: { week_num: 48, year: 2025 },
  message: 'No snapshot uploaded',
};

// Filter/transform test
const trendData = [
  { week_num: 47, total_active: 8230 },
  { week_num: 48, total_active: null }, // Missing week
  { week_num: 49, total_active: 8226 },
];
const validWeeks = trendData.filter(d => d.total_active !== null);
expect(validWeeks).toHaveLength(2);
```

_PHP mocking:_

- No mocking: Tests call actual functions directly
- Database skipped if test database unavailable: `$this->markTestSkipped()`
- Setup method checks connectivity in `ApiEndpointTest::setUp()`

**What to Mock:**

- Data structures: Mock API responses, database results
- Date/time: Use fixed dates for reproducible tests (e.g., '2025-12-06')
- External dependencies: Not mocked currently, tests use real database if available

**What NOT to Mock:**

- Core calculation functions: Test with real logic
- Database connection: Integration tests use real test database
- HTTP requests: Use actual API endpoints in integration tests

## Fixtures and Factories

**Test Data:**

- Embedded directly in test files (no factory pattern)
- Data structures match API response schema:
  ```javascript
  const mockData = {
    has_data: true,
    week: {
      week_num: 48,
      year: 2025,
      label: 'Week 48, 2025',
      date_range: 'Nov 23 - Nov 29, 2025',
    },
    // ... additional fields
  };
  ```

**Location:**

- JavaScript: `tests/app.test.js` contains all test data
- PHP: Test data created inline in each test method
- No separate fixtures directory

**Database Fixtures:**

- Integration tests configure test database in `phpunit.xml`:
  - Host: localhost
  - Database: circulation_dashboard_test
  - Credentials in environment section
- Test database must exist before running integration tests
- No automatic database seeding in tests

## Coverage

**Requirements:** None enforced

**JavaScript Coverage:**

- Provider: v8
- Reporters: text, html, lcov (configured in `vitest.config.js`)
- Excludes: node_modules, tests/, vendor/, coverage/, \*.config.js files
- View coverage: `npm run test:coverage` (command not yet in package.json)

**PHP Coverage:**

- Configured but not enforced
- View coverage: Run with `./vendor/bin/phpunit --coverage-html coverage/`
- Generated as HTML report in `coverage/` directory

**Current State:**

- Basic test framework set up
- Limited test coverage (few tests written)
- Integration tests placeholder-only (marked incomplete)
- Legacy test scripts exist but not maintained

## Test Types

**Unit Tests:**

- Location: `tests/Unit/`
- Scope: Single functions, specific behaviors
- Examples: `WeekBoundariesTest.php` tests week number calculation logic
- Approach: Isolated function calls with known inputs
- Database required: No (uses `require_once` to load functions)

**Integration Tests:**

- Location: `tests/Integration/`
- Scope: Multiple components working together
- Examples: API endpoints returning valid JSON structure
- Approach: Call actual API endpoints, check response format
- Database required: Yes (marked skipped if test database unavailable)
- Current state: Mostly placeholder tests with `markTestIncomplete()`

**E2E Tests:**

- Status: Not implemented
- Would test: Full user workflows (upload → dashboard → export)
- Automation: Not configured

## Common Patterns

**Async Testing in JavaScript:**

```javascript
it('should handle async operations', async () => {
  const result = await loadDashboardData();
  expect(result).toBeDefined();
});
```

**Async Testing in PHP:**

- Not applicable (PHP is synchronous)
- Tests that call database use synchronous queries

**Error Testing in JavaScript:**

```javascript
it('should handle null values', () => {
  const data = { total_active: null };
  const result = data.total_active !== null;
  expect(result).toBe(false);
});
```

**Error Testing in PHP:**

```php
public function testDatabaseConnectionFailure(): void
{
  try {
    // Connection that should fail
    $pdo = new PDO('mysql:host=invalid_host', 'user', 'pass');
  } catch (PDOException $e) {
    $this->assertStringContainsString('connection', $e->getMessage());
  }
}
```

**Database Testing Pattern:**

```php
protected function setUp(): void
{
    parent::setUp();

    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=circulation_dashboard_test",
            'circ_dash',
            'Barnaby358@Jones!'
        );
    } catch (PDOException $e) {
        $this->markTestSkipped('Test database not available');
    }
}
```

## Test Environment Setup

**JavaScript Setup (`tests/setup.js`):**

```javascript
// Mock global Chart object used by charts library
global.Chart = class MockChart {
  constructor() {}
  destroy() {}
};
```

**PHP Setup (`tests/bootstrap.php`):**

```php
// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting and timezone
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Denver');

// Define constants for test environment
define('TESTING', true);
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Load test environment variables
$_ENV['DB_HOST'] = 'localhost';
// ... database config
```

## Running Tests

**All Tests:**

```bash
# JavaScript
npm test                           # Via Vitest

# PHP
./vendor/bin/phpunit             # All suites
```

**Specific Test File:**

```bash
# JavaScript
npm test tests/app.test.js

# PHP
./vendor/bin/phpunit tests/Unit/WeekBoundariesTest.php
```

**Watch Mode (Not Configured):**

```bash
# Would need to add to package.json:
npm run test:watch
```

## Pre-commit Testing

**Husky Integration:**

- Configured in `package.json` with `"prepare": "husky"`
- Pre-commit hook not yet configured (husky installed but no hooks)
- Linting runs on commit via `lint-staged`: eslint + prettier for JavaScript files

## Test Development Workflow

**When Adding Tests:**

1. Create `.test.js` file in `tests/` (JavaScript) or `*Test.php` in `tests/Unit/` or `tests/Integration/` (PHP)
2. Follow existing test structure and naming patterns
3. Use mock/fixture data matching actual API/database schemas
4. Run tests: `npm test` (JavaScript) or `./vendor/bin/phpunit` (PHP)
5. Incomplete tests: Use `it.skip()` (JavaScript) or `markTestIncomplete()` (PHP)

**Test Data Patterns:**

- Use realistic snapshot data from actual API responses
- Include edge cases: null values, boundaries, missing data
- Test with multiple business units (SC, MI, WY)
- Verify week boundary calculations across year boundaries

---

_Testing analysis: 2026-02-09_
