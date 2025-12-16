#!/bin/bash
# Automated Code Quality Cleanup Script
# Phase 1: Critical File Organization
# 
# This script performs safe, reversible cleanup operations
# Run with: ./cleanup-phase1.sh
# Dry-run: ./cleanup-phase1.sh --dry-run

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo -e "${YELLOW}🔍 DRY RUN MODE - No changes will be made${NC}\n"
fi

# Logging
LOG_FILE="cleanup-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "=========================================="
echo "  Code Quality Cleanup - Phase 1"
echo "  Date: $(date)"
echo "=========================================="
echo ""

# Helper function
do_action() {
    if [ "$DRY_RUN" = true ]; then
        echo -e "${BLUE}[DRY RUN]${NC} $1"
    else
        echo -e "${GREEN}[EXECUTING]${NC} $1"
        eval "$2"
    fi
}

# Create backup before any changes
echo -e "${YELLOW}📦 Creating safety backup...${NC}"
BACKUP_DIR="backup-$(date +%Y%m%d-%H%M%S)"
do_action "Create backup directory" "mkdir -p $BACKUP_DIR"
do_action "Backup web/ directory" "cp -r web/ $BACKUP_DIR/web/"

echo ""
echo -e "${GREEN}✓${NC} Backup created at: $BACKUP_DIR"
echo ""

# ============================================================
# STEP 1: Archive old/backup files from web/ directory
# ============================================================
echo -e "${BLUE}Step 1: Archiving backup and old files${NC}"
echo "─────────────────────────────────────────"

do_action "Create archive structure" "mkdir -p archive/web-backups/$(date +%Y-%m)"

# Find and move backup files
echo "Finding .backup files..."
BACKUP_FILES=$(find web/ -maxdepth 1 -name "*.backup" 2>/dev/null || true)
if [ ! -z "$BACKUP_FILES" ]; then
    for file in $BACKUP_FILES; do
        filename=$(basename "$file")
        do_action "Archive $filename" "mv '$file' 'archive/web-backups/$(date +%Y-%m)/$filename'"
    done
    echo -e "${GREEN}✓${NC} Archived $(echo "$BACKUP_FILES" | wc -l) backup files"
else
    echo "  No .backup files found"
fi

# Find and move .old files
echo "Finding .old files..."
OLD_FILES=$(find web/ -maxdepth 1 -name "*.old*" 2>/dev/null || true)
if [ ! -z "$OLD_FILES" ]; then
    for file in $OLD_FILES; do
        filename=$(basename "$file")
        do_action "Archive $filename" "mv '$file' 'archive/web-backups/$(date +%Y-%m)/$filename'"
    done
    echo -e "${GREEN}✓${NC} Archived $(echo "$OLD_FILES" | wc -l) .old files"
else
    echo "  No .old files found"
fi

echo ""

# ============================================================
# STEP 2: Organize test and debug files
# ============================================================
echo -e "${BLUE}Step 2: Organizing test and debug files${NC}"
echo "─────────────────────────────────────────"

do_action "Create test directories" "mkdir -p tests/Legacy tests/Debug"

# Move test files
echo "Finding test_*.php files in web/..."
TEST_FILES=$(find web/ -maxdepth 1 -name "test_*.php" 2>/dev/null || true)
if [ ! -z "$TEST_FILES" ]; then
    for file in $TEST_FILES; do
        filename=$(basename "$file")
        do_action "Move $filename to tests/Legacy/" "mv '$file' 'tests/Legacy/$filename'"
    done
    echo -e "${GREEN}✓${NC} Moved $(echo "$TEST_FILES" | wc -l) test files"
else
    echo "  No test_*.php files found"
fi

# Move debug files
echo "Finding debug_*.php files in web/..."
DEBUG_FILES=$(find web/ -maxdepth 1 -name "debug_*.php" 2>/dev/null || true)
if [ ! -z "$DEBUG_FILES" ]; then
    for file in $DEBUG_FILES; do
        filename=$(basename "$file")
        do_action "Move $filename to tests/Debug/" "mv '$file' 'tests/Debug/$filename'"
    done
    echo -e "${GREEN}✓${NC} Moved $(echo "$DEBUG_FILES" | wc -l) debug files"
else
    echo "  No debug_*.php files found"
fi

echo ""

# ============================================================
# STEP 3: Create README files for organization
# ============================================================
echo -e "${BLUE}Step 3: Creating documentation READMEs${NC}"
echo "─────────────────────────────────────────"

# Tests directory README
if [ ! -f "tests/README.md" ]; then
    do_action "Create tests/README.md" "cat > tests/README.md << 'EOF'
# Testing Directory

## Structure

- **Unit/** - Isolated unit tests for individual functions/classes
- **Integration/** - Tests that verify component interactions
- **Legacy/** - Archived test scripts from web/ directory
- **Debug/** - Debug and diagnostic scripts

## Running Tests

### PHP Tests (PHPUnit)
\`\`\`bash
./vendor/bin/phpunit
\`\`\`

### JavaScript Tests (Vitest)
\`\`\`bash
npm test
\`\`\`

## Guidelines

- Keep test files organized by feature/module
- Name test files with \`Test.php\` or \`.test.js\` suffix
- Mock external dependencies
- Aim for 70%+ code coverage
EOF"
    echo -e "${GREEN}✓${NC} Created tests/README.md"
else
    echo "  tests/README.md already exists"
fi

# Archive directory README
if [ ! -f "archive/README.md" ]; then
    do_action "Create archive/README.md" "cat > archive/README.md << 'EOF'
# Archive Directory

This directory contains historical files, old implementations, and deprecated features.

## Structure

- **web-backups/** - Backup copies of web files (organized by date)
- **old-analysis-scripts/** - Legacy data analysis tools
- **old-test-scripts/** - Historical test files
- **old-deployments/** - Previous deployment packages
- **screenshots/** - Historical UI screenshots

## Policy

- Files are archived with timestamps
- Nothing in archive/ should be referenced by active code
- Review and purge files older than 1 year annually
- Document significant historical decisions in docs/decisions/
EOF"
    echo -e "${GREEN}✓${NC} Created archive/README.md"
else
    echo "  archive/README.md already exists"
fi

echo ""

# ============================================================
# STEP 4: Create necessary .gitignore entries
# ============================================================
echo -e "${BLUE}Step 4: Updating .gitignore${NC}"
echo "─────────────────────────────────────────"

GITIGNORE_ADDITIONS="
# Backup files (added by cleanup script)
*.backup
*.old
*.bak
*~

# Temporary test files
web/test_*.php
web/debug_*.php

# Cleanup logs
cleanup-*.log

# Safety backups
backup-*/
"

if [ -f ".gitignore" ]; then
    if ! grep -q "# Backup files (added by cleanup script)" .gitignore; then
        do_action "Append to .gitignore" "echo '$GITIGNORE_ADDITIONS' >> .gitignore"
        echo -e "${GREEN}✓${NC} Updated .gitignore"
    else
        echo "  .gitignore already contains cleanup entries"
    fi
else
    do_action "Create .gitignore" "echo '$GITIGNORE_ADDITIONS' > .gitignore"
    echo -e "${GREEN}✓${NC} Created .gitignore"
fi

echo ""

# ============================================================
# STEP 5: Generate cleanup report
# ============================================================
echo -e "${BLUE}Step 5: Generating cleanup report${NC}"
echo "─────────────────────────────────────────"

REPORT_FILE="cleanup-report-$(date +%Y%m%d-%H%M%S).md"

cat > "$REPORT_FILE" << EOF
# Code Cleanup Report
**Date:** $(date)
**Phase:** 1 - Critical File Organization

## Actions Taken

### Files Archived
- Backup files moved to: \`archive/web-backups/$(date +%Y-%m)/\`
- Test files moved to: \`tests/Legacy/\`
- Debug files moved to: \`tests/Debug/\`

### Directories Created
- \`archive/web-backups/$(date +%Y-%m)/\`
- \`tests/Legacy/\`
- \`tests/Debug/\`

### Documentation Added
- \`tests/README.md\`
- \`archive/README.md\`

### .gitignore Updated
Added entries for:
- Backup files (*.backup, *.old, *.bak)
- Temporary test files
- Cleanup logs

## File Count Summary

### Before Cleanup
\`\`\`
$(find web/ -type f | wc -l) files in web/
\`\`\`

### After Cleanup
\`\`\`
$(find web/ -type f | wc -l) files in web/ (production)
$(find tests/Legacy -type f 2>/dev/null | wc -l) files in tests/Legacy/
$(find tests/Debug -type f 2>/dev/null | wc -l) files in tests/Debug/
$(find archive/web-backups -type f 2>/dev/null | wc -l) files archived
\`\`\`

## Next Steps

1. Review moved files in archive/web-backups/
2. Verify application still works correctly
3. Run test suite: \`npm test && ./vendor/bin/phpunit\`
4. Proceed to Phase 2: Code Standards (see CODE_QUALITY_AUDIT_AND_CLEANUP_PLAN.md)

## Rollback Instructions

If needed, restore from backup:
\`\`\`bash
rm -rf web/
cp -r $BACKUP_DIR/web/ web/
\`\`\`

## Log File
Full execution log: \`$LOG_FILE\`

---
*Generated by cleanup-phase1.sh*
EOF

echo -e "${GREEN}✓${NC} Report generated: $REPORT_FILE"
echo ""

# ============================================================
# FINAL SUMMARY
# ============================================================
echo ""
echo "=========================================="
echo -e "${GREEN}✓ Phase 1 Cleanup Complete${NC}"
echo "=========================================="
echo ""
echo "📄 Report saved to: $REPORT_FILE"
echo "📝 Full log saved to: $LOG_FILE"
echo "💾 Backup created at: $BACKUP_DIR"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Review the cleanup report"
echo "2. Test your application: visit http://localhost"
echo "3. Run tests: npm test && ./vendor/bin/phpunit"
echo "4. If everything looks good, commit changes"
echo "5. Proceed to Phase 2 (see CODE_QUALITY_AUDIT_AND_CLEANUP_PLAN.md)"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}⚠️  This was a DRY RUN - no changes were made${NC}"
    echo "   Run without --dry-run to execute cleanup"
    echo ""
fi
