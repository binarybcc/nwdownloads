# Cache-Busting System - Browser Cache Prevention

## 🎯 Purpose

Prevents end users from seeing outdated JavaScript after deployments by forcing browsers to fetch fresh files.

---

## 🔧 How It Works

**Version Query Parameters:**
```html
<script src="assets/detail_panel.js?v=20251205"></script>
```

- Browser sees `?v=20251205` as part of the URL
- When version changes to `?v=20251206`, browser treats it as a NEW file
- Old cached version is ignored automatically
- **No manual user cache clearing required!**

---

## 🚀 Automated Workflow

### Using the Deployment Skill (Recommended)

The `/deploy-nwdownloads` skill **automatically handles everything**:

1. Runs version updater script
2. Updates all `?v=YYYYMMDD` to today's date
3. Deploys index.html + all changed files
4. Reports deployment status

**Just use the skill - version updates happen automatically!**

### Manual Script Usage

If deploying without the skill:

```bash
# Navigate to project
cd /Users/johncorbin/Desktop/projs/nwdownloads

# Run version updater
./scripts/update-version.sh

# Review changes
git diff web/index.html

# Then deploy normally
```

---

## 📝 Version Format

**Format**: `?v=YYYYMMDD`

**Examples:**
- December 5, 2025 → `?v=20251205`
- January 15, 2026 → `?v=20260115`

**Why Date Format?**
- Easy to understand when code was deployed
- Chronological sorting
- No manual version tracking needed

---

## 🔍 What Gets Versioned

**All JavaScript files in index.html** (lines 866-883):

```html
<script src="assets/app.js?v=20251205"></script>
<script src="assets/app_phase2_enhancements.js?v=20251205"></script>
<script src="assets/state-icons.js?v=20251205"></script>
<script src="assets/chart-layout-manager.js?v=20251205"></script>
<script src="assets/donut-to-state-animation.js?v=20251205"></script>
<script src="assets/detail_panel.js?v=20251205"></script>
<script src="assets/ui-enhancements.js?v=20251205"></script>
<script src="assets/context-menu.js?v=20251205"></script>
<script src="assets/export-utils.js?v=20251205"></script>
<script src="assets/subscriber-table-panel.js?v=20251205"></script>
<script src="assets/chart-transition-manager.js?v=20251205"></script>
<script src="assets/chart-context-integration.js?v=20251205"></script>
```

**All files use the SAME version number** - easier to manage, deploy together.

---

## ✅ Best Practices

### When to Update Version

**UPDATE version when:**
- ✅ Deploying JavaScript changes
- ✅ Fixing bugs in JS files
- ✅ Adding new features
- ✅ Modifying any .js file

**DON'T update version when:**
- ❌ Only changing PHP files (upload.php, api.php)
- ❌ Only changing CSS
- ❌ Only changing database

### Deployment Checklist

1. ✅ Make JavaScript changes in development
2. ✅ Test in local environment
3. ✅ Run deployment skill (auto-updates version)
4. ✅ Verify production deployment
5. ✅ Test in production (no cache clearing needed!)

---

## 🛠️ Files Involved

**Version Updater Script:**
- Location: `/scripts/update-version.sh`
- Purpose: Automatically updates all version dates
- Usage: Run before deployment (or use skill)

**Deployment Skill:**
- Location: `~/.claude/skills/deploy-nwdownloads.md`
- Purpose: Full automated deployment
- Includes: Version update + file transfer + instructions

**Index HTML:**
- Location: `/web/index.html`
- Lines: 866-883 (script tags with versions)
- Pattern: `?v=YYYYMMDD` on all JS includes

---

## 🔧 Troubleshooting

### Users Still Seeing Old Code

**Unlikely but possible if:**
1. Container wasn't restarted after deployment
2. Version wasn't actually updated in index.html
3. Browser has aggressive caching policy

**Solution:**
```bash
# Verify version on production
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 'grep "?v=" /volume1/docker/nwdownloads/web/index.html | head -3'

# Should show today's date, e.g., ?v=20251205
```

### Version Update Failed

**If script fails:**
```bash
# Check script exists and is executable
ls -la scripts/update-version.sh

# Make executable if needed
chmod +x scripts/update-version.sh

# Run manually with verbose output
bash -x scripts/update-version.sh
```

### Need to Rollback Version

**If deployment needs rollback:**
```bash
# Restore backup created by script
cp web/index.html.backup web/index.html

# Or manually edit version date
# Change ?v=20251206 back to ?v=20251205
```

---

## 📊 Benefits

**Before Cache-Busting:**
- ❌ Users see old code after deployment
- ❌ Support requests: "It's not working!"
- ❌ Manual instructions: "Clear your cache"
- ❌ Confusion and frustration

**After Cache-Busting:**
- ✅ Users automatically get latest code
- ✅ Zero support requests about caching
- ✅ Smooth deployments
- ✅ Professional user experience

---

## 🔮 Future Enhancements

**Potential Improvements:**
- Build-time version generation (hash-based)
- Service worker cache invalidation
- CDN cache purging integration
- Automated testing of version updates

**Current system is sufficient for:**
- Small to medium deployment frequency
- Internal business applications
- Controlled user base

---

## 📖 Related Documentation

- **Deployment Guide**: `/docs/DEPLOYMENT-2025-12-05.md`
- **Deployment Skill**: `~/.claude/skills/deploy-nwdownloads.md`
- **Version Script**: `/scripts/update-version.sh`

---

**Implementation Date**: 2025-12-05
**Status**: ✅ Active
**Automation**: Integrated into deployment skill
**Maintenance**: Automatic via script
