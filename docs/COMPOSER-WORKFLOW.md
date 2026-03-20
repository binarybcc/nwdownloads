# Composer Workflow Guide

**Last Updated:** December 11, 2025
**Project:** NWDownloads Circulation Dashboard

---

## 🎯 Current Approach: Hybrid

Your project uses **procedural PHP** (simple, working) with **Composer tooling** (modern quality checks).

**This is a valid approach!** Not every project needs full OOP/namespaces.

---

## 📦 What Composer Provides

### **Installed Tools (via composer.json)**

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.5", // Unit testing
    "phpstan/phpstan": "^1.10", // Static analysis
    "squizlabs/php_codesniffer": "^3.8", // Code style
    "mockery/mockery": "^1.6" // Mocking for tests
  }
}
```

### **Pre-Configured Scripts**

```bash
# Run all quality checks
composer quality

# Individual commands
composer analyse        # PHPStan static analysis
composer cs:check       # Check code style
composer cs:fix         # Auto-fix code style
composer test           # Run PHPUnit tests
composer test:coverage  # Generate coverage report
```

---

## 🚀 Recommended Workflow

### **Daily Development**

```bash
# 1. Make changes to PHP files
vim web/api.php

# 2. Auto-fix code style
composer cs:fix

# 3. Check for errors
composer analyse

# 4. Commit (pre-commit hook runs automatically)
git add web/api.php
git commit -m "Fix: Update API endpoint"
```

### **Before Creating PR**

```bash
# Run full quality suite
composer quality

# If all pass, create PR
git push origin feature/my-feature
gh pr create
```

### **Manual Testing**

```bash
# Visit production dashboard
open https://cdash.upstatetoday.com

# Check logs on NAS
ssh nas
tail -f /volume1/web/circulation/error.log
```

---

## 🔧 Using Local vs Global Tools

### **Local (Recommended)**

**Pros:**

- ✅ Project-specific versions
- ✅ Works for all team members
- ✅ Consistent across computers
- ✅ Documented in composer.json

**Usage:**

```bash
# Install dependencies
composer install

# Use tools via composer scripts
composer analyse
composer cs:fix

# Or directly
./vendor/bin/phpstan analyze web/
./vendor/bin/phpcs web/
```

### **Global (Current Setup)**

**Pros:**

- ✅ Available everywhere
- ✅ Don't need to run composer install

**Cons:**

- ❌ Version might differ from project
- ❌ Other developers might have different versions

**Usage:**

```bash
# Already installed globally
~/.composer/vendor/bin/phpstan analyze web/
~/.composer/vendor/bin/phpcs web/
```

### **Hybrid (Best of Both)**

Pre-commit hook now tries **local first, global second**:

```bash
1. Check ./vendor/bin/phpstan
2. Fallback to ~/.composer/vendor/bin/phpstan
3. Skip if neither found
```

---

## 📁 Project Structure

### **What You Have**

```
nwdownloads/
├── web/                  ← Your actual code (procedural PHP)
│   ├── api.php
│   ├── rates.php
│   ├── upload.php
│   └── ...
├── src/                  ← Empty (configured for OOP, not used)
│   └── input.css
├── tests/                ← Mostly empty
│   ├── bootstrap.php
│   ├── ui/
│   │   └── header-redesign-test.php
│   └── app.test.js
├── vendor/               ← Composer dependencies
├── composer.json         ← Tool configuration
└── .phpstan.neon        ← PHPStan config
```

### **The Gap**

**composer.json says:**

```json
"autoload": {
  "psr-4": {
    "NWDownloads\\": "src/"    ← Expects namespaced classes in src/
  }
}
```

**Reality:**

- `src/` is empty
- Code is in `web/` (procedural, no namespaces)
- This is **fine** - just use Composer for tooling, not autoloading

---

## 🤔 Should You Modernize?

### **Option 1: Stay Procedural (Recommended)**

**Keep current approach:**

- ✅ Code works great
- ✅ Simple to maintain
- ✅ Use Composer only for dev tools
- ✅ No big refactor needed

**Just improve:**

- Centralize database connections (src/database.php)
- Add helper functions (src/helpers.php)
- Keep procedural style

**Time:** 2-4 hours

---

### **Option 2: Light Modernization**

**Move to simple classes (no namespaces yet):**

```php
// web/classes/Database.php
class Database {
    private static $instance;

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new PDO(/* ... */);
        }
        return self::$instance;
    }
}

// web/api.php
require_once 'classes/Database.php';
$db = Database::getInstance();
```

**Time:** 1-2 weeks

---

### **Option 3: Full OOP + PSR-4**

**Modernize completely:**

```php
// src/Database/Connection.php
namespace NWDownloads\Database;

class Connection {
    // Modern OOP code
}

// web/api.php
require_once '../vendor/autoload.php';

use NWDownloads\Database\Connection;
$db = Connection::getInstance();
```

**Time:** 3-6 weeks
**Worth it?** Only if:

- Team is growing
- Adding lots of features
- Need better testability

---

## 💡 My Recommendation

### **For Your Project**

**Stay procedural, improve incrementally:**

1. ✅ **Keep using Composer tools** (PHPStan, PHPCS) - you're already doing this!
2. ✅ **Centralize database connections** (Phase 2 suggestion)
3. ✅ **Add utility files** when duplication gets painful
4. ❌ **Don't refactor to OOP** unless you have a specific reason

**Why?**

- Your code works
- Small team/solo developer
- Internal tool, not public library
- Time better spent on features

### **When to Modernize**

**Consider OOP/namespaces if:**

- Team grows beyond 2-3 developers
- Code becomes hard to test
- You're building a public API/library
- Adding complex business logic

---

## 📚 Quick Reference

### **Composer Commands**

```bash
# Install/update dependencies
composer install          # Install from lock file
composer update          # Update to latest versions

# Quality checks
composer analyse         # PHPStan
composer cs:check        # PHPCS
composer cs:fix          # PHPCBF
composer test            # PHPUnit

# All at once
composer quality         # Run all checks
```

### **Direct Tool Usage**

```bash
# PHPStan
./vendor/bin/phpstan analyze web/ --level=5

# PHPCS
./vendor/bin/phpcs web/ --standard=PSR12

# PHPCBF
./vendor/bin/phpcbf web/ --standard=PSR12

# PHPUnit (when you have tests)
./vendor/bin/phpunit tests/
```

---

## 🎓 Learning Path

**If you want to modernize gradually:**

1. **Week 1:** Learn namespaces and PSR-4
   - Read: https://www.php-fig.org/psr/psr-4/

2. **Week 2:** Practice with small utility classes
   - Create src/Helpers/StringUtils.php

3. **Week 3:** Refactor one feature to OOP
   - Pick smallest feature (e.g., rate calculations)

4. **Week 4+:** Gradually migrate

**Or don't!** Procedural PHP is perfectly valid.

---

## ✅ Bottom Line

**You already have great tooling:**

- ✅ PHPStan catches errors
- ✅ PHPCS enforces style
- ✅ Pre-commit hooks prevent issues
- ✅ Composer manages versions

**You don't need OOP/namespaces unless:**

- Code becomes unmaintainable
- Team grows significantly
- You enjoy refactoring

**Focus on:**

- ✅ Reducing code duplication (centralize DB connections)
- ✅ Writing good procedural code
- ✅ Using the tools you have

**Your workflow is solid!** 🎉
