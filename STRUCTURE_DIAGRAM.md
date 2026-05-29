# Current Deployment Note

This project now targets a single-root hosting layout. Runtime files are loaded directly from the server root, `index.php` is the entry point, and `install.php` generates `config.php` plus `installed.lock`.
