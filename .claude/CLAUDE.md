# NWDownloads Project - Circulation Dashboard

Newspaper circulation dashboard for tracking subscriber metrics across multiple business units and publications.

## Version & Standards

**Current Version:** v2.0.0 (in `package.json`) | **Changelog:** `CHANGELOG.md`

**Commits:** Conventional format `type(scope): description` — Types: feat, fix, docs, style, refactor, test, chore, perf, security

**Branches:** `master` (production) | `feature/*` | `fix/*` | `hotfix/*`

**Code:** PHP: snake_case/PascalCase | JS: camelCase | Files: kebab-case (assets), snake_case (PHP)

**Versioning:** Automated via `npm run release` (standard-version). See `docs/operations-reference.md` for details.

## Infrastructure

- **Production:** Native Synology Apache + PHP 8.2 + MariaDB 10 at `/volume1/web/circulation/`
- **Production URL:** http://192.168.1.254:8081 (also https://cdash.upstatetoday.com)
- **NO DOCKER** — this project does not use Docker in any capacity
- **Deploy:** Copy files to NAS via `ssh nas`, place in `/volume1/web/circulation/`\*\*

## Production Operations Protocol

Before ANY production operation, Claude MUST:

1. Source `.env.credentials` first
2. Read `docs/KNOWLEDGE-BASE.md` for connection details
3. Follow the 3-attempt rule — if it takes 3+ attempts, read the docs

**Key details:** DB via Unix socket (`$PROD_DB_SOCKET`) | Web dir: `/volume1/web/circulation/` | Deploy: git pull + rsync

See `docs/operations-reference.md` for all commands, credentials setup, and environment details.

## Git Workflow

**NEVER commit directly to master.** All changes go through Pull Requests.

Claude will ask: "Should I create a PR for review, or merge directly?"

See `docs/git-workflow-reference.md` for full workflow, examples, and quick reference commands.

## Upload Interface

**CANONICAL:** `upload_unified.php` — the ONLY upload interface (handles Subscribers AND Vacations). Do not use `upload.html` directly.

See `docs/data-upload-reference.md` for weekly process, publications, schema, and troubleshooting.

## Documentation

| Doc                              | Purpose                                                    |
| -------------------------------- | ---------------------------------------------------------- |
| `docs/DESIGN-SYSTEM.md`          | Component library and UI patterns (READ FIRST for UI work) |
| `docs/KNOWLEDGE-BASE.md`         | Architecture, schemas, API endpoints, deployment           |
| `docs/TROUBLESHOOTING.md`        | Decision tree diagnostics for 9 issue categories           |
| `docs/operations-reference.md`   | Versioning, credentials, deployment, DB commands           |
| `docs/git-workflow-reference.md` | PR workflow, branch naming, examples                       |
| `docs/data-upload-reference.md`  | Upload process, publications, data notes                   |
| `docs/cost_analysis.md`          | Development cost analysis & ROI                            |
| `docs/ARCHIVE/`                  | Historical documentation (33+ files)                       |

## File Organization

```
/web/          - PHP application and API
/database/     - Migrations and init scripts
/scripts/      - CLI tools, deployment, backup/restore
/docs/         - All documentation
/tests/        - Test infrastructure
```
