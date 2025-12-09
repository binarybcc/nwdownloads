# 🚨 READ THIS FIRST - YOU WILL FORGET THIS EXISTS 🚨

## You Created an Automated Deployment System!

**Date Created:** December 9, 2025
**Why:** You kept forgetting manual deployment steps and things broke

---

## ⚡ The One Command That Does Everything:

```bash
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 '~/scripts/deploy-production.sh'
```

**That's it. That's the whole deployment process.**

---

## 🎯 What This Command Does Automatically:

1. ✅ Pulls latest code from GitHub
2. ✅ Runs all pending database migrations
3. ✅ Fixes vacation data integrity issues
4. ✅ Syncs vacation counts to API tables
5. ✅ Deploys all files (PHP, JS, CSS, HTML)
6. ✅ Fixes file permissions
7. ✅ Verifies CSS files deployed correctly
8. ✅ Runs post-deployment health checks

**Colored output shows you exactly what's happening.**

---

## 🔴 STOP! Before You Try to Deploy Manually:

### ❌ DON'T DO THIS ANYMORE:
- ~~Manually copy CSS files~~
- ~~SSH in and run SQL commands~~
- ~~Fix vacation flags manually~~
- ~~Rsync files by hand~~
- ~~Chmod files one by one~~
- ~~Hope everything worked~~

### ✅ DO THIS INSTEAD:
```bash
# Just run the deployment script
~/scripts/deploy-production.sh
```

---

## 📋 Problems This Solved:

### Problem #1: CSS Files Not Deploying
**Before:** Dashboard showed giant cloud icon (no CSS)
**Now:** Automatically verifies CSS deployed and correct size

### Problem #2: Vacation Data Showing Zeros
**Before:** Data existed but dashboard showed 0 vacations
**Now:** Automatically syncs subscriber_snapshots → daily_snapshots

### Problem #3: Database Migrations Forgotten
**Before:** Had to remember which migrations to run
**Now:** Tracks migrations in `.migrations.log`, runs pending ones

### Problem #4: Manual Multi-Step Process
**Before:** 10+ commands to deploy correctly
**Now:** 1 command does everything

---

## 📖 Full Documentation:

**Deployment Checklist:** `docs/DEPLOYMENT-CHECKLIST.md`
- Pre-deployment checklist
- Step-by-step guide
- Troubleshooting
- Emergency rollback

**Deployment Script:** `scripts/deploy-production.sh`
- Production location: `/volume1/homes/it/scripts/deploy-production.sh`
- Colored output
- Error handling
- Comprehensive verification

---

## 🔧 How to Update the Deployment Script:

If you need to modify the deployment script:

```bash
# 1. Edit locally
vim scripts/deploy-production.sh

# 2. Commit and push
git add scripts/deploy-production.sh
git commit -m "Update deployment script"
git push

# 3. Copy to production
cat scripts/deploy-production.sh | \
  sshpass -p 'Mojave48ice' ssh it@192.168.1.254 \
  "cat > ~/scripts/deploy-production.sh && chmod +x ~/scripts/deploy-production.sh && sed -i 's/\r$//' ~/scripts/deploy-production.sh"
```

---

## 🎯 Git Workflow Reminder:

**NEVER commit directly to master!** Use the PR workflow:

```bash
# 1. Create feature branch
git checkout -b feature/my-changes

# 2. Make changes and commit
git add .
git commit -m "Description of changes"

# 3. Push and create PR
git push -u origin feature/my-changes
gh pr create --title "Title" --body "Description"

# 4. Merge PR
gh pr merge --squash

# 5. Deploy to production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 '~/scripts/deploy-production.sh'
```

**See:** `.claude/GIT-WORKFLOW.md` for complete workflow

---

## 🆘 If Something Goes Wrong:

### The Script Failed - What Now?

1. **Read the error message** - Script shows exactly what failed
2. **Check the logs** - Script output is colored and detailed
3. **See troubleshooting** - `docs/DEPLOYMENT-CHECKLIST.md` has solutions
4. **Ask Claude** - "The deployment script failed with [error]"

### Emergency Rollback

```bash
# SSH into production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254

# Rollback code to previous commit
cd /volume1/homes/it/circulation-deploy
git log --oneline -5  # Find previous commit
git checkout <commit-hash>

# Re-deploy
~/scripts/deploy-production.sh
```

---

## 💡 You Will Forget This - That's OK!

**When you need to deploy and can't remember how:**

1. Open this file: `README-DEPLOYMENT.md` (this file!)
2. Run the one command at the top
3. Watch it do everything automatically
4. Refresh the dashboard

**That's it!**

---

**Last Updated:** 2025-12-09
**Created by:** Claude (during the "vacation data zero" debugging session)
**Why it exists:** So you don't have to remember 20 manual steps
