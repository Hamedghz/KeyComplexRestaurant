# Installation

Deploy the project files directly into the domain server root. There is no wrapper web-root directory dependency.

## Steps

1. Copy all tracked project files into the server root.
2. Make sure PHP can write `config.php`, `installed.lock`, `uploads/`, and `storage/`.
3. Browse to `/install.php`.
4. Enter database host, database name, username, and password.
5. Submit the form. The installer will verify the DB connection, execute `database/schema.sql`, provision the default admin, write `config.php`, and create `installed.lock`.
6. Browse to `/index.php`.

## Security notes

- `config.php` is generated and ignored by Git.
- `installed.lock` prevents running the installer again.
- Direct access to `config.php`, `installed.lock`, `core/`, `database/`, and `storage/` is blocked by `.htaccess` and PHP guards.
- Change the default admin password immediately after first login.
