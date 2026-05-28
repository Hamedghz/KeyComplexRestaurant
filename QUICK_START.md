# ⚡ KEY Restaurant - Quick Start Guide

## 🚀 Get Running in 10 Minutes

### Step 1: Upload Files (2 min)
```bash
# Upload these folders to your hosting:
/public_html/  → Your web root (e.g., public_html/)
/config/       → Outside web root
/core/         → Outside web root
/database/     → Outside web root
/storage/      → Outside web root
```

### Step 2: Create Database (1 min)
```sql
-- In phpMyAdmin or MySQL:
CREATE DATABASE key_restaurant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 3: Import Schema (1 min)
```bash
# In phpMyAdmin:
1. Select "key_restaurant" database
2. Click "Import" tab
3. Choose file: database/schema.sql
4. Click "Go"
```

### Step 4: Configure Database (1 min)
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'key_restaurant');
define('DB_USER', 'your_username');    // ← Change this
define('DB_PASS', 'your_password');    // ← Change this
```

### Step 5: Set Permissions (1 min)
```bash
chmod 755 public_html/uploads -R
chmod 755 storage -R
```

### Step 6: Test Frontend (1 min)
Visit: `https://yourdomain.com`

You should see:
- ✅ WebGL hero animation
- ✅ Lotus logo
- ✅ Menu section

### Step 7: Login to Admin (1 min)
Visit: `https://yourdomain.com/admin`

```
Username: admin
Password: admin123
```

### Step 8: Change Password (1 min)
**IMPORTANT:** Change the default password immediately!

### Step 9: Add Content (1 min)
1. Go to Settings → Upload logo
2. Go to Menu Items → Add your first item
3. Upload an image

### Step 10: Test Order (1 min)
1. Visit homepage
2. Click "سفارش آنلاین"
3. View menu
4. Check admin panel for order

---

## ✅ You're Done!

Your restaurant website is now live! 🎉

## 🎯 Next Steps

### Essential
- [ ] Change admin password
- [ ] Upload restaurant logo
- [ ] Add all menu categories
- [ ] Add menu items with images
- [ ] Configure contact information
- [ ] Set opening hours
- [ ] Add social media links

### Optional
- [ ] Customize colors
- [ ] Adjust WebGL settings
- [ ] Create QR codes for tables
- [ ] Train staff on admin panel
- [ ] Set up backups

## 🆘 Troubleshooting

### Can't see homepage?
- Check if files are in correct directory
- Verify .htaccess exists
- Check Apache mod_rewrite is enabled

### Database connection error?
- Verify credentials in config/database.php
- Check database exists
- Test MySQL connection

### Can't login to admin?
- Username: `admin`
- Password: `admin123`
- Check database has admin user

### Images not showing?
- Create uploads directory: `mkdir public_html/uploads`
- Set permissions: `chmod 755 public_html/uploads -R`

## 📞 Need Help?

Check these files:
- `INSTALLATION.md` - Detailed setup
- `PROJECT_STRUCTURE.md` - File organization
- `DEPLOYMENT_CHECKLIST.md` - Complete checklist
- `README.md` - Full documentation

---

**🎉 Enjoy your new restaurant website!**
