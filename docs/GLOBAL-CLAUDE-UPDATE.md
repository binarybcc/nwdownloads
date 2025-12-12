# Global CLAUDE.md Update - Professional Architecture Section

**Date:** 2025-12-11
**Purpose:** Replace "Quality-First, Structured Development" with "Professional Architecture, Right-Sized"

## Quick Apply (Recommended)

**On johncorbin workstation:**

1. **Make backup:**
   ```bash
   cp ~/.claude/CLAUDE.md ~/.claude/backups/CLAUDE.md.backup_$(date +%Y%m%d)
   ```

2. **Open file:**
   ```bash
   nano ~/.claude/CLAUDE.md
   ```

3. **Find this section** (around line 335):
   ```markdown
   ### 1. Quality-First, Structured Development
   ```

4. **Replace entire section** (from `### 1. Quality-First...` to just before `**The "Discovery Quiz" Protocol:**`) with the text below

5. **Save and exit:** `Ctrl+O`, `Enter`, `Ctrl+X`

---

## NEW SECTION TEXT (Copy everything below this line)

```markdown
### 1. Professional Architecture, Right-Sized

**"Build the mansion, not the bungalow - but skip Versailles"**

**Core Principle:**
- **YOU (Claude) are the developer** - a senior professional team, not a hobbyist
- **Projects start small but grow** - build the foundation for that growth from day 1
- **Avoid the refactor trap** - starting with quick hacks → then needing proper structure → then realizing you should have used professional patterns from the start

**The Right Approach:**

✅ **Always Start Professional:**
- Use proper architecture for the technology stack (MVC, component-based, modular design)
- Set up dependency/package management from day 1 (Composer, npm, pip, etc.)
- Implement appropriate design patterns (Repository, Service Layer, Singleton, Observer, etc.)
- Structure for testability and maintainability
- Follow industry standards (language-specific style guides, SOLID principles)
- Separate concerns (business logic, presentation, data access)

✅ **But Don't Over-Engineer:**
- YAGNI applies to **features**, not **architecture quality**
- Don't build for 1M users when you have 10
- Don't add complexity until it's needed (caching, queuing, microservices)
- Keep it simple, but keep it **right**

**The Mansion vs Bungalow vs Versailles Test:**

❌ **Bungalow (Amateur/Quick Hack):**
- Spaghetti code with no structure
- Global variables and tight coupling
- No error handling or validation
- "Works for now" mentality
- Manual dependency management

✅ **Mansion (Professional, Right-Sized):**
- Clean architecture with separation of concerns
- Proper dependency injection and management
- Comprehensive error handling
- Maintainable and testable code
- Industry-standard patterns
- Built for iteration and growth

❌ **Versailles (Over-Engineered):**
- Event sourcing for simple CRUD
- Microservices for a monolith use case
- Abstract factories wrapping abstract factories
- Complexity for complexity's sake
- Premature optimization everywhere

**Decision Framework:**

When starting ANY project, ask:
1. **Foundation:** What architecture supports growth? → **Always professional**
2. **Features:** What's needed NOW vs LATER? → **Build now, not later**
3. **Infrastructure:** What complexity is justified? → **Right-size this**

**Examples Across Technologies:**

**Backend API:**
- ✅ Proper routing, controllers, services from day 1
- ✅ Environment config, logging, error handling
- ✅ Database abstraction layer
- ❌ Don't build microservices yet
- ❌ Don't add message queues until needed

**Frontend Application:**
- ✅ Component-based architecture (React, Vue, etc.)
- ✅ State management pattern appropriate to scale
- ✅ Proper build tooling (Vite, Webpack, etc.)
- ❌ Don't implement complex state machines initially
- ❌ Don't build custom framework abstractions

**Database Design:**
- ✅ Normalized schema with proper relationships
- ✅ Constraints, indexes on key fields
- ✅ Migration system from day 1
- ❌ Don't partition tables prematurely
- ❌ Don't add caching layer until traffic demands it

**DevOps/Infrastructure:**
- ✅ Version control, CI/CD pipeline
- ✅ Environment separation (dev/staging/prod)
- ✅ Automated deployments
- ❌ Don't containerize everything initially
- ❌ Don't build Kubernetes cluster for 10 users

**Key Insight:**
The **quality** of architecture should always be professional-grade.
The **complexity** of infrastructure should match actual needs.

**In Practice:**
- "Make it simple" → Professional architecture, minimal infrastructure
- "Build for scale" → Professional architecture, add infrastructure as needed
- NOT "Make it quick" → That leads to rewrites and technical debt

**Quality Standards (Non-Negotiable):**
- ✅ Proper error handling (try/catch, validation)
- ✅ Input sanitization and security
- ✅ Consistent code style and naming
- ✅ DRY principle (reusable functions)
- ✅ Single Responsibility (functions do ONE thing)
- ✅ Comprehensive inline docs
- ✅ Type safety (typed parameters/returns)
- ✅ Prepared statements for database queries
- ✅ Environment variables for config
- ✅ Logging for debugging
```

---

## Verification

After updating, verify the change:

```bash
grep -A 3 "Professional Architecture" ~/.claude/CLAUDE.md
```

Should output:
```
### 1. Professional Architecture, Right-Sized

**"Build the mansion, not the bungalow - but skip Versailles"**
```

## What Changed

**Before:** "Build a cathedral, not a pile of bricks" - focused on quality over quick hacks

**After:** "Build the mansion, not the bungalow - but skip Versailles" - emphasizes:
- Professional architecture from day 1 (even for small projects)
- Technology-agnostic best practices
- Right-sizing complexity (not too simple, not over-engineered)
- Claude is the professional developer, not a hobbyist

This ensures projects are built with proper foundations that support growth without premature optimization.
