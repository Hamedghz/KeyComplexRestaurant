# Security Rules

## Secrets

Never expose:

- API keys
- Passwords
- Tokens
- Private keys
- Session IDs
- Cookies
- Database credentials
- SMTP credentials
- SMS provider credentials
- Internal server paths in public UI

Never commit real secrets into:

- .env
- PHP config files
- setup scripts
- markdown docs
- test fixtures
- migration files
- seed files

Use environment variables or existing secure config patterns.

## Authentication

Every private admin page must:

- Require admin bootstrap.
- Require authenticated admin/user.
- Use the existing auth/adminGuard pattern.
- Redirect unauthenticated users safely.
- Avoid leaking whether a resource exists to unauthorized users.

## Authorization

Every sensitive action must check:

- Is the user authenticated?
- Does the user have the required permission?
- Is the target resource allowed for this user?
- Is this action scoped to the user's role/team/department where applicable?

Sensitive actions include:

- Create
- Update
- Delete
- Import
- Export
- Assign
- Approve
- Reject
- Calculate
- Review
- Seed
- Migration
- Schema repair
- Settings update

## CSRF

Every write action must use CSRF protection:

- POST create
- POST update
- POST delete
- POST import
- POST export request if it changes state
- POST settings update
- POST assignment
- POST approval/rejection

Do not add write endpoints without CSRF.

## SQL Safety

- Use prepared statements or existing safe DB helpers.
- Never concatenate raw user input into SQL.
- Validate dynamic table, column, sort, and filter names against allowlists.
- Cast IDs to integers.
- Validate date ranges.
- Paginate large result sets.
- Avoid SELECT * in large reports.

## XSS Safety

- Escape all user-controlled output.
- Escape titles, names, descriptions, notes, comments, JSON previews, and uploaded file names.
- Do not render raw HTML from database unless explicitly sanitized.
- Validate rich text input if enabled.

## File Upload Safety

- Validate file type.
- Validate file size.
- Generate safe filenames.
- Store outside public root when possible.
- Never execute uploaded files.
- Do not trust file extension alone.
- Do not display uploaded SVG as inline HTML unless sanitized.

## Error Handling

User-facing errors must be:

- Simple
- Persian where UI is Persian
- Non-technical
- Actionable

Internal logs may include context but must not include secrets.

Never show:

- Raw SQL error
- Stack trace
- Database credentials
- Full server path
- Full request payload containing sensitive data

## Audit Logging

Log important actions safely:

- admin/user id
- action
- module
- entity type
- entity id
- safe summary
- timestamp
- IP if existing pattern supports it

Do not log:

- passwords
- tokens
- API keys
- full cookies
- raw uploaded file contents
- raw private config

## Destructive Operations

Do not run:

- DROP TABLE
- DROP COLUMN
- TRUNCATE
- global DELETE
- destructive ALTER

unless the user explicitly requests it and the scope is clearly limited.

Prefer:

- archive
- backup
- soft delete
- status = archived
- idempotent repair
