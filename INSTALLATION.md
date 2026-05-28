# KEY Restaurant & Coffeehouse - Installation Guide

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite enabled
- DirectAdmin or cPanel hosting (or any shared hosting)
- Minimum 50MB disk space

## 🚀 Installation Steps

### 1. Upload Files

Upload the entire project to your DirectAdmin hosting:

```
/home/username/domains/yourdomain.com/
├── public_html/          (Upload contents here)
├── config/
├── core/
├── database/
└── storage/
```

**Important:** Only the `public_html` folder should be web-accessible.

### 2. Database Setup

#### Option A: Using phpMyAdmin

1. Login to phpMyAdmin
2. Create a new database named `key_restaurant`
3. Select the database
4. Click "Import" tab
5. Choose file: `database/schema.sql`
6. Click "Go" to import

#### Option B: Using MySQL Command Line

```bash
mysql -u username -p
CREATE DATABASE key_restaurant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE key_restaurant;
SOURCE /path/to/database/schema.sql;
EXIT;
```

### 3. Configure Database Connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'key_restaurant');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

### 4. Configure Application Settings

Edit `config/config.php`:

```php
// Update these URLs to match your domain
define('BASE_URL', 'https://yourdomain.com');
define('ADMIN_URL', BASE_URL . '/admin');
define('API_URL', BASE_URL . '/api');
```

### 5. Set File Permissions

```bash
chmod 755 public_html/
chmod 755 public_html/uploads/
chmod 755 storage/
chmod 644 config/*.php
chmod 644 public_html/.htaccess
```

### 6. Update .htaccess

Edit `public_html/.htaccess` and update the RewriteBase:

```apache
RewriteBase /
```

If your site is in a subdirectory:
```apache
RewriteBase /subdirectory/
```

### 7. Create Upload Directories

```bash
mkdir -p public_html/uploads/menu
mkdir -p public_html/uploads/logo
mkdir -p public_html/uploads/hero
mkdir -p public_html/uploads/textures
chmod 755 public_html/uploads -R
```

## 🔐 Default Admin Credentials

**URL:** `https://yourdomain.com/admin`

**Username:** `admin`  
**Password:** `admin123`

**⚠️ IMPORTANT:** Change these credentials immediately after first login!

## 🎨 Customization

### Change Colors

Edit settings in Admin Panel → Settings → Theme:
- Primary Color: `#004647` (Teal)
- Accent Color: `#D4AF37` (Gold)

### Upload Logo

Admin Panel → Media → Upload Logo

### Configure WebGL Settings

Admin Panel → Settings → WebGL:
- Fog Intensity: 0.5
- Bloom Intensity: 0.8
- Animation Speed: 1.0

### Add Menu Items

1. Go to Admin Panel → Menu Items
2. Click "Add New Item"
3. Fill in details (Persian and English)
4. Upload image
5. Set price and category
6. Save

## 📱 Features

### Frontend
- ✅ WebGL animated hero section
- ✅ 9-petal lotus logo animation
- ✅ RTL Persian layout
- ✅ Mobile-first responsive design
- ✅ Glass morphism UI
- ✅ Featured menu display
- ✅ Social media links

### Admin Panel
- ✅ Dashboard with statistics
- ✅ Order management
- ✅ Menu item CRUD
- ✅ Category management
- ✅ User management
- ✅ Feedback/reviews
- ✅ Media library
- ✅ Settings management
- ✅ Activity logging

### API Endpoints
- `GET /api/menu` - Get all menu items
- `GET /api/menu?category=1` - Filter by category
- `GET /api/menu?search=coffee` - Search items
- `GET /api/settings` - Get public settings
- `POST /api/order` - Create new order
- `POST /api/feedback` - Submit feedback

## 🔧 Troubleshooting

### Database Connection Error
- Check database credentials in `config/database.php`
- Ensure MySQL service is running
- Verify database exists and user has permissions

### 404 Errors on Admin Panel
- Check `.htaccess` file exists in `public_html/`
- Verify `mod_rewrite` is enabled in Apache
- Check file permissions

### Images Not Displaying
- Verify upload directories exist and are writable
- Check file permissions (755 for directories, 644 for files)
- Ensure images are in correct path: `public_html/uploads/`

### WebGL Not Working
- Check browser console for errors
- Ensure browser supports WebGL
- Try different browser (Chrome, Firefox recommended)

## 📊 Database Structure

### Main Tables
- `admins` - Admin users
- `users` - Customer accounts
- `menu_categories` - Menu categories
- `menu_items` - Menu items
- `orders` - Customer orders
- `order_items` - Order line items
- `feedback` - Customer reviews
- `media` - Uploaded files
- `settings` - Site configuration
- `memberships` - Loyalty program
- `admin_sessions` - Admin sessions
- `activity_log` - Admin activity

## 🔒 Security Recommendations

1. **Change Default Credentials**
   ```sql
   UPDATE admins SET password = '$2y$10$newhashedpassword' WHERE username = 'admin';
   ```

2. **Enable HTTPS**
   - Get SSL certificate (Let's Encrypt recommended)
   - Uncomment HTTPS redirect in `.htaccess`

3. **Secure Database**
   - Use strong database password
   - Restrict database user permissions
   - Regular backups

4. **File Permissions**
   ```bash
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   chmod 600 config/database.php
   ```

5. **Disable Error Display in Production**
   Edit `config/config.php`:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

## 📈 Performance Optimization

1. **Enable Caching**
   - Browser caching (already configured in `.htaccess`)
   - OpCache for PHP
   - MySQL query cache

2. **Optimize Images**
   - Use WebP format
   - Compress images before upload
   - Recommended max size: 500KB per image

3. **Enable Compression**
   - Gzip compression (configured in `.htaccess`)

## 🆘 Support

For issues or questions:
1. Check this documentation
2. Review error logs: `storage/logs/`
3. Check Apache error logs
4. Verify PHP version and extensions

## 📝 License

Proprietary - KEY Restaurant & Coffeehouse

## 🎯 Next Steps After Installation

1. ✅ Login to admin panel
2. ✅ Change default password
3. ✅ Upload restaurant logo
4. ✅ Configure site settings
5. ✅ Add menu categories
6. ✅ Add menu items with images
7. ✅ Test order placement
8. ✅ Configure social media links
9. ✅ Set opening hours
10. ✅ Launch website!

---

**Congratulations! Your KEY Restaurant & Coffeehouse website is ready! 🎉**
