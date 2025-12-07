# Testing Guide

Comprehensive testing infrastructure for the NWDownloads Circulation Dashboard.

## Quick Start

### PHP Testing

```bash
# Run all PHP tests
composer test

# Run with coverage report
composer test:coverage

# Run static analysis
composer analyse

# Check code style
composer cs:check

# Auto-fix code style
composer cs:fix

# Run all quality checks
composer quality
```

### JavaScript Testing

```bash
# Install dependencies (first time only)
npm install

# Run JavaScript tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with UI
npm run test:ui

# Run tests with coverage
npm run test:coverage
```

## Test Structure

```
tests/
├── bootstrap.php           # PHPUnit bootstrap
├── setup.js               # Vitest setup
├── Unit/                  # PHP unit tests
│   └── WeekBoundariesTest.php
├── Integration/           # PHP integration tests
│   └── ApiEndpointTest.php
├── Fixtures/             # Test data and fixtures
└── app.test.js           # JavaScript tests
```

## PHP Testing (PHPUnit)

### Configuration

- **phpunit.xml** - PHPUnit configuration
- **composer.json** - Dependencies and scripts
- **tests/bootstrap.php** - Bootstrap file

### Writing Tests

```php
<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

### Test Database

Tests use a separate database: `circulation_dashboard_test`

Configure in phpunit.xml:
```xml
<php>
    <env name="DB_NAME" value="circulation_dashboard_test"/>
</php>
```

## JavaScript Testing (Vitest)

### Configuration

- **vitest.config.js** - Vitest configuration
- **package.json** - Dependencies and scripts
- **tests/setup.js** - Global test setup

### Writing Tests

```javascript
import { describe, it, expect } from 'vitest';

describe('Feature Name', () => {
  it('should do something', () => {
    expect(true).toBe(true);
  });
});
```

### Mocking

Vitest provides built-in mocking:

```javascript
import { vi } from 'vitest';

const mockFunction = vi.fn();
mockFunction.mockReturnValue(42);
```

## Static Analysis (PHPStan)

### Configuration

PHPStan is configured in composer.json with level 5 analysis.

### Running Analysis

```bash
# Analyze all PHP files
composer analyse

# Analyze specific file
./vendor/bin/phpstan analyse web/api.php --level=5
```

### Suppressing Warnings

For known issues (like $argv in CLI scripts):

```php
/** @phpstan-ignore-next-line */
$argv = $argv ?? [];
```

## Code Style (PHP_CodeSniffer)

### Standard

Uses PSR-12 coding standard.

### Running Checks

```bash
# Check code style
composer cs:check

# Auto-fix violations
composer cs:fix

# Check specific file
./vendor/bin/phpcs web/api.php --standard=PSR12
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  php-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.0
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
      - name: Run static analysis
        run: composer analyse

  js-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node
        uses: actions/setup-node@v2
        with:
          node-version: 18
      - name: Install dependencies
        run: npm install
      - name: Run tests
        run: npm test
```

## Coverage Reports

### PHP Coverage

```bash
composer test:coverage
# Opens coverage/index.html
```

### JavaScript Coverage

```bash
npm run test:coverage
# Opens coverage/index.html
```

## Testing Best Practices

### Unit Tests
- Test one thing at a time
- Use descriptive test names
- Follow AAA pattern (Arrange, Act, Assert)
- Mock external dependencies

### Integration Tests
- Test actual data flow
- Use test database
- Clean up after tests
- Test error conditions

### Test Data
- Use fixtures for complex data
- Keep test data minimal
- Use factories for object creation
- Reset state between tests

## Troubleshooting

### PHPUnit Issues

**"Class not found"**
```bash
composer dump-autoload
```

**"Database connection failed"**
- Check test database exists
- Verify credentials in phpunit.xml

### Vitest Issues

**"Cannot find module"**
```bash
npm install
```

**"JSDOM errors"**
- Ensure jsdom is installed
- Check environment setting in vitest.config.js

## Resources

- [PHPUnit Documentation](https://phpunit.de/)
- [PHPStan Documentation](https://phpstan.org/)
- [Vitest Documentation](https://vitest.dev/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
