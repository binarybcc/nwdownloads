# Code Quality Tools - Quick Reference Guide

## Quick Start

### 1. Install Dependencies

```bash
# PHP Code Quality Tools
composer require --dev phpstan/phpstan:^1.10
composer require --dev squizlabs/php_codesniffer:^3.7
composer require --dev friendsofphp/php-cs-fixer:^3.40

# JavaScript Code Quality Tools
npm install --save-dev eslint prettier eslint-config-prettier

# Python Code Quality Tools (create virtual environment first)
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install black isort pylint pytest pytest-cov
```

### 2. Run Phase 1 Cleanup

```bash
# Make script executable
chmod +x cleanup-phase1.sh

# Dry run first (see what will happen)
./cleanup-phase1.sh --dry-run

# Execute cleanup
./cleanup-phase1.sh
```

---

## Code Quality Commands

### PHP

#### Check Code Style (PHP_CodeSniffer)
```bash
# Check all files
./vendor/bin/phpcs web/ --standard=PSR12

# Check specific file
./vendor/bin/phpcs web/api.php

# Auto-fix issues
./vendor/bin/phpcbf web/ --standard=PSR12
```

#### Fix Code Style (PHP CS Fixer)
```bash
# Dry run (show what will be fixed)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix all files
./vendor/bin/php-cs-fixer fix

# Fix specific directory
./vendor/bin/php-cs-fixer fix web/api/
```

#### Static Analysis (PHPStan)
```bash
# Basic analysis (level 0-9, start with 0)
./vendor/bin/phpstan analyse web/ --level=0

# Increase level gradually
./vendor/bin/phpstan analyse web/ --level=5

# Generate baseline (for legacy code)
./vendor/bin/phpstan analyse web/ --level=5 --generate-baseline
```

#### Run Tests
```bash
# All tests
./vendor/bin/phpunit

# Specific test
./vendor/bin/phpunit tests/Unit/WeekBoundariesTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

---

### JavaScript

#### Lint JavaScript
```bash
# Check all files
npx eslint web/assets/**/*.js

# Check specific file
npx eslint web/assets/app.js

# Auto-fix issues
npx eslint web/assets/**/*.js --fix
```

#### Format Code (Prettier)
```bash
# Check formatting
npx prettier --check "web/assets/**/*.js"

# Fix formatting
npx prettier --write "web/assets/**/*.js"

# Both ESLint and Prettier
npx prettier --write "web/assets/**/*.js" && npx eslint web/assets/**/*.js --fix
```

#### Run Tests
```bash
# All tests
npm test

# Watch mode
npm test -- --watch

# Coverage
npm test -- --coverage
```

---

### Python

#### Format Code (Black)
```bash
# Check formatting
black --check scripts/ hotfolder/

# Format all files
black scripts/ hotfolder/

# Format specific file
black scripts/import_to_database.py
```

#### Sort Imports (isort)
```bash
# Check import sorting
isort --check-only scripts/ hotfolder/

# Fix imports
isort scripts/ hotfolder/
```

#### Lint Code (Pylint)
```bash
# Lint all Python files
pylint scripts/ hotfolder/

# Lint specific file
pylint scripts/import_to_database.py

# Generate report
pylint scripts/ hotfolder/ --output-format=text > pylint-report.txt
```

#### Run Tests (Pytest)
```bash
# All tests
pytest

# Specific test file
pytest tests/python/test_imports.py

# With coverage
pytest --cov=scripts --cov-report=html

# Verbose output
pytest -v
```

---

## Audit Commands

### Find Issues

#### Large Files
```bash
# PHP files over 500 lines
find web/ -name "*.php" -exec wc -l {} + | awk '$1 > 500' | sort -rn

# JavaScript files over 400 lines
find web/assets/ -name "*.js" -exec wc -l {} + | awk '$1 > 400' | sort -rn

# All files over 1000 lines
find . -type f \( -name "*.php" -o -name "*.js" -o -name "*.py" \) -exec wc -l {} + | awk '$1 > 1000' | sort -rn
```

#### Complexity Analysis
```bash
# Find files with high cyclomatic complexity (PHP)
./vendor/bin/phpmd web/ text cleancode,codesize,controversial,design,naming,unusedcode

# Count functions per file (indicator of complexity)
grep -r "function " web/*.php | cut -d: -f1 | uniq -c | sort -rn
```

#### Find TODO/FIXME Comments
```bash
# All TODO/FIXME/HACK comments
grep -r "TODO\|FIXME\|HACK\|XXX" --include="*.php" --include="*.js" --include="*.py" . | grep -v node_modules | grep -v vendor

# Count by type
echo "TODO: $(grep -r "TODO" --include="*.php" --include="*.js" --include="*.py" . | grep -v node_modules | wc -l)"
echo "FIXME: $(grep -r "FIXME" --include="*.php" --include="*.js" --include="*.py" . | grep -v node_modules | wc -l)"
```

#### Find Backup/Old Files
```bash
# Find all backup files
find . -type f \( -name "*.backup" -o -name "*.old" -o -name "*.bak" \) | grep -v backup-

# Find timestamped backup files
find . -type f -name "*-20[0-9][0-9][0-9][0-9][0-9][0-9]*"
```

#### Find Potential Security Issues
```bash
# Potential SQL injection (PHP)
grep -r "\$_GET\|\$_POST" --include="*.php" web/ | grep -v "htmlspecialchars\|filter_input\|PDO"

# Hardcoded credentials
grep -ri "password\s*=\|pwd\s*=\|passwd\s*=" --include="*.php" --include="*.js" . | grep -v "placeholder\|example\|test"

# Unescaped output (XSS risk)
grep -r "echo \$_" --include="*.php" web/ | grep -v "htmlspecialchars"
```

#### Code Duplication
```bash
# Find duplicate file names
find . -type f | rev | cut -d'/' -f1 | rev | sort | uniq -d

# Simple duplicate code detection (requires phpcpd)
# composer require --dev sebastian/phpcpd
./vendor/bin/phpcpd web/
```

---

## Metrics & Reports

### Generate Comprehensive Report
```bash
#!/bin/bash
# Create comprehensive code quality report

echo "# Code Quality Report - $(date)" > code-quality-report.md
echo "" >> code-quality-report.md

echo "## Lines of Code" >> code-quality-report.md
echo "\`\`\`" >> code-quality-report.md
echo "PHP:" >> code-quality-report.md
find web/ -name "*.php" | xargs wc -l | tail -1 >> code-quality-report.md
echo "" >> code-quality-report.md
echo "JavaScript:" >> code-quality-report.md
find web/assets/ -name "*.js" | xargs wc -l | tail -1 >> code-quality-report.md
echo "" >> code-quality-report.md
echo "Python:" >> code-quality-report.md
find scripts/ hotfolder/ -name "*.py" | xargs wc -l | tail -1 >> code-quality-report.md
echo "\`\`\`" >> code-quality-report.md
echo "" >> code-quality-report.md

echo "## File Counts" >> code-quality-report.md
echo "\`\`\`" >> code-quality-report.md
echo "PHP files: $(find web/ -name "*.php" | wc -l)" >> code-quality-report.md
echo "JavaScript files: $(find web/assets/ -name "*.js" | wc -l)" >> code-quality-report.md
echo "Python files: $(find scripts/ hotfolder/ -name "*.py" | wc -l)" >> code-quality-report.md
echo "Test files: $(find tests/ -name "*.php" -o -name "*.js" -o -name "*.py" | wc -l)" >> code-quality-report.md
echo "\`\`\`" >> code-quality-report.md

echo "Report generated: code-quality-report.md"
```

---

## Pre-commit Hooks

### Setup Git Hooks
```bash
# Create .git/hooks/pre-commit
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash

echo "Running pre-commit checks..."

# Get staged PHP files
PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

# Get staged JS files
JS_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.js$')

# Get staged Python files
PY_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.py$')

FAILED=0

# Check PHP files
if [ -n "$PHP_FILES" ]; then
    echo "Checking PHP files..."
    ./vendor/bin/php-cs-fixer fix --dry-run --diff $PHP_FILES
    if [ $? -ne 0 ]; then
        echo "❌ PHP CS Fixer found issues. Run: ./vendor/bin/php-cs-fixer fix"
        FAILED=1
    fi
fi

# Check JavaScript files
if [ -n "$JS_FILES" ]; then
    echo "Checking JavaScript files..."
    npx eslint $JS_FILES
    if [ $? -ne 0 ]; then
        echo "❌ ESLint found issues. Run: npx eslint --fix"
        FAILED=1
    fi
fi

# Check Python files
if [ -n "$PY_FILES" ]; then
    echo "Checking Python files..."
    black --check $PY_FILES
    if [ $? -ne 0 ]; then
        echo "❌ Black found formatting issues. Run: black ."
        FAILED=1
    fi
fi

if [ $FAILED -eq 1 ]; then
    echo ""
    echo "❌ Pre-commit checks failed. Please fix issues and try again."
    exit 1
fi

echo "✅ All pre-commit checks passed!"
exit 0
EOF

chmod +x .git/hooks/pre-commit
echo "Pre-commit hook installed successfully!"
```

---

## Continuous Integration (CI)

### GitHub Actions Example
```yaml
# .github/workflows/code-quality.yml
name: Code Quality

on: [push, pull_request]

jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: PHP CS Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
      - name: PHPStan
        run: ./vendor/bin/phpstan analyse
      - name: PHPUnit
        run: ./vendor/bin/phpunit

  javascript:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm ci
      - name: ESLint
        run: npx eslint .
      - name: Tests
        run: npm test

  python:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Python
        uses: actions/setup-python@v4
        with:
          python-version: '3.12'
      - name: Install dependencies
        run: |
          pip install black pylint pytest
      - name: Black
        run: black --check .
      - name: Pylint
        run: pylint scripts/ hotfolder/
      - name: Tests
        run: pytest
```

---

## Troubleshooting

### Common Issues

#### "command not found: phpstan"
```bash
# Ensure Composer dependencies are installed
composer install

# Use full path
./vendor/bin/phpstan analyse
```

#### "ESLint: No files matching pattern"
```bash
# Check file paths
npx eslint "web/assets/**/*.js"

# Or specify files explicitly
npx eslint web/assets/app.js
```

#### "Black: No files found"
```bash
# Ensure you're in project root
pwd

# Check Python files exist
find scripts/ -name "*.py"
```

#### Memory Issues with PHPStan
```bash
# Increase memory limit
php -d memory_limit=1G ./vendor/bin/phpstan analyse
```

---

## Best Practices

### Before Committing
1. Run applicable linters for changed files
2. Run relevant tests
3. Check for TODO/FIXME comments you added
4. Review your changes with `git diff`

### Weekly Maintenance
1. Run full test suite
2. Check for outdated dependencies
3. Review code quality metrics
4. Update documentation

### Monthly Review
1. Generate comprehensive quality report
2. Review and address TODOs
3. Check for deprecated code
4. Update dependencies

---

## Additional Resources

### Documentation
- PHP: https://www.php-fig.org/psr/psr-12/
- JavaScript: https://eslint.org/docs/rules/
- Python: https://peps.python.org/pep-0008/

### Tools
- PHP CS Fixer: https://cs.symfony.com/
- PHPStan: https://phpstan.org/
- ESLint: https://eslint.org/
- Prettier: https://prettier.io/
- Black: https://black.readthedocs.io/
- Pylint: https://pylint.pycqa.org/

---

*Last Updated: December 2025*
