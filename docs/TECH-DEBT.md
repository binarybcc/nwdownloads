# Technical Debt & Future Improvements

**Last Updated:** December 22, 2024

This document tracks technical debt, refactoring needs, and future enhancement opportunities for the Circulation Dashboard project.

---

## High Priority

### 1. Unit Test Infrastructure

**Status:** Not Started
**Priority:** High
**Effort:** Medium (2-3 days)

**Problem:**
- No test infrastructure exists (no PHPUnit, no tests/ directory)
- Week calculation logic and backfill algorithm are complex and prone to regressions
- Manual testing is time-consuming and error-prone

**Solution:**
- Set up PHPUnit for PHP testing
- Create `tests/` directory structure
- Add unit tests for critical logic:
  - `AllSubscriberImporter::getWeekAndYear()` - Week calculation
  - `-7 day adjustment` logic - Date manipulation
  - Backfill algorithm - Complex state management
  - CSV parsing logic - Edge cases and validation

**Benefits:**
- Prevent regressions when modifying upload logic
- Faster development with confidence
- Easier onboarding for new developers
- Documentation through test examples

**References:**
- PR feedback: #[PR number] - "Test coverage for week calculation logic"
- Related: `web/lib/AllSubscriberImporter.php` lines 178-184 (date adjustment)

---

## Medium Priority

### 2. Refactor Upload Interface Confusion

**Status:** Partially Addressed
**Priority:** Medium
**Effort:** Low (1-2 hours)

**Problem:**
- Had multiple upload interfaces (`upload.html`, `upload.php`, `upload_unified.php`)
- Caused confusion and documentation drift
- `upload.html` now redirects, but still exists

**Current State:**
- ✅ Documentation updated to reference `upload_unified.php`
- ✅ `upload.html` redirects to canonical interface
- ⚠️ Old `upload.php` still exists (unused?)

**Next Steps:**
- Audit and remove unused upload files
- Consolidate all upload logic in `upload_unified.php`
- Ensure backward compatibility for any external integrations

**References:**
- `.claude/CLAUDE.md` lines 659-667 (upload interface warning)
- `web/upload_unified.php` - Canonical interface

---

### 3. Configuration Management

**Status:** In Progress
**Priority:** Medium
**Effort:** Medium (2-3 days)

**Problem:**
- Configuration scattered across multiple files
- Some config in code (hardcoded values)
- Environment-specific logic mixed with business logic

**Examples:**
- Database credentials in code vs `.env` files
- Business unit mappings hardcoded in `AllSubscriberImporter.php`
- Publication schedules in database vs code

**Solution:**
- Centralize configuration in config files
- Use environment variables for deployment-specific values
- Create configuration validation system
- Document all configuration points

**Benefits:**
- Easier deployment across environments
- Clear separation of concerns
- Reduced risk of configuration errors

---

## Low Priority (Future Enhancements)

### 4. Automated Testing in CI/CD

**Status:** Not Started
**Priority:** Low
**Effort:** Medium (2-3 days)

**Dependencies:**
- Requires #1 (Unit Test Infrastructure) to be completed

**Problem:**
- No automated testing in deployment pipeline
- Manual verification required after each deployment
- Risk of deploying broken code to production

**Solution:**
- Add GitHub Actions workflow for automated testing
- Run tests on every PR
- Block merge if tests fail
- Add deployment smoke tests

**Benefits:**
- Catch bugs before production
- Faster, safer deployments
- Confidence in code changes

---

### 5. Standardize Error Handling

**Status:** Not Started
**Priority:** Low
**Effort:** Medium (2-3 days)

**Problem:**
- Inconsistent error handling patterns
- Mix of exceptions, error_log, and silent failures
- Difficult to debug production issues

**Solution:**
- Create standardized error handling layer
- Implement structured logging
- Add error tracking/monitoring
- Document error handling patterns

**Benefits:**
- Easier debugging
- Better user feedback
- Proactive issue detection

---

### 6. Database Migration System

**Status:** Partially Implemented
**Priority:** Low
**Effort:** Low (1 day)

**Current State:**
- Raw SQL files in `database/migrations/`
- Manual tracking of applied migrations
- No rollback capability

**Solution:**
- Implement Phinx or similar migration tool
- Track applied migrations in database
- Add rollback support
- Document migration workflow

**Benefits:**
- Safer database changes
- Version control for schema
- Easier deployment across environments

**Note:** Migration files exist but aren't actively used. Audit needed.

---

## Completed ✅

### ✅ SoftBackfill System Documentation

**Completed:** December 22, 2024

**Problem:** Backfill logic was complex and poorly documented
**Solution:** Created comprehensive `SOFT_BACKFILL_SYSTEM.md`
**Result:** Clear understanding of real vs backfilled data distinction

### ✅ Minimum Backfill Date as Constant

**Completed:** December 22, 2024

**Problem:** Hardcoded date buried in algorithm code
**Solution:** Created `MIN_BACKFILL_DATE` class constant
**Result:** Easier to find and modify, self-documenting

---

## Notes

**Adding New Items:**
1. Describe the problem clearly
2. Propose a solution
3. Estimate effort (Low/Medium/High)
4. Assign priority based on impact
5. Reference related files/PRs

**Prioritization Criteria:**
- **High:** Blocks development or risks production issues
- **Medium:** Improves developer experience or code quality
- **Low:** Nice to have, future enhancement

**Review Schedule:**
- Review this document monthly
- Update status as work progresses
- Archive completed items with dates
