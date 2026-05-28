# KEY Restaurant & Coffeehouse - Project Structure

## 📁 Complete Directory Tree

```
KeyComplexRestaurant/
│
├── 📄 INSTALLATION.md              # Installation guide
├── 📄 PROJECT_STRUCTURE.md         # This file
├── 📄 README.md                    # Project overview
│
├── 📁 config/                      # Configuration files
│   ├── config.php                  # Application config
│   └── database.php                # Database connection
│
├── 📁 core/                        # Core application logic
│   ├── Auth.php                    # Authentication system
│   │
│   ├── 📁 models/                  # Data models
│   │   ├── Model.php               # Base model class
│   │   ├── MenuItem.php            # Menu item model
│   │   ├── Order.php               # Order model
│   │   ├── Setting.php             # Settings model
│   │   ├── User.php                # User model
│   │   ├── Category.php            # Category model
│   │   └── Feedback.php            # Feedback model
│   │
│   ├── 📁 controllers/             # Controllers (optional MVC)
│   │   ├── MenuController.php
│   │   ├── OrderController.php
│   │   └── AdminController.php
│   │
│   └── 📁 helpers/                 # Helper functions
│       ├── jdate.php               # Persian date converter
│       └── functions.php           # Common functions
│
├── 📁 database/                    # Database files
│   ├── schema.sql                  # Complete database schema
│   └── migrations/                 # Database migrations (optional)
│
├── 📁 storage/                     # Storage directory
│   ├── logs/                       # Application logs
│   ├── cache/                      # Cache files
│   └── sessions/                   # Session files
│
└── 📁 public_html/                 # Web root (publicly accessible)
    │
    ├── 📄 index.php                # Frontend homepage
    ├── 📄 .htaccess                # Apache configuration
    │
    ├── 📁 admin/                   # Admin panel
    │   ├── index.php               # Admin login
    │   ├── dashboard.php           # Admin dashboard
    │   ├── logout.php              # Logout handler
    │   ├── orders.php              # Order management
    │   ├── order-detail.php        # Order details
    │   ├── menu-items.php          # Menu management
    │   ├── menu-item-form.php      # Add/edit menu item
    │   ├── categories.php          # Category management
    │   ├── users.php               # User management
    │   ├── feedback.php            # Feedback management
    │   ├── media.php               # Media library
    │   ├── settings.php            # Settings page
    │   │
    │   └── 📁 includes/            # Admin includes
    │       ├── header.php          # Admin header
    │       └── footer.php          # Admin footer
    │
    ├── 📁 api/                     # REST API
    │   ├── index.php               # API router
    │   ├── menu.php                # Menu endpoints
    │   ├── menu-featured.php       # Featured items
    │   ├── categories.php          # Categories endpoint
    │   ├── order.php               # Order endpoints
    │   ├── settings.php            # Settings endpoint
    │   └── feedback.php            # Feedback endpoint
    │
    ├── 📁 assets/                  # Frontend assets
    │   │
    │   ├── 📁 css/                 # Stylesheets
    │   │   ├── main.css            # Main styles
    │   │   ├── hero.css            # Hero section
    │   │   └── menu.css            # Menu styles
    │   │
    │   ├── 📁 js/                  # JavaScript
    │   │   ├── app.js              # Main app
    │   │   ├── webgl-hero.js       # WebGL scene
    │   │   ├── menu.js             # Menu functionality
    │   │   └── order.js            # Order handling
    │   │
    │   ├── 📁 webgl/               # WebGL resources
    │   │   ├── shaders/            # GLSL shaders
    │   │   │   ├── vertex.glsl
    │   │   │   ├── fragment.glsl
    │   │   │   ├── fog.glsl
    │   │   │   └── bloom.glsl
    │   │   └── textures/           # WebGL textures
    │   │
    │   ├── 📁 fonts/               # Local fonts
    │   │   ├── IRANSans.woff2
    │   │   └── Vazir.woff2
    │   │
    │   ├── 📁 images/              # Static images
    │   │   ├── logo.svg
    │   │   ├── lotus.svg
    │   │   └── placeholder.jpg
    │   │
    │   └── 📁 icons/               # Icon files
    │       └── favicon.ico
    │
    └── 📁 uploads/                 # User uploads
        ├── menu/                   # Menu item images
        ├── logo/                   # Logo files
        ├── hero/                   # Hero backgrounds
        ├── textures/               # WebGL textures
        └── models/                 # 3D models (GLB)
```

## 🎯 Key Files Description

### Configuration
- **config/config.php** - Application settings, paths, constants
- **config/database.php** - Database connection singleton

### Core System
- **core/Auth.php** - Admin authentication, session management
- **core/models/Model.php** - Base model with CRUD operations
- **core/models/MenuItem.php** - Menu item operations
- **core/models/Order.php** - Order processing
- **core/models/Setting.php** - Site settings management

### Frontend
- **public_html/index.php** - Main landing page with WebGL hero
- **public_html/.htaccess** - URL rewriting, security headers

### Admin Panel
- **admin/dashboard.php** - Admin dashboard with statistics
- **admin/orders.php** - Order management interface
- **admin/menu-items.php** - Menu CRUD interface
- **admin/settings.php** - Site configuration

### API
- **api/index.php** - API router
- **api/menu.php** - Menu data endpoints
- **api/order.php** - Order creation endpoint
- **api/settings.php** - Public settings endpoint

## 🔄 Data Flow

### Frontend Order Flow
```
User → index.php → Menu Display → Add to Cart → 
→ api/order.php → Order Model → Database → 
→ Response → Confirmation
```

### Admin Order Management
```
Admin Login → dashboard.php → orders.php → 
→ Order Model → Database → Display Orders → 
→ Update Status → Database
```

### Settings Management
```
Admin → settings.php → Setting Model → 
→ Database → Cache → Frontend API
```

## 🗄️ Database Schema Overview

### Core Tables
- **admins** - Admin users with roles
- **users** - Customer accounts
- **menu_categories** - Menu organization
- **menu_items** - Food/drink items
- **orders** - Customer orders
- **order_items** - Order line items
- **feedback** - Customer reviews
- **media** - File uploads
- **settings** - Configuration
- **memberships** - Loyalty program
- **admin_sessions** - Session tracking
- **activity_log** - Audit trail

## 🎨 Frontend Architecture

### WebGL Hero
```
index.php
  ├── Canvas element
  ├── WebGL context
  ├── Shader programs
  ├── Animation loop
  └── Mouse interaction
```

### UI Components
- Glass morphism panels
- Animated lotus logo (9 petals)
- Smooth scroll navigation
- Responsive grid layout
- RTL typography

## 🔐 Security Layers

1. **Authentication** - Session-based admin auth
2. **CSRF Protection** - Token validation
3. **SQL Injection** - PDO prepared statements
4. **XSS Prevention** - Input sanitization
5. **File Upload** - Type/size validation
6. **Access Control** - Role-based permissions

## 📊 Admin Panel Features

### Dashboard
- Today's orders count
- Revenue statistics
- Pending orders
- Active menu items
- Recent activity

### Order Management
- View all orders
- Filter by status
- Update order status
- View order details
- Print receipts

### Menu Management
- Add/edit/delete items
- Upload images
- Set prices
- Manage availability
- Sort order

### Settings
- Site information
- Theme colors
- WebGL parameters
- Contact details
- Social media links
- Opening hours

## 🚀 Deployment Checklist

- [ ] Upload files to server
- [ ] Import database schema
- [ ] Configure database connection
- [ ] Set file permissions
- [ ] Update .htaccess paths
- [ ] Create upload directories
- [ ] Change admin password
- [ ] Configure site settings
- [ ] Upload logo
- [ ] Add menu items
- [ ] Test order flow
- [ ] Enable HTTPS
- [ ] Test on mobile devices

## 🔧 Customization Points

### Colors
Edit in admin settings or CSS variables:
```css
:root {
    --primary: #004647;
    --accent: #D4AF37;
}
```

### WebGL Scene
Adjust in admin settings:
- Fog intensity
- Bloom intensity
- Animation speed

### Layout
Modify in respective PHP files:
- Hero section: `index.php`
- Menu grid: `index.php`
- Admin layout: `admin/includes/header.php`

## 📱 Responsive Breakpoints

- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

## 🌐 Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## 📈 Performance Targets

- First Contentful Paint: < 1.5s
- Time to Interactive: < 3.5s
- Lighthouse Score: > 90
- WebGL FPS: 60fps

---

**This structure provides a complete, production-ready restaurant management system with modern Persian design and WebGL effects.**
