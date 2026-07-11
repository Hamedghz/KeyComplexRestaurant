# Project Rules

## General Rules

- Keep code readable, modular, and maintainable.
- Respect the existing classic PHP architecture.
- Do not introduce unnecessary abstraction.
- Do not duplicate logic.
- Do not mix UI, business logic, database access, validation, and permissions unnecessarily.
- Prefer clear names over clever names.
- Prefer explicit validation over hidden assumptions.
- Use Persian RTL UI patterns where the admin panel is Persian.
- Do not create fake data.
- Do not create placeholder features that look functional but are not connected to real data.

## Architecture Rules

Preferred direction:

UI/Page
→ Bootstrap/Auth
→ Controller/Handler
→ Validation
→ Service/Business Logic
→ Repository/Data Access
→ Database
→ View/Response

Avoid:

- SQL scattered inside unrelated views.
- Permission checks duplicated inconsistently.
- Schema repair inside page rendering.
- Hardcoded page-specific logic inside generic CRUD.
- Large files that contain multiple unrelated modules.
- Direct destructive SQL in page files.

## Admin Module Rules

For admin CRUD modules:

- Page wrappers should be thin.
- Module config should be separated.
- Schema repair must run before form rendering.
- Forms must match real database columns.
- Missing columns must be repaired safely.
- Module-specific logic must use before_save and after_save hooks.
- List pages must support empty states.
- Errors must be Persian, clean, and non-technical.

## Database Rules

- Do not drop tables.
- Do not drop columns.
- Do not truncate tables.
- Do not overwrite production data.
- Do not delete unrelated module data.
- Make migrations idempotent.
- Use safe defaults.
- Add nullable columns first when needed.
- Add indexes only when justified.
- Keep seeds separate from schema.
- Seeds must be repeatable/idempotent.
- Existing data must be preserved.
- Legacy compatibility fields should remain until safely migrated.

## PHP Rules

- Use PHP 8.1-compatible syntax.
- Keep functions focused.
- Use strict comparison where practical.
- Validate input before use.
- Escape output with htmlspecialchars or existing project helper.
- Avoid raw $_POST and $_GET usage without validation.
- Avoid raw SQL interpolation.
- Avoid fatal errors in UI.
- Do not show stack traces to users.
- Reuse existing bootstrap, auth, CSRF, and DB helpers.

## Security Rules

- Use permission checks before sensitive actions.
- Use CSRF tokens for POST/PUT/DELETE-like actions.
- Validate IDs as integers.
- Validate dates.
- Validate JSON before saving.
- Validate uploaded files.
- Do not log secrets.
- Do not expose raw server paths.
- Do not expose raw SQL errors.
- Prevent IDOR by checking target resource access.

## UI Rules

- Keep Persian RTL consistency.
- Keep forms simple.
- Use clear labels.
- Show meaningful empty states.
- Avoid visual clutter.
- Avoid debug logs inside page body.
- Use consistent buttons and tables.
- Preserve responsive behavior where present.
- Do not change the whole theme unless requested.

## Testing Rules

- Run php -l on changed PHP files when PHP is available.
- Run available tests if present.
- If PHP is not available in PATH, say syntax checks were skipped.
- Do not claim a test passed if it was not run.
- Include manual verification steps for admin pages.

## Do Not Touch Without Explicit Approval

- Authentication flow
- Admin login
- User deletion logic
- Permission core
- Production database destructive cleanup
- Public website routing
- Payment/financial logic
- Orders
- CRM customer data
- Analytics event data
- Media/upload deletion
- Hosting/deployment scripts
