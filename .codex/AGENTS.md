# Agent Instructions

You are working inside the KEY Restaurant & Coffeehouse repository.

Project stack:
- PHP 8.1
- MySQL/MariaDB
- Classic PHP admin panel
- Persian RTL UI
- DirectAdmin/cPanel compatible
- No Laravel
- No Composer dependency unless already present
- No Node build unless already present
- No SPA rewrite

## Main Mission

Help with:
- Code review
- Safe refactoring
- Bug fixing
- Schema repair
- Admin panel cleanup
- Security hardening
- UI/UX improvement
- Reporting and analytics
- Deployment preparation
- Documentation

## Critical Behavior

- Preserve the current architecture unless the task explicitly asks for a refactor.
- Make small, reviewable changes.
- Do not rewrite the whole system.
- Do not remove existing features without explicit approval.
- Do not break admin login.
- Do not break public website pages.
- Do not touch unrelated modules during a scoped task.
- Do not introduce Laravel, Composer dependencies, Node builds, or SPA architecture.
- Follow existing PHP patterns, bootstrap files, auth guards, CSRF helpers, database helpers, and UI conventions.
- Keep Persian RTL compatibility.
- Prefer backward compatibility.

## Before Changing Code

1. Inspect the current file.
2. Inspect related includes, routes, helpers, services, database schema, migrations, and menu entries.
3. Identify existing naming patterns.
4. Identify affected tables and columns.
5. Check whether a similar implementation already exists.
6. Explain the intended change.
7. Apply the smallest safe change.
8. Run syntax checks where possible.

## Required Safety

- Never expose secrets, passwords, tokens, API keys, session IDs, cookies, or database credentials.
- Never show raw SQL/database/PHP errors to end users.
- Never concatenate raw user input into SQL.
- Use prepared statements or existing safe database helpers.
- Use CSRF protection for write actions.
- Validate and sanitize input.
- Escape output.
- Check authorization before sensitive actions.
- Log important admin actions safely.

## Database Rules

- Never run DROP or TRUNCATE unless the task explicitly allows it for a specific module.
- Never delete users/admins/orders/menu/CRM/analytics/public website data unless explicitly requested.
- Prefer idempotent migrations.
- Use CREATE TABLE IF NOT EXISTS for new tables.
- For existing tables, repair missing columns and indexes safely.
- Do not rely on CREATE TABLE IF NOT EXISTS to repair old tables.
- Preserve old legacy columns when needed for compatibility.
- Add sync hooks instead of breaking old code.

## Refactor Rules

- Separate schema repair from rendering.
- Separate module configuration from generic CRUD.
- Keep page wrappers thin.
- Use module-specific hooks for module-specific behavior.
- Avoid mixing UI, validation, business logic, and SQL in one file.
- Avoid duplicate bootstrap and try/catch logic.
- Do not hardcode business rules inside views.

## Final Response Format

After completing a task, report:

### Summary
What changed.

### Files Changed
List changed files.

### Files Created
List new files.

### Database Changes
List tables/columns/indexes/migrations.

### Validation
Mention syntax checks, tests, and manual checks.

### Risks / Notes
Mention incomplete, risky, or manually verified parts.

### Next Step
One practical next action.
