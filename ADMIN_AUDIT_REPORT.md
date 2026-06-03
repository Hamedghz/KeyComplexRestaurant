# KEY Admin Production Audit Report

Generated during the final production implementation pass. This report maps every required admin menu item to a physical PHP route and verifies syntax-level load blockers that commonly cause HTTP 500 errors. Runtime database connectivity still depends on the deployed `config.php` credentials and MySQL server.

## Admin menu and route audit

| Menu item | Route | File present | PHP syntax | MySQL connection path |
|---|---|---:|---|---|
| Dashboard | `admin/dashboard.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| CRM | `admin/crm.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Acquisition Reports | `admin/crm-reports.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Acquisition Sources | `admin/acquisition-sources.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Matches | `admin/matches.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Predictions | `admin/predictions.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Hero Banners | `admin/banners.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Menu Filters | `admin/categories.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Menu Items | `admin/menu-items.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Surveys | `admin/surveys.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Survey Responses | `admin/survey-responses.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Orders | `admin/orders.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Users | `admin/users.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Reviews | `admin/feedback.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Media Library | `admin/media.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Employee Dashboard | `admin/employee-dashboard.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Employee Evaluations | `admin/employee-evaluations.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Employee Performance | `admin/employee-performance.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Settings | `admin/settings.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| KEY Story | `admin/key-story.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Pool Leads | `admin/pool-leads.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Visitor Logs | `admin/analytics.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Traffic Sources | `admin/analytics-traffic-sources.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Live Visitors | `admin/analytics-live.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Geographic Analytics | `admin/analytics-geographic.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Device Analytics | `admin/analytics-device.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| Export Center | `admin/analytics-export.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |
| System Update | `admin/system-update.php` | Yes | pass | Uses `adminGuard()`/`ensureAdminSchema()` or admin CRUD model where applicable |

## AJAX/API endpoint audit

| Endpoint | Purpose | Status |
|---|---|---|
| `api/index.php` | API landing/status router | Present and PHP lint clean |
| `api/menu.php` | Menu data endpoint | Present and PHP lint clean |
| `api/order.php` | Order submission endpoint | Present and PHP lint clean |
| `api/settings.php` | Settings endpoint | Present and PHP lint clean |
| `api/survey-submit.php` | Survey submission endpoint | Present and PHP lint clean |

## Database schema audit

The admin bootstrap now calls `ensureAdminSchema()` from high-risk modules so required tables/columns are created if missing: acquisition sources, social links, KEY story, pool leads, traffic analytics tables, employee evaluation/performance tables, match result fields, prediction filter fields, and user RBAC columns. Optional form values are normalized to NULL in shared CRUD collection logic unless explicitly required by business rules.

## Fixed production blockers in this pass

- Added missing analytics routes referenced by the admin menu: traffic sources, live visitors, geographic analytics, device analytics, and export center.
- Reworked the system updater to compare local and GitHub commits, fetch release/tag metadata, show changelog data, back up files and database, run migrations, clear cache, and rollback on failure.
- Added the sticky administrator header requested by production spec while preserving the existing sidebar navigation.

## Verification commands

- `find . -path ./node_modules -prune -o -name "*.php" -print | sort | xargs -n1 php -l`
- `php -l core/SystemUpdater.php`
- `php -l admin/includes/header.php`
