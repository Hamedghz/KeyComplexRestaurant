# Deployment Checklist

- [ ] Files are placed directly in the server root.
- [ ] `index.php` exists directly in the server root.
- [ ] `install.php` exists directly in the server root for first install.
- [ ] `assets/` and `uploads/` are directly under the server root.
- [ ] No wrapper web-root directory is required.
- [ ] Run `/install.php` once.
- [ ] Confirm `config.php` is generated.
- [ ] Confirm `installed.lock` is generated.
- [ ] Confirm `/index.php` loads without path errors.
- [ ] Confirm `/install.php` returns `Already installed` after installation.
- [ ] Change the default admin password after first login.
