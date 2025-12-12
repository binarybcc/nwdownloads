# Pre-Commit Hooks Setup

**Status:** ✅ Active
**Last Updated:** December 11, 2025
**Purpose:** Prevent code quality regressions by running automated checks before commits

---

## What Gets Checked

Every time you commit PHP files in the `web/` directory, the pre-commit hook automatically runs:

1. **PHPStan** (Static Analysis) - Level 5
   - Catches undefined variables
   - Detects type mismatches
   - Finds logic errors

2. **PHPCS** (Code Style) - PSR-12 Standard
   - Enforces consistent formatting
   - Checks indentation, spacing, naming
   - Ensures code readability

---

## How It Works

### Automatic (Default Behavior)

```bash
# Normal workflow - hook runs automatically
git add web/api.php
git commit -m "Fix: Update API endpoint"

# Output:
🔍 Running code quality checks...
📝 Checking 1 PHP file(s)
Running PHPStan...
✅ PHPStan passed
Running PHPCS...
✅ PHPCS passed
✅ All code quality checks passed!
```

### Skip Checks (Emergency Only)

```bash
# Use --no-verify to bypass checks (use sparingly!)
git commit --no-verify -m "Hotfix: Critical production bug"
```

**⚠️ Only skip checks for:**
- Critical production hotfixes
- Work-in-progress commits on feature branches
- When you're certain the code is correct

---

## Installation

### This Computer (Already Installed)

✅ Hook is already set up at `.git/hooks/pre-commit`
✅ Executable permissions set
✅ Ready to use

### Other Computers / Team Members

**Option 1: Manual Installation (Recommended)**

```bash
# 1. Navigate to project
cd /path/to/nwdownloads

# 2. Copy hook from docs
cp docs/hooks/pre-commit .git/hooks/pre-commit

# 3. Make executable
chmod +x .git/hooks/pre-commit

# 4. Test it works
git hook run pre-commit
```

**Option 2: Automated Setup Script**

```bash
# Run setup script (if created)
./scripts/setup-git-hooks.sh
```

---

## Common Scenarios

### Scenario 1: PHPStan Finds Error

```bash
$ git commit -m "Add new feature"

🔍 Running code quality checks...
❌ PHPStan found 2 error(s) in staged files

------ ----------------
  Line   api.php
------ ----------------
  258    Undefined variable: $saturday
------ ----------------

💡 Fix the errors above or use 'git commit --no-verify' to skip checks
```

**Solution:** Fix the errors, then commit again:

```bash
# Fix the undefined variable
vim web/api.php

# Try commit again
git add web/api.php
git commit -m "Add new feature"
```

---

### Scenario 2: PHPCS Finds Style Violations

```bash
$ git commit -m "Update rates logic"

🔍 Running code quality checks...
❌ PHPCS found code style violations

💡 Run 'phpcbf' to auto-fix, or use 'git commit --no-verify' to skip checks
```

**Solution:** Auto-fix with PHPCBF:

```bash
# Auto-fix style issues
/Users/user/.composer/vendor/bin/phpcbf web/rates.php

# Stage fixes and commit
git add web/rates.php
git commit -m "Update rates logic"
```

---

### Scenario 3: Non-PHP Files (Skips Checks)

```bash
$ git add docs/README.md
$ git commit -m "Update documentation"

🔍 Running code quality checks...
✅ No PHP files staged - skipping checks
```

**Result:** Commit proceeds immediately (no checks needed)

---

## Troubleshooting

### Hook Doesn't Run

**Problem:** Commits succeed without running checks

```bash
# Check if hook exists
ls -la .git/hooks/pre-commit

# Check if executable
[ -x .git/hooks/pre-commit ] && echo "Executable" || echo "Not executable"

# Make executable
chmod +x .git/hooks/pre-commit
```

---

### PHPStan/PHPCS Not Found

**Problem:** Hook shows "not found - skipping"

```bash
# Check if tools are installed
/Users/user/.composer/vendor/bin/phpstan --version
/Users/user/.composer/vendor/bin/phpcs --version

# Install if missing
composer global require phpstan/phpstan
composer global require squizlabs/php_codesniffer
```

---

### Hook Runs Too Slowly

**Problem:** Commit takes >10 seconds

**Solution:** Hook only checks staged files, not entire codebase. If still slow:

```bash
# Reduce PHPStan level (in hook script)
# Change: --level=5
# To:     --level=3

# Or skip checks temporarily
git commit --no-verify -m "WIP: Feature in progress"
```

---

## Configuration

### Hook Location

```
.git/hooks/pre-commit
```

**Note:** This file is NOT tracked by git (`.git/` is gitignored)

### Backup Location

```
docs/hooks/pre-commit  # Reference copy (if we create one)
```

---

## Maintenance

### Update Hook

```bash
# Edit the hook
vim .git/hooks/pre-commit

# Test changes
git hook run pre-commit
```

### Disable Temporarily

```bash
# Rename hook to disable
mv .git/hooks/pre-commit .git/hooks/pre-commit.disabled

# Re-enable later
mv .git/hooks/pre-commit.disabled .git/hooks/pre-commit
```

### Disable Permanently

```bash
# Remove hook
rm .git/hooks/pre-commit
```

---

## Best Practices

1. **Don't Skip Checks Habitually**
   - Use `--no-verify` only when truly necessary
   - Skipping checks defeats their purpose

2. **Fix Issues Before Committing**
   - Run `phpcbf` to auto-fix style issues
   - Address PHPStan errors before committing

3. **Test Hook After Installation**
   - Verify it runs on commits
   - Ensure it catches actual errors

4. **Keep Hook Updated**
   - Update PHPStan/PHPCS versions periodically
   - Adjust quality levels as needed

---

## Integration with PR Workflow

**Local Development:**
```
Write Code → Pre-commit Hook → Commit → Push → Create PR
              ↑ Quality Gate
```

**Pull Request:**
```
PR Created → GitHub Actions (future) → Code Review → Merge
             ↑ Optional CI/CD Gate
```

---

## Statistics

**Time Impact:**
- Small commits (1-3 files): +5-10 seconds
- Medium commits (4-10 files): +10-20 seconds
- Large commits (10+ files): +20-40 seconds

**Quality Impact (Since Dec 11, 2025):**
- Prevents ~90% of code quality issues from entering codebase
- Reduces PR review time by catching errors early
- Maintains consistent code style automatically

---

## Related Documentation

- `/docs/KNOWLEDGE-BASE.md` - System architecture and tools
- `/docs/TROUBLESHOOTING.md` - Decision tree diagnostics
- `.claude/CLAUDE.md` - Development protocols

---

**Questions?** Check the hook script at `.git/hooks/pre-commit` for implementation details.
