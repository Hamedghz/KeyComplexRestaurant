---
name: php-admin
description: Work on classic PHP admin panel pages, routes, includes, forms, CRUD, permissions, CSRF, and Persian RTL screens.
---

# PHP Admin Skill

## Rules

- Use existing admin bootstrap.
- Use existing auth/adminGuard pattern.
- Use existing CSRF helpers.
- Use existing DB helpers.
- Keep page wrappers thin.
- Keep Persian RTL UI.
- Escape output.
- Validate input.
- Use clean Persian messages.
- Do not expose raw errors.
- Do not duplicate CRUD logic.
- Do not mix schema repair with rendering.

## Page Pattern

1. require bootstrap/includes
2. guard user
3. check permission
4. repair required schema if applicable
5. handle POST with CSRF
6. validate input
7. call service/repository
8. render clean UI
9. log important action

## Validation

Always validate:

- id
- status
- dates
- numeric values
- JSON
- uploaded files
- foreign keys
- enum-like fields
