# Credentials Management

This project uses a structured approach to credential management with git-ignored files.

## Files Overview

### 🔐 Credential Files (Git-Ignored)

All credential files are protected by `.gitignore` and will **NEVER** be committed to version control.

| File | Purpose | When to Use |
|------|---------|-------------|
| `.passwords` | Master quick reference | Quick lookup of all credentials |
| `.env.production.credentials` | Production environment variables | When deploying to production NAS |
| `.env.local.credentials` | Local development variables | When running local Docker environment |

### 📝 Configuration Files (Committed)

| File | Purpose | Safe to Commit? |
|------|---------|-----------------|
| `.env.example` | Template with placeholder values | ✅ Yes |
| `.env` | Active local environment | ❌ No (contains real values) |

## Quick Access

### View Master Password File
```bash
cat .passwords
```

### View Production Credentials
```bash
cat .env.production.credentials
```

### View Local Development Credentials
```bash
cat .env.local.credentials
```

## Common Scenarios

### "I need to SSH into production"
```bash
# Look in .passwords under "PRODUCTION NAS SSH"
# Or:
cat .passwords | grep -A 3 "PRODUCTION NAS SSH"
```

### "I need to connect to production database"
```bash
# Look in .env.production.credentials or .passwords
cat .env.production.credentials | grep DB_ROOT_PASSWORD
```

### "I forgot the local database password"
```bash
cat .env.local.credentials | grep DB_ROOT_PASSWORD
```

## Security Best Practices

1. ✅ **Never commit credentials** - All credential files are git-ignored
2. ✅ **Keep files in sync** - Update `.passwords` when credentials change
3. ✅ **Different passwords** - Production uses different passwords than local
4. ✅ **Rotate regularly** - Update passwords periodically for security
5. ✅ **Document changes** - Add date/notes to credential files when updating

## File Protection

The `.gitignore` protects these patterns:
- `*.credentials`
- `.passwords`
- `.env.local`
- `.env.production`

### Verify Protection
```bash
git status --porcelain | grep credentials
# Should return nothing if properly ignored
```

## Adding New Credentials

When adding new services/credentials:

1. **Update `.passwords`** - Add to quick reference section
2. **Update appropriate `.env.*.credentials`** - Add full environment variables
3. **Document in this README** - Update tables if needed
4. **Test git ignore** - Run `git status` to ensure not tracked

## Troubleshooting

### "I accidentally staged a credential file"
```bash
git reset HEAD .passwords
git reset HEAD .env.production.credentials
```

### "My credential file was committed"
```bash
# Remove from git history (dangerous!)
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .passwords" \
  --prune-empty --tag-name-filter cat -- --all

# Then force push (coordinate with team first!)
git push origin --force --all
```

### "I can't find a password"
1. Check `.passwords` first (master reference)
2. Check specific `.env.*.credentials` file
3. Check memory tool: credentials are also stored in Claude's memory

## Integration with Claude

All credentials are also stored in Claude Code's memory system for easy reference during development sessions. Ask Claude to retrieve credentials if needed.

## Emergency Access

If credential files are lost:

1. **Production NAS**: Contact IT department
2. **Production Database**: SSH to NAS, check docker-compose.yml
3. **Local Database**: Check local docker-compose.yml
4. **Claude Memory**: Ask Claude to retrieve from memory store

## Maintenance Schedule

- **Weekly**: Review access logs
- **Monthly**: Verify all credentials still work
- **Quarterly**: Consider password rotation
- **Annually**: Full security audit

---

**Last Updated**: 2025-12-02
**Maintained By**: Development Team
