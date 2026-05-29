# KEY Restaurant & Coffeehouse

The project is prepared for single-root shared hosting: deploy the repository contents directly into the server root, with `index.php` as the public entry point.

## Deployment

1. Upload the project files directly into the server root directory for the domain.
2. Open `/install.php` in the browser.
3. Enter the database host, database name, username, and password from the hosting panel.
4. The installer validates the database connection, executes `database/*.sql`, writes `config.php`, and creates `installed.lock`.
5. After installation, open `/index.php` or `/`.

## Runtime configuration

Runtime code reads database credentials only from the generated root `config.php`. Do not commit real credentials. For local development, run the installer with the local environment option or use `config.example.php` as the expected config shape.

## Important paths

```text
index.php              Main site entry
install.php            One-time installer
config.php             Generated runtime config (ignored by Git)
installed.lock         Installer lock (ignored by Git)
assets/                CSS, JS, images, fonts
uploads/               Runtime uploads
core/                  Application bootstrap, services, and models
database/              SQL schemas used by installer
api/                   API endpoints
admin/                 Admin panel
```
