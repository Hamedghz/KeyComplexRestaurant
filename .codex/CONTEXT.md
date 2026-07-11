# Project Context

## Project

KEY Restaurant & Coffeehouse admin panel.

## Stack

- Backend: PHP 8.1 classic PHP
- Database: MySQL/MariaDB
- UI: Persian RTL admin panel
- Hosting: DirectAdmin/cPanel compatible
- Framework: No Laravel
- Build: No Node build
- Dependencies: Avoid Composer unless already present

## Main Areas

- Admin dashboard
- Users/admins/roles/permissions
- Menu categories and menu items
- Hero banners
- Matches and predictions
- CRM and customer follow-up
- Analytics and visitor tracking
- HR / Evaluation / Tests / KPI / Duties / Checklist / Planner / OKR
- System update / migrations / seeds

## Current Priorities

- Safe refactor
- Schema repair before rendering
- Thin admin page wrappers
- Modular admin CRUD configuration
- Persian RTL UI consistency
- Production-safe migrations and seeds
- Clear validation and permission checks

## Constraints

- Preserve current architecture.
- Do not rewrite into Laravel.
- Do not add Node/SPA.
- Do not break public pages.
- Do not break admin login.
- Do not destroy production data.
