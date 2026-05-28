# 🚀 KEY Restaurant - Deployment Checklist

## Pre-Deployment

### ✅ Server Requirements
- [ ] PHP 8.0+ installed
- [ ] MySQL 5.7+ or MariaDB 10.3+ available
- [ ] Apache with mod_rewrite enabled
- [ ] Minimum 50MB disk space
- [ ] SSL certificate (recommended)

### ✅ File Preparation
- [ ] All files uploaded to correct directories
- [ ] `public_html/` is web root
- [ ] `config/`, `core/`, `database/`, `storage/` outside web root
- [ ] `.htaccess` file present in `public_html/`

## Database Setup

### ✅ Database Creation
- [ ] Database created: `key_restaurant`
- [ ] Character set: `utf8mb4`
- [ ] Collation: `utf8mb4_unicode_ci`
- [ ] Database user created with full privileges
- [ ] Schema imported from `database/schema.sql`
- [ ] Default admin account exists

### ✅ Database Configuration
- [ ] `config/database.php` updated with credentials
- [ ] Database connection tested
- [ ] PDO connection working

## Configuration

### ✅ Application Settings
- [ ] `config/config.php` BASE_URL updated
- [ ] ADMIN_URL configured
- [ ] API_URL configured
- [ ] Timezone set correctly
- [ ] Error reporting configured for production

### ✅ File Permissions
```bash
# Execute these commands
chmod 755 public_html/
chmod 755 public_html/uploads/
chmod 755 storage/
chmod 644 config/*.php
chmod 644 public_html/.htaccess
```

- [ ] Web root: 755
- [ ] Upload directories: 755
- [ ] Storage directory: 755
- [ ] Config files: 644
- [ ] .htaccess: 644

### ✅ Upload Directories
```bash
# Create these directories
mkdir -p public_html/uploads/menu
mkdir -p public_html/uploads/logo
mkdir -p public_html/uploads/hero
mkdir -p public_html/uploads/textures
mkdir -p public_html/uploads/models
mkdir -p storage/logs
mkdir -p storage/cache
```

- [ ] `uploads/menu/` created
- [ ] `uploads/logo/` created
- [ ] `uploads/hero/` created
- [ ] `uploads/textures/` created
- [ ] `uploads/models/` created
- [ ] `storage/logs/` created
- [ ] `storage/cache/` created

## Security

### ✅ Admin Security
- [ ] Default admin password changed
- [ ] Strong password policy enforced
- [ ] Admin email updated
- [ ] Session timeout configured

### ✅ Application Security
- [ ] CSRF protection enabled
- [ ] SQL injection prevention (PDO)
- [ ] XSS sanitization active
- [ ] File upload validation working
- [ ] HTTPS enabled (recommended)
- [ ] Security headers configured

### ✅ .htaccess Security
- [ ] Directory browsing disabled
- [ ] Sensitive files protected
- [ ] HTTPS redirect enabled (if SSL available)
- [ ] Security headers active

## Testing

### ✅ Frontend Testing
- [ ] Homepage loads correctly
- [ ] WebGL hero scene renders
- [ ] Lotus logo animates
- [ ] Menu items display
- [ ] Images load properly
- [ ] Social links work
- [ ] Responsive on mobile
- [ ] RTL layout correct

### ✅ Admin Panel Testing
- [ ] Login page accessible
- [ ] Admin login works
- [ ] Dashboard displays statistics
- [ ] Menu management works
- [ ] Order creation works
- [ ] Settings save correctly
- [ ] Media upload works
- [ ] Logout functions

### ✅ API Testing
```bash
# Test these endpoints
curl https://yourdomain.com/api/menu
curl https://yourdomain.com/api/settings
curl -X POST https://yourdomain.com/api/order -d '{...}'
```

- [ ] GET /api/menu returns data
- [ ] GET /api/settings returns config
- [ ] POST /api/order creates order
- [ ] API returns proper JSON
- [ ] CORS headers correct

### ✅ Order Flow Testing
- [ ] Customer can view menu
- [ ] Add items to cart
- [ ] Submit order
- [ ] Order appears in admin
- [ ] Admin can update status
- [ ] Order number generated

## Content Setup

### ✅ Initial Content
- [ ] Restaurant logo uploaded
- [ ] Hero title/subtitle set
- [ ] Contact information added
- [ ] Social media links configured
- [ ] Opening hours set
- [ ] At least 3 menu categories created
- [ ] At least 10 menu items added
- [ ] Menu item images uploaded

### ✅ Settings Configuration
- [ ] Site name (FA & EN)
- [ ] Primary color: #004647
- [ ] Accent color: #D4AF37
- [ ] Phone number
- [ ] Email address
- [ ] Physical address
- [ ] Instagram URL
- [ ] Telegram URL
- [ ] WhatsApp number

### ✅ WebGL Settings
- [ ] Fog intensity: 0.5
- [ ] Bloom intensity: 0.8
- [ ] Animation speed: 1.0

## Performance

### ✅ Optimization
- [ ] Images compressed (< 500KB each)
- [ ] Gzip compression enabled
- [ ] Browser caching configured
- [ ] OpCache enabled (if available)
- [ ] Database queries optimized

### ✅ Performance Testing
- [ ] Page load time < 3 seconds
- [ ] WebGL runs at 60 FPS
- [ ] Mobile performance acceptable
- [ ] Lighthouse score > 80

## Browser Testing

### ✅ Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### ✅ Mobile Browsers
- [ ] iOS Safari
- [ ] Chrome Mobile
- [ ] Samsung Internet
- [ ] Firefox Mobile

### ✅ Device Testing
- [ ] iPhone (various sizes)
- [ ] Android phones
- [ ] iPad / tablets
- [ ] Desktop (1920x1080)

## Documentation

### ✅ Documentation Complete
- [ ] README.md reviewed
- [ ] INSTALLATION.md accurate
- [ ] PROJECT_STRUCTURE.md current
- [ ] API endpoints documented
- [ ] Admin credentials documented

## Backup

### ✅ Backup Strategy
- [ ] Database backup created
- [ ] Files backup created
- [ ] Backup schedule established
- [ ] Restore procedure tested

## Monitoring

### ✅ Monitoring Setup
- [ ] Error logging enabled
- [ ] Log rotation configured
- [ ] Uptime monitoring (optional)
- [ ] Analytics installed (optional)

## Launch

### ✅ Pre-Launch
- [ ] All checklist items completed
- [ ] Stakeholders notified
- [ ] Support plan ready
- [ ] Rollback plan prepared

### ✅ Launch Day
- [ ] DNS configured (if needed)
- [ ] SSL certificate active
- [ ] Site accessible
- [ ] Admin panel accessible
- [ ] Test order placed
- [ ] QR codes generated
- [ ] Staff trained

### ✅ Post-Launch
- [ ] Monitor error logs
- [ ] Check order flow
- [ ] Verify email notifications
- [ ] Test from customer perspective
- [ ] Gather initial feedback

## Maintenance

### ✅ Regular Tasks
- [ ] Daily: Check orders
- [ ] Daily: Review error logs
- [ ] Weekly: Database backup
- [ ] Weekly: Update menu items
- [ ] Monthly: Security updates
- [ ] Monthly: Performance review

## Support Contacts

**Technical Support**
- Server: [Hosting Provider]
- Database: [Database Admin]
- Developer: [Your Contact]

**Emergency Contacts**
- Admin: [Admin Phone]
- Technical: [Tech Phone]
- Manager: [Manager Phone]

## Notes

### Known Issues
- [ ] Document any known issues
- [ ] Workarounds documented
- [ ] Fix timeline established

### Future Enhancements
- [ ] Payment gateway integration
- [ ] SMS notifications
- [ ] Mobile app (PWA)
- [ ] Table reservations
- [ ] Inventory management

---

## ✅ Final Sign-Off

**Deployment Date:** _______________

**Deployed By:** _______________

**Verified By:** _______________

**Status:** ⬜ Ready to Launch | ⬜ Launched | ⬜ Issues Found

**Notes:**
_____________________________________________
_____________________________________________
_____________________________________________

---

**🎉 Congratulations on your deployment!**

*KEY Restaurant & Coffeehouse is now live!*
