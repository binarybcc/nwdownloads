# 🚨 MANDATORY GIT WORKFLOW - READ BEFORE EVERY COMMIT

## ⛔ CRITICAL RULE: NEVER COMMIT DIRECTLY TO MASTER

**ALL changes MUST go through Pull Requests. NO EXCEPTIONS.**

---

## ✅ Proper Workflow for EVERY Change

### **Before Making ANY Code Changes:**

```bash
# 1. Start on master and pull latest
git checkout master
git pull origin master

# 2. Create feature branch with descriptive name
git checkout -b feature/your-feature-name
# OR
git checkout -b fix/bug-description
```

### **Branch Naming Convention:**
- `feature/description` - New functionality
- `fix/description` - Bug fixes
- `refactor/description` - Code improvements
- `docs/description` - Documentation updates

### **After Making Changes:**

```bash
# 1. Stage and commit
git add .
git commit -m "Clear description of changes"

# 2. Push branch to GitHub
git push -u origin feature/your-feature-name

# 3. Create Pull Request
gh pr create \
  --title "Clear PR title" \
  --body "## Summary
- What changed
- Why it changed
- How to test

## Testing:
✅ Tested on Development
✅ Verified functionality

## Ready for review"

# 4. Review (optional - can review yourself or ask user)
gh pr view --web

# 5. Merge when ready
gh pr merge --squash

# 6. Clean up
git checkout master
git pull origin master
git branch -d feature/your-feature-name
```

---

## 🔴 STOP Checklist Before Committing

**Before running `git commit`, ask yourself:**

- [ ] Am I on a feature branch? (`git branch --show-current`)
- [ ] Did I create a PR? (NOT committing directly to master?)
- [ ] Have I tested the changes in Development?

**If ANY answer is "No" - STOP and fix it!**

---

## Quick Reference

**Create branch:**
```bash
git checkout -b feature/my-feature
```

**Push & create PR:**
```bash
git push -u origin $(git branch --show-current)
gh pr create --title "Title" --body "Description"
```

**Merge PR:**
```bash
gh pr merge --squash
```

**Clean up:**
```bash
git checkout master && git pull && git branch -d feature/my-feature
```

---

## Why This Matters

✅ **Safety** - Code review before production
✅ **History** - Clear record of changes
✅ **Quality** - Catch bugs before deployment
✅ **Rollback** - Easy to revert if needed
✅ **Collaboration** - Team can review and discuss

---

**Last Updated:** 2025-12-09
**This is MANDATORY - not optional!**
