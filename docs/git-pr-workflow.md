# Git Pull Request Workflow

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## The Proper Workflow (For Every Feature/Fix)

### Step 1: Start with a Feature Branch

```bash
# BEFORE making ANY changes
git checkout master
git pull origin master  # Get latest code

# Create descriptive branch name
git checkout -b feature/add-weekly-trends
# OR
git checkout -b fix/dashboard-loading-error
```

**Branch Naming Convention:**

- `feature/description` - New functionality
- `fix/description` - Bug fixes
- `refactor/description` - Code improvements
- `docs/description` - Documentation updates

### Step 2: Make Changes and Commit

```bash
# Make your changes to files
# Test locally in Development environment

git add .
git commit -m "Add weekly trend comparison chart

- Added trend chart component
- Updated dashboard layout
- Added API endpoint for trend data
- Tested with last 4 weeks of data"
```

**Commit Message Format:**

```
Short summary (50 chars or less)

Detailed explanation:
- What changed
- Why it changed
- How to test it
```

### Step 3: Push Branch to GitHub

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
- Updated dashboard layout to accommodate chart
- Added database query for trend calculations

## Testing:
- Tested with last 4 weeks of data
- Verified on Development environment (cdash.upstatetoday.com)
- Checked mobile and desktop layouts
- Validated data accuracy against raw reports

## Database Impact:
- No schema changes
- Uses existing daily_snapshots table
- Query performance: <100ms

## Deployment Notes:
- Test on Development first
- Deploy to Production after approval
- No additional configuration needed"
```

### Step 5: Review with Claude

```bash
# In GitHub, add comment to PR:
@claude review this code for:
- Security vulnerabilities
- Performance issues
- Code quality
- Database query optimization
```

### Step 6: Test on Development

```bash
git checkout feature/add-weekly-trends
# Test changes locally, then deploy to NAS and verify at https://cdash.upstatetoday.com
```

### Step 7: Merge When Ready

```bash
# Option A: Via CLI
gh pr merge --squash  # Squashes commits into one

# Option B: Via GitHub Web
# Click "Merge pull request" button
```

### Step 8: Clean Up

```bash
git checkout master
git pull origin master  # Get the merged changes
git branch -d feature/add-weekly-trends
git push origin --delete feature/add-weekly-trends  # optional
```

## Quick Reference Commands

| Action         | Command                                                                                                |
| -------------- | ------------------------------------------------------------------------------------------------------ |
| Start new work | `git checkout master && git pull && git checkout -b feature/my-feature`                                |
| Create PR      | `git push -u origin $(git branch --show-current) && gh pr create --title "Title" --body "Description"` |
| Review PR      | `gh pr view --web` or `gh pr checks`                                                                   |
| Merge PR       | `gh pr merge --squash`                                                                                 |
| Clean up       | `git checkout master && git pull && git branch -d feature/my-feature`                                  |

## Common Scenarios

**Small Bug Fix:**

```bash
git checkout -b fix/typo-in-upload-form
# Fix the typo
git add web/upload_unified.php
git commit -m "Fix typo in upload form label"
git push -u origin fix/typo-in-upload-form
gh pr create --title "Fix typo in upload form" --body "Corrects 'Uplaod' to 'Upload'"
gh pr merge --squash
git checkout master && git pull
```

**Large Feature:**

```bash
git checkout -b feature/subscriber-alerts
# Work on feature over several commits
git commit -m "Add alert data model"
git commit -m "Add alert UI components"
git commit -m "Add email notification system"
git push -u origin feature/subscriber-alerts
gh pr create --title "Add subscriber alert system" --body "[detailed description]"
# Wait for review, make adjustments
gh pr merge --squash
```

**Emergency Production Fix:**

```bash
git checkout -b hotfix/database-connection-error
# Fix the critical issue
git add .
git commit -m "Fix database connection timeout in production"
git push -u origin hotfix/database-connection-error
gh pr create --title "HOTFIX: Database connection timeout" --body "Critical fix for production issue"
gh pr merge --squash
# Deploy to production immediately
```

## What NOT to Do

- **Direct to Master:** `git push origin master` — NEVER DO THIS
- **No Testing:** Merging PRs without testing in Development first
- **Vague PRs:** `--title "Changes" --body "Updated stuff"` — always describe what and why

## Claude's Responsibilities

When implementing features, Claude MUST:

1. Create a feature branch (NEVER commit to master)
2. Make changes on the branch
3. Test in Development environment
4. Create PR with detailed description
5. Wait for approval before merging
6. Clean up branch after merge

**Claude will ask:** "Should I create a PR for review, or merge directly?"

- **"merge"** → Create PR, merge immediately, clean up
- **"review"** → Create PR, wait for approval, then merge and clean up

## Integration with Deployment

**Full Development → Production Flow:**

1. Create feature branch
2. Make changes in Development environment
3. Test thoroughly locally (http://cdash.upstatetoday.com)
4. Create Pull Request
5. Review (manual or `@claude` review)
6. Merge to master on GitHub
7. Deploy to Production via deployment script
8. Verify at https://cdash.upstatetoday.com
