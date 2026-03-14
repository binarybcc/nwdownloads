# Git Workflow Reference

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## CRITICAL RULE: NEVER COMMIT DIRECTLY TO MASTER

All changes MUST go through Pull Requests (PRs). No exceptions.

## Why Pull Requests?

- Code review before deployment
- Catch bugs before Production
- Test in Development environment first
- Easy rollback if issues occur
- Clear history of what changed and why
- `@claude` can review code automatically

## The Proper Workflow

### Step 1: Start with a Feature Branch

```bash
git checkout master
git pull origin master
git checkout -b feature/add-weekly-trends
```

**Branch Naming:**
- `feature/description` - New functionality
- `fix/description` - Bug fixes
- `refactor/description` - Code improvements
- `docs/description` - Documentation updates

### Step 2: Make Changes and Commit

```bash
git add .
git commit -m "Add weekly trend comparison chart

- Added trend chart component
- Updated dashboard layout
- Added API endpoint for trend data
- Tested with last 4 weeks of data"
```

### Step 3: Push Branch

```bash
git push -u origin feature/add-weekly-trends
```

### Step 4: Create Pull Request

```bash
gh pr create \
  --title "Add weekly trend comparison chart" \
  --body "## Summary
Adds a new chart showing week-over-week subscriber trends by business unit.

## Changes:
- Added trend chart component to dashboard
- Created new API endpoint: /api/get_weekly_trends.php

## Testing:
- Tested with last 4 weeks of data
- Verified on Development environment (localhost:8081)

## Database Impact:
- No schema changes

## Deployment Notes:
- Test on Development first
- Deploy to Production after approval"
```

### Step 5: Review, Merge, Clean Up

```bash
# Review
gh pr view --web
gh pr checks

# Merge
gh pr merge --squash

# Clean up
git checkout master && git pull && git branch -d feature/my-feature
```

## Quick Reference Commands

| Action | Command |
|--------|---------|
| Start new work | `git checkout master && git pull && git checkout -b feature/my-feature` |
| Create PR | `git push -u origin $(git branch --show-current) && gh pr create` |
| Review PR | `gh pr view --web` |
| Merge PR | `gh pr merge --squash` |
| Clean up | `git checkout master && git pull && git branch -d feature/my-feature` |

## Common Scenarios

**Small Bug Fix:**
```bash
git checkout -b fix/typo-in-upload-form
# Fix the typo
git commit -m "Fix typo in upload form label"
git push -u origin fix/typo-in-upload-form
gh pr create --title "Fix typo in upload form" --body "Corrects 'Uplaod' to 'Upload'"
gh pr merge --squash
git checkout master && git pull
```

**Emergency Production Fix:**
```bash
git checkout -b hotfix/database-connection-error
# Fix the critical issue
git commit -m "Fix database connection timeout in production"
git push -u origin hotfix/database-connection-error
gh pr create --title "HOTFIX: Database connection timeout" --body "Critical fix"
# Get quick review, merge, deploy immediately
```

## Claude's Responsibilities

1. Create a feature branch (NEVER commit to master)
2. Make changes on the branch
3. Test in Development environment
4. Create PR with detailed description
5. Ask: "Should I create a PR for review, or merge directly?"
6. Clean up branch after merge

## Full Development to Production Flow

1. Create feature branch
2. Make changes in Development environment
3. Test thoroughly locally (http://localhost:8081)
4. Create Pull Request
5. Review (manual or `@claude` review)
6. Merge to master on GitHub
7. Deploy to Production via deployment script
8. Verify at https://cdash.upstatetoday.com
