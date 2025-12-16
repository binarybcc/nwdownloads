# Testing Directory

## Structure

- **Unit/** - Isolated unit tests for individual functions/classes
- **Integration/** - Tests that verify component interactions
- **Legacy/** - Archived test scripts from web/ directory
- **Debug/** - Debug and diagnostic scripts

## Running Tests

### PHP Tests (PHPUnit)
```bash
./vendor/bin/phpunit
```

### JavaScript Tests (Vitest)
```bash
npm test
```

## Guidelines

- Keep test files organized by feature/module
- Name test files with `Test.php` or `.test.js` suffix
- Mock external dependencies
- Aim for 70%+ code coverage
