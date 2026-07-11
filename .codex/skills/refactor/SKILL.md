---
name: refactor
description: Improve structure, readability, modularity, and maintainability without changing behavior.
---

# Refactor Skill

## Rules

- Preserve behavior.
- Preserve public routes unless explicitly changed.
- Preserve admin login.
- Preserve database data.
- Do not rewrite the whole project.
- Make small, testable changes.
- Extract duplicated logic.
- Keep public interfaces stable.
- Avoid unnecessary dependencies.
- Keep compatibility wrappers where needed.

## Refactor Process

1. Identify duplicated or tangled logic.
2. Identify the safest boundary.
3. Separate bootstrap/auth from page logic.
4. Separate validation from business logic.
5. Separate database access from rendering.
6. Move reusable logic into service/helper files.
7. Keep old route wrappers temporarily if needed.
8. Validate with php -l where available.
9. Report changed files and risks.

## Preferred Admin Refactor Pattern

Page wrapper
→ adminGuard
→ schema repair
→ module config normalization
→ handler/service
→ renderer
→ clean Persian response
