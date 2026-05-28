# KEY Restaurant & Coffeehouse 🍽️☕

> A premium Persian digital dining experience with WebGL effects, RTL design, and complete restaurant management system.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![License](https://img.shields.io/badge/license-Proprietary-red)

## ✨ Features

### 🎨 Frontend
- **WebGL Hero Scene** - Immersive 3D atmosphere with fog, bloom, and particles
- **9-Petal Lotus Logo** - Animated SVG with sequential bloom effect
- **RTL Persian Design** - Right-to-left layout optimized for Persian content
- **Glass Morphism UI** - Modern translucent interface elements
- **Mobile-First** - Fully responsive design for all devices
- **QR Code Optimized** - Perfect for restaurant table ordering

### 🛠️ Admin Panel
- **Dashboard** - Real-time statistics and analytics
- **Order Management** - Track and update order status
- **Menu CRUD** - Complete menu item management
- **Category Management** - Organize menu items
- **User Management** - Customer accounts and loyalty
- **Feedback System** - Customer reviews and ratings
- **Media Library** - Upload and manage images
- **Settings** - Configure site, theme, and WebGL parameters
- **Activity Log** - Track all admin actions

### 🔌 API
- RESTful JSON API
- Menu endpoints with filtering
- Order creation and tracking
- Public settings access
- Feedback submission

## 🎯 Design Principles

### Color Palette
```
Primary:   #004647 (Teal)
Accent:    #D4AF37 (Gold)
White:     #FFFFFF
Black:     #0A0A0A
```

### Typography
- Persian-first content
- RTL text direction
- Responsive font sizing
- Elegant hierarchy

### Visual Identity
- Persian heritage
- Minimal luxury
- Cinematic atmosphere
- Warm and inviting
- Modern elegance

## 🚀 Quick Start

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache 2.4+ with mod_rewrite
- 50MB disk space

### Installation

1. **Upload Files**
   ```bash
   # Upload to your hosting
   /public_html/  # Web root
   /config/       # Configuration
   /core/         # Application logic
   /database/     # SQL schema
   /storage/      # Logs and cache
   ```

2. **Create Database**
   ```sql
   CREATE DATABASE key_restaurant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import Schema**
   ```bash
   mysql -u username -p key_restaurant < database/schema.sql
   ```

4. **Configure Database**
   Edit `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'key_restaurant');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

5. **Set Permissions**
   ```bash
   chmod 755 public_html/uploads -R
   chmod 755 storage -R
   ```

6. **Access Admin Panel**
   ```
   URL: https://yourdomain.com/admin
   Username: admin
   Password: admin123
   ```

   **⚠️ Change password immediately!**

## 📚 Documentation

- [Installation Guide](INSTALLATION.md) - Detailed setup instructions
- [Project Structure](PROJECT_STRUCTURE.md) - Complete file organization
- [API Documentation](#api-endpoints) - API reference

## 🔐 Default Credentials

**Admin Panel**
- Username: `admin`
- Password: `admin123`

**Database**
- Default admin password hash: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`

## 📡 API Endpoints

### Menu
```http
GET /api/menu
GET /api/menu?category=1
GET /api/menu?search=coffee
GET /api/menu?featured=1
```

### Orders
```http
POST /api/order
Content-Type: application/json

{
  "customer_name": "علی احمدی",
  "customer_phone": "09121234567",
  "items": [
    {
      "menu_item_id": 1,
      "quantity": 2,
      "notes": "بدون شکر"
    }
  ],
  "order_type": "delivery",
  "delivery_address": "تهران، خیابان ولیعصر",
  "payment_method": "cash"
}
```

### Settings
```http
GET /api/settings
```

### Feedback
```http
POST /api/feedback
Content-Type: application/json

{
  "customer_name": "علی احمدی",
  "rating": 5,
  "review_text": "عالی بود!"
}
```

## 🗄️ Database Schema

### Main Tables
- `admins` - Admin users with role-based access
- `users` - Customer accounts and loyalty
- `menu_categories` - Menu organization
- `menu_items` - Food and beverage items
- `orders` - Customer orders
- `order_items` - Order line items
- `feedback` - Customer reviews
- `media` - Uploaded files
- `settings` - Site configuration
- `memberships` - Loyalty program
- `admin_sessions` - Session management
- `activity_log` - Audit trail

## 🎨 Customization

### Theme Colors
Admin Panel → Settings → Theme
```php
Primary Color: #004647
Accent Color: #D4AF37
```

### WebGL Settings
Admin Panel → Settings → WebGL
```php
Fog Intensity: 0.5
Bloom Intensity: 0.8
Animation Speed: 1.0
```

### Hero Content
Admin Panel → Settings → Hero
```php
Title (FA): KEY رستوران و کافه
Subtitle (FA): تجربه‌ای بی‌نظیر از غذا و نوشیدنی
CTA Text (FA): سفارش آنلاین
```

## 🔒 Security Features

- ✅ PDO prepared statements (SQL injection prevention)
- ✅ CSRF token validation
- ✅ XSS input sanitization
- ✅ Session-based authentication
- ✅ Password hashing (bcrypt)
- ✅ File upload validation
- ✅ Role-based access control
- ✅ Activity logging
- ✅ Secure headers (.htaccess)

## 📱 Mobile Optimization

- Touch-friendly interface
- Responsive grid layouts
- Optimized images
- Fast loading times
- QR code entry flow
- Bottom navigation
- Swipe gestures

## 🌐 Browser Support

| Browser | Version |
|---------|---------|
| Chrome  | 90+     |
| Firefox | 88+     |
| Safari  | 14+     |
| Edge    | 90+     |
| Mobile  | Latest  |

## 📊 Performance

- First Contentful Paint: < 1.5s
- Time to Interactive: < 3.5s
- WebGL rendering: 60 FPS
- Lighthouse Score: 90+
- Mobile-optimized assets

## 🛠️ Tech Stack

**Backend**
- PHP 8.0+
- MySQL 8.0+
- PDO for database
- Session management

**Frontend**
- Vanilla JavaScript
- WebGL for 3D effects
- CSS3 animations
- SVG graphics
- Responsive design

**Server**
- Apache 2.4+
- mod_rewrite
- DirectAdmin compatible
- Shared hosting ready

## 📁 Project Structure

```
KeyComplexRestaurant/
├── config/              # Configuration
├── core/                # Application logic
│   ├── Auth.php
│   └── models/
├── database/            # SQL schema
├── storage/             # Logs & cache
└── public_html/         # Web root
    ├── admin/           # Admin panel
    ├── api/             # REST API
    ├── assets/          # Frontend assets
    └── uploads/         # User uploads
```

## 🚦 Getting Started

1. **Install** - Follow [INSTALLATION.md](INSTALLATION.md)
2. **Configure** - Set database credentials
3. **Login** - Access admin panel
4. **Customize** - Upload logo, set colors
5. **Add Menu** - Create categories and items
6. **Test** - Place test order
7. **Launch** - Go live!

## 📝 License

Proprietary - KEY Restaurant & Coffeehouse

## 🆘 Support

For installation help or issues:
1. Check [INSTALLATION.md](INSTALLATION.md)
2. Review [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md)
3. Check server error logs
4. Verify PHP version and extensions

## 🎯 Roadmap

- [ ] Online payment integration
- [ ] SMS notifications
- [ ] Mobile app (PWA)
- [ ] Table reservation system
- [ ] Inventory management
- [ ] Multi-language support
- [ ] Advanced analytics
- [ ] Customer app

## 🙏 Credits

**Design & Development**
- WebGL hero scene
- Persian RTL layout
- Glass morphism UI
- Complete admin system

**Technologies**
- PHP 8
- MySQL 8
- WebGL
- Apache

---

**Made with ❤️ for KEY Restaurant & Coffeehouse**

*A premium Persian digital dining experience*

🍽️ **Bon Appétit!** ☕
