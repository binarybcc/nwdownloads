# Release Process

Reference material for Claude Code. Loaded on demand from CLAUDE.md pointer.

## Automated Release (using `standard-version`)

```bash
# Automatic version bump based on commits since last release
npm run release              # Auto-detects: feat=MINOR, fix=PATCH
npm run release:patch        # Force PATCH (bug fixes)
npm run release:minor        # Force MINOR (new features)
npm run release:major        # Force MAJOR (breaking changes)
npm run release:dry-run      # Preview without making changes
```

**What `standard-version` does automatically:**

1. Analyzes conventional commits since last release
2. Determines appropriate version bump (MAJOR/MINOR/PATCH)
3. Updates `package.json` version
4. Generates/updates `CHANGELOG.md` with grouped changes
5. Creates git commit: `chore(release): vX.Y.Z`
6. Creates git tag: `vX.Y.Z`

**Then push the release:**

```bash
git push --follow-tags origin master
```

## Manual Override (only if `standard-version` fails)

1. Update version in `package.json`
2. Update `CHANGELOG.md` with changes under appropriate section
3. Create git tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z: description"`
