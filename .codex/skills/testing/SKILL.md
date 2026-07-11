---
name: testing
description: Run or design validation checks for PHP syntax, admin pages, database migrations, and manual QA.
---

# Testing Skill

## Automated Checks

When PHP is available:

- php -l for changed PHP files
- Existing test runner if present
- Migration dry-run if project supports it
- Seed idempotency check if project supports it

When PHP is not available:

- State that syntax checks were skipped because PHP is not in PATH.
- Provide exact commands for the user to run.

## Manual Admin QA

For changed admin pages:

1. Open page.
2. Confirm access control.
3. Confirm list loads.
4. Confirm filters/search.
5. Confirm add form if create is enabled.
6. Confirm edit form.
7. Confirm delete/archive behavior if enabled.
8. Confirm validation errors.
9. Confirm success messages.
10. Confirm no PHP warning.
11. Confirm no raw SQL error.
12. Confirm no debug logs in UI.

## Final Report

Always include:

- Checks run
- Checks skipped
- Reason for skipped checks
- Manual checks still required
