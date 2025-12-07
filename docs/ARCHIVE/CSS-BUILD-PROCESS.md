# CSS Build Process

## Overview

This project uses **Tailwind CSS** for styling. The CSS must be built before the application will display correctly.

**Generated file:** `web/assets/output.css` (gitignored - must be built locally)

---

## Prerequisites

- Node.js v18+ (v22.17.0 confirmed working)
- npm v9+ (v11.6.1 confirmed working)

---

## One-Time Setup (New Workstation)

When you first clone the project or switch to a new workstation:

```bash
# Navigate to project
cd $PROJECT_ROOT

# Install dependencies
npm install

# Build CSS
npm run build:css
```

**Result:** Creates `web/assets/output.css` (~19KB minified)

---

## Development Workflow

### Build CSS Once

```bash
npm run build:css
```

Use this when:
- Starting work on the project
- After pulling changes that affect HTML/Tailwind classes
- CSS isn't displaying correctly

### Watch Mode (Recommended for active development)

```bash
npm run watch:css
```

or

```bash
npm run dev
```

**What it does:**
- Watches for changes to `./web/**/*.{html,js}`
- Automatically rebuilds CSS when Tailwind classes change
- Runs in background - keep terminal open

**When to use:**
- Actively working on UI/styling
- Making changes to HTML templates
- Adding new Tailwind classes

---

## How It Works

### File Structure

```
/src/input.css          - Tailwind directives (source)
/tailwind.config.js     - Tailwind configuration
/package.json           - Build scripts and dependencies
/web/assets/output.css  - Generated CSS (gitignored)
```

### Build Process

1. **Input:** `src/input.css` contains Tailwind directives:
   ```css
   @tailwind base;
   @tailwind components;
   @tailwind utilities;
   ```

2. **Scan:** Tailwind scans `./web/**/*.{html,js}` for class usage

3. **Generate:** Creates optimized CSS with only used classes

4. **Output:** Minified CSS written to `web/assets/output.css`

### Configuration

**tailwind.config.js:**
```javascript
module.exports = {
  content: [
    "./web/**/*.{html,js}",  // Files to scan for Tailwind classes
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

---

## Troubleshooting

### Page has no styling (blue shapes only)

**Problem:** `output.css` doesn't exist
**Solution:**
```bash
npm run build:css
```

### CSS changes not reflecting

**Problem:** CSS not rebuilt after changes
**Solution:**
```bash
# Option 1: Manual rebuild
npm run build:css

# Option 2: Use watch mode
npm run watch:css
```

### "Module not found" errors

**Problem:** Dependencies not installed
**Solution:**
```bash
npm install
```

### Old CSS cached in browser

**Problem:** Browser cached old CSS
**Solution:**
1. Hard refresh: Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)
2. Or change version parameter in index.php: `output.css?v=20251206`

---

## Production Deployment

### Docker Hub Image Build

The CSS **must be built BEFORE** building the Docker image:

```bash
# Build CSS first
npm run build:css

# Then build Docker image
./build-and-push.sh
```

**Why:** The Dockerfile copies `web/` into the image. If `output.css` doesn't exist locally, it won't be in the image.

### Dockerfile Integration

The Dockerfile already copies everything:
```dockerfile
COPY web/ /var/www/html/
```

Just ensure `output.css` exists before running `docker build`.

---

## Scripts Reference

| Script | Command | Purpose |
|--------|---------|---------|
| `npm run build:css` | Build CSS once | One-time build |
| `npm run watch:css` | Watch and rebuild | Development mode |
| `npm run dev` | Same as watch | Development shortcut |

---

## Why Is output.css Gitignored?

**Reasons:**
1. ✅ **Generated file** - Like compiled code, shouldn't be in source control
2. ✅ **Large and changes frequently** - Git history bloat
3. ✅ **Environment-specific** - May differ between dev/prod builds
4. ✅ **Forces proper build process** - Ensures developers run build steps

**How it works across workstations:**
- Primary: Builds locally with `npm run build:css`
- Secondary: Builds locally with `npm run build:css`
- Production: Baked into Docker image during build

---

## Common Workflow Examples

### Starting Work (Fresh Clone)

```bash
cd $PROJECT_ROOT
npm install
npm run build:css
docker compose up -d
# Open http://localhost:8081/
```

### Active UI Development

```bash
# Terminal 1: Watch CSS
npm run dev

# Terminal 2: Docker containers
docker compose up -d

# Edit web/*.html or web/assets/*.js
# CSS automatically rebuilds!
```

### Before Committing Changes

```bash
# NO need to commit output.css - it's gitignored
git add web/  # Add HTML/JS changes
git commit -m "Update UI styling"
```

### Before Deploying to Production

```bash
# Ensure CSS is built
npm run build:css

# Build and push Docker image
./build-and-push.sh

# Deploy to NAS
# (See docs/docker-hub-workflow.md)
```

---

## Best Practices

1. ✅ **Always build CSS after pulling** - Other developers may have added classes
2. ✅ **Use watch mode during development** - Automatic rebuilds
3. ✅ **Build before Docker image** - Ensures CSS is in the image
4. ✅ **Don't commit output.css** - It's gitignored for a reason
5. ✅ **Document CSS changes** - If you add custom Tailwind classes

---

**Last Updated:** 2025-12-06
**Tailwind Version:** 3.4.0
